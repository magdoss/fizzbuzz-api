# FizzBuzz API

Une API REST en Symfony 7.4 et PHP 8.4.

## Sommaire

- **README.md** (cette page)
  - [Endpoints](#endpoints)
  - [Lancer](#lancer)
  - [Essayer](#essayer)
  - [Tester](#tester)
  - [Ce qui a été mesuré](#ce-qui-a-été-mesuré)
  - [Limites connues, et la suite](#limites-connues-et-la-suite)
  - [Contribuer](#contribuer)
- **docs/**
  - **[architecture.md](docs/architecture.md)**
    - [Organisation du code](docs/architecture.md#organisation-du-code)
    - [Trajet d'un appel à `/fizzbuzz`](docs/architecture.md#trajet-dun-appel-à-fizzbuzz)
    - [Le calcul](docs/architecture.md#le-calcul)
    - [Pourquoi envoyer par morceaux](docs/architecture.md#pourquoi-envoyer-par-morceaux)
    - [Validation](docs/architecture.md#validation)
    - [Erreurs](docs/architecture.md#erreurs)
    - [Statistiques](docs/architecture.md#statistiques)
  - **[exploitation.md](docs/exploitation.md)**
    - [Durcissement](docs/exploitation.md#durcissement)
    - [Délais et arrêt](docs/exploitation.md#délais-et-arrêt)
    - [Test de charge](docs/exploitation.md#test-de-charge)
    - [Référence](docs/exploitation.md#référence)
      - [Images](docs/exploitation.md#images)
      - [Trois modes](docs/exploitation.md#trois-modes)
      - [Configuration](docs/exploitation.md#configuration)
      - [Tests et pipeline](docs/exploitation.md#tests-et-pipeline)
      - [Logs](docs/exploitation.md#logs)
    - [Pièges connus](docs/exploitation.md#pièges-connus)
  - **[decisions.md](docs/decisions.md)**
    - [Toutes les décisions, en un tableau](docs/decisions.md#toutes-les-décisions-en-un-tableau)
    - [Envoyer par morceaux](docs/decisions.md#envoyer-par-morceaux)
    - [Compter avec un UPSERT](docs/decisions.md#compter-avec-un-upsert)
    - [Choisir les versions](docs/decisions.md#choisir-les-versions)
    - [Choisir les images](docs/decisions.md#choisir-les-images)

## Endpoints

| Endpoint | Rôle |
|---|---|
| `GET /fizzbuzz` | les nombres de 1 à `limit` : multiples de `int1` → `str1`, de `int2` → `str2`, des deux → `str1str2` |
| `GET /statistics` | la requête la plus demandée, et son nombre d'appels |
| `GET /health` | répond `{"status":"ok"}`, pour les sondes |
| `GET /api/doc` | documentation interactive. Spécification OpenAPI : `/api/doc.json` |

## Lancer

Il faut Docker avec Compose v2 ou plus récent (la commande `docker compose`, sans tiret), et le port 8080 libre. Sinon : `HTTP_PORT=8081 make up`.

```bash
make up
curl 'http://localhost:8080/fizzbuzz?int1=3&int2=5&limit=15&str1=fizz&str2=buzz'
```

```json
["1","2","fizz","4","buzz","fizz","7","8","fizz","buzz","11","fizz","13","14","fizzbuzz"]
```

`make up` fait tout : il construit l'image, démarre nginx, PHP et MariaDB, attend qu'ils répondent, puis crée les tables.
La première fois, compter deux à trois minutes : téléchargement des images et compilation des extensions PHP.

Sans `make` :

```bash
docker compose up -d --build --wait
docker compose run --rm php php bin/console doctrine:migrations:migrate -n
```

## Essayer

```bash
curl http://localhost:8080/statistics
# {"request":{"int1":3,"int2":5,"limit":15,"str1":"fizz","str2":"buzz"},"hits":1}

curl 'http://localhost:8080/fizzbuzz?int1=500&int2=5&limit=100&str1=fizz&str2=buzz'
# 400 {"title":"Validation Failed", ..., "violations":[{"propertyPath":"int1","message":"... less than or equal to limit (100)."}]}
```

Ce qui est accepté :

- `int1`, `int2`, `limit` : entiers, 1 ou plus ;
- `int1` et `int2` : pas plus grands que `limit` ;
- `limit` : pas plus grand que `FIZZBUZZ_MAX_LIMIT`, 100 000 par défaut ;
- `str1`, `str2` : de 1 à 64 caractères.

## Tester

```bash
make lint    # style du code et analyse statique (PHPStan, niveau max)
make test    # PHPUnit, contre une vraie MariaDB
make audit   # recherche de failles dans les trois images (Trivy)
```

`make help` liste les autres commandes. Leur équivalent Docker se lit dans le `Makefile`.

## Ce qui a été mesuré

| Sujet | Résultat | Détail |
|---|---|---|
| Mémoire constante | 15 millions d'éléments servis, le conteneur PHP reste à 45 Mo de mémoire | [architecture](docs/architecture.md) |
| Accès concurrents | 8 000 appels simultanés, 8 000 comptés. Avec un `hits++` classique par l'ORM : 974 comptés, le reste perdu | [décisions](docs/decisions.md) |
| Conteneurs durcis | sans root, disque en lecture seule, 0 faille grave sur les 3 images | [exploitation](docs/exploitation.md) |
| Abus bloqués | un robot ou un client qui boucle est refusé par nginx en 4 ms, sans jamais atteindre PHP | [exploitation](docs/exploitation.md) |
| Pannes prévues | base coupée : `/fizzbuzz` répond encore. Arrêt d'un conteneur : aucune réponse tronquée | [architecture](docs/architecture.md) |

## Limites connues, et la suite

- **Les statistiques sont écrites avant la réponse.** Une base qui ne répond plus ajoute jusqu'à 5 s à chaque `/fizzbuzz`. Suite : écrire après l'envoi de la réponse.
- **La table des statistiques n'est jamais purgée.** Elle grandit avec le nombre de requêtes différentes.
- **Le scan hebdomadaire ne couvre que notre image**, et il alerte sans corriger. Suite : scanner aussi nginx et MariaDB, et Renovate pour les mises à jour.
- **Pas d'identifiant de requête dans les logs.**
- **Limite de débit par adresse IP seulement.** Avec des clients authentifiés : des quotas par client (RateLimiter de Symfony et Redis).

## Contribuer

1. Créer une branche depuis `develop` : `git switch -c feature/ma-modif develop`.
2. Vérifier en local : `make lint` et `make test`. `make fix` corrige le style.
3. Nommer les commits en anglais, au format Conventional Commits : `feat:`, `fix:`, `docs:`, `ci:`, `chore:`.
4. Ouvrir une pull request vers `develop`. Le modèle demande quoi, pourquoi, et comment vérifier.
5. Le pipeline rejoue `lint`, `test`, `build` et `security` sur la pull request. Tout doit être vert avant la fusion.

Tout l'historique suit cette règle : une branche et une pull request par étape. Il se lit avec `git log --graph --oneline`.
