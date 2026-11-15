# Migration vers les nouvelles routes galaxy-api

## Pourquoi

Le back de l'e-shop Biotech Dental parlait jusqu'ici à l'ancienne LaGalaxy :

- le SSO legacy `ssogalaxy` (`URL_SSO`, protocole broker `attach` / `validate` / `login/credentials` / `login/api`) pour la connexion ;
- l'API Symfony `api-galaxy` (`URL_S4A`, `smileys/total`, `smileys/consume`) pour les smileys ;
- un token Keycloak `s4a` en `client_credentials` pour appeler cette API.

Ces services sont remplacés par **galaxy-api** (NestJS) et par Keycloak `s4a` comme fournisseur d'identité. L'e-shop passe directement sur les nouvelles routes : il n'utilise pas les routes de compatibilité legacy-iso (`/v1/*`, `/smileys/total/:uuid`, etc.) exposées par galaxy-api pendant la transition.

Tout l'accès à Galaxy passe par un seul client : `app/Services/Galaxy/GalaxyApiClient.php`. Il gère les tokens Keycloak, déballe l'enveloppe standard `{status, statusCode, result, timestamp}` et transforme les erreurs `{status: false, statusCode, message, errors}` en `GalaxyApiException`.

Le flag `GALAXY_API_ENABLED` choisit le chemin : `true` pour les nouvelles routes, `false` pour le code legacy, qui reste en place pour le rollback.

## Correspondance des appels

| Appelant | Appel legacy | Nouvelle route | Auth | Changement payload / réponse |
|---|---|---|---|---|
| `AuthController::loginWithSso` (`POST /api/login-sso`) | SSO `POST attach/api`, `GET validate`, `POST login/credentials` avec `?token=` reçu de Galaxy | Keycloak `s4a` : `GET /realms/s4a/protocol/openid-connect/auth` (code + PKCE S256) puis `POST .../token` (`authorization_code`), puis galaxy-api `GET /integrations/eshop/me` | token **utilisateur** Keycloak (client `biotech-dental-eshop`) | Le front envoie `{code, state}` au lieu de `{token}`. `/me` renvoie `{galaxyUuid, email, firstName, lastName, role, language, billingCountry}` ; l'e-shop lit `email` (login / création locale inchangée) et renseigne `suid` avec `galaxyUuid` quand il est vide. |
| nouveau : `AuthController::galaxyLoginUrl` (`GET /api/galaxy/login-url`) | aucun | construit l'URL d'autorisation Keycloak | public | Renvoie `{success: 1, url}`. `state` et `code_verifier` sont gardés 10 min en cache et ne servent qu'une fois. |
| `AuthController::reverseLoginSSO` (`POST /api/reverse-login-sso`) | SSO `POST login/api` (mot de passe chiffré RSA) puis `login/credentials` | Keycloak `POST .../token` (`grant_type=password`) puis galaxy-api `GET /integrations/eshop/me` | token **utilisateur** Keycloak | Même payload `{email, password}`. Identifiants refusés : HTTP 401 `{success: 0, message}` (avant : 500). |
| `MiddlewareController::getSmileys` (`GET /api/middleware/getSmileys/{uuid}`) | S4A `GET smileys/total/{uuid}` | galaxy-api `GET /integrations/eshop/users/{galaxyUuid}/smileys/balance?salesforceAccountId=` | `client_credentials` + rôle `SERVICE` | Nouveau paramètre `salesforceAccountId` (le front envoie `user.sfuid`). Réponse `{success: 1, result: <smileys entiers>, salesforceAccountId}`. Utilisateur inconnu : `{success: 0, code: "GALAXY_USER_NOT_FOUND"}` (avant : `ERR-SMY-002`). Compte absent ou ambigu : `GALAXY_ACCOUNT_UNAVAILABLE`. Galaxy en erreur : 502. |
| `MiddlewareController::postOrder` (`POST /api/middleware/post-order`, `smileyamount > 0`) | S4A `POST smileys/consume/{uuid}` `{act: debit, amount, buyer: {uuid: PLATFORM_UUID}}` | galaxy-api `POST /integrations/eshop/users/{galaxyUuid}/smileys/debits` `{amount, reference, salesforceAccountId?}` | `client_credentials` + rôle `SERVICE` | `amount` en smileys entiers (1 smiley = 1 €). `reference` = id du panier, et un rejeu avec la même référence ne débite pas deux fois. La plateforme vendeuse est fixée côté galaxy-api (`ESHOP_SMILEY_PLATFORM_CODE`) et ne vient plus de `PLATFORM_UUID`. Une erreur de débit est journalisée sans bloquer la commande, comme avant. |
| `MiddlewareController::getToken` / `GenerateDataController::getToken` | Keycloak `OAUTH_URI` + `S4A_CLIENT` / `S4A_SECRET` | `GalaxyApiClient::serviceToken()` (`GALAXY_KEYCLOAK_*`), mis en cache jusqu'à expiration moins 30 s | `client_credentials` | Seulement pour le chemin legacy. |
| `GenerateDataController` (`POST /api/middleware/test`), `MiddlewareController::postSmileys`, `POST /api/middleware/consume-smileys` | S4A `invoices`, `orders`, `credits`, `smileys`, `users/application-add`, `users/patient-add` | aucune | — | Outillage de génération de données et route cassée. Non migrés : ces appels restent legacy et sont à supprimer (voir « Écarts connus »). |
| `AuthController::register`, `MiddlewareController` (adresses, factures, produits, réseau…) | middleware Sage X3 (`MIDDLEWARE_SERVICE_BASE_URL`, ex. `lireExisteEmailStrictLaGalaxy`, `factureGalaxyPdf`, `clientsLinkedToLab`) | inchangé | secret middleware | Ce ne sont pas des routes Galaxy : ce sont des proxys Sage X3 et Salesforce (factures, avoirs, réseaux → Salesforce/Sage). Hors périmètre de galaxy-api. |

