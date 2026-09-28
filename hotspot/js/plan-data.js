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
 * Tant que LIMETE_SESSION reste null, les dates restent vides
 * et seul le temps réellement envoyé par le routeur est affiché.
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

  global.LIMETE_SESSION = null;
})(window);
