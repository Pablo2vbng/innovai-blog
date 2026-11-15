<?php
// innovAI · Test GEO: analiza si una web está preparada para que las IA (ChatGPT, Gemini, Perplexity…) la encuentren y la entiendan.
declare(strict_types=1);

const GEO_UA = 'Mozilla/5.0 (compatible; innovAI-TestGEO/1.0; +https://innovai.es/test-geo/)';
const GEO_MAX_BYTES = 3_000_000;

final class GeoError extends RuntimeException {}

/** Normaliza lo que escribe el usuario a una URL https/http válida. */
function geo_normalize_url(string $in): string {
    $u = trim($in);
    if ($u === '' || mb_strlen($u) > 300) throw new GeoError('Escribe la dirección de tu web, por ejemplo: minegocio.es');
    if (!preg_match('~^https?://~i', $u)) $u = 'https://' . $u;
    $p = parse_url($u);
    if (!$p || empty($p['host']) || !preg_match('/^[a-z0-9.-]+\.[a-z]{2,}$/i', $p['host']) && getenv('TG_TEST') !== '1') throw new GeoError('Esa dirección no parece una web válida. Prueba con algo como minegocio.es');
    return strtolower($p['scheme']) . '://' . strtolower($p['host']) . (isset($p['port']) ? ':' . $p['port'] : '') . ($p['path'] ?? '/') . (isset($p['query']) ? '?' . $p['query'] : '');
}

/** Evita que se use la herramienta para entrar en redes internas (SSRF). */
function geo_host_is_public(string $host): bool {
    if (getenv("TG_TEST") === "1" && $host === "127.0.0.1") return true;
    $ips = @gethostbynamel($host) ?: [];
    if (!$ips) return false;
    foreach ($ips as $ip) {
        if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) return false;
    }
    return true;
}

/** Descarga una URL siguiendo hasta 4 redirecciones y comprobando cada salto. */
function geo_fetch(string $url, int $timeout = 12): array {
    for ($hop = 0; $hop < 5; $hop++) {
        $host = parse_url($url, PHP_URL_HOST) ?: '';
        if (!geo_host_is_public($host)) throw new GeoError('No hemos podido encontrar esa web. Revisa que la dirección esté bien escrita.');
        $body = ''; $headers = [];
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => false, CURLOPT_FOLLOWLOCATION => false, CURLOPT_TIMEOUT => $timeout, CURLOPT_CONNECTTIMEOUT => 6,
            CURLOPT_USERAGENT => GEO_UA, CURLOPT_PROTOCOLS => CURLPROTO_HTTP | CURLPROTO_HTTPS, CURLOPT_ENCODING => '',
            CURLOPT_HTTPHEADER => ['Accept: text/html,application/xhtml+xml,text/plain;q=0.9,*/*;q=0.5', 'Accept-Language: es-ES,es;q=0.9'],
            CURLOPT_HEADERFUNCTION => function ($c, $h) use (&$headers) { $p = strpos($h, ':'); if ($p) $headers[strtolower(trim(substr($h, 0, $p)))] = trim(substr($h, $p + 1)); return strlen($h); },
            CURLOPT_WRITEFUNCTION => function ($c, $d) use (&$body) { $body .= $d; return strlen($body) > GEO_MAX_BYTES ? 0 : strlen($d); },
        ]);
        curl_exec($ch);
        $code = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $err = curl_errno($ch);
        curl_close($ch);
        if ($err && $err !== CURLE_WRITE_ERROR) {
            if ($hop === 0 && str_starts_with($url, 'https://') && in_array($err, [CURLE_SSL_CONNECT_ERROR, 60, 35, 51], true)) {
                return ['code' => 0, 'url' => $url, 'body' => '', 'headers' => [], 'ssl_error' => true];
            }
            throw new GeoError('No hemos podido abrir esa web (no responde o tarda demasiado). Revisa la dirección y vuelve a probar.');
        }
        if ($code >= 300 && $code < 400 && !empty($headers['location'])) {
            $loc = $headers['location'];
            if (!preg_match('~^https?://~i', $loc)) { $b = parse_url($url); $loc = $b['scheme'] . '://' . $b['host'] . (isset($b['port']) ? ':' . $b['port'] : '') . (str_starts_with($loc, '/') ? $loc : '/' . $loc); }
            $url = $loc; continue;
        }
        return ['code' => $code, 'url' => $url, 'body' => $body, 'headers' => $headers];
    }
    throw new GeoError('La web redirige demasiadas veces y no hemos podido analizarla.');
}

