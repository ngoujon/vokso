import React, { useEffect } from 'react';
import { Routes, Route } from 'react-router-dom';
import Login from './pages/Login';
import PrivacyPolicy from './pages/PrivacyPolicy';
import Contact from './pages/Contact';
import UserDashboard from './pages/UserDashboard';
import AdminDashboard from './pages/AdminDashboard';
import ChangePassword from './pages/ChangePassword';
import NotFound from './pages/NotFound';
import ProtectedRoute from './components/ProtectedRoute';
import CookieConsentBanner from './components/CookieConsentBanner';
import { AuthProvider } from './AuthContext';
import './styles/App.css';

/**
 * L'accueil (création d'un épisode, sélection, discothèque) vit désormais
 * sur la vitrine, à la racine du site : /app/ y renvoie.
 */
function SiteHome() {
  useEffect(() => {
    window.location.replace('/');
  }, []);
  return null;
}

function App() {
  return (
    <AuthProvider>
      <div className="App">
        <CookieConsentBanner />
        <Routes>
          <Route path="/" element={<SiteHome />} />
          <Route path="/login" element={<Login />} />
          {/* Ancienne page de tarifs : le service est désormais gratuit. */}
          <Route path="/tarifs" element={<SiteHome />} />
          <Route path="/politique-de-confidentialite" element={<PrivacyPolicy />} />
          <Route path="/contact" element={<Contact />} />
          <Route
            path="/changer-mot-de-passe"
            element={(
              <ProtectedRoute skipPasswordCheck>
                <ChangePassword />
              </ProtectedRoute>
            )}
          />
          <Route
            path="/dashboard"
            element={(
              <ProtectedRoute>
                <UserDashboard />
              </ProtectedRoute>
            )}
          />
          <Route
            path="/admin"
            element={(
              <ProtectedRoute requireAdmin>
                <AdminDashboard />
              </ProtectedRoute>
            )}
          />
          <Route path="*" element={<NotFound />} />
        </Routes>
      </div>
    </AuthProvider>
  );
}

export default App; 