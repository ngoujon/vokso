'use client';

import { useState } from 'react';
import '../styles/globals.css';  // Assurez-vous que ce chemin est correct

export default function Home() {
  const [inputValue, setInputValue] = useState('');
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState(null);
  const [duration, setDuration] = useState(null); // Variable pour stocker la durée de l'appel

  const handleSubmit = async (e) => {
    e.preventDefault();
    setLoading(true);
    setError(null);
    setDuration(null);

    const startTime = Date.now(); // Temps de départ pour mesurer la durée

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

      // Calcul du temps écoulé en secondes
      const endTime = Date.now();
      const durationInSeconds = ((endTime - startTime) / 1000).toFixed(2);
      setDuration(durationInSeconds); // Mise à jour de la durée

    } catch (err) {
      setError(err.message);
    } finally {
      setLoading(false);
    }
  };

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
        
        {loading && <div className="loader"></div>} {/* Loader circulaire pendant le chargement */}

        {error && <p className="error">{error}</p>}

        {duration && (
          <div>
            <p className="success">Temps d'appel API : {duration} secondes</p>
          </div>
        )}
      </div>
    </div>
  );
}
