# Décisions

[← Retour au README](../README.md)

FizzBuzz est trivial. L'effort porte sur ce que l'énoncé appelle « prêt pour la production ».
Chaque choix cite ce qui a été écarté, et le chiffre qui a tranché.

## Toutes les décisions, en un tableau

| Décision | Écarté | Chiffre ou raison |
|---|---|---|
| `GET` | `POST` | une lecture, rejouable sans effet, paramètres dans l'URL |
| Réponse toujours envoyée par morceaux | tableau complet puis `json_encode` | 0,5 Mo au lieu de 528 Mo pour 10 millions |
| Plafond de `limit` réglable | pagination | 100 000 par défaut, testé jusqu'à 15 millions sans changer le code |
| `int1`, `int2` pas plus grands que `limit` | tout accepter | sinon rien n'est remplacé. Plus strict que l'énoncé, assumé |
| Compteur par UPSERT, une seule instruction SQL | l'ORM : lire, `+1`, écrire | 8 000 comptés sur 8 000, contre 974 |
| Empreinte du JSON des cinq valeurs | coller les valeurs bout à bout | un séparateur peut être tapé par l'utilisateur : `str1="a\|b", str2="c"` et `str1="a", str2="b\|c"` donneraient la même empreinte |
| `id` en `BIGINT` | `INT` | l'UPSERT réserve un `id` à chaque appel, même sans nouvelle ligne. En `INT`, le compteur serait épuisé après 4,29 milliards d'appels |
| Limite de débit dans nginx | composant RateLimiter de Symfony | 429 en 4 ms, PHP n'est pas réveillé |
| Erreurs courtes : champ et message | format complet de Symfony | ce qu'il faut pour corriger sa requête, rien de plus |
| Symfony 7.4 LTS, PHP 8.4, MariaDB 11.8 LTS | Symfony 8.1, PHP 8.5 | des versions maintenues plusieurs années : sécurité jusqu'en 2029, 2028 et 2028 |
| Images officielles sur Alpine | Debian, FrankenPHP, MySQL | 0 faille grave, 91 Mo contre 506 |
| Git flow | tout sur une branche | une branche et une pull request par étape, pipeline vert avant fusion |

Les quatre sections suivantes donnent le détail là où il y a des mesures.

## Envoyer par morceaux

Six méthodes ont été comparées, avec une sortie identique à l'octet près. Les trois qui comptent :

| Méthode | 1 million | 10 millions | Mémoire PHP à 10 millions |
|---|---|---|---|
| Tableau complet, puis `json_encode` | 348 ms | 3,9 s | 528 Mo |
| **Générateur, `json_encode` par paquets de 8192** | 342 ms | 3,6 s | 0,5 Mo |
| Motif précalculé (la suite se répète), rempli par `vsprintf` | 80 ms | 0,8 s | 0,5 Mo |

*Mesuré en PHP 8.2 sans JIT, en ligne de commande. Ce tableau compare les méthodes entre elles. Les temps réels, en production avec JIT, sont dans [architecture](architecture.md).*

- Le temps est le même avec ou sans morceaux. Seule la mémoire change, et la méthode retenue coûte dix lignes.
- Le motif précalculé est 4 fois plus rapide, mais il mélange le calcul et le format JSON. À ce débit, c'est le réseau qui limite.
- `StreamedJsonResponse`, fourni par Symfony, encode élément par élément : 2,3 fois plus lent.

## Compter avec un UPSERT

Un UPSERT insère la ligne, ou la met à jour si elle existe. Test : 16 processus incrémentent la même ligne 500 fois chacun.

| Méthode | Comptés sur 8 000 | Durée |
|---|---|---|
| ORM : `find()`, `hits++`, `flush()` | 974 | 1,2 s |
| Même chose avec un verrou `SELECT ... FOR UPDATE` | 8 000 | 7,0 s |
| `UPDATE hits = hits + 1`, puis `INSERT` si besoin | 8 000 | 1,0 s |
| **`INSERT ... ON DUPLICATE KEY UPDATE`** | 8 000 | 1,0 s |

- L'ORM écrit « la valeur lue, plus un ». Deux appels au même instant lisent 10 et écrivent 11 tous les deux.
- La requête la plus fréquente, celle que `/statistics` doit renvoyer, est donc la plus mal comptée.
- Cet UPSERT est propre à MySQL et MariaDB. Il est isolé dans `RequestStatRepository` et testé contre une vraie MariaDB.

## Choisir les versions

| Composant | Retenu | Écarté | Corrections de bugs jusqu'à | Correctifs de sécurité jusqu'à |
|---|---|---|---|---|
| Symfony | **7.4 LTS** | 8.1 | novembre 2028 | novembre 2029 |
| PHP | **8.4** | 8.5 | décembre 2026 | décembre 2028 |
| MariaDB | **11.8 LTS** | | juin 2028 | juin 2028 |

- **Symfony 7.4 est la version LTS**, à support long. Symfony 8.1 n'est maintenue que 8 mois, jusqu'en janvier 2027 : il faudrait migrer tous les six mois.
- **PHP 8.4** est supportée par tous les outils du projet : PHPStan, PHP-CS-Fixer, les extensions. PHP 8.5 est plus récente. Y passer revient à changer le tag de l'image.
- Dates vérifiées le 19 septembre 2026 sur [symfony.com/releases](https://symfony.com/releases), [php.net/supported-versions](https://www.php.net/supported-versions.php) et [endoflife.date/mariadb](https://endoflife.date/mariadb).

## Choisir les images

| Candidate | Taille | Failles graves (HIGH et CRITICAL) |
|---|---|---|
| **`php:8.4-fpm-alpine`** | 91 Mo | 0 |
| `php:8.4-fpm` sur Debian | 506 Mo | 174 |
| `dunglas/frankenphp` | 190 Mo | 7 |
| **`nginx:alpine-slim`** | 18 Mo | 0 |
| **`mariadb:11.8`** | 314 Mo | 0 dans le système. 22 dans `gosu`, un programme jamais exécuté sans root |
| `mysql:8.4` | 775 Mo | 46 |

Alpine est à la fois la plus petite et la plus propre. Les images durcies de Docker sont payantes, celles de Bitnami ne publient plus de versions figées.
En production, PHP 8.4 produit 4,9 millions d'éléments par seconde.

Les limites connues et la suite sont dans le [README](../README.md).
