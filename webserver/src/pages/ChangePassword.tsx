import React, { useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { errorMessage } from '../api';
import { useAuth } from '../AuthContext';
import NavBar from '../components/NavBar';
import Footer from '../components/Footer';
import useCanonical from '../hooks/useCanonical';
import useDocumentMeta from '../hooks/useDocumentMeta';
import '../styles/globals.css';
import '../styles/Dashboard.css';

export default function ChangePassword() {
  useCanonical();
  useDocumentMeta('Changer le mot de passe — Vokso', 'Changez votre mot de passe Vokso.');
  const { user, changePassword } = useAuth();
  const [currentPassword, setCurrentPassword] = useState('');
  const [newPassword, setNewPassword] = useState('');
  const [error, setError] = useState<string | null>(null);
  const [loading, setLoading] = useState(false);
  const navigate = useNavigate();

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    setError(null);
    setLoading(true);
    try {
      await changePassword(currentPassword, newPassword);
      navigate(user?.role === 'admin' ? '/admin' : '/dashboard');
    } catch (err) {
      setError(errorMessage(err));
    } finally {
      setLoading(false);
    }
  };

  return (
    <>
      <NavBar />
      <div className="container">
        <div className="auth-card">
          <h1>Changer le mot de passe</h1>
          {user?.must_change_password && (
            <p>Ce compte utilise un mot de passe temporaire : vous devez le changer avant de continuer.</p>
          )}
          <form onSubmit={handleSubmit} className="auth-form">
            <label>
              Mot de passe actuel
              <input
                type="password"
                value={currentPassword}
                onChange={(e) => setCurrentPassword(e.target.value)}
                required
              />
            </label>
            <label>
              Nouveau mot de passe
              <input
                type="password"
                value={newPassword}
                onChange={(e) => setNewPassword(e.target.value)}
                minLength={12}
                required
              />
              <small>12 caractères minimum, au moins une lettre et un chiffre.</small>
            </label>
            {error && <p className="auth-error">{error}</p>}
            <button type="submit" disabled={loading}>
              {loading ? 'Chargement...' : 'Changer le mot de passe'}
            </button>
          </form>
        </div>
      </div>
      <Footer />
    </>
  );
}
