<?php

namespace Tests;

use App\Models\User;
use App\Support\TokenAuth;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    use RefreshDatabase;

    protected function createUser(array $attributes = []): User
    {
        return User::create(array_merge([
            'email' => 'user'.uniqid().'@example.com',
            'password_hash' => password_hash('MotDePasse123', PASSWORD_BCRYPT),
            'role' => 'user',
            'status' => 'active',
        ], $attributes));
    }

    /**
     * Chaque requête HTTP réelle part d'un processus neuf : on oublie donc
     * l'utilisateur mémorisé par le garde entre deux requêtes d'un même test.
     */
    public function call($method, $uri, $parameters = [], $cookies = [], $files = [], $server = [], $content = null)
    {
        $this->app['auth']->forgetGuards();

        return parent::call($method, $uri, $parameters, $cookies, $files, $server, $content);
    }

    /** En-têtes d'une requête authentifiée par jeton Bearer. */
    protected function authHeaders(User $user): array
    {
        return ['Authorization' => 'Bearer '.TokenAuth::issue($user)];
    }
}
