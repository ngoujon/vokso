<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>QWAI POD</title>
    <link rel="stylesheet" href="../assets/styles.css">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
</head>

<body>
    <!-- Premier bloc : Formulaire de génération -->
    <div id="input-form">
        <h1>Générer un podcast</h1>
        <form id="form" method="POST" action="index.php">
            <input type="text" name="user_input" placeholder="Saisir un sujet / thème" required>
            <div class="button-loader-container">
                <button type="submit" id="submit-button">Générer</button>
                <div class="loader" id="loader" style="display: none;"></div>
            </div>
        </form>
        <div id="response" class="response"></div>
        <div id="completed-message" class="completed-message"></div>
    </div>

    <!-- Deuxième bloc : Dernières générations -->
    <div id="last-generations">
        <h2>Dernières générations :</h2>
        <div class="last-generations-container">
            <?php foreach ($generations_with_files as $item): ?>
                <?php $title = htmlspecialchars($item['generation']['title']); ?>
                <div class="generation-item">
                    <h3><?= $title ?></h3>
                    <?php if (isset($item['files']['image'])): ?>
                        <img src="/output/<?= htmlspecialchars($item['files']['image']) ?>" alt="Image de la génération">
                    <?php else: ?>
                        <p>Aucune image disponible pour cette génération.</p>
                    <?php endif; ?>

                    <?php if (isset($item['files']['audio'])): ?>
                        <div class="custom-audio-player">
                            <audio id="audio-<?= $item['generation']['id'] ?>" src="/output/<?= htmlspecialchars($item['files']['audio']) ?>" preload="auto"></audio>
                            <div class="controls">
                                <button class="skipBtn" data-id="<?= $item['generation']['id'] ?>" data-skip="-10">-10s</button>
                                <button class="playPauseBtn" data-id="<?= $item['generation']['id'] ?>">Play</button>
                                <button class="skipBtn" data-id="<?= $item['generation']['id'] ?>" data-skip="10">+10s</button>
                            </div>
                            <div class="time-display">
                                <span id="currentTime-<?= $item['generation']['id'] ?>">00:00</span> /
                                <span id="duration-<?= $item['generation']['id'] ?>">Chargement...</span>
                            </div>
                        </div>
                    <?php else: ?>
                        <p>Aucun fichier audio disponible pour cette génération.</p>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <script>
        $(function() {
            const $form = $('#form');
            const $loader = $('#loader').hide();
            const $submitButton = $('#submit-button');
            const $response = $('#response');
            const $completedMessage = $('#completed-message');

            $form.on('submit', function(event) {
                event.preventDefault();
                $loader.show();
                $submitButton.hide();
                $response.empty();
                $completedMessage.empty();

                $.ajax({
                    url: '/index.php',
                    type: 'POST',
                    data: $form.serialize(),
                    dataType: 'json',
                    success: (data) => handleResponse(data.total_time),
                    error: () => handleResponse(null, 'Une erreur est survenue. Veuillez réessayer.')
                });
            });

            function handleResponse(totalTime, errorMessage = null) {
                $loader.hide();
                $submitButton.show();
                if (errorMessage) {
                    $response.text(errorMessage);
                } else {
                    $completedMessage.text(`Temps total de génération : ${totalTime} s`);
                }
                // Recharger la page pour afficher les dernières générations
                location.reload();
            }

            // Event delegation for play/pause and skip buttons
            $('.last-generations-container').on('click', '.playPauseBtn', function() {
                const audioId = $(this).data('id');
                togglePlayPause(audioId, $(this));
            }).on('click', '.skipBtn', function() {
                const audioId = $(this).data('id');
                const skipTime = $(this).data('skip');
                skipAudio(audioId, skipTime);
            });

            function togglePlayPause(id, $btn) {
                const audio = document.getElementById(`audio-${id}`);
                if (audio.paused) {
                    audio.play();
                    $btn.text('Pause');
                } else {
                    audio.pause();
                    $btn.text('Play');
                }
                updateTimeDisplay(audio, id);
            }

            function skipAudio(id, time) {
                const audio = document.getElementById(`audio-${id}`);
                audio.currentTime = Math.min(Math.max(0, audio.currentTime + time), audio.duration);
            }

            function updateTimeDisplay(audio, id) {
                const $currentTimeDisplay = $(`#currentTime-${id}`);
                const $durationDisplay = $(`#duration-${id}`);

                // Vérifier si l'audio existe et récupérer la durée dès que possible
                if (audio) {
                    console.log("Audio Loaded: ", audio);

                    // Ajouter l'événement loadedmetadata pour récupérer la durée
                    audio.addEventListener('loadedmetadata', function() {
                        console.log("Durée de l'audio : ", audio.duration);

                        if (!isNaN(audio.duration)) {
                            $durationDisplay.text(formatTime(audio.duration)); // Afficher la durée formatée
                        } else {
                            $durationDisplay.text("Indisponible");
                        }
                    });

                    // Si le fichier audio est déjà prêt (peut être le cas lors du préchargement)
                    if (audio.duration && !isNaN(audio.duration)) {
                        $durationDisplay.text(formatTime(audio.duration)); // Afficher la durée immédiatement
                    }

                    // Mise à jour des informations de temps pendant la lecture
                    audio.addEventListener('timeupdate', function() {
                        $currentTimeDisplay.text(formatTime(audio.currentTime));
                    });
                } else {
                    console.error("Élément audio non trouvé !");
                }
            }

            function formatTime(seconds) {
                const minutes = Math.floor(seconds / 60).toString().padStart(2, '0');
                const remainingSeconds = Math.floor(seconds % 60).toString().padStart(2, '0');
                return `${minutes}:${remainingSeconds}`;
            }

            // Récupérer la durée de l'audio pour tous les éléments audio immédiatement au chargement de la page
            $('audio').each(function() {
                const audio = this;
                const audioId = $(audio).attr('id').replace('audio-', '');
                const $durationDisplay = $(`#duration-${audioId}`);
                audio.addEventListener('loadedmetadata', function() {
                    if (!isNaN(audio.duration)) {
                        $durationDisplay.text(formatTime(audio.duration)); // Afficher la durée dès que l'audio est prêt
                    }
                });

                // Si l'audio est déjà prêt (par exemple, préchargé), afficher directement la durée
                if (audio.duration && !isNaN(audio.duration)) {
                    $durationDisplay.text(formatTime(audio.duration));
                }
            });
        });
    </script>
</body>

</html>
