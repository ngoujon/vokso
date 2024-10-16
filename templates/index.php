<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Assistant AI</title>
    <style>
        #progress {
            width: 100%;
            background-color: #f3f3f3;
            border: 1px solid #ccc;
            border-radius: 5px;
            overflow: hidden;
        }
        #progress-bar {
            height: 20px;
            width: 0;
            background-color: #4caf50;
            text-align: center;
            line-height: 20px;
            color: white;
        }
    </style>
</head>
<body>

<h1>Assistant AI</h1>

<form id="input-form" action="/ask" method="POST" onsubmit="showProgress();">
    <textarea name="user_input" placeholder="Posez votre question ici..." required></textarea>
    <button type="submit">Envoyer</button>
</form>

<div id="progress">
    <div id="progress-bar">0%</div>
</div>

<!-- Affichage de la réponse -->
{% if bot_response %}
    <h2>Réponse :</h2>
    <p>{{ bot_response }}</p>
    <audio controls>
        <source src="{{ audio_file }}" type="audio/mpeg">
        Votre navigateur ne prend pas en charge l'élément audio.
    </audio>
    <img src="{{ image_file }}" alt="Image générée" style="max-width: 100%;">
{% endif %}

<script>
    function showProgress() {
        let progressBar = document.getElementById('progress-bar');
        let width = 0;

        // Simuler la progression
        const interval = setInterval(() => {
            if (width >= 100) {
                clearInterval(interval);
            } else {
                width++;
                progressBar.style.width = width + '%';
                progressBar.innerText = width + '%';
            }
        }, 50); // Ajustez le temps de progression ici
    }
</script>

</body>
</html>
