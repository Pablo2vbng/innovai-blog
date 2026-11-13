<?php
// innovAI Hub · datos del panel de resultados (se guardan en caché para que el panel cargue rápido)
declare(strict_types=1);

const DASH_TTL = 3 * 3600;

function dash_tool(string $name, array $args = []) {
    $t = $GLOBALS['HUB_TOOLS'][$name] ?? throw new RuntimeException("Herramienta $name no existe");
    return ($t['fn'])($args);
}

/** Ejecuta un bloque y, si falla, devuelve el error en vez de romper todo el panel. */
function dash_try(callable $fn): array {
    try { return ['ok' => true, 'd' => $fn()]; }
    catch (Throwable $e) { return ['ok' => false, 'error' => dash_err($e->getMessage())]; }
}
function dash_err(string $m): string {
    if (str_contains($m, 'no está conectado') || str_contains($m, 'no conectado')) return 'Sin conectar';
    if (str_contains($m, 'Quota exceeded') || str_contains($m, '429')) return 'Pendiente de que Google apruebe el acceso';
    return mb_substr($m, 0, 160);
}
function dash_series($values): array { // valores diarios de Meta → [[fecha, valor]]
    $out = [];
    if (!is_array($values)) return $out;
    foreach ($values as $v) if (isset($v['end_time'])) $out[] = [substr($v['end_time'], 0, 10), (int)($v['value'] ?? 0)];
    return $out;
}

