# Architecture

[← Retour au README](../README.md)

**En bref**

- Un dossier par rôle, comme dans tout projet Symfony.
- La liste est envoyée par morceaux : la mémoire du serveur ne dépend pas de `limit`.
- Tout ce qui peut échouer est fait avant le premier octet envoyé.
- Une erreur est toujours du JSON au format Problem Details, jamais du HTML.

## Organisation du code

```
src/
├── Controller/     un contrôleur par endpoint : FizzBuzz, Statistics, Health
├── Dto/            FizzBuzzQuery : les cinq paramètres et leurs règles
├── Service/        FizzBuzzGenerator : le calcul, en PHP pur
├── Validator/      LimitCap : le plafond de limit, lu dans l'environnement
├── Response/       StreamedJsonArrayResponse : envoie une liste en JSON, par morceaux
├── Entity/         RequestStat : une ligne par requête différente
├── Repository/     RequestStatRepository : compte les appels, trouve le plus fréquent
├── Exception/      StatsUnavailableException
├── EventListener/  ProblemDetailsListener : met les erreurs au bon format
└── Serializer/     ConciseProblemNormalizer : une erreur de champ = un champ et un message
```

`tests/` a la même forme. Le script de charge est dans `tests/load`.

## Trajet d'un appel à `/fizzbuzz`

1. **nginx** refuse les clients trop rapides (429), compresse, et transmet la réponse à mesure qu'elle arrive.
2. **Symfony** lit l'URL, remplit un `FizzBuzzQuery` et le valide. S'il est invalide : 400, le contrôleur n'est pas appelé.
3. **Le contrôleur** compte l'appel dans les statistiques. Si la base ne répond pas, il l'écrit dans les logs et continue.
4. **Le générateur** produit les valeurs une par une. La réponse les envoie par paquets de 8192.

## Le calcul

```php
for ($i = 1; $i <= $query->limit; ++$i) {
    $word = '';
    if (0 === $i % $query->int1) { $word .= $query->str1; }
    if (0 === $i % $query->int2) { $word .= $query->str2; }
    yield '' === $word ? (string) $i : $word;
}
```

- Deux tests indépendants : un multiple des deux reçoit `str1` puis `str2`, sans troisième condition.
- Aucun tri des entiers : avec `int1=5, int2=3, str1=buzz, str2=fizz`, 15 donne `buzzfizz`.
- Le multiple commun est le plus petit, pas le produit : avec 4 et 6, c'est 12 qui est remplacé.

## Pourquoi envoyer par morceaux

Pour que la mémoire ne dépende pas de `limit`. Mesuré dans le conteneur de production :

| `limit` | Taille de la réponse | Durée |
|---|---|---|
| 100 000 | 774 Ko | 45 ms |
| 1 000 000 | 8,3 Mo | 214 ms |
| 15 000 000 | 135 Mo | 3,06 s |

- Le conteneur PHP reste à **45 Mo de mémoire** pendant qu'il envoie une réponse de 135 Mo. Le premier octet part en moins de 70 ms dans les trois cas.
- Sans envoi par morceaux, 10 millions d'éléments demandent 528 Mo. PHP est limité à 128 Mo ici (`memory_limit`).
- Le plafond de `limit` devient un réglage, `FIZZBUZZ_MAX_LIMIT`, pas une contrainte technique.

Deux conséquences :

- **Une fois l'envoi commencé, le code HTTP ne peut plus changer.** La validation et l'écriture des statistiques passent donc avant le premier octet.
- **Le client doit quand même tout recevoir.** Un parseur JSON classique attend le `]` final avant de rendre le tableau. L'envoi par morceaux économise la mémoire du serveur, pas celle du client.

## Validation

| Règle | Contrainte Symfony |
|---|---|
| `int1`, `int2`, `limit` : entiers, 1 ou plus | `Positive`, types stricts. `3.5`, `abc`, `+3` refusés. `03` est lu comme 3 |
| `int1` et `int2` pas plus grands que `limit` | `LessThanOrEqual(propertyPath: 'limit')` |
| `limit` pas plus grand que le plafond | `LimitCap` |
| `str1`, `str2` : 1 à 64 caractères, en UTF-8 valide | `NotBlank`, `Length(max: 64)` |

`int1 == int2` est accepté : leurs multiples donnent `str1str2`. Si `int1` dépasse `limit`, rien ne serait remplacé : c'est refusé comme une erreur de saisie.

## Erreurs

Format Problem Details (RFC 9457, ex 7807) : `type`, `title`, `status`, `detail`.

| Cas | Code | Qui répond |
|---|---|---|
| Paramètre manquant, mal typé, hors bornes | 400, avec la liste `violations` | Symfony |
| Route inconnue, mauvaise méthode | 404, 405 | Symfony |
| Client trop rapide | 429 | nginx |
| `/statistics`, base indisponible | 503 | Symfony |
| `/fizzbuzz`, base indisponible | **200** | le contrôleur écrit l'erreur dans les logs et sert la liste |
| PHP arrêté | 503 | nginx |

- **Une seule exception maison.** `RequestStatRepository` transforme toute erreur SQL en `StatsUnavailableException`. Seul le contrôleur l'attrape pour continuer.
- **Des statistiques vides ne sont pas une erreur** : `{"request": null, "hits": 0}`.
- **Le calcul ne valide rien et ne lance rien.** Il reçoit des paramètres déjà validés.

## Statistiques

- Deux requêtes sont les mêmes si leurs cinq paramètres sont les mêmes. L'ordre dans l'URL ne compte pas.
- `(3, 5, fizz, buzz)` et `(5, 3, buzz, fizz)` donnent la même liste, mais sont deux requêtes.
- Les requêtes refusées ne sont pas comptées.

Chaque appel exécute une seule instruction SQL :

```sql
INSERT INTO request_stats (request_hash, `int1`, `int2`, limit_value, str1, str2, hits)
VALUES (:hash, :int1, :int2, :limit, :str1, :str2, 1)
ON DUPLICATE KEY UPDATE hits = hits + 1
```

- `request_hash` est une empreinte SHA-256 des cinq valeurs. Un index unique dessus permet à MariaDB de reconnaître une requête déjà vue.
- Le `+ 1` est calculé par la base : deux appels au même instant comptent pour deux.
- À égalité, `/statistics` renvoie la requête vue en premier.
