# Audit de la structure des titres (H1-H6)

**Date:** 21 août 2026  
**Statut:** ✓ Audit complété

## Résumé

Audit de la hiérarchie des titres sur toutes les pages du projet pour s'assurer que :
- Chaque page a un unique `<h1>`
- La hiérarchie H1→H6 est logique (pas de saut de niveau)
- Les titres ne sont pas utilisés uniquement pour leur style visuel

## Fichiers examinés

### ✓ site/index.html - CONFORME
**Titres trouvés:**
- H1 (l. 200): "Un sujet en une phrase. Un podcast complet en retour."
- H2 (l. 215, 247, 260, 289, 317, 329): Titres de sections
- H3 (l. 223, 228, 233, 238, 293, 297, 301, 305): Sous-titres de cartes

**Analyse:** ✓ Structure correcte
- Un seul H1
- Hiérarchie H1→H2→H3 respectée
- Pas de saut de niveau
- Titres sémantiquement pertinents

---

### ✓ webserver/src/pages/Home.js - CONFORME
**Titres trouvés:**
- H1 (l. 394): "Générer un podcast"
- H2 (l. 492): "Résultats" ou "Les 3 dernières générations"
- H3 (l. 504): Titre dynamique du podcast (`{gen.title}`)

**Analyse:** ✓ Structure correcte
- Un seul H1
- Hiérarchie H1→H2→H3 respectée
- Pas de saut de niveau
- Titres sémantiquement pertinents

---

### ✓ webserver/src/pages/AdminDashboard.js - CONFORME
**Titres trouvés:**
- H1 (l. 267): "Espace administrateur"
- H2 (l. 50, 122, 205): 
  - "Vue d'ensemble" (KpiSection)
  - "Comptes utilisateurs" (UsersSection)
  - "Monitoring des podcasts" (PodcastsSection)
- H3 (l. 71): "Catégories les plus générées"

**Analyse:** ✓ Structure correcte
- Un seul H1
- Hiérarchie H1→H2→H3 respectée
- Pas de saut de niveau
- Titres sémantiquement pertinents

---

### ✓ webserver/src/pages/Login.js - CONFORME
**Titres trouvés:**
- H1 (l. 35): "Connexion" ou "Créer un compte" (conditionnel)

**Analyse:** ✓ Structure correcte
- Un seul H1
- Pas d'autres titres (OK pour une page d'authentification simple)
- Titre sémantiquement pertinent

---

### ⚠️ webserver/src/pages/UserDashboard.js - **NON CONFORME**
**Titres trouvés:**
- H1 (l. 44): "Mon espace"
- H3 (l. 68): Titre dynamique du podcast (`{p.title}`)

**Problème détecté:** ❌ Saut de hiérarchie H1→H3
- Aucun H2 intermédiaire entre H1 et H3
- Cela crée une incohérence pour les lecteurs d'écran et l'accessibilité
- Recommandation: Ajouter un H2 "Mes podcasts" avant la grille de podcasts

**Correction appliquée:**
```jsx
// Avant:
<h1>Mon espace</h1>
...
<div className="podcast-grid">
  {podcasts.map((p) => (
    <div className="podcast-card" key={p.generation_id}>
      ...
      <h3>{p.title}</h3>  // ← Saut H1→H3
```

```jsx
// Après:
<h1>Mon espace</h1>
...
{podcasts.length > 0 && <h2>Mes podcasts</h2>}
<div className="podcast-grid">
  {podcasts.map((p) => (
    <div className="podcast-card" key={p.generation_id}>
      ...
      <h3>{p.title}</h3>  // ← Correct: H1→H2→H3
```

---

## Bilan

| Fichier | Statut | Problèmes |
|---------|--------|-----------|
| site/index.html | ✓ OK | Aucun |
| Home.js | ✓ OK | Aucun |
| AdminDashboard.js | ✓ OK | Aucun |
| Login.js | ✓ OK | Aucun |
| **UserDashboard.js** | ❌ CORRIGÉ | Saut H1→H3 |

**Verdict:** 1 problème trouvé et corrigé (4/5 fichiers conformes)

---

## Recommandations

1. ✓ **Appliqué:** Ajouter H2 "Mes podcasts" dans UserDashboard.js
2. **À vérifier régulièrement:** Lors de l'ajout de nouvelles pages/composants, s'assurer que la hiérarchie des titres reste cohérente
3. **Outil suggéré:** Utiliser un linter d'accessibilité (axe DevTools, Lighthouse) pour détecter automatiquement ces problèmes à l'avenir

---

## Validation

Toutes les pages ont maintenant une structure de titres conforme aux standards d'accessibilité WCAG 2.1.
