import React, { useEffect, useState } from 'react';
import { Link } from 'react-router-dom';
import { apiRequest, downloadText, errorMessage, triggerDownload } from '../api';
import { useAuth } from '../AuthContext';
import { staticFileUrl } from '../config';
import GdprPanel from '../components/account/GdprPanel';
import useCanonical from '../hooks/useCanonical';
import useDocumentMeta from '../hooks/useDocumentMeta';
import type { PodcastWithCosts } from '../types';
import { cleanTitle } from '../utils/text';
import '../styles/globals.css';
import '../styles/Dashboard.css';

export default function UserDashboard() {
  useCanonical();
  useDocumentMeta('Mon espace — Vokso', 'Retrouvez et gérez vos épisodes de podcast générés sur Vokso.');
  const { user, logout } = useAuth();
  const [podcasts, setPodcasts] = useState<PodcastWithCosts[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    apiRequest<{ data: PodcastWithCosts[] }>('user-podcasts')
      .then((data) => setPodcasts(data.data))
      .catch((e) => setError(errorMessage(e)))
      .finally(() => setLoading(false));
  }, []);

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
              src={staticFileUrl('images', p.image_url)}
              alt={cleanTitle(p.title)}
              className="podcast-card-image"
              loading="lazy"
            />
            <div className="podcast-card-body">
              <h3>{cleanTitle(p.title)}</h3>
              <span className="podcast-card-category">{p.category}</span>
              <p className="podcast-card-date">{new Date(p.created_at).toLocaleString('fr-FR')}</p>
              <audio controls src={staticFileUrl('audios', p.audio_url)} className="podcast-card-audio" />
              <div className="podcast-card-actions">
                <button onClick={() => triggerDownload(staticFileUrl('images', p.image_url), p.image_url, true)}>
                  Télécharger l'image
                </button>
                <button onClick={() => triggerDownload(staticFileUrl('audios', p.audio_url), p.audio_url, true)}>
                  Télécharger l'audio
                </button>
                <button onClick={() => downloadText(cleanTitle(p.title), p.description)}>Télécharger le texte</button>
              </div>
            </div>
          </div>
        ))}
      </div>
    </div>
  );
}
