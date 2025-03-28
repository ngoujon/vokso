import React, { useState, useEffect, useRef } from 'react';
import '../styles/globals.css';
import { config } from '../config';

// Vérification des variables d'environnement
if (!config.apiUrl || !config.staticUrl) {
  throw new Error("Les variables d'environnement REACT_APP_API_URL et REACT_APP_STATIC_URL doivent être définies");
}

export default function Home() {
  const [inputValue, setInputValue] = useState("");
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState(null);
  const [duration, setDuration] = useState(null);
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
        setError("Aucune génération trouvée");
        setGenerations([]);
      }
    } catch (err) {
      setError(err.message);
      setGenerations([]);
    } finally {
      lastFetchRef.current = null;
    }
  };

  const handleSubmit = async (e) => {
    e.preventDefault();
    setLoading(true);
    setError(null);
    setDuration(null);

    const startTime = Date.now();
    try {
      const response = await fetch(`${config.apiUrl}/generation`, {
        method: "POST",
        headers: {
          "Content-Type": "application/json",
        },
        body: JSON.stringify({ input: inputValue }),
      });

      const data = await response.json();

      if (!response.ok) {
        throw new Error(data.error || "Erreur inconnue");
      }

      const endTime = Date.now();
      const durationInSeconds = ((endTime - startTime) / 1000).toFixed(2);
      setDuration(durationInSeconds);

      // Forcer le rafraîchissement après une génération
      await fetchGenerations(`${config.apiUrl}/listing`, true);
    } catch (err) {
      setError(err.message);
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    let isMounted = true;

    const searchPodcasts = async () => {
      try {
        if (inputValue.length >= 3) {
          setIsSearching(true);
          await fetchGenerations(`${config.apiUrl}/search?query=${inputValue}`);
        } else if (inputValue.length === 0) {
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
  }, [inputValue]);

  useEffect(() => {
    let isMounted = true;

    const fetchInitialData = async () => {
      if (isMounted && inputValue.length === 0) {
        await fetchGenerations(`${config.apiUrl}/listing`);
      }
    };

    fetchInitialData();

    return () => {
      isMounted = false;
    };
  }, []); // Dépendance vide pour ne s'exécuter qu'une fois au montage

  const handleLoadedMetadata = (index) => {
    const audio = document.getElementById(`audio-${index}`);
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
    const audio = document.getElementById(`audio-${index}`);
    if (audio) {
      setCurrentTimes((prev) => {
        const updated = [...prev];
        updated[index] = audio.currentTime;
        return updated;
      });

      const progressBar = document.getElementById(`progress-${index}`);
      if (progressBar && audio.duration > 0) {
        const progress = (audio.currentTime / audio.duration) * 100;
        progressBar.style.setProperty("--progress-width", `${progress}%`);
      }
    }
  };

  const handlePlayPause = (index) => {
    const currentAudio = document.getElementById(`audio-${index}`);

    if (audioPlayingIndex === index) {
      currentAudio.pause();
      setAudioPlayingIndex(null);
    } else {
      if (audioPlayingIndex !== null) {
        document.getElementById(`audio-${audioPlayingIndex}`).pause();
      }
      currentAudio.play();
      setAudioPlayingIndex(index);
    }
  };

  const handleRewind = (index) => {
    const audio = document.getElementById(`audio-${index}`);
    audio.currentTime = Math.max(0, audio.currentTime - 10);
  };

  const handleForward = (index) => {
    const audio = document.getElementById(`audio-${index}`);
    audio.currentTime = Math.min(audio.duration, audio.currentTime + 10);
  };

  const formatTime = (seconds) => {
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
      <div className="form-container">
        <h1>Générer un podcast</h1>
        <form onSubmit={handleSubmit}>
          <div className="input-container">
            <input
              type="text"
              placeholder="Tapez ici..."
              value={inputValue}
              onChange={(e) => setInputValue(e.target.value)}
              className="input-field"
              required
            />
            <button
              type="button"
              className={`clear-input ${inputValue.length > 0 ? 'visible' : ''}`}
              onClick={() => {
                setInputValue('');
                setIsSearching(false);
                fetchGenerations(`${config.apiUrl}/listing`);
              }}
              aria-label="Effacer le texte"
            >
              <i className="bi bi-x-lg"></i>
            </button>
          </div>
          <button type="submit" disabled={loading} className="submit-btn">
            {loading ? "Envoi en cours..." : "Générer"}
          </button>
        </form>

        {loading && <div className="loader"></div>}
        {error && <p className="error">{error}</p>}
        {duration && (
          <p className="success">Temps d'appel API : {duration} secondes</p>
        )}
      </div>

      <div className="last-generations">
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
                    id={`audio-${index}`}
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
                    <div className="progress-bar" id={`progress-${index}`}></div>
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