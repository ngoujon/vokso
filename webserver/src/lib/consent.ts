// Choix du visiteur dans le bandeau cookies, conservé dans le navigateur.
// L'ancienne clé (nom de projet précédent) reste lue pour ne pas redemander
// leur choix aux visiteurs qui l'ont déjà donné.
const STORAGE_KEY = 'vokso_cookie_consent';
const LEGACY_STORAGE_KEY = 'qwai_cookie_consent';

export type ConsentStatus = 'accepted' | 'refused';

export function getConsentStatus(): ConsentStatus | null {
  try {
    const value = localStorage.getItem(STORAGE_KEY) ?? localStorage.getItem(LEGACY_STORAGE_KEY);
    return value === 'accepted' || value === 'refused' ? value : null;
  } catch {
    return null;
  }
}

export function setConsentStatus(status: ConsentStatus): void {
  try {
    localStorage.setItem(STORAGE_KEY, status);
  } catch {
    // Stockage indisponible (navigation privée, quotas) : le bandeau réapparaîtra.
  }
}
