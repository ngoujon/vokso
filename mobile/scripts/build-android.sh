#!/usr/bin/env bash
# Construit l'app Android et dépose les fichiers dans ../exports/android/
# (dossier non versionné).
#
# Prérequis : JDK 21 et SDK Android (plateforme 36). Par défaut, ceux installés
# par Homebrew (openjdk@21, android-commandlinetools) et ~/Library/Android/sdk.
#
# Sans clé de signature : APK de debug, installable directement sur un
# téléphone (adb install, ou fichier envoyé sur l'appareil).
# Avec clé : APK et AAB (Play Store) de release signés. Variables :
#   VOKSO_KEYSTORE           chemin du fichier .jks (jamais dans le dépôt)
#   VOKSO_KEYSTORE_PASSWORD  mot de passe du keystore
#   VOKSO_KEY_ALIAS          alias de la clé
#   VOKSO_KEY_PASSWORD       mot de passe de la clé (défaut : celui du keystore)
set -euo pipefail

cd "$(dirname "$0")/.."
export JAVA_HOME="${JAVA_HOME:-/opt/homebrew/opt/openjdk@21}"
export ANDROID_HOME="${ANDROID_HOME:-$HOME/Library/Android/sdk}"
OUT="../exports/android"
VERSION=$(sed -n 's/.*versionName "\(.*\)"/\1/p' android/app/build.gradle)

npx cap sync android
mkdir -p "$OUT"

if [[ -n "${VOKSO_KEYSTORE:-}" ]]; then
  SIGNING=(
    -Pandroid.injected.signing.store.file="$VOKSO_KEYSTORE"
    -Pandroid.injected.signing.store.password="$VOKSO_KEYSTORE_PASSWORD"
    -Pandroid.injected.signing.key.alias="$VOKSO_KEY_ALIAS"
    -Pandroid.injected.signing.key.password="${VOKSO_KEY_PASSWORD:-$VOKSO_KEYSTORE_PASSWORD}"
  )
  (cd android && ./gradlew --quiet assembleRelease bundleRelease "${SIGNING[@]}")
  cp android/app/build/outputs/apk/release/app-release.apk "$OUT/vokso-$VERSION.apk"
  cp android/app/build/outputs/bundle/release/app-release.aab "$OUT/vokso-$VERSION.aab"
else
  (cd android && ./gradlew --quiet assembleDebug)
  cp android/app/build/outputs/apk/debug/app-debug.apk "$OUT/vokso-$VERSION-debug.apk"
fi

ls -lh "$OUT"
