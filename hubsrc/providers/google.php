<?php
// Google: Search Console, Merchant Center, Business Profile, Analytics 4
declare(strict_types=1);
function google_scopes(): string {
    return implode(' ', ['openid', 'email', 'https://www.googleapis.com/auth/webmasters.readonly', 'https://www.googleapis.com/auth/content',
        'https://www.googleapis.com/auth/business.manage', 'https://www.googleapis.com/auth/analytics.readonly']);
}
function google_auth_url(): string {
    session_boot(); $_SESSION['oauth_state'] = bin2hex(random_bytes(12));
    return 'https://accounts.google.com/o/oauth2/v2/auth?' . http_build_query([
        'client_id' => cfg('google_client_id'), 'redirect_uri' => base_url() . '/oauth/callback.php?p=google', 'response_type' => 'code',
        'scope' => google_scopes(), 'access_type' => 'offline', 'prompt' => 'consent', 'include_granted_scopes' => 'true', 'state' => $_SESSION['oauth_state']]);
}
function google_callback(string $code): string {
    $t = http_ok(http('POST', 'https://oauth2.googleapis.com/token', ['form' => ['code' => $code, 'client_id' => cfg('google_client_id'),
        'client_secret' => cfg('google_client_secret'), 'redirect_uri' => base_url() . '/oauth/callback.php?p=google', 'grant_type' => 'authorization_code']]), 'Google token');
    if (empty($t['refresh_token'])) throw new RuntimeException('Google no ha devuelto refresh_token. Quita el acceso en myaccount.google.com/permissions y vuelve a conectar.');
    $email = '';
    if (!empty($t['id_token'])) { $p = json_decode(base64_decode(strtr(explode('.', $t['id_token'])[1] ?? '', '-_', '+/')), true); $email = $p['email'] ?? ''; }
    tok_set('google', ['refresh_token' => $t['refresh_token'], 'access_token' => $t['access_token'], 'expires' => time() + (int)$t['expires_in'] - 60, 'email' => $email, 'connected_at' => date('c')]);
    return "Google ($email)";
}
function google_token(): string {
    $t = tok('google');
    if (empty($t['refresh_token'])) throw new RuntimeException('Google no está conectado. Entra en el panel del hub y pulsa «Conectar Google».');
    if (($t['expires'] ?? 0) > time()) return $t['access_token'];
    $n = http_ok(http('POST', 'https://oauth2.googleapis.com/token', ['form' => ['client_id' => cfg('google_client_id'), 'client_secret' => cfg('google_client_secret'),
        'refresh_token' => $t['refresh_token'], 'grant_type' => 'refresh_token']]), 'Google renovar token');
    $t['access_token'] = $n['access_token']; $t['expires'] = time() + (int)$n['expires_in'] - 60; tok_set('google', $t);
    return $t['access_token'];
}
function gapi(string $method, string $url, array $opt = []) {
    $opt['headers'] = array_merge($opt['headers'] ?? [], ['Authorization: Bearer ' . google_token()]);
    return http_ok(http($method, $url, $opt), 'Google ' . parse_url($url, PHP_URL_HOST));
}
function d_ago(int $n): string { return date('Y-m-d', strtotime("-$n days")); }

// ---- Search Console ----
function gsc_site(?string $s): string { return $s ?: cfg('gsc_site', 'sc-domain:innovai.es'); }
tool('search_console_sitios', 'Lista las propiedades de Search Console a las que tienes acceso.', [],
    fn($a) => gapi('GET', 'https://www.googleapis.com/webmasters/v3/sites')['siteEntry'] ?? []);
tool('search_console_consultas', 'Rendimiento en Google Search: clics, impresiones, CTR y posición por consulta, página, fecha, país o dispositivo.', [
    'dias' => ['integer', 'Días hacia atrás (por defecto 28; los datos llevan ~2 días de retraso)'], 'dimension' => ['string', 'query | page | date | country | device (por defecto query)'],
    'limite' => ['integer', 'Filas (por defecto 25)'], 'sitio' => ['string', 'Propiedad, p. ej. sc-domain:innovai.es (por defecto la configurada)'], 'contiene' => ['string', 'Filtrar consultas que contengan este texto']],
    function ($a) { $body = ['startDate' => d_ago((int)($a['dias'] ?? 28) + 2), 'endDate' => d_ago(2), 'dimensions' => [$a['dimension'] ?? 'query'], 'rowLimit' => (int)($a['limite'] ?? 25)];
        if (!empty($a['contiene'])) $body['dimensionFilterGroups'] = [['filters' => [['dimension' => 'query', 'operator' => 'contains', 'expression' => $a['contiene']]]]];
        return gapi('POST', 'https://www.googleapis.com/webmasters/v3/sites/' . rawurlencode(gsc_site($a['sitio'] ?? null)) . '/searchAnalytics/query', ['json' => $body])['rows'] ?? []; });
