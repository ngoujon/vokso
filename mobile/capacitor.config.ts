import type { CapacitorConfig } from '@capacitor/cli';

/**
 * Applications natives Vokso (Android, iOS : iPhone, iPad et Mac Apple
 * Silicon) : une coquille Capacitor qui charge le site en production, pour que
 * chaque déploiement sur `production` soit aussitôt visible dans les apps,
 * sans republier sur les stores. www/ ne sert que de page hors ligne.
 */
const config: CapacitorConfig = {
  appId: 'fr.vokso.app',
  appName: 'Vokso',
  webDir: 'www',
  server: {
    url: 'https://vokso.fr',
    // Page embarquée affichée si vokso.fr est injoignable (hors ligne).
    errorPath: 'offline.html',
  },
  backgroundColor: '#f3eee4',
  android: {
    // Pas de contenu http:// mêlé à la page https.
    allowMixedContent: false,
  },
  ios: {
    contentInset: 'never',
    // Glisser depuis le bord pour revenir en arrière, comme dans Safari.
    allowsBackForwardNavigationGestures: true,
  },
  plugins: {
    SplashScreen: {
      launchShowDuration: 800,
      backgroundColor: '#f5c400',
      showSpinner: false,
    },
  },
};

export default config;
