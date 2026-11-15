<?php
// innovAI · Test GEO: utilidades comunes (datos fuera de la carpeta pública, límites de uso, sesión)
declare(strict_types=1);
date_default_timezone_set('Europe/Madrid');

function tg_data_dir(): string {
    // En el servidor: /domains/innovai.es/hub-data/test-geo (fuera de public_html). En local: carpeta temporal.
    $base = dirname(__DIR__, 2) . '/hub-data';
    $dir = is_dir($base) ? $base . '/test-geo' : sys_get_temp_dir() . '/test-geo-data';
    if (!is_dir($dir)) @mkdir($dir, 0700, true);
    return $dir;
}

function tg_ip(): string { return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0'; }

/** Límite sencillo: $max usos por IP cada $window segundos. */
function tg_rate_ok(string $bucket, int $max, int $window): bool {
    $f = tg_data_dir() . '/rate-' . $bucket . '-' . hash('sha256', tg_ip() . 'innovai') . '.json';
    $now = time();
    $hits = is_file($f) ? (json_decode((string)file_get_contents($f), true) ?: []) : [];
    $hits = array_values(array_filter($hits, fn($t) => $t > $now - $window));
    if (count($hits) >= $max) return false;
    $hits[] = $now;
    file_put_contents($f, json_encode($hits), LOCK_EX);
    return true;
}

function tg_session(): void {
    if (session_status() === PHP_SESSION_NONE) {
        session_name('innovai_tg');
        session_set_cookie_params(['lifetime' => 0, 'path' => '/test-geo/', 'secure' => !empty($_SERVER['HTTPS']), 'httponly' => true, 'samesite' => 'Lax']);
        session_start();
    }
}

function tg_json(array $data, int $code = 200): never {
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}
