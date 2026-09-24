import React, { useState } from 'react';
import { useNavigate, Link } from 'react-router-dom';
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
    'Connectez-vous ou créez un compte Vokso pour générer et gérer vos épisodes.'
  );
  const [email, setEmail] = useState('');
  const [password, setPassword] = useState('');
  const [totpCode, setTotpCode] = useState('');
  const [totpRequired, setTotpRequired] = useState(false);
  const [website, setWebsite] = useState('');
  const [mode, setMode] = useState('login'); // 'login' | 'register'
  const [error, setError] = useState(null);
  const [loading, setLoading] = useState(false);
  const { login, register } = useAuth();
  const navigate = useNavigate();

  const handleSubmit = async (e) => {
    e.preventDefault();
    setError(null);
    setLoading(true);
    try {
      const user = mode === 'login' ? await login(email, password, totpCode) : await register(email, password, website);
      if (user.must_change_password) {
        navigate('/changer-mot-de-passe');
        return;
      }
      navigate(user.role === 'admin' ? '/admin' : '/dashboard');
    } catch (err) {
      if (err.code === 'totp_required') {
        setTotpRequired(true);
        setError(totpCode ? 'Code invalide, réessayez.' : 'Entrez le code de votre application d\'authentification.');
      } else {
        setError(err.message);
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
          <h1>{mode === 'login' ? 'Connexion' : 'Créer un compte'}</h1>
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
                minLength={mode === 'register' ? 12 : 8}
                required
              />
              {mode === 'register' && <small>12 caractères minimum, au moins une lettre et un chiffre.</small>}
            </label>
            {mode === 'login' && totpRequired && (
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
            {mode === 'register' && (
              // Piège à robots : masqué visuellement et aux lecteurs d'écran, jamais rempli par un humain.
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
            )}
            {error && <p className="auth-error">{error}</p>}
            <button type="submit" disabled={loading}>
              {loading ? 'Chargement...' : mode === 'login' ? 'Se connecter' : "S'inscrire"}
            </button>
          </form>
          <button className="auth-switch" onClick={() => setMode(mode === 'login' ? 'register' : 'login')}>
            {mode === 'login' ? 'Pas encore de compte ? Inscrivez-vous' : 'Déjà un compte ? Connectez-vous'}
          </button>
          <Link to="/" className="auth-back">← Retour à l'accueil</Link>
        </div>
      </div>
      <Footer />
    </>
  );
}
