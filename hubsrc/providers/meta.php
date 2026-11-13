<?php
// Meta: Facebook (página) + Instagram (cuenta profesional) vía Graph API
declare(strict_types=1);
function meta_v(): string { return cfg('meta_graph_version', 'v23.0'); }
function meta_g(string $method, string $path, array $params = [], ?string $token = null) {
    $t = $token ?? (tok('meta')['page_token'] ?? '');
    if (!$t) throw new RuntimeException('Meta no está conectado. Entra en el panel del hub y pulsa «Conectar Meta».');
    $url = 'https://graph.facebook.com/' . meta_v() . '/' . ltrim($path, '/');
    $params['access_token'] = $t;
    $r = $method === 'GET' ? http('GET', $url, ['query' => $params]) : http($method, $url, ['form' => $params, 'timeout' => 120]);
    return http_ok($r, "Meta $path");
}
function meta_scopes(): string {
    return 'pages_show_list,pages_read_engagement,pages_manage_posts,pages_read_user_content,pages_manage_engagement,read_insights,business_management,instagram_basic,instagram_content_publish,instagram_manage_comments,instagram_manage_insights';
}
function meta_auth_url(): string {
    session_boot(); $_SESSION['oauth_state'] = bin2hex(random_bytes(12));
    return 'https://www.facebook.com/' . meta_v() . '/dialog/oauth?' . http_build_query([
        'client_id' => cfg('meta_app_id'), 'redirect_uri' => base_url() . '/oauth/callback.php?p=meta',
        'state' => $_SESSION['oauth_state'], 'response_type' => 'code'] + (cfg('meta_config_id') ? ['config_id' => cfg('meta_config_id'), 'override_default_response_type' => 'true'] : ['scope' => meta_scopes()]));
}
function meta_callback(string $code): string {
    $base = 'https://graph.facebook.com/' . meta_v();
    $short = http_ok(http('GET', "$base/oauth/access_token", ['query' => ['client_id' => cfg('meta_app_id'), 'client_secret' => cfg('meta_app_secret'), 'redirect_uri' => base_url() . '/oauth/callback.php?p=meta', 'code' => $code]]), 'Meta token');
    $long = http_ok(http('GET', "$base/oauth/access_token", ['query' => ['grant_type' => 'fb_exchange_token', 'client_id' => cfg('meta_app_id'), 'client_secret' => cfg('meta_app_secret'), 'fb_exchange_token' => $short['access_token']]]), 'Meta token largo');
    $user = $long['access_token'];
    $pages = http_ok(http('GET', "$base/me/accounts", ['query' => ['access_token' => $user, 'fields' => 'id,name,access_token,instagram_business_account{id,username}']]), 'Meta páginas');
    $list = $pages['data'] ?? [];
    if (!$list) throw new RuntimeException('No se ha encontrado ninguna página de Facebook administrada por esta cuenta.');
    $want = cfg('meta_page_id');
    $page = $list[0];
    foreach ($list as $p) if ($want && $p['id'] === $want) $page = $p;
    tok_set('meta', ['user_token' => $user, 'page_id' => $page['id'], 'page_name' => $page['name'], 'page_token' => $page['access_token'],
        'ig_id' => $page['instagram_business_account']['id'] ?? null, 'ig_username' => $page['instagram_business_account']['username'] ?? null,
        'pages' => array_map(fn($p) => ['id' => $p['id'], 'name' => $p['name']], $list), 'connected_at' => date('c')]);
    return "Página «{$page['name']}»" . (isset($page['instagram_business_account']) ? " e Instagram @{$page['instagram_business_account']['username']}" : ' (sin Instagram vinculado)');
}
function ig_id(): string { if (!tok('meta')) throw new RuntimeException('Meta no está conectado. Entra en el panel del hub y pulsa «Conectar Meta».'); $id = tok('meta')['ig_id'] ?? null; if (!$id) throw new RuntimeException('No hay cuenta de Instagram profesional vinculada a la página.'); return $id; }
function page_id(): string { return tok('meta')['page_id'] ?? throw new RuntimeException('Meta no está conectado.'); }

