# Vokso mobile (Android, iOS, iPad, Mac)

Coquilles natives [Capacitor](https://capacitorjs.com) qui chargent
https://vokso.fr : chaque déploiement sur `production` est aussitôt visible dans
les apps, sans republier sur les stores. `www/offline.html` est la seule page
embarquée, affichée sans connexion.

- `android/` : projet Android Studio (`fr.vokso.app`).
- `ios/` : projet Xcode (`fr.vokso.app`), une seule app pour iPhone, iPad et
  Mac Apple Silicon (option « Designed for iPad », publiée sur le Mac App Store
  avec la même fiche).
- Les fichiers construits vont dans `../exports/` (non versionné).

## Prérequis

```sh
cd mobile && npm install
```

- Android : JDK 21 et SDK Android (plateforme 36), par exemple
  `brew install openjdk@21 && brew install --cask android-commandlinetools`.
- iOS / iPad / Mac : Xcode 26 complet (Mac App Store) et un compte Apple
  Developer pour signer et publier.

## Commandes

| Commande | Effet |
|---|---|
| `npm run android:apk` | APK de debug dans `exports/android/` ; APK + AAB de release signés si `VOKSO_KEYSTORE` est défini (voir `scripts/build-android.sh`) |
| `npm run ios:open` | Ouvre le projet dans Xcode (Product → Archive pour l'App Store, iPhone + iPad + Mac) |
| `npm run android:open` | Ouvre le projet dans Android Studio |
| `npm run sync` | Recopie `capacitor.config.ts` et `www/` dans les projets natifs |
| `npm run assets` | Régénère icônes et écrans de lancement depuis la marque (`scripts/render-assets.mjs`) |

## Version

Avant chaque publication, augmenter `versionCode` / `versionName` dans
`android/app/build.gradle` et `CURRENT_PROJECT_VERSION` / `MARKETING_VERSION`
dans le projet Xcode.
