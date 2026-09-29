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

    public function test_account_deletion_requires_the_password_and_deletes_the_account(): void
    {
        $user = $this->createUser(['email' => 'bye@example.com']);
        $headers = $this->authHeaders($user);
        DB::table('generations')->insert([
            'generation_id' => 'gen_1', 'text_content' => 'x', 'image_url' => 'a', 'audio_url' => 'b', 'user_id' => $user->id,
        ]);

        $this->postJson('/gdpr-delete-account', ['password' => 'mauvais'], $headers)->assertStatus(401);
        $this->postJson('/gdpr-delete-account', ['password' => 'MotDePasse123'], $headers)->assertOk();

        $this->assertNull(User::find($user->id));
        $this->assertSame(0, DB::table('auth_tokens')->count());
        $this->assertNull(DB::table('generations')->where('generation_id', 'gen_1')->value('user_id'));
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
