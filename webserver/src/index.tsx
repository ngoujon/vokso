import React from 'react';
import ReactDOM from 'react-dom/client';
import { BrowserRouter } from 'react-router-dom';
import * as Sentry from '@sentry/react';
import './index.css';
import App from './App';

// Sans DSN, le SDK reste inactif : aucune configuration nécessaire en local.
if (process.env.REACT_APP_SENTRY_DSN) {
  Sentry.init({
    dsn: process.env.REACT_APP_SENTRY_DSN,
    environment: process.env.NODE_ENV,
  });
}

// L'administration est servie à l'adresse /admin (réécrite vers le build par
// site/.htaccess), hors du préfixe /app des autres pages.
const basename = window.location.pathname.startsWith('/admin') ? '' : process.env.PUBLIC_URL;

const root = ReactDOM.createRoot(document.getElementById('root') as HTMLElement);
root.render(
  <React.StrictMode>
    <Sentry.ErrorBoundary fallback={<p>Une erreur est survenue.</p>}>
      <BrowserRouter basename={basename}>
        <App />
      </BrowserRouter>
    </Sentry.ErrorBoundary>
  </React.StrictMode>
);
