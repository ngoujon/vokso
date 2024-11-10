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
        <h2>Dernières générations :</h2> <!-- Nouveau titre ajouté -->
        <div class="last-generations-container"> <!-- Conteneur Flexbox pour les générations -->
            <?php foreach ($generations_with_files as $item): ?>
                <div class="generation-item">
                    <h3><?php echo htmlspecialchars($item['generation']['title']); ?></h3>

                    <?php if (isset($item['files']['image'])): ?>
                        <img src="/output/<?php echo htmlspecialchars($item['files']['image']); ?>" alt="Image de la génération">
                    <?php else: ?>
                        <p>Aucune image disponible pour cette génération.</p>
                    <?php endif; ?>

                    <?php if (isset($item['files']['audio'])): ?>
                        <div class="custom-audio-player">
                            <audio id="audio-<?php echo $item['generation']['id']; ?>" src="/output/<?php echo htmlspecialchars($item['files']['audio']); ?>" preload="auto"></audio>
                            <div class="controls">
                                <!-- Bouton Reculer 10 secondes -->
                                <button class="skipBtn" id="skipBack-<?php echo $item['generation']['id']; ?>">-10s</button>

                                <!-- Bouton Play/Pause -->
                                <button class="playPauseBtn" id="playPauseBtn-<?php echo $item['generation']['id']; ?>">Play</button>

                                <!-- Bouton Avancer 10 secondes -->
                                <button class="skipBtn" id="skipForward-<?php echo $item['generation']['id']; ?>">+10s</button>
                            </div>
                            <div class="time-display">
                                    <span id="currentTime-<?php echo $item['generation']['id']; ?>">00:00</span> /
                                    <span id="duration-<?php echo $item['generation']['id']; ?>">00:00</span>
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
        $(document).ready(function() {
            $('#form').on('submit', function(event) {
                event.preventDefault();
                $('#loader').show(); // Afficher le cercle de chargement
                $('#submit-button').hide(); // Masquer le bouton
                $('#response').empty();
                $('#completed-message').empty();

                $.ajax({
                    url: '/index.php',
                    type: 'POST',
                    data: $(this).serialize(),
                    dataType: 'json',
                    success: function(data) {
                        $('#loader').hide(); // Masquer le cercle de chargement
                        $('#submit-button').show(); // Afficher le bouton
                        $('#completed-message').text(`Temps total de génération : ${data.total_time} s`);
                    },
                    error: function() {
                        $('#loader').hide(); // Masquer le cercle de chargement
                        $('#submit-button').show(); // Afficher le bouton
                        $('#response').text('Une erreur est survenue. Veuillez réessayer.');
                    }
                });
            });
        });
    </script>
    <script>
        $(document).ready(function() {
            // Gérer les boutons Play/Pause et afficher les temps
            $('[id^="playPauseBtn-"]').each(function() {
                const btn = $(this);
                const audioId = btn.attr('id').replace('playPauseBtn-', '');
                const audio = document.getElementById('audio-' + audioId);
                const currentTimeDisplay = $('#currentTime-' + audioId);
                const durationDisplay = $('#duration-' + audioId);

                // Afficher la durée totale de l'audio au chargement
                audio.addEventListener('loadedmetadata', function() {
                    const duration = audio.duration;
                    durationDisplay.text(formatTime(duration));
                });

                // Play/Pause
                btn.on('click', function() {
                    if (audio.paused) {
                        audio.play();
                        btn.text('Pause');
                    } else {
                        audio.pause();
                        btn.text('Play');
                    }
                });

                // Mettre à jour le temps de lecture
                audio.addEventListener('timeupdate', function() {
                    currentTimeDisplay.text(formatTime(audio.currentTime));
                });

                // Fonction pour formater le temps en minutes:secondes
                function formatTime(seconds) {
                    const minutes = Math.floor(seconds / 60);
                    const remainingSeconds = Math.floor(seconds % 60);
                    return `${minutes.toString().padStart(2, '0')}:${remainingSeconds.toString().padStart(2, '0')}`;
                }

                // Gérer le bouton reculer de 10 secondes
                $('#skipBack-' + audioId).on('click', function() {
                    audio.currentTime = Math.max(0, audio.currentTime - 10); // Reculer de 10 secondes, ne pas aller en dessous de 0
                });

                // Gérer le bouton avancer de 10 secondes
                $('#skipForward-' + audioId).on('click', function() {
                    audio.currentTime = Math.min(audio.duration, audio.currentTime + 10); // Avancer de 10 secondes, ne pas dépasser la durée
                });
            });
        });
    </script>




</body>

</html>