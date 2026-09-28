import React, { useEffect, useState } from 'react';
import { Link, useSearchParams } from 'react-router-dom';
import { apiRequest, useAuth } from '../AuthContext';
import { config } from '../config';
import BillingProfileForm from '../components/account/BillingProfileForm';
import InvoiceHistory from '../components/account/InvoiceHistory';
import GdprPanel from '../components/account/GdprPanel';
import useCanonical from '../hooks/useCanonical';
import useDocumentMeta from '../hooks/useDocumentMeta';
import { cleanTitle } from '../utils/text';
import '../styles/globals.css';
import '../styles/Dashboard.css';

const PLAN_LABELS = {
  decouverte: 'Découverte',
  createur: 'Créateur',
  studio: 'Studio',
};

function downloadFile(url, filename) {
  const link = document.createElement('a');
  link.href = url;
  link.download = filename;
  link.target = '_blank';
  link.rel = 'noopener noreferrer';
  document.body.appendChild(link);
  link.click();
  link.remove();
}

function downloadText(title, text) {
  const blob = new Blob([text], { type: 'text/plain;charset=utf-8' });
  const url = URL.createObjectURL(blob);
  downloadFile(url, `${title || 'podcast'}.txt`);
  URL.revokeObjectURL(url);
}

export default function UserDashboard() {
  useCanonical();
  useDocumentMeta(
    'Mon espace — Vokso',
    'Retrouvez et gérez vos épisodes de podcast générés sur Vokso.'
  );
  const { user, logout } = useAuth();
  const [podcasts, setPodcasts] = useState([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(null);
  const [subscription, setSubscription] = useState(null);
  const [portalLoading, setPortalLoading] = useState(false);
  const [searchParams] = useSearchParams();
  const checkoutState = searchParams.get('checkout');

  useEffect(() => {
    apiRequest('user-podcasts')
      .then((data) => setPodcasts(data.data))
      .catch((e) => setError(e.message))
      .finally(() => setLoading(false));

    apiRequest('billing-status')
      .then((data) => setSubscription(data.subscription))
      .catch(() => setSubscription(null));
  }, []);

  const openBillingPortal = async () => {
    setPortalLoading(true);
    try {
      const data = await apiRequest('billing-portal', { method: 'POST' });
      window.location.href = data.url;
    } catch (e) {
      setError(e.message);
      setPortalLoading(false);
    }
  };

  return (
    <div className="container">
      <div className="dashboard-header">
        <h1>Mon espace</h1>
        <div className="dashboard-header-actions">
          <span>{user?.email}</span>
          <Link to="/">Accueil</Link>
          <button onClick={logout}>Déconnexion</button>
        </div>
      </div>

      {checkoutState === 'success' && (
        <p className="dashboard-notice">Votre abonnement est actif, merci !</p>
      )}
      {checkoutState === 'cancel' && (
        <p className="dashboard-notice">Paiement annulé, vous pouvez réessayer à tout moment.</p>
      )}

      <section>
        <h2>Ma formule</h2>
        {subscription ? (
          <>
            <p>
              Formule actuelle : <strong>{PLAN_LABELS[subscription.plan] || subscription.plan}</strong>
              {subscription.cancel_at_period_end && ' (résiliation programmée en fin de période)'}
            </p>
            {subscription.has_stripe_customer ? (
              <button onClick={openBillingPortal} disabled={portalLoading}>
                {portalLoading ? 'Redirection…' : 'Gérer mon abonnement'}
              </button>
            ) : (
              <Link to="/tarifs" className="btn btn-primary">Passer à une formule supérieure</Link>
            )}
          </>
        ) : (
          <p>Chargement de votre formule...</p>
        )}
      </section>

      <section>
        <h2>Facturation</h2>
        <h3>Informations de facturation</h3>
        <BillingProfileForm />
        <h3>Historique des factures</h3>
        <InvoiceHistory />
      </section>

      <section>
        <h2>Confidentialité et données personnelles</h2>
        <GdprPanel />
      </section>

      {loading && <p>Chargement de vos podcasts...</p>}
      {error && <p className="auth-error">{error}</p>}

      {!loading && podcasts.length === 0 && (
        <p>Vous n'avez pas encore généré de podcast. Retournez à l'accueil pour en créer un.</p>
      )}

      {!loading && podcasts.length > 0 && <h2>Mes podcasts</h2>}
      <div className="podcast-grid">
        {podcasts.map((p) => (
          <div className="podcast-card" key={p.generation_id}>
            <img
              src={`${config.staticUrl}/static/images/${p.image_url}`}
              alt={cleanTitle(p.title)}
              className="podcast-card-image"
              loading="lazy"
            />
            <div className="podcast-card-body">
              <h3>{cleanTitle(p.title)}</h3>
              <span className="podcast-card-category">{p.category}</span>
              <p className="podcast-card-date">{new Date(p.created_at).toLocaleString('fr-FR')}</p>
              <p className="podcast-card-cost">Coût estimé : {Number(p.cost_total).toFixed(4)} $</p>
              <audio controls src={`${config.staticUrl}/static/audios/${p.audio_url}`} className="podcast-card-audio" />
              <div className="podcast-card-actions">
                <button onClick={() => downloadFile(`${config.staticUrl}/static/images/${p.image_url}`, p.image_url)}>
                  Télécharger l'image
                </button>
                <button onClick={() => downloadFile(`${config.staticUrl}/static/audios/${p.audio_url}`, p.audio_url)}>
                  Télécharger l'audio
                </button>
                <button onClick={() => downloadText(p.title, p.description)}>
                  Télécharger le texte
                </button>
              </div>
            </div>
          </div>
        ))}
      </div>
    </div>
  );
}
