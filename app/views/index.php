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
            <input type="text" name="user_input" id="user_input" placeholder="Saisir un sujet / thème" required>
            <div class="button-loader-container">
                <button type="submit" id="submit-button">Générer</button>
                <div class="loader" id="loader" style="display: none;"></div>
            </div>
        </form>
        <div id="response" class="response"></div>
        <div id="completed-message" class="completed-message"></div>
    </div>

    <!-- Section pour les résultats de la recherche en temps réel -->
    <div id="live-search-results">
        <ul id="search-results-list"></ul>
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
            // Fonction de recherche en temps réel
            $('#user_input').on('input', function() {
                let query = $(this).val();

                if (query.length > 2) {
                    $.ajax({
                        url: 'index.php?action=search&query=' + encodeURIComponent(query),
                        method: 'GET',
                        success: function(response) {
                            let resultsList = $('#search-results-list');
                            resultsList.empty();

                            if (response.results.length > 0) {
                                response.results.forEach(function(item) {
                                    let listItem = $('<div class="search-result-item"></div>');
                                    listItem.append('<h4>' + item.title + '</h4>');
                                    listItem.append('<img src="/output/images/' + item.image_url + '" alt="Image de la génération">');
                                    listItem.append('<div class="custom-audio-player">' +
                                        '<audio id="audio-' + item.id + '" src="/output/audios/' + item.audio_url + '" preload="auto"></audio>' +
                                        '<div class="controls">' +
                                        '<button class="skipBtn" data-id="' + item.id + '" data-skip="-10">-10s</button>' +
                                        '<button class="playPauseBtn" data-id="' + item.id + '">Play</button>' +
                                        '<button class="skipBtn" data-id="' + item.id + '" data-skip="10">+10s</button>' +
                                        '</div>' +
                                        '<div class="time-display">' +
                                        '<span id="currentTime-' + item.id + '">00:00</span> / ' +
                                        '<span id="duration-' + item.id + '">Chargement...</span>' +
                                        '</div>' +
                                        '</div>');
                                    resultsList.append(listItem);
                                    initializeAudioPlayer(item.id);
                                });
                            } else {
                                resultsList.append('<li>Aucun résultat trouvé.</li>');
                            }
                        }
                    });
                } else {
                    $('#search-results-list').empty();
                }
            });

            // Initialisation des éléments audio dans la recherche en temps réel
            function initializeAudioPlayer(id) {
                const audio = document.getElementById('audio-' + id);
                const $durationDisplay = $('#duration-' + id);
                const $currentTimeDisplay = $('#currentTime-' + id);
                const $playPauseBtn = $('[data-id="' + id + '"].playPauseBtn');

                // Attendre que le fichier audio soit prêt
                audio.addEventListener('loadedmetadata', function() {
                    if (!isNaN(audio.duration)) {
                        $durationDisplay.text(formatTime(audio.duration)); // Afficher la durée dès que possible
                    } else {
                        $durationDisplay.text('Durée Indisponible');
                    }
                });

                // Vérification de la durée immédiatement si déjà disponible
                if (audio.duration && !isNaN(audio.duration)) {
                    $durationDisplay.text(formatTime(audio.duration));
                }

                // Gestion de la lecture / pause
                $playPauseBtn.on('click', function() {
                    if (audio.paused) {
                        audio.play();
                        $(this).text('Pause');
                    } else {
                        audio.pause();
                        $(this).text('Play');
                    }
                    updateCurrentTime(audio, id);
                });

                // Gestion des sauts de temps (+10s, -10s)
                $('.skipBtn[data-id="' + id + '"]').on('click', function() {
                    const skipTime = $(this).data('skip');
                    audio.currentTime = Math.min(Math.max(0, audio.currentTime + skipTime), audio.duration);
                    updateCurrentTime(audio, id);
                });

                // Mise à jour de l'affichage du temps actuel
                audio.addEventListener('timeupdate', function() {
                    updateCurrentTime(audio, id);
                });

                // Fonction pour formater le temps
                function formatTime(seconds) {
                    const minutes = Math.floor(seconds / 60).toString().padStart(2, '0');
                    const remainingSeconds = Math.floor(seconds % 60).toString().padStart(2, '0');
                    return `${minutes}:${remainingSeconds}`;
                }

                // Mise à jour du temps actuel
                function updateCurrentTime(audio, id) {
                    $currentTimeDisplay.text(formatTime(audio.currentTime));
                }
            }

            // Initialisation de la lecture et de l'affichage des durées pour les éléments audio existants
            $('.custom-audio-player audio').each(function() {
                const audioId = $(this).attr('id').replace('audio-', '');
                initializeAudioPlayer(audioId);
            });
        });
    </script>
</body>

</html>