tool('search_console_inspeccionar_url', 'Comprueba si una URL está indexada en Google y por qué no, si es el caso.', [
    'url' => ['string', 'URL completa a inspeccionar', true], 'sitio' => ['string', 'Propiedad de Search Console']],
    fn($a) => gapi('POST', 'https://searchconsole.googleapis.com/v1/urlInspection/index:inspect', ['json' => ['inspectionUrl' => need($a, 'url'), 'siteUrl' => gsc_site($a['sitio'] ?? null), 'languageCode' => 'es-ES']])['inspectionResult'] ?? []);

// ---- Merchant Center (Merchant API) ----
function merchant_id(?string $id): string { $m = $id ?: cfg('merchant_id'); if (!$m) throw new RuntimeException('Falta el ID de Merchant Center: ponlo en la configuración del hub.'); return $m; }
tool('merchant_productos', 'Productos de Google Merchant Center con su estado (aprobado, rechazado y motivos).', ['merchant_id' => ['string', 'ID de cuenta de Merchant (por defecto el configurado)']],
    function ($a) { $mid = merchant_id($a['merchant_id'] ?? null);
        try { $r = gapi('GET', "https://merchantapi.googleapis.com/products/v1/accounts/$mid/products", ['query' => ['pageSize' => 250]]); }
        catch (RuntimeException $e) {
            if (!str_contains($e->getMessage(), 'not registered')) throw $e;
            // Registro único del proyecto de Google Cloud en la cuenta de Merchant (lo exige Merchant API).
            $email = tok('google')['email'] ?? '';
            gapi('POST', "https://merchantapi.googleapis.com/accounts/v1/accounts/$mid/developerRegistration:registerGcp", ['json' => $email ? ['developerEmail' => $email] : (object)[]]);
            throw new RuntimeException('He registrado el hub en tu cuenta de Merchant. Google tarda unos 5 minutos en activarlo: vuelve a probar después.');
        }
        return array_map(fn($p) => ['id' => $p['offerId'] ?? $p['name'], 'titulo' => $p['productAttributes']['title'] ?? $p['attributes']['title'] ?? null,
            'estado' => $p['productStatus']['destinationStatuses'] ?? null, 'problemas' => $p['productStatus']['itemLevelIssues'] ?? []], $r['products'] ?? []); });

// ---- Business Profile ----
function gbp_account(): string {
    if ($a = cfg('gbp_account')) return $a;
    $r = gapi('GET', 'https://mybusinessaccountmanagement.googleapis.com/v1/accounts');
    $acc = $r['accounts'][0]['name'] ?? throw new RuntimeException('No hay cuentas de Business Profile.'); cfg_set(['gbp_account' => $acc]); return $acc;
}
function gbp_location(?string $l): string { $l = $l ?: cfg('gbp_location'); if (!$l) throw new RuntimeException('Elige la ficha con business_fichas y guárdala en la configuración (locations/…).'); return str_starts_with($l, 'locations/') ? $l : "locations/$l"; }
tool('business_fichas', 'Lista tus fichas de Google Business Profile (para saber el ID de cada una).', [],
    fn($a) => gapi('GET', 'https://mybusinessbusinessinformation.googleapis.com/v1/' . gbp_account() . '/locations', ['query' => ['readMask' => 'name,title,storefrontAddress,websiteUri,phoneNumbers', 'pageSize' => 100]])['locations'] ?? []);
tool('business_resenas', 'Reseñas de una ficha de Google Business (las más recientes primero).', ['ficha' => ['string', 'locations/… (por defecto la configurada)'], 'limite' => ['integer', 'Cuántas (por defecto 20)']],
    fn($a) => gapi('GET', 'https://mybusiness.googleapis.com/v4/' . gbp_account() . '/' . gbp_location($a['ficha'] ?? null) . '/reviews', ['query' => ['pageSize' => (int)($a['limite'] ?? 20), 'orderBy' => 'updateTime desc']]));
tool('business_responder_resena', 'Publica o cambia la respuesta del propietario a una reseña de Google.', [
    'review_id' => ['string', 'ID de la reseña (último tramo de name)', true], 'respuesta' => ['string', 'Texto de la respuesta', true], 'ficha' => ['string', 'locations/…']],
    fn($a) => gapi('PUT', 'https://mybusiness.googleapis.com/v4/' . gbp_account() . '/' . gbp_location($a['ficha'] ?? null) . '/reviews/' . need($a, 'review_id') . '/reply', ['json' => ['comment' => need($a, 'respuesta')]]), true);
