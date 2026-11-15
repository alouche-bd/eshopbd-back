# Migration vers les nouvelles routes galaxy-api

## Pourquoi

Le back de l'e-shop Biotech Dental parlait jusqu'ici à l'ancienne LaGalaxy :

- le SSO legacy `ssogalaxy` (`URL_SSO`, protocole broker `attach` / `validate` / `login/credentials` / `login/api`) pour la connexion ;
- l'API Symfony `api-galaxy` (`URL_S4A`, `smileys/total`, `smileys/consume`) pour les smileys ;
- un token Keycloak `s4a` en `client_credentials` pour appeler cette API.

Ces services sont remplacés par **galaxy-api** (NestJS) et par Keycloak `s4a` comme fournisseur d'identité. L'e-shop passe directement sur les nouvelles routes : il n'utilise pas les routes de compatibilité legacy-iso (`/v1/*`, `/smileys/total/:uuid`, etc.) exposées par galaxy-api pendant la transition.

Tout l'accès à Galaxy passe par un seul client : `app/Services/Galaxy/GalaxyApiClient.php`. Il gère les tokens Keycloak, déballe l'enveloppe standard `{status, statusCode, result, timestamp}` et transforme les erreurs `{status: false, statusCode, message, code, errors?, details?}` en `GalaxyApiException`, qui garde `code` et `details`. Deux sous-classes typées : `GalaxyPartnerAuthorizationException` (403 `PARTNER_CLIENT_UNKNOWN` / `PARTNER_SCOPE_DENIED`, `missingScopes()`) et `GalaxyAccountAmbiguousException` (409 `ACCOUNT_AMBIGUOUS`, `accountCount()`).

Le flag `GALAXY_API_ENABLED` choisit le chemin : `true` pour les nouvelles routes, `false` pour le code legacy, qui reste en place pour le rollback.

## Correspondance des appels

