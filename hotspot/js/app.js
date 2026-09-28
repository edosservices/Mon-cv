/*
 * Comportement du portail LIMETE WIFI.
 * Aucun identifiant n'est enregistré. Aucun envoi vers un serveur externe.
 * Le formulaire de connexion reste celui du HotSpot MikroTik.
 */
(function (global) {
  "use strict";

  var WHATSAPP = "243860392283";

  function textOf(id) {
    var node = document.getElementById(id);
    return node ? (node.textContent || "").replace(/^\s+|\s+$/g, "") : "";
  }

  function isRawVariable(value) {
    return !value || value.indexOf("$(") === 0;
  }

  /* Durées MikroTik : 18h32m, 2d3h, 1w, 90s, ou un nombre de secondes. */
  function parseDuration(value) {
    if (value === null || value === undefined) return null;
    var raw = String(value).replace(/^\s+|\s+$/g, "");
    if (isRawVariable(raw)) return null;
    if (/^\d+$/.test(raw)) return parseInt(raw, 10);

    var clock = raw.match(/^(?:(\d+):)?(\d+):(\d+)$/);
    if (clock) {
      return (parseInt(clock[1] || "0", 10) * 3600) +
        (parseInt(clock[2], 10) * 60) +
        parseInt(clock[3], 10);
    }

    var re = /(\d+)\s*([wdhms])/gi;
    var match;
    var total = 0;
    var found = false;
    while ((match = re.exec(raw))) {
      found = true;
      var n = parseInt(match[1], 10);
      var unit = match[2].toLowerCase();
      if (unit === "w") total += n * 604800;
      else if (unit === "d") total += n * 86400;
      else if (unit === "h") total += n * 3600;
      else if (unit === "m") total += n * 60;
      else total += n;
    }
    return found ? total : null;
  }

  function formatDuration(totalSeconds) {
    var s = Math.max(0, Math.floor(totalSeconds));
    var days = Math.floor(s / 86400);
    var hours = Math.floor((s % 86400) / 3600);
    var mins = Math.floor((s % 3600) / 60);
    if (days > 0 && hours === 0 && mins === 0) return days + " j";
    if (days > 0) return days + " j " + hours + "h " + mins + "min";
    if (hours > 0) return hours + "h" + (mins ? " " + mins + "min" : "");
    if (mins > 0) return mins + "min";
    return s + " s";
  }

  function formatDate(iso, timeZone) {
    var date = new Date(iso);
    if (isNaN(date.getTime())) return null;
    try {
      var parts = new Intl.DateTimeFormat("fr-FR", {
        timeZone: timeZone || "Africa/Kinshasa",
        day: "2-digit",
        month: "2-digit",
        year: "numeric",
        hour: "2-digit",
        minute: "2-digit",
        hourCycle: "h23"
      }).formatToParts(date);
      function pick(type) {
        for (var i = 0; i < parts.length; i++) {
          if (parts[i].type === type) return parts[i].value;
        }
        return "";
      }
      return pick("day") + "/" + pick("month") + "/" + pick("year") + " " + pick("hour") + ":" + pick("minute");
    } catch (e) {
      return null;
    }
  }

  function matchPlan(username, config) {
    if (!config || !config.matchTicketCode || !config.plans || !username) return null;
    var name = String(username).toLowerCase();
    var keys = Object.keys(config.plans);
    for (var i = 0; i < keys.length; i++) {
      var key = String(keys[i]).toLowerCase();
      var re = new RegExp("(^|[^a-z0-9])" + key.replace(/[.*+?^${}()|[\]\\]/g, "\\$&") + "([^a-z0-9]|$)");
      if (re.test(name)) return config.plans[keys[i]];
    }
    return null;
  }

  /*
   * Le temps restant commercial vient uniquement de LIMETE_SESSION.expiresAt.
   * $(session-time-left) est le quota de la session routeur, pas la validité du ticket.
   */
  function resolveStatus(input, session, config, nowMs) {
    var now = nowMs || Date.now();
    var zone = (config && config.timezone) || "Africa/Kinshasa";
    var view = {
      state: "active",
      planLabel: "Selon votre ticket",
      startText: null,
      endText: null,
      elapsedSeconds: null,
      elapsedScope: "session",
      remainSeconds: null,
      remainKnown: false,
      percent: null
    };

    var plan = matchPlan(input.username, config);
    if (plan && plan.label) view.planLabel = plan.label;
    if (session && session.planLabel) view.planLabel = session.planLabel;

    if (session && session.expiresAt) {
      var exp = new Date(session.expiresAt);
      if (!isNaN(exp.getTime())) {
        var remainPlan = Math.floor((exp.getTime() - now) / 1000);
        view.endText = formatDate(session.expiresAt, zone);
        view.remainSeconds = remainPlan;
        view.remainKnown = true;
        view.state = remainPlan > 0 ? "active" : "expired";
        if (session.startedAt) {
          var start = new Date(session.startedAt);
          if (!isNaN(start.getTime())) {
            view.startText = formatDate(session.startedAt, zone);
            view.elapsedSeconds = Math.max(0, Math.floor((now - start.getTime()) / 1000));
            view.elapsedScope = "plan";
            var total = Math.floor((exp.getTime() - start.getTime()) / 1000);
            if (total > 0) {
              view.percent = Math.max(0, Math.min(100, Math.round((remainPlan / total) * 100)));
            }
          }
        }
        if (view.percent === null && session.planSeconds > 0) {
          view.percent = Math.max(0, Math.min(100, Math.round((remainPlan / session.planSeconds) * 100)));
        }
        return view;
      }
    }

    var elapsed = parseDuration(input.uptimeSecs);
    if (elapsed === null) elapsed = parseDuration(input.uptime);
    view.elapsedSeconds = elapsed;
    view.elapsedScope = "session";
    return view;
  }

  function classifyError(message) {
    var msg = message || "";
    if (/session expir|not logged in|session timeout|idle timeout/i.test(msg)) {
      return { title: "Session expirée", state: "session" };
    }
    if (/forfait expir|uptime limit|temps .*termin|validity/i.test(msg)) {
      return { title: "Forfait expiré", state: "expired" };
    }
    if (/nom d'utilisateur ou le mot de passe|invalid username|invalid password|mot de passe/i.test(msg)) {
      return {
        title: "Erreur de connexion",
        state: "error",
        detail: "Le nom d'utilisateur ou le mot de passe ne correspond pas au ticket."
      };
    }
    if (/déjà utilisé|no more sessions|session limit|already authorizing/i.test(msg)) {
      return {
        title: "Erreur de connexion",
        state: "error",
        detail: "Ce ticket est déjà utilisé. Libérez l'autre appareil ou contactez l'assistance."
      };
    }
    if (/trop de temps|radius|indisponible|shutting down|redémarre/i.test(msg)) {
      return {
        title: "Erreur de connexion",
        state: "error",
        detail: "Le service est momentanément indisponible. Réessayez dans un instant."
      };
    }
    return { title: "Erreur de connexion", state: "error" };
  }

  function initPasswordToggle() {
    var input = document.getElementById("password");
    var button = document.getElementById("toggle-password");
    if (!input || !button) return;
    button.addEventListener("click", function () {
      var show = input.type === "password";
      input.type = show ? "text" : "password";
      button.textContent = show ? "Masquer" : "Afficher";
      button.setAttribute("aria-pressed", show ? "true" : "false");
    });
  }

  function initLoginForm() {
    var form = document.forms.login;
    var button = document.getElementById("login-button");
    if (!form || !button) return;
    form.addEventListener("submit", function () {
      button.disabled = true;
      button.textContent = "Connexion en cours...";
    });
  }

  function initError() {
    var box = document.getElementById("error-box");
    var node = document.getElementById("mt-error");
    if (!box || !node) return;
    var raw = (node.textContent || "").replace(/^\s+|\s+$/g, "");
    var info = classifyError(raw);
    var title = document.getElementById("error-title");
    if (title) title.textContent = info.title;
    var english = /invalid|password|username|limit|radius|timeout|not responding|wrong|already authorizing/i.test(raw);
    if (english && info.detail) node.textContent = info.detail;
    box.className = "alert is-" + info.state;

    if (document.getElementById("mt-data")) return;
    var banner = document.getElementById("status-banner");
    if (!banner) return;
    banner.className = "banner is-" + info.state;
    setText("state-badge", info.state === "expired" ? "🔴 Expiré" : info.state === "session" ? "Session expirée" : "🔴 Erreur");
    setText("state-title", info.title);
  }

  function setText(id, value) {
    var node = document.getElementById(id);
    if (node && value !== null && value !== undefined && value !== "") node.textContent = value;
  }

  function applyStatus(view) {
    var banner = document.getElementById("status-banner");
    if (!banner) return;
    var expired = view.state === "expired";
    banner.className = "banner " + (expired ? "is-expired" : "is-active");
    setText("state-badge", expired ? "🔴 Expiré" : "🟢 Connecté");
    setText("state-title", expired ? "FORFAIT EXPIRÉ" : "CONNECTÉ");
    setText("state-text", expired
      ? "Le temps de votre ticket est terminé. Contactez l'assistance si vous pensez qu'il vous reste du temps."
      : "Votre accès Internet est actif.");
    setText("plan-label", view.planLabel);

    if (view.startText) setText("start-value", view.startText);
    if (view.endText) setText("end-value", view.endText);
    var datesNote = document.getElementById("dates-note");
    if (datesNote) datesNote.hidden = Boolean(view.startText || view.endText);

    if (view.elapsedSeconds !== null) {
      setText("elapsed-value", formatDuration(view.elapsedSeconds));
    }
    setText("elapsed-note", view.elapsedScope === "plan" ? "Depuis l'activation" : "Cette connexion");

    var remainNode = document.getElementById("remain-big");
    var remainFact = document.getElementById("remain-value");
    if (view.remainKnown) {
      var label = view.remainSeconds > 0 ? formatDuration(view.remainSeconds) : "Terminé";
      if (remainNode) remainNode.textContent = label;
      if (remainFact) remainFact.textContent = label;
    }

    var block = document.getElementById("percent-label");
    var fill = document.getElementById("fill");
    var track = document.getElementById("track");
    if (view.percent !== null && view.remainKnown) {
      var shown = Math.max(0, view.percent);
      if (track) {
        track.hidden = false;
        track.setAttribute("aria-valuenow", String(shown));
        track.setAttribute("aria-valuetext", shown + " % restant");
      }
      if (fill) fill.style.width = shown + "%";
      if (block) block.textContent = shown + "% restant";
    } else {
      if (track) track.hidden = true;
      if (block) {
        block.textContent = view.remainKnown
          ? "Pourcentage disponible avec le détail du forfait."
          : "Le temps restant s'affichera ici.";
      }
    }
  }

  function initStatus() {
    if (!document.getElementById("mt-data")) return;

    var input = {
      username: textOf("mt-username"),
      uptime: textOf("mt-uptime"),
      uptimeSecs: textOf("mt-uptime-secs"),
      timeLeft: textOf("mt-left"),
      timeLeftSecs: textOf("mt-left-secs")
    };
    var session = global.LIMETE_SESSION || null;
    var config = global.LIMETE_CONFIG || null;
    applyStatus(resolveStatus(input, session, config, Date.now()));

    /* Décompte local seulement si une date de fin a été fournie.
       Sans cette date, on garde la valeur du routeur. */
    if (!session || !session.expiresAt) return;
    var end = new Date(session.expiresAt).getTime();
    if (isNaN(end)) return;
    global.setInterval(function () {
      applyStatus(resolveStatus(input, session, config, Date.now()));
    }, 30000);
  }

  function initWhatsApp() {
    var link = document.getElementById("wa-link");
    if (!link) return;
    var user = textOf("mt-username");
    var ip = textOf("mt-ip");
    var mac = textOf("mt-mac");
    if (isRawVariable(user) && isRawVariable(ip)) return;
    var parts = ["Bonjour LIMETE WIFI, j'ai besoin d'aide."];
    if (!isRawVariable(user)) parts.push("Ticket : " + user + ".");
    if (!isRawVariable(ip)) parts.push("Adresse : " + ip + ".");
    if (!isRawVariable(mac)) parts.push("Appareil : " + mac + ".");
    link.href = "https://wa.me/" + WHATSAPP + "?text=" + encodeURIComponent(parts.join(" "));
  }

  function initShop() {
    var url = global.LIMETE_SHOP;
    var box = document.getElementById("buy-box");
    var link = document.getElementById("buy-link");
    if (!box || !link || typeof url !== "string") return;
    if (url.indexOf("https://") !== 0 && url.indexOf("http://") !== 0) return;
    link.href = url;
    box.hidden = false;
  }

  function initTicket() {
    var url = global.LIMETE_TICKET;
    var box = document.getElementById("ticket-box");
    var link = document.getElementById("ticket-link");
    if (!box || !link || typeof url !== "string") return;
    if (url.indexOf("https://") !== 0 && url.indexOf("http://") !== 0) return;
    if (url.indexOf("/ticket/") === -1) return;
    link.href = url;
    box.hidden = false;
  }

  function boot() {
    initPasswordToggle();
    initLoginForm();
    initError();
    initStatus();
    initWhatsApp();
    initShop();
    initTicket();
  }

  global.LimetePortal = {
    parseDuration: parseDuration,
    formatDuration: formatDuration,
    formatDate: formatDate,
    resolveStatus: resolveStatus,
    classifyError: classifyError
  };

  if (typeof document !== "undefined") {
    if (document.readyState === "loading") {
      document.addEventListener("DOMContentLoaded", boot);
    } else {
      boot();
    }
  }
})(window);
