# Checklist avant merge

Audit logiciel uniquement. REAL MIKROTIK NOT TESTED. NO LIVE PAYMENT EXECUTED.

- [x] Tests Laravel verts
- [x] Aucun secret Git (`.env` ignoré, `.env.example` sans clé, aucune clé UniPay réelle)
- [x] `.env.example` propre
- [x] UniPay TEST par défaut
- [x] Webhook sécurisé (signature HMAC, montant, devise, référence, identifiant, idempotence)
- [x] Ticket après paiement confirmé uniquement
- [x] Tenant isolation (404 ou 403 selon la route)
- [x] Zone isolation
- [x] MikroTik isolation
- [x] Password encryption (routeur et ticket chiffrés)
- [x] Logs sans secrets
- [x] Portal prêt dans le dépôt (`hotspot/`), pas encore copié sur un routeur
- [x] Branding prêt dans l’application, pas encore lu sur un routeur
- [x] Mobile responsive sur les pages parcourues, sans débordement horizontal critique
- [x] Documentation (`README.md`, `docs/production.md`, `docs/mikrotik-real-test.md`, `docs/unipay.md`)
- [x] Migrations appliquées en local et rejouées par les tests
- [x] Production check (`/admin/production-check`)
- [x] Rollback documenté (instantané et confirmation avant écriture RouterOS)
- [x] Aucun faux résultat réel

Le merge de la partie logicielle ne valide ni un MikroTik physique, ni un paiement live, ni un HotSpot installé sur un routeur.