| Appelant | Appel legacy | Nouvelle route | Auth | Changement payload / réponse |
|---|---|---|---|---|
| `AuthController::loginWithSso` (`POST /api/login-sso`) | SSO `POST attach/api`, `GET validate`, `POST login/credentials` avec `?token=` reçu de Galaxy | Keycloak `s4a` : `GET /realms/s4a/protocol/openid-connect/auth` (code + PKCE S256) puis `POST .../token` (`authorization_code`), puis galaxy-api `GET /integrations/eshop/me` | token **utilisateur** Keycloak (client `biotech-dental-eshop`), scope partenaire `users:read` | Le front envoie `{code, state}` au lieu de `{token}`. `/me` renvoie `PartnerUserDto` `{galaxyUuid, email, firstName, lastName, role, language, billingCountry (nullable), isActive}`, mappé sur `GalaxyPartnerUser`. L'e-shop lit `email` (login / création locale inchangée), renseigne `suid` avec `galaxyUuid` quand il est vide et refuse un utilisateur inactif (403 `GALAXY_USER_INACTIVE`). Une réponse sans `galaxyUuid`, `email` ou `isActive` valides est refusée (502). |
| nouveau : `AuthController::galaxyLoginUrl` (`GET /api/galaxy/login-url`) | aucun | construit l'URL d'autorisation Keycloak | public | Renvoie `{success: 1, url}`. `state` et `code_verifier` sont gardés 10 min en cache et ne servent qu'une fois. |
| `AuthController::reverseLoginSSO` (`POST /api/reverse-login-sso`) | SSO `POST login/api` (mot de passe chiffré RSA) puis `login/credentials` | Keycloak `POST .../token` (`grant_type=password`) puis galaxy-api `GET /integrations/eshop/me` | token **utilisateur** Keycloak, scope `users:read` | Même payload `{email, password}`. Identifiants refusés : HTTP 401 `{success: 0, code: "GALAXY_INVALID_CREDENTIALS", message}` (avant : 500). Client e-shop non enregistré ou scope manquant côté galaxy-api : HTTP 502 `GALAXY_PARTNER_CLIENT_UNKNOWN` / `GALAXY_PARTNER_SCOPE_DENIED` (journalisé avec les scopes manquants), jamais présenté comme un mauvais mot de passe. Utilisateur inactif : 403 `GALAXY_USER_INACTIVE`. Ces codes valent aussi pour `login-sso`. |
| `MiddlewareController::getSmileys` (`GET /api/middleware/getSmileys/{uuid}`) | S4A `GET smileys/total/{uuid}` | galaxy-api `GET /integrations/eshop/users/{galaxyUuid}/smileys/balance?salesforceAccountId=` | `client_credentials` (compte de service, `partner_client` en `ANY_USER`), scope `smileys:read` | Nouveau paramètre `salesforceAccountId` (le front envoie `user.sfuid`). Réponse `{success: 1, result: <smileys entiers>, salesforceAccountId}`. Réponses HTTP 200 `{success: 0, code, message}` : `GALAXY_USER_NOT_FOUND` (404 `PARTNER_USER_NOT_FOUND`, avant : `ERR-SMY-002`), `GALAXY_USER_INACTIVE` (403 `PARTNER_USER_INACTIVE`), `GALAXY_ACCOUNT_AMBIGUOUS` + `accounts` (409 `ACCOUNT_AMBIGUOUS` : plusieurs comptes Salesforce et pas de `salesforceAccountId`), `GALAXY_ACCOUNT_NOT_MEMBER` (403 `ACCOUNT_NOT_MEMBER` : compte qui n'est pas celui de l'utilisateur, ou aucun compte), `GALAXY_ACCOUNT_UNAVAILABLE` (autre 403/409 sans code connu). Refus partenaire : HTTP 502 `GALAXY_PARTNER_CLIENT_UNKNOWN` / `GALAXY_PARTNER_SCOPE_DENIED`. Galaxy en erreur (dont 503 `SALESFORCE_UNAVAILABLE`) : 502. |
| `MiddlewareController::postOrder` (`POST /api/middleware/post-order`, `smileyamount > 0`) | S4A `POST smileys/consume/{uuid}` `{act: debit, amount, buyer: {uuid: PLATFORM_UUID}}` | galaxy-api `POST /integrations/eshop/users/{galaxyUuid}/smileys/debits` `{amount, reference, salesforceAccountId?}` | `client_credentials` (compte de service, `ANY_USER`), scope `smileys:debit` | `amount` en smileys entiers (1 smiley = 1 €). `reference` = id du panier, et un rejeu avec la même référence ne débite pas deux fois (`replayed: true`). La plateforme vendeuse est le `platformId` de la ligne `partner_client` (à défaut `BIOTECH_DENTAL_E_SHOP`) et ne vient plus de `PLATFORM_UUID`. Une erreur de débit est journalisée avec son code galaxy-api (`ACCOUNT_AMBIGUOUS`, `PARTNER_SCOPE_DENIED`…) sans bloquer la commande, comme avant. |
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

## Autorisation côté galaxy-api : ligne `partner_client`

galaxy-api n'utilise plus `ESHOP_KEYCLOAK_CLIENT_IDS` ni le rôle `SERVICE` pour `/integrations/eshop/*` (ni `ESHOP_SMILEY_PLATFORM_CODE`). Chaque route porte `@PartnerScope` : le `azp` du token Keycloak doit avoir une ligne active dans la table `partner_client` (modèle Prisma `PartnerClient`) avec le bon scope.

| Route | Scope requis | Token |
|---|---|---|
| `GET /integrations/eshop/me` | `users:read` | utilisateur |
| `GET /integrations/eshop/users/{galaxyUuid}/smileys/balance` | `smileys:read` | compte de service |
| `POST /integrations/eshop/users/{galaxyUuid}/smileys/debits` | `smileys:debit` | compte de service |

