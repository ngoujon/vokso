import React, { useEffect, useState } from 'react';
import { Link } from 'react-router-dom';
import { apiRequest, useAuth } from '../AuthContext';
import { config } from '../config';
import useCanonical from '../hooks/useCanonical';
import useDocumentMeta from '../hooks/useDocumentMeta';
import '../styles/globals.css';
import '../styles/Dashboard.css';

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

  useEffect(() => {
    apiRequest('user-podcasts')
      .then((data) => setPodcasts(data.data))
      .catch((e) => setError(e.message))
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
              alt={p.title}
              className="podcast-card-image"
              loading="lazy"
            />
            <div className="podcast-card-body">
              <h3>{p.title}</h3>
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
