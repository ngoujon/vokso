import React, { useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { useAuth } from '../../AuthContext';
import { config } from '../../config';

async function downloadPersonalData(setError) {
  const token = localStorage.getItem('auth_token');
  try {
    const response = await fetch(`${config.apiUrl}/gdpr-export`, {
      headers: token ? { Authorization: `Bearer ${token}` } : {},
    });
    if (!response.ok) {
      const data = await response.json().catch(() => ({}));
      throw new Error(data.error || 'Export impossible.');
    }
    const blob = await response.blob();
    const url = URL.createObjectURL(blob);
    const link = document.createElement('a');
    link.href = url;
    link.download = 'vokso-donnees-personnelles.json';
    document.body.appendChild(link);
    link.click();
    link.remove();
    URL.revokeObjectURL(url);
  } catch (e) {
    setError(e.message);
  }
}

/** Droits RGPD exerçables directement depuis l'espace compte : accès/portabilité et effacement. */
export default function GdprPanel() {
  const { logout } = useAuth();
  const navigate = useNavigate();
  const [error, setError] = useState(null);
  const [confirming, setConfirming] = useState(false);
  const [password, setPassword] = useState('');
  const [deleting, setDeleting] = useState(false);
  const token = localStorage.getItem('auth_token');

  const handleDelete = async (e) => {
    e.preventDefault();
    setError(null);
    setDeleting(true);
    try {
      const response = await fetch(`${config.apiUrl}/gdpr-delete-account`, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          ...(token ? { Authorization: `Bearer ${token}` } : {}),
        },
        body: JSON.stringify({ password }),
      });
      const data = await response.json().catch(() => ({}));
      if (!response.ok) {
        throw new Error(data.error || 'La suppression a échoué.');
      }
      await logout();
      navigate('/');
    } catch (err) {
      setError(err.message);
    } finally {
      setDeleting(false);
    }
  };

  return (
    <div>
      <p>
        Conformément au RGPD, vous pouvez récupérer une copie de toutes vos données personnelles
        (profil, factures éventuelles, podcasts générés) ou demander la suppression de votre compte.
      </p>
      <button onClick={() => downloadPersonalData(setError)}>Exporter mes données</button>

      {!confirming && (
        <button className="btn-danger" onClick={() => setConfirming(true)} style={{ marginLeft: '0.5rem' }}>
          Supprimer mon compte
        </button>
      )}

      {confirming && (
        <form onSubmit={handleDelete} className="auth-form">
          <p>
            La suppression désactive votre compte et anonymise vos coordonnées. Vos factures déjà
            émises sont conservées 10 ans, comme la loi l'exige, mais ne sont plus rattachées à votre
            identité. Cette action est irréversible.
          </p>
          <label>
            Confirmez avec votre mot de passe
            <input
              type="password"
              value={password}
              onChange={(e) => setPassword(e.target.value)}
              required
            />
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
