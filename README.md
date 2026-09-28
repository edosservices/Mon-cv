# LIMETE WIFI MANAGER

Plateforme SaaS pour les entrepreneurs qui exploitent des WiFi Zones sur MikroTik RouterOS.

Chaque entrepreneur est un tenant. Il ne voit que ses zones, ses routeurs, ses clients, ses tickets, ses ventes et ses statistiques. Le super admin voit l’ensemble de la plateforme.

Le dossier `hotspot/` reste le portail captif statique à copier sur le routeur. Il est indépendant de cette application.

## Prérequis

- PHP 8.3 avec les extensions `mbstring`, `xml`, `curl`, `mysql`, `sqlite`, `zip`, `gd`, `bcmath`, `intl`
- Composer 2
- MySQL ou MariaDB
- Node.js 22 pour compiler les assets Tailwind

## Installation

```bash
composer install
cp .env.example .env
php artisan key:generate
```

Renseignez la base dans `.env` :

```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=limete_wifi
DB_USERNAME=limete
DB_PASSWORD=
```

Créez la base, puis :

```bash
php artisan migrate --force
php artisan db:seed
php artisan storage:link
npm install
npm run build
php artisan serve
```

L’application répond sur `http://127.0.0.1:8000`.

## Comptes de développement

Le seeder crée un super admin. Le mot de passe vient de l’environnement, avec une valeur locale seulement si elle n’est pas définie :

```env
SUPER_ADMIN_EMAIL=admin@limetewifi.local
SUPER_ADMIN_PASSWORD=local-admin-change-me
```

En environnement `local`, un tenant de démonstration est aussi créé :

- Entreprise : LIMETE WIFI
- Email : `demo@limetewifi.local`
- Mot de passe : valeur de `DEMO_PASSWORD`, sinon `local-demo-change-me`
- Zone publique : `/wifi/limete`
- MikroTik fictif : `192.0.2.55`, utilisateur `demo`, mot de passe `not-a-real-router-password`

Ces identifiants ne sont pas ceux d’un routeur réel. Changez-les avant toute mise en ligne, et ne lancez pas le seeder de démonstration en production. Le tenant de démo n’est créé que si `APP_ENV=local`.

## Abonnements

Les plans STARTER, BUSINESS et PRO sont en base (`saas_plans`). Leurs prix sont vides au départ : le super admin les saisit dans `/admin/plans`. Aucun tarif n’est codé en dur.

À l’inscription, l’entrepreneur reçoit le plan STARTER en essai. La durée se règle avec `LIMETE_TRIAL_DAYS` (14 jours par défaut).

Limites initiales, modifiables par le super admin :

- STARTER : 1 zone, 1 MikroTik, statistiques de base
- BUSINESS : zones et routeurs illimités, statistiques avancées, gestion d’équipe
- PRO : le plan BUSINESS plus l’API

Statuts : `trial`, `active`, `expired`, `suspended`, `cancelled`.

## Paiements

Les transactions sont enregistrées dans `payments`.

Le moyen `manual` fonctionne sans clé : le client ou l’entrepreneur indique une référence, puis la vente ou l’abonnement reste `pending` jusqu’à confirmation.

Les autres moyens refusent de démarrer si leur clé est absente :

```env
AIRTEL_MONEY_API_KEY=
ORANGE_MONEY_API_KEY=
MPESA_API_KEY=
CARD_GATEWAY_SECRET=
```

Ces clés restent dans `.env`, jamais dans Git ni dans le navigateur. Les connecteurs Airtel Money, Orange Money, M-Pesa et carte sont des adaptateurs : aucun appel HTTP opérateur n’est fait, faute de contrat d’API officiel. Un webhook sandbox `POST /payments/{provider}/webhook` accepte une notification signée en HMAC seulement si le secret webhook est défini. Le retour du client sur la page de commande ne confirme jamais un paiement. Le comptoir confirme encore le paiement manuel.

UniPay se configure avec `UNIPAY_API_KEY`, `UNIPAY_BASE_URL`, `UNIPAY_WEBHOOK_SECRET` et `UNIPAY_MODE=test`. Le détail est dans [docs/unipay.md](docs/unipay.md). Sans clé, l’écran affiche « UniPay non configuré » et n’envoie rien. Le mode live ne s’active que si `UNIPAY_MODE` vaut exactement `live`.

## MikroTik

Les mots de passe des routeurs sont chiffrés avec la clé de l’application. Ils ne sont pas renvoyés dans les formulaires ni dans l’API.

`MikrotikService` parle au routeur uniquement depuis le serveur, via l’API RouterOS (port 8728 par défaut). La connexion en clair est celle de RouterOS 6.43 ou plus récent. Le bouton « Tester la connexion » met à jour l’état : connecté, hors ligne ou erreur.

Sans routeur joignable, la vente et le ticket restent enregistrés. `sync_status` indique qu’ils ne sont pas sur le MikroTik, et `mikrotik_id` n’est rempli qu’après une création réussie du compte HotSpot.

Le dossier `hotspot/` se copie dans les fichiers HotSpot du routeur. Il n’embarque aucun secret d’API.

## Parcours

1. `/register` crée le tenant, l’utilisateur entrepreneur et l’abonnement d’essai.
2. `/wifi-zones` crée une zone et sa page `/wifi/{slug}`.
3. `/mikrotiks` relie un routeur à cette zone.
4. `/plans` définit les forfaits et leurs prix.
5. `/vouchers` génère des tickets, imprimables et exportables en PDF, avec QR code.
6. `/wifi/{slug}` est la boutique du client. Le paiement confirmé active le ticket : `activated_at` et `expires_at` sont la durée commerciale, qui continue pendant une déconnexion. Le ticket public est `/ticket/{token}`. `/wifi/{slug}/mes-tickets` retrouve un ticket avec le téléphone et le code, pas avec le téléphone seul.
7. `/active-users` interroge le routeur et peut déconnecter une session.
8. `/statistics` agrège les ventes. `/subscription` suit l’abonnement à la plateforme.
9. `/admin` est réservé au super admin : entrepreneurs, prix, paiements, journal. Une suppression d’entrepreneur est un soft delete.

## API

Préfixe `/api/v1`. Connexion : `POST /api/v1/auth/login`. Les autres routes exigent un jeton Sanctum et le plan PRO.

Un identifiant d’une autre entreprise répond 404. Le filtre `tenant_id` est appliqué côté serveur, y compris si l’URL est modifiée.

## Sécurité

- CSRF sur les formulaires
- limitation des tentatives de connexion
- mots de passe hashés
- secrets MikroTik chiffrés
- policies et permissions pour le personnel
- journal `audit_logs` (mot de passe masqué)

## Modèle de données

`sessions` est la table des sessions web de Laravel. L’historique WiFi est dans `wifi_sessions`, pour ne pas mélanger les deux.

Les jetons d’API sont dans `personal_access_tokens` (Sanctum), qui joue le rôle de la table de jetons.

## Tests

```bash
php artisan test
```

Les tests utilisent SQLite en mémoire. Ils vérifient l’inscription, l’isolation entre tenants, l’absence du mot de passe MikroTik dans le HTML, la vente publique et le blocage d’un compte suspendu.

## Ce qui reste à brancher

- Les contrats réels Airtel Money, Orange Money, M-Pesa et carte, dès que les clés et la documentation opérateur sont disponibles.
- Un routeur MikroTik de test pour valider la connexion API en conditions réelles.
- Les prix SaaS, à saisir par le super admin.
