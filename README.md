# Vokso - Générateur de Podcasts

Une application web permettant de générer automatiquement des podcasts à partir de textes, en utilisant l'intelligence artificielle pour créer du contenu multimédia complet.

## Description

Vokso est une application qui transforme du texte en podcasts complets, incluant :
- Un texte structuré en français
- Une illustration générée par IA
- Un fichier audio de synthèse vocale

Le système utilise des modèles d'IA avancés pour :
- Générer du contenu textuel cohérent et structuré
- Créer des images minimalistes et modernes
- Convertir le texte en audio naturel
- Catégoriser automatiquement le contenu

## Fonctionnalités

### Génération de Contenu
- Création de textes en français (limité à 4000 caractères)
- Génération d'images minimalistes avec style scandinave
- Synthèse vocale pour la narration
- Catégorisation automatique du contenu

### Interface Utilisateur
- Formulaire de saisie pour le contenu
- Recherche en temps réel dans les podcasts existants
- Lecteur audio intégré avec contrôles avancés
- Affichage des images et textes générés
- Gestion des erreurs et états de chargement

## Installation

### Prérequis
- PHP 7.4 ou supérieur
- Composer
- Node.js 14.0 ou supérieur
- MySQL
- Clé API OpenAI

### Configuration

1. Installation du backend :
```bash
cd backend
composer install
```

2. Configuration de l'environnement :
Créez un fichier `.env` avec les variables suivantes :
```env
DB_HOST=
DB_NAME=
DB_USER=
DB_PASS=
OPENAI_API_KEY=
```

3. Configuration de la base de données :
```bash
mysql -u root -p < SQL/2025-01-11.sql
```

4. Démarrage du serveur backend :
```bash
php -S localhost:8000 -t public
```

5. Installation du frontend :
```bash
cd frontend
npm install
npm run dev
```

## Architecture

### Base de Données
- Table `prompt` : Stockage des modèles de prompts
- Table `histo_prompt` : Historique des modifications des prompts
- Table `categorie` : Gestion des catégories de contenu
- Table `generations` : Stockage des contenus générés

### API Endpoints
- POST `/generation` : Création de nouveau contenu
- GET `/listing` : Liste des générations récentes
- GET `/search` : Recherche dans les contenus

## Technologies

### Backend
- PHP 7+
- MySQL
- Guzzle (client HTTP)

### Frontend
- React
- CSS personnalisé
- Fetch API

### Services Externes
- OpenAI API (GPT-4, DALL-E, TTS)

## Contribution

1. Fork du projet
2. Création d'une branche pour la fonctionnalité
3. Tests et modifications
4. Pull Request

## Licence

Ce projet est sous licence MIT. Voir le fichier `LICENSE` pour plus de détails.