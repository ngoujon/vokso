import { useEffect } from 'react';
import { useLocation } from 'react-router-dom';

// Domaine canonique fixe : vokso.fr et www.vokso.fr servent le même contenu
// (pas de redirection www -> non-www côté Apache), donc on ne peut pas se
// baser sur window.location.origin sans dupliquer le signal SEO entre les deux hosts.
const CANONICAL_ORIGIN = 'https://vokso.fr';

export default function useCanonical(canonicalUrl = null) {
  const location = useLocation();

  useEffect(() => {
    const baseUrl = CANONICAL_ORIGIN;
    const fullPath = location.pathname;

    // Utiliser l'URL canonique fournie ou construire à partir du chemin
    const url = canonicalUrl || `${baseUrl}${fullPath}`;

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
