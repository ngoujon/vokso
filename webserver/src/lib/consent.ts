// Bandeau cookies : implémenté une seule fois dans /assets/consent.js
// (chargé par public/index.html comme par la vitrine), qui expose
// window.VoksoConsent. Absent en local si la vitrine n'est pas servie.

declare global {
  interface Window {
    VoksoConsent?: {
      open: () => void;
      status: () => 'accepted' | 'refused' | null;
    };
  }
}

/** Rouvre le bandeau pour modifier son choix. */
export function reopenConsent(): void {
  window.VoksoConsent?.open();
}
