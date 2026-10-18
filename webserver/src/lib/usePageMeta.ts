import { useEffect } from 'react';
import { useLocation } from 'react-router-dom';

// Domaine canonique fixe (vokso.fr, jamais www.vokso.fr) : la redirection
// www -> non-www est faite côté Apache (site/.htaccess), on la fige aussi ici.
const CANONICAL_ORIGIN = 'https://vokso.fr';

function upsertHeadTag<T extends HTMLElement>(selector: string, create: () => T): T {
  let tag = document.head.querySelector<T>(selector);
  if (!tag) {
    tag = create();
    document.head.appendChild(tag);
  }
  return tag;
}

/**
 * Titre, description et URL canonique de la page courante. Titre et
 * description sont restaurés en quittant la page ; une page d'erreur passe
 * canonical: false pour ne pas se déclarer canonique.
 */
export default function usePageMeta(title: string, description?: string, { canonical = true } = {}): void {
  const { pathname } = useLocation();

  useEffect(() => {
    const previousTitle = document.title;
    document.title = title;

    let descriptionTag: HTMLMetaElement | null = null;
    let previousDescription: string | null = null;
    if (description) {
      descriptionTag = upsertHeadTag('meta[name="description"]', () => {
        const meta = document.createElement('meta');
        meta.name = 'description';
        return meta;
      });
      previousDescription = descriptionTag.getAttribute('content');
      descriptionTag.setAttribute('content', description);
    }

    return () => {
      document.title = previousTitle;
      if (descriptionTag && previousDescription !== null) {
        descriptionTag.setAttribute('content', previousDescription);
      }
    };
  }, [title, description]);

  useEffect(() => {
    if (!canonical) {
      return;
    }
    const link = upsertHeadTag('link[rel="canonical"]', () => {
      const tag = document.createElement('link');
      tag.rel = 'canonical';
      return tag;
    });
    // pathname est relatif au basename ("/app") : window.location donne le
    // chemin réellement servi, /app inclus.
    link.href = `${CANONICAL_ORIGIN}${window.location.pathname}`;
  }, [pathname, canonical]);
}
