<?php
// innovAI Hub · conector MCP (Streamable HTTP, JSON-RPC 2.0) para usarlo desde Claude
declare(strict_types=1);
require __DIR__ . '/lib/core.php';
require __DIR__ . '/lib/tools.php';
header('Cache-Control: no-store');
$key = cfg('mcp_key');
$given = $_GET['k'] ?? (preg_match('/Bearer\s+(\S+)/', $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '', $m) ? $m[1] : '');
if (!$key || !hash_equals($key, (string)$given)) { http_response_code(401); header('Content-Type: application/json'); echo '{"error":"unauthorized"}'; exit; }
if ($_SERVER['REQUEST_METHOD'] === 'GET') { http_response_code(405); header('Allow: POST'); exit; }
if ($_SERVER['REQUEST_METHOD'] === 'DELETE') { http_response_code(204); exit; }
$in = json_decode((string)file_get_contents('php://input'), true);
if (!is_array($in)) { http_response_code(400); exit; }
@set_time_limit(600);
$batch = array_is_list($in); $msgs = $batch ? $in : [$in]; $out = [];
foreach ($msgs as $msg) { $r = mcp_handle($msg); if ($r !== null) $out[] = $r; }
if (!$out) { http_response_code(202); exit; }
header('Content-Type: application/json');
if (empty($_SERVER['HTTP_MCP_SESSION_ID'])) header('Mcp-Session-Id: ' . bin2hex(random_bytes(8)));
echo json_encode($batch ? $out : $out[0], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

function mcp_handle(array $m): ?array {
    $id = $m['id'] ?? null; $method = $m['method'] ?? ''; $p = $m['params'] ?? [];
    if ($id === null) return null; // notificación
    $ok = fn($res) => ['jsonrpc' => '2.0', 'id' => $id, 'result' => $res];
    switch ($method) {
        case 'initialize':
            $v = in_array($p['protocolVersion'] ?? '', ['2024-11-05', '2025-03-26', '2025-06-18'], true) ? $p['protocolVersion'] : '2025-06-18';
            return $ok(['protocolVersion' => $v, 'capabilities' => ['tools' => ['listChanged' => false]],
                'serverInfo' => ['name' => 'innovai-hub', 'title' => 'innovAI Hub', 'version' => HUB_VERSION],
                'instructions' => 'Hub propio de innovAI (Pablo Vidal), también para sus clientes (parámetro «cliente», ver clientes_lista). Publica y lee estadísticas de Instagram, Facebook, LinkedIn, Google Business, Search Console, Merchant Center, Analytics y el catálogo de ChatGPT para negocios. Las herramientas que publican lo hacen de verdad: confirma siempre con Pablo antes de usarlas.']);
        case 'ping': return $ok((object)[]);
        case 'tools/list':
            $tools = [];
            foreach ($GLOBALS['HUB_TOOLS'] as $n => $t) $tools[] = ['name' => $n, 'description' => $t['description'], 'inputSchema' => mcp_schema($n, $t['inputSchema']),
                'annotations' => ['readOnlyHint' => !$t['writes'], 'destructiveHint' => false, 'openWorldHint' => true]];
            return $ok(['tools' => $tools]);
        case 'tools/call':
            $n = $p['name'] ?? ''; $t = $GLOBALS['HUB_TOOLS'][$n] ?? null;
            if (!$t) return ['jsonrpc' => '2.0', 'id' => $id, 'error' => ['code' => -32602, 'message' => "Herramienta desconocida: $n"]];
            $args = $p['arguments'] ?? []; $cli = (string)($args['cliente'] ?? ''); unset($args['cliente']);
            try { client_use($cli); $res = ($t['fn'])($args); hub_log("tool $n " . ($cli ? "[$cli] " : '') . 'ok');
                return $ok(['content' => [['type' => 'text', 'text' => json_encode($res, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)]]]); }
            catch (Throwable $e) { hub_log("tool $n " . ($cli ? "[$cli] " : '') . 'ERROR ' . $e->getMessage()); return $ok(['content' => [['type' => 'text', 'text' => 'Error: ' . $e->getMessage()]], 'isError' => true]); }
        default: return ['jsonrpc' => '2.0', 'id' => $id, 'error' => ['code' => -32601, 'message' => "Método no soportado: $method"]];
    }
}
/** Añade a cada herramienta el parámetro opcional «cliente» (vacío = cuentas de innovAI). */
function mcp_schema(string $name, array $schema): array {
    if ($name === 'clientes_lista') return $schema;
    $props = (array)$schema['properties'];
    $props['cliente'] = ['type' => 'string', 'description' => 'Código del cliente (ver clientes_lista). Vacío = cuentas de innovAI.'];
    $schema['properties'] = (object)$props;
    return $schema;
}
