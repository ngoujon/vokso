import React, { useState, useEffect, useRef } from 'react';
import { Link } from 'react-router-dom';
import { apiRequest, errorMessage } from '../api';
import { useAuth } from '../AuthContext';
import '../styles/globals.css';
import '../styles/Dashboard.css';
import { config } from '../config';
import NavBar from '../components/NavBar';
import Footer from '../components/Footer';
import SovereigntySection from '../components/SovereigntySection';
import { ReactComponent as VoksoMark } from '../assets/vokso-mark.svg';
import useCanonical from '../hooks/useCanonical';
import useDocumentMeta from '../hooks/useDocumentMeta';
import useJsonLd from '../hooks/useJsonLd';
import type { Category, Episode, JobStatus, JobStep } from '../types';
import { cleanTitle, episodePath } from '../utils/text';

// Vérification des variables d'environnement
if (!config.apiUrl || !config.staticUrl) {
  throw new Error("Les variables d'environnement REACT_APP_API_URL et REACT_APP_STATIC_URL doivent être définies");
}

const STEP_LABELS: Record<JobStep, string> = {
  queued: "En file d'attente...",
  transcription: "Transcription de l'audio...",
  text: "Génération du texte...",
  media: "Création de l'illustration et de la voix...",
  finalizing: "Finalisation...",
  done: "Terminé",
};

interface ListingResponse {
  success: boolean;
  data?: Episode[];
  error?: string;
}

