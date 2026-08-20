# Checklist de Vérification : Mode sombre disponible

Vérification de la présence d'un mode sombre (bascule manuelle ou respect
automatique de `prefers-color-scheme`) sur l'application réelle
(`webserver/src`, servie via `webserver/public/index.html`).

## Résultat

**❌ Critère NON respecté.**

- Aucune règle `prefers-color-scheme` ni de bascule manuelle (toggle, contexte
  de thème, classe `dark`/`.theme-dark`, etc.) n'est présente dans les feuilles
  de style réellement utilisées par l'application :
  `webserver/src/styles/App.css`, `globals.css`, `Dashboard.css`, `index.css`.
- Le thème actuel de l'application (fond sombre violet/bleu, cf.
  `CONTRASTE_COULEURS_CHECKLIST.md`) est un thème fixe codé en dur, pas un
  mode sombre activable/désactivable ni sensible aux préférences système.
- Un fichier `assets/css/style.css`, à la racine du dépôt, contient bien un
  bloc `@media (prefers-color-scheme: dark)` (lignes 278-303), mais ce fichier
  n'est référencé nulle part dans le code de l'application (aucune balise
  `<link>`, aucun import) : il s'agit d'un fichier orphelin/inutilisé, pas
  d'une fonctionnalité active du site.

## Recommandation

Pour satisfaire ce critère, il faudrait soit :
1. ajouter un bloc `@media (prefers-color-scheme: dark)` dans les feuilles de
   style effectivement chargées (`webserver/src/styles/`), soit
2. implémenter une bascule manuelle (toggle clair/sombre) persistée côté
   utilisateur.

Aucune modification de code n'a été effectuée dans le cadre de cette
vérification : il s'agit d'un audit de conformité, pas d'une implémentation.
