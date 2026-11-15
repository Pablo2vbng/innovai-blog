<?php
// innovAI · Test GEO: analiza la web que envía el usuario y devuelve el resultado en JSON
declare(strict_types=1);
require __DIR__ . '/common.php';
require __DIR__ . '/geo-lib.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') tg_json(['error' => 'Método no permitido'], 405);
tg_session();
$in = json_decode((string)file_get_contents('php://input'), true) ?: $_POST;
if (!hash_equals($_SESSION['tg_csrf'] ?? '', (string)($in['t'] ?? ''))) tg_json(['error' => 'La página ha caducado. Recárgala y vuelve a probar.'], 400);
if (!tg_rate_ok('analizar', 12, 3600)) tg_json(['error' => 'Has hecho muchas pruebas seguidas. Espera un rato y vuelve a intentarlo.'], 429);
@set_time_limit(60);
try {
    $r = geo_analyze((string)($in['url'] ?? ''));
    $_SESSION['tg_last'] = ['url' => $r['url'], 'puntuacion' => $r['puntuacion'], 'nivel' => $r['nivel'],
        'mejoras' => array_values(array_map(fn($p) => $p['titulo'] . ': ' . $p['texto'], array_filter($r['puntos'], fn($p) => $p['estado'] !== 'ok' && $p['peso'] > 0)))];
    // Registro anónimo de uso (dominio, puntuación y fecha), útil para ver qué webs se analizan
    file_put_contents(tg_data_dir() . '/usos.csv', implode(';', [date('c'), $r['dominio'], $r['puntuacion']]) . "\n", FILE_APPEND | LOCK_EX);
    tg_json($r);
} catch (GeoError $e) {
    tg_json(['error' => $e->getMessage()], 422);
} catch (Throwable $e) {
    tg_json(['error' => 'No hemos podido analizar esa web ahora mismo. Vuelve a probar en unos minutos.'], 500);
}