export default function Home() {
  useCanonical();
  useDocumentMeta(
    'Vokso — Générez et écoutez des podcasts',
    "Vokso permet de générer et d'écouter des épisodes de podcast à partir d'un sujet ou d'un texte."
  );
  const { user, loading: authLoading } = useAuth();
  const [subject, setSubject] = useState("");
  const [searchQuery, setSearchQuery] = useState("");
  const [sourceMode, setSourceMode] = useState<"text" | "audio">("text");
  const [audioFile, setAudioFile] = useState<File | null>(null);
  const [isRecording, setIsRecording] = useState(false);
  const [recordingError, setRecordingError] = useState<string | null>(null);
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [duration, setDuration] = useState<string | null>(null);
  const [jobProgress, setJobProgress] = useState<{ step: JobStep; progress: number } | null>(null);
  const [generations, setGenerations] = useState<Episode[]>([]);
  const [categories, setCategories] = useState<Category[]>([]);
  const [selectedCategory, setSelectedCategory] = useState('');
  const [audioPlayingIndex, setAudioPlayingIndex] = useState<number | null>(null);
  const [currentTimes, setCurrentTimes] = useState<number[]>([]);
  const [totalDurations, setTotalDurations] = useState<number[]>([]);
  const [isSearching, setIsSearching] = useState(false);
  const [expandedTextIndex, setExpandedTextIndex] = useState<number | null>(null);
  
  // Références pour le cache et le debounce
  const cacheRef = useRef(new Map<string, { data: Episode[]; timestamp: number }>());
  const lastFetchRef = useRef<string | null>(null);
  const debounceTimerRef = useRef<ReturnType<typeof setTimeout> | null>(null);
  const pollTimerRef = useRef<ReturnType<typeof setInterval> | null>(null);
  const audioRefs = useRef<Record<number, HTMLAudioElement | null>>({});
  const progressRefs = useRef<Record<number, HTMLDivElement | null>>({});
  const mediaRecorderRef = useRef<MediaRecorder | null>(null);
  const recordedChunksRef = useRef<Blob[]>([]);

  const fetchGenerations = async (url: string, forceRefresh = false) => {
    try {
      // Vérifier si on a des données en cache et si on ne force pas le rafraîchissement
      if (!forceRefresh && cacheRef.current.has(url)) {
        const cachedData = cacheRef.current.get(url)!;
        const cacheAge = Date.now() - cachedData.timestamp;
        
        // Utiliser le cache si moins de 30 secondes se sont écoulées
        if (cacheAge < 30000) {
          setGenerations(cachedData.data);
          setCurrentTimes(new Array(cachedData.data.length).fill(0));
          return;
        }
      }

      // Éviter les appels simultanés à la même URL
      if (lastFetchRef.current === url) {
        return;
      }
      lastFetchRef.current = url;

      const response = await fetch(url);
      const data: ListingResponse = await response.json();

      if (!response.ok) {
        throw new Error(data.error || "Erreur inconnue");
      }

      if (data.success && data.data) {
        // Un épisode sans audio n'est pas jouable : on ne l'affiche pas.
        const validGenerations = data.data.filter((gen) => Boolean(gen.audio_url));

        // Mettre en cache les données
        cacheRef.current.set(url, {
          data: validGenerations,
          timestamp: Date.now()
        });

        setGenerations(validGenerations);
        setCurrentTimes(new Array(validGenerations.length).fill(0));
      } else {
        setGenerations([]);
      }
    } catch (err) {
      setError(errorMessage(err));
      setGenerations([]);
    } finally {
      lastFetchRef.current = null;
    }
  };

  const listingUrl = (category: string) => {
    const params = new URLSearchParams({ limit: category ? '12' : '9' });
    if (category) {
      params.set('category', category);
    }
    return `${config.apiUrl}/listing?${params.toString()}`;
  };

  const fetchCategories = async () => {
    try {
      const data = await apiRequest<{ success: boolean; data: Category[] }>('categories');
      if (data.success && Array.isArray(data.data)) {
        setCategories(data.data);
      }
    } catch (err) {
      // Navigation par catégorie non bloquante : une erreur ici ne doit pas empêcher d'afficher les générations.
    }
  };

  const pollJobStatus = (jobId: string, startTime: number) => {
    pollTimerRef.current = setInterval(async () => {
      try {
        const data = await apiRequest<JobStatus>(`generation-status?id=${encodeURIComponent(jobId)}`);

        setJobProgress({ step: data.step, progress: data.progress });

        if (data.status === "done") {
          stopPolling();
          const durationInSeconds = ((Date.now() - startTime) / 1000).toFixed(2);
          setDuration(durationInSeconds);
          setLoading(false);
          setJobProgress(null);
          await fetchGenerations(listingUrl(selectedCategory), true);
          await fetchCategories();
        } else if (data.status === "error") {
          stopPolling();
          setError(data.error || "La génération a échoué.");
          setLoading(false);
          setJobProgress(null);
        }
      } catch (err) {
        stopPolling();
        setError(errorMessage(err));
        setLoading(false);
        setJobProgress(null);
      }
    }, 2000);
  };

  const startRecording = async () => {
    setRecordingError(null);
    try {
      const stream = await navigator.mediaDevices.getUserMedia({ audio: true });
      const mediaRecorder = new MediaRecorder(stream);
      recordedChunksRef.current = [];

      mediaRecorder.ondataavailable = (e: BlobEvent) => {
        if (e.data.size > 0) {
          recordedChunksRef.current.push(e.data);
        }
      };

      mediaRecorder.onstop = () => {
        const blob = new Blob(recordedChunksRef.current, { type: 'audio/webm' });
        setAudioFile(new File([blob], `enregistrement-${Date.now()}.webm`, { type: 'audio/webm' }));
        stream.getTracks().forEach((track) => track.stop());
      };

      mediaRecorderRef.current = mediaRecorder;
      mediaRecorder.start();
      setIsRecording(true);
    } catch (err) {
      setRecordingError("Impossible d'accéder au micro. Vérifiez les autorisations du navigateur.");
    }
  };

  const stopRecording = () => {
    mediaRecorderRef.current?.stop();
    setIsRecording(false);
  };

  const stopPolling = () => {
    if (pollTimerRef.current) {
      clearInterval(pollTimerRef.current);
      pollTimerRef.current = null;
    }
  };

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();

    if (sourceMode === "audio" && !audioFile) {
      setError("Merci d'enregistrer un message vocal.");
      return;
    }

    setLoading(true);
    setError(null);
    setDuration(null);
    setJobProgress({ step: "queued", progress: 0 });

    const startTime = Date.now();
    try {
      let data: { job_id: string };
      if (sourceMode === "audio" && audioFile) {
        const formData = new FormData();
        formData.append("audio", audioFile);
        data = await apiRequest('generation-audio', { method: "POST", body: formData });
      } else {
        data = await apiRequest('generation', { method: "POST", body: JSON.stringify({ input: subject }) });
      }

      pollJobStatus(data.job_id, startTime);
    } catch (err) {
      setError(errorMessage(err));
      setLoading(false);
      setJobProgress(null);
    }
  };

  useEffect(() => {
    return () => stopPolling();
  }, []);

  // Recherche texte (avec debounce) et navigation par catégorie partagent le
  // même effet : sélectionner une catégorie doit se comporter comme un
  // retour à la liste (recherche vidée côté affichage), pas comme une
  // recherche.
  useEffect(() => {
    let isMounted = true;

    const loadGenerations = async () => {
      try {
        if (searchQuery.length >= 3) {
          setIsSearching(true);
          await fetchGenerations(`${config.apiUrl}/search?query=${encodeURIComponent(searchQuery)}`);
        } else if (searchQuery.length === 0) {
          setIsSearching(false);
          await fetchGenerations(listingUrl(selectedCategory));
        }
      } catch (err) {
        if (isMounted) {
          setError("Erreur lors de la recherche.");
        }
      }
    };

    if (debounceTimerRef.current) {
      clearTimeout(debounceTimerRef.current);
    }

    // Pas de debounce nécessaire pour un changement de catégorie (pas de
    // frappe en rafale à absorber, contrairement à la recherche texte).
    debounceTimerRef.current = setTimeout(loadGenerations, searchQuery.length >= 3 ? 2000 : 0);

    return () => {
      isMounted = false;
      if (debounceTimerRef.current) {
        clearTimeout(debounceTimerRef.current);
      }
    };
  }, [searchQuery, selectedCategory]);

  useEffect(() => {
    fetchCategories();
  }, []);

  const handleLoadedMetadata = (index: number) => {
    const audio = audioRefs.current[index];
    if (audio && !isNaN(audio.duration)) {
      setTotalDurations((prev) => {
        const updated = [...prev];
        if (!updated[index] || updated[index] === 0) {
          updated[index] = audio.duration;
        }
        return updated;
      });
    }
  };

  const handleTimeUpdate = (index: number) => {
    const audio = audioRefs.current[index];
    if (audio) {
      setCurrentTimes((prev) => {
        const updated = [...prev];
        updated[index] = audio.currentTime;
        return updated;
      });

      const progressBar = progressRefs.current[index];
      if (progressBar && audio.duration > 0) {
        const progress = (audio.currentTime / audio.duration) * 100;
        progressBar.style.setProperty("--progress-width", `${progress}%`);
      }
    }
  };

  const handlePlayPause = (index: number) => {
    const currentAudio = audioRefs.current[index];
    if (!currentAudio) {
      return;
    }

    if (audioPlayingIndex === index) {
      currentAudio.pause();
      setAudioPlayingIndex(null);
    } else {
      if (audioPlayingIndex !== null) {
        audioRefs.current[audioPlayingIndex]?.pause();
      }
      currentAudio.play();
      setAudioPlayingIndex(index);
    }
  };

  const handleRewind = (index: number) => {
    const audio = audioRefs.current[index];
    if (!audio) {
      return;
    }
    audio.currentTime = Math.max(0, audio.currentTime - 10);
  };

  const handleForward = (index: number) => {
    const audio = audioRefs.current[index];
    if (!audio) {
      return;
    }
    audio.currentTime = Math.min(audio.duration, audio.currentTime + 10);
  };

  const formatTime = (seconds: number | undefined) => {
    // Les durées ne sont connues qu'après le chargement des métadonnées audio :
    // sans ce garde-fou, le lecteur affiche "NaN:NaN".
    if (seconds === undefined || !Number.isFinite(seconds)) {
      return "0:00";
    }
    const minutes = Math.floor(seconds / 60);
    const remainingSeconds = Math.floor(seconds % 60);
    return `${minutes}:${remainingSeconds < 10 ? "0" : ""}${remainingSeconds}`;
  };

  const formatDate = (dateString: string) => {
    try {
      const date = new Date(dateString);
      if (isNaN(date.getTime())) {
        return 'Date non disponible';
      }
      
      const options: Intl.DateTimeFormatOptions = {
        weekday: 'long',
        day: 'numeric',
        month: 'long',
        year: 'numeric'
      };
      
      // Formatage de la date en français
      let formattedDate = date.toLocaleDateString('fr-FR', options);
      
      // Première lettre en majuscule
      formattedDate = formattedDate.charAt(0).toUpperCase() + formattedDate.slice(1);
      
      return formattedDate;
    } catch (error) {
      console.error('Erreur de formatage de la date:', error);
      return 'Date non disponible';
    }
  };

  useJsonLd({
    '@context': 'https://schema.org',
    '@graph': [
      {
        '@type': 'WebSite',
        name: 'Vokso',
        url: window.location.origin,
      },
      ...(generations.length > 0
        ? [
          {
            '@type': 'ItemList',
            itemListElement: generations.map((gen, index) => ({
              '@type': 'ListItem',
              position: index + 1,
              item: {
                '@type': 'PodcastEpisode',
                name: cleanTitle(gen.title) || 'Sans titre',
                url: `https://vokso.fr${episodePath(gen.id, gen.title)}`,
                datePublished: gen.created_at,
                associatedMedia: {
                  '@type': 'MediaObject',
                  contentUrl: `${config.staticUrl}/static/audios/${gen.audio_url}`,
                },
              },
            })),
          },
        ]
        : []),
    ],
  });

  return (
    <>
      <NavBar />
      <div className="container">
      <div className="hero">
        <span className="hero-logo" aria-hidden="true">
          <VoksoMark />
        </span>
        <span className="badge-sovereign"><i className="bi bi-shield-lock"></i> Hébergé en Europe</span>
      </div>
      <div className="form-container">
        <h1>Générer un podcast</h1>
        {!authLoading && !user ? (
          <div className="auth-required">
            <p>Vokso est gratuit : créez un compte pour générer vos propres podcasts.</p>
            <Link to="/login" className="btn btn-primary">Créer un compte gratuit</Link>
            {' '}
            <Link to="/login">J'ai déjà un compte</Link>
          </div>
        ) : (
        <>
        <div className="source-mode-tabs">
          <button
            type="button"
            className={sourceMode === "text" ? "active" : ""}
            onClick={() => setSourceMode("text")}
          >
            Sujet texte
          </button>
          <button
            type="button"
            className={sourceMode === "audio" ? "active" : ""}
            onClick={() => setSourceMode("audio")}
          >
            Message vocal
          </button>
        </div>
        <form onSubmit={handleSubmit}>
          {sourceMode === "text" ? (
            <div className="input-container">
              <input
                type="text"
                placeholder="Tapez ici..."
                value={subject}
                onChange={(e) => setSubject(e.target.value)}
                className="input-field"
                required
              />
              <button
                type="button"
                className={`clear-input ${subject.length > 0 ? 'visible' : ''}`}
                onClick={() => setSubject('')}
                aria-label="Effacer le texte"
              >
                <i className="bi bi-x-lg"></i>
              </button>
            </div>
          ) : (
            <div className="voice-input-container">
              <button
                type="button"
                className={`record-btn ${isRecording ? "recording" : ""}`}
                onClick={isRecording ? stopRecording : startRecording}
              >
                <i className={`bi ${isRecording ? "bi-stop-fill" : "bi-mic-fill"}`}></i>
                {isRecording ? "Arrêter l'enregistrement" : "Parler au lieu d'écrire"}
              </button>
              {audioFile && !isRecording && (
                <p className="recording-ready">Message vocal prêt ({audioFile.name})</p>
              )}
              {recordingError && <p className="error">{recordingError}</p>}
            </div>
          )}
          <button type="submit" disabled={loading} className="submit-btn">
            {loading ? "Envoi en cours..." : "Générer"}
          </button>
        </form>
        </>
        )}

        {loading && (
          <div className="job-progress">
            <div className="vokso-loader" role="status" aria-label="Génération en cours">
              <span></span>
              <span></span>
              <span></span>
              <span></span>
            </div>
            {jobProgress && (
              <>
                <p className="job-progress-label">{STEP_LABELS[jobProgress.step] || "Traitement en cours..."}</p>
                <div className="progress-container">
                  <div
                    className="progress-bar"
                    style={{ "--progress-width": `${jobProgress.progress}%` } as React.CSSProperties}
                  ></div>
                </div>
              </>
            )}
          </div>
        )}
        {error && <p className="error">{error}</p>}
        {duration && (
          <p className="success">Temps d'appel API : {duration} secondes</p>
        )}
      </div>

      <div className="last-generations">
        <div className="input-container">
          <input
            type="text"
            placeholder="Rechercher un podcast..."
            value={searchQuery}
            onChange={(e) => setSearchQuery(e.target.value)}
            className="input-field"
          />
          <button
            type="button"
            className={`clear-input ${searchQuery.length > 0 ? 'visible' : ''}`}
            onClick={() => setSearchQuery('')}
            aria-label="Effacer la recherche"
          >
            <i className="bi bi-x-lg"></i>
          </button>
        </div>
        {categories.length > 1 && !isSearching && (
          <div className="category-nav" role="tablist" aria-label="Filtrer par catégorie">
            <button
              type="button"
              role="tab"
              aria-selected={selectedCategory === ''}
              className={`category-pill ${selectedCategory === '' ? 'active' : ''}`}
              onClick={() => setSelectedCategory('')}
            >
              <span className="category-pill-icon">
                <i className="bi bi-grid-fill"></i>
              </span>
              Toutes
            </button>
            {categories.map((cat) => (
              <button
                key={cat.id}
                type="button"
                role="tab"
                aria-selected={selectedCategory === cat.label}
                className={`category-pill ${selectedCategory === cat.label ? 'active' : ''}`}
                onClick={() => setSelectedCategory(cat.label)}
              >
                <span
                  className={`category-pill-icon ${cat.cover_image ? 'has-cover' : ''}`}
                  style={cat.cover_image ? {
                    backgroundImage: `url(${config.staticUrl}/static/images/${cat.cover_image})`,
                  } : undefined}
                >
                  <i className={`bi bi-${cat.icon || 'soundwave'}`}></i>
                </span>
                {cat.label}
              </button>
            ))}
          </div>
        )}
        <h2>
          {isSearching
            ? "Résultats"
            : selectedCategory
              ? `Catégorie : ${selectedCategory}`
              : "Dernières générations"}
        </h2>
        {generations.length > 0 ? (
          <div className="generations-list">
            {generations.map((gen, index) => (
              <div key={index} className="generation-item">
                <div className="generation-thumbnail">
                  <img
                    src={`${config.staticUrl}/static/images/${gen.image_url}`}
                    alt={`Image pour ${cleanTitle(gen.title) || 'Génération'}`}
                    loading="lazy"
                  />
                  {gen.category && (
                    <span className="generation-category">
                      <i className={`bi bi-${gen.category_icon || 'soundwave'}`}></i>
                      {gen.category}
                    </span>
                  )}
                </div>
                <div className="generation-info">
                  <h3>{cleanTitle(gen.title) || 'Sans titre'}</h3>
                  <p className="generation-date">
                    {formatDate(gen.created_at)}
                  </p>
                  {gen.description && (
                    <>
                      <button
                        type="button"
                        className="generation-text-toggle"
                        onClick={() =>
                          setExpandedTextIndex(expandedTextIndex === index ? null : index)
                        }
                      >
                        {expandedTextIndex === index ? 'Masquer le texte' : 'Lire le texte'}
                      </button>
                      {expandedTextIndex === index && (
                        <p className="generation-full-text">{gen.description}</p>
                      )}
                    </>
                  )}
                  {gen.id && (
                    <a
                      className="generation-permalink"
                      href={episodePath(gen.id, gen.title)}
                    >
                      Voir la page complète de l'épisode ↗
                    </a>
                  )}
                </div>
                <div className="audio-player">
                  <audio
                    ref={(el) => { audioRefs.current[index] = el; }}
                    src={`${config.staticUrl}/static/audios/${gen.audio_url}`}
                    onLoadedMetadata={() => handleLoadedMetadata(index)}
                    onTimeUpdate={() => handleTimeUpdate(index)}
                    onEnded={() => {
                      setAudioPlayingIndex(null);
                      setCurrentTimes((prev) => {
                        const updated = [...prev];
                        updated[index] = 0;
                        return updated;
                      });
                    }}
                    preload="metadata"
                    onError={(e) => {
                      const audioElement = e.currentTarget;
                      console.error('Erreur de chargement audio:', {
                        audio_url: gen.audio_url,
                        url: audioElement.src,
                        error: audioElement.error
                      });
                      setError(`Erreur de chargement de l'audio: ${gen.audio_url}`);
                    }}
                  />
                  <div className="time-display">
                    <span>{formatTime(currentTimes[index])}</span>
                    <div className="controls">
                      <button
                        onClick={() => handleRewind(index)}
                        className="rewind-btn"
                      >
                        <i className="bi bi-skip-backward-fill"></i>
                      </button>
                      <button
                        onClick={() => handlePlayPause(index)}
                        className="play-pause-btn"
                      >
                        {audioPlayingIndex === index ? (
                          <i className="bi bi-pause-fill"></i>
                        ) : (
                          <i className="bi bi-play-fill"></i>
                        )}
                      </button>
                      <button
                        onClick={() => handleForward(index)}
                        className="forward-btn"
                      >
                        <i className="bi bi-skip-forward-fill"></i>
                      </button>
                    </div>
                    <span>{formatTime(totalDurations[index])}</span>
                  </div>
                  <div className="progress-container">
                    <div
                      className="progress-bar"
                      ref={(el) => { progressRefs.current[index] = el; }}
                    ></div>
                  </div>
                </div>
              </div>
            ))}
          </div>
        ) : (
          <p className="no-generations">Aucune génération disponible</p>
        )}
      </div>

      <SovereigntySection />
      </div>
      <Footer />
    </>
  );
} 