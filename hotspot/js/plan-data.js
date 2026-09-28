/*
 * Point d'extension des forfaits LIMETE WIFI.
 *
 * MikroTik HotSpot ne fournit pas la date de début ni la date
 * d'expiration commerciale. Il fournit seulement :
 *   $(uptime) / $(uptime-secs)          durée de CETTE connexion
 *   $(session-time-left)                temps restant, s'il existe
 *   $(session-time-left-secs)           le même temps, en secondes
 *
 * $(uptime-secs) n'est donc pas la durée du ticket. Le portail
 * ne calcule jamais une date d'expiration à partir de cette valeur.
 *
 * Pour afficher plus tard le forfait, le début et l'expiration,
 * un script (on-login, User Manager, RADIUS) peut renseigner
 * window.LIMETE_SESSION avant le chargement de app.js :
 *
 *   window.LIMETE_SESSION = {
 *     planLabel: "24 HEURES",
 *     planSeconds: 86400,
 *     startedAt: "2026-09-28T10:00:00+01:00",
 *     expiresAt: "2026-09-29T10:00:00+01:00"
 *   };
 *
 * Tant que LIMETE_SESSION reste null, les dates commerciales restent vides.
 * Le temps de connexion du routeur n'est pas affiché comme validité du ticket.
 *
 * Le portail est copié sur le routeur. Pour le bouton d'achat :
 *   window.LIMETE_SHOP = "https://exemple.test/wifi/limete";
 *
 * matchTicketCode : laisser false. Le passer à true seulement si
 * les identifiants contiennent un code clair (24h, 48h, 7j, 15j, 30j).
 * Cela nomme le forfait. Cela ne crée toujours pas de date.
 */
(function (global) {
  global.LIMETE_CONFIG = {
    timezone: "Africa/Kinshasa",
    matchTicketCode: false,
    plans: {
      "24h": { label: "24 HEURES", seconds: 86400 },
      "48h": { label: "48 HEURES", seconds: 172800 },
      "7j": { label: "7 JOURS", seconds: 604800 },
      "15j": { label: "15 JOURS", seconds: 1296000 },
      "30j": { label: "30 JOURS", seconds: 2592000 }
    }
  };

  if (typeof global.LIMETE_SESSION === "undefined") {
    global.LIMETE_SESSION = null;
  }
  if (typeof global.LIMETE_SHOP === "undefined") {
    global.LIMETE_SHOP = null;
  }
})(window);
