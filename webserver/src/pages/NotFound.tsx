import React from 'react';
import '../styles/globals.css';
import NavBar from '../components/NavBar';
import Footer from '../components/Footer';
import useDocumentMeta from '../hooks/useDocumentMeta';

export default function NotFound() {
  useDocumentMeta(
    'Page introuvable — Vokso',
    "La page que vous cherchez n'existe pas ou plus."
  );

  return (
    <>
      <NavBar />
      <div
        className="page-container"
        style={{ maxWidth: '600px', margin: '0 auto', padding: '4rem 1.5rem', textAlign: 'center' }}
      >
        <h1>404</h1>
        <p>Cette page n'existe pas ou plus.</p>
        <p><a href="/">&larr; Retour à l'accueil</a></p>
      </div>
      <Footer />
    </>
  );
}
