# Production

Ce document décrit l’état logiciel. Il ne décrit pas un MikroTik joint ni un paiement live.

REAL MIKROTIK NOT TESTED. NO LIVE PAYMENT EXECUTED.

## A. Installation

PHP 8.3, Composer 2, MySQL ou MariaDB, Node.js 22. Les commandes sont dans le README : `composer install`, `cp .env.example .env`, `php artisan key:generate`, `php artisan migrate --force`, `npm install`, `npm run build`.

Le seeder de démonstration ne s’exécute qu’en `APP_ENV=local`. Ne pas le lancer en production.

## B. .env

Les secrets restent dans `.env`, jamais dans Git. `.env` est ignoré. `.env.example` ne contient que des valeurs vides pour les clés.

À renseigner avant une mise en ligne : `APP_KEY`, `APP_URL` en HTTPS, la base, `SUPER_ADMIN_EMAIL`, `SUPER_ADMIN_PASSWORD`. UniPay : `UNIPAY_API_KEY`, `UNIPAY_BASE_URL`, `UNIPAY_WEBHOOK_SECRET`, `UNIPAY_MODE=test`.

## C. Base de données

`php artisan migrate --force` applique les tables tenants, zones, MikroTik, tickets, ventes, paiements, audits et le fournisseur `unipay`.

Contraintes utiles : `payments.internal_reference` unique, `vouchers.public_token` unique, `vouchers (tenant_id, username)` unique, profils MikroTik uniques par routeur, lien forfait/routeur unique. Les clés étrangères relient tenant, zone, vente, paiement et ticket.

Les tests utilisent SQLite en mémoire. La base locale de développement n’est pas une preuve de production.

## D. Admin

`/admin` est réservé au super admin. `/admin/production-check` affiche PASS, WARN, FAIL ou NOT TESTED. Un avertissement n’est pas converti en succès. Sans routeur authentifié, le bandeau reste REAL MIKROTIK NOT TESTED.

UniPay y apparaît sans sa clé : clé et webhook CONFIGURED ou NOT CONFIGURED, mode TEST ou LIVE.

## E. Entrepreneur

L’inscription crée le tenant, l’utilisateur et l’essai STARTER. Il ne voit que ses données. Une ressource d’un autre tenant répond 404. L’accès admin répond 403.

## F. WiFi Zone

Chaque zone a son slug, son branding et ses forfaits. La boutique est `/wifi/{slug}`. Un ticket est lié à `tenant_id` et `wifi_zone_id`.

## G. MikroTik

Le routeur s’ajoute depuis l’interface, pas depuis `.env`. Le mot de passe est chiffré. `MikrotikService` est le seul client RouterOS. L’adresse `192.0.2.55` est une adresse de documentation : elle ne reçoit aucune commande et ne valide aucun test.

La procédure du premier routeur réel est dans [mikrotik-real-test.md](mikrotik-real-test.md). Elle n’a pas été exécutée.

## H. HotSpot

Les fichiers à copier sont `hotspot/login.html`, `status.html`, `logout.html`, `error.html`, `css/` et `js/`. Ils attendent le bloc `LIMETE_ZONE`, puis `window.LIMETE_SHOP`, `window.LIMETE_SESSION` et `window.LIMETE_TICKET`. Leur présence dans l’application ne prouve pas qu’ils sont sur un routeur.

## I. UniPay

Voir [unipay.md](unipay.md). Défaut : `UNIPAY_MODE=test`. Sans clé, aucun HTTP, message « UniPay non configuré ».

## J. Webhook

`POST /payments/unipay/webhook` exige une signature HMAC du corps brut. Montant, devise, référence interne et identifiant doivent correspondre. Un second succès ne crée pas un second ticket. Le retour du navigateur ne confirme pas le paiement.

Les webhooks Airtel, Orange, M-Pesa et carte restent un format sandbox signé, pas un appel opérateur.

## K. Ticket

Le ticket n’est créé qu’après `PaymentSettlement` puis `SaleService::confirm()`. Pour 24 heures, `expires_at = activated_at + 24 heures`. Une reconnexion ne recalcule pas `expires_at`.

## L. Synchronisation

`SyncHotspotUser` envoie le compte aux MikroTik actifs de la même zone et du même tenant. Un échec laisse le ticket sans `mikrotik_id`, avec un statut de synchronisation échoué. Le retry ne crée pas un doublon si le compte existe déjà. Un routeur d’une autre zone ne reçoit pas la commande.

## M. Test réel

Non exécuté. Voir [mikrotik-real-test.md](mikrotik-real-test.md).

## N. Production

Avant d’ouvrir le service :

1. `APP_ENV=production`, `APP_DEBUG=false`, `APP_URL` en HTTPS.
2. `php artisan migrate --force`.
3. `php artisan config:cache`, `php artisan route:cache`, `php artisan view:cache` sur le serveur de production, puis les retirer avec `config:clear`, `route:clear` et `view:clear` si vous revenez au développement.
4. Cron : `* * * * * php artisan schedule:run`.
5. Worker si `QUEUE_CONNECTION` n’est pas `sync`.
6. Ouvrir `/admin/production-check` et lire les statuts tels quels.

Le cache de config, de routes et de vues a été compilé puis effacé pendant l’audit, pour ne pas laisser le développement sur un cache figé.

## Rollback

« Préparer mon MikroTik » affiche la valeur actuelle, la valeur proposée et l’impact avant toute écriture. Une restauration n’est proposée que lorsqu’un instantané existe. L’application ne réinitialise pas RouterOS, ne supprime pas un HotSpot existant, ne change pas l’adresse du routeur et n’écrase pas un profil du même nom.
