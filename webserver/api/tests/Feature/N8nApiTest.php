<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Generation;
use App\Models\GenerationJob;
use Tests\TestCase;

class N8nApiTest extends TestCase
{
    private const TOKEN = 'jeton-de-test';

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'vokso.n8n.token_sha256' => hash('sha256', self::TOKEN),
            'vokso.n8n.allowed_ips' => ['127.0.0.1', '172.16.0.0/12'],
        ]);
    }

    private function headers(string $token = self::TOKEN): array
    {
        return ['Authorization' => 'Bearer '.$token];
    }

    public function test_n8n_creates_a_podcast_and_follows_it_until_published(): void
    {
        $response = $this->postJson('/n8n/podcasts', ['sujet' => 'Les anneaux de Saturne'], $this->headers())
            ->assertStatus(202)
            ->assertJsonPath('status', 'pending')
            ->assertJsonPath('sujet', 'Les anneaux de Saturne');

        $jobId = $response->json('job_id');
        $this->assertStringEndsWith('/api/n8n/podcasts/'.$jobId, $response->json('status_url'));

        $this->getJson('/n8n/podcasts/'.$jobId, $this->headers())
            ->assertOk()
            ->assertJsonPath('done', false)
            ->assertJsonPath('episode', null);

        $category = Category::create(['label' => 'Astronomie']);
        Generation::create(['generation_id' => 'gen_sat', 'title' => '**Saturne**', 'text_content' => 'x', 'image_url' => 'i.webp', 'audio_url' => 'a.mp3', 'idcategorie' => $category->idcategorie, 'statut' => 'on']);
        GenerationJob::whereKey($jobId)->update(['status' => 'done', 'step' => 'done', 'progress' => 100, 'generation_id' => 'gen_sat']);

        $this->getJson('/n8n/podcasts/'.$jobId, $this->headers())
            ->assertOk()
            ->assertJsonPath('done', true)
            ->assertJsonPath('episode.title', 'Saturne')
            ->assertJsonPath('episode.category', 'Astronomie')
            ->assertJsonPath('episode.url', 'https://vokso.fr/podcast/gen_sat-saturne');
    }

    public function test_n8n_api_requires_the_token_and_an_allowed_address(): void
    {
        $this->postJson('/n8n/podcasts', ['sujet' => 'Mars'])->assertStatus(403);
        $this->postJson('/n8n/podcasts', ['sujet' => 'Mars'], $this->headers('mauvais'))->assertStatus(403);

        config(['vokso.n8n.allowed_ips' => ['51.254.211.144']]);
        $this->postJson('/n8n/podcasts', ['sujet' => 'Mars'], $this->headers())->assertStatus(403);

        $this->assertSame(0, GenerationJob::count());
    }

    public function test_n8n_api_validates_the_subject_and_keeps_the_global_cap(): void
    {
        $this->postJson('/n8n/podcasts', ['sujet' => '  '], $this->headers())->assertStatus(422);
        $this->postJson('/n8n/podcasts', ['sujet' => str_repeat('a', 301)], $this->headers())->assertStatus(422);

        config(['vokso.generation_limits.global_daily' => 1, 'vokso.generation_limits.per_ip_hourly' => 0]);
        $this->postJson('/n8n/podcasts', ['sujet' => 'Mars'], $this->headers())->assertStatus(202);
        $this->postJson('/n8n/podcasts', ['sujet' => 'Vénus'], $this->headers())->assertStatus(429);
    }
}
