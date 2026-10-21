import React, { useEffect } from 'react';
import { Routes, Route } from 'react-router-dom';
import PrivacyPolicy from './pages/PrivacyPolicy';
import Contact from './pages/Contact';
import AdminApp from './pages/AdminApp';
import NotFound from './pages/NotFound';
import { AuthProvider } from './lib/AuthContext';

/** Redirection hors de l'application (vitrine, ou administration sous /admin). */
function ExternalRedirect({ to }: { to: string }) {
  useEffect(() => {
    window.location.replace(to);
  }, [to]);
  return null;
}

/** L'administration ne vit qu'à l'adresse /admin : sous /app, on y renvoie. */
function isAdminUrl(): boolean {
  return window.location.pathname.startsWith('/admin');
}

function App() {
  return (
    <AuthProvider>
      <div className="App">
        {/* Bandeau cookies : /assets/consent.js, commun à tout le site. */}
        <Routes>
          {/* L'accueil (création d'un épisode, sélection, discothèque) vit sur la vitrine. */}
          <Route path="/" element={<ExternalRedirect to="/" />} />
          {/* Ancienne page de tarifs : le service est désormais gratuit. */}
          <Route path="/tarifs" element={<ExternalRedirect to="/" />} />
          <Route path="/politique-de-confidentialite" element={<PrivacyPolicy />} />
          <Route path="/contact" element={<Contact />} />
          <Route path="/admin" element={isAdminUrl() ? <AdminApp /> : <ExternalRedirect to="/admin" />} />
          {/* Anciennes adresses de connexion et des espaces connectés. */}
          <Route path="/login" element={<ExternalRedirect to="/admin" />} />
          <Route path="/dashboard" element={<ExternalRedirect to="/admin" />} />
          <Route path="/changer-mot-de-passe" element={<ExternalRedirect to="/admin" />} />
          <Route path="*" element={<NotFound />} />
        </Routes>
      </div>
    </AuthProvider>
  );
}

export default App;
