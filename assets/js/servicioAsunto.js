/*
  Los enlaces de servicios/ llevan al formulario de contacto con el
  asunto ya escrito: quien llega abajo se encuentra medio formulario
  hecho, y cada mensaje que entra dice de qué servicio viene.

  El formulario no está en el DOM cuando acaba de enviarse (ver el
  bloque PHP de #contact), así que puede no haber campo que rellenar.
*/
const campoAsunto = document.getElementById("asunto");

if (campoAsunto) {
  document.querySelectorAll(".service-cta[data-asunto]").forEach((enlace) => {
    enlace.addEventListener("click", () => {
      campoAsunto.value = enlace.dataset.asunto;
    });
  });
}
