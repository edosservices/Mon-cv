/*
 * Complète window.LIMETE_SESSION avant app.js.
 * Ne remplace pas une valeur déjà fournie par le script du routeur.
 * N'envoie rien si LIMETE_ORIGIN n'a pas été collé depuis l'écran MikroTik.
 */
(function (global) {
  if (global.LIMETE_SESSION && global.LIMETE_SESSION.expiresAt) return;
  var origin = global.LIMETE_ORIGIN;
  if (typeof origin !== "string" || (origin.indexOf("https://") !== 0 && origin.indexOf("http://") !== 0)) return;
  var node = global.document && global.document.getElementById("mt-username");
  var user = node ? String(node.textContent || "").replace(/^\s+|\s+$/g, "") : "";
  if (!user || user.indexOf("$(") === 0) return;

  try {
    var xhr = new global.XMLHttpRequest();
    xhr.open("GET", origin.replace(/\/$/, "") + "/hotspot/session/" + encodeURIComponent(user), false);
    xhr.send(null);
    if (xhr.status !== 200 || !xhr.responseText) return;
    var data = JSON.parse(xhr.responseText);
    if (!data || !data.expiresAt) return;
    global.LIMETE_SESSION = {
      planLabel: data.planLabel || null,
      planSeconds: data.planSeconds || null,
      startedAt: data.startedAt || null,
      expiresAt: data.expiresAt,
      unlimited: data.unlimited === true
    };
    if (typeof data.ticketUrl === "string" && data.ticketUrl.indexOf("/ticket/") !== -1) {
      global.LIMETE_TICKET = data.ticketUrl;
    }
  } catch (e) {
    /* Le portail reste utilisable sans date si Laravel n'est pas joignable. */
  }
})(window);
