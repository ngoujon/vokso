<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Support\PasswordPolicy;
use Illuminate\Console\Command;

/**
 * Crée (ou promeut) un compte administrateur. Le mot de passe est demandé de
 * façon interactive : il n'apparaît ni dans l'historique du shell ni dans le
 * dépôt. Son changement est imposé à la première connexion.
 */
class CreateAdmin extends Command
{
    protected $signature = 'vokso:create-admin {email}';

    protected $description = 'Crée un compte administrateur (mot de passe demandé de façon interactive)';

    public function handle(): int
    {
        $email = strtolower(trim((string) $this->argument('email')));
        if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->error('Email invalide.');

            return self::FAILURE;
        }

        $password = (string) $this->secret('Mot de passe temporaire (12 caractères minimum, lettres et chiffres)');
        $policyError = PasswordPolicy::validate($password);
        if ($policyError !== null) {
            $this->error($policyError);

            return self::FAILURE;
        }

        $user = User::firstOrNew(['email' => $email]);
        $user->forceFill([
            'password_hash' => password_hash($password, PASSWORD_BCRYPT),
            'role' => 'admin',
            'status' => 'active',
            'must_change_password' => true,
        ])->save();

        $this->info("Compte administrateur {$email} prêt (changement de mot de passe imposé à la première connexion).");

        return self::SUCCESS;
    }
}
