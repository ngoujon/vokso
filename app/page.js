"use client";

import { useState, useEffect } from "react";
import "../styles/globals.css";

export default function Home() {
  const [inputValue, setInputValue] = useState("");
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState(null);
  const [duration, setDuration] = useState(null);
  const [generations, setGenerations] = useState([]);

  // Gère quel fichier audio est en cours de lecture (index). null = aucun
  const [audioPlayingIndex, setAudioPlayingIndex] = useState(null);

  // Tableaux pour stocker le temps courant et la durée totale de chaque audio
  const [currentTimes, setCurrentTimes] = useState([]);
  const [totalDurations, setTotalDurations] = useState([]);

  // Gère la visibilité des tooltips
  const [tooltipVisible, setTooltipVisible] = useState(null);

  /* -------------------------------------------------------------------------
   * Soumission du formulaire
   * ----------------------------------------------------------------------- */
  const handleSubmit = async (e) => {
    e.preventDefault();
    setLoading(true);
    setError(null);
    setDuration(null);

    const startTime = Date.now();
    try {
      const response = await fetch("http://api-podcast.qwebty.local/generation", {
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

      await fetchLastGenerations();
    } catch (err) {
      setError(err.message);
    } finally {
      setLoading(false);
    }
  };

  /* -------------------------------------------------------------------------
   * Récupération des dernières générations
   * ----------------------------------------------------------------------- */
  const fetchLastGenerations = async () => {
    try {
      const response = await fetch("http://api-podcast.qwebty.local/listing");
      const data = await response.json();

      if (!response.ok) {
        throw new Error(data.error || "Erreur inconnue");
      }

      if (data.success && data.data) {
        setGenerations(data.data);
      } else {
        setError("Aucune génération trouvée");
      }
    } catch (err) {
      setError(err.message);
    }
  };

  useEffect(() => {
    // Fonction de recherche dynamique
    const searchPodcasts = async () => {
      try {
        if (inputValue.length >= 3) {
          const response = await fetch(`http://api-podcast.qwebty.local/search?query=${inputValue}`);
          const data = await response.json();
  
          if (data.success) {
            setGenerations(data.data); // Met à jour les générations avec les résultats de recherche
          } else {
            setGenerations([]); // Vide les générations si aucun résultat n'est trouvé
            setError(data.message); // Affiche un message d'erreur
          }
        } else if (inputValue.length === 0) {
          // Si l'input est vidé, recharge les dernières générations
          fetchLastGenerations();
        }
      } catch (err) {
        setError("Erreur lors de la recherche."); // Gestion des erreurs réseau
      }
    };
  
    // Délai de recherche (2 secondes)
    const timer = setTimeout(() => {
      searchPodcasts();
    }, 2000);
  
    // Nettoie le timer si l'utilisateur continue à taper
    return () => clearTimeout(timer);
  }, [inputValue]); // Déclenche l'effet lorsque `inputValue` change

  /* -------------------------------------------------------------------------
   * Gestion de la lecture/pause
   * ----------------------------------------------------------------------- */
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

  /* -------------------------------------------------------------------------
   * Réculer & Avancer de 10 secondes
   * ----------------------------------------------------------------------- */
  const handleRewind = (index) => {
    const audio = document.getElementById(`audio-${index}`);
    audio.currentTime = Math.max(0, audio.currentTime - 10);
  };

  const handleForward = (index) => {
    const audio = document.getElementById(`audio-${index}`);
    audio.currentTime = Math.min(audio.duration, audio.currentTime + 10);
  };

  /* -------------------------------------------------------------------------
   * Formatage du temps (secondes -> mm:ss)
   * ----------------------------------------------------------------------- */
  const formatTime = (seconds) => {
    if (!seconds || isNaN(seconds)) return "0:00";
    const minutes = Math.floor(seconds / 60);
    const remainingSeconds = Math.floor(seconds % 60);
    return `${minutes}:${remainingSeconds < 10 ? "0" : ""}${remainingSeconds}`;
  };

  /* -------------------------------------------------------------------------
   * Chargement des métadonnées (durée totale)
   * ----------------------------------------------------------------------- */
  const handleLoadedMetadata = (index) => {
    const audio = document.getElementById(`audio-${index}`);
    setTotalDurations((prev) => {
      const updated = [...prev];
      updated[index] = audio.duration;
      return updated;
    });
  };

  /* -------------------------------------------------------------------------
   * Mise à jour du temps courant et de la barre de progression
   * ----------------------------------------------------------------------- */
  const handleTimeUpdate = (index) => {
    const audio = document.getElementById(`audio-${index}`);
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
  };

  return (
    <div className="container">
      {/* FORMULAIRE */}
      <div className="form-container">
        <h1>Générer un podcast</h1>
        <form onSubmit={handleSubmit}>
          <input
            type="text"
            placeholder="Tapez ici..."
            value={inputValue}
            onChange={(e) => setInputValue(e.target.value)}
            className="input-field"
            required
          />
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

      {/* LISTE DES GÉNÉRATIONS */}
      <div className="last-generations">
        <h2>Les 3 dernières générations</h2>
        {generations.length > 0 ? (
          <div className="generations-list">
            {generations.map((gen, index) => (
              <div key={index} className="generation-item">
                <div
                  className="tooltip-container"
                  onMouseEnter={() => gen.title.length > 30 && setTooltipVisible(index)}
                  onMouseLeave={() => setTooltipVisible(null)}
                >
                  <h3 className="generation-title">
                    {gen.title.length > 30
                      ? gen.title.substring(0, 30) + "..."
                      : gen.title}
                  </h3>
                  {tooltipVisible === index && gen.title.length > 30 && (
                    <div className="tooltip">{gen.title}</div>
                  )}
                </div>

                {gen.image_url && (
                  <img
                    src={
                      "http://static-podcast.qwebty.local/images/" + gen.image_url
                    }
                    alt={gen.title}
                    className="generation-image"
                  />
                )}

                {gen.audio_url && (
                  <div className="audio-player">
                    <audio
                      id={`audio-${index}`}
                      src={
                        "http://static-podcast.qwebty.local/audios/" +
                        gen.audio_url
                      }
                      type="audio/mp3"
                      onLoadedMetadata={() => handleLoadedMetadata(index)}
                      onTimeUpdate={() => handleTimeUpdate(index)}
                    >
                      Votre navigateur ne supporte pas l'élément audio.
                    </audio>

                    <div className="audio-info">
                      <span>{formatTime(currentTimes[index])}</span> /{" "}
                      <span>{formatTime(totalDurations[index])}</span>
                    </div>

                    <div
                      id={`progress-${index}`}
                      className="progress-bar"
                      style={{ "--progress-width": "0%" }}
                    ></div>

                    <div className="audio-controls">
                      <button
                        onClick={() => handleRewind(index)}
                        className="audio-button"
                      >
                        <i className="bi bi-arrow-counterclockwise"></i>
                      </button>
                      <button
                        onClick={() => handlePlayPause(index)}
                        className="audio-button"
                      >
                        {audioPlayingIndex === index ? (
                          <i className="bi bi-pause"></i>
                        ) : (
                          <i className="bi bi-play"></i>
                        )}
                      </button>
                      <button
                        onClick={() => handleForward(index)}
                        className="audio-button"
                      >
                        <i className="bi bi-arrow-clockwise"></i>
                      </button>
                    </div>
                  </div>
                )}
              </div>
            ))}
          </div>
        ) : (
          <p>Aucune génération disponible pour le moment.</p>
        )}
      </div>
    </div>
  );
}