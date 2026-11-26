<?php
// innovAI · Formulario de auditoría / presupuesto (auditoria-gratuita.html).
// Reutiliza las utilidades del Test GEO: correo por SMTP de info@innovai.es, límites por IP y datos fuera de public_html.
declare(strict_types=1);
require is_file(__DIR__ . '/test-geo/common.php') ? __DIR__ . '/test-geo/common.php' : dirname(__DIR__) . '/test-geo/common.php';

$isJson = stripos($_SERVER['CONTENT_TYPE'] ?? '', 'application/json') !== false;
$back = function (string $estado) use ($isJson) {
    if ($isJson) return;
    header('Location: auditoria-gratuita.html?envio=' . $estado . '#formulario', true, 303);
    exit;
};
$fail = function (string $msg, int $code) use ($back) { $back('error'); tg_json(['error' => $msg], $code); };

if ($_SERVER['REQUEST_METHOD'] !== 'POST') $fail('Método no permitido', 405);

// Solo desde la propia web
$origin = (string)($_SERVER['HTTP_ORIGIN'] ?? $_SERVER['HTTP_REFERER'] ?? '');
if ($origin !== '' && !preg_match('#^https://(www\.)?innovai\.es(/|$)#', $origin)) $fail('Origen no permitido', 403);

$in = $isJson ? (json_decode((string)file_get_contents('php://input'), true) ?: []) : $_POST;
if (!empty($in['web2'])) { $back('ok'); tg_json(['ok' => true]); } // trampa para robots
if (!tg_rate_ok('solicitud', 5, 3600)) $fail('Ya hemos recibido varias solicitudes desde aquí. Te escribiremos pronto.', 429);

$clean = fn($v, $n) => mb_substr(trim(preg_replace('/[\r\n\t]+/', ' ', strip_tags((string)$v))), 0, $n);
$nombre  = $clean($in['nombre'] ?? '', 80);
$empresa = $clean($in['empresa'] ?? '', 120);
$email   = $clean($in['email'] ?? '', 120);
$web     = $clean($in['web'] ?? '', 200);
$mensaje = mb_substr(trim(strip_tags((string)($in['mensaje'] ?? ''))), 0, 1500);
$servicios = ['Auditoría GEO (ChatGPT y buscadores con IA)', 'Posicionamiento SEO en Google', 'Diseño web optimizado',
              'SEO local y Google Business', 'Inteligencia artificial y automatización', 'Presupuesto de otro servicio'];
$servicio = in_array($in['servicio'] ?? '', $servicios, true) ? $in['servicio'] : 'Sin indicar';
$acepta = !empty($in['acepta']);

if ($nombre === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) $fail('Escribe tu nombre y un email válido.', 422);
if ($web !== '' && !preg_match('#^(https?://)?[a-z0-9.-]+\.[a-z]{2,}(/.*)?$#i', $web)) $fail('Revisa la dirección de la web (o déjala en blanco).', 422);
if (!$acepta) $fail('Necesitamos que aceptes la política de privacidad para poder responderte.', 422);

// Registro fuera de la carpeta pública (protegido contra fórmulas al abrirlo en Excel)
$row = array_map(fn($v) => preg_match('/^[=+\-@]/', (string)$v) ? "'" . $v : $v,
    [date('c'), $nombre, $empresa, $email, $web, $servicio, str_replace(["\r", "\n"], ' ', $mensaje), 'acepta_privacidad']);
$fp = fopen(tg_data_dir() . '/solicitudes-web.csv', 'a');
if ($fp) { flock($fp, LOCK_EX); fputcsv($fp, $row, ';'); flock($fp, LOCK_UN); fclose($fp); }

$cuerpo = "Nueva solicitud desde innovai.es/auditoria-gratuita.html\n\n"
    . "Nombre: $nombre\nEmpresa: " . ($empresa ?: '—') . "\nEmail: $email\nWeb: " . ($web ?: '—') . "\nServicio: $servicio\n\n"
    . "Mensaje:\n" . ($mensaje ?: '—') . "\n\nHa aceptado la política de privacidad para que le contactemos sobre esta solicitud.\n";
tg_mail('info@innovai.es', "📩 Solicitud web: $servicio · $nombre", $cuerpo, $email, 'innovAI web');

$copia = "Hola, $nombre:\n\nHemos recibido tu solicitud ($servicio). Pablo, de innovAI, la revisará y te escribirá personalmente a este correo.\n\n"
    . "Mientras tanto, puedes hacer el Test GEO gratuito en https://innovai.es/test-geo/\n\n"
    . "Un saludo,\nPablo Vidal · innovAI\nhttps://innovai.es · 601 68 97 09\n\n"
    . "Recibes este correo porque has rellenado el formulario de innovai.es. Responsable: Pablo Vidal Vidal (innovAI). Puedes pedir el acceso, la rectificación o la supresión de tus datos escribiendo a info@innovai.es.\n";
tg_mail($email, 'Hemos recibido tu solicitud · innovAI', $copia, 'info@innovai.es', 'Pablo de innovAI');

$back('ok');
tg_json(['ok' => true]);
