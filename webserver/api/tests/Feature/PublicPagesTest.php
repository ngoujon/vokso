<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Generation;
use Tests\TestCase;

class PublicPagesTest extends TestCase
{
    private function publish(string $id, string $title, ?string $category = null, string $statut = 'on'): void
    {
        $categoryId = $category ? Category::firstOrCreate(['label' => $category])->idcategorie : 0;

        Generation::create([
            'generation_id' => $id,
            'title' => $title,
            'text_content' => 'Texte de l\'épisode '.$title,
            'image_url' => $id.'.webp',
            'audio_url' => $id.'.mp3',
            'idcategorie' => $categoryId,
        ])->forceFill(['statut' => $statut])->save();
    }

    public function test_home_and_ping(): void
    {
        $this->get('/')->assertOk()->assertSee('api url ok');
        $this->postJson('/ping', ['value' => 'ping'])->assertOk()->assertJson(['message' => 'pong']);
        $this->postJson('/ping', ['value' => 'pong'])->assertStatus(400);
        $this->getJson('/ping')->assertStatus(405);
        $this->getJson('/route-inconnue')->assertNotFound()->assertJson(['error' => 'Route inconnue']);
    }

    public function test_listing_search_and_categories_only_show_published_episodes(): void
    {
        $this->publish('gen_1', 'Les volcans', 'Science');
        $this->publish('gen_2', 'Le jazz', 'Musique');
        $this->publish('gen_3', 'Brouillon volcanique', 'Science', 'off');

        $this->getJson('/listing?limit=10')->assertOk()->assertJsonCount(2, 'data');
        $this->getJson('/listing?limit=1&offset=1')->assertOk()->assertJsonCount(1, 'data');
        $this->getJson('/listing?limit=10&offset=2')->assertOk()->assertJsonCount(0, 'data');
        $this->getJson('/listing?category=Science')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', 'gen_1');

        $this->getJson('/search?query=volcan')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('success', true);
        $this->getJson('/search?query=vo')->assertOk()->assertJsonPath('success', false);
        $this->getJson('/search?query=100%')->assertOk()->assertJsonCount(0, 'data');

        $this->getJson('/categories')->assertOk()->assertJsonCount(2, 'data');
    }

    public function test_episode_page_is_server_rendered_and_escaped(): void
    {
        $this->publish('gen_abc', '**Les <volcans>**', 'Science');

        $this->get('/podcast/gen_abc-les-volcans')
            ->assertOk()
            ->assertHeader('Content-Type', 'text/html; charset=utf-8')
            ->assertSee('<title>Les &lt;volcans&gt; — Vokso</title>', false)
            ->assertSee('"@type":"PodcastEpisode"', false)
            ->assertSee('https://vokso.fr/podcast/gen_abc-les-volcans', false);

        $this->get('/podcast/gen_inconnu')->assertNotFound()->assertHeader('X-Robots-Tag', 'noindex');
    }

    public function test_sitemap_lists_published_episodes(): void
    {
        $this->publish('gen_1', 'Les volcans');
        $this->publish('gen_2', 'Caché', null, 'off');

        $this->get('/sitemap')
            ->assertOk()
            ->assertHeader('Content-Type', 'application/xml; charset=utf-8')
            ->assertSee('<loc>https://vokso.fr/podcast/gen_1-les-volcans</loc>', false)
            ->assertDontSee('gen_2');
    }

    public function test_user_sees_only_their_own_podcasts(): void
    {
        $user = $this->createUser();
        $this->publish('gen_mine', 'À moi');
        Generation::where('generation_id', 'gen_mine')->update(['user_id' => $user->id]);
        $this->publish('gen_other', 'Pas à moi');

        $this->getJson('/user-podcasts', $this->authHeaders($user))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.title', 'À moi');
    }

    public function test_cors_allows_only_listed_origins(): void
    {
        config(['cors.allowed_origins' => ['https://vokso.fr']]);

        $this->getJson('/listing', ['Origin' => 'https://vokso.fr'])
            ->assertHeader('Access-Control-Allow-Origin', 'https://vokso.fr');
        $this->assertNotSame(
            'https://evil.example',
            $this->getJson('/listing', ['Origin' => 'https://evil.example'])->headers->get('Access-Control-Allow-Origin')
        );
    }
}
