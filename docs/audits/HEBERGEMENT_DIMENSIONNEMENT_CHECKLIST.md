# Checklist : Audience cible et dimensionnement de l'hébergement

## État actuel

Le projet n'est pas encore en production (voir `DEPLOIEMENT_REPRODUCTIBLE_CHECKLIST.md`) : il n'existe donc **aucune donnée de trafic réelle** à ce jour. L'infrastructure actuelle est un unique serveur Docker Compose (`docker-compose.yml`) :

- 1 conteneur Apache/PHP (`webserver/Dockerfile`, PHP 8.3) qui sert à la fois le front (React buildé), le back (API PHP) et les fichiers audio générés (`webserver/public/output`, `webserver/api/storage/uploads`).
- 1 conteneur MySQL.
- Aucune répartition de charge, aucun CDN, aucun cache HTTP dédié.

Point important pour ce projet : les épisodes sont des **fichiers audio** (contenu lourd, statique une fois généré, potentiellement consommé depuis n'importe où géographiquement). C'est le facteur qui pèse le plus sur le dimensionnement, bien plus que le nombre de pages HTML servies.

## Audience cible : à mesurer, pas à deviner

Le trafic réel (visiteurs/jour, pics) ne peut pas être fixé a priori sans données : ça dépend de la stratégie de lancement (bouche-à-oreille, réseaux sociaux, référencement...) que seul le porteur du projet connaît. Un bandeau de consentement cookies a été mis en place (`commit 3c95c1a`) : dès qu'un outil analytics sera branché derrière, le trafic réel deviendra mesurable.

En attendant, ce document raisonne par **paliers de trafic**, pour permettre de choisir la bonne réponse dès que le palier réel est connu, sans avoir à refaire l'analyse à chaque fois.

| Palier | Trafic indicatif | Caractéristique |
|---|---|---|
| Lancement | quelques dizaines à quelques centaines de visites/jour | trafic régulier, pas de pic |
| Croissance | quelques milliers de visites/jour | pics modérés (publication d'un épisode, partage ponctuel) |
| Pic viral | dizaines de milliers de visites en quelques heures | partage massif sur réseaux sociaux, référencement presse |

## Recommandations par palier

### Palier "Lancement" (situation actuelle probable)

- Le serveur actuel (1 conteneur Apache/PHP + MySQL) suffit largement pour la partie dynamique.
- **Mettre un CDN devant les fichiers audio et les assets statiques dès maintenant**, indépendamment du volume de trafic. Contrairement au reste, ce n'est pas une réponse à un problème de charge : c'est un gain immédiat et peu coûteux (latence réduite pour les auditeurs éloignés géographiquement, bande passante du serveur d'origine épargnée, résilience si le serveur redémarre). Aucune raison d'attendre un palier de trafic pour le faire.
- Pas besoin de répartition de charge ni de réplicas à ce stade.

### Palier "Croissance"

- Le CDN devant les fichiers audio devient quasi indispensable (ce sont les fichiers qui consomment le plus de bande passante).
- Ajouter du cache HTTP côté Apache pour les pages qui peuvent l'être.
- Si la partie dynamique (API PHP, génération d'épisodes) sature, renforcer le serveur actuel (plus de CPU/RAM) est la réponse la plus simple avant d'envisager plusieurs machines.

### Palier "Pic viral"

- Le CDN absorbe l'essentiel du pic sur les fichiers audio/statiques (c'est justement sa fonction : il tient une charge que le serveur d'origine ne pourrait pas absorber seul).
- Pour la partie dynamique (API, base de données), passer à plusieurs réplicas de l'application derrière une répartition de charge devient nécessaire si le serveur renforcé ne suffit plus.
- La base de données MySQL, elle, reste un point à surveiller en priorité : c'est souvent elle qui limite la montée en charge avant le serveur web.

## Recommandation concrète pour l'état actuel du projet

Compte tenu de l'absence de données de trafic et du stade pré-production du projet :

1. **Ne rien changer côté serveur applicatif pour l'instant** — l'architecture actuelle (1 serveur) est adaptée à un lancement.
2. **Mettre en place un CDN devant les fichiers audio et statiques** avant ou au moment du lancement : c'est la seule action qui apporte un bénéfice réel sans attendre de connaître le trafic.
3. **Brancher un outil de mesure d'audience** derrière le bandeau de consentement déjà en place, pour obtenir de vrais chiffres de visiteurs/jour et de pics dès le lancement.
4. **Revisiter ce document une fois des chiffres réels disponibles** (ex. après 1 à 3 mois en ligne) pour décider concrètement entre renforcer le serveur, ajouter des réplicas, ou étendre le CDN — voir aussi `ALERTES_ERREURS_CHECKLIST.md` pour la mise en place d'un monitoring qui permettra de détecter le moment où le serveur actuel sature.

## Prérequis pour aller plus loin

- Choisir un fournisseur/outil analytics (à brancher derrière le bandeau de consentement existant) pour mesurer le trafic réel.
- Choisir un fournisseur de CDN (ex. Cloudflare, adapté au budget d'un projet de cette taille) une fois le nom de domaine et l'hébergement de production choisis (voir `DNS_EMAIL_CHECKLIST.md`).
