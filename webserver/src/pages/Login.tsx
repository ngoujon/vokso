import React, { useState } from 'react';
import { ApiError, errorMessage } from '../lib/api';
import { useAuth } from '../lib/AuthContext';

/**
 * Connexion à l'administration (e-mail / mot de passe, puis code TOTP si la
 * double authentification est active). Une fois connecté, AdminApp affiche
 * directement le tableau de bord : aucune redirection.
 */
export default function Login() {
  const [email, setEmail] = useState('');
  const [password, setPassword] = useState('');
  const [totpCode, setTotpCode] = useState('');
  const [totpRequired, setTotpRequired] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [loading, setLoading] = useState(false);
  const { login } = useAuth();

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    setError(null);
    setLoading(true);
    try {
      await login(email, password, totpCode);
    } catch (err) {
      if (err instanceof ApiError && err.code === 'totp_required') {
        setTotpRequired(true);
        setError(totpCode ? 'Code invalide, réessayez.' : "Entrez le code de votre application d'authentification.");
      } else {
        setError(errorMessage(err));
      }
      setLoading(false);
    }
  };

  return (
    <div className="auth-card">
      <h1>Administration</h1>
      <form onSubmit={handleSubmit} className="auth-form">
        <label>
          Email
          <input type="email" autoComplete="username" value={email} onChange={(e) => setEmail(e.target.value)} required />
        </label>
        <label>
          Mot de passe
          <input
            type="password"
            autoComplete="current-password"
            value={password}
            onChange={(e) => setPassword(e.target.value)}
            minLength={8}
            required
          />
        </label>
        {totpRequired && (
          <label>
            Code de double authentification
            <input
              type="text"
              inputMode="numeric"
              autoComplete="one-time-code"
              pattern="[0-9]{6}"
              maxLength={6}
              autoFocus
              value={totpCode}
              onChange={(e) => setTotpCode(e.target.value)}
              required
            />
          </label>
        )}
        {error && <p className="auth-error">{error}</p>}
        <button type="submit" disabled={loading}>
          {loading ? 'Chargement...' : 'Se connecter'}
        </button>
      </form>
      <a href="/" className="auth-back">← Retour à l'accueil</a>
    </div>
  );
}
