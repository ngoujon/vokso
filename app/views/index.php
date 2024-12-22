<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>QWAI POD</title>
    <link rel="stylesheet" href="../assets/styles.css">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
</head>

<body>
    <!-- Premier bloc : Formulaire de génération -->
    <div id="input-form">
        <h1>Générer un podcast</h1>
        <form id="form" method="POST" action="index.php">
            <input type="text" name="user_input" id="user_input" placeholder="Saisir un sujet / thème" required autocomplete="off">
            <div class="button-loader-container">
                <button type="submit" id="submit-button"><i class="bi custom-icons bi-plus-square"></i></button>
                <div class="loader" id="loader" style="display: none;"></div>
            </div>
        </form>
        <div id="response" class="response"></div>
        <div id="completed-message" class="completed-message" style="display: none;"></div>
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
                                <button class="skipBtn" data-id="<?= $item['generation']['id'] ?>" data-skip="-10"><i class="bi custom-icons bi-skip-backward"></i></button>
                                <button class="playPauseBtn" data-id="<?= $item['generation']['id'] ?>"><i class="bi custom-icons bi-play"></i></button>
                                <button class="skipBtn" data-id="<?= $item['generation']['id'] ?>" data-skip="10"><i class="bi custom-icons bi-skip-forward"></i></button>
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
            let isGenerating = false; // Variable pour vérifier si une génération est en cours
            // Fonction de recherche en temps réel
            let searchTimeout;

            $('#user_input').on('input', function() {
                let query = $(this).val();

                // Réinitialise le timer à chaque frappe
                clearTimeout(searchTimeout);

                if (query.length > 2) {
                    // Définit un délai de 2 secondes avant de lancer la recherche
                    searchTimeout = setTimeout(() => {
                        // Effectue la requête AJAX après 2 secondes d'inactivité
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
                                            '<button class="skipBtn" data-id="' + item.id + '" data-skip="-10"><i class="bi custom-icons bi-skip-backward"></i></button>' +
                                            '<button class="playPauseBtn" data-id="' + item.id + '"><i class="bi custom-icons bi-play"></i></button>' +
                                            '<button class="skipBtn" data-id="' + item.id + '" data-skip="10"><i class="bi custom-icons bi-skip-forward"></i></button>' +
                                            '</div>' +
                                            '<div class="time-display">' +
                                            '<span id="currentTime-' + item.id + '">00:00</span> / ' +
                                            '<span id="duration-' + item.id + '">Chargement...</span>' +
                                            '</div>' +
                                            '</div>');
                                        resultsList.append(listItem);
                                        initializeAudioPlayer(item.id);
                                    });
                                }
                                else {
                                    resultsList.append('Aucun résultat trouvé');
                                }
                            }
                        });
                    }, 500); 
                } else {
                    // Vide les résultats si la longueur de la saisie est inférieure ou égale à 2
                    $('#search-results-list').empty();
                }
            });


            // Initialisation des éléments audio dans la recherche en temps réel
            function initializeAudioPlayer(id) {
                const audio = document.getElementById('audio-' + id);
                const $durationDisplay = $('#duration-' + id);
                const $currentTimeDisplay = $('#currentTime-' + id);
                const $playPauseBtn = $('[data-id="' + id + '"].playPauseBtn');

                audio.addEventListener('loadedmetadata', function() {
                    if (!isNaN(audio.duration)) {
                        $durationDisplay.text(formatTime(audio.duration)); // Afficher la durée dès que possible
                    } else {
                        $durationDisplay.text('Durée Indisponible');
                    }
                });

                if (audio.duration && !isNaN(audio.duration)) {
                    $durationDisplay.text(formatTime(audio.duration));
                }

                $playPauseBtn.on('click', function() {
                    if (audio.paused) {
                        audio.play();
                        $(this).html('<i class="bi custom-icons bi-pause"></i>');
                    } else {
                        audio.pause();
                        $(this).html('<i class="bi custom-icons bi-play"></i>');
                    }
                    updateCurrentTime(audio, id);
                });

                $('.skipBtn[data-id="' + id + '"]').on('click', function() {
                    const skipTime = $(this).data('skip');
                    audio.currentTime = Math.min(Math.max(0, audio.currentTime + skipTime), audio.duration);
                    updateCurrentTime(audio, id);
                });

                audio.addEventListener('timeupdate', function() {
                    updateCurrentTime(audio, id);
                });

                function formatTime(seconds) {
                    const minutes = Math.floor(seconds / 60).toString().padStart(2, '0');
                    const remainingSeconds = Math.floor(seconds % 60).toString().padStart(2, '0');
                    return `${minutes}:${remainingSeconds}`;
                }

                function updateCurrentTime(audio, id) {
                    $currentTimeDisplay.text(formatTime(audio.currentTime));
                }
            }

            $('.custom-audio-player audio').each(function() {
                const audioId = $(this).attr('id').replace('audio-', '');
                initializeAudioPlayer(audioId);
            });

            // Soumission du formulaire : Masquer le bouton et afficher le cercle de chargement
            $('#form').on('submit', function(event) {
                event.preventDefault();

                if (isGenerating) return;

                isGenerating = true;
                $('#submit-button').hide();
                $('#loader').show();
                $('#completed-message').hide();

                const startTime = Date.now(); // Commence à mesurer le temps de génération

                $('#completed-message').text("Temps de génération : Chargement...").show();

                $.ajax({
                    url: $(this).attr('action'),
                    method: $(this).attr('method'),
                    data: $(this).serialize(),
                    success: function(response) {
                        const endTime = Date.now(); // Fin de la mesure du temps de génération
                        const generationTime = ((endTime - startTime) / 1000).toFixed(2); // Calcul du temps en secondes

                        $('#submit-button').show();
                        $('#loader').hide();

                        $('#last-generations').html($(response).find('#last-generations').html());

                        $('#completed-message').text("Temps de génération : " + generationTime + " secondes").show();

                        isGenerating = false;
                    },
                    error: function() {
                        $('#submit-button').show();
                        $('#loader').hide();
                        $('#completed-message').text("Une erreur s'est produite.").show();
                        isGenerating = false;
                    }
                });
            });
        });
    </script>
</body>

</html>