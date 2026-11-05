// Botão "Imprimir" da lista de presença (layouts/print.phtml).
document.addEventListener("DOMContentLoaded", function () {
  const button = document.getElementById("print_button");

  if (!button) return;

  button.addEventListener("click", function () {
    window.print();
  });
});
