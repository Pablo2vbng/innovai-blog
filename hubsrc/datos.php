<?php
// innovAI Hub · datos del panel (solo con sesión iniciada)
declare(strict_types=1);
require __DIR__ . '/lib/core.php';
require __DIR__ . '/lib/tools.php';
require __DIR__ . '/lib/dash.php';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
if (!is_admin()) { http_response_code(403); echo '{"error":"Sesión caducada"}'; exit; }
@set_time_limit(120);
echo json_encode(dash_data(isset($_GET['actualizar'])), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
