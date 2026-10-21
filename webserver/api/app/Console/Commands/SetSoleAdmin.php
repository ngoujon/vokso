<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Support\PasswordPolicy;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Fait d'un compte l'unique accès à l'administration : crée ou met à jour le
 * compte (rôle admin, mot de passe définitif), puis désactive tous les autres
 * et coupe leurs sessions. Le mot de passe est demandé de façon interactive
 * (ou lu sur l'entrée standard avec --stdin) : il n'apparaît ni dans
 * l'historique du shell ni dans le dépôt.
 */
class SetSoleAdmin extends Command
{
    protected $signature = 'vokso:sole-admin {email} {--stdin : lit le mot de passe sur l\'entrée standard}';

    protected $description = 'Définit le seul compte autorisé à se connecter (les autres sont désactivés)';

    public function handle(): int
    {
        $email = strtolower(trim((string) $this->argument('email')));
        if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->error('Email invalide.');

            return self::FAILURE;
        }

        if ($this->option('stdin')) {
            $password = rtrim((string) fgets(STDIN), "\r\n");
        } else {
            $password = (string) $this->secret('Mot de passe (12 caractères minimum, lettres et chiffres)');
            if ($password !== (string) $this->secret('Confirmez le mot de passe')) {
                $this->error('Les deux saisies diffèrent.');

                return self::FAILURE;
            }
        }

        $policyError = PasswordPolicy::validate($password);
        if ($policyError !== null) {
            $this->error($policyError);

            return self::FAILURE;
        }

        $disabled = DB::transaction(function () use ($email, $password) {
            $user = User::firstOrNew(['email' => $email]);
            $user->forceFill([
                'password_hash' => password_hash($password, PASSWORD_BCRYPT),
                'role' => 'admin',
                'status' => 'active',
                'must_change_password' => false,
            ])->save();

            $others = User::where('id', '<>', $user->id)->pluck('id');
            DB::table('auth_tokens')->whereIn('user_id', $others)->delete();

            return User::whereIn('id', $others)->update(['status' => 'disabled', 'role' => 'user']);
        });

        $this->info("Compte {$email} prêt. Autres comptes désactivés : {$disabled}.");

        return self::SUCCESS;
    }
}
