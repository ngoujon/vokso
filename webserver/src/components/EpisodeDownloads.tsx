import React from 'react';
import { downloadText, staticFileUrl, triggerDownload } from '../lib/api';
import { cleanTitle } from '../lib/text';
import type { PodcastWithCosts } from '../lib/types';

/**
 * Boutons de téléchargement des trois fichiers d'un épisode (image, audio,
 * narration). « compact » raccourcit les libellés pour les tableaux.
 */
const LABELS = {
  full: { image: "Télécharger l'image", audio: "Télécharger l'audio", text: 'Télécharger le texte' },
  compact: { image: 'Image', audio: 'Audio', text: 'Texte' },
};

export default function EpisodeDownloads({ episode, compact = false }: { episode: PodcastWithCosts; compact?: boolean }) {
  const label = LABELS[compact ? 'compact' : 'full'];

  return (
    <>
      <button onClick={() => triggerDownload(staticFileUrl('images', episode.image_url), episode.image_url, true)}>
        {label.image}
      </button>
      <button onClick={() => triggerDownload(staticFileUrl('audios', episode.audio_url), episode.audio_url, true)}>
        {label.audio}
      </button>
      <button onClick={() => downloadText(cleanTitle(episode.title), episode.description)}>{label.text}</button>
    </>
  );
}
