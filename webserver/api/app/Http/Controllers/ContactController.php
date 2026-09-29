<?php

namespace App\Http\Controllers;

use App\Services\Mailer;
use App\Support\CaptchaService;
use App\Support\RateLimiter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Log;

/** Formulaire de contact et inscription à la newsletter (anti-spam sans service tiers). */
class ContactController extends Controller
{
    private const MAX_NAME_LENGTH = 200;
    private const MAX_MESSAGE_LENGTH = 5000;

    /** Jeton invisible à récupérer au chargement du formulaire, voir CaptchaService. */
    public function challenge(): JsonResponse
    {
        return response()->json(['token' => $this->captcha()->issueToken()]);
    }

    public function send(Request $request, Mailer $mailer): JsonResponse
    {
        if (RateLimiter::fromConfig()->tooManyRequests((string) $request->ip(), 'contact')) {
            return $this->error('Trop de messages envoyés, réessayez plus tard.', 429);
        }

        // Champ piège invisible : un humain ne le remplit jamais. On répond
        // succès pour ne pas signaler l'échec aux robots.
        if (trim((string) $request->json('website', '')) !== '') {
            return response()->json(['message' => 'Message envoyé, merci !']);
        }

        $token = $request->json('captchaToken');
        if (! $this->captcha()->verify(is_string($token) ? $token : null)) {
            return $this->error('Vérification anti-spam échouée, rechargez la page et réessayez.', 400);
        }

        $name = trim((string) $request->json('name', ''));
        $email = trim((string) $request->json('email', ''));
        $message = trim((string) $request->json('message', ''));

        // Le nom finit dans le corps du mail : pas de retour à la ligne.
        if ($name === '' || mb_strlen($name) > self::MAX_NAME_LENGTH || preg_match('/[\r\n]/', $name)) {
            return $this->error('Nom invalide.', 400);
        }
        if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return $this->error('Adresse email invalide.', 400);
        }
        if ($message === '' || mb_strlen($message) > self::MAX_MESSAGE_LENGTH) {
            return $this->error('Message invalide.', 400);
        }

        $sent = $mailer->send(
            (string) config('vokso.contact_to'),
            'Nouveau message via le formulaire de contact',
            "De : {$name} <{$email}>\n\n{$message}",
            $email
        );

        if (! $sent) {
            return $this->error("L'envoi a échoué, réessayez plus tard.", 502);
        }

        return response()->json(['message' => 'Message envoyé, merci !']);
    }

    public function subscribeNewsletter(Request $request, Mailer $mailer): JsonResponse
    {
        if (RateLimiter::fromConfig()->tooManyRequests((string) $request->ip(), 'newsletter')) {
            return response()->json(['message' => 'Trop de tentatives, réessayez plus tard'], 429);
        }

        $confirmation = ['message' => 'Inscription confirmée, vérifiez vos mails'];

        // Champ piège invisible (voir site/index.html).
        if (trim((string) $request->json('website', '')) !== '') {
            return response()->json($confirmation);
        }

        $email = trim((string) $request->json('email', ''));
        if ($email === '' || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return response()->json(['message' => 'Adresse email invalide'], 400);
        }

        try {
            DB::table('newsletter_subscribers')->insert(['email' => $email]);
        } catch (QueryException $e) {
            // 23000 : l'email est déjà inscrit, ce n'est pas une erreur pour l'appelant
            // (et on n'envoie pas de second mail, pour ne pas servir de relais de spam).
            if ((string) $e->getCode() === '23000') {
                return response()->json($confirmation);
            }
            Log::error($e->getMessage(), ['controller' => 'newsletter']);

            return response()->json(['message' => "L'inscription a échoué, réessayez plus tard"], 500);
        }

        $mailer->send(
            $email,
            'Bienvenue sur Vokso',
            "Merci de votre inscription !\n\nVous serez prévenu par mail des nouveautés de Vokso (nouveaux modes, nouvelles voix).\n\nÀ bientôt,\nL'équipe Vokso"
        );

        return response()->json($confirmation);
    }

    private function captcha(): CaptchaService
    {
        return new CaptchaService((string) (config('vokso.captcha_secret') ?: config('app.key')));
    }
}
