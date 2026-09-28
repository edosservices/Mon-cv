# UniPay Congo

LIMETE WIFI MANAGER prépare l’appel UniPay. Aucune transaction live n’est lancée par les tests ni par l’audit logiciel. Le mode live n’est jamais choisi tout seul.

Si `UNIPAY_MODE` n’est pas exactement `live`, l’en-tête envoyé est `X-UniPay-Mode: test`. Aucune transaction LIVE n’est alors produite par l’application. Les tests passent par `Http::fake()` et `Http::preventStrayRequests()` : ils n’appellent pas l’API réelle.

Le site public d’UniPay décrit une API REST. Il ne publie pas, dans cette intégration, le schéma exact des champs. Le corps envoyé est celui décrit ci-dessous. Avant un passage en live, comparez-le au contrat remis par UniPay et ajustez `UNIPAY_BASE_URL` sur l’adresse qu’ils fournissent.

## Variables

À placer uniquement dans `.env`. Ne pas les écrire dans Git, dans une vue, dans un journal ou dans un ticket.

```env
UNIPAY_API_KEY=
UNIPAY_BASE_URL=
UNIPAY_WEBHOOK_SECRET=
UNIPAY_MODE=test
```

| Variable | Rôle |
|---|---|
| `UNIPAY_API_KEY` | Clé lue par `config/services.php`. Vide : la page affiche « UniPay non configuré » et aucun HTTP n’est envoyé. |
| `UNIPAY_BASE_URL` | Origine officielle fournie par UniPay, sans barre finale. Exemple de forme : `https://api.exemple-unipay`. Vide : aucun HTTP n’est envoyé. |
| `UNIPAY_WEBHOOK_SECRET` | Secret HMAC du webhook. Vide : toute notification est refusée. |
| `UNIPAY_MODE` | `test` par défaut. Seule la valeur exacte `live` envoie l’en-tête de mode live. Toute autre valeur reste `test`. |

Les secrets sont lus depuis `config('services.unipay.*')`, qui lit `.env`. Le code ne contient pas de clé.

## Initiation

Quand la clé et l’URL sont présentes, le serveur envoie :

`POST {UNIPAY_BASE_URL}/payments`

En-têtes : `Authorization: Bearer`, `Accept: application/json`, `X-UniPay-Mode: test` ou `live`.

Corps :

- `reference` : référence interne `PAY-…`
- `amount`, `currency`
- `customer.phone`, `customer.name` si le client les a donnés
- `callback_url` : `https://{APP_URL}/payments/unipay/webhook`
- `return_url` : page de commande de la boutique
- `metadata` : `tenant_id`, `wifi_zone_id`, `sale_id`, `plan_reference`, `voucher_reference` si un ticket existe déjà

L’identifiant renvoyé (`transaction_id` ou `id`) est enregistré dans `provider_reference`. Cette réponse ne crée pas de ticket, même si elle annonce un succès. Le paiement reste en attente ou en cours.

## Webhook

URL à déclarer chez UniPay :

`POST https://{APP_URL}/payments/unipay/webhook`

Signature attendue : en-tête `X-UniPay-Signature` (ou `X-Payment-Signature`), HMAC-SHA256 hexadécimal du corps brut, clé `UNIPAY_WEBHOOK_SECRET`. Le préfixe `sha256=` est accepté.

Corps minimal :

```json
{
  "reference": "PAY-XXXXXXXXXX",
  "transaction_id": "identifiant-unipay",
  "amount": "1000.00",
  "currency": "CDF",
  "status": "success"
}
```

`status` accepté, après traduction : `pending`, `processing`, `success`, `failed`, `cancelled`. Les alias `paid`, `succeeded`, `declined`, `canceled` et `expired` sont traduits. Un statut inconnu est refusé.

Le webhook vérifie la signature, la référence interne, le montant, la devise et l’identifiant de transaction. Un second envoi `success` ne crée pas un second ticket. `PaymentSettlement` n’est appelé qu’après cette vérification. Le navigateur qui revient sur la commande, même avec `?payment_status=success`, ne crée pas de ticket.

## Vérifier une transaction

`GET {UNIPAY_BASE_URL}/payments/{transaction_id}`

Le bouton « Actualiser » de la commande appelle cette lecture. Le ticket n’est créé que si UniPay renvoie un succès dont la référence, le montant, la devise et l’identifiant correspondent au paiement enregistré. Un paiement `pending` ou `failed` ne donne pas de ticket actif.

Après un succès confirmé : `PaymentSettlement` → `SaleService::confirm()` → ticket → `SyncHotspotUser` vers les MikroTik autorisés de la zone.

## Passer du test au live

1. Garder `UNIPAY_MODE=test` le temps de vérifier l’initiation, le webhook et le statut avec les réponses de test d’UniPay.
2. Vérifier sur `/admin/production-check` : clé CONFIGURED, mode TEST, webhook CONFIGURED. La clé elle-même ne s’affiche pas.
3. Changer ensuite, à la main, `UNIPAY_MODE=live` et `UNIPAY_BASE_URL` vers l’adresse live fournie par UniPay.
4. Recharger la configuration (`php artisan config:clear` si elle était mise en cache).
5. Le contrôle doit alors afficher LIVE. Il n’envoie toujours pas de paiement tout seul.

## Diagnostiquer un webhook

| Réponse | Lecture |
|---|---|
| 401 | Signature absente, secret vide, ou signature différente du corps reçu. |
| 404 | Référence interne inconnue, ou fournisseur différent de `unipay`. |
| 422 | Montant, devise, ou identifiant de transaction différent de l’enregistrement. |
| 200 `accepted` | Notification retenue. Un succès crée un seul ticket. |
| 200 `duplicate` | Le même succès a déjà été traité. |

Le journal `unipay.payment` contient l’action, la référence, le mode, le succès et le code HTTP. Il ne contient pas la clé ni le secret. L’événement est aussi dans `payment_events`, avec les champs sensibles masqués.
