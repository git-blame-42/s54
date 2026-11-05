// Inscrição sem recarregar a página (prévia do Ajax da 4.4): o form de "Inscrever-se" do detalhe
// do Evento é enviado com fetch() pedindo JSON; sem JavaScript, o form continua funcionando (HTML).
document.addEventListener("DOMContentLoaded", function () {
  const form = document.querySelector("[data-enrollment-form]");
  const feedback = document.getElementById("enrollment_feedback");

  if (!(form && feedback)) return;

  function showFeedback(kind, message) {
    feedback.className = "alert alert-" + kind;
    feedback.textContent = message;
  }

  form.addEventListener("submit", async function (event) {
    event.preventDefault();
    const button = form.querySelector("button");
    button.disabled = true;

    try {
      const response = await fetch(form.action, {
        method: "POST",
        headers: { Accept: "application/json" },
        credentials: "same-origin",
      });
      const data = await response.json();

      // O 404 (Evento não encontrado) não traz contagens: só atualiza quando elas vêm.
      if ("capacity" in data) {
        document.getElementById("event_spots").textContent = data.enrollments_count + "/" + data.capacity;
        document.getElementById("event_available").textContent = "(" + data.available_spots + " disponíveis)";
      }

      if (data.ok) {
        showFeedback("success", data.message);
        const enrolled = document.createElement("p");
        enrolled.className = "text-success";
        enrolled.textContent = "Você está inscrito neste evento.";
        form.replaceWith(enrolled);
      } else {
        showFeedback("danger", data.message);
        button.disabled = false;
      }
    } catch (error) {
      // Resposta que não é JSON (ex.: sessão expirada redireciona para /login): envia o form normal.
      form.submit();
    }
  });
});
