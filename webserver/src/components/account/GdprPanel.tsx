import React, { useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { apiRequest, downloadAuthenticated, errorMessage } from '../../api';
import { useAuth } from '../../AuthContext';

/** Droits RGPD exerçables depuis l'espace compte : accès/portabilité et effacement. */
export default function GdprPanel() {
  const { logout } = useAuth();
  const navigate = useNavigate();
  const [error, setError] = useState<string | null>(null);
  const [confirming, setConfirming] = useState(false);
  const [password, setPassword] = useState('');
  const [deleting, setDeleting] = useState(false);

  const exportData = async () => {
    setError(null);
    try {
      await downloadAuthenticated('gdpr-export', 'vokso-donnees-personnelles.json');
    } catch (err) {
      setError(errorMessage(err, 'Export impossible.'));
    }
  };

  const handleDelete = async (e: React.FormEvent) => {
    e.preventDefault();
    setError(null);
    setDeleting(true);
    try {
      await apiRequest('gdpr-delete-account', { method: 'POST', body: JSON.stringify({ password }) });
      await logout();
      navigate('/');
    } catch (err) {
      setError(errorMessage(err, 'La suppression a échoué.'));
    } finally {
      setDeleting(false);
    }
  };

  return (
    <div>
      <p>
        Conformément au RGPD, vous pouvez récupérer une copie de toutes vos données personnelles
        (profil, podcasts générés) ou demander la suppression de votre compte.
      </p>
      <button onClick={exportData}>Exporter mes données</button>

      {!confirming && (
        <button className="btn-danger" onClick={() => setConfirming(true)} style={{ marginLeft: '0.5rem' }}>
          Supprimer mon compte
        </button>
      )}

      {confirming && (
        <form onSubmit={handleDelete} className="auth-form">
          <p>
            La suppression efface définitivement votre compte. Les podcasts déjà publiés restent en
            ligne mais ne sont plus rattachés à votre identité. Cette action est irréversible.
          </p>
          <label>
            Confirmez avec votre mot de passe
            <input type="password" value={password} onChange={(e) => setPassword(e.target.value)} required />
          </label>
          {error && <p className="auth-error">{error}</p>}
          <button type="submit" className="btn-danger" disabled={deleting}>
            {deleting ? 'Suppression...' : 'Confirmer la suppression définitive'}
          </button>
          <button type="button" onClick={() => setConfirming(false)}>
            Annuler
          </button>
        </form>
      )}

      {error && !confirming && <p className="auth-error">{error}</p>}
    </div>
  );
}
