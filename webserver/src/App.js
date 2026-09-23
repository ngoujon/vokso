import React from 'react';
import { Routes, Route } from 'react-router-dom';
import Home from './pages/Home';
import Login from './pages/Login';
import Pricing from './pages/Pricing';
import PrivacyPolicy from './pages/PrivacyPolicy';
import Contact from './pages/Contact';
import UserDashboard from './pages/UserDashboard';
import AdminDashboard from './pages/AdminDashboard';
import ProtectedRoute from './components/ProtectedRoute';
import CookieConsentBanner from './components/CookieConsentBanner';
import { AuthProvider } from './AuthContext';
import './styles/App.css';

function App() {
  return (
    <AuthProvider>
      <div className="App">
        <CookieConsentBanner />
        <Routes>
          <Route path="/" element={<Home />} />
          <Route path="/login" element={<Login />} />
          <Route path="/tarifs" element={<Pricing />} />
          <Route path="/politique-de-confidentialite" element={<PrivacyPolicy />} />
          <Route path="/contact" element={<Contact />} />
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
        </Routes>
      </div>
    </AuthProvider>
  );
}

export default App; 