/** Crea contenedor de IG, espera a que termine de procesar y publica */
function ig_publish_container(array $params): array {
    $ig = ig_id();
    $c = meta_g('POST', "$ig/media", $params);
    $cid = $c['id'];
    for ($i = 0; $i < 60; $i++) {
        $st = meta_g('GET', $cid, ['fields' => 'status_code,status']);
        if (($st['status_code'] ?? '') === 'FINISHED') break;
        if (($st['status_code'] ?? '') === 'ERROR') throw new RuntimeException('Instagram rechazó el archivo: ' . ($st['status'] ?? ''));
        sleep(5);
    }
    $p = meta_g('POST', "$ig/media_publish", ['creation_id' => $cid]);
    $m = meta_g('GET', $p['id'], ['fields' => 'permalink']);
    return ['media_id' => $p['id'], 'permalink' => $m['permalink'] ?? null];
}

tool('instagram_publicar_imagen', 'Publica una foto en Instagram (JPEG público, 4:5 a 1,91:1).', [
    'image_url' => ['string', 'URL pública de la imagen JPEG', true], 'caption' => ['string', 'Texto de la publicación']],
    fn($a) => ig_publish_container(['image_url' => need($a, 'image_url'), 'caption' => $a['caption'] ?? '']), true);
tool('instagram_publicar_reel', 'Publica un vídeo como Reel en Instagram (MP4 público, 3 s a 15 min).', [
    'video_url' => ['string', 'URL pública del MP4', true], 'caption' => ['string', 'Texto'], 'cover_url' => ['string', 'URL pública de la portada JPEG'],
    'share_to_feed' => ['boolean', 'Mostrar también en el feed (por defecto sí)']],
    function ($a) { $p = ['media_type' => 'REELS', 'video_url' => need($a, 'video_url'), 'caption' => $a['caption'] ?? '', 'share_to_feed' => ($a['share_to_feed'] ?? true) ? 'true' : 'false'];
        if (!empty($a['cover_url'])) $p['cover_url'] = $a['cover_url']; return ig_publish_container($p); }, true);
tool('instagram_publicar_carrusel', 'Publica un carrusel de 2 a 10 imágenes en Instagram.', [
    'image_urls' => ['array', 'URLs públicas de las imágenes, en orden', true, ['items' => ['type' => 'string']]], 'caption' => ['string', 'Texto']],
    function ($a) { $ig = ig_id(); $kids = [];
        foreach (need($a, 'image_urls') as $u) $kids[] = meta_g('POST', "$ig/media", ['image_url' => $u, 'is_carousel_item' => 'true'])['id'];
        return ig_publish_container(['media_type' => 'CAROUSEL', 'children' => implode(',', $kids), 'caption' => $a['caption'] ?? '']); }, true);
tool('instagram_historia', 'Publica una historia de Instagram con una imagen o un vídeo público.', [
    'image_url' => ['string', 'URL pública de imagen'], 'video_url' => ['string', 'URL pública de vídeo']],
    function ($a) { $p = ['media_type' => 'STORIES']; if (!empty($a['video_url'])) $p['video_url'] = $a['video_url']; else $p['image_url'] = need($a, 'image_url'); return ig_publish_container($p); }, true);
tool('instagram_publicaciones', 'Lista las últimas publicaciones de Instagram con me gusta, comentarios y enlace.', [
    'limite' => ['integer', 'Cuántas (por defecto 12)']],
    fn($a) => meta_g('GET', ig_id() . '/media', ['fields' => 'id,caption,media_type,timestamp,like_count,comments_count,permalink', 'limit' => (int)($a['limite'] ?? 12)])['data'] ?? []);