function dash_build(): array {
    $d = ['generado' => date('c')];

    $d['instagram'] = dash_try(function () {
        $c = dash_tool('instagram_estadisticas_cuenta', ['dias' => 28]);
        $posts = dash_tool('instagram_publicaciones', ['limite' => 8]);
        foreach (array_slice($posts, 0, 6) as $i => $p) {
            foreach (['reach', 'views', 'saved', 'shares'] as $m) {
                try { $r = meta_g('GET', $p['id'] . '/insights', ['metric' => $m]); $posts[$i][$m] = $r['data'][0]['values'][0]['value'] ?? null; }
                catch (Throwable $e) { $posts[$i][$m] = null; }
            }
        }
        $met = $c['metricas'] ?? [];
        $tot = fn($k) => is_array($met[$k] ?? null) ? (int)($met[$k]['value'] ?? array_sum(array_column($met[$k], 'value'))) : null;
        return ['usuario' => $c['cuenta']['username'] ?? '', 'seguidores' => (int)($c['cuenta']['followers_count'] ?? 0),
            'publicaciones_total' => (int)($c['cuenta']['media_count'] ?? 0),
            'alcance_diario' => dash_series($met['reach'] ?? null), 'seguidores_nuevos' => dash_series($met['follower_count'] ?? null),
            'visitas_perfil' => $tot('profile_views'), 'cuentas_interaccion' => $tot('accounts_engaged'), 'interacciones' => $tot('total_interactions'),
            'posts' => array_map(fn($p) => ['texto' => mb_substr(trim(strtok($p['caption'] ?? '', "\n") ?: ''), 0, 90), 'fecha' => substr($p['timestamp'] ?? '', 0, 10),
                'tipo' => $p['media_type'] ?? '', 'enlace' => $p['permalink'] ?? '', 'likes' => (int)($p['like_count'] ?? 0), 'comentarios' => (int)($p['comments_count'] ?? 0),
                'alcance' => $p['reach'] ?? null, 'vistas' => $p['views'] ?? null, 'guardados' => $p['saved'] ?? null, 'compartidos' => $p['shares'] ?? null], $posts)];
    });

    $d['facebook'] = dash_try(function () {
        $s = dash_tool('facebook_estadisticas', ['dias' => 28]);
        $posts = dash_tool('facebook_publicaciones', ['limite' => 6]);
        return ['pagina' => $s['pagina']['name'] ?? '', 'seguidores' => (int)($s['pagina']['followers_count'] ?? $s['pagina']['fan_count'] ?? 0),
            'alcance_diario' => dash_series($s['page_impressions_unique'] ?? null), 'interacciones_diario' => dash_series($s['page_post_engagements'] ?? null),
            'posts' => array_map(fn($p) => ['texto' => mb_substr(trim(strtok($p['message'] ?? '', "\n") ?: ''), 0, 90), 'fecha' => substr($p['created_time'] ?? '', 0, 10),
                'enlace' => $p['permalink_url'] ?? '', 'reacciones' => (int)($p['reactions']['summary']['total_count'] ?? 0),
                'comentarios' => (int)($p['comments']['summary']['total_count'] ?? 0), 'compartidos' => (int)($p['shares']['count'] ?? 0)], $posts)];
    });

    $d['google'] = dash_try(function () {
        $dias = dash_tool('search_console_consultas', ['dias' => 56, 'dimension' => 'date', 'limite' => 100]);
        $q = dash_tool('search_console_consultas', ['dias' => 28, 'dimension' => 'query', 'limite' => 10]);
        $pg = dash_tool('search_console_consultas', ['dias' => 28, 'dimension' => 'page', 'limite' => 6]);
        $row = fn($r) => ['k' => $r['keys'][0] ?? '', 'clics' => (int)$r['clicks'], 'impresiones' => (int)$r['impressions'], 'ctr' => round($r['ctr'] * 100, 1), 'posicion' => round($r['position'], 1)];
        return ['diario' => array_map(fn($r) => [$r['keys'][0], (int)$r['clicks'], (int)$r['impressions']], $dias),
            'consultas' => array_map($row, $q), 'paginas' => array_map($row, $pg)];
    });

    $d['web'] = dash_try(function () {
        $dia = dash_tool('analytics_informe', ['dias' => 56, 'dimensiones' => ['date'], 'metricas' => ['activeUsers', 'sessions', 'screenPageViews'], 'limite' => 100]);
        usort($dia, fn($a, $b) => strcmp($a['date'], $b['date']));
        $can = dash_tool('analytics_informe', ['dias' => 28, 'dimensiones' => ['sessionDefaultChannelGroup'], 'metricas' => ['sessions'], 'limite' => 10]);
        $pag = dash_tool('analytics_informe', ['dias' => 28, 'dimensiones' => ['pagePath'], 'metricas' => ['screenPageViews'], 'limite' => 8]);
        $ref = [];
        try { $ref = dash_tool('analytics_informe', ['dias' => 28, 'dimensiones' => ['sessionSource'], 'metricas' => ['sessions'], 'limite' => 50]); } catch (Throwable $e) {}
        $ia = 0; foreach ($ref as $r) if (preg_match('/chatgpt|openai|perplexity|gemini|copilot|claude/i', $r['sessionSource'])) $ia += (int)$r['sessions'];
        return ['diario' => array_map(fn($r) => [substr($r['date'], 0, 4) . '-' . substr($r['date'], 4, 2) . '-' . substr($r['date'], 6, 2), (int)$r['activeUsers'], (int)$r['sessions'], (int)$r['screenPageViews']], $dia),
            'canales' => array_map(fn($r) => [$r['sessionDefaultChannelGroup'], (int)$r['sessions']], $can),
            'paginas' => array_map(fn($r) => [$r['pagePath'], (int)$r['screenPageViews']], $pag), 'sesiones_ia' => $ia];
    });

    $d['merchant'] = dash_try(function () {
        $p = dash_tool('merchant_productos');
        $ok = 0; $mal = []; foreach ($p as $x) { $aprob = false; foreach ($x['estado'] ?? [] as $s) if (!empty($s['approvedCountries'])) $aprob = true;
            if ($aprob && empty($x['problemas'])) $ok++; else $mal[] = ['titulo' => $x['titulo'], 'problema' => $x['problemas'][0]['description'] ?? 'No aprobado']; }
        return ['total' => count($p), 'aprobados' => $ok, 'con_problemas' => $mal];
    });

    $d['business'] = dash_try(fn() => dash_tool('business_estadisticas', ['dias' => 28]));

    $d['chatgpt'] = dash_try(fn() => ['productos' => count(chatgpt_products())]);

    $l = tok('linkedin');
    $d['linkedin'] = ['ok' => !empty($l) && ($l['expires'] ?? 0) > time(), 'd' => ['nombre' => $l['name'] ?? '', 'caduca' => isset($l['expires']) ? date('d/m/Y', $l['expires']) : null]];
    return $d;
}

function dash_data(bool $refresh = false): array {
    $c = store_get('dash_cache', []);
    if (!$refresh && !empty($c['data']) && time() - ($c['at'] ?? 0) < DASH_TTL) return $c['data'];
    $data = dash_build();
    store_put('dash_cache', ['at' => time(), 'data' => $data]);
    return $data;
}
