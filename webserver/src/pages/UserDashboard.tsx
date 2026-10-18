import React, { useEffect, useState } from 'react';
import { apiRequest, errorMessage, staticFileUrl } from '../lib/api';
import DashboardHeader from '../components/DashboardHeader';
import EpisodeDownloads from '../components/EpisodeDownloads';
import GdprPanel from '../components/GdprPanel';
import Footer from '../components/Footer';
import usePageMeta from '../lib/usePageMeta';
import type { PodcastWithCosts } from '../lib/types';
import { cleanTitle } from '../lib/text';

/** Espace utilisateur : données personnelles (RGPD) et épisodes créés avec ce compte. */
export default function UserDashboard() {
  usePageMeta('Mon espace — Vokso', 'Retrouvez et gérez vos épisodes de podcast générés sur Vokso.');
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
    <>
    <div className="container">
      <DashboardHeader title="Mon espace" />

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
                <EpisodeDownloads episode={p} />
              </div>
            </div>
          </div>
        ))}
      </div>
    </div>
    <Footer />
    </>
  );
}
