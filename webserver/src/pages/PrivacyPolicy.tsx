import React, { useState } from 'react';
import NavBar from '../components/NavBar';
import Footer from '../components/Footer';
import usePageMeta from '../lib/usePageMeta';
import { optOutOfAudience } from '../lib/audience';

// TODO (à compléter par l'éditeur du site) : identité du responsable du
// traitement, introuvable dans le projet (aucune page de mentions légales).
const DATA_CONTROLLER = 'TODO : nom ou raison sociale, adresse et e-mail de contact du responsable du traitement';

export default function PrivacyPolicy() {
  const [optedOut, setOptedOut] = useState<null | boolean>(null);
  usePageMeta(
    'Politique de confidentialité — Vokso',
    'Comment Vokso collecte et traite vos données personnelles : contenus générés par IA, newsletter, données techniques, mesure d\'audience.'
  );

  return (
    <>
    <NavBar />
    <div className="page-container" style={{ maxWidth: '800px', margin: '0 auto', padding: '2rem 1.5rem', lineHeight: 1.6 }}>
      <p><a href="/">&larr; Retour à l'accueil</a></p>
      <h1>Politique de confidentialité</h1>
      <p>
        Cette page explique quelles données personnelles Vokso collecte lorsque vous
        utilisez le site, pourquoi, et avec qui elles sont partagées.
      </p>
      <p>
        <strong>Responsable du traitement</strong> : <mark>{DATA_CONTROLLER}</mark>.
      </p>

      <h2>1. Données collectées</h2>
      <ul>
        <li><strong>Contenus soumis pour génération</strong> : le sujet ou texte que vous saisissez, ou l'enregistrement audio de votre voix si vous utilisez la saisie vocale.</li>
        <li><strong>Contenus générés</strong> : les textes, images et fichiers audio produits par le site à partir de vos demandes, publiés dans le catalogue public des épisodes.</li>
        <li><strong>Newsletter</strong> : votre adresse e-mail, si vous choisissez de vous y inscrire.</li>
        <li><strong>Données techniques</strong> : adresse IP, conservée le temps nécessaire (de l'ordre de 24 heures) pour limiter le nombre de générations et de requêtes et prévenir les abus (le service est gratuit et sans inscription).</li>
        <li><strong>Mesure d'audience</strong> : statistiques de fréquentation anonymes, détaillées dans la section 7 ci-dessous.</li>
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
          <strong>Envoi d'e-mails</strong> : les e-mails liés à la newsletter ou à vos messages de contact sont envoyés via
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
        <li>Générer les podcasts (texte, image, audio) que vous demandez.</li>
        <li>Publier les épisodes générés pour qu'ils puissent être écoutés.</li>
        <li>Vous envoyer la newsletter si vous y êtes inscrit·e.</li>
        <li>Assurer la sécurité et la disponibilité du site (limitation du nombre de requêtes).</li>
      </ul>

      <h2>4. Durée de conservation</h2>
      <p>
        Les épisodes générés restent publiés tant que le service existe ; vous pouvez demander le retrait
        d'un épisode à tout moment via le formulaire de contact. Les adresses IP utilisées pour la limitation
        des requêtes sont effacées dès qu'elles ne servent plus à ce calcul (de l'ordre de 24 heures).
      </p>

      <h2>5. Vos droits</h2>
      <p>
        Vous pouvez demander l'accès, la rectification ou la suppression de vos données personnelles, ainsi que
        vous désinscrire de la newsletter à tout moment, en nous contactant via le formulaire de contact du site.
      </p>

      <h2>6. Cookies et traceurs</h2>
      <p>
        Le site utilise les cookies ou jetons techniques strictement nécessaires à votre connexion
        (maintien de la session), ainsi qu'un traceur de mesure d'audience exempté de consentement :
      </p>
      <ul>
        <li>
          <strong>sm_vid</strong> (stockage local du navigateur) : identifiant aléatoire de mesure d'audience,
          conservé 13 mois au maximum, exempté de consentement (voir la section 7).
        </li>
      </ul>
      <p>Aucun cookie publicitaire ni traceur d'un service tiers n'est déposé.</p>

      <h2 id="mesure-audience">7. Mesure d'audience</h2>
      <p>
        Ce site utilise SEO Monitor, un outil de mesure d'audience auto-hébergé, afin d'établir des statistiques
        de fréquentation anonymes (pages consultées, provenance des visites, durée de consultation, type
        d'appareil). Ces données servent uniquement au bon fonctionnement et à l'amélioration du site.
      </p>
      <p>
        Un identifiant aléatoire est conservé dans votre navigateur pendant 13 mois au maximum. Votre adresse IP
        n'est jamais enregistrée : elle sert uniquement, de manière éphémère, à déduire un pays et une ville
        approximatifs. Les données ne sont ni recoupées avec d'autres traitements, ni transmises à des tiers, et
        sont conservées 25 mois au maximum.
      </p>
      <ul>
        <li><strong>Responsable du traitement</strong> : <mark>{DATA_CONTROLLER}</mark>.</li>
        <li><strong>Finalité</strong> : mesure d'audience du site.</li>
        <li>
          <strong>Base légale</strong> : intérêt légitime de l'éditeur à mesurer la fréquentation de son site ;
          traitement exempté de consentement au titre de l'article 82 de la loi Informatique et Libertés et des
          lignes directrices de la CNIL relatives aux cookies et autres traceurs.
        </li>
        <li>
          <strong>Données collectées</strong> : pages vues, provenance des visites, durée de consultation,
          défilement dans la page, type d'appareil, navigateur, langue, pays et ville approximatifs. L'adresse IP
          n'est pas conservée.
        </li>
        <li>
          <strong>Durée de conservation</strong> : 25 mois au maximum pour les statistiques, 13 mois au maximum
          pour l'identifiant stocké dans le navigateur (sm_vid).
        </li>
        <li>
          <strong>Destinataires</strong> : aucun tiers ; les données sont hébergées sur un serveur OVH situé en
          France.
        </li>
        <li>
          <strong>Vos droits</strong> : accès, rectification, effacement, opposition et limitation du traitement,
          en nous écrivant via le formulaire de contact du site. Vous pouvez aussi introduire une réclamation
          auprès de la CNIL (<a href="https://www.cnil.fr" target="_blank" rel="noopener noreferrer">www.cnil.fr</a>).
        </li>
      </ul>
      <p>
        Conformément aux lignes directrices de la CNIL, ce traitement est exempté de consentement. Vous pouvez
        néanmoins vous y opposer à tout moment :{' '}
        {optedOut === null ? (
          <a
            href="#mesure-audience"
            onClick={(e) => {
              e.preventDefault();
              setOptedOut(optOutOfAudience());
            }}
          >
            ne plus être mesuré
          </a>
        ) : optedOut ? (
          <span role="status">Vos visites ne sont plus mesurées.</span>
        ) : (
          <span role="status">La mesure d'audience n'est pas active dans ce navigateur : aucune visite n'est mesurée.</span>
        )}
        .
      </p>
    </div>
    <Footer />
    </>
  );
}
