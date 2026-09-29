import React from 'react';
import { Link } from 'react-router-dom';
import '../styles/globals.css';
import NavBar from '../components/NavBar';
import Footer from '../components/Footer';
import useCanonical from '../hooks/useCanonical';
import useDocumentMeta from '../hooks/useDocumentMeta';

export default function PrivacyPolicy() {
  useCanonical();
  useDocumentMeta(
    'Politique de confidentialité — Vokso',
    'Comment Vokso collecte et traite vos données personnelles : compte utilisateur, contenus générés par IA, newsletter.'
  );

  return (
    <>
    <NavBar />
    <div className="page-container" style={{ maxWidth: '800px', margin: '0 auto', padding: '2rem 1.5rem', lineHeight: 1.6 }}>
      <p><Link to="/">&larr; Retour à l'accueil</Link></p>
      <h1>Politique de confidentialité</h1>
      <p>
        Cette page explique quelles données personnelles Vokso collecte lorsque vous
        utilisez le site, pourquoi, et avec qui elles sont partagées.
      </p>

      <h2>1. Données collectées</h2>
      <ul>
        <li><strong>Compte utilisateur</strong> : adresse e-mail et mot de passe (stocké sous forme chiffrée), utilisés pour la connexion et la gestion de votre compte.</li>
        <li><strong>Contenus soumis pour génération</strong> : le sujet ou texte que vous saisissez, ou l'enregistrement audio de votre voix si vous utilisez la saisie vocale.</li>
        <li><strong>Contenus générés</strong> : les textes, images et fichiers audio produits par le site à partir de vos demandes, ainsi que l'historique de vos générations.</li>
        <li><strong>Newsletter</strong> : votre adresse e-mail, si vous choisissez de vous y inscrire.</li>
        <li><strong>Données techniques</strong> : adresse IP, utilisée pour limiter le nombre de requêtes et prévenir les abus (protection anti-spam), et nombre de générations effectuées par votre compte dans le mois (le service est gratuit, avec une limite mensuelle par compte).</li>
      </ul>

      <h2>2. Services utilisés pour traiter vos données</h2>
      <p>Pour fonctionner, Vokso fait appel aux prestataires suivants :</p>
      <ul>
        <li>
          <strong>Génération de texte, d'images et de la voix</strong> : le sujet ou texte que vous fournissez
          est envoyé à Mistral AI (société française, traitement dans l'Union européenne) pour produire le
          texte, l'illustration et la narration audio du podcast.
        </li>
        <li>
          <strong>Transcription vocale</strong> : si vous utilisez la saisie par la voix, votre enregistrement
          audio est envoyé à Mistral AI afin d'être converti en texte, puis supprimé de nos serveurs une fois
          la transcription effectuée.
        </li>
        <li>
          <strong>Envoi d'e-mails</strong> : les e-mails liés à votre compte ou à la newsletter sont envoyés via
          un serveur d'envoi d'e-mails (SMTP).
        </li>
      </ul>
      <p>
        Ces prestataires ne reçoivent que les données strictement nécessaires à l'exécution de la tâche demandée
        (le texte ou l'audio à traiter) et ne sont pas autorisés à les utiliser à d'autres fins que la fourniture
        du service à Vokso.
      </p>

      <h2>3. Pourquoi ces données sont traitées</h2>
      <ul>
        <li>Créer et gérer votre compte, et vous permettre de vous connecter.</li>
        <li>Générer les podcasts (texte, image, audio) que vous demandez.</li>
        <li>Conserver l'historique de vos générations pour vous permettre de les réécouter.</li>
        <li>Vous envoyer la newsletter si vous y êtes inscrit·e.</li>
        <li>Assurer la sécurité et la disponibilité du site (limitation du nombre de requêtes).</li>
      </ul>

      <h2>4. Durée de conservation</h2>
      <p>
        Vos données de compte et l'historique de vos générations sont conservés tant que votre compte est actif.
        Vous pouvez demander la suppression de votre compte et des données associées à tout moment.
      </p>

      <h2>5. Vos droits</h2>
      <p>
        Vous pouvez demander l'accès, la rectification ou la suppression de vos données personnelles, ainsi que
        vous désinscrire de la newsletter à tout moment, en nous contactant via le formulaire de contact du site.
      </p>

      <h2>6. Cookies</h2>
      <p>
        Le site utilise uniquement les cookies ou jetons techniques strictement nécessaires à votre connexion
        (maintien de la session). Aucun cookie publicitaire ou de mesure d'audience tiers n'est déposé.
      </p>
    </div>
    <Footer />
    </>
  );
}
