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
        Vokso n'utilise aucun cookie publicitaire. La mesure d'audience (SEO Monitor, auto-hébergé, sans tiers)
        est exemptée de consentement : elle reste active quel que soit votre choix, et vous pouvez vous y opposer
        dans la <Link to="/politique-de-confidentialite">politique de confidentialité</Link>. Tout autre outil
        soumis à consentement ne serait activé qu'avec votre accord.
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
