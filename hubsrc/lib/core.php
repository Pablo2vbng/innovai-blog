<?php
// innovAI Hub · núcleo: almacenamiento, sesión, HTTP
declare(strict_types=1);
const HUB_VERSION = '1.1.0';

function hub_data_dir(): string {
    // Fuera de public_html si es posible: /domains/innovai.es/hub-data
    $outside = dirname(__DIR__, 3) . '/hub-data';
    $inside  = dirname(__DIR__) . '/data';
    foreach ([$outside, $inside] as $d) {
        if (is_dir($d) || @mkdir($d, 0700, true)) {
            if (is_writable($d)) {
                if ($d === $inside && !file_exists("$d/.htaccess")) file_put_contents("$d/.htaccess", "Require all denied\nDeny from all\n");
                return $d;
            }
        }
    }
    throw new RuntimeException('No hay carpeta de datos escribible');
}
function store_get(string $name, $default = []) {
    $f = hub_data_dir() . "/$name.json";
    if (!is_file($f)) return $default;
    $j = json_decode((string)file_get_contents($f), true);
    return $j ?? $default;
}
function store_put(string $name, $value): void {
    $f = hub_data_dir() . "/$name.json";
    $tmp = $f . '.tmp';
    file_put_contents($tmp, json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), LOCK_EX);
    @chmod($tmp, 0600);
    rename($tmp, $f);
}
// ---- Clientes (cada negocio con sus propias cuentas y su propio acceso de solo lectura) ----
/** Ajustes que cambian según el cliente. Con un cliente activo NUNCA se usan los de innovAI. */
const CLIENT_KEYS = ['gsc_site', 'ga4_property', 'gbp_location', 'merchant_id', 'meta_page_id', 'linkedin_org', 'tiktok_user'];
$GLOBALS['HUB_CLIENT'] = null;
function clients_all(): array { $c = store_get('clientes', []); return is_array($c) ? $c : []; }
function client_get(?string $slug): ?array { if (!$slug) return null; $c = clients_all(); return isset($c[$slug]) ? $c[$slug] + ['slug' => $slug] : null; }
function client_use(?string $slug): void {
    if ($slug !== null && $slug !== '' && !client_get($slug)) throw new RuntimeException("Cliente desconocido: $slug");
    $GLOBALS['HUB_CLIENT'] = ($slug === '' ? null : $slug);
}
function client_cur(): ?string { return $GLOBALS['HUB_CLIENT'] ?? null; }
function client_slug(string $name): string {
    $s = strtolower(trim((string)preg_replace('/[^a-z0-9]+/i', '-', iconv('UTF-8', 'ASCII//TRANSLIT', $name) ?: $name), '-'));
    return substr($s, 0, 40) ?: 'cliente';
}
/** Token de Meta de la página del cliente, sacado del acceso de Pablo (que administra la página). */
function client_meta_tok(string $slug, array $admin): array {
    $pid = trim((string)(client_get($slug)['meta_page_id'] ?? ''));
    if ($pid === '') return [];
    $cache = store_get('clientes_meta', []);
    if (!empty($cache[$slug]['page_token']) && ($cache[$slug]['page_id'] ?? '') === $pid) return $cache[$slug];
    if (empty($admin['user_token'])) return [];
    $r = http('GET', 'https://graph.facebook.com/' . cfg('meta_graph_version', 'v23.0') . '/' . rawurlencode($pid),
        ['query' => ['fields' => 'id,name,access_token,instagram_business_account{id,username}', 'access_token' => $admin['user_token']]]);
    $p = http_ok($r, 'Meta página del cliente');
    if (empty($p['access_token'])) throw new RuntimeException('Tu cuenta de Facebook no administra la página de este cliente.');
    $v = ['page_id' => $p['id'], 'page_name' => $p['name'] ?? '', 'page_token' => $p['access_token'],
        'ig_id' => $p['instagram_business_account']['id'] ?? null, 'ig_username' => $p['instagram_business_account']['username'] ?? null, 'at' => date('c')];
    $cache[$slug] = $v; store_put('clientes_meta', $cache);
    return $v;
}
function cfg(string $key, $default = null) {
    if (($s = client_cur()) && in_array($key, CLIENT_KEYS, true)) { $v = client_get($s)[$key] ?? null; return ($v === '' ? null : $v); }
    $c = store_get('config'); return $c[$key] ?? $default;
}
function cfg_set(array $kv): void { $c = store_get('config'); foreach ($kv as $k => $v) { if ($v === null) unset($c[$k]); else $c[$k] = $v; } store_put('config', $c); }
function tok(string $provider): array {
    $t = store_get('tokens');
    if ($s = client_cur()) {
        if ($provider === 'google') return $t['google'] ?? [];          // la misma cuenta de Google de Pablo, con las propiedades del cliente
        if ($provider === 'meta') return client_meta_tok($s, $t['meta'] ?? []);
        return [];                                                       // LinkedIn y demás: aún no por cliente
    }
    return $t[$provider] ?? [];
}
function tok_set(string $provider, array $v): void {
    if (($s = client_cur()) && $provider === 'meta') { $c = store_get('clientes_meta', []); $c[$s] = $v; store_put('clientes_meta', $c); return; }
    if (client_cur() && $provider !== 'google') throw new RuntimeException('Esta conexión no se puede cambiar desde la vista de un cliente.');
    $t = store_get('tokens'); $t[$provider] = $v; store_put('tokens', $t);
}
function hub_log(string $msg): void { @file_put_contents(hub_data_dir() . '/hub.log', date('c') . " $msg\n", FILE_APPEND | LOCK_EX); }

