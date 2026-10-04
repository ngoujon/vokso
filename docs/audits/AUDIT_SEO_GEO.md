# Audit SEO et GEO — octobre 2026

GEO (*Generative Engine Optimization*) : être compris, cité et recommandé par
les moteurs de réponse et assistants IA (ChatGPT, Perplexity, Claude, Le Chat,
Google AI Overviews). Ces robots lisent surtout le **HTML brut** : la plupart
n'exécutent pas le JavaScript.

## État avant correctifs

Points déjà solides : HTTPS et domaine canonique unique (`www` → 301), balises
`title`/`description`/`canonical`/Open Graph sur la vitrine, page épisode rendue
côté serveur avec transcription et JSON-LD `PodcastEpisode`, sitemap dynamique,
`llms.txt`, robots ouverts, temps de réponse < 100 ms.

| # | Constat | Gravité | Correctif |
|---|---|---|---|
| 1 | URL d'épisode opaques : `/podcast/gen_6abe49c4bb560-le-mystere-des-vaccins-revele` | Élevée | `/podcast/le-mystere-des-vaccins-revele` ; anciennes URL en **301** |
| 2 | Titres sans ponctuation imposés par le prompt (« Fourmis autoroutes jardin astuces et mystères ») | Élevée | Prompt `titre` : titre naturel et ponctué, 45–65 caractères, mot-clé en tête |
| 3 | 26 catégories pour 35 épisodes (une catégorie par épisode ou presque) | Élevée | Liste fermée de 10 rubriques (prompt `keyword`) et regroupement des épisodes existants |
| 4 | Aucune page thématique lisible sans JavaScript (la discothèque charge tout en JS) | Élevée | Pages `/discotheque/{rubrique}` rendues côté serveur, `CollectionPage` + `ItemList`, dans le sitemap |
| 5 | Meta description d'épisode = 200 premiers caractères coupés en plein mot | Moyenne | Phrases entières, 155 caractères maximum |
| 6 | Pages épisode en `Cache-Control: private` | Moyenne | `public, max-age=3600` + `Last-Modified` |
| 7 | Pas de maillage interne entre épisodes | Moyenne | Bloc « À écouter aussi » (même rubrique) et lien vers la page de la rubrique |
| 8 | Pas de fil d'Ariane | Moyenne | Fil d'Ariane visible + `BreadcrumbList` (épisode, rubrique, discothèque, comment ça marche) |
| 9 | JSON-LD d'épisode minimal | Moyenne | `@graph` : langue, accès gratuit, éditeur, `AudioObject` typé, `wordCount`, rubrique |
| 10 | FAQ de « Comment ça marche ? » sans données structurées | Moyenne | `FAQPage` (6 questions, texte identique à la page) |
| 11 | Discothèque et « Comment ça marche ? » sans cartes Twitter ni JSON-LD | Faible | Cartes `summary_large_image`, `CollectionPage` |
| 12 | Aucun index des épisodes lisible par une IA en un seul fichier | Moyenne (GEO) | `/llms-full.txt` dynamique : chaque épisode, par rubrique, avec résumé et URL |
| 13 | Robots d'IA non mentionnés explicitement | Faible (GEO) | `robots.txt` : GPTBot, ClaudeBot, PerplexityBot, MistralAI-User… autorisés |
| 14 | Balises audio Open Graph absentes | Faible | `og:audio`, `article:published_time`, `article:section` |

## Déploiement des correctifs

1. **Avant** de déployer le code : appliquer `SQL/20261004000000.sql` (colonne
   `slug`, prompts `titre` et `keyword`, regroupement des catégories). L'ancien
   code fonctionne avec la nouvelle colonne.
2. Déployer (push sur `production`).
3. Remplir les slugs : `sudo -u www-data php artisan vokso:episode-slugs`
   (dans `webserver/api`). Relançable sans effet de bord.

## Recommandations restantes

- **Flux RSS de podcast** (`/podcast.xml`, balises iTunes) : indispensable pour
  Apple Podcasts, Spotify et les annuaires, et très bien compris des IA.
- **Durée des épisodes** (`duration` ISO 8601 dans le JSON-LD, lecteur) : à
  mesurer à la génération (ffprobe) et à stocker.
- **Sélection du jour et discothèque** : rendre aussi côté serveur la liste
  initiale (aujourd'hui chargée en JavaScript).
- **E-E-A-T** : page « À propos » (qui édite Vokso, méthode de vérification
  des faits), `sameAs` vers les réseaux sociaux dans le JSON-LD `Organization`.
- **Search Console / Bing Webmaster Tools** : soumettre le sitemap et suivre
  l'indexation des nouvelles URL après la redirection 301.
