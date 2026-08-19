import React, { useState, useEffect, useRef } from 'react';
import { Link } from 'react-router-dom';
import '../styles/globals.css';
import '../styles/Dashboard.css';
import { config } from '../config';
import { useAuth } from '../AuthContext';

// Vérification des variables d'environnement
if (!config.apiUrl || !config.staticUrl) {
  throw new Error("Les variables d'environnement REACT_APP_API_URL et REACT_APP_STATIC_URL doivent être définies");
}

export default function Home() {
  const { user, logout } = useAuth();
  const [subject, setSubject] = useState("");
  const [searchQuery, setSearchQuery] = useState("");
  const [sourceMode, setSourceMode] = useState("text");
  const [audioFile, setAudioFile] = useState(null);
  const [isRecording, setIsRecording] = useState(false);
  const [recordingError, setRecordingError] = useState(null);
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState(null);
  const [duration, setDuration] = useState(null);
  const [jobProgress, setJobProgress] = useState(null);
  const [generations, setGenerations] = useState([]);
  const [audioPlayingIndex, setAudioPlayingIndex] = useState(null);
  const [currentTimes, setCurrentTimes] = useState([]);
  const [totalDurations, setTotalDurations] = useState([]);
  const [tooltipVisible, setTooltipVisible] = useState(null);
  const [isSearching, setIsSearching] = useState(false);
  
  // Références pour le cache et le debounce
  const cacheRef = useRef(new Map());
  const lastFetchRef = useRef(null);
  const debounceTimerRef = useRef(null);
  const pollTimerRef = useRef(null);
  const audioRefs = useRef({});
  const progressRefs = useRef({});
  const mediaRecorderRef = useRef(null);
  const recordedChunksRef = useRef([]);

  const STEP_LABELS = {
    queued: "En file d'attente...",
    transcription: "Transcription de l'audio...",
    text: "Génération du texte...",
    image: "Génération de l'image...",
    audio: "Génération de l'audio...",
    category: "Classification...",
    done: "Terminé",
  };

  const fetchGenerations = async (url, forceRefresh = false) => {
    try {
      // Vérifier si on a des données en cache et si on ne force pas le rafraîchissement
      if (!forceRefresh && cacheRef.current.has(url)) {
        const cachedData = cacheRef.current.get(url);
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
      const data = await response.json();

      if (!response.ok) {
        throw new Error(data.error || "Erreur inconnue");
      }

      if (data.success && data.data) {
        const validGenerations = data.data.map(gen => {
          if (!gen.audio_url) {
            return null;
          }
          return {
            ...gen,
            filename: gen.audio_url
          };
        }).filter(Boolean);

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
      setError(err.message);
      setGenerations([]);
    } finally {
      lastFetchRef.current = null;
    }
  };

  const pollJobStatus = (jobId, startTime) => {
    pollTimerRef.current = setInterval(async () => {
      try {
        const response = await fetch(`${config.apiUrl}/generation-status?id=${jobId}`);
        const data = await response.json();

        if (!response.ok) {
          throw new Error(data.error || "Erreur inconnue");
        }

        setJobProgress({ step: data.step, progress: data.progress });

        if (data.status === "done") {
          clearInterval(pollTimerRef.current);
          const durationInSeconds = ((Date.now() - startTime) / 1000).toFixed(2);
          setDuration(durationInSeconds);
          setLoading(false);
          setJobProgress(null);
          await fetchGenerations(`${config.apiUrl}/listing`, true);
        } else if (data.status === "error") {
          clearInterval(pollTimerRef.current);
          setError(data.error || "La génération a échoué.");
          setLoading(false);
          setJobProgress(null);
        }
      } catch (err) {
        clearInterval(pollTimerRef.current);
        setError(err.message);
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

      mediaRecorder.ondataavailable = (e) => {
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

  const handleSubmit = async (e) => {
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
      let response;
      if (sourceMode === "audio") {
        const formData = new FormData();
        formData.append("audio", audioFile);
        response = await fetch(`${config.apiUrl}/generation-audio`, {
          method: "POST",
          body: formData,
        });
      } else {
        response = await fetch(`${config.apiUrl}/generation`, {
          method: "POST",
          headers: {
            "Content-Type": "application/json",
          },
          body: JSON.stringify({ input: subject }),
        });
      }

      const data = await response.json();

      if (!response.ok) {
        throw new Error(data.error || "Erreur inconnue");
      }

      pollJobStatus(data.job_id, startTime);
    } catch (err) {
      setError(err.message);
      setLoading(false);
      setJobProgress(null);
    }
  };

  useEffect(() => {
    return () => {
      if (pollTimerRef.current) {
        clearInterval(pollTimerRef.current);
      }
    };
  }, []);

  useEffect(() => {
    let isMounted = true;

    const searchPodcasts = async () => {
      try {
        if (searchQuery.length >= 3) {
          setIsSearching(true);
          await fetchGenerations(`${config.apiUrl}/search?query=${encodeURIComponent(searchQuery)}`);
        } else if (searchQuery.length === 0) {
          setIsSearching(false);
          await fetchGenerations(`${config.apiUrl}/listing`);
        }
      } catch (err) {
        if (isMounted) {
          setError("Erreur lors de la recherche.");
        }
      }
    };
  
    // Nettoyer le timer précédent
    if (debounceTimerRef.current) {
      clearTimeout(debounceTimerRef.current);
    }

    // Mettre en place un nouveau timer
    debounceTimerRef.current = setTimeout(() => {
      searchPodcasts();
    }, 2000);
  
    return () => {
      isMounted = false;
      if (debounceTimerRef.current) {
        clearTimeout(debounceTimerRef.current);
      }
    };
  }, [searchQuery]);

  useEffect(() => {
    let isMounted = true;

    const fetchInitialData = async () => {
      if (isMounted && searchQuery.length === 0) {
        await fetchGenerations(`${config.apiUrl}/listing`);
      }
    };

    fetchInitialData();

    return () => {
      isMounted = false;
    };
  }, []); // Dépendance vide pour ne s'exécuter qu'une fois au montage

  const handleLoadedMetadata = (index) => {
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

  const handleTimeUpdate = (index) => {
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

  const handlePlayPause = (index) => {
    const currentAudio = audioRefs.current[index];

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

  const handleRewind = (index) => {
    const audio = audioRefs.current[index];
    audio.currentTime = Math.max(0, audio.currentTime - 10);
  };

  const handleForward = (index) => {
    const audio = audioRefs.current[index];
    audio.currentTime = Math.min(audio.duration, audio.currentTime + 10);
  };

  const formatTime = (seconds) => {
    // Les durées ne sont connues qu'après le chargement des métadonnées audio :
    // sans ce garde-fou, le lecteur affiche "NaN:NaN".
    if (!Number.isFinite(seconds)) {
      return "0:00";
    }
    const minutes = Math.floor(seconds / 60);
    const remainingSeconds = Math.floor(seconds % 60);
    return `${minutes}:${remainingSeconds < 10 ? "0" : ""}${remainingSeconds}`;
  };

  const formatDate = (dateString) => {
    try {
      const date = new Date(dateString);
      if (isNaN(date.getTime())) {
        return 'Date non disponible';
      }
      
      const options = {
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

  return (
    <div className="container">
      <div className="top-nav-auth">
        {user ? (
          <>
            <Link to={user.role === 'admin' ? '/admin' : '/dashboard'}>
              {user.role === 'admin' ? 'Espace admin' : 'Mon espace'}
            </Link>
            <button onClick={logout}>Déconnexion</button>
          </>
        ) : (
          <Link to="/login">Se connecter</Link>
        )}
      </div>
      <div className="form-container">
        <h1>Générer un podcast</h1>
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

        {loading && (
          <div className="job-progress">
            <div className="loader"></div>
            {jobProgress && (
              <>
                <p className="job-progress-label">{STEP_LABELS[jobProgress.step] || "Traitement en cours..."}</p>
                <div className="progress-container">
                  <div
                    className="progress-bar"
                    style={{ "--progress-width": `${jobProgress.progress}%` }}
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
        <h2>{isSearching ? "Résultats" : "Les 3 dernières générations"}</h2>
        {generations.length > 0 ? (
          <div className="generations-list">
            {generations.map((gen, index) => (
              <div key={index} className="generation-item">
                <div className="generation-thumbnail">
                  <img 
                    src={`${config.staticUrl}/images/${gen.image_url}`} 
                    alt={`Image pour ${gen.title || 'Génération'}`}
                  />
                </div>
                <div className="generation-info">
                  <h3>{gen.title || 'Sans titre'}</h3>
                  <p className="generation-date">
                    {formatDate(gen.created_at)}
                  </p>
                </div>
                <div className="audio-player">
                  <audio
                    ref={(el) => (audioRefs.current[index] = el)}
                    src={`${config.staticUrl}/audios/${gen.audio_url}`}
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
                      const audioElement = e.target;
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
                      ref={(el) => (progressRefs.current[index] = el)}
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
    </div>
  );
} 