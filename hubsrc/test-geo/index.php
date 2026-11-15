<?php
declare(strict_types=1);
require __DIR__ . '/common.php';
tg_session();
if (empty($_SESSION['tg_csrf'])) $_SESSION['tg_csrf'] = bin2hex(random_bytes(16));
$t = $_SESSION['tg_csrf'];
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: strict-origin-when-cross-origin');
?><!DOCTYPE html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Test GEO gratis: ¿está tu web preparada para ChatGPT? | innovAI</title>
<meta name="description" content="Analiza gratis en un minuto si tu web está preparada para que ChatGPT, Gemini y otras IA entiendan y tengan en cuenta tu negocio. Resultado al momento y mejoras concretas.">
<link rel="canonical" href="https://innovai.es/test-geo/">
<meta property="og:title" content="Test GEO gratis: ¿está tu web preparada para ChatGPT?">
<meta property="og:description" content="Descubre en un minuto qué le falta a tu web para que la inteligencia artificial la entienda y la tenga en cuenta.">
<meta property="og:url" content="https://innovai.es/test-geo/">
<meta property="og:type" content="website">
<meta property="og:locale" content="es_ES">
<link rel="icon" href="/img/logo-innovai.svg">
<link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@400;500;600;700&display=swap" rel="stylesheet">
<script type="application/ld+json">
{"@context":"https://schema.org","@graph":[
{"@type":"WebApplication","name":"Test GEO de innovAI","url":"https://innovai.es/test-geo/","applicationCategory":"BusinessApplication","operatingSystem":"Web","inLanguage":"es","offers":{"@type":"Offer","price":"0","priceCurrency":"EUR"},"description":"Herramienta gratuita que analiza si una web está preparada para que ChatGPT, Gemini y otras IA la entiendan.","provider":{"@id":"https://innovai.es/#org"}},
{"@type":"ProfessionalService","@id":"https://innovai.es/#org","name":"innovAI","url":"https://innovai.es","telephone":"+34 601 68 97 09","email":"info@innovai.es","address":{"@type":"PostalAddress","streetAddress":"C/ del Sol, 10","postalCode":"46830","addressLocality":"Benigànim","addressRegion":"Valencia","addressCountry":"ES"},"areaServed":"Comunidad Valenciana","sameAs":["https://www.instagram.com/innovai.dev/","https://www.linkedin.com/company/144047361/"]},
{"@type":"FAQPage","mainEntity":[
{"@type":"Question","name":"¿Qué es el GEO?","acceptedAnswer":{"@type":"Answer","text":"GEO (Generative Engine Optimization) es preparar tu web y tu presencia en internet para que las inteligencias artificiales como ChatGPT, Gemini o Perplexity encuentren, entiendan y tengan en cuenta tu negocio cuando alguien les pregunta."}},
{"@type":"Question","name":"¿Qué revisa el Test GEO?","acceptedAnswer":{"@type":"Answer","text":"Revisa si las IA pueden leer tu web, si tiene datos estructurados de negocio, dirección, teléfono y horario, preguntas frecuentes, contenido suficiente, títulos y descripciones claros, conexión segura y mapa del sitio."}},
{"@type":"Question","name":"¿El test me dice si ChatGPT me recomienda?","acceptedAnswer":{"@type":"Answer","text":"No. Ninguna herramienta puede garantizar lo que responde una IA. El test revisa las señales de tu web que ayudan a que la IA te entienda y te tenga en cuenta."}},
{"@type":"Question","name":"¿Es gratis?","acceptedAnswer":{"@type":"Answer","text":"Sí. El resultado sale al momento y no hace falta registrarse. Si quieres, puedes pedirnos que lo revisemos contigo sin compromiso."}}]}
]}
</script>
<style>
:root{--bg:#05070B;--card:#0C121D;--card2:#0F1726;--line:#1D2636;--txt:#F3F6FA;--mut:#97A3B6;--blue:#2F6BFF;--ok:#3DD68C;--mid:#F5B942;--bad:#FF6B6B}
*{box-sizing:border-box}html{-webkit-text-size-adjust:100%}
body{margin:0;background:var(--bg);color:var(--txt);font:16px/1.55 'Space Grotesk',system-ui,sans-serif}
a{color:var(--blue)}
.w{max-width:860px;margin:0 auto;padding:0 20px}
header{display:flex;align-items:center;justify-content:space-between;padding:18px 0}
.brand{display:flex;align-items:center;gap:10px;color:var(--txt);text-decoration:none;font-weight:700;font-size:20px}.brand img{width:34px;height:34px}.brand b{color:var(--blue)}
.hero{position:relative;padding:40px 0 26px;text-align:center}
.hero:before{content:"";position:absolute;inset:-120px -40% auto;height:520px;background:radial-gradient(closest-side,rgba(47,107,255,.25),transparent);z-index:-1}
.tag{display:inline-block;border:1px solid var(--blue);color:var(--blue);border-radius:99px;padding:5px 14px;font-size:13px;font-weight:600;letter-spacing:.08em}
h1{font-size:clamp(32px,6vw,52px);line-height:1.08;letter-spacing:-.03em;margin:18px 0 14px}h1 em{font-style:normal;color:var(--blue)}
.lead{color:#C9D2DF;font-size:18px;max-width:640px;margin:0 auto}
form.an{display:flex;gap:10px;margin:28px auto 8px;max-width:620px;background:var(--card);border:1px solid var(--line);border-radius:16px;padding:8px}
form.an input{flex:1;min-width:0;background:transparent;border:0;color:var(--txt);font:inherit;font-size:17px;padding:12px 12px;outline:none}
button{background:var(--blue);color:#fff;border:0;border-radius:12px;padding:13px 20px;font:inherit;font-weight:700;cursor:pointer;white-space:nowrap}button:disabled{opacity:.6;cursor:wait}
.hint{color:var(--mut);font-size:14px}
.err{background:rgba(255,107,107,.1);border:1px solid rgba(255,107,107,.35);color:#FFB3B3;border-radius:12px;padding:12px 14px;margin:14px auto;max-width:620px;display:none}
#res{display:none;margin:30px 0}
.scorecard{display:grid;grid-template-columns:auto 1fr;gap:22px;align-items:center;background:var(--card);border:1px solid var(--line);border-radius:20px;padding:22px}
.ring{width:132px;height:132px;position:relative}.ring svg{transform:rotate(-90deg)}.ring .n{position:absolute;inset:0;display:flex;flex-direction:column;align-items:center;justify-content:center;font-weight:700;font-size:38px;line-height:1}.ring .n small{font-size:13px;color:var(--mut);font-weight:500;margin-top:4px}
.scorecard h2{margin:0 0 6px;font-size:24px}.scorecard p{margin:0;color:#C9D2DF}.dom{color:var(--mut);font-size:14px;margin-top:6px!important;word-break:break-all}
.list{margin-top:18px;display:grid;gap:10px}
.it{background:var(--card);border:1px solid var(--line);border-radius:14px;padding:14px 16px;display:grid;grid-template-columns:28px 1fr;gap:12px}
.ic{width:28px;height:28px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-weight:700;font-size:15px}
.ok .ic{background:rgba(61,214,140,.15);color:var(--ok)}.regular .ic{background:rgba(245,185,66,.15);color:var(--mid)}.mal .ic{background:rgba(255,107,107,.15);color:var(--bad)}
.it h3{margin:0 0 3px;font-size:16px}.it p{margin:0;color:#C9D2DF;font-size:15px}
.sec-t{margin:26px 0 8px;font-size:15px;color:var(--mut);font-weight:600;letter-spacing:.06em;text-transform:uppercase}
.cta{margin-top:26px;background:linear-gradient(145deg,#12224A,#0C121D);border:1px solid #23407F;border-radius:20px;padding:24px}
.cta h2{margin:0 0 6px;font-size:24px}.cta p{margin:0 0 16px;color:#C9D2DF}
.grid{display:grid;grid-template-columns:1fr 1fr;gap:10px}
.cta input[type=text],.cta input[type=email],.cta input[type=tel]{width:100%;background:var(--bg);border:1px solid var(--line);border-radius:10px;color:var(--txt);font:inherit;padding:12px}
.chk{display:flex;gap:10px;align-items:flex-start;color:#C9D2DF;font-size:14px;margin:12px 0}.chk input{margin-top:4px;width:18px;height:18px;accent-color:var(--blue)}
.legal{color:var(--mut);font-size:12.5px;margin-top:10px}
.done{display:none;background:rgba(61,214,140,.1);border:1px solid rgba(61,214,140,.35);border-radius:12px;padding:14px;color:#BFF5D9}
.hp{position:absolute;left:-9999px}
.info{margin:48px 0 30px}.info h2{font-size:26px;margin:0 0 10px}.info p{color:#C9D2DF}
.faq details{background:var(--card);border:1px solid var(--line);border-radius:12px;padding:14px 16px;margin-bottom:10px}.faq summary{cursor:pointer;font-weight:600}.faq p{margin:10px 0 0}
footer{border-top:1px solid var(--line);color:var(--mut);font-size:14px;padding:22px 0 40px;margin-top:30px}
.spin{display:inline-block;width:16px;height:16px;border:2px solid rgba(255,255,255,.4);border-top-color:#fff;border-radius:50%;animation:s 1s linear infinite;vertical-align:-3px;margin-right:6px}@keyframes s{to{transform:rotate(360deg)}}
@media(max-width:600px){form.an{flex-direction:column}button{width:100%}.scorecard{grid-template-columns:1fr;justify-items:center;text-align:center}.grid{grid-template-columns:1fr}.lead{font-size:16.5px}}
</style>
</head>
<body>
<div class="w">
<header><a class="brand" href="https://innovai.es/"><img src="/img/logo-innovai.svg" alt="" width="34" height="34"><span>innov<b>AI</b></span></a><a href="https://innovai.es/" style="color:var(--mut);text-decoration:none;font-size:14px">innovai.es</a></header>

<section class="hero">
  <span class="tag">TEST GEO GRATUITO</span>
  <h1>¿Está tu web preparada para que <em>ChatGPT</em> la entienda?</h1>
  <p class="lead">Cada vez más clientes preguntan a la inteligencia artificial antes de elegir un negocio. Descubre en un minuto qué le falta a tu web para que la IA la entienda y la tenga en cuenta.</p>
  <form class="an" id="f" autocomplete="off">
    <input id="u" name="url" type="text" inputmode="url" placeholder="Tu web, p. ej. minegocio.es" aria-label="Dirección de tu web" required>
    <button id="b" type="submit">Analizar gratis</button>
  </form>
  <div class="hint">Sin registrarte. El resultado sale al momento.</div>
  <div class="err" id="e" role="alert"></div>
</section>

<section id="res" aria-live="polite">
  <div class="scorecard">
    <div class="ring"><svg width="132" height="132" viewBox="0 0 132 132"><circle cx="66" cy="66" r="56" fill="none" stroke="#1D2636" stroke-width="12"/><circle id="arc" cx="66" cy="66" r="56" fill="none" stroke="#2F6BFF" stroke-width="12" stroke-linecap="round" stroke-dasharray="351.86" stroke-dashoffset="351.86"/></svg><div class="n"><span id="sc">0</span><small>de 100</small></div></div>
    <div><h2 id="nv"></h2><p id="rs"></p><p class="dom" id="dm"></p></div>
  </div>
  <div class="sec-t" id="t-mal">Qué mejorar</div><div class="list" id="l-mal"></div>
  <div class="sec-t" id="t-ok">Lo que ya tienes bien</div><div class="list" id="l-ok"></div>

  <div class="cta" id="cta">
    <h2>¿Lo revisamos contigo?</h2>
    <p>Déjanos tus datos y Pablo, de innovAI, te escribe para explicarte el resultado y por dónde empezar. Gratis y sin compromiso. También te enviamos el resumen por correo.</p>
    <form id="lf" novalidate>
      <div class="grid">
        <input type="text" name="nombre" placeholder="Tu nombre *" required autocomplete="name">
        <input type="email" name="email" placeholder="Tu correo *" required autocomplete="email">
        <input type="tel" name="telefono" placeholder="Teléfono (opcional)" autocomplete="tel">
        <input type="text" name="negocio" placeholder="Nombre de tu negocio" autocomplete="organization">
      </div>
      <input class="hp" type="text" name="web2" tabindex="-1" autocomplete="off" aria-hidden="true">
      <label class="chk"><input type="checkbox" name="acepta" required> <span>He leído y acepto la <a href="https://innovai.es/#privacidad" target="_blank" rel="noopener">política de privacidad</a> y que innovAI me contacte sobre esta solicitud.</span></label>
      <button id="lb" type="submit">Quiero que lo revisemos</button>
      <div class="err" id="le" role="alert"></div>
      <p class="legal">Responsable: Pablo Vidal Vidal (innovAI). Finalidad: responder a tu solicitud y enviarte el resultado. No cedemos tus datos a terceros. Puedes ejercer tus derechos de acceso, rectificación y supresión en info@innovai.es.</p>
    </form>
    <div class="done" id="ld">¡Recibido! Te hemos enviado el resumen a tu correo y Pablo te escribirá en breve. Si no lo ves, mira en la carpeta de spam.</div>
  </div>
</section>

<section class="info">
  <h2>Qué es el GEO y por qué importa</h2>
  <p>GEO (<em>Generative Engine Optimization</em>) es preparar tu web para que las inteligencias artificiales, como ChatGPT, Gemini o Perplexity, encuentren tu negocio, lo entiendan y lo tengan en cuenta cuando alguien les pregunta por lo que tú ofreces. Es el siguiente paso del SEO: ya no basta con salir en Google, también importa cómo te describe la IA.</p>
  <div class="faq">
    <details><summary>¿Qué revisa el test?</summary><p>Si las IA pueden leer tu web, si tiene una ficha de negocio en datos estructurados (con dirección, teléfono y horario), preguntas frecuentes, contenido suficiente, títulos y descripciones claros, datos de contacto visibles, conexión segura y mapa del sitio.</p></details>
    <details><summary>¿Me dice si ChatGPT me recomienda?</summary><p>No, y desconfía de quien te lo asegure: nadie controla lo que responde una IA. El test revisa las señales de tu web que ayudan a que la IA te entienda y te tenga en cuenta.</p></details>
    <details><summary>¿Es gratis de verdad?</summary><p>Sí. El resultado sale al momento y no hace falta registrarse. Si quieres, puedes pedirnos que lo revisemos contigo, sin compromiso.</p></details>
    <details><summary>¿Quién está detrás?</summary><p>innovAI, de Benigànim (Valencia): hacemos webs, SEO, GEO y automatizaciones para pymes y autónomos.</p></details>
  </div>
</section>

<footer>© <?= date('Y') ?> innovAI · Pablo Vidal Vidal · C/ del Sol, 10, 46830 Benigànim (Valencia) · <a href="tel:+34601689709">601 68 97 09</a> · <a href="mailto:info@innovai.es">info@innovai.es</a> · <a href="https://innovai.es/#privacidad">Privacidad</a> · <a href="https://innovai.es/#aviso-legal">Aviso legal</a></footer>
</div>
<script>
(() => {
  const T = <?= json_encode($t) ?>;
  const $ = id => document.getElementById(id);
  const esc = s => String(s).replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
  let lastUrl = '';
  $('f').addEventListener('submit', async ev => {
    ev.preventDefault();
    const b = $('b'), e = $('e'); e.style.display = 'none';
    b.disabled = true; b.innerHTML = '<span class="spin"></span>Analizando…';
    try {
      const r = await fetch('analizar.php', {method: 'POST', headers: {'Content-Type': 'application/json'}, body: JSON.stringify({url: $('u').value, t: T})});
      const d = await r.json();
      if (!r.ok || d.error) throw new Error(d.error || 'Error');
      lastUrl = d.url; show(d);
    } catch (x) { e.textContent = x.message; e.style.display = 'block'; }
    finally { b.disabled = false; b.textContent = 'Analizar gratis'; }
  });
  function show(d) {
    $('res').style.display = 'block';
    const col = d.puntuacion >= 80 ? '#3DD68C' : d.puntuacion >= 55 ? '#F5B942' : '#FF6B6B';
    $('arc').setAttribute('stroke', col);
    $('arc').style.transition = 'stroke-dashoffset 1s ease'; $('arc').style.strokeDashoffset = 351.86 * (1 - d.puntuacion / 100);
    let n = 0; const tick = () => { n = Math.min(d.puntuacion, n + 2); $('sc').textContent = n; if (n < d.puntuacion) requestAnimationFrame(tick); }; tick();
    $('nv').textContent = d.nivel; $('rs').textContent = d.resumen; $('dm').textContent = 'Web analizada: ' + d.url;
    const row = p => `<div class="it ${p.estado}"><div class="ic">${p.estado === 'ok' ? '✓' : p.estado === 'regular' ? '!' : '✕'}</div><div><h3>${esc(p.titulo)}</h3><p>${esc(p.texto)}</p></div></div>`;
    const mal = d.puntos.filter(p => p.estado !== 'ok'), ok = d.puntos.filter(p => p.estado === 'ok');
    $('l-mal').innerHTML = mal.map(row).join(''); $('t-mal').style.display = mal.length ? '' : 'none';
    $('l-ok').innerHTML = ok.map(row).join(''); $('t-ok').style.display = ok.length ? '' : 'none';
    $('res').scrollIntoView({behavior: 'smooth', block: 'start'});
  }
  $('lf').addEventListener('submit', async ev => {
    ev.preventDefault();
    const f = ev.target, b = $('lb'), e = $('le'); e.style.display = 'none';
    const data = Object.fromEntries(new FormData(f)); data.acepta = f.acepta.checked; data.t = T; data.url = lastUrl;
    b.disabled = true; b.innerHTML = '<span class="spin"></span>Enviando…';
    try {
      const r = await fetch('contacto.php', {method: 'POST', headers: {'Content-Type': 'application/json'}, body: JSON.stringify(data)});
      const d = await r.json();
      if (!r.ok || d.error) throw new Error(d.error || 'Error');
      f.style.display = 'none'; $('ld').style.display = 'block';
    } catch (x) { e.textContent = x.message; e.style.display = 'block'; }
    finally { b.disabled = false; b.textContent = 'Quiero que lo revisemos'; }
  });
  const q = new URLSearchParams(location.search).get('web'); if (q) { $('u').value = q; }
})();
</script>
</body>
</html>
