/* innovAI · efectos del estilo «Cuaderno vivo». Solo decoración: no cambia ningún texto ni enlace. */
(function () {
function init() {
  var html = document.documentElement;
  if (!html.classList.contains('cuaderno')) return;

  // Logo en tinta oscura (el original es claro, pensado para fondo negro)
  document.querySelectorAll('img[src*="/img/logo-innovai.svg"]').forEach(function (img) {
    img.setAttribute('src', '/img/logo-innovai-tinta.svg');
  });
  // Tienda: ilustraciones claras de las tarjetas NFC
  [['tarjeta-google-instagram.webp', 'tarjeta-google-instagram-claro.webp'], ['tarjeta-contacto.webp', 'tarjeta-contacto-claro.webp']].forEach(function (par) {
    document.querySelectorAll('img[src*="/img/tienda/' + par[0] + '"]').forEach(function (img) {
      img.setAttribute('src', '/img/tienda/' + par[1] + '?v=1');
    });
  });

  var reduce = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;

  // Subrayado a mano bajo los títulos de sección (decorativo, oculto a lectores de pantalla)
  var NS = 'http://www.w3.org/2000/svg';
  document.querySelectorAll('.section-head h2, section > .wrap > h2').forEach(function (h2) {
    if (h2.closest('.final-box') || h2.nextElementSibling && h2.nextElementSibling.classList && h2.nextElementSibling.classList.contains('cdn-squiggle')) return;
    var svg = document.createElementNS(NS, 'svg');
    svg.setAttribute('viewBox', '0 0 260 20'); svg.setAttribute('aria-hidden', 'true'); svg.setAttribute('focusable', 'false');
    svg.setAttribute('class', 'cdn-squiggle');
    var p = document.createElementNS(NS, 'path');
    p.setAttribute('d', 'M2 12 C30 2 50 20 80 11 S130 2 160 11 S212 20 256 7');
    p.setAttribute('pathLength', '1');
    svg.appendChild(p);
    h2.insertAdjacentElement('afterend', svg);
  });

  if (reduce || !('IntersectionObserver' in window)) {
    document.querySelectorAll('.grad, .honest-quote em').forEach(function (el) { el.classList.add('cdn-vis'); });
    return;
  }

  // Animación al entrar en pantalla: rotulador y garabatos que se dibujan
  html.classList.add('cdn-anim');
  var style = document.createElement('style');
  style.textContent = '.cdn-squiggle path{stroke-dasharray:1;stroke-dashoffset:1;transition:stroke-dashoffset 1.1s ease-out .15s}.cdn-squiggle.cdn-vis path{stroke-dashoffset:0}';
  document.head.appendChild(style);
  var io = new IntersectionObserver(function (entries) {
    entries.forEach(function (e) {
      if (e.isIntersecting) { e.target.classList.add('cdn-vis'); io.unobserve(e.target); }
    });
  }, { threshold: 0.4 });
  document.querySelectorAll('.grad, .honest-quote em, .cdn-squiggle').forEach(function (el) { io.observe(el); });
}
if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init); else init();
})();
