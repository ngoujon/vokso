// Filet de sécurité d'affichage : certains titres générés avant le
// renforcement de PodcastGenerator::sanitizeTitle() sont restés en base avec
// des artefacts Markdown ("**Titre**"). On les nettoie aussi ici pour ne pas
// afficher/indexer du Markdown brut sur les titres existants.
export function cleanTitle(title) {
  if (!title) {
    return title;
  }
  return title
    .replace(/(\*\*|__)(.*?)\1/g, '$2')
    .replace(/[*_`#]/g, '')
    .replace(/\s+/g, ' ')
    .trim();
}

// Même algorithme que Slugger::slugify() côté PHP (PodcastPageController) :
// doit produire un slug cohérent pour le lien vers /podcast/{id}-{slug}.
export function slugify(text) {
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
