import React from 'react';
import { useAuth } from '../lib/AuthContext';
import usePageMeta from '../lib/usePageMeta';
import NavBar from '../components/NavBar';
import Footer from '../components/Footer';
import Login from './Login';
import ChangePassword from './ChangePassword';
import AdminDashboard from './AdminDashboard';

/**
 * Point d'entrée unique de l'administration, à l'adresse /admin : formulaire
 * de connexion sans session, changement de mot de passe s'il est imposé,
 * tableau de bord sinon. Seul le compte administrateur peut se connecter.
 */
export default function AdminApp() {
  usePageMeta('Administration — Vokso', "Espace d'administration Vokso.");
  const { user, loading, logout } = useAuth();

  let content: React.ReactNode;
  if (loading) {
    content = <p>Chargement...</p>;
  } else if (!user) {
    content = <Login />;
  } else if (user.role !== 'admin') {
    content = (
      <div className="auth-card">
        <h1>Accès refusé</h1>
        <p>Cet espace est réservé à l'administrateur.</p>
        <button type="button" onClick={logout}>Déconnexion</button>
      </div>
    );
  } else if (user.must_change_password) {
    content = <div className="auth-card"><ChangePassword /></div>;
  } else {
    return <AdminDashboard />;
  }

  return (
    <>
      <NavBar />
      <div className="container">{content}</div>
      <Footer />
    </>
  );
}
