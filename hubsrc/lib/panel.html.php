<?php /* innovAI Hub · pestaña Resultados. Se incluye desde index.php con sesión iniciada. */ ?>
<style>
.dh{display:flex;align-items:baseline;justify-content:space-between;gap:10px;flex-wrap:wrap;margin-bottom:14px}.dh h2{margin:0;font-size:20px}
.dh .act{font-size:13px;color:var(--mut)}.dh button{margin-left:8px}
.kpis{display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:10px;margin-bottom:16px}
.kpi{background:var(--card);border:1px solid var(--line);border-radius:12px;padding:12px 14px}.kpi .l{font-size:12px;color:var(--mut)}
.kpi .n{font-size:26px;font-weight:700;letter-spacing:-.02em;margin:2px 0}.kpi .c{font-size:12px}.up{color:var(--ok)}.down{color:var(--bad)}.flat{color:var(--mut)}
.two{display:grid;grid-template-columns:repeat(auto-fit,minmax(min(100%,430px),1fr));gap:16px}.two .card{margin-bottom:0}.blk{margin-bottom:16px}
.card h3{margin:0 0 4px;font-size:15px}.card .sub{margin-bottom:10px}.chart{position:relative;width:100%}
.chart svg{display:block;width:100%;height:auto;overflow:visible}.tip{position:absolute;pointer-events:none;background:#1A2333;border:1px solid var(--line);border-radius:8px;padding:5px 8px;font-size:12px;white-space:nowrap;transform:translate(-50%,-110%);display:none;z-index:2}
.leg{display:flex;gap:14px;font-size:12px;color:var(--mut);margin-top:6px}.leg i{display:inline-block;width:10px;height:3px;border-radius:2px;margin-right:5px;vertical-align:middle}
.tb{width:100%;border-collapse:collapse;font-size:13px}.tb th{text-align:left;color:var(--mut);font-weight:500;padding:6px 6px;border-bottom:1px solid var(--line)}.tb td{padding:7px 6px;border-bottom:1px solid var(--line);color:var(--txt);white-space:normal}
.tb td:first-child{color:var(--txt);white-space:normal}.tb .r{text-align:right;font-variant-numeric:tabular-nums}.tb a{color:var(--txt);text-decoration:none}.tb a:hover{color:var(--blue)}
.bar{display:flex;align-items:center;gap:8px;font-size:13px;margin:6px 0}.bar span:first-child{width:38%;color:var(--mut);overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.bar .t{flex:1;height:8px;background:#141C2A;border-radius:5px;overflow:hidden}.bar .t b{display:block;height:100%;background:var(--blue);border-radius:5px}.bar span:last-child{width:46px;text-align:right;font-variant-numeric:tabular-nums}
.pill{display:inline-block;font-size:12px;padding:2px 8px;border-radius:20px;background:#141C2A;color:var(--mut)}.pill.ok{color:var(--ok)}.pill.no{color:#f5b942}
.empty{color:var(--mut);font-size:13px;padding:10px 0}.loading{color:var(--mut);padding:40px 0;text-align:center}
.mini{display:grid;grid-template-columns:repeat(auto-fit,minmax(120px,1fr));gap:10px}.mini div{background:#0A0F18;border:1px solid var(--line);border-radius:10px;padding:10px}.mini b{display:block;font-size:20px}.mini span{font-size:12px;color:var(--mut)}
@media(max-width:520px){.kpi .n{font-size:22px}.hide-s{display:none}}
</style>
<div class="dh"><h2>Resultados · últimos 28 días</h2><div class="act"><span id="upd">Cargando…</span><button class="sec" id="refresh">Actualizar</button></div></div>
<div id="dash"><div class="loading">Reuniendo tus datos de Instagram, Facebook y Google… (la primera vez tarda unos segundos)</div></div>
<script>
(function () {
  const $ = (h) => { const t = document.createElement('template'); t.innerHTML = h.trim(); return t.content.firstChild; };
  const esc = (s) => String(s ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
  const nf = new Intl.NumberFormat('es-ES'); const n = (x) => x == null ? '—' : nf.format(x);
  const sum = (a, i) => a.reduce((s, r) => s + (+r[i] || 0), 0);
  const fdate = (d) => { const [y, m, dd] = d.split('-'); return `${+dd}/${+m}`; };
  const BLUE = '#2F6BFF', GREEN = '#3DD68C', VIOLET = '#A78BFA';

  function delta(cur, prev) {
    if (!prev) return '<span class="flat">sin periodo anterior</span>';
    const p = Math.round((cur - prev) / prev * 100);
    return `<span class="${p > 0 ? 'up' : p < 0 ? 'down' : 'flat'}">${p > 0 ? '▲' : p < 0 ? '▼' : '='} ${Math.abs(p)} %</span> <span class="flat">vs 28 días antes</span>`;
  }
  // Divide una serie diaria en los últimos 28 días y los 28 anteriores.
  function split(rows) {
    const last = rows.length ? rows[rows.length - 1][0] : new Date().toISOString().slice(0, 10);
    const back = (k) => { const t = new Date(last + 'T12:00:00'); t.setDate(t.getDate() - k); return t.toISOString().slice(0, 10); };
    const a = back(27), b = back(55);
    return [rows.filter(r => r[0] >= a), rows.filter(r => r[0] >= b && r[0] < a)];
  }

  function lineChart(el, rows, series) {
    const W = Math.max(280, el.clientWidth), H = 170, P = {l: 34, r: 8, t: 10, b: 22};
    if (!rows.length) { el.innerHTML = '<div class="empty">Sin datos todavía.</div>'; return; }
    const max = Math.max(1, ...rows.flatMap(r => series.map(s => +r[s.i] || 0)));
    const x = i => P.l + (rows.length === 1 ? 0 : i * (W - P.l - P.r) / (rows.length - 1)), y = v => H - P.b - v / max * (H - P.t - P.b);
    let g = '';
    for (let k = 0; k <= 3; k++) { const v = Math.round(max * k / 3), yy = y(v); g += `<line x1="${P.l}" x2="${W - P.r}" y1="${yy}" y2="${yy}" stroke="#1D2636"/><text x="${P.l - 6}" y="${yy + 4}" fill="#97A3B6" font-size="10" text-anchor="end">${nf.format(v)}</text>`; }
    const step = Math.ceil(rows.length / 6);
    rows.forEach((r, i) => { if (i % step === 0 || i === rows.length - 1) g += `<text x="${x(i)}" y="${H - 5}" fill="#97A3B6" font-size="10" text-anchor="middle">${fdate(r[0])}</text>`; });
    series.forEach(s => { g += `<polyline fill="none" stroke="${s.c}" stroke-width="2" stroke-linejoin="round" points="${rows.map((r, i) => x(i) + ',' + y(+r[s.i] || 0)).join(' ')}"/>`; });
    el.innerHTML = `<svg viewBox="0 0 ${W} ${H}" role="img">${g}<line class="hl" x1="0" x2="0" y1="${P.t}" y2="${H - P.b}" stroke="#97A3B6" stroke-dasharray="3 3" visibility="hidden"/></svg><div class="tip"></div>`
      + `<div class="leg">${series.map(s => `<span><i style="background:${s.c}"></i>${esc(s.n)}</span>`).join('')}</div>`;
    const svg = el.querySelector('svg'), tip = el.querySelector('.tip'), hl = svg.querySelector('.hl');
    const move = (ev) => { const b = svg.getBoundingClientRect(), px = ((ev.touches ? ev.touches[0].clientX : ev.clientX) - b.left) * W / b.width;
      const i = Math.max(0, Math.min(rows.length - 1, Math.round((px - P.l) / ((W - P.l - P.r) / Math.max(1, rows.length - 1)))));
      hl.setAttribute('x1', x(i)); hl.setAttribute('x2', x(i)); hl.setAttribute('visibility', 'visible');
      tip.innerHTML = `<b>${fdate(rows[i][0])}</b> · ` + series.map(s => `${esc(s.n)}: ${n(+rows[i][s.i] || 0)}`).join(' · ');
      tip.style.display = 'block'; tip.style.left = Math.min(Math.max(x(i) / W * b.width, 70), b.width - 70) + 'px'; tip.style.top = '18px'; };
    svg.addEventListener('mousemove', move); svg.addEventListener('touchstart', move, {passive: true});
    svg.addEventListener('mouseleave', () => { tip.style.display = 'none'; hl.setAttribute('visibility', 'hidden'); });
  }
  const bars = (list) => { const m = Math.max(1, ...list.map(r => r[1])); return list.map(r => `<div class="bar"><span title="${esc(r[0])}">${esc(r[0])}</span><span class="t"><b style="width:${r[1] / m * 100}%"></b></span><span>${n(r[1])}</span></div>`).join('') || '<div class="empty">Sin datos todavía.</div>'; };
  const err = (b) => `<div class="empty">${esc(b && b.error ? b.error : 'Sin datos')}</div>`;

  function render(d) {
    const root = document.getElementById('dash'); root.innerHTML = '';
    const ig = d.instagram, fb = d.facebook, go = d.google, web = d.web;
    // KPIs
    const k = [];
    if (web.ok) { const [c, p] = split(web.d.diario); k.push(['Visitas a la web', sum(c, 1), delta(sum(c, 1), sum(p, 1))]); }
    if (go.ok) { const [c, p] = split(go.d.diario); k.push(['Clics desde Google', sum(c, 1), delta(sum(c, 1), sum(p, 1))]); k.push(['Veces que sales en Google', sum(c, 2), delta(sum(c, 2), sum(p, 2))]); }
    if (ig.ok) { k.push(['Seguidores Instagram', ig.d.seguidores, `<span class="flat">@${esc(ig.d.usuario)}</span>`]); k.push(['Alcance Instagram', sum(ig.d.alcance_diario, 1), '<span class="flat">cuentas alcanzadas</span>']); }
    if (fb.ok) k.push(['Seguidores Facebook', fb.d.seguidores, `<span class="flat">${esc(fb.d.pagina)}</span>`]);
    root.append($(`<div class="kpis">${k.map(x => `<div class="kpi"><div class="l">${x[0]}</div><div class="n">${n(x[1])}</div><div class="c">${x[2]}</div></div>`).join('')}</div>`));

    // Web + Google
    const s1 = $(`<div class="two blk"><div class="card"><h3>Tu web</h3><div class="sub">Personas que han entrado cada día (Analytics)</div><div class="chart" id="c-web"></div></div>
      <div class="card"><h3>Google</h3><div class="sub">Clics desde el buscador cada día (Search Console, con 2 días de retraso)</div><div class="chart" id="c-gsc"></div></div></div>`);
    root.append(s1);
    web.ok ? lineChart(s1.querySelector('#c-web'), split(web.d.diario)[0], [{i: 1, n: 'Usuarios', c: BLUE}, {i: 2, n: 'Sesiones', c: VIOLET}]) : s1.querySelector('#c-web').innerHTML = err(web);
    go.ok ? lineChart(s1.querySelector('#c-gsc'), split(go.d.diario)[0], [{i: 1, n: 'Clics', c: GREEN}]) : s1.querySelector('#c-gsc').innerHTML = err(go);

    // Búsquedas y canales
    const s2 = $(`<div class="two blk"><div class="card"><h3>Qué busca la gente para encontrarte</h3><div class="sub">Búsquedas en Google donde apareces</div><div id="t-q"></div></div>
      <div class="card"><h3>De dónde llegan las visitas</h3><div class="sub">Sesiones por canal</div><div id="t-can"></div><div id="t-ia" style="margin-top:10px"></div></div></div>`);
    root.append(s2);
    s2.querySelector('#t-q').innerHTML = go.ok ? (go.d.consultas.length ? `<table class="tb"><tr><th>Búsqueda</th><th class="r">Clics</th><th class="r">Veces</th><th class="r hide-s">Posición</th></tr>${go.d.consultas.map(r => `<tr><td>${esc(r.k)}</td><td class="r">${n(r.clics)}</td><td class="r">${n(r.impresiones)}</td><td class="r hide-s">${r.posicion}</td></tr>`).join('')}</table>` : '<div class="empty">Aún no hay búsquedas.</div>') : err(go);
    if (web.ok) { s2.querySelector('#t-can').innerHTML = bars(web.d.canales); s2.querySelector('#t-ia').innerHTML = `<span class="pill ${web.d.sesiones_ia ? 'ok' : ''}">🤖 Visitas desde ChatGPT y otras IA: ${n(web.d.sesiones_ia)}</span>`; } else s2.querySelector('#t-can').innerHTML = err(web);

    // Instagram
    const s3 = $(`<div class="card blk"><h3>Instagram ${ig.ok ? '· @' + esc(ig.d.usuario) : ''}</h3><div class="sub">Alcance diario y tus últimas publicaciones</div><div id="ig-body"></div></div>`);
    root.append(s3);
    if (ig.ok) {
      const b = s3.querySelector('#ig-body');
      b.append($(`<div class="mini" style="margin-bottom:12px"><div><b>${n(ig.d.seguidores)}</b><span>seguidores</span></div><div><b>${n(ig.d.visitas_perfil)}</b><span>visitas al perfil</span></div><div><b>${n(ig.d.cuentas_interaccion)}</b><span>cuentas que interactúan</span></div><div><b>${n(ig.d.interacciones)}</b><span>interacciones</span></div></div>`));
      const ch = $('<div class="chart"></div>'); b.append(ch); lineChart(ch, ig.d.alcance_diario, [{i: 1, n: 'Alcance', c: BLUE}]);
      b.append($(`<table class="tb" style="margin-top:12px"><tr><th>Publicación</th><th class="r hide-s">Fecha</th><th class="r">Vistas</th><th class="r">Alcance</th><th class="r">❤</th><th class="r">💬</th><th class="r hide-s">Guardados</th></tr>${ig.d.posts.map(p => `<tr><td><a href="${esc(p.enlace)}" target="_blank" rel="noopener">${esc(p.texto || '(sin texto)')}</a></td><td class="r hide-s">${p.fecha ? fdate(p.fecha) : ''}</td><td class="r">${n(p.vistas)}</td><td class="r">${n(p.alcance)}</td><td class="r">${n(p.likes)}</td><td class="r">${n(p.comentarios)}</td><td class="r hide-s">${n(p.guardados)}</td></tr>`).join('')}</table>`));
    } else s3.querySelector('#ig-body').innerHTML = err(ig);

    // Facebook + páginas web
    const s4 = $(`<div class="two blk"><div class="card"><h3>Facebook ${fb.ok ? '· ' + esc(fb.d.pagina) : ''}</h3><div class="sub">Últimas publicaciones</div><div id="fb-body"></div></div>
      <div class="card"><h3>Páginas más vistas</h3><div class="sub">Vistas por página de tu web</div><div id="t-pag"></div></div></div>`);
    root.append(s4);
    s4.querySelector('#fb-body').innerHTML = fb.ok ? (fb.d.posts.length ? `<table class="tb"><tr><th>Publicación</th><th class="r">Reacc.</th><th class="r">💬</th><th class="r">Compart.</th></tr>${fb.d.posts.map(p => `<tr><td><a href="${esc(p.enlace)}" target="_blank" rel="noopener">${esc(p.texto || '(sin texto)')}</a></td><td class="r">${n(p.reacciones)}</td><td class="r">${n(p.comentarios)}</td><td class="r">${n(p.compartidos)}</td></tr>`).join('')}</table>` : '<div class="empty">Sin publicaciones.</div>') : err(fb);
    s4.querySelector('#t-pag').innerHTML = web.ok ? bars(web.d.paginas) : err(web);

    // Tienda y otros
    const m = d.merchant, bz = d.business, li = d.linkedin, cg = d.chatgpt;
    const tot = bz.ok ? bz.d.totales || {} : {};
    root.append($(`<div class="card blk"><h3>Escaparates y otras cuentas</h3><div class="sub">Dónde te pueden encontrar además de tu web</div><div class="mini">
      <div><b>${m.ok ? n(m.d.aprobados) + ' / ' + n(m.d.total) : '—'}</b><span>productos aprobados en Google Shopping ${m.ok ? '' : '· ' + esc(m.error)}</span></div>
      <div><b>${cg.ok ? n(cg.d.productos) : '—'}</b><span>productos en el catálogo de ChatGPT</span></div>
      <div><b>${bz.ok ? n((tot.BUSINESS_IMPRESSIONS_MOBILE_SEARCH || 0) + (tot.BUSINESS_IMPRESSIONS_DESKTOP_SEARCH || 0) + (tot.BUSINESS_IMPRESSIONS_MOBILE_MAPS || 0) + (tot.BUSINESS_IMPRESSIONS_DESKTOP_MAPS || 0)) : '—'}</b><span>vistas de tu ficha de Google ${bz.ok ? '' : '· ' + esc(bz.error)}</span></div>
      <div><b>${li.ok ? '✓' : '—'}</b><span>LinkedIn ${li.ok ? 'conectado · renovar antes del ' + esc(li.d.caduca) : 'sin conectar'}</span></div></div>
      ${m.ok && m.d.con_problemas.length ? `<div style="margin-top:10px">${m.d.con_problemas.map(p => `<div class="pill no" style="margin:3px 4px 0 0">⚠ ${esc(p.titulo)}: ${esc(p.problema)}</div>`).join('')}</div>` : ''}</div>`));
  }

  async function load(force) {
    const upd = document.getElementById('upd'); upd.textContent = force ? 'Actualizando…' : 'Cargando…';
    try {
      const r = await fetch('datos.php' + (force ? '?actualizar=1' : ''), {credentials: 'same-origin'});
      if (!r.ok) throw new Error(r.status === 403 ? 'La sesión ha caducado: vuelve a entrar.' : 'Error ' + r.status);
      const d = await r.json(); render(d);
      const mins = Math.round((Date.now() - new Date(d.generado)) / 60000);
      upd.textContent = mins < 1 ? 'Datos de ahora mismo' : `Datos de hace ${mins} min`;
    } catch (e) { upd.textContent = ''; document.getElementById('dash').innerHTML = `<div class="card err">${esc(e.message)}</div>`; }
  }
  document.getElementById('refresh').addEventListener('click', () => load(true));
  let last = 0; window.addEventListener('resize', () => { clearTimeout(last); last = setTimeout(() => load(false), 300); });
  load(false);
})();
</script>