Le solde et le débit sont appelés avec un token `client_credentials`, qui ne porte pas de `galaxy_uuid` : la ligne doit être en `userAccess = 'ANY_USER'`. En `TOKEN_USER` (valeur par défaut), galaxy-api refuse ces appels (403 `PARTNER_SCOPE_DENIED`, « This client may only act for the user who signed the token »). `platformId` pointe sur la plateforme `BIOTECH_DENTAL_E_SHOP`, où les débits sont enregistrés. Pas de `planetId`.

Ligne à demander, par environnement (MariaDB 10.5, à relire avant exécution) :

```sql
INSERT INTO `partner_client`
  (`clientId`, `name`, `scopes`, `userAccess`, `platformId`, `planetId`, `isActive`, `createdAt`, `updatedAt`)
SELECT
  'biotech-dental-eshop',
  'Biotech Dental e-shop',
  '["users:read","smileys:read","smileys:debit"]',
  'ANY_USER',
  p.`id`,
  NULL,
  1,
  CURRENT_TIMESTAMP(3),
  CURRENT_TIMESTAMP(3)
FROM `platform` p
WHERE p.`code` = 'BIOTECH_DENTAL_E_SHOP'
ON DUPLICATE KEY UPDATE
  `scopes` = VALUES(`scopes`),
  `userAccess` = VALUES(`userAccess`),
  `platformId` = VALUES(`platformId`),
  `isActive` = 1,
  `updatedAt` = CURRENT_TIMESTAMP(3);
```

- Si la plateforme `BIOTECH_DENTAL_E_SHOP` n'existe pas, la requête n'insère rien : vérifier avec `SELECT ROW_COUNT();` (attendu : 1, ou 2 sur une mise à jour).
- Un client par environnement (par exemple `biotech-dental-eshop-recette`) : remplacer `clientId` et `name`.
- galaxy-api garde les lignes en cache 60 s par processus : attendre une minute ou redémarrer après l'insertion.

Erreurs galaxy-api liées à cette ligne, et ce qu'en fait l'e-shop :

| galaxy-api | Cause | E-shop |
|---|---|---|
| 401 | token absent, expiré, mauvaise audience | login : 401 `GALAXY_INVALID_CREDENTIALS` ; solde : 502 |
| 403 `PARTNER_CLIENT_UNKNOWN` | `azp` absent de `partner_client`, ou `isActive = 0` | 502 `GALAXY_PARTNER_CLIENT_UNKNOWN`, journalisé |
| 403 `PARTNER_SCOPE_DENIED` + `details.missing` | scope manquant, ou ligne en `TOKEN_USER` pour un appel de compte de service | 502 `GALAXY_PARTNER_SCOPE_DENIED`, journalisé avec `missingScopes` |
| 409 `ACCOUNT_AMBIGUOUS` + `details.accounts` | plusieurs comptes Salesforce et pas de `salesforceAccountId` | `GALAXY_ACCOUNT_AMBIGUOUS` (`GalaxyAccountAmbiguousException`) |
| 403 `ACCOUNT_NOT_MEMBER` | compte demandé hors des comptes de l'utilisateur, ou aucun compte | `GALAXY_ACCOUNT_NOT_MEMBER` |

## Client Keycloak à créer dans `s4a`

Client `biotech-dental-eshop` (un par environnement si les URLs diffèrent, par exemple `biotech-dental-eshop-recette`, avec alors sa propre ligne `partner_client`) :

