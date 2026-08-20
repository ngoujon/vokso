# Checklist de Vérification : Compression et Mise en Cache HTTP

## État de la Configuration

### ✅ Compression (gzip/deflate)

**Configurée globalement dans Apache:**
- ✅ Contenus texte (HTML, CSS, JS, XML)
- ✅ JSON (application/json)
- ✅ Images (SVG, PNG, WebP)
- ✅ Exclusions : fichiers pré-compressés (GIF, JPG)

**Fichier:** `apache-config/qwebty-podcast.conf`
```apache
<IfModule mod_deflate.c>
    AddOutputFilterByType DEFLATE text/html text/plain text/xml text/css
    AddOutputFilterByType DEFLATE text/javascript application/javascript
    AddOutputFilterByType DEFLATE application/json
    AddOutputFilterByType DEFLATE image/svg+xml image/png image/webp
</IfModule>
```

### ✅ En-têtes de Cache HTTP

**Configuration par type de contenu:**

| Type | Cache-Control | Durée | Module |
|------|----------------|-------|--------|
| HTML | must-revalidate | 1 heure | mod_headers/mod_expires |
| CSS/JS | immutable | 1 an | mod_headers/mod_expires |
| Images | immutable | 1 an | mod_headers/mod_expires |
| Fonts | immutable | 1 an | mod_headers/mod_expires |
| JSON | must-revalidate | 1 heure | mod_headers/mod_expires |
| Défaut | public | 24 heures | mod_headers/mod_expires |

**Fichiers configurés:**
1. `apache-config/qwebty-podcast.conf` (configuration principale)
2. `webserver/site/.htaccess` (racine du site)
3. `webserver/build/.htaccess` (application React)

### ✅ ETag (Validation)

- ✅ ETag activé pour tous les fichiers
- Header: `ETag: %{UNIQUE_ID}e`
- Permet aux navigateurs de vérifier la fraîcheur sans re-télécharger

### ⚠️ Brotli (Non implémenté)

**Raison:** mod_brotli n'est pas configuré (optionnel, compression supplémentaire)
- gzip/deflate suffisent pour la plupart des cas
- Brotli peut être ajouté ultérieurement si besoin

## Vérification des En-têtes Réels

### Avant de déployer, tester avec:

```bash
# Vérifier la compression
curl -I -H "Accept-Encoding: gzip, deflate" https://qwaipod.fr/

# Vérifier les en-têtes de cache
curl -I https://qwaipod.fr/index.html
curl -I https://qwaipod.fr/app/index.html
curl -I https://qwaipod.fr/static/image.webp
```

### En-têtes attendus:

**Pour HTML:**
```
Content-Encoding: gzip
Cache-Control: public, max-age=3600, must-revalidate
ETag: <unique-id>
Expires: <1-heure>
```

**Pour CSS/JS:**
```
Content-Encoding: gzip
Cache-Control: public, max-age=31536000, immutable
ETag: <unique-id>
Expires: <1-an>
```

**Pour Images:**
```
Cache-Control: public, max-age=31536000, immutable
ETag: <unique-id>
Expires: <1-an>
```

## Modules Apache Requis

Vérifier que les modules suivants sont activés:
```bash
sudo a2enmod deflate    # Pour compression gzip
sudo a2enmod expires    # Pour expiration de cache
sudo a2enmod headers    # Pour en-têtes personnalisés
sudo a2enmod rewrite    # Pour routage React
```

## Impact sur les Performances

### Avant cette configuration:
- ❌ Aucune compression (fichiers plus lourds)
- ❌ Aucune validation de cache (re-téléchargement à chaque visite)
- ❌ ETag absent (revalidation inefficace)

### Après cette configuration:
- ✅ Réduction de bande passante (~60-80% pour contenus texte)
- ✅ Navigation plus rapide (cache navigateur)
- ✅ Validation efficace des mises à jour (ETag)
- ✅ Économie de ressources serveur

## Notes d'Implémentation

### Configuration Apache (Principal)
- Module `mod_deflate` pour compression
- Modules `mod_expires` et `mod_headers` pour cache-control
- Niveau de compression: 6 (équilibre vitesse/ratio)

### Fichiers .htaccess (Complément)
- Applicables à chaque répertoire
- Permettent overrides locaux si besoin
- Fallback en cas de changement config Apache

### CSS/JS avec hachage
- À la construction React: `npm run build`
- Fichiers générés avec hash au nom (ex: `app.abc123.js`)
- Permet cache immutable sans versioning
