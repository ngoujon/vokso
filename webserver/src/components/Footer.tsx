import React from 'react';
import { Link } from 'react-router-dom';
import { BrandMark } from './NavBar';

/** Pied de page commun à tout le site (même balisage que la vitrine, voir /assets/site.css). */
export default function Footer() {
  return (
    <footer className="site-footer">
      <div className="wrap">
        <div className="site-footer-top">
          <div className="site-footer-about">
            <a className="site-brand" href="/" aria-label="Vokso, accueil">
              <BrandMark />
              Vokso
            </a>
            <p className="site-footer-pitch">
              Un sujet en une phrase, un podcast complet en retour : texte, pochette et voix générés par IA,
              sur une infrastructure européenne.
            </p>
            <div className="site-footer-badges"><span>Gratuit</span><span>Sans inscription</span><span>Hébergé en Europe</span></div>
          </div>
          <nav className="site-footer-col" aria-label="Écouter">
            <p className="site-footer-title">Écouter</p>
            <ul>
              <li><a href="/#selection">Sélection du jour</a></li>
              <li><a href="/discotheque">Toute la discothèque</a></li>
              <li><a href="/discotheque#categories">Par catégorie</a></li>
            </ul>
          </nav>
          <nav className="site-footer-col" aria-label="Créer">
            <p className="site-footer-title">Créer</p>
            <ul>
              <li><a href="/#creer">Générer un épisode</a></li>
              <li><a href="/comment-ca-marche">Comment ça marche ?</a></li>
              <li><a href="/comment-ca-marche#souverainete">Souveraineté et données</a></li>
            </ul>
          </nav>
          <nav className="site-footer-col" aria-label="Vokso">
            <p className="site-footer-title">Vokso</p>
            <ul>
              <li><Link to="/contact">Contact</Link></li>
              <li><Link to="/politique-de-confidentialite">Confidentialité</Link></li>
              <li><Link to="/admin">Administration</Link></li>
            </ul>
          </nav>
        </div>
        <p className="site-footer-wordmark" aria-hidden="true">Vokso</p>
        <div className="site-footer-bottom">
          <p>© {new Date().getFullYear()} Vokso — podcasts générés par IA, hébergés en Europe.</p>
          <p>Texte, image et voix : Mistral AI · Émis depuis l'Union européenne</p>
        </div>
      </div>
    </footer>
  );
}
