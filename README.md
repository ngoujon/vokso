# Podcast Generator

Une application complète pour générer des podcasts comprenant du texte, des images et des fichiers audio, avec une interface utilisateur interactive et une API puissante.

---

## Fonctionnalités

### Backend (API)
- Génération de texte à l'aide de **OpenAI GPT-4**.
- Création d'images à partir du texte généré via **DALL-E**.
- Synthèse vocale (TTS) pour produire des fichiers audio.
- Catégorisation automatique des entrées utilisateur.
- Stockage des données générées (texte, images, audio) dans une base de données MySQL.
- API REST permettant :
  - **POST** `/generation` : Soumettre une génération.
  - **GET** `/listing` : Récupérer les dernières générations.
  - **GET** `/search?query=...` : Rechercher des contenus.

### Frontend
- Champ de saisie pour générer du contenu.
- Recherche dynamique dans les contenus existants.
- Lecture audio avec contrôle avancé (lecture/pause, avancer/reculer, barre de progression).
- Affichage des images et texte générés.
- Info-bulles pour les titres longs.
- Gestion des erreurs et indicateurs de chargement.

---

## Installation

### Prérequis
- PHP (≥ 7.4)
- Composer
- Node.js (≥ 14.0) et npm/yarn
- MySQL
- OpenAI API Key

### Backend

1. Installez les dépendances PHP avec Composer :

cd backend  
composer install  

2. Configurez votre environnement :
   - Créez un fichier `.env` à la racine du répertoire backend avec les clés API et les paramètres de base de données :

DB_HOST=localhost  
DB_NAME=generation_db  
DB_USER=webapp  
DB_PASS=***MOT-DE-PASSE-SUPPRIME***  

OPENAI_API_KEY=sk-xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx  

3. Configurez la base de données :
   - Importez le fichier SQL (`schema.sql`) pour créer les tables nécessaires :

mysql -u root -p generation_db < schema.sql  

4. Démarrez le serveur local pour le backend :

php -S localhost:8000 -t public  

### Frontend

1. Naviguez dans le répertoire frontend :

cd ../frontend  

2. Installez les dépendances Node.js :

npm install  

3. Lancez l'application React en mode développement :

npm run dev  

4. Accédez à l'interface utilisateur :
   - [http://localhost:3000](http://localhost:3000)

---

## Utilisation

### Génération d'un contenu
1. Entrez un texte dans le champ d'entrée sur la page d'accueil.
2. Cliquez sur **Générer**.
3. Les résultats (texte, image, audio) s'affichent avec des options de lecture et d'affichage.

### Recherche
1. Tapez un mot-clé dans le champ d'entrée.
2. Attendez 2 secondes pour voir les résultats de la recherche.
3. Les résultats s'affichent dynamiquement.

---

## Structure du projet

### Backend
- **`/app/Controllers/GenerationController.php`** : Contrôleur principal pour la génération.
- **`/app/Models`** : Gestion des interactions avec la base de données.
- **`/public/output/`** : Répertoire pour stocker les fichiers générés (texte, images, audios).

### Frontend
- **`/frontend/pages/Home.js`** : Composant principal React pour l'interface utilisateur.
- **`/frontend/styles/globals.css`** : Styles globaux.
- **`/frontend/public/`** : Ressources statiques.

---

## API Endpoints

### POST `/generation`
- **Description** : Génère du contenu (texte, image, audio) à partir d'une entrée utilisateur.
- **Requête** :
```
{
  "input": "Votre texte ici"
}
```
- **Réponse** :
```
{
  "message": "Texte, image et audio générés avec succès",
  "file": "response_20250101_123456.txt",
  "generated_text": "Texte généré...",
  "image": "image_20250101_123456.png",
  "audio": "audio_20250101_123456.mp3",
  "generation_id": "gen_xxxxx",
  "idcategorie": 1
}
```
### GET `/listing`
- **Description** : Récupère les trois dernières générations.
- **Réponse** :
```
{
  "success": true,
  "data": [
    {
      "title": "Titre 1",
      "description": "Description 1",
      "image_url": "image1.png",
      "audio_url": "audio1.mp3"
    },
    ...
  ]
}
```
### GET `/search?query=...`
- **Description** : Recherche des contenus générés basés sur un mot-clé.
- **Réponse** :
```
{
  "success": true,
  "data": [...]
}
```
---

## Technologies utilisées

- **Backend** :
  - PHP 7+ (Framework custom)
  - MySQL pour le stockage des données
  - Guzzle pour les appels API

- **Frontend** :
  - React (avec Hooks)
  - CSS personnalisé
  - Fetch API pour les requêtes

- **Services externes** :
  - OpenAI API pour GPT-4, DALL-E et TTS

---

## Contribution

1. Forkez le projet.
2. Créez une branche pour votre fonctionnalité :

git checkout -b nouvelle-fonctionnalite  

3. Faites vos modifications et testez-les.
4. Soumettez une Pull Request.

---

## Auteur

- **Nom** : [Votre Nom]
- **Contact** : [Votre Email]

---

## Licence

Ce projet est sous licence MIT. Consultez le fichier `LICENSE` pour plus de détails.