import React, { useState } from 'react';
import { Link, useNavigate } from 'react-router-dom';
import { apiRequest, useAuth } from '../AuthContext';
import NavBar from '../components/NavBar';
import Footer from '../components/Footer';
import SovereigntySection from '../components/SovereigntySection';
import useCanonical from '../hooks/useCanonical';
import useDocumentMeta from '../hooks/useDocumentMeta';
import '../styles/globals.css';
import '../styles/Pricing.css';

const PLANS = [
  {
    name: 'Découverte',
    tagline: 'Pour essayer sans engagement',
    price: '0€',
    period: '/ mois',
    cta: 'Commencer gratuitement',
    features: [
      '5 podcasts générés par mois',
      'Texte, illustration et voix inclus',
      'Historique de 7 jours',
      'Hébergement européen',
    ],
  },
  {
    name: 'Créateur',
    planId: 'createur',
    tagline: "Pour publier régulièrement",
    price: '9,90€',
    period: '/ mois',
    cta: 'Choisir Créateur',
    featured: true,
    features: [
      'Podcasts illimités (usage raisonnable)',
      'Voix et illustrations en haute qualité',
      'Téléchargement de tous vos épisodes',
      'File de génération prioritaire',
      'Historique illimité',
    ],
  },
  {
    name: 'Studio',
    planId: 'studio',
    tagline: 'Pour les équipes et les usages pro',
    price: '29€',
    period: '/ mois',
    cta: 'Choisir Studio',
    features: [
      'Tout Créateur, plus :',
      'Accès API pour vos intégrations',
      'Plusieurs voix et langues',
      'Export multi-formats',
      'Support prioritaire',
    ],
  },
];

const FAQ = [
  {
    q: 'Mes données servent-elles à entraîner un modèle ?',
    a: "Non. Les sujets que vous soumettez et les podcasts générés restent liés à votre compte et ne sont jamais utilisés pour entraîner un quelconque modèle tiers.",
  },
  {
    q: 'Où sont hébergées mes générations ?',
    a: 'Sur une infrastructure basée en Europe, soumise au RGPD, y compris pour les traitements liés à la génération de contenu.',
  },
  {
    q: 'Puis-je changer de formule à tout moment ?',
    a: 'Oui, vous pouvez passer à une formule supérieure ou inférieure depuis votre espace, sans engagement de durée.',
  },
  {
    q: 'La formule Studio est-elle facturable à une entreprise ?',
    a: "Oui, une facture avec TVA est disponible pour les formules Créateur et Studio depuis votre espace.",
  },
];

export default function Pricing() {
  useCanonical();
  useDocumentMeta(
    'Tarifs — Vokso',
    'Découvrez les formules Vokso pour générer vos podcasts par IA, hébergés en Europe.'
  );
  const { user } = useAuth();
  const navigate = useNavigate();
  const [loadingPlan, setLoadingPlan] = useState(null);
  const [error, setError] = useState(null);

  const handleSubscribe = async (planId) => {
    if (!user) {
      navigate('/login');
      return;
    }
    setError(null);
    setLoadingPlan(planId);
    try {
      const data = await apiRequest('billing-checkout', {
        method: 'POST',
        body: JSON.stringify({ plan: planId }),
      });
      window.location.href = data.url;
    } catch (err) {
      setError(err.message);
      setLoadingPlan(null);
    }
  };

  return (
    <>
      <NavBar />
      <div className="container">
        <div className="hero">
          <span className="badge-sovereign"><i className="bi bi-shield-lock"></i> Hébergé en Europe</span>
          <h1>Des tarifs simples, sans surprise</h1>
          <p className="hero-subtitle">
            Générez vos podcasts en quelques secondes : texte, illustration et voix inclus dans chaque formule.
            Changez ou annulez à tout moment.
          </p>
        </div>

        {error && <p className="auth-error">{error}</p>}

        <div className="pricing-grid">
          {PLANS.map((plan) => (
            <div className={`pricing-card ${plan.featured ? 'featured' : ''}`} key={plan.name}>
              {plan.featured && <span className="pricing-card-badge">Le plus choisi</span>}
              <div className="pricing-card-name">{plan.name}</div>
              <div className="pricing-card-tagline">{plan.tagline}</div>
              <div className="pricing-card-price">
                <span className="amount">{plan.price}</span>
                <span className="period">{plan.period}</span>
              </div>
              <ul className="pricing-card-features">
                {plan.features.map((feature) => (
                  <li key={feature}><i className="bi bi-check-circle-fill"></i>{feature}</li>
                ))}
              </ul>
              {plan.planId ? (
                <button
                  type="button"
                  className={`btn ${plan.featured ? 'btn-primary' : 'btn-outline'}`}
                  onClick={() => handleSubscribe(plan.planId)}
                  disabled={loadingPlan === plan.planId}
                >
                  {loadingPlan === plan.planId ? 'Redirection…' : plan.cta}
                </button>
              ) : (
                <Link to="/login" className={`btn ${plan.featured ? 'btn-primary' : 'btn-outline'}`}>
                  {plan.cta}
                </Link>
              )}
            </div>
          ))}
        </div>

        <SovereigntySection />

        <div className="pricing-faq">
          <h2>Questions fréquentes</h2>
          {FAQ.map((item) => (
            <div className="pricing-faq-item" key={item.q}>
              <h3>{item.q}</h3>
              <p>{item.a}</p>
            </div>
          ))}
        </div>
      </div>
      <Footer />
    </>
  );
}
