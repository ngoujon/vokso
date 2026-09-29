const STORAGE_KEY = 'vokso_cookie_consent';
const LEGACY_STORAGE_KEY = 'qwai_cookie_consent';
export const CONSENT_EVENT_NAME = 'vokso-consent-change';

export type ConsentStatus = 'accepted' | 'refused';

export function getConsentStatus(): ConsentStatus | null {
  try {
    const value = localStorage.getItem(STORAGE_KEY) ?? localStorage.getItem(LEGACY_STORAGE_KEY);
    return value === 'accepted' || value === 'refused' ? value : null;
  } catch {
    return null;
  }
}

export function hasAnalyticsConsent(): boolean {
  return getConsentStatus() === 'accepted';
}

export function setConsentStatus(status: ConsentStatus): void {
  try {
    localStorage.setItem(STORAGE_KEY, status);
  } catch {
    // Stockage indisponible (navigation privée, quotas) : le bandeau réapparaîtra.
  }
  window.dispatchEvent(new CustomEvent(CONSENT_EVENT_NAME, { detail: { status } }));
}

// Point d'entrée pour tout futur script d'analytics ou de publicité : le
// callback n'est exécuté qu'après un accord explicite du visiteur.
export function runWhenConsentGiven(callback: () => void): () => void {
  if (hasAnalyticsConsent()) {
    callback();
    return () => {};
  }
  const handler = (event: Event) => {
    if ((event as CustomEvent<{ status: ConsentStatus }>).detail.status === 'accepted') {
      callback();
      window.removeEventListener(CONSENT_EVENT_NAME, handler);
    }
  };
  window.addEventListener(CONSENT_EVENT_NAME, handler);
  return () => window.removeEventListener(CONSENT_EVENT_NAME, handler);
}
