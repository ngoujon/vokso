<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\Mailer;
use App\Support\PasswordPolicy;
use App\Support\RateLimiter;
use App\Support\TokenAuth;
use App\Support\Totp;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuthController extends Controller
{
    /** Inscription publique : toujours en rôle "user", les comptes admin se créent depuis l'espace admin. */
    public function register(Request $request, Mailer $mailer): JsonResponse
    {
        if (RateLimiter::fromConfig()->tooManyRequests((string) $request->ip(), 'auth-register')) {
            return $this->error('Trop de tentatives, réessayez plus tard.', 429);
        }

        // Champ piège invisible (voir Login.tsx) : un humain ne le remplit
        // jamais. Même erreur générique que pour un email invalide, pour ne
        // pas signaler l'échec aux robots.
        if (trim((string) $request->json('website', '')) !== '') {
            return $this->error('Inscription impossible, réessayez plus tard.', 400);
        }

        [$email, $password, $error] = $this->readCredentials($request);
        if ($error !== null) {
            return $this->error($error, 400);
        }

        $policyError = PasswordPolicy::validate($password);
        if ($policyError !== null) {
            return $this->error($policyError, 400);
        }

        if (User::where('email', $email)->exists()) {
            return $this->error('Un compte existe déjà avec cet email.', 409);
        }

        $user = User::create([
            'email' => $email,
            'password_hash' => password_hash($password, PASSWORD_BCRYPT),
            'role' => 'user',
            'status' => 'active',
        ]);

        // Un échec d'envoi ne bloque jamais la création du compte.
        $mailer->send(
            $email,
            'Bienvenue sur Vokso',
            "Votre compte Vokso est créé.\n\n"
            ."Vokso est entièrement gratuit. Vos podcasts sont générés sur une infrastructure hébergée en Europe : "
            ."vos données ne servent jamais à entraîner un modèle tiers.\n\n"
            ."Vous pouvez dès maintenant générer votre premier épisode depuis votre espace.\n\n"
            ."À bientôt,\nL'équipe Vokso"
        );

        return response()->json([
            'token' => TokenAuth::issue($user),
            'user' => ['id' => $user->id, 'email' => $email, 'role' => 'user'],
        ], 201);
    }

    public function login(Request $request): JsonResponse
    {
        // Limite par IP : le brute-force ou le credential stuffing émettent
        // des dizaines de requêtes, un humain qui se trompe rarement plus de
        // quelques-unes.
        if (RateLimiter::fromConfig()->tooManyRequests((string) $request->ip(), 'auth-login')) {
            return $this->error('Trop de tentatives, réessayez plus tard.', 429);
        }

        [$email, $password, $error] = $this->readCredentials($request);
        if ($error !== null) {
            return $this->error($error, 400);
        }

        $user = User::where('email', $email)->first();
        if (! $user || ! password_verify($password, $user->password_hash)) {
            return $this->error('Email ou mot de passe incorrect.', 401);
        }

        if (! $user->isActive()) {
            return $this->error('Ce compte est désactivé.', 403);
        }

        // 2FA TOTP pour les admins qui l'ont activée.
        if ($user->isAdmin() && $user->totp_enabled) {
            $code = (string) $request->json('totp_code', '');
            if ($code === '') {
                return $this->error('Code de double authentification requis.', 401, ['code' => 'totp_required']);
            }
            if (! Totp::verify((string) $user->totp_secret, $code)) {
                return $this->error('Code de double authentification invalide.', 401, ['code' => 'totp_required']);
            }
        }

        return response()->json([
            'token' => TokenAuth::issue($user),
            'user' => $user->toApiArray(),
        ]);
    }

    public function me(Request $request): JsonResponse
    {
        return response()->json(['user' => $request->user()->toApiArray()]);
    }

    public function logout(Request $request): JsonResponse
    {
        $token = $request->bearerToken();
        if (is_string($token) && $token !== '') {
            TokenAuth::revoke($token);
        }

        return response()->json(['success' => true]);
    }

    /** Changement de mot de passe, y compris pour lever un changement imposé. */
    public function changePassword(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        if (! password_verify((string) $request->json('current_password', ''), $user->password_hash)) {
            return $this->error('Mot de passe actuel incorrect.', 401);
        }

        $newPassword = (string) $request->json('new_password', '');
        $policyError = PasswordPolicy::validate($newPassword);
        if ($policyError !== null) {
            return $this->error($policyError, 400);
        }

        $user->forceFill([
            'password_hash' => password_hash($newPassword, PASSWORD_BCRYPT),
            'must_change_password' => false,
        ])->save();

        return response()->json(['success' => true]);
    }

    /** Étape 1 de l'activation 2FA (admin) : génère un secret en attente de confirmation. */
    public function twoFactorSetup(Request $request): JsonResponse
    {
        /** @var User $admin */
        $admin = $request->user();
        $secret = Totp::generateSecret();
        $admin->forceFill(['totp_secret' => $secret, 'totp_enabled' => false])->save();

        return response()->json([
            'secret' => $secret,
            'otpauth_uri' => Totp::provisioningUri($secret, $admin->email),
        ]);
    }

    /** Étape 2 : confirme la possession du secret via un code TOTP avant d'activer la 2FA. */
    public function twoFactorEnable(Request $request): JsonResponse
    {
        /** @var User $admin */
        $admin = $request->user();
        $code = (string) $request->json('totp_code', '');

        if (! $admin->totp_secret || ! Totp::verify((string) $admin->totp_secret, $code)) {
            return $this->error('Code invalide, réessayez.', 400);
        }

        $admin->forceFill(['totp_enabled' => true])->save();

        return response()->json(['success' => true]);
    }

    /** Désactivation de la 2FA : exige le mot de passe, un jeton volé ne suffit pas. */
    public function twoFactorDisable(Request $request): JsonResponse
    {
        /** @var User $admin */
        $admin = $request->user();

        if (! password_verify((string) $request->json('password', ''), $admin->password_hash)) {
            return $this->error('Mot de passe incorrect.', 401);
        }

        $admin->forceFill(['totp_secret' => null, 'totp_enabled' => false])->save();

        return response()->json(['success' => true]);
    }

    /** @return array{0: ?string, 1: ?string, 2: ?string} [email, password, error] */
    private function readCredentials(Request $request): array
    {
        $email = $request->json('email');
        $password = $request->json('password');
        $email = is_string($email) ? trim(strtolower($email)) : '';
        $password = is_string($password) ? $password : '';

        if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return [null, null, 'Email invalide.'];
        }
        if (mb_strlen($password) < 8) {
            return [null, null, 'Le mot de passe doit contenir au moins 8 caractères.'];
        }

        return [$email, $password, null];
    }
}
