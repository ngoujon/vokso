import React, { useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { ApiError, errorMessage } from '../api';
import { useAuth } from '../AuthContext';
import NavBar from '../components/NavBar';
import Footer from '../components/Footer';
import useCanonical from '../hooks/useCanonical';
import useDocumentMeta from '../hooks/useDocumentMeta';
import '../styles/globals.css';
import '../styles/Dashboard.css';

export default function Login() {
  useCanonical();
  useDocumentMeta(
    'Connexion — Vokso',
    'Connexion à l\'espace d\'administration de Vokso.'
  );
  const [email, setEmail] = useState('');
  const [password, setPassword] = useState('');
  const [totpCode, setTotpCode] = useState('');
  const [totpRequired, setTotpRequired] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [loading, setLoading] = useState(false);
  const { login } = useAuth();
  const navigate = useNavigate();

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    setError(null);
    setLoading(true);
    try {
      const user = await login(email, password, totpCode);
      if (user.must_change_password) {
        navigate('/changer-mot-de-passe');
        return;
      }
      navigate(user.role === 'admin' ? '/admin' : '/dashboard');
    } catch (err) {
      if (err instanceof ApiError && err.code === 'totp_required') {
        setTotpRequired(true);
        setError(totpCode ? 'Code invalide, réessayez.' : "Entrez le code de votre application d'authentification.");
      } else {
        setError(errorMessage(err));
      }
    } finally {
      setLoading(false);
    }
  };

  return (
    <>
      <NavBar />
      <div className="container">
        <div className="auth-card">
          <h1>Connexion</h1>
          <form onSubmit={handleSubmit} className="auth-form">
            <label>
              Email
              <input type="email" value={email} onChange={(e) => setEmail(e.target.value)} required />
            </label>
            <label>
              Mot de passe
              <input
                type="password"
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
      </div>
      <Footer />
    </>
  );
}
