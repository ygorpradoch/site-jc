/* Telas de acesso: QR code da 2ª etapa e códigos de recuperação (sem dependências externas) */
(function () {
  'use strict';

  var qr = document.getElementById('qr');
  if (qr && window.qrcode) {
    try {
      var q = window.qrcode(0, 'M');
      q.addData(qr.getAttribute('data-uri'), 'Byte');
      q.make();
      var img = document.createElement('img');
      img.src = q.createDataURL(5, 2);
      img.alt = '';
      img.width = img.height = q.getModuleCount() * 5 + 4;
      qr.appendChild(img);
    } catch (e) {
      qr.textContent = 'Não foi possível gerar o QR code. Use a chave abaixo.';
    }
  }

  var lista = document.getElementById('rec-codes');
  if (lista) {
    var texto = function () {
      return 'Códigos de recuperação · painel JC Síndico\n(cada código vale uma única vez)\n\n' +
        Array.prototype.map.call(lista.querySelectorAll('li'), function (li) { return li.textContent; }).join('\n') + '\n';
    };
    var copy = document.getElementById('rec-copy'), save = document.getElementById('rec-save');
    if (copy) copy.addEventListener('click', function () {
      var ok = function () { copy.textContent = 'Copiado'; setTimeout(function () { copy.textContent = 'Copiar códigos'; }, 2000); };
      if (navigator.clipboard && navigator.clipboard.writeText) navigator.clipboard.writeText(texto()).then(ok, function () {});
    });
    if (save) save.addEventListener('click', function () {
      var a = document.createElement('a');
      a.href = URL.createObjectURL(new Blob([texto()], { type: 'text/plain' }));
      a.download = 'codigos-recuperacao-painel.txt';
      document.body.appendChild(a);
      a.click();
      a.remove();
    });
  }
})();
