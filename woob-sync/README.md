# woob-sync

Petit service Python (Flask) qui fait le pont entre le back Symfony et
[woob](https://gitlab.com/woob/woob), la lib qui sait se connecter aux sites
des banques (scraping) pour récupérer comptes et transactions. Symfony ne
parle jamais à woob directement : il envoie un `POST /sync` ici, et ce
service se charge de la connexion réelle à la banque.

## Ce qu'il faut avoir de lancé, et pourquoi

La synchro bancaire, ce sont **3 process séparés**. Les 3 doivent tourner en
même temps pour qu'un sync fonctionne.

| # | Process | Commande | Rôle |
|---|---|---|---|
| 1 | Back Symfony | `php -S 127.0.0.1:8001 -t public` (depuis la racine du projet) | Sert l'API (`/api/...`) que le front appelle |
| 2 | Worker Messenger | `php bin/console messenger:consume async -vv` (depuis la racine du projet) | Traite en tâche de fond les demandes de sync mises en queue par l'API. **Sans lui, un `/sync` reste bloqué en `pending` pour toujours.** |
| 3 | woob-sync | `./venv/bin/python app.py` (depuis ce dossier) | Fait le vrai scraping bancaire. Écoute sur `127.0.0.1:8002`. |

Le front (`pnpm run dev`) reste inchangé, il appelle juste l'API Symfony (port 8001) normalement.

### Démarrage rapide

```bash
# terminal 1 — back Symfony
cd /Users/ardek/Development/perso/wealth_lab
php -S 127.0.0.1:8001 -t public

# terminal 2 — worker de synchro
cd /Users/ardek/Development/perso/wealth_lab
php bin/console messenger:consume async -vv

# terminal 3 — service woob
cd /Users/ardek/Development/perso/wealth_lab/woob-sync
./venv/bin/python app.py
```

Rien à réinstaller si le venv existe déjà (`woob-sync/venv/`). Si besoin de le
recréer : `python3.14 -m venv venv && ./venv/bin/pip install -r requirements.txt`.

## Variables d'env nécessaires (`.env.local` à la racine du projet)

```
WOOB_SYNC_URL=http://127.0.0.1:8002
BANK_SYNC_ENCRYPTION_KEY=<clé sodium générée avec: php -r "echo base64_encode(sodium_crypto_secretbox_keygen());">
MESSENGER_TRANSPORT_DSN=doctrine://default
```

## Comment un sync se déroule

1. Le front crée une connexion bancaire → `POST /api/bank-connections` (module, login/password chiffrés en DB)
2. Le front déclenche le sync → `POST /api/bank-connections/{id}/sync` → statut passe à `pending`, un message est mis en queue
3. Le **worker Messenger** dépile le message, appelle `woob-sync` (`http://127.0.0.1:8002/sync`) avec les identifiants déchiffrés
4. `woob-sync` se connecte réellement à la banque via woob, renvoie comptes + transactions (ou un statut d'erreur)
5. Le back persiste tout en DB, statut passe à `active` (ou `error`/`invalid_credentials`/`action_needed`)

## Debug d'un scraping qui échoue

Si une banque échoue avec une erreur bizarre (page inattendue, etc.), on peut
sauvegarder la page HTML sur laquelle woob s'est planté :

```bash
WOOB_SYNC_DEBUG=1 ./venv/bin/python app.py
```

Le fichier atterrit dans `/tmp/woob_debug_<label>.html`, en lecture seule
pour toi (chmod 600). **Une fois inspecté, supprime-le** — passé le login,
c'est une vraie page de compte bancaire authentifiée. Le mode debug est
désactivé par défaut, ne pas le laisser actif en usage normal.

## Patches spécifiques à certains modules woob

`app.py` contient des monkeypatches ciblés pour corriger des bugs des
modules woob installés automatiquement (`~/.local/share/woob/modules/`,
mis à jour par woob lui-même — donc on ne peut pas patcher ces fichiers
directement, ils seraient écrasés). Voir `MODULE_PATCHES` dans `app.py`.
Actuellement : Boursorama (détection de sa page-tampon de cookie anti-bot,
et limite de la fenêtre d'historique demandée à `since` au lieu de 3 ans
en dur).

## Ajouter une banque

Le module et les champs de config (`login`, `password`, + un éventuel champ
spécifique comme `website` pour le Crédit Agricole) sont définis côté front
dans `wealth_lab_front/src/constants/banks.ts`. Le nom du module doit
correspondre exactement à un module existant dans le dépôt woob — si un
module semble ne plus exister (`ModuleLoadError: Module does not exist`),
woob l'a probablement renommé/fusionné ; chercher le bon nom dans
`~/.local/share/woob/repositories/00-https___updates_woob_tech_3_main_`.
