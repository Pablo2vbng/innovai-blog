<?php
// innovAI · Test GEO: recoge la solicitud de revisión (con consentimiento) y avisa a innovAI
declare(strict_types=1);
require __DIR__ . '/common.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') tg_json(['error' => 'Método no permitido'], 405);
tg_session();
$in = json_decode((string)file_get_contents('php://input'), true) ?: $_POST;
if (!hash_equals($_SESSION['tg_csrf'] ?? '', (string)($in['t'] ?? ''))) tg_json(['error' => 'La página ha caducado. Recárgala y vuelve a probar.'], 400);
if (!empty($in['web2'])) tg_json(['ok' => true]); // trampa para robots
if (!tg_rate_ok('contacto', 4, 3600)) tg_json(['error' => 'Ya hemos recibido tu solicitud. Te escribiremos pronto.'], 429);

$clean = fn($v, $n) => mb_substr(trim(preg_replace('/[\r\n\t]+/', ' ', strip_tags((string)$v))), 0, $n);
$nombre = $clean($in['nombre'] ?? '', 80);
$email = $clean($in['email'] ?? '', 120);
$tel = $clean($in['telefono'] ?? '', 30);
$negocio = $clean($in['negocio'] ?? '', 120);
$acepta = !empty($in['acepta']);
if ($nombre === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) tg_json(['error' => 'Escribe tu nombre y un correo válido.'], 422);
if ($tel !== '' && !preg_match('/^[+0-9 ().-]{9,20}$/', $tel)) tg_json(['error' => 'Revisa el teléfono (o déjalo en blanco).'], 422);
if (!$acepta) tg_json(['error' => 'Para poder contestarte necesitamos que aceptes la política de privacidad.'], 422);

$last = $_SESSION['tg_last'] ?? null;
$web = $last['url'] ?? $clean($in['url'] ?? '', 200);
$score = $last['puntuacion'] ?? '';
$mejoras = $last['mejoras'] ?? [];

// Registro de solicitudes (fuera de la carpeta pública)
$row = array_map(fn($v) => preg_match('/^[=+\-@]/', (string)$v) ? "'" . $v : $v, [date('c'), $nombre, $email, $tel, $negocio, $web, $score, 'acepta_privacidad']);
$fp = fopen(tg_data_dir() . '/solicitudes.csv', 'a');
if ($fp) { flock($fp, LOCK_EX); fputcsv($fp, $row, ';'); flock($fp, LOCK_UN); fclose($fp); }

// Aviso a innovAI
$cuerpo = "Nueva solicitud desde el Test GEO de innovai.es\n\n"
    . "Nombre: $nombre\nCorreo: $email\nTeléfono: " . ($tel ?: '—') . "\nNegocio: " . ($negocio ?: '—') . "\nWeb analizada: $web\nPuntuación GEO: $score/100\n\n"
    . "Puntos a mejorar detectados:\n- " . implode("\n- ", $mejoras ?: ['(ninguno)']) . "\n\n"
    . "Ha aceptado la política de privacidad para que le contactemos sobre esta solicitud.\n";
$headers = "From: innovAI Test GEO <info@innovai.es>\r\nReply-To: $email\r\nContent-Type: text/plain; charset=UTF-8\r\n";
@mail('info@innovai.es', '=?UTF-8?B?' . base64_encode("🤖 Test GEO: $nombre ($score/100)") . '?=', $cuerpo, $headers, '-finfo@innovai.es');

// Copia del resultado a la persona que lo ha pedido
$copia = "Hola, $nombre:\n\nGracias por usar el Test GEO de innovAI. Este es el resumen de tu web ($web):\n\nPuntuación: $score/100\n\n"
    . ($mejoras ? "Qué mejorar para que ChatGPT y otras IA entiendan mejor tu negocio:\n- " . implode("\n- ", $mejoras) . "\n\n" : '')
    . "Pablo, de innovAI, te escribirá en breve para revisarlo contigo y contarte por dónde empezar, sin compromiso.\n\n"
    . "Un saludo,\nPablo Vidal · innovAI\nhttps://innovai.es · 601 68 97 09\n\n"
    . "Recibes este correo porque has pedido la revisión en innovai.es/test-geo. Responsable: Pablo Vidal Vidal (innovAI). Puedes pedir el acceso, la rectificación o la supresión de tus datos escribiendo a info@innovai.es.\n";
$h2 = "From: Pablo de innovAI <info@innovai.es>\r\nReply-To: info@innovai.es\r\nContent-Type: text/plain; charset=UTF-8\r\n";
@mail($email, '=?UTF-8?B?' . base64_encode('Tu resultado del Test GEO de innovAI') . '?=', $copia, $h2, '-finfo@innovai.es');

tg_json(['ok' => true]);
