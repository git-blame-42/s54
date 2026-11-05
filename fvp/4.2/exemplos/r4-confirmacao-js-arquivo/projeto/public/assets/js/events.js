// Pede confirmação antes de enviar qualquer formulário com o atributo data-confirm
// (ex.: o botão Remover da listagem de Eventos). O texto vem do próprio atributo.
document.addEventListener("DOMContentLoaded", function () {
  document.querySelectorAll("form[data-confirm]").forEach(function (form) {
    form.addEventListener("submit", function (event) {
      if (!window.confirm(form.dataset.confirm)) {
        event.preventDefault();
      }
    });
  });
});
