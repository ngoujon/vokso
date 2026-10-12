// Mesure d'audience SEO Monitor : script chargé depuis public/index.html, qui
// expose window.seoMonitor (absent en local, ou si le script est bloqué).
// Jamais de donnée personnelle dans les propriétés (ni e-mail, ni nom).

type AudienceProps = Record<string, string | number>;

declare global {
  interface Window {
    seoMonitor?: {
      track: (name: string, props?: AudienceProps) => void;
      optOut: () => void;
    };
  }
}

export function trackConversion(name: string, props?: AudienceProps): void {
  window.seoMonitor?.track(name, props);
}

/** Opposition à la mesure d'audience ; false si le script n'est pas chargé. */
export function optOutOfAudience(): boolean {
  if (!window.seoMonitor) return false;
  window.seoMonitor.optOut();
  return true;
}
