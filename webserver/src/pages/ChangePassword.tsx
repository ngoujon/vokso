import React, { useState } from 'react';
import { errorMessage } from '../lib/api';
import { useAuth } from '../lib/AuthContext';

/**
 * Changement de mot de passe : imposé à la première connexion d'un compte au
 * mot de passe temporaire, et disponible à tout moment dans l'onglet
 * « Mot de passe & 2FA ».
 */
export default function ChangePassword() {
  const { user, changePassword } = useAuth();
  const [currentPassword, setCurrentPassword] = useState('');
  const [newPassword, setNewPassword] = useState('');
  const [confirmation, setConfirmation] = useState('');
  const [error, setError] = useState<string | null>(null);
  const [info, setInfo] = useState<string | null>(null);
  const [loading, setLoading] = useState(false);

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    setError(null);
    setInfo(null);
    if (newPassword !== confirmation) {
      setError('Les deux saisies du nouveau mot de passe diffèrent.');
      return;
    }
    setLoading(true);
    try {
      await changePassword(currentPassword, newPassword);
      setCurrentPassword('');
      setNewPassword('');
      setConfirmation('');
      setInfo('Mot de passe changé.');
    } catch (err) {
      setError(errorMessage(err));
    } finally {
      setLoading(false);
    }
  };

  return (
    <section>
      <h2>Changer le mot de passe</h2>
      {user?.must_change_password && (
        <p>Ce compte utilise un mot de passe temporaire : vous devez le changer avant de continuer.</p>
      )}
      <form onSubmit={handleSubmit} className="auth-form password-form">
        <label>
          Mot de passe actuel
          <input
            type="password"
            autoComplete="current-password"
            value={currentPassword}
            onChange={(e) => setCurrentPassword(e.target.value)}
            required
          />
        </label>
        <label>
          Nouveau mot de passe
          <input
            type="password"
            autoComplete="new-password"
            value={newPassword}
            onChange={(e) => setNewPassword(e.target.value)}
            minLength={12}
            required
          />
          <small>12 caractères minimum, au moins une lettre et un chiffre.</small>
        </label>
        <label>
          Confirmer le nouveau mot de passe
          <input
            type="password"
            autoComplete="new-password"
            value={confirmation}
            onChange={(e) => setConfirmation(e.target.value)}
            minLength={12}
            required
          />
        </label>
        {error && <p className="auth-error">{error}</p>}
        {info && <p role="status">{info}</p>}
        <button type="submit" disabled={loading}>
          {loading ? 'Chargement...' : 'Changer le mot de passe'}
        </button>
      </form>
    </section>
  );
}