tool('business_publicar', 'Crea una publicación (novedad) en la ficha de Google Business, con imagen y botón opcionales.', [
    'texto' => ['string', 'Texto (máx. 1.500 caracteres)', true], 'image_url' => ['string', 'URL pública de imagen'], 'boton' => ['string', 'LEARN_MORE | BOOK | ORDER | SHOP | SIGN_UP | CALL'],
    'enlace' => ['string', 'URL del botón'], 'ficha' => ['string', 'locations/…']],
    function ($a) { $b = ['languageCode' => 'es', 'summary' => need($a, 'texto'), 'topicType' => 'STANDARD'];
        if (!empty($a['image_url'])) $b['media'] = [['mediaFormat' => 'PHOTO', 'sourceUrl' => $a['image_url']]];
        if (!empty($a['boton'])) $b['callToAction'] = array_filter(['actionType' => $a['boton'], 'url' => $a['enlace'] ?? null]);
        return gapi('POST', 'https://mybusiness.googleapis.com/v4/' . gbp_account() . '/' . gbp_location($a['ficha'] ?? null) . '/localPosts', ['json' => $b]); }, true);
tool('business_estadisticas', 'Estadísticas diarias de la ficha de Google: vistas en Búsqueda y Maps, llamadas, clics a la web y rutas.', [
    'dias' => ['integer', 'Días hacia atrás (por defecto 28)'], 'ficha' => ['string', 'locations/…']],
    function ($a) { $d = (int)($a['dias'] ?? 28); $s = strtotime("-" . ($d + 3) . " days"); $e = strtotime('-3 days');
        $metrics = ['BUSINESS_IMPRESSIONS_DESKTOP_SEARCH', 'BUSINESS_IMPRESSIONS_MOBILE_SEARCH', 'BUSINESS_IMPRESSIONS_DESKTOP_MAPS', 'BUSINESS_IMPRESSIONS_MOBILE_MAPS', 'CALL_CLICKS', 'WEBSITE_CLICKS', 'BUSINESS_DIRECTION_REQUESTS'];
        $q = 'dailyMetrics=' . implode('&dailyMetrics=', $metrics) . '&' . http_build_query(['dailyRange.startDate.year' => date('Y', $s), 'dailyRange.startDate.month' => date('n', $s), 'dailyRange.startDate.day' => date('j', $s),
            'dailyRange.endDate.year' => date('Y', $e), 'dailyRange.endDate.month' => date('n', $e), 'dailyRange.endDate.day' => date('j', $e)]);
        $r = gapi('GET', 'https://businessprofileperformance.googleapis.com/v1/' . gbp_location($a['ficha'] ?? null) . ':fetchMultiDailyMetricsTimeSeries?' . $q);
        $tot = [];
        foreach ($r['multiDailyMetricTimeSeries'][0]['dailyMetricTimeSeries'] ?? [] as $ts) { $sum = 0; foreach ($ts['timeSeries']['datedValues'] ?? [] as $v) $sum += (int)($v['value'] ?? 0); $tot[$ts['dailyMetric']] = $sum; }
        return ['periodo' => date('Y-m-d', $s) . ' a ' . date('Y-m-d', $e), 'totales' => $tot]; });

// ---- Analytics 4 ----
tool('analytics_informe', 'Informe de Google Analytics 4: usuarios, sesiones, conversiones… por la dimensión que elijas.', [
    'propiedad' => ['string', 'ID numérico de la propiedad GA4 (por defecto el configurado)'], 'dias' => ['integer', 'Días hacia atrás (por defecto 28)'],
    'metricas' => ['array', 'p. ej. ["activeUsers","sessions","conversions"]', false, ['items' => ['type' => 'string']]],
    'dimensiones' => ['array', 'p. ej. ["date"] o ["sessionDefaultChannelGroup"] o ["pagePath"]', false, ['items' => ['type' => 'string']]], 'limite' => ['integer', 'Filas (por defecto 50)']],
    function ($a) { $p = $a['propiedad'] ?? cfg('ga4_property'); if (!$p) throw new RuntimeException('Falta el ID de la propiedad GA4 en la configuración.');
        $body = ['dateRanges' => [['startDate' => ((int)($a['dias'] ?? 28)) . 'daysAgo', 'endDate' => 'today']],
            'metrics' => array_map(fn($m) => ['name' => $m], $a['metricas'] ?? ['activeUsers', 'sessions', 'screenPageViews']),
            'dimensions' => array_map(fn($d) => ['name' => $d], $a['dimensiones'] ?? ['date']), 'limit' => (int)($a['limite'] ?? 50)];
        $r = gapi('POST', "https://analyticsdata.googleapis.com/v1beta/properties/$p:runReport", ['json' => $body]);
        $h = array_merge(array_column($r['dimensionHeaders'] ?? [], 'name'), array_column($r['metricHeaders'] ?? [], 'name'));
        return array_map(fn($row) => array_combine($h, array_merge(array_column($row['dimensionValues'] ?? [], 'value'), array_column($row['metricValues'] ?? [], 'value'))), $r['rows'] ?? []); });