function base_url(): string {
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
    $host = $_SERVER['HTTP_HOST'] ?? 'innovai.es';
    $dir = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/hub/index.php')), '/');
    $dir = preg_replace('#/(oauth|feeds)$#', '', $dir);
    return ($https ? 'https' : 'http') . "://$host$dir";
}
function h(?string $s): string { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

/** Petición HTTP genérica. Devuelve [status, body decodificado|string, headers] */
function http(string $method, string $url, array $opt = []): array {
    $ch = curl_init();
    $headers = $opt['headers'] ?? [];
    if (isset($opt['query'])) $url .= (str_contains($url, '?') ? '&' : '?') . http_build_query($opt['query']);
    if (isset($opt['json'])) { $body = json_encode($opt['json'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); $headers[] = 'Content-Type: application/json'; }
    elseif (isset($opt['form'])) { $body = http_build_query($opt['form']); $headers[] = 'Content-Type: application/x-www-form-urlencoded'; }
    else { $body = $opt['body'] ?? null; }
    $respHeaders = [];
    curl_setopt_array($ch, [
        CURLOPT_URL => $url, CURLOPT_CUSTOMREQUEST => $method, CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => $opt['timeout'] ?? 60, CURLOPT_HTTPHEADER => $headers, CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_HEADERFUNCTION => function ($c, $line) use (&$respHeaders) { $p = explode(':', $line, 2); if (count($p) === 2) $respHeaders[strtolower(trim($p[0]))] = trim($p[1]); return strlen($line); },
    ]);
    if ($body !== null) curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
    $raw = curl_exec($ch);
    if ($raw === false) { $e = curl_error($ch); curl_close($ch); throw new RuntimeException("Error de red: $e"); }
    $status = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    curl_close($ch);
    $dec = json_decode((string)$raw, true);
    return [$status, $dec ?? $raw, $respHeaders];
}
function http_ok(array $r, string $what) {
    [$s, $b] = $r;
    if ($s >= 200 && $s < 300) return $b;
    $msg = is_array($b) ? ($b['error']['message'] ?? $b['message'] ?? json_encode($b, JSON_UNESCAPED_UNICODE)) : substr((string)$b, 0, 500);
    throw new RuntimeException("$what falló ($s): $msg");
}

// ---- Sesión de administrador ----
function session_boot(): void {
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_name('innovai_hub');
        session_set_cookie_params(['lifetime' => 0, 'path' => '/', 'secure' => true, 'httponly' => true, 'samesite' => 'Lax']);
        session_start();
    }
}
function is_admin(): bool { session_boot(); return !empty($_SESSION['admin']); }
/** Cliente con sesión iniciada (solo puede ver sus resultados). */
function client_session(): ?string {
    session_boot(); $s = $_SESSION['cliente'] ?? null;
    if (!$s) return null; $c = client_get($s);
    return ($c && empty($c['desactivado'])) ? $s : null;
}
function require_admin(): void { if (!is_admin()) { header('Location: ' . base_url() . '/index.php'); exit; } }
function csrf(): string { session_boot(); if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(16)); return $_SESSION['csrf']; }
function csrf_check(): void { session_boot(); if (!hash_equals($_SESSION['csrf'] ?? '', $_POST['csrf'] ?? '')) { http_response_code(400); exit('CSRF'); } }
