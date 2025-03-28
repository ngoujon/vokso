// Vérification des variables d'environnement
const apiUrl = process.env.NEXT_PUBLIC_API_URL;
const staticUrl = process.env.NEXT_PUBLIC_STATIC_URL;

if (!apiUrl || !staticUrl) {
  console.error("Erreur: Les variables d'environnement ne sont pas définies");
}

export const config = {
  apiUrl,
  staticUrl
}; 