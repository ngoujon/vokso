# Flask AI Multimedia Generator

Ce projet est une application web basée sur **Flask** qui utilise les services d'OpenAI pour générer du **texte**, de l'**audio** et des **images** à partir des entrées des utilisateurs. Il combine plusieurs technologies de génération automatique pour offrir une expérience multimédia complète et interactive.

## Fonctionnalités

- **Génération de texte** : Utilisation de GPT-3.5 pour produire des réponses détaillées et organisées selon une structure prédéfinie (Introduction, Historique, Concepts clés, Applications, etc.).
- **Synthèse vocale** : Conversion du texte généré en fichier audio MP3 à l'aide de l'API Text-to-Speech d'OpenAI.
- **Génération d'images** : Création d'images minimalistes basées sur le sujet saisi par l'utilisateur via DALL-E 2.
- **Suivi de la progression** : Le processus est divisé en plusieurs étapes, et l'utilisateur peut voir l'avancement de la génération en temps réel.
- **Sauvegarde des fichiers** : Les réponses textuelles, les fichiers audio, et les images générées sont sauvegardés localement avec des noms de fichiers basés sur la date et l'heure.

## Technologies utilisées

### Backend

- **Flask** : Framework web léger en Python utilisé pour créer l'API et gérer les requêtes utilisateur.
- **OpenAI API** : L'API d'OpenAI est utilisée pour générer du texte, de l'audio et des images.
  - **GPT-3.5-turbo** : Modèle de traitement du langage naturel pour générer des réponses textuelles.
  - **Text-to-Speech (TTS)** : Modèle de conversion de texte en parole pour générer des fichiers audio MP3.
  - **DALL-E 2** : Modèle de génération d'images basé sur des descriptions textuelles.

### Frontend

- **HTML/CSS avec Flask** : L'interface utilisateur de base est servie via des templates Flask. La route principale `/` charge une page où l'utilisateur peut soumettre des demandes de génération.

### Stockage des fichiers

- **Pathlib** : Utilisé pour gérer la création et la gestion des chemins d'accès aux fichiers. Les fichiers générés (texte, audio et images) sont sauvegardés dans un dossier `output` avec un nom basé sur un horodatage.

### Environnement

- **Dotenv** : Pour charger les variables d'environnement (comme la clé API d'OpenAI) à partir d'un fichier `.env`.

### Dépendances Python

Le fichier `requirements.txt` contient toutes les bibliothèques nécessaires pour exécuter le projet :

- `Flask`
- `requests`
- `python-dotenv`
- `openai`

## Prérequis

1. **Clé API OpenAI** : Assurez-vous d'avoir une clé API OpenAI valide pour accéder aux modèles GPT-3.5, Text-to-Speech et DALL-E.
2. **Python 3.x** : Le projet est écrit en Python, donc assurez-vous d'avoir une version récente de Python installée.
3. **Pip** : Utilisé pour installer les dépendances Python.

## Installation

1. Clonez le dépôt Git :

   ```bash
   git clone https://github.com/votre-utilisateur/flask-ai-multimedia.git
   cd flask-ai-multimedia
   ```

2. Installez les dépendances :

```bash
pip install -r requirements.txt
```

3. Créez un fichier .env à la racine du projet et ajoutez votre clé OpenAI :

```
bash
OPENAI_API_KEY=your-openai-api-key
```

4. Créez un répertoire output dans le projet pour stocker les fichiers générés :

```bash
mkdir output
```
5. Lancez l'application Flask :

```bash
python app.py
```
6. Accédez à l'application dans votre navigateur :

bash
http://127.0.0.1:5000

## Utilisation

- Saisissez une demande dans le formulaire sur la page d'accueil.
- L'application génèrera une réponse textuelle, un fichier audio et une image en fonction de votre entrée.
- Vous recevrez un retour JSON avec des liens vers les fichiers générés.

## Structure du projet

```bash
├── app.py                 # Application principale
├── output/                # Dossier de sauvegarde des fichiers générés
├── templates/             # Fichiers HTML pour l'interface utilisateur
│   └── index.php          # Page d'accueil de l'application
├── .env                   # Fichier d'environnement pour la clé API
├── requirements.txt       # Dépendances Python
└── README.md              # Ce fichier
```

## Améliorations possibles

- Gestion d'erreurs : Ajouter une gestion des erreurs plus robuste pour traiter les éventuels échecs des appels API.
- Interface utilisateur : Améliorer l'interface en ajoutant du feedback visuel ou des barres de progression animées.
- Multilingue : Permettre la génération de réponses dans plusieurs langues, en fonction des préférences utilisateur.

## Contributeurs
Nicolas GOUJON - Développeur principal
