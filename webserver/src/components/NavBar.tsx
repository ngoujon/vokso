import React, { useState } from 'react';
import { Link } from 'react-router-dom';
import { useAuth } from '../AuthContext';

/**
 * En-tête commun à tout le site : même balisage que la vitrine (site/*.html)
 * et la page d'un épisode, stylé par /assets/site.css. Les liens vers la
 * vitrine sont de simples <a> : ces pages vivent hors de l'application (/app).
 */
export function BrandMark() {
  return (
    <span className="site-brand-mark" aria-hidden="true">
      <svg viewBox="218 312 588 382" fill="currentColor">
        <rect x="258" y="432" width="52" height="160" rx="26" />
        <rect x="336" y="352" width="52" height="320" rx="26" />
        <rect x="414" y="402" width="52" height="220" rx="26" />
        <rect x="500" y="362" width="266" height="52" rx="26" opacity="0.55" />
        <rect x="500" y="442" width="200" height="52" rx="26" opacity="0.55" />
        <rect x="500" y="522" width="266" height="52" rx="26" opacity="0.55" />
        <rect x="500" y="602" width="160" height="52" rx="26" opacity="0.55" />
      </svg>
    </span>
  );
}

export default function NavBar() {
  const { user, logout } = useAuth();
  const [open, setOpen] = useState(false);

  return (
    <header className="site-header">
      <div className="wrap">
        <a className="site-brand" href="/" aria-label="Vokso, accueil">
          <BrandMark />
          Vokso
        </a>
        <button
          className="site-nav-toggle"
          type="button"
          aria-expanded={open}
          aria-controls="site-nav"
          aria-label="Menu"
          onClick={() => setOpen(!open)}
        >
          <span></span>
        </button>
        <nav className={`site-nav ${open ? 'is-open' : ''}`} id="site-nav" aria-label="Navigation principale">
          <a className="site-nav-link" href="/#selection">Sélection du jour</a>
          <a className="site-nav-link" href="/discotheque">Discothèque</a>
          <a className="site-nav-link" href="/comment-ca-marche">Comment ça marche ?</a>
          {user && (
            <>
              <Link className="site-nav-link" to={user.role === 'admin' ? '/admin' : '/dashboard'}>
                {user.role === 'admin' ? 'Espace admin' : 'Mon espace'}
              </Link>
              <button type="button" className="site-nav-link" onClick={logout}>Déconnexion</button>
            </>
          )}
          <a className="vk-btn vk-btn-signal" href="/#creer">Créer un épisode</a>
        </nav>
      </div>
    </header>
  );
}
