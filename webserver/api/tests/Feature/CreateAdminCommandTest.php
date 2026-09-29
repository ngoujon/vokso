<?php

namespace Tests\Feature;

use App\Models\User;
use Tests\TestCase;

class CreateAdminCommandTest extends TestCase
{
    public function test_it_creates_an_admin_who_must_change_password(): void
    {
        $this->artisan('vokso:create-admin', ['email' => 'Admin@Example.com'])
            ->expectsQuestion('Mot de passe temporaire (12 caractères minimum, lettres et chiffres)', 'MotDePasse123')
            ->assertSuccessful();

        $admin = User::where('email', 'admin@example.com')->first();
        $this->assertTrue($admin->isAdmin());
        $this->assertTrue($admin->must_change_password);
        $this->assertTrue(password_verify('MotDePasse123', $admin->password_hash));
    }

    public function test_it_rejects_a_weak_password(): void
    {
        $this->artisan('vokso:create-admin', ['email' => 'admin@example.com'])
            ->expectsQuestion('Mot de passe temporaire (12 caractères minimum, lettres et chiffres)', 'court')
            ->assertFailed();

        $this->assertSame(0, User::count());
    }
}
