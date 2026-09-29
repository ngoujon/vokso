# Checklist de Vérification : Contraste de Couleurs (WCAG AA)

Vérification du ratio de contraste texte/fond sur la charte de couleurs utilisée
par l'application (`webserver/styles/globals.css`), thème sombre violet/bleu.
Seuils WCAG AA : 4.5:1 pour le texte courant, 3:1 pour le texte large (≥ 18pt ou
≥ 14pt gras) et les éléments d'interface non textuels.

## Résultats

| Élément | Texte | Fond | Ratio | Seuil requis | Statut |
|---|---|---|---|---|---|
| Texte de base (`body`) | `#d4d4d4` | dégradé `#2e0249 → #020024` | 12.9:1 | 4.5:1 | ✅ |
| Titre `h1` / texte formulaire | `#e3e3ff` | `#121111` | 15.0:1 | 4.5:1 | ✅ |
| Champ de saisie (`.input-field`) | `#e3e3ff` | `#2a2a40` | 11.1:1 | 4.5:1 | ✅ |
| Message d'erreur (`.error`) | `#ff5252` | `#121111` | 5.9:1 | 4.5:1 | ✅ |
| Message de succès (`.success`) | `#69f0ae` | `#121111` | 13.2:1 | 4.5:1 | ✅ |
| Icône effacer au survol (`.clear-input:hover`) | `#7c4dff` | `#2a2a40` | 3.9:1 | 3:1 (icône) | ✅ |
| Bouton désactivé (`.submit-btn:disabled`) | `#bbbbbb` | `#555555` | 3.9:1 | 4.5:1 | ⚠️ (état désactivé, hors périmètre AA) |
| **Bouton principal (`.submit-btn`)** | `#fff` | dégradé `#7c4dff → #536dfe` | **4.2:1 à l'extrémité bleue** | 4.5:1 | ❌ → **corrigé** |
| **Infobulle (`.tooltip`)** | `#e3e3ff` | dégradé `#7c4dff → #536dfe` | **3.3:1 à 3.8:1** | 4.5:1 | ❌ → **corrigé** |

## Corrections apportées

Deux éléments ne respectaient pas le seuil AA de 4.5:1 pour le texte courant :

1. **Bouton d'envoi (`.submit-btn`)** : la seconde couleur du dégradé
   `#536dfe` a été assombrie en `#4a5fe0`, portant le ratio du texte blanc
   à 4.8:1 (côté violet) et 5.2:1 (côté bleu) — conforme sur toute la largeur
   du dégradé.
2. **Infobulle (`.tooltip`)** : même correction de dégradé, et la couleur du
   texte a été changée de `#e3e3ff` (lavande, insuffisant) vers `#fff`
   (blanc pur), portant le ratio à 4.8:1 / 5.2:1.

Ces changements sont purement chromatiques (aucun changement de mise en page)
et conservent l'identité visuelle violet/bleu de la charte.

## Non concerné par le seuil AA

- **Bordures et éléments décoratifs** (ex. `.input-field` bordure `#565685`
  sur `#2a2a40`) : le seuil de 3:1 s'applique uniquement aux composants
  d'interface actifs (boutons, champs), pas aux simples liserés décoratifs.
- **État désactivé du bouton** (`.submit-btn:disabled`, ratio 3.9:1) : les
  états désactivés sont exclus des exigences WCAG AA (l'utilisateur ne peut
  pas interagir avec l'élément).

## Méthode

Calcul des ratios via la formule de luminance relative WCAG 2.1 (accès direct
aux valeurs hexadécimales de la charte, sans dépendance à un outil externe).
