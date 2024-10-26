<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Assistant AI</title>
    <link rel="stylesheet" href="../styles.css"> <!-- Lien vers le fichier CSS -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
</head>
<body>
    <div id="input-form">
        <h1>Assistant AI</h1>
        <form id="form" method="POST" action="index.php"> <!-- Mise à jour de l'action -->
            <input type="text" name="user_input" placeholder="Posez votre question..." required>
            <button type="submit">Envoyer</button>
        </form>
        <div class="loader" id="loader"></div> <!-- Animation de chargement -->
        <div id="response" class="response"></div>
        <div id="completed-message" class="completed-message"></div>
    </div>

    <script>
        $(document).ready(function() {
            // Événement de soumission du formulaire
            $('#form').on('submit', function(event) {
                event.preventDefault(); // Empêcher le comportement par défaut du formulaire
                $('#loader').show(); // Afficher l'animation de chargement
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

                        // Afficher uniquement le message de complétion et l'image générée
                        $('#completed-message').text(`Tokens utilisés : Texte - ${data.text_tokens}, Image - ${data.image_tokens}`);
                        // if (data.image_file) {
                        //     $('#response').append(`<img src="${data.image_file}" alt="Image générée">`);
                        // }
                    },
                    error: function() {
                        $('#loader').hide(); // Cacher l'animation de chargement
                        $('#response').text('Une erreur est survenue. Veuillez réessayer.'); // Afficher un message d'erreur
                    }
                });
            });
        });
    </script>
</body>
</html>