- **Client authentication** : activée (client confidentiel) ;
- **Standard flow** : activé, avec redirect URI `https://shop.biotech-dental.com/login-sso` (et l'URL de recette), PKCE `S256` ;
- **Direct access grants** : activé (formulaire email + mot de passe de l'e-shop) ;
- **Service accounts** : activé. Le rôle `SERVICE` n'est plus nécessaire : l'autorisation vient de la ligne `partner_client` (voir plus haut) ;
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

1. **galaxy-api** : déployer une version qui contient `partner_client` (pile au-dessus de `feat/eshop-new-routes`, validée à `6e6fc05`), en recette, puis en préprod et en prod. Vérifier que la plateforme `BIOTECH_DENTAL_E_SHOP` existe, puis insérer la ligne `partner_client` (SQL ci-dessus).
2. **Keycloak** : créer ou ajuster le client `biotech-dental-eshop` (ci-dessus), avec le compte de service et le mapper d'audience.
3. **Back e-shop** : déployer cette branche avec `GALAXY_API_ENABLED=false`, lancer `scripts/setup-galaxy-api.sh`, puis passer `GALAXY_API_ENABLED=true`.
4. **Front e-shop** : déployer la branche `feat/galaxy-api-new-routes` du front. Elle reste compatible avec un back en mode legacy (`?token=`).
5. **galaxy-front** : la planète `biotech` est déjà en stratégie `KEYCLOAK` et pointe sur `https://shop.biotech-dental.com/login-sso`. Rien à changer.

## Rollback

Repasser `GALAXY_API_ENABLED=false` sur le back (`scripts/setup-galaxy-api.sh --disable --non-interactive`) puis redémarrer. Le code legacy (SSO broker, S4A) est toujours là et réutilise les anciennes variables. Le front n'a pas besoin d'être redéployé : une arrivée `/login-sso?token=…` retrouve le flux legacy.

## Tests

- `vendor/bin/phpunit --filter Galaxy` : tests unitaires du client (enveloppe, erreurs, cache du token, PKCE, `state` à usage unique) et des routes `getSmileys`, `galaxy/login-url`, `login-sso` et `reverse-login-sso` (Keycloak et galaxy-api simulés avec `Http::fake`), hors ligne.
- `vendor/bin/phpunit --filter GalaxyApiContract` : tests de contrat contre une galaxy-api simulée à partir de réponses enregistrées. `tests/fixtures/galaxy-api/openapi-eshop.json` est l'extrait e-shop (`paths` `/integrations/eshop/*` et `components.schemas` `PartnerUserDto`, `EshopSmileyBalanceDto`, `EshopSmileyDebitDto`, `EshopSmileyDebitResponseDto`) du document OpenAPI généré par `npm run doc:postman` sur galaxy-api au commit `6e6fc05`. `tests/fixtures/galaxy-api/responses.json` contient les réponses, dans l'enveloppe réelle (`response.interceptor.ts`, `global-exception.filter.ts`), y compris les refus `partner_client` et les erreurs de compte. Le test valide chaque fixture contre les schémas, vérifie chemins, query et corps du débit contre le document, et fait passer `getSmileys` et `reverse-login-sso` par chaque réponse. Pour suivre une nouvelle version de galaxy-api : régénérer le document, réextraire ces chemins et schémas, mettre à jour `source.commit` dans les deux fichiers et relancer le test.
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
- **Commande pour un client de laboratoire** : le front ne connaît pas le compte Salesforce du client final. galaxy-api prend le compte unique de l'utilisateur ; s'il en a plusieurs, le débit est refusé (409 `ACCOUNT_AMBIGUOUS`) et journalisé avec ce code, sans compensation. Le solde affiché reste celui du laborantin, comme avant.
- **Choix du compte Salesforce** : l'e-shop n'a pas la liste des comptes d'un utilisateur. Sur `GALAXY_ACCOUNT_AMBIGUOUS`, le front affiche un message dédié mais ne peut pas proposer de choix ; il envoie toujours `user.sfuid` quand il le connaît.
- **Restes legacy** : `GenerateDataController` (`POST /api/middleware/test`, public, écrit des données de test), `postSmileys` et la route cassée `consume-smileys` appellent encore S4A. Ils sont à supprimer.
- **Proxys Sage / Salesforce** : `factureGalaxyPdf`, `lireExisteEmailStrictLaGalaxy` et `clientsLinkedToLab` ne passent pas par galaxy-api et restent sur le middleware.