function geo_text(string $s): string { return trim(preg_replace('/\s+/u', ' ', html_entity_decode($s, ENT_QUOTES | ENT_HTML5, 'UTF-8'))); }

/** Recorre los JSON-LD y devuelve todos los nodos con su @type. */
function geo_jsonld_nodes(DOMXPath $xp): array {
    $nodes = [];
    $walk = function ($v) use (&$walk, &$nodes) {
        if (!is_array($v)) return;
        if (isset($v['@type'])) $nodes[] = $v;
        foreach ($v as $k => $c) if (is_array($c)) $walk($c);
    };
    foreach ($xp->query('//script[@type="application/ld+json"]') as $s) {
        $j = json_decode(trim($s->textContent), true);
        if ($j !== null) $walk($j);
    }
    return $nodes;
}
function geo_types(array $nodes): array {
    $t = [];
    foreach ($nodes as $n) foreach ((array)$n['@type'] as $x) $t[] = strtolower((string)$x);
    return array_unique($t);
}

/** Analiza la web y devuelve puntuación y puntos de mejora. */
function geo_analyze(string $input): array {
    $url = geo_normalize_url($input);
    $r = geo_fetch($url);
    $sslBroken = !empty($r['ssl_error']);
    if ($sslBroken) $r = geo_fetch(preg_replace('~^https://~', 'http://', $url));
    if ($r['code'] >= 400 || $r['code'] === 0) throw new GeoError('La web ha respondido con un error (' . $r['code'] . '). Revisa la dirección y vuelve a probar.');
    $final = $r['url'];
    $p = parse_url($final);
    $origin = $p['scheme'] . '://' . $p['host'] . (isset($p['port']) ? ':' . $p['port'] : '');
    $html = $r['body'];
    if (stripos($r['headers']['content-type'] ?? 'text/html', 'html') === false) throw new GeoError('Esa dirección no lleva a una página web.');

    libxml_use_internal_errors(true);
    $dom = new DOMDocument();
    $dom->loadHTML('<?xml encoding="UTF-8">' . $html, LIBXML_NOWARNING | LIBXML_NOERROR | LIBXML_NONET);
    $xp = new DOMXPath($dom);

    $title = geo_text($xp->evaluate('string(//title)'));
    $desc = geo_text($xp->evaluate('string(//meta[translate(@name,"DESCRIPTION","description")="description"]/@content)'));
    $h1s = array_map(fn($n) => geo_text($n->textContent), iterator_to_array($xp->query('//h1')));
    $h2c = $xp->query('//h2|//h3')->length;
    $lang = $xp->evaluate('string(/html/@lang)');
    $viewport = $xp->evaluate('string(//meta[@name="viewport"]/@content)');
    $og = $xp->query('//meta[starts-with(@property,"og:")]')->length;
    $nodes = geo_jsonld_nodes($xp);
    foreach ($xp->query('//script|//style|//noscript|//svg') as $n) $n->parentNode->removeChild($n);
    $bodyText = geo_text($xp->evaluate('string(//body)'));
    $words = count(preg_split('/\s+/u', $bodyText, -1, PREG_SPLIT_NO_EMPTY));
    $types = geo_types($nodes);
    $links = array_map(fn($a) => strtolower($a->getAttribute('href') . ' ' . geo_text($a->textContent)), iterator_to_array($xp->query('//a[@href]')));
    $allLinks = implode(' ', $links);

    // Datos de negocio en el esquema
    $biz = null;
    $bizTypes = ['localbusiness', 'organization', 'professionalservice', 'store', 'restaurant', 'dentist', 'medicalbusiness', 'medicalclinic', 'legalservice', 'attorney', 'realestateagent', 'beautysalon', 'hairsalon', 'healthandbeautybusiness', 'homeandconstructionbusiness', 'autorepair', 'educationalorganization', 'accountingservice', 'financialservice', 'physician', 'physiotherapy', 'optician', 'foodestablishment', 'lodgingbusiness', 'sportsactivitylocation', 'corporation', 'onlinestore'];
    foreach ($nodes as $n) foreach ((array)$n['@type'] as $t) if (in_array(strtolower((string)$t), $bizTypes, true)) { $biz = $n; break 2; }
    $hasAddr = $biz && !empty($biz['address']);
    $hasTel = $biz && !empty($biz['telephone']);
    $hasHours = $biz && (!empty($biz['openingHours']) || !empty($biz['openingHoursSpecification']));
    $hasSameAs = $biz && !empty($biz['sameAs']);

    // Contacto visible
    $telVisible = (bool)preg_match('/(\+34[\s.-]?)?[6789]\d{2}[\s.-]?\d{2,3}[\s.-]?\d{2,3}[\s.-]?\d{0,3}/', $bodyText) || str_contains($allLinks, 'tel:');
    $addrVisible = (bool)preg_match('/\b(c\/|calle|avda|avenida|plaza|pza|carrer|av\.|camí|partida|pol[ií]gono)\b/iu', $bodyText) || (bool)preg_match('/\b\d{5}\b/', $bodyText);

    // FAQ
    $hasFaqSchema = in_array('faqpage', $types, true);
    $hasFaqText = (bool)preg_match('/preguntas frecuentes|faq|dudas frecuentes|preguntes freqüents/iu', $bodyText . ' ' . $allLinks);
    $questions = preg_match_all('/¿[^?]{8,140}\?/u', $bodyText);

    // Sobre nosotros
    $hasAbout = (bool)preg_match('/(sobre[- ]nosotros|quienes[- ]somos|quiénes somos|nosotros|about|equipo|qui[eé]n soy|sobre[- ]m[ií]|qui som)/iu', $allLinks);

    // robots.txt: rastreadores de IA
    $aiBots = ['GPTBot' => 'ChatGPT (OpenAI)', 'OAI-SearchBot' => 'ChatGPT Search', 'ChatGPT-User' => 'ChatGPT', 'PerplexityBot' => 'Perplexity', 'Google-Extended' => 'Gemini (Google)', 'ClaudeBot' => 'Claude', 'Applebot-Extended' => 'Apple Intelligence', 'CCBot' => 'Common Crawl'];
    $blocked = []; $blockAll = false; $robotsFound = false; $sitemapInRobots = false;
    try {
        $rb = geo_fetch($origin . '/robots.txt', 8);
        if ($rb['code'] === 200 && stripos($rb['headers']['content-type'] ?? 'text/plain', 'html') === false) {
            $robotsFound = true;
            $groups = []; $cur = []; $last = '';
            foreach (preg_split('/\R/', $rb['body']) as $line) {
                $line = trim(preg_replace('/#.*/', '', $line));
                if ($line === '' || !str_contains($line, ':')) continue;
                [$k, $v] = array_map('trim', explode(':', $line, 2));
                $k = strtolower($k);
                if ($k === 'sitemap') { $sitemapInRobots = true; continue; }
                if ($k === 'user-agent') { if ($last !== 'user-agent') { if ($cur) $groups[] = $cur; $cur = ['ua' => [], 'dis' => []]; } $cur['ua'][] = strtolower($v); }
                elseif ($k === 'disallow' && $cur) $cur['dis'][] = $v;
                $last = $k;
            }
            if ($cur) $groups[] = $cur;
            $blocksRoot = fn($g) => in_array('/', $g['dis'], true);
            foreach ($aiBots as $bot => $label) {
                $specific = null;
                foreach ($groups as $g) if (in_array(strtolower($bot), $g['ua'], true)) $specific = $g;
                if ($specific ? $blocksRoot($specific) : false) $blocked[] = $label;
            }
            foreach ($groups as $g) if (in_array('*', $g['ua'], true) && $blocksRoot($g)) $blockAll = true;
        }
    } catch (Throwable $e) {}

    $hasSitemap = $sitemapInRobots;
    if (!$hasSitemap) { try { $sm = geo_fetch($origin . '/sitemap.xml', 8); $hasSitemap = $sm['code'] === 200 && (stripos($sm['body'], '<urlset') !== false || stripos($sm['body'], '<sitemapindex') !== false); } catch (Throwable $e) {} }
    $hasLlms = false;
    try { $ll = geo_fetch($origin . '/llms.txt', 6); $hasLlms = $ll['code'] === 200 && strlen(trim($ll['body'])) > 40 && stripos($ll['headers']['content-type'] ?? '', 'html') === false; } catch (Throwable $e) {}

    $https = str_starts_with($final, 'https://') && !$sslBroken;

    // ---------- Puntos (peso total 100) ----------
    $c = [];
    $add = function (string $id, string $titulo, int $peso, bool|float $ok, string $bien, string $mejora) use (&$c) {
        $f = is_bool($ok) ? ($ok ? 1.0 : 0.0) : max(0.0, min(1.0, $ok));
        $c[] = ['id' => $id, 'titulo' => $titulo, 'peso' => $peso, 'puntos' => (int)round($peso * $f), 'estado' => $f >= 1 ? 'ok' : ($f > 0 || $peso === 0 ? 'regular' : 'mal'), 'texto' => $f >= 1 ? $bien : $mejora];
    };

    $botsMsg = $blockAll ? 'Tu robots.txt bloquea a todos los rastreadores: ni Google ni las IA pueden leer tu web.' : ($blocked ? 'Tu robots.txt impide leer tu web a: ' . implode(', ', array_unique($blocked)) . '. Si quieres que te recomienden, deben poder leerla.' : '');
    $add('bots', 'Las IA pueden leer tu web', 14, !$blockAll && !$blocked, 'Tu web no bloquea a los rastreadores de ChatGPT, Gemini o Perplexity.', $botsMsg);

    $add('schema', 'Ficha de negocio para máquinas (datos estructurados)', 14, $biz ? 1.0 : ($nodes ? 0.4 : 0.0),
        'Tu web incluye datos estructurados de negocio (schema.org), el formato que usan buscadores e IA para entender quién eres.',
        $nodes ? 'Tienes algún dato estructurado, pero no una ficha de tu negocio (LocalBusiness u Organization) con nombre, actividad y zona. Es lo que mejor entienden las IA.' : 'Tu web no tiene datos estructurados (schema.org). Son una «ficha» invisible que explica a Google y a las IA qué haces, dónde y cómo contactarte.');

    $napScore = ($hasAddr ? .35 : 0) + ($hasTel ? .35 : 0) + ($hasHours ? .15 : 0) + ($hasSameAs ? .15 : 0);
    $add('nap', 'Dirección, teléfono y horario en la ficha', 10, $biz ? $napScore : 0.0,
        'Tu ficha de negocio incluye dirección, teléfono, horario y enlaces a tus perfiles.',
        $biz ? 'A tu ficha de negocio le faltan datos: ' . implode(', ', array_filter([$hasAddr ? '' : 'dirección', $hasTel ? '' : 'teléfono', $hasHours ? '' : 'horario', $hasSameAs ? '' : 'enlaces a tus redes y a Google'])) . '. Sin ellos, a la IA le cuesta recomendarte para búsquedas de tu zona.' : 'Como no hay ficha de negocio, las IA no tienen tu dirección, teléfono y horario en un formato que puedan leer con seguridad.');

    $add('contacto', 'Datos de contacto visibles', 8, ($telVisible ? .5 : 0) + ($addrVisible ? .5 : 0),
        'Tu teléfono y tu dirección aparecen en la página, así que cualquiera (persona o IA) sabe cómo y dónde encontrarte.',
        'En la página principal no aparece ' . implode(' ni ', array_filter([$telVisible ? '' : 'un teléfono', $addrVisible ? '' : 'una dirección o código postal'])) . '. Las IA dan más confianza a los negocios con datos de contacto claros y coherentes.');

    $titleOk = mb_strlen($title) >= 25 && mb_strlen($title) <= 70;
    $add('title', 'Título de la página', 7, $title === '' ? 0.0 : ($titleOk ? 1.0 : 0.5),
        'Tu título («' . mb_substr($title, 0, 70) . '») tiene buena longitud.',
        $title === '' ? 'Tu página no tiene título. Es lo primero que leen Google y las IA para saber de qué va.' : 'Tu título («' . mb_substr($title, 0, 70) . '») ' . (mb_strlen($title) < 25 ? 'es demasiado corto' : 'es demasiado largo') . '. Lo ideal: qué haces + dónde + tu marca, en 30-65 caracteres.');

    $add('desc', 'Descripción de la página', 6, $desc !== '' && mb_strlen($desc) >= 70 ? 1.0 : ($desc !== '' ? 0.5 : 0.0),
        'Tienes una descripción clara de tu página.',
        $desc === '' ? 'Falta la meta descripción: un resumen de 1-2 frases de qué haces y para quién, que usan buscadores e IA.' : 'Tu descripción es muy corta. Explica en 1-2 frases qué haces, para quién y en qué zona.');

    $add('h1', 'Encabezado principal claro', 6, count($h1s) === 1 && mb_strlen($h1s[0]) >= 8 ? 1.0 : (count($h1s) > 0 ? 0.5 : 0.0),
        'Tu página tiene un encabezado principal claro: «' . mb_substr($h1s[0] ?? '', 0, 80) . '».',
        count($h1s) === 0 ? 'Tu página no tiene encabezado principal (H1). Debe decir en una frase qué haces.' : 'Tu página tiene ' . count($h1s) . ' encabezados principales o uno muy corto. Lo mejor es uno solo que diga qué haces y dónde.');

    $add('texto', 'Contenido que explica qué haces', 10, $words >= 400 ? 1.0 : ($words >= 200 ? 0.6 : ($words >= 80 ? 0.3 : 0.0)),
        'Tu página tiene contenido suficiente (unas ' . $words . ' palabras) para que se entienda qué ofreces.',
        'Tu página principal tiene poco texto (unas ' . $words . ' palabras). Las IA recomiendan a quien explica bien sus servicios, para quién son y en qué zona trabaja.');

    $add('faq', 'Preguntas frecuentes', 9, $hasFaqSchema ? 1.0 : (($hasFaqText || $questions >= 3) ? 0.6 : 0.0),
        'Tienes preguntas frecuentes marcadas para buscadores. Es el formato que más citan las IA en sus respuestas.',
        ($hasFaqText || $questions >= 3) ? 'Tienes preguntas y respuestas, pero sin marcarlas como FAQ para buscadores (FAQPage). Marcadas, las IA las citan mucho más.' : 'No hemos visto preguntas frecuentes. Responder a las dudas reales de tus clientes («¿cuánto tarda?», «¿trabajáis en…?») es de lo que más ayuda a aparecer en las respuestas de la IA.');

    $add('estructura', 'Información ordenada por apartados', 5, $h2c >= 3 ? 1.0 : ($h2c >= 1 ? 0.5 : 0.0),
        'Tu contenido está ordenado en apartados con subtítulos, fácil de leer para personas e IA.',
        'Tu página tiene pocos subtítulos. Ordenar la información en apartados (servicios, zona, preguntas…) ayuda a que la IA la entienda y la cite.');

    $add('about', 'Quién está detrás del negocio', 4, $hasAbout,
        'Tienes una página sobre vosotros. La experiencia y la confianza cuentan para que la IA te recomiende.',
        'No hemos encontrado una página de «quiénes somos». Contar quién está detrás y su experiencia da confianza a clientes y a la IA.');

    $add('https', 'Conexión segura (https)', 4, $https, 'Tu web usa conexión segura (https).', 'Tu web no usa conexión segura (https) o su certificado falla. Muchos navegadores la marcan como «no segura».');

    $add('sitemap', 'Mapa del sitio', 3, $hasSitemap, 'Tienes mapa del sitio (sitemap), que ayuda a que encuentren todas tus páginas.', 'No hemos encontrado mapa del sitio (sitemap.xml). Ayuda a que buscadores y rastreadores de IA encuentren todas tus páginas.');

    $add('llms', 'Archivo llms.txt (opcional)', 0, $hasLlms, 'Tienes un archivo llms.txt con un resumen para las IA. ¡Bien!', 'Opcional: un archivo llms.txt con un resumen de tu negocio es una práctica nueva que algunas IA empiezan a leer.');

    $total = array_sum(array_column($c, 'puntos'));
    $max = array_sum(array_column($c, 'peso'));
    $score = (int)round($total * 100 / max(1, $max));
    $nivel = $score >= 80 ? 'Bien preparada' : ($score >= 55 ? 'A medio camino' : 'Poco preparada');
    $resumen = $score >= 80 ? 'Tu web tiene una buena base para que las IA la entiendan. Puliendo los detalles de abajo puedes destacar más.' :
        ($score >= 55 ? 'Tu web tiene parte del trabajo hecho, pero le faltan piezas importantes para que ChatGPT y otras IA la entiendan y la tengan en cuenta.' :
        'A tu web le faltan bastantes de las señales que usan las IA para entender un negocio. Con unos cambios concretos puede mejorar mucho.');

    usort($c, fn($a, $b) => [$a['estado'] === 'ok', -$a['peso']] <=> [$b['estado'] === 'ok', -$b['peso']]);
    return ['url' => $final, 'dominio' => $p['host'], 'puntuacion' => $score, 'nivel' => $nivel, 'resumen' => $resumen, 'puntos' => $c,
        'fecha' => date('c')];
}
