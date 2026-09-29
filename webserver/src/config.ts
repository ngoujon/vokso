export const config = {
  apiUrl: process.env.REACT_APP_API_URL ?? '',
  staticUrl: process.env.REACT_APP_STATIC_URL ?? '',
};

/** URL publique d'un fichier généré (images/audios servis sous /static). */
export function staticFileUrl(kind: 'images' | 'audios', fileName: string): string {
  return `${config.staticUrl}/static/${kind}/${fileName}`;
}
