const STORAGE_KEY = 'qwai_cookie_consent';
export const CONSENT_EVENT_NAME = 'qwai-consent-change';

export function getConsentStatus() {
  try {
    return localStorage.getItem(STORAGE_KEY);
  } catch {
    return null;
  }
}

export function hasAnalyticsConsent() {
  return getConsentStatus() === 'accepted';
}

export function setConsentStatus(status) {
  try {
    localStorage.setItem(STORAGE_KEY, status);
  } catch {
    // stockage indisponible (navigation privée, quotas) : le bandeau réapparaîtra à chaque visite
  }
  window.dispatchEvent(new CustomEvent(CONSENT_EVENT_NAME, { detail: { status } }));
}

// Point d'entrée à utiliser par tout futur script d'analytics ou de publicité :
// le callback (ex: chargement du script tiers) n'est exécuté que si le visiteur a
// explicitement accepté les cookies, immédiatement ou dès qu'il donne son accord.
export function runWhenConsentGiven(callback) {
  if (hasAnalyticsConsent()) {
    callback();
    return () => {};
  }
  const handler = (event) => {
    if (event.detail.status === 'accepted') {
      callback();
      window.removeEventListener(CONSENT_EVENT_NAME, handler);
    }
  };
  window.addEventListener(CONSENT_EVENT_NAME, handler);
  return () => window.removeEventListener(CONSENT_EVENT_NAME, handler);
}
