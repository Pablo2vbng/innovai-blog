/* innovAI · interruptor del estilo de la web.
   PUBLICO = true  → todo el mundo ve el estilo «Cuaderno» (con ?estilo=viejo se vuelve al oscuro en ese navegador).
   SOBRIO  = true  → encima del cuaderno se aplica la capa «Sobrio» (más seria). Con ?sobrio=0 / ?sobrio=1 se prueba en ese navegador.
   Para volver al cuaderno original: SOBRIO = false. */
(function () {
  var PUBLICO = true;
  var SOBRIO = false;
  var V = '8';
  try {
    var q = location.search, k = 'innovai_estilo', ks = 'innovai_sobrio', ls = window.localStorage;
    if (/[?&]estilo=nuevo\b/.test(q)) ls.setItem(k, 'nuevo');
    if (/[?&]estilo=viejo\b/.test(q)) ls.setItem(k, 'viejo');
    if (/[?&]sobrio=1\b/.test(q)) ls.setItem(ks, 'si');
    if (/[?&]sobrio=0\b/.test(q)) ls.setItem(ks, 'no');
    var pref = ls.getItem(k), ps = ls.getItem(ks);
    var activo = PUBLICO ? pref !== 'viejo' : pref === 'nuevo';
    if (!activo) return;
    var sob = SOBRIO ? ps !== 'no' : ps === 'si';
    document.documentElement.className += sob ? ' cuaderno sobrio' : ' cuaderno';
    document.write('<link rel="stylesheet" href="/css/cuaderno.css?v=' + V + '">');
    if (sob) document.write('<link rel="stylesheet" href="/css/sobrio.css?v=' + V + '">');
    document.write('<script src="/js/cuaderno.js?v=' + V + '" defer><\/script>');
  } catch (e) {}
})();
