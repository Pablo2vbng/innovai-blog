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

/** Envía un correo de texto por el SMTP de Hostinger con la cuenta info@innovai.es (contraseña guardada en el hub). */
function tg_mail(string $to, string $subject, string $body, string $replyTo, string $fromName): bool {
    $cfgFile = dirname(__DIR__, 2) . '/hub-data/config.json';
    $cfg = is_file($cfgFile) ? (json_decode((string)file_get_contents($cfgFile), true) ?: []) : [];
    $user = 'info@innovai.es'; $pass = (string)($cfg['smtp_pass'] ?? '');
    $log = fn(string $m) => @file_put_contents(tg_data_dir() . '/correo.log', date('c') . " $to $m\n", FILE_APPEND | LOCK_EX);
    $subj = '=?UTF-8?B?' . base64_encode($subject) . '?=';
    if ($pass === '') { $ok = @mail($to, $subj, $body, "From: $fromName <$user>\r\nReply-To: $replyTo\r\nContent-Type: text/plain; charset=UTF-8\r\n", "-f$user"); $log('mail() ' . ($ok ? 'ok' : 'fallo') . ' (sin contraseña SMTP)'); return $ok; }
    $fp = @stream_socket_client('ssl://smtp.hostinger.com:465', $en, $es, 15);
    if (!$fp) { $log("SMTP no conecta: $es"); return false; }
    stream_set_timeout($fp, 15);
    $read = function () use ($fp) { $r = ''; while (($l = fgets($fp, 515)) !== false) { $r .= $l; if (strlen($l) < 4 || $l[3] === ' ') break; } return $r; };
    $cmd = function (?string $c, string $expect) use ($fp, $read) { if ($c !== null) fwrite($fp, $c . "\r\n"); $r = $read(); if (strpos($r, $expect) !== 0) throw new RuntimeException(trim(substr($r, 0, 120))); return $r; };
    try {
        $cmd(null, '220'); $cmd('EHLO innovai.es', '250');
        $cmd('AUTH LOGIN', '334'); $cmd(base64_encode($user), '334'); $cmd(base64_encode($pass), '235');
        $cmd("MAIL FROM:<$user>", '250'); $cmd("RCPT TO:<$to>", '25'); $cmd('DATA', '354');
        $fromEnc = '=?UTF-8?B?' . base64_encode($fromName) . '?=';
        $msg = "Date: " . date('r') . "\r\nFrom: $fromEnc <$user>\r\nTo: <$to>\r\nReply-To: <$replyTo>\r\nSubject: $subj\r\nMessage-ID: <" . bin2hex(random_bytes(8)) . "@innovai.es>\r\nMIME-Version: 1.0\r\nContent-Type: text/plain; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n" . chunk_split(base64_encode($body), 76, "\r\n");
        $cmd($msg . "\r\n.", '250'); fwrite($fp, "QUIT\r\n"); fclose($fp);
        $log('SMTP ok'); return true;
    } catch (Throwable $e) { @fclose($fp); $log('SMTP error: ' . $e->getMessage()); return false; }
}
