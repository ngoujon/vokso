<?php

namespace Tests\Feature;

use App\Models\AuthToken;
use App\Support\Totp;
use Tests\TestCase;

class AuthTest extends TestCase
{
    public function test_public_registration_is_gone(): void
    {
        $this->postJson('/auth-register', ['email' => 'a@example.com', 'password' => 'MotDePasse123'])->assertNotFound();
    }

    public function test_login_rejects_bad_credentials_and_disabled_accounts(): void
    {
        $this->createUser(['email' => 'off@example.com', 'status' => 'disabled']);

        $this->postJson('/auth-login', ['email' => 'off@example.com', 'password' => 'mauvais-mdp'])->assertStatus(401);
        $this->postJson('/auth-login', ['email' => 'off@example.com', 'password' => 'MotDePasse123'])->assertStatus(403);
    }

    public function test_login_is_rate_limited_per_ip(): void
    {
        config(['vokso.rate_limit.max_requests' => 2]);

        $this->postJson('/auth-login', ['email' => 'x@example.com', 'password' => 'MotDePasse123'])->assertStatus(401);
        $this->postJson('/auth-login', ['email' => 'x@example.com', 'password' => 'MotDePasse123'])->assertStatus(401);
        $this->postJson('/auth-login', ['email' => 'x@example.com', 'password' => 'MotDePasse123'])->assertStatus(429);
    }

    public function test_admin_with_2fa_must_provide_a_valid_code(): void
    {
        $secret = Totp::generateSecret();
        $this->createUser(['email' => 'admin@example.com', 'role' => 'admin'])
            ->forceFill(['totp_secret' => $secret, 'totp_enabled' => true])->save();

        $this->postJson('/auth-login', ['email' => 'admin@example.com', 'password' => 'MotDePasse123'])
            ->assertStatus(401)
            ->assertJsonPath('code', 'totp_required');
    }

    public function test_logout_revokes_the_token(): void
    {
        $headers = $this->authHeaders($this->createUser());

        $this->getJson('/auth-me', $headers)->assertOk();
        $this->postJson('/auth-logout', [], $headers)->assertOk();
        $this->getJson('/auth-me', $headers)->assertStatus(401);
    }

    public function test_pending_password_change_blocks_everything_but_the_change(): void
    {
        $user = $this->createUser(['must_change_password' => true]);
        $headers = $this->authHeaders($user);

        $this->getJson('/auth-me', $headers)->assertOk();
        $this->getJson('/user-podcasts', $headers)->assertStatus(403)->assertJsonPath('code', 'password_change_required');

        $this->postJson('/auth-change-password', ['current_password' => 'MotDePasse123', 'new_password' => 'NouveauMdp456'], $headers)
            ->assertOk();
        $this->getJson('/user-podcasts', $headers)->assertOk();
    }

    public function test_admin_routes_are_reserved_to_admins(): void
    {
        $this->getJson('/admin-kpis')->assertStatus(401);
        $this->getJson('/admin-kpis', $this->authHeaders($this->createUser()))->assertStatus(403);
        $this->getJson('/admin-kpis', $this->authHeaders($this->createUser(['role' => 'admin'])))
            ->assertOk()
            ->assertJsonStructure(['total_users', 'total_podcasts', 'cost' => ['total'], 'jobs_by_status']);
    }

    public function test_disabling_an_account_revokes_its_sessions(): void
    {
        $user = $this->createUser();
        $userHeaders = $this->authHeaders($user);
        $adminHeaders = $this->authHeaders($this->createUser(['role' => 'admin']));

        $this->patchJson('/admin-users', ['id' => $user->id, 'status' => 'disabled'], $adminHeaders)->assertOk();
        $this->getJson('/auth-me', $userHeaders)->assertStatus(401);
    }
}
