import React, { useState } from 'react';
import { errorMessage } from '../api';
import { useAuth } from '../AuthContext';

export default function SecuritySection() {
  const { user, setup2fa, enable2fa, disable2fa } = useAuth();
  const [setupData, setSetupData] = useState<{ secret: string } | null>(null);
  const [code, setCode] = useState('');
  const [disablePassword, setDisablePassword] = useState('');
  const [showDisable, setShowDisable] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [info, setInfo] = useState<string | null>(null);
  const [loading, setLoading] = useState(false);

  const startSetup = async () => {
    setError(null);
    setInfo(null);
    setLoading(true);
    try {
      const data = await setup2fa();
      setSetupData(data);
    } catch (err) {
      setError(errorMessage(err));
    } finally {
      setLoading(false);
    }
  };

  const confirmEnable = async (e: React.FormEvent) => {
    e.preventDefault();
    setError(null);
    setLoading(true);
    try {
      await enable2fa(code);
      setSetupData(null);
      setCode('');
      setInfo('Double authentification activée.');
    } catch (err) {
      setError(errorMessage(err));
    } finally {
      setLoading(false);
    }
  };

  const confirmDisable = async (e: React.FormEvent) => {
    e.preventDefault();
    setError(null);
    setLoading(true);
    try {
      await disable2fa(disablePassword);
      setShowDisable(false);
      setDisablePassword('');
      setInfo('Double authentification désactivée.');
    } catch (err) {
      setError(errorMessage(err));
    } finally {
      setLoading(false);
    }
  };

  return (
    <section>
      <h2>Sécurité du compte</h2>
      {error && <p className="auth-error">{error}</p>}
      {info && <p>{info}</p>}

      <p>
        Double authentification (2FA) : <strong>{user?.totp_enabled ? 'activée' : 'désactivée'}</strong>
      </p>

      {!user?.totp_enabled && !setupData && (
        <button onClick={startSetup} disabled={loading}>Activer la 2FA</button>
      )}

      {setupData && (
        <form onSubmit={confirmEnable} className="inline-form">
          <p>
            Ajoutez ce compte dans votre application d'authentification (Google Authenticator, Authy...)
            en saisissant manuellement ce code : <code>{setupData.secret}</code>
          </p>
          <input
            type="text"
            inputMode="numeric"
            pattern="[0-9]{6}"
            maxLength={6}
            placeholder="Code à 6 chiffres"
            value={code}
            onChange={(e) => setCode(e.target.value)}
            required
          />
          <button type="submit" disabled={loading}>Confirmer l'activation</button>
        </form>
      )}

      {user?.totp_enabled && !showDisable && (
        <button onClick={() => setShowDisable(true)}>Désactiver la 2FA</button>
      )}

      {user?.totp_enabled && showDisable && (
        <form onSubmit={confirmDisable} className="inline-form">
          <input
            type="password"
            placeholder="Mot de passe actuel"
            value={disablePassword}
            onChange={(e) => setDisablePassword(e.target.value)}
            required
          />
          <button type="submit" disabled={loading}>Confirmer la désactivation</button>
        </form>
      )}
    </section>
  );
}
