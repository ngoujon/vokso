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
