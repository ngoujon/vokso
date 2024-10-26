<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>QWAI POD</title>
    <link rel="stylesheet" href="../assets/styles.css"> <!-- Lien vers le fichier CSS -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
</head>

<body>
    <div id="input-form">
        <h1>Génerer un podcast</h1>
        <form id="form" method="POST" action="index.php"> <!-- Mise à jour de l'action -->
            <input type="text" name="user_input" placeholder="Saisir un sujet / thème" required>
            <button type="submit" id="submit-button">Génerer</button>
            </form>
        <div id="response" class="response"></div>
        <div id="completed-message" class="completed-message"></div>
        <div class="loader" id="loader" style="display: none;"></div> <!-- Animation de chargement -->
    </div>

    <script>
        $(document).ready(function() {
            // Événement de soumission du formulaire
            $('#form').on('submit', function(event) {
                event.preventDefault(); // Empêcher le comportement par défaut du formulaire
                $('#loader').show(); // Afficher l'animation de chargement
                $('#submit-button').hide(); // Cacher le bouton Envoyer
                $('#response').empty(); // Vider les réponses précédentes
                $('#completed-message').empty(); // Vider le message de complétion

                // Appel AJAX pour soumettre le formulaire
                $.ajax({
                    url: '../public/index.php', // Mise à jour du chemin
                    type: 'POST',
                    data: $(this).serialize(),
                    dataType: 'json',
                    success: function(data) {
                        $('#loader').hide(); // Cacher l'animation de chargement
                        $('#submit-button').show(); // Afficher le bouton Envoyer

                        // Afficher le nombre de tokens utilisés pour texte, image et audio
                        $('#completed-message').text(`Tokens utilisés : Texte - ${data.text_tokens}, Image - ${data.image_tokens}, Audio - ${data.audio_tokens}`);
                    },
                    error: function() {
                        $('#loader').hide(); // Cacher l'animation de chargement
                        $('#submit-button').show(); // Afficher le bouton Envoyer
                        $('#response').text('Une erreur est survenue. Veuillez réessayer.'); // Afficher un message d'erreur
                    }
                });
            });
        });
    </script>
</body>

</html>