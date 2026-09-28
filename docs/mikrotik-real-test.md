# Premier test MikroTik réel

REAL MIKROTIK NOT TESTED.

Aucun MikroTik physique n’a été joint pendant l’audit logiciel. `192.0.2.55` est une adresse de documentation (RFC 5737). Elle ne doit pas servir à déclarer un succès.

## Avant de commencer

1. Le serveur Laravel doit pouvoir ouvrir le port API du routeur (8728, ou 8729 si API-SSL).
2. Ajouter le routeur depuis le tableau de l’entrepreneur : hôte, port, utilisateur, mot de passe. Le mot de passe est chiffré et n’est pas réaffiché.
3. L’associer à la WiFi Zone qui vendra les tickets.
4. Ne pas coller le mot de passe dans un ticket, un journal, une capture ou Git.

## Lecture

Ouvrir `/admin/production-check` et cliquer « Tester le MikroTik ».

La lecture tente, dans l’ordre : résolution si le hôte est un nom, TCP API, TCP API-SSL si ce mode est choisi, authentification, identité, version RouterOS, uptime, CPU, mémoire, interfaces, adresses, DNS, HotSpot, profils, utilisateurs, sessions.

Si le TCP échoue, le texte reste : « Le serveur Laravel ne peut pas joindre le MikroTik. » Le bandeau reste REAL MIKROTIK NOT TESTED. Aucune valeur non lue ne devient un succès.

Si le HotSpot ou un profil de forfait manque, la page dit « Configuration manquante » ou « profil inexistant ». Elle ne crée rien. La création passe par « Préparer mon MikroTik », après affichage de l’actuel, du proposé et de l’impact, puis confirmation.

## Utilisateur de test

Après une lecture API réussie seulement : « Créer un utilisateur de test réel ». Le nom est `LIMETE_TEST_` suivi de l’horodatage. Le profil choisi doit déjà exister. Le succès n’est affiché que si RouterOS relit ce nom. « Supprimer l'utilisateur de test » ne supprime que ce compte.

## Portail

Copier `hotspot/` sur le routeur et coller le bloc `LIMETE_ZONE` proposé dans l’écran MikroTik. Tant que le routeur n’a pas renvoyé ces fichiers, leur présence sur l’appareil reste NOT TESTED.

## Parcours client

À faire sur le WiFi réel, une étape à la fois. Chaque étape reste NOT TESTED tant qu’elle n’a pas été observée.

1. Le client joint le WiFi.
2. Il arrive sur le portail.
3. Il ouvre la boutique de cette zone.
4. Il choisit le forfait.
5. Il choisit le paiement.
6. Le paiement reste en attente.
7. Le retour du navigateur ne crée pas le ticket.
8. La confirmation vient du webhook ou de la vérification UniPay, pas de la page.
9. Le ticket apparaît.
10. Le compte HotSpot est créé sur les routeurs autorisés de la zone.
11. `synchronized` n’est posé que si RouterOS confirme.
12. Le client reçoit le code.
13. Il se connecte au portail.
14. RouterOS l’authentifie.
15. L’accès Internet est observé, pas supposé.
16. Le statut montre le code, le forfait, les dates, le temps restant, l’IP et l’état connecté.
17. Déconnexion.
18. Reconnexion.
19. `expires_at` ne change pas. Pour 24 h : `activated_at + 24 heures`.
20. À l’échéance, l’accès est refusé, le ticket est expiré, et le compte RouterOS suit la règle déjà prévue.

## Isolation à recontrôler sur le matériel

Un ticket de la zone A ne doit pas être créé sur un routeur de la zone B, ni chez un autre entrepreneur. Le test automatisé le vérifie avec un routeur simulé. Le double routeur physique reste NOT TESTED tant que deux appareils n’ont pas été lus.
