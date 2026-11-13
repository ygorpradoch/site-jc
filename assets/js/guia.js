/* Guia: botões de compartilhar do artigo (sem dependências) */
(function () {
  'use strict';
  var box = document.querySelector('[data-share]');
  if (!box) return;
  var url = box.dataset.url, titulo = box.dataset.title;
  var copiar = box.querySelector('[data-copy]'), nativo = box.querySelector('[data-native]');

  function aviso(btn, txt) {
    var antes = btn.textContent;
    btn.textContent = txt;
    setTimeout(function () { btn.textContent = antes; }, 2000);
  }
  if (copiar) {
    copiar.addEventListener('click', function () {
      var ok = function () { aviso(copiar, 'Link copiado'); };
      if (navigator.clipboard && navigator.clipboard.writeText) {
        navigator.clipboard.writeText(url).then(ok, function () { window.prompt('Copie o link:', url); });
      } else {
        window.prompt('Copie o link:', url);
      }
    });
  }
  if (nativo && navigator.share) {
    nativo.hidden = false;
    nativo.addEventListener('click', function () {
      navigator.share({ title: titulo, url: url }).catch(function () {});
    });
  }
})();
