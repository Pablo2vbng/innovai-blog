<?php
// innovAI Hub · datos del panel (solo con sesión iniciada)
declare(strict_types=1);
require __DIR__ . '/lib/core.php';
require __DIR__ . '/lib/tools.php';
require __DIR__ . '/lib/dash.php';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
// Pablo puede ver cualquier negocio (?c=cliente); un cliente solo ve el suyo, diga lo que diga la URL.
if (is_admin()) $c = (string)($_GET['c'] ?? '');
elseif ($s = client_session()) $c = $s;
else { http_response_code(403); echo '{"error":"Sesión caducada"}'; exit; }
try { client_use($c); } catch (Throwable $e) { http_response_code(404); echo '{"error":"Cliente no encontrado"}'; exit; }
@set_time_limit(120);
// Un cliente puede pulsar «Actualizar» como mucho una vez cada 10 minutos.
echo json_encode(dash_data(isset($_GET['actualizar']), is_admin() ? 0 : 600), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
