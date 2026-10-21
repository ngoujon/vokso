<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\AiBudget;
use App\Support\AiBudgetExceeded;
use App\Support\Stats;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AdminDashboardTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Stats::reset();
    }

    private function publishEpisode(string $id = 'gen_abc'): void
    {
        DB::table('generations')->insert([
            'generation_id' => $id, 'title' => 'Titre', 'slug' => $id, 'text_content' => 'Texte',
            'image_url' => 'a.png', 'audio_url' => 'a.mp3', 'statut' => 'on', 'cost_total' => 0.2,
        ]);
    }

    public function test_only_admins_can_log_in(): void
    {
        $this->createUser(['email' => 'user@example.com']);
        $this->createUser(['email' => 'admin@example.com', 'role' => 'admin']);

        $this->postJson('/auth-login', ['email' => 'user@example.com', 'password' => 'MotDePasse123'])->assertStatus(403);
        $this->postJson('/auth-login', ['email' => 'admin@example.com', 'password' => 'MotDePasse123'])->assertOk();
    }

    public function test_sole_admin_command_disables_every_other_account(): void
    {
        $other = $this->createUser(['email' => 'old@example.com', 'role' => 'admin']);
        $token = $this->authHeaders($other);

        $this->artisan('vokso:sole-admin', ['email' => 'Boss@Example.com'])
            ->expectsQuestion('Mot de passe (12 caractères minimum, lettres et chiffres)', 'NouveauSecret42')
            ->expectsQuestion('Confirmez le mot de passe', 'NouveauSecret42')
            ->assertSuccessful();

        $boss = User::where('email', 'boss@example.com')->first();
        $this->assertTrue($boss->isAdmin() && $boss->isActive() && ! $boss->must_change_password);
        $this->assertSame('disabled', $other->fresh()->status);
        $this->getJson('/auth-me', $token)->assertStatus(401);
    }

    public function test_events_are_recorded_and_reported_in_stats(): void
    {
        $this->publishEpisode();

        $this->postJson('/podcast-event', ['generation_id' => 'gen_abc', 'type' => 'play'])->assertOk();
        $this->postJson('/podcast-event', ['generation_id' => 'gen_abc', 'type' => 'download_audio'])->assertOk();
        $this->postJson('/podcast-event', ['generation_id' => 'inconnu', 'type' => 'play'])->assertOk();
        $this->postJson('/podcast-event', ['generation_id' => 'gen_abc', 'type' => 'hack'])->assertStatus(400);

        $admin = $this->createUser(['role' => 'admin']);
        $this->getJson('/admin-stats?days=7', $this->authHeaders($admin))
            ->assertOk()
            ->assertJsonPath('current.plays', 1)
            ->assertJsonPath('current.downloads', 1)
            ->assertJsonPath('current.generations', 1)
            ->assertJsonCount(7, 'series')
            ->assertJsonPath('top_episodes.0.generation_id', 'gen_abc');
    }

    public function test_consents_are_recorded_and_exported_as_csv(): void
    {
        $id = '0f8fad5b-d9cb-469f-a165-70867728950e';
        $this->postJson('/consent', ['consent_id' => $id, 'choice' => 'refused', 'version' => '2026-10', 'page' => '/'])
            ->assertOk()
            ->assertJsonPath('recorded', true);
        $this->postJson('/consent', ['consent_id' => 'pas-un-uuid', 'choice' => 'accepted', 'version' => '2026-10'])->assertStatus(400);

        $row = DB::table('cookie_consents')->first();
        $this->assertSame(64, strlen($row->ip_hash));
        $this->assertNotSame('127.0.0.1', $row->ip_hash);

        $headers = $this->authHeaders($this->createUser(['role' => 'admin']));
        $this->getJson('/admin-consents', $headers)->assertOk()->assertJsonPath('refused', 1);

        $csv = $this->get('/admin-consents-export', $headers)->assertOk()->streamedContent();
        $this->assertStringContainsString($id, $csv);
        $this->assertStringContainsString('refuse', $csv);
    }

    public function test_ai_budget_blocks_calls_beyond_the_cap(): void
    {
        config(['vokso.ai_budget.image' => ['hourly' => 2, 'daily' => 10]]);

        AiBudget::consume('image');
        AiBudget::consume('image');

        $this->expectException(AiBudgetExceeded::class);
        AiBudget::consume('image');
    }
}
