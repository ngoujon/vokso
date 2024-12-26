'use client';

import { useState, useEffect } from 'react';
import '../styles/globals.css';

export default function Home() {
  const [inputValue, setInputValue] = useState('');
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState(null);
  const [duration, setDuration] = useState(null);
  const [generations, setGenerations] = useState([]); // État pour stocker les générations

  const handleSubmit = async (e) => {
    e.preventDefault();
    setLoading(true);
    setError(null);
    setDuration(null);

    const startTime = Date.now();

    try {
      const response = await fetch('http://api-podcast.qwebty.local/generation', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
        },
        body: JSON.stringify({ input: inputValue }),
      });

      const data = await response.json();

      if (!response.ok) {
        throw new Error(data.error || 'Erreur inconnue');
      }

      const endTime = Date.now();
      const durationInSeconds = ((endTime - startTime) / 1000).toFixed(2);
      setDuration(durationInSeconds);

      // Recharger les dernières générations après la création du podcast
      fetchLastGenerations();

    } catch (err) {
      setError(err.message);
    } finally {
      setLoading(false);
    }
  };

  // Fonction pour récupérer les dernières générations
  const fetchLastGenerations = async () => {
    try {
      const response = await fetch('http://api-podcast.qwebty.local/listing');
      const data = await response.json();

      if (!response.ok) {
        throw new Error(data.error || 'Erreur inconnue');
      }

      // Vérifier la structure de la réponse et stocker les données correctement
      if (data.success && data.data) {
        setGenerations(data.data); // Mettre à jour les générations avec la liste des podcasts
      } else {
        setError('Aucune génération trouvée');
      }
    } catch (err) {
      setError(err.message);
    }
  };

  useEffect(() => {
    fetchLastGenerations(); // Appeler la fonction au chargement de la page
  }, []);

  return (
    <div className="container">
      <div className="form-container">
        <h1>Générer un podcast</h1>
        <form onSubmit={handleSubmit}>
          <input
            type="text"
            placeholder="Tapez ici..."
            value={inputValue}
            onChange={(e) => setInputValue(e.target.value)}
            className="input-field"
            required
          />
          <button type="submit" disabled={loading} className="submit-btn">
            {loading ? 'Envoi en cours...' : 'Générer'}
          </button>
        </form>

        {loading && <div className="loader"></div>}
        {error && <p className="error">{error}</p>}
        {duration && <p className="success">Temps d'appel API : {duration} secondes</p>}
      </div>

      {/* Section des dernières générations */}
      <div className="last-generations">
        <h2>Les 3 dernières générations</h2>
        {generations.length > 0 ? (
          <div className="generations-list">
            {generations.map((gen, index) => (
              <div key={index} className="generation-item">
                {/* Affichage de l'image si l'URL est disponible */}
                {gen.image_url && (
                  <img
                    src={'http://static-podcast.qwebty.local/images/'+gen.image_url}
                    alt={gen.title}
                    className="generation-image"
                  />
                )}
                <h3>{gen.title}</h3>
                {/* Affichage du lecteur audio si l'URL est disponible */}
                {gen.audio_url && (
                  <audio controls>
                    <source src={'http://static-podcast.qwebty.local/audios/'+gen.audio_url} type="audio/mp3" />
                    Votre navigateur ne supporte pas l'élément audio.
                  </audio>
                )}
              </div>
            ))}
          </div>
        ) : (
          <p>Aucune génération disponible pour le moment.</p>
        )}
      </div>
    </div>
  );
}
