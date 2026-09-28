/*
 * Applique le branding public de la WiFi Zone.
 * LIMETE_ORIGIN et LIMETE_ZONE viennent du bloc collé depuis l'écran MikroTik.
 * Aucun mot de passe ni clé API n'est demandé ni envoyé.
 */
(function (global) {
  var origin = global.LIMETE_ORIGIN;
  var zone = global.LIMETE_ZONE;
  if (typeof origin !== "string" || (origin.indexOf("https://") !== 0 && origin.indexOf("http://") !== 0)) return;
  if (typeof zone !== "string" || !/^[a-z0-9-]{1,80}$/.test(zone)) return;

  var brand = null;
  try {
    var xhr = new global.XMLHttpRequest();
    xhr.open("GET", origin.replace(/\/$/, "") + "/wifi/" + encodeURIComponent(zone) + "/marque", false);
    xhr.send(null);
    if (xhr.status !== 200 || !xhr.responseText) return;
    if (/password|api[_-]?key|client_secret|BEGIN PRIVATE/i.test(xhr.responseText)) return;
    brand = JSON.parse(xhr.responseText);
  } catch (e) {
    return;
  }
  if (!brand || typeof brand.name !== "string" || brand.name === "") return;

  function text(node, value) {
    if (node && typeof value === "string" && value !== "") node.textContent = value;
  }

  var titles = global.document.querySelectorAll(".brand h1");
  for (var i = 0; i < titles.length; i++) text(titles[i], brand.name);
  var taglines = global.document.querySelectorAll(".tagline");
  for (var j = 0; j < taglines.length; j++) text(taglines[j], brand.slogan || "");

  if (typeof brand.logo === "string" && /^https?:\/\//.test(brand.logo)) {
    var logos = global.document.querySelectorAll("img.logo");
    for (var k = 0; k < logos.length; k++) {
      logos[k].src = brand.logo;
      logos[k].alt = brand.name;
    }
  }

  if (typeof brand.whatsapp === "string" && /^[0-9]{8,15}$/.test(brand.whatsapp)) {
    var links = global.document.querySelectorAll('a[href*="wa.me"]');
    for (var n = 0; n < links.length; n++) links[n].href = "https://wa.me/" + brand.whatsapp;
  }

  if (typeof brand.primary === "string" && /^#[0-9A-Fa-f]{6}$/.test(brand.primary)) {
    var theme = global.document.querySelector('meta[name="theme-color"]');
    if (theme) theme.setAttribute("content", brand.primary);
  }
})(window);
