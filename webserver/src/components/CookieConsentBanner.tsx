import React, { useState } from 'react';
import { Link } from 'react-router-dom';
import { ConsentStatus, getConsentStatus, setConsentStatus } from '../consent/cookieConsent';
import '../styles/CookieConsentBanner.css';

export default function CookieConsentBanner() {
  const [status, setStatus] = useState(() => getConsentStatus());

  if (status) {
    return null;
  }

  const choose = (value: ConsentStatus) => {
    setConsentStatus(value);
    setStatus(value);
  };

  return (
    <div className="cookie-banner" role="dialog" aria-live="polite" aria-label="Consentement aux cookies">
      <p className="cookie-banner__text">
        Vokso n'utilise aucun cookie de mesure d'audience ou de publicité pour l'instant.
        Si ce type d'outil est ajouté un jour, il ne sera activé qu'avec votre accord.
        En savoir plus : <Link to="/politique-de-confidentialite">politique de confidentialité</Link>.
      </p>
      <div className="cookie-banner__actions">
        <button type="button" className="cookie-banner__btn cookie-banner__btn--secondary" onClick={() => choose('refused')}>
          Refuser
        </button>
        <button type="button" className="cookie-banner__btn cookie-banner__btn--primary" onClick={() => choose('accepted')}>
          Accepter
        </button>
      </div>
    </div>
  );
}
