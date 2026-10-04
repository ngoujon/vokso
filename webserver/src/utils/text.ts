// Filet de sécurité d'affichage : certains titres générés anciennement sont
// restés en base avec des artefacts Markdown ("**Titre**").
export function cleanTitle(title: string | null | undefined): string {
  if (!title) {
    return '';
  }
  return title
    .replace(/(\*\*|__)(.*?)\1/g, '$2')
    .replace(/[*_`#]/g, '')
    .replace(/\s+/g, ' ')
    .trim();
}

// Même algorithme que EpisodeText::slugify() côté API : le lien vers
// /podcast/{id}-{slug} doit correspondre à l'URL canonique.
export function slugify(text: string | null | undefined): string {
  if (!text) {
    return '';
  }
  return text
    .normalize('NFD')
    .replace(/[̀-ͯ]/g, '')
    .toLowerCase()
    .replace(/[^a-z0-9]+/g, '-')
    .replace(/^-+|-+$/g, '')
    .slice(0, 80);
}

// URL lisible /podcast/{slug} renvoyée par l'API ; repli sur l'ancienne forme
// /podcast/{id}-{titre} (redirigée en 301 par l'API) pour un épisode sans slug.
export function episodePath(id: string, title: string | null | undefined, apiSlug?: string | null): string {
  if (apiSlug) {
    return `/podcast/${apiSlug}`;
  }
  const slug = slugify(cleanTitle(title));
  return `/podcast/${id}${slug ? `-${slug}` : ''}`;
}
