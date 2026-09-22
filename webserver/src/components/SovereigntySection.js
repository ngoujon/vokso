import React from 'react';

const POINTS = [
  {
    icon: 'bi-geo-alt',
    title: 'Hébergé en Europe',
    text: "Vos textes, images et audios sont générés et stockés sur une infrastructure basée dans l'Union européenne, soumise au droit européen.",
  },
  {
    icon: 'bi-eye-slash',
    title: 'Vos contenus restent les vôtres',
    text: "Ce que vous créez ne sert jamais à entraîner un modèle tiers et n'est jamais revendu ou partagé à des fins publicitaires.",
  },
  {
    icon: 'bi-shield-check',
    title: 'Conformité RGPD par construction',
    text: 'Chiffrement des mots de passe, minimisation des données collectées et droit à l\'effacement intégrés dès la conception.',
  },
  {
    icon: 'bi-diagram-3',
    title: 'Indépendance technologique',
    text: "Nous choisissons des briques ouvertes et un hébergement européen plutôt qu'une dépendance exclusive aux grands clouds non-européens.",
  },
];

export default function SovereigntySection() {
  return (
    <section className="sovereignty-section">
      <div className="sovereignty-header">
        <span className="badge-sovereign"><i className="bi bi-shield-lock"></i> Souverain par conception</span>
        <h2>Une IA de confiance, hébergée en Europe</h2>
        <p>
          QwaiPod est pensé pour rester indépendant des grands acteurs non-européens du cloud et de l'IA :
          hébergement européen, modèles ouverts et aucune revente de vos données.
        </p>
      </div>
      <div className="sovereignty-grid">
        {POINTS.map((point) => (
          <div className="sovereignty-card" key={point.title}>
            <div className="sovereignty-card-icon">
              <i className={`bi ${point.icon}`}></i>
            </div>
            <h3>{point.title}</h3>
            <p>{point.text}</p>
          </div>
        ))}
      </div>
    </section>
  );
}
