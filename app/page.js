'use client';

import { useState } from 'react';
import '../styles/globals.css';  // Assurez-vous que ce chemin est correct

export default function Home() {
  const [inputValue, setInputValue] = useState('');
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState(null);
  const [generatedText, setGeneratedText] = useState('');
  const [fileName, setFileName] = useState('');

  const handleSubmit = async (e) => {
    e.preventDefault();
    setLoading(true);
    setError(null);
    setGeneratedText('');
    setFileName('');

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

      // Si tout s'est bien passé
      setGeneratedText(data.generated_text);
      setFileName(data.file); // Nom du fichier généré
    } catch (err) {
      setError(err.message);
    } finally {
      setLoading(false);
    }
  };

  return (
    <div className="container">
      <div className="form-container">
        <h1>Générer du texte avec OpenAI</h1>
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
        {error && <p className="error">{error}</p>}
        {generatedText && (
          <div>
            <p className="success">Texte généré :</p>
            <pre>{generatedText}</pre>
            <p>Le fichier a été enregistré sous : {fileName}</p>
          </div>
        )}
      </div>
    </div>
  );
}
