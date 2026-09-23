import React, { useEffect, useState } from 'react';
import { Link } from 'react-router-dom';
import NavBar from '../components/NavBar';
import Footer from '../components/Footer';
import useCanonical from '../hooks/useCanonical';
import useDocumentMeta from '../hooks/useDocumentMeta';
import { config } from '../config';
import '../styles/globals.css';
import '../styles/Dashboard.css';

export default function Contact() {
  useCanonical();
  useDocumentMeta(
    'Contact — Vokso',
    'Une question sur Vokso ? Écrivez-nous via ce formulaire, nous vous répondons rapidement.'
  );

  const [name, setName] = useState('');
  const [email, setEmail] = useState('');
  const [message, setMessage] = useState('');
  // Champ piège invisible : un humain ne le voit ni ne le remplit, un robot
  // qui soumet tous les champs du formulaire si. Voir ContactController.
  const [website, setWebsite] = useState('');
  const [captchaToken, setCaptchaToken] = useState(null);
  const [error, setError] = useState(null);
  const [success, setSuccess] = useState(false);
  const [loading, setLoading] = useState(false);

  useEffect(() => {
    fetch(`${config.apiUrl}/contact-challenge`)
      .then((res) => res.json())
      .then((data) => setCaptchaToken(data.token))
      .catch(() => setCaptchaToken(null));
  }, []);

  const handleSubmit = async (e) => {
    e.preventDefault();
    setError(null);
    setLoading(true);
    try {
      const response = await fetch(`${config.apiUrl}/contact`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ name, email, message, website, captchaToken }),
      });
      const data = await response.json().catch(() => ({}));
      if (!response.ok) {
        throw new Error(data.error || 'Une erreur est survenue');
      }
      setSuccess(true);
      setName('');
      setEmail('');
      setMessage('');
    } catch (err) {
      setError(err.message);
    } finally {
      setLoading(false);
    }
  };

  return (
    <>
      <NavBar />
      <div className="container">
        <div className="auth-card">
          <h1>Contact</h1>
          {success ? (
            <p>Merci, votre message a bien été envoyé. Nous vous répondons rapidement.</p>
          ) : (
            <form onSubmit={handleSubmit} className="auth-form">
              <label>
                Nom
                <input type="text" value={name} onChange={(e) => setName(e.target.value)} maxLength={200} required />
              </label>
              <label>
                Email
                <input type="email" value={email} onChange={(e) => setEmail(e.target.value)} required />
              </label>
              <label>
                Message
                <textarea
                  value={message}
                  onChange={(e) => setMessage(e.target.value)}
                  maxLength={5000}
                  rows={6}
                  required
                />
              </label>
              {/* Piège à robots : masqué visuellement et aux lecteurs d'écran, jamais rempli par un humain. */}
              <div aria-hidden="true" style={{ position: 'absolute', left: '-9999px', top: 'auto', width: '1px', height: '1px', overflow: 'hidden' }}>
                <label htmlFor="website">Site web</label>
                <input
                  id="website"
                  type="text"
                  tabIndex={-1}
                  autoComplete="off"
                  value={website}
                  onChange={(e) => setWebsite(e.target.value)}
                />
              </div>
              {error && <p className="auth-error">{error}</p>}
              <button type="submit" disabled={loading}>
                {loading ? 'Envoi...' : 'Envoyer'}
              </button>
            </form>
          )}
          <Link to="/" className="auth-back">← Retour à l'accueil</Link>
        </div>
      </div>
      <Footer />
    </>
  );
}
