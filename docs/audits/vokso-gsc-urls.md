# URL en erreur dans Google Search Console (vokso.fr)

Relevé du 17/11/2026, propriété de domaine `sc-domain:vokso.fr`, Indexation > Pages
(dernière mise à jour GSC : 13/11/2026).

| Motif | Pages | Traitement |
|---|---|---|
| Introuvable (404) | 7 | 4 redirections 301, 3 laissées en 404 |
| Bloquée en raison d'une interdiction d'accès (403) | 1 | 403 gardé + `X-Robots-Tag: noindex` |
| Exclue par la balise « noindex » | 1 | noindex volontaire, gardé |
| Bloquée par le fichier robots.txt | 2 | Disallow retiré, `X-Robots-Tag: noindex, nofollow` |
| Indexée malgré le blocage par le fichier robots.txt | 0 | plus aucune page listée |

## Introuvable (404)

| URL | Dernière exploration | Traitement |
|---|---|---|
| https://vokso.fr/podcast/gen_6ab2918d862ad-jupiter-la-geante-mysterieuse | 14/11/2026 | 301 vers /podcast/jupiter-la-geante-qui-fascine-le-systeme-solaire |
| https://vokso.fr/podcast/ | 06/11/2026 | 301 vers /discotheque (catalogue des épisodes) |
| https://vokso.fr/podcast/gen_6ab2931c9df2c-decouvrez-les-secrets-de-saturne | 30/10/2026 | 301 vers /podcast/saturne-et-ses-anneaux-un-mystere-celeste |
| https://vokso.fr/podcast/independance-de-l-ouganda-le-9-octobre-1962-et-la-construction-d-une- | 26/10/2026 | 301 vers le slug complet (…-construction-d-une-nation) |
| https://vokso.fr/audios/ | 18/10/2026 | 404 : ancien répertoire, aucun contenu équivalent |
| https://vokso.fr/images/ | 17/10/2026 | 404 : ancien répertoire, aucun contenu équivalent |
| https://vokso.fr/site/ | 16/10/2026 | 404 : chemin interne du serveur, jamais une page |

## Bloquée en raison d'une interdiction d'accès (403)

| URL | Dernière exploration | Traitement |
|---|---|---|
| https://vokso.fr/static | 12/10/2026 | 403 gardé (répertoire des fichiers générés, listing interdit) + `X-Robots-Tag: noindex` ; /static ne redirige plus vers http:// |

## Exclue par la balise « noindex »

| URL | Dernière exploration | Traitement |
|---|---|---|
| https://vokso.fr/discotheque/langues | 09/10/2026 | noindex volontaire : catégorie à 1 épisode, sous le seuil `CategoryIndex::MIN_EPISODES_TO_INDEX` (2), absente du sitemap |

## Bloquée par le fichier robots.txt

| URL | Dernière exploration | Traitement |
|---|---|---|
| https://vokso.fr/admin | 22/10/2026 | Disallow retiré, `X-Robots-Tag: noindex, nofollow` |
| https://vokso.fr/app/login | 12/10/2026 | Disallow retiré, `X-Robots-Tag: noindex, nofollow` |

## Indexée malgré le blocage par le fichier robots.txt

Aucune URL listée au 17/11/2026 (0 page).

## Autres motifs (informatifs, non traités)

Page avec redirection (9), Autre page avec balise canonique correcte (6),
Explorée actuellement non indexée (6), Détectée actuellement non indexée (4).
