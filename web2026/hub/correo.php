<?php
// innovAI Hub · correo de prospección (prospeccion@innovai.es) por SMTP/IMAP de Hostinger.
// La contraseña la guarda Pablo en el panel del hub (Ajustes → «Correo prospeccion@innovai.es»).
declare(strict_types=1);

const PROSP_USER = 'prospeccion@innovai.es';

function prosp_pass(): string {
    $c = store_get('config'); $p = (string)($c['smtp_pass_prospeccion'] ?? '');
    if ($p === '') throw new RuntimeException('Falta la contraseña de prospeccion@innovai.es: guárdala en el panel del hub (Ajustes).');
    return $p;
}
function prosp_addr(string $a): string {
    $a = trim($a);
    if (!filter_var($a, FILTER_VALIDATE_EMAIL)) throw new InvalidArgumentException("Dirección no válida: $a");
    return $a;
}
function prosp_list($v): array { if ($v === null || $v === '') return []; $v = is_array($v) ? $v : preg_split('/[,;\s]+/', (string)$v); return array_values(array_map('prosp_addr', array_filter($v, fn($x) => trim((string)$x) !== ''))); }
function prosp_hdr(string $s): string { return preg_match('/[^\x20-\x7E]/', $s) ? '=?UTF-8?B?' . base64_encode($s) . '?=' : $s; }
function prosp_log(string $m): void { @file_put_contents(hub_data_dir() . '/correo-prospeccion.log', date('c') . " $m\n", FILE_APPEND | LOCK_EX); }

/** Conversación de línea con un servidor (SMTP o IMAP). */
function prosp_sock(string $host, int $port) {
    $fp = @stream_socket_client("ssl://$host:$port", $en, $es, 20);
    if (!$fp) throw new RuntimeException("No conecta con $host: $es");
    stream_set_timeout($fp, 25);
    return $fp;
}

function prosp_send(array $to, array $cc, string $subject, string $text, string $html, string $replyTo): string {
    $pass = prosp_pass();
    $fp = prosp_sock('smtp.hostinger.com', 465);
    $read = function () use ($fp) { $r = ''; while (($l = fgets($fp, 1024)) !== false) { $r .= $l; if (strlen($l) < 4 || $l[3] === ' ') break; } return $r; };
    $cmd = function (?string $c, string $expect) use ($fp, $read) { if ($c !== null) fwrite($fp, $c . "\r\n"); $r = $read(); if (strpos($r, $expect) !== 0) throw new RuntimeException('SMTP: ' . trim(substr($r, 0, 160))); return $r; };
    $mid = bin2hex(random_bytes(12)) . '@innovai.es';
    try {
        $cmd(null, '220'); $cmd('EHLO innovai.es', '250');
        $cmd('AUTH LOGIN', '334'); $cmd(base64_encode(PROSP_USER), '334'); $cmd(base64_encode($pass), '235');
        $cmd('MAIL FROM:<' . PROSP_USER . '>', '250');
        foreach (array_unique(array_merge($to, $cc, [PROSP_USER])) as $r) $cmd("RCPT TO:<$r>", '25');   // copia oculta a prospeccion@ para el historial
        $cmd('DATA', '354');
        $b = 'b' . bin2hex(random_bytes(8));
        $h = 'Date: ' . date('r') . "\r\nFrom: " . prosp_hdr('Prospección innovAI') . ' <' . PROSP_USER . ">\r\nTo: " . implode(', ', $to) . "\r\n"
           . ($cc ? 'Cc: ' . implode(', ', $cc) . "\r\n" : '') . ($replyTo ? "Reply-To: <$replyTo>\r\n" : '')
           . 'Subject: ' . prosp_hdr($subject) . "\r\nMessage-ID: <$mid>\r\nMIME-Version: 1.0\r\n";
        if ($html !== '') {
            $h .= "Content-Type: multipart/alternative; boundary=\"$b\"\r\n\r\n"
                . "--$b\r\nContent-Type: text/plain; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n" . chunk_split(base64_encode($text !== '' ? $text : trim(strip_tags($html))))
                . "--$b\r\nContent-Type: text/html; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n" . chunk_split(base64_encode($html)) . "--$b--\r\n";
        } else {
            $h .= "Content-Type: text/plain; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n" . chunk_split(base64_encode($text));
        }
        fwrite($fp, $h . "\r\n.\r\n"); $cmd(null, '250'); $cmd('QUIT', '221');
    } finally { fclose($fp); }
    prosp_log('enviado a ' . implode(',', array_merge($to, $cc)) . " · $subject");
    return $mid;
}

