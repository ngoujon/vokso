import { useEffect } from 'react';
import { useLocation } from 'react-router-dom';

// Domaine canonique fixe (vokso.fr, jamais www.vokso.fr) : la redirection
// www -> non-www est maintenant faite côté Apache (voir apache-config/vokso.conf),
// mais on la fige aussi ici en filet de sécurité pour ne jamais générer de
// canonical vers www.
const CANONICAL_ORIGIN = 'https://vokso.fr';

export default function useCanonical(canonicalUrl = null) {
  const location = useLocation();

  useEffect(() => {
    // location.pathname vient de react-router et est relatif au basename
    // ("/app") : pour /app/tarifs il vaut "/tarifs", pas "/app/tarifs". Comme
    // l'app est servie sous /app/, une canonical basée dessus pointait vers
    // une URL sans /app qui n'existe pas (404) — voir AUDIT_CONTENU_HTML.md.
    // window.location.pathname donne le chemin réellement servi, /app inclus.
    const url = canonicalUrl || `${CANONICAL_ORIGIN}${window.location.pathname}`;

    // Vérifier si une balise canonical existe déjà
    let canonicalTag = document.querySelector('link[rel="canonical"]');

    if (!canonicalTag) {
      canonicalTag = document.createElement('link');
      canonicalTag.rel = 'canonical';
      document.head.appendChild(canonicalTag);
    }

    canonicalTag.href = url;
  }, [location.pathname, canonicalUrl]);
}
