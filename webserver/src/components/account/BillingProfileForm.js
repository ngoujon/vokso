import React, { useEffect, useState } from 'react';
import { apiRequest } from '../../AuthContext';

const EMPTY_PROFILE = {
  client_type: 'particulier',
  full_name: '',
  company_name: '',
  siret: '',
  vat_number: '',
  address_line1: '',
  address_line2: '',
  postal_code: '',
  city: '',
  country_code: 'FR',
};

/**
 * Coordonnées de facturation nécessaires à l'émission d'une facture
 * Factur-X conforme : obligatoires avant tout paiement si l'utilisateur veut
 * une facture correcte (raison sociale/SIRET pour un compte "pro").
 */
export default function BillingProfileForm() {
  const [profile, setProfile] = useState(EMPTY_PROFILE);
  const [loading, setLoading] = useState(true);
  const [saving, setSaving] = useState(false);
  const [error, setError] = useState(null);
  const [success, setSuccess] = useState(false);

  useEffect(() => {
    apiRequest('billing-profile')
      .then((data) => setProfile({ ...EMPTY_PROFILE, ...data.profile }))
      .catch((e) => setError(e.message))
      .finally(() => setLoading(false));
  }, []);

  const update = (field) => (e) => {
    setSuccess(false);
    setProfile((prev) => ({ ...prev, [field]: e.target.value }));
  };

  const handleSubmit = async (e) => {
    e.preventDefault();
    setError(null);
    setSuccess(false);
    setSaving(true);
    try {
      await apiRequest('billing-profile-update', { method: 'POST', body: JSON.stringify(profile) });
      setSuccess(true);
    } catch (err) {
      setError(err.message);
    } finally {
      setSaving(false);
    }
  };

  if (loading) {
    return <p>Chargement de vos informations de facturation...</p>;
  }

  const isBusiness = profile.client_type === 'pro';

  return (
    <form onSubmit={handleSubmit} className="auth-form">
      <label>
        Type de client
        <select value={profile.client_type} onChange={update('client_type')}>
          <option value="particulier">Particulier</option>
          <option value="pro">Professionnel</option>
        </select>
      </label>

      <label>
        {isBusiness ? 'Nom du contact' : 'Nom complet'}
        <input type="text" value={profile.full_name} onChange={update('full_name')} required />
      </label>

      {isBusiness && (
        <>
          <label>
            Raison sociale
            <input type="text" value={profile.company_name} onChange={update('company_name')} required />
          </label>
          <label>
            SIRET
            <input
              type="text"
              value={profile.siret}
              onChange={update('siret')}
              placeholder="14 chiffres"
              required
            />
          </label>
          <label>
            N° de TVA intracommunautaire (facultatif)
            <input
              type="text"
              value={profile.vat_number}
              onChange={update('vat_number')}
              placeholder="FR12345678901"
            />
            <small>Laisser vide si vous êtes en franchise en base de TVA.</small>
          </label>
        </>
      )}

      <label>
        Adresse
        <input type="text" value={profile.address_line1} onChange={update('address_line1')} required />
      </label>
      <label>
        Complément d'adresse (facultatif)
        <input type="text" value={profile.address_line2 || ''} onChange={update('address_line2')} />
      </label>
      <label>
        Code postal
        <input type="text" value={profile.postal_code} onChange={update('postal_code')} required />
      </label>
      <label>
        Ville
        <input type="text" value={profile.city} onChange={update('city')} required />
      </label>
      <label>
        Pays (code ISO à 2 lettres)
        <input
          type="text"
          value={profile.country_code}
          onChange={update('country_code')}
          maxLength={2}
          required
        />
      </label>

      {error && <p className="auth-error">{error}</p>}
      {success && <p>Informations de facturation enregistrées.</p>}
      <button type="submit" disabled={saving}>
        {saving ? 'Enregistrement...' : 'Enregistrer'}
      </button>
    </form>
  );
}
