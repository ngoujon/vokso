<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class AccountTest extends TestCase
{
    public function test_gdpr_export_contains_the_account(): void
    {
        $user = $this->createUser(['email' => 'rgpd@example.com']);

        $this->get('/gdpr-export', $this->authHeaders($user))
            ->assertOk()
            ->assertHeader('Content-Disposition', 'attachment; filename="vokso-donnees-personnelles.json"')
            ->assertJsonPath('account.email', 'rgpd@example.com');
    }

    public function test_account_deletion_anonymises_and_requires_the_password(): void
    {
        $user = $this->createUser(['email' => 'bye@example.com']);
        $headers = $this->authHeaders($user);

        $this->postJson('/gdpr-delete-account', ['password' => 'mauvais'], $headers)->assertStatus(401);
        $this->postJson('/gdpr-delete-account', ['password' => 'MotDePasse123'], $headers)->assertOk();

        $user = User::find($user->id);
        $this->assertSame('disabled', $user->status);
        $this->assertStringStartsWith('deleted-user-', $user->email);
        $this->assertSame(0, DB::table('auth_tokens')->count());
    }

    public function test_invoices_are_hidden_from_other_users(): void
    {
        $owner = $this->createUser();
        $id = DB::table('invoices')->insertGetId([
            'user_id' => $owner->id, 'number' => 'VOKSO-2026-000001', 'issued_at' => now(), 'plan' => 'createur',
            'description' => 'Abonnement', 'amount_excl_tax' => 9, 'amount_total' => 9, 'client_snapshot' => '{}',
        ]);

        $this->getJson('/invoices', $this->authHeaders($owner))->assertOk()->assertJsonCount(1, 'data');
        $this->getJson('/invoice-download?id='.$id, $this->authHeaders($this->createUser()))->assertNotFound();
        // Vendeur non configuré : régénération impossible, mais pas d'erreur 500.
        $this->getJson('/invoice-download?id='.$id, $this->authHeaders($owner))->assertStatus(503);
    }

    public function test_contact_form_requires_a_valid_captcha_and_sends_the_message(): void
    {
        Mail::fake();
        config(['vokso.captcha_secret' => 'secret', 'vokso.contact_to' => 'contact@vokso.fr']);

        $message = ['name' => 'Alice', 'email' => 'alice@example.com', 'message' => 'Bonjour'];
        $this->postJson('/contact', $message + ['captchaToken' => 'faux'])->assertStatus(400);

        // Jeton émis il y a plus de 3 secondes (délai minimal anti-robot).
        $issuedAt = (string) (time() - 10);
        $token = base64_encode($issuedAt.'.'.hash_hmac('sha256', $issuedAt, 'secret'));

        $this->postJson('/contact', $message + ['captchaToken' => $token])->assertOk();
    }

    public function test_newsletter_subscription_is_idempotent(): void
    {
        Mail::fake();

        $this->postJson('/newsletter', ['email' => 'news@example.com'])->assertOk();
        $this->postJson('/newsletter', ['email' => 'news@example.com'])->assertOk();
        $this->postJson('/newsletter', ['email' => 'pas-un-email'])->assertStatus(400);

        $this->assertSame(1, DB::table('newsletter_subscribers')->count());
    }
}
