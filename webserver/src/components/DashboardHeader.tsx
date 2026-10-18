import React from 'react';
import { useAuth } from '../lib/AuthContext';

/** En-tête des espaces connectés : titre, compte courant, retour à l'accueil et déconnexion. */
export default function DashboardHeader({ title }: { title: string }) {
  const { user, logout } = useAuth();

  return (
    <div className="dashboard-header">
      <h1>{title}</h1>
      <div className="dashboard-header-actions">
        <span>{user?.email}</span>
        <a href="/">Accueil</a>
        <button onClick={logout}>Déconnexion</button>
      </div>
    </div>
  );
}
