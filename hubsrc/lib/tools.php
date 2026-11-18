<?php
declare(strict_types=1);
$GLOBALS['HUB_TOOLS'] = [];
/** Registra una herramienta para el conector MCP. $props = [nombre => [tipo, descripción, requerido?, extra?]] */
function tool(string $name, string $desc, array $props, callable $fn, bool $writes = false): void {
    $properties = []; $required = [];
    foreach ($props as $p => $def) {
        [$type, $d] = $def; $req = $def[2] ?? false; $extra = $def[3] ?? [];
        $properties[$p] = array_merge(['type' => $type, 'description' => $d], $extra);
        if ($req) $required[] = $p;
    }
    $schema = ['type' => 'object', 'properties' => (object)$properties];
    if ($required) $schema['required'] = $required;
    $GLOBALS['HUB_TOOLS'][$name] = ['description' => $desc, 'inputSchema' => $schema, 'fn' => $fn, 'writes' => $writes];
}
function need(array $a, string $k) { if (!isset($a[$k]) || $a[$k] === '') throw new InvalidArgumentException("Falta el parámetro «$k»"); return $a[$k]; }
foreach (glob(dirname(__DIR__) . '/providers/*.php') as $f) require_once $f;
tool('clientes_lista', 'Lista los clientes del hub (su código, nombre y qué cuentas tienen configuradas). Usa el código en el parámetro «cliente» de cualquier herramienta para trabajar con las cuentas de ese cliente.', [],
    fn($a) => array_values(array_map(fn($c, $slug) => ['cliente' => $slug, 'nombre' => $c['nombre'] ?? $slug, 'web' => $c['web'] ?? '', 'activo' => empty($c['desactivado']),
        'cuentas' => array_keys(array_filter(array_intersect_key($c, array_flip(CLIENT_KEYS))))], clients_all(), array_keys(clients_all()))));
