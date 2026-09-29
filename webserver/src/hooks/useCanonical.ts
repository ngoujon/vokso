import { useEffect } from 'react';
import { useLocation } from 'react-router-dom';

// Domaine canonique fixe (vokso.fr, jamais www.vokso.fr) : la redirection
// www -> non-www est faite côté Apache (site/.htaccess), on la fige aussi ici.
const CANONICAL_ORIGIN = 'https://vokso.fr';

export default function useCanonical(canonicalUrl: string | null = null): void {
  const location = useLocation();

  useEffect(() => {
    // location.pathname est relatif au basename ("/app") : window.location
    // donne le chemin réellement servi, /app inclus.
    const url = canonicalUrl || `${CANONICAL_ORIGIN}${window.location.pathname}`;

    let canonicalTag = document.querySelector<HTMLLinkElement>('link[rel="canonical"]');
    if (!canonicalTag) {
      canonicalTag = document.createElement('link');
      canonicalTag.rel = 'canonical';
      document.head.appendChild(canonicalTag);
    }
    canonicalTag.href = url;
  }, [location.pathname, canonicalUrl]);
}
