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
        <?php foreach ($generations_with_files as $item): ?>
            <div class="generation-item">
                <h3><?php echo htmlspecialchars($item['generation']['title']); ?></h3>

                <?php if (isset($item['files']['image'])): ?>
                    <img src="/output/<?php echo htmlspecialchars($item['files']['image']); ?>" alt="Image de la génération">
                <?php else: ?>
                    <p>Aucune image disponible pour cette génération.</p>
                <?php endif; ?>

                <?php if (isset($item['files']['audio'])): ?>
                    <audio controls>
                        <source src="/output/<?php echo htmlspecialchars($item['files']['audio']); ?>" type="audio/mp3">
                        Votre navigateur ne prend pas en charge la lecture audio.
                    </audio>
                <?php else: ?>
                    <p>Aucun fichier audio disponible pour cette génération.</p>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    </div>

    <script>
        $(document).ready(function() {
            $('#form').on('submit', function(event) {
                event.preventDefault();
                $('#loader').show();
                $('#submit-button').hide();
                $('#response').empty();
                $('#completed-message').empty();

                $.ajax({
                    url: '/index.php',
                    type: 'POST',
                    data: $(this).serialize(),
                    dataType: 'json',
                    success: function(data) {
                        $('#loader').hide();
                        $('#submit-button').show();
                        $('#completed-message').text(`Temps total de génération : ${data.total_time} secondes`);
                    },
                    error: function() {
                        $('#loader').hide();
                        $('#submit-button').show();
                        $('#response').text('Une erreur est survenue. Veuillez réessayer.');
                    }
                });
            });
        });
    </script>

</body>

</html>