tool('instagram_estadisticas_publicacion', 'Estadísticas de una publicación de Instagram (alcance, guardados, compartidos, reproducciones).', [
    'media_id' => ['string', 'ID de la publicación', true]],
    function ($a) { $id = need($a, 'media_id'); $out = [];
        foreach (['reach', 'saved', 'shares', 'likes', 'comments', 'total_interactions', 'views'] as $m) {
            try { $r = meta_g('GET', "$id/insights", ['metric' => $m]); $out[$m] = $r['data'][0]['values'][0]['value'] ?? null; } catch (Throwable $e) { $out[$m] = null; }
        } return $out; });
tool('instagram_estadisticas_cuenta', 'Estadísticas de la cuenta de Instagram: seguidores y métricas diarias de los últimos días.', [
    'dias' => ['integer', 'Días hacia atrás (máx. 30, por defecto 28)']],
    function ($a) { $ig = ig_id(); $d = min(30, (int)($a['dias'] ?? 28));
        $info = meta_g('GET', $ig, ['fields' => 'username,followers_count,follows_count,media_count']);
        $met = [];
        foreach (['reach', 'profile_views', 'accounts_engaged', 'total_interactions', 'follower_count'] as $m) {
            try { $r = meta_g('GET', "$ig/insights", ['metric' => $m, 'period' => 'day', 'metric_type' => $m === 'reach' || $m === 'follower_count' ? 'time_series' : 'total_value', 'since' => strtotime("-$d days"), 'until' => time()]);
                $met[$m] = $r['data'][0]['values'] ?? $r['data'][0]['total_value'] ?? null; } catch (Throwable $e) { $met[$m] = 'no disponible: ' . $e->getMessage(); }
        } return ['cuenta' => $info, 'metricas' => $met]; });
tool('instagram_comentarios', 'Lista los comentarios de una publicación de Instagram.', ['media_id' => ['string', 'ID de la publicación', true]],
    fn($a) => meta_g('GET', need($a, 'media_id') . '/comments', ['fields' => 'id,text,username,timestamp,replies{id,text,username}'])['data'] ?? []);
tool('instagram_responder_comentario', 'Responde públicamente a un comentario de Instagram.', [
    'comment_id' => ['string', 'ID del comentario', true], 'mensaje' => ['string', 'Texto de la respuesta', true]],
    fn($a) => meta_g('POST', need($a, 'comment_id') . '/replies', ['message' => need($a, 'mensaje')]), true);
tool('instagram_comentar', 'Escribe un comentario en una publicación propia de Instagram.', [
    'media_id' => ['string', 'ID de la publicación', true], 'mensaje' => ['string', 'Texto', true]],
    fn($a) => meta_g('POST', need($a, 'media_id') . '/comments', ['message' => need($a, 'mensaje')]), true);

tool('facebook_publicar', 'Publica un texto (y opcionalmente un enlace) en la página de Facebook. Admite programar.', [
    'mensaje' => ['string', 'Texto', true], 'enlace' => ['string', 'URL a compartir'], 'programar_unix' => ['integer', 'Fecha de publicación (timestamp Unix, entre 10 min y 30 días)']],
    function ($a) { $p = ['message' => need($a, 'mensaje')]; if (!empty($a['enlace'])) $p['link'] = $a['enlace'];
        if (!empty($a['programar_unix'])) { $p['published'] = 'false'; $p['scheduled_publish_time'] = (int)$a['programar_unix']; }
        return meta_g('POST', page_id() . '/feed', $p); }, true);
tool('facebook_publicar_foto', 'Publica una foto en la página de Facebook.', [
    'image_url' => ['string', 'URL pública de la imagen', true], 'mensaje' => ['string', 'Texto'], 'programar_unix' => ['integer', 'Timestamp Unix para programar']],
    function ($a) { $p = ['url' => need($a, 'image_url'), 'caption' => $a['mensaje'] ?? ''];
        if (!empty($a['programar_unix'])) { $p['published'] = 'false'; $p['scheduled_publish_time'] = (int)$a['programar_unix']; }
        return meta_g('POST', page_id() . '/photos', $p); }, true);
tool('facebook_publicar_video', 'Publica un vídeo en la página de Facebook desde una URL pública (sin límite de 10 MB). Admite programar.', [
    'video_url' => ['string', 'URL pública del MP4', true], 'descripcion' => ['string', 'Texto'], 'titulo' => ['string', 'Título'], 'programar_unix' => ['integer', 'Timestamp Unix para programar']],
    function ($a) { $p = ['file_url' => need($a, 'video_url'), 'description' => $a['descripcion'] ?? '', 'title' => $a['titulo'] ?? ''];
        if (!empty($a['programar_unix'])) { $p['published'] = 'false'; $p['scheduled_publish_time'] = (int)$a['programar_unix']; }
        return meta_g('POST', page_id() . '/videos', $p); }, true);
tool('facebook_publicaciones', 'Últimas publicaciones de la página de Facebook con reacciones y comentarios.', ['limite' => ['integer', 'Cuántas (por defecto 10)']],
    fn($a) => meta_g('GET', page_id() . '/posts', ['fields' => 'id,message,created_time,permalink_url,reactions.summary(true).limit(0),comments.summary(true).limit(0),shares', 'limit' => (int)($a['limite'] ?? 10)])['data'] ?? []);
tool('facebook_estadisticas', 'Estadísticas diarias de la página de Facebook (alcance, interacciones, seguidores).', ['dias' => ['integer', 'Días hacia atrás (por defecto 28)']],
    function ($a) { $d = (int)($a['dias'] ?? 28); $out = ['pagina' => meta_g('GET', page_id(), ['fields' => 'name,followers_count,fan_count'])];
        foreach (['page_impressions_unique', 'page_post_engagements', 'page_follows', 'page_views_total'] as $m) {
            try { $out[$m] = meta_g('GET', page_id() . '/insights', ['metric' => $m, 'period' => 'day', 'since' => strtotime("-$d days"), 'until' => time()])['data'][0]['values'] ?? null; }
            catch (Throwable $e) { $out[$m] = 'no disponible'; } } return $out; });
tool('facebook_comentarios', 'Comentarios de una publicación de la página de Facebook.', ['post_id' => ['string', 'ID de la publicación', true]],
    fn($a) => meta_g('GET', need($a, 'post_id') . '/comments', ['fields' => 'id,message,from,created_time'])['data'] ?? []);
tool('facebook_responder_comentario', 'Responde a un comentario en la página de Facebook.', ['comment_id' => ['string', 'ID del comentario', true], 'mensaje' => ['string', 'Texto', true]],
    fn($a) => meta_g('POST', need($a, 'comment_id') . '/comments', ['message' => need($a, 'mensaje')]), true);

tool('meta_diagnostico', 'Comprueba la conexión con Meta: permisos concedidos e Instagram vinculado a la página. Si encuentra la cuenta de Instagram, la guarda.', [],
    function ($a) { $t = tok('meta'); if (!$t) throw new RuntimeException('Meta no está conectado.');
        $perm = meta_g('GET', 'me/permissions', [], $t['user_token'])['data'] ?? [];
        $page = meta_g('GET', $t['page_id'], ['fields' => 'name,instagram_business_account{id,username},connected_instagram_account{id,username}']);
        $ig = $page['instagram_business_account'] ?? $page['connected_instagram_account'] ?? null;
        if ($ig) { $t['ig_id'] = $ig['id']; $t['ig_username'] = $ig['username'] ?? null; tok_set('meta', $t); }
        return ['permisos' => array_map(fn($p) => $p['permission'] . ':' . $p['status'], $perm), 'pagina' => $page, 'instagram_guardado' => $ig]; });
