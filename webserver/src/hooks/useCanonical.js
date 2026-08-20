import { useEffect } from 'react';
import { useLocation } from 'react-router-dom';

export default function useCanonical(canonicalUrl = null) {
  const location = useLocation();

  useEffect(() => {
    const baseUrl = window.location.origin;
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