/** Lectura mínima por IMAP (sin extensión imap de PHP). */
function prosp_imap(int $dias, int $max, string $de): array {
    $pass = prosp_pass();
    $fp = prosp_sock('imap.hostinger.com', 993);
    $n = 0;
    $q = function (string $c) use ($fp, &$n): string {
        $tag = 'A' . (++$n); fwrite($fp, "$tag $c\r\n"); $out = '';
        while (($l = fgets($fp, 8192)) !== false) {
            if (preg_match('/\{(\d+)\}\r\n$/', $l, $m)) { $out .= $l; $len = (int)$m[1]; $buf = ''; while (strlen($buf) < $len && !feof($fp)) { $buf .= fread($fp, $len - strlen($buf)); } $out .= $buf; continue; }
            if (strpos($l, "$tag ") === 0) { if (strpos($l, "$tag OK") !== 0) throw new RuntimeException('IMAP: ' . trim(substr($l, 0, 160))); return $out; }
            $out .= $l;
        }
        throw new RuntimeException('IMAP: conexión cortada');
    };
    try {
        fgets($fp, 1024);
        $q('LOGIN "' . addcslashes(PROSP_USER, '"\\') . '" "' . addcslashes($pass, '"\\') . '"');
        $q('SELECT INBOX');
        $crit = 'SINCE ' . date('d-M-Y', strtotime("-$dias days")) . ($de !== '' ? ' FROM "' . addcslashes($de, '"\\') . '"' : '');
        preg_match('/\* SEARCH([\d ]*)/', $q("UID SEARCH $crit"), $m);
        $uids = array_slice(array_reverse(array_filter(explode(' ', trim($m[1] ?? '')))), 0, $max);
        $res = [];
        foreach ($uids as $uid) {
            $raw = $q("UID FETCH $uid (FLAGS BODY.PEEK[HEADER.FIELDS (FROM TO CC SUBJECT DATE MESSAGE-ID IN-REPLY-TO)] BODY.PEEK[TEXT]<0.12000>)");
            $parts = preg_split('/\{\d+\}\r\n/', $raw);
            $hdr = $parts[1] ?? ''; $body = $parts[2] ?? '';
            $get = function (string $k) use ($hdr) { return preg_match('/^' . $k . ':\s*(.*(?:\r\n[ \t].*)*)/mi', $hdr, $mm) ? trim(iconv_mime_decode(preg_replace('/\r\n[ \t]+/', ' ', $mm[1]), ICONV_MIME_DECODE_CONTINUE_ON_ERROR, 'UTF-8')) : ''; };
            $res[] = ['uid' => (int)$uid, 'fecha' => $get('Date'), 'de' => $get('From'), 'para' => $get('To'), 'cc' => $get('Cc'), 'asunto' => $get('Subject'),
                'message_id' => $get('Message-ID'), 'en_respuesta_a' => $get('In-Reply-To'), 'leido' => str_contains($raw, '\\Seen'), 'texto' => prosp_text($body)];
        }
        $q('LOGOUT');
        return $res;
    } finally { @fclose($fp); }
}
/** Saca texto legible del cuerpo (texto plano, base64 o quoted-printable; quita el HTML). */
function prosp_text(string $b): string {
    if (preg_match('/Content-Type:\s*text\/plain[^\r\n]*(?:\r\n[^\r\n]+)*\r\n\r\n(.*?)(?:\r\n--|$)/si', $b, $m)) {
        $seg = $m[0]; $txt = $m[1];
        if (stripos($seg, 'base64') !== false) $txt = base64_decode(preg_replace('/\s+/', '', $txt)) ?: $txt;
        elseif (stripos($seg, 'quoted-printable') !== false) $txt = quoted_printable_decode($txt);
    } else {
        $txt = preg_match('/^[A-Za-z0-9+\/=\r\n]+$/', trim($b)) ? (base64_decode(preg_replace('/\s+/', '', $b)) ?: $b) : quoted_printable_decode($b);
        $txt = html_entity_decode(strip_tags(preg_replace('/<(br|p|div)[^>]*>/i', "\n", $txt)));
    }
    if (!mb_check_encoding($txt, 'UTF-8')) $txt = mb_convert_encoding($txt, 'UTF-8', 'ISO-8859-1');
    return trim(mb_substr(preg_replace("/\n{3,}/", "\n\n", str_replace("\r", '', $txt)), 0, 6000));
}

tool('correo_enviar', 'Envía un correo REAL desde prospeccion@innovai.es (SMTP de Hostinger). Para resúmenes, previsiones y listados de prospección de los clientes de innovAI. Se guarda copia en prospeccion@. Confirma siempre con Pablo antes de enviar, salvo en las tareas programadas que él ya aprobó.', [
    'para' => ['array', 'Destinatarios', true, ['items' => ['type' => 'string']]],
    'cc' => ['array', 'Copia (opcional)', false, ['items' => ['type' => 'string']]],
    'asunto' => ['string', 'Asunto', true],
    'texto' => ['string', 'Cuerpo en texto plano (obligatorio si no hay html)'],
    'html' => ['string', 'Cuerpo en HTML (opcional)'],
    'responder_a' => ['string', 'Dirección Reply-To (opcional)'],
], function ($a) {
    $to = prosp_list(need($a, 'para')); $cc = prosp_list($a['cc'] ?? []);
    if (!$to) throw new InvalidArgumentException('Falta al menos un destinatario');
    $text = (string)($a['texto'] ?? ''); $html = (string)($a['html'] ?? '');
    if ($text === '' && $html === '') throw new InvalidArgumentException('Falta el cuerpo del correo');
    $rt = trim((string)($a['responder_a'] ?? '')); if ($rt !== '') prosp_addr($rt);
    $mid = prosp_send($to, $cc, (string)need($a, 'asunto'), $text, $html, $rt);
    return ['enviado' => true, 'de' => PROSP_USER, 'para' => $to, 'cc' => $cc, 'message_id' => $mid];
}, true);

tool('correo_leer', 'Lee la bandeja de entrada de prospeccion@innovai.es (solo lectura, no marca como leído). Sirve para ver respuestas y aprobaciones de los clientes.', [
    'dias' => ['integer', 'Últimos N días (por defecto 7, máx. 60)'],
    'max' => ['integer', 'Máximo de correos, los más recientes (por defecto 20, máx. 50)'],
    'de' => ['string', 'Filtrar por remitente (opcional), p. ej. info@protege-t.es'],
], fn($a) => prosp_imap(max(1, min(60, (int)($a['dias'] ?? 7))), max(1, min(50, (int)($a['max'] ?? 20))), trim((string)($a['de'] ?? ''))));