## Variables d'environnement

À ajouter dans `.env` (le script d'installation les écrit) :

| Variable | Exemple prod | Rôle |
|---|---|---|
| `GALAXY_API_ENABLED` | `true` | `false` pour revenir au legacy |
| `GALAXY_API_BASE_URL` | `https://01.fr.api.biotechgalaxy.com` | base galaxy-api (recette : `https://01.api.new-recette.biotechgalaxy.secure4all.tech`, préprod : `https://01.api.preprod.biotechgalaxy.secure4all.tech`) |
| `GALAXY_KEYCLOAK_URL` | `https://01.fr.oauth.biotechgalaxy.com/auth` | base Keycloak, avant `/realms` |
| `GALAXY_KEYCLOAK_REALM` | `s4a` | realm |
| `GALAXY_KEYCLOAK_CLIENT_ID` | `biotech-dental-eshop` | client Keycloak de l'e-shop |
| `GALAXY_KEYCLOAK_CLIENT_SECRET` | *(secret)* | secret du client, jamais commité |
| `GALAXY_LOGIN_REDIRECT_URI` | `https://shop.biotech-dental.com/login-sso` | URL de retour Keycloak (page `/login-sso` du front) |
| `GALAXY_API_TIMEOUT` | `15` | timeout HTTP en secondes |

Les anciennes variables (`URL_SSO`, `BROKER_UUID`, `BROKER_SECRET`, `URL_S4A`, `S4A_*`, `OAUTH_URI`, `PLATFORM_UUID`) restent en place tant que le rollback doit rester possible.

Côté galaxy-api (branche `feat/eshop-new-routes`) :

- `ESHOP_KEYCLOAK_CLIENT_IDS=biotech-dental-eshop` : clients Keycloak (`azp`) autorisés sur `/integrations/eshop/*`, séparés par des virgules ;
- `ESHOP_SMILEY_PLATFORM_CODE=BIOTECH_DENTAL_E_SHOP` : plateforme sur laquelle les débits sont enregistrés. Elle doit exister dans la table `platform`.

## Client Keycloak à créer dans `s4a`

Client `biotech-dental-eshop` (un par environnement si les URLs diffèrent, par exemple `biotech-dental-eshop-recette`, à ajouter alors dans `ESHOP_KEYCLOAK_CLIENT_IDS`) :

- **Client authentication** : activée (client confidentiel) ;
- **Standard flow** : activé, avec redirect URI `https://shop.biotech-dental.com/login-sso` (et l'URL de recette), PKCE `S256` ;
- **Direct access grants** : activé (formulaire email + mot de passe de l'e-shop) ;
- **Service accounts** : activé, avec le rôle `SERVICE` attribué au compte de service (rôle de realm, ou rôle client du client galaxy-api) ;
- **Mappers** :
  - *Audience* : ajoute le client id de galaxy-api (`KEYCLOAK_AUDIENCE` / `KEYCLOAK_CLIENT_ID` de galaxy-api) dans `aud`, sinon galaxy-api rejette le token ;
  - *User Attribute* `galaxy_uuid` → claim `galaxy_uuid` (access token) ; à défaut, galaxy-api retrouve l'utilisateur par l'email vérifié ;
  - scopes `openid email profile`.

## Script d'installation

```bash
scripts/setup-galaxy-api.sh                       # interactif
echo "$SECRET" | scripts/setup-galaxy-api.sh \
  --non-interactive --client-secret-stdin \
  --base-url https://01.api.new-recette.biotechgalaxy.secure4all.tech \
  --keycloak-url https://01.oauth.recette.biotechgalaxy.secure4all.tech/auth \
  --redirect-uri https://<front-recette>/login-sso
scripts/setup-galaxy-api.sh --check-user <galaxyUuid>   # lit aussi un solde de smileys
scripts/setup-galaxy-api.sh --disable --non-interactive # rollback
```

Le script :

1. vérifie `curl` et `php` ;
2. met à jour `.env` (idempotent, `chmod 600`) ;
3. demande un token `client_credentials` à Keycloak `s4a` pour valider le client et le secret ;
4. appelle `GET /health` sur galaxy-api ;
5. affiche un récapitulatif OK/KO.

Il n'affiche jamais le secret et ne le passe jamais en argument de commande.

## Ordre de déploiement

1. **galaxy-api** : merger `feat/eshop-new-routes` dans `develop`, déployer en recette, puis en préprod et en prod. Vérifier que la plateforme `BIOTECH_DENTAL_E_SHOP` existe.
2. **Keycloak** : créer ou ajuster le client `biotech-dental-eshop` (ci-dessus), avec le rôle `SERVICE` et le mapper d'audience.
3. **Back e-shop** : déployer cette branche avec `GALAXY_API_ENABLED=false`, lancer `scripts/setup-galaxy-api.sh`, puis passer `GALAXY_API_ENABLED=true`.
4. **Front e-shop** : déployer la branche `feat/galaxy-api-new-routes` du front. Elle reste compatible avec un back en mode legacy (`?token=`).
5. **galaxy-front** : la planète `biotech` est déjà en stratégie `KEYCLOAK` et pointe sur `https://shop.biotech-dental.com/login-sso`. Rien à changer.

## Rollback

Repasser `GALAXY_API_ENABLED=false` sur le back (`scripts/setup-galaxy-api.sh --disable --non-interactive`) puis redémarrer. Le code legacy (SSO broker, S4A) est toujours là et réutilise les anciennes variables. Le front n'a pas besoin d'être redéployé : une arrivée `/login-sso?token=…` retrouve le flux legacy.

## Tests

- `vendor/bin/phpunit --filter Galaxy` : tests unitaires du client (enveloppe, erreurs, cache du token, PKCE, `state` à usage unique) et des routes `getSmileys`, `galaxy/login-url`, `login-sso` et `reverse-login-sso` (Keycloak et galaxy-api simulés avec `Http::fake`).
- `vendor/bin/phpunit` : la suite complète. Les tests `MiddlewareTest` appellent le vrai middleware Sage et échouent sans accès réseau ni secret.
- Recette manuelle :
  1. depuis galaxy-front, cliquer la planète Biotech Dental : on passe par Keycloak puis on arrive connecté sur l'e-shop ;
  2. sur `/login`, se connecter avec un compte Galaxy ;
  3. au checkout, vérifier le solde de smileys, puis passer une commande avec smileys et contrôler le débit dans galaxy-api (`GET /smileys` sur le compte Salesforce).

## Écarts connus

- **Mots de passe Keycloak** : galaxy-api vérifie les mots de passe dans sa base, pas dans Keycloak. Le formulaire e-shop et le retour Keycloak ne marchent que pour les comptes qui ont un mot de passe dans `s4a` (synchronisation `KEYCLOAK_ADMIN_SYNC_ENABLED`, `script:backfill-keycloak`).
- **SSO depuis galaxy-front** : la stratégie `KEYCLOAK` ne fait qu'une redirection. Comme la connexion galaxy-front n'ouvre pas de session Keycloak, l'utilisateur peut devoir saisir ses identifiants une fois sur Keycloak.
- **Direct grant** : le formulaire email + mot de passe utilise `grant_type=password`, déconseillé par OAuth 2.1. La cible est une simple redirection vers `/login-sso`.
- **Débit après commande** : comme avant, le débit des smileys a lieu après l'envoi de la commande à Sage, et un échec est seulement journalisé. Il n'y a pas de compensation.
- **Commande pour un client de laboratoire** : le front ne connaît pas le compte Salesforce du client final. galaxy-api prend le compte unique de l'utilisateur ; s'il en a plusieurs, le débit est refusé (409) et journalisé. Le solde affiché reste celui du laborantin, comme avant.
- **Restes legacy** : `GenerateDataController` (`POST /api/middleware/test`, public, écrit des données de test), `postSmileys` et la route cassée `consume-smileys` appellent encore S4A. Ils sont à supprimer.
- **Proxys Sage / Salesforce** : `factureGalaxyPdf`, `lireExisteEmailStrictLaGalaxy` et `clientsLinkedToLab` ne passent pas par galaxy-api et restent sur le middleware.
