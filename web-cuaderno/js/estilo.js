/* innovAI · interruptor del estilo «Cuaderno vivo».
   PUBLICO = false → solo lo ve quien entra con ?estilo=nuevo (se recuerda en ese navegador).
   PUBLICO = true  → lo ve todo el mundo. Con ?estilo=viejo se vuelve al anterior en ese navegador.
   Para volver atrás del todo: poner PUBLICO = false. */
(function () {
  var PUBLICO = false;
  var V = '2';
  try {
    var q = location.search, k = 'innovai_estilo', ls = window.localStorage;
    if (/[?&]estilo=nuevo\b/.test(q)) ls.setItem(k, 'nuevo');
    if (/[?&]estilo=viejo\b/.test(q)) ls.setItem(k, 'viejo');
    var pref = ls.getItem(k);
    var activo = PUBLICO ? pref !== 'viejo' : pref === 'nuevo';
    if (!activo) return;
    document.documentElement.className += ' cuaderno';
    document.write('<link rel="stylesheet" href="/css/cuaderno.css?v=' + V + '">');
    document.write('<script src="/js/cuaderno.js?v=' + V + '" defer><\/script>');
  } catch (e) {}
})();
