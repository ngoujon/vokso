import React from 'react';
import { Link, useLocation } from 'react-router-dom';
import { useAuth } from '../AuthContext';
import { ReactComponent as VoksoMark } from '../assets/vokso-mark.svg';

export default function NavBar() {
  const { user, logout } = useAuth();
  const location = useLocation();

  const isActive = (path: string) => location.pathname === path;

  return (
    <nav className="navbar">
      <div className="navbar-inner">
        <Link to="/" className="navbar-logo">
          <span className="navbar-logo-mark" aria-hidden="true">
            <VoksoMark />
          </span>
          Vokso
        </Link>
        <div className="navbar-links">
          <Link to="/" className={isActive('/') ? 'active' : ''}>Accueil</Link>
          <Link to="/contact" className={isActive('/contact') ? 'active' : ''}>Contact</Link>
          {/* Pas de compte à créer : la connexion (/login) ne sert plus qu'à l'administration. */}
          {user && (
            <>
              <Link to={user.role === 'admin' ? '/admin' : '/dashboard'}>
                {user.role === 'admin' ? 'Espace admin' : 'Mon espace'}
              </Link>
              <button onClick={logout}>Déconnexion</button>
            </>
          )}
        </div>
      </div>
    </nav>
  );
}
