import React from 'react';
import { Link } from 'react-router-dom';

export default function Footer() {
  return (
    <footer className="site-footer">
      <div className="site-footer-inner">
        <span>© {new Date().getFullYear()} QwaiPod — Infrastructure hébergée en Europe.</span>
        <div className="site-footer-links">
          <Link to="/tarifs">Tarifs</Link>
          <Link to="/politique-de-confidentialite">Politique de confidentialité</Link>
        </div>
      </div>
    </footer>
  );
}
