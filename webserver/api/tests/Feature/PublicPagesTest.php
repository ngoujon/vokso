<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Generation;
use Tests\TestCase;

class PublicPagesTest extends TestCase
{
    private function publish(string $id, string $title, ?string $category = null, string $statut = 'on', ?string $slug = null): void
    {
        $categoryId = $category ? Category::firstOrCreate(['label' => $category])->idcategorie : 0;

        Generation::create([
            'generation_id' => $id,
            'slug' => $slug,
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
        $this->publish('gen_abc', '**Les <volcans>**', 'Science', 'on', 'les-volcans');
        $this->publish('gen_def', 'Les séismes', 'Science', 'on', 'les-seismes');

        $this->get('/podcast/les-volcans')
            ->assertOk()
            ->assertHeader('Content-Type', 'text/html; charset=utf-8')
            ->assertSee('<title>Les &lt;volcans&gt; — Vokso</title>', false)
            ->assertSee('"@type":"PodcastEpisode"', false)
            ->assertSee('"@type":"BreadcrumbList"', false)
            ->assertSee('<link rel="canonical" href="https://vokso.fr/podcast/les-volcans">', false)
            ->assertSee('href="/discotheque/science"', false)
            ->assertSee('href="/podcast/les-seismes"', false);

        $this->assertStringContainsString('public', (string) $this->get('/podcast/les-volcans')->headers->get('Cache-Control'));

        $this->get('/podcast/gen_inconnu')->assertNotFound()->assertHeader('X-Robots-Tag', 'noindex');
        $this->get('/podcast/slug-inconnu')->assertNotFound();
    }

    public function test_legacy_episode_urls_redirect_permanently_to_the_slug(): void
    {
        $this->publish('gen_abc', 'Les volcans', null, 'on', 'les-volcans');

        $this->get('/podcast/gen_abc-les-volcans')->assertStatus(301)->assertRedirect('https://vokso.fr/podcast/les-volcans');
        $this->get('/podcast/gen_abc')->assertStatus(301)->assertRedirect('https://vokso.fr/podcast/les-volcans');
    }

    public function test_episode_without_slug_stays_reachable_at_its_legacy_url(): void
    {
        $this->publish('gen_old', 'Les volcans');

        $this->get('/podcast/gen_old-les-volcans')
            ->assertOk()
            ->assertSee('<link rel="canonical" href="https://vokso.fr/podcast/gen_old-les-volcans">', false);
    }

    public function test_category_page_lists_published_episodes(): void
    {
        $this->publish('gen_1', 'Les volcans', 'Science', 'on', 'les-volcans');
        $this->publish('gen_2', 'Les séismes', 'Science', 'on', 'les-seismes');
        $this->publish('gen_3', 'Brouillon', 'Science', 'off', 'brouillon');
        $this->publish('gen_4', 'Le jazz', 'Musique', 'on', 'le-jazz');

        $this->get('/discotheque/science')
            ->assertOk()
            ->assertSee('<meta name="robots" content="index, follow">', false)
            ->assertSee('"@type":"CollectionPage"', false)
            ->assertSee('href="/podcast/les-volcans"', false)
            ->assertSee('href="/podcast/les-seismes"', false)
            ->assertDontSee('brouillon')
            ->assertSee('href="/discotheque/musique"', false);

        // Une seule émission : page accessible mais non indexée (contenu trop mince).
        $this->get('/discotheque/musique')->assertOk()->assertSee('<meta name="robots" content="noindex, follow">', false);
        $this->get('/discotheque/inconnue')->assertNotFound();
    }

    public function test_sitemap_lists_published_episodes(): void
    {
        $this->publish('gen_1', 'Les volcans', 'Science', 'on', 'les-volcans');
        $this->publish('gen_3', 'Les séismes', 'Science', 'on', 'les-seismes');
        $this->publish('gen_old', 'Ancien épisode');
        $this->publish('gen_2', 'Caché', null, 'off', 'cache');

        $this->get('/sitemap')
            ->assertOk()
            ->assertHeader('Content-Type', 'application/xml; charset=utf-8')
            ->assertSee('<loc>https://vokso.fr/podcast/les-volcans</loc>', false)
            ->assertSee('<loc>https://vokso.fr/podcast/gen_old-ancien-episode</loc>', false)
            ->assertSee('<loc>https://vokso.fr/discotheque/science</loc>', false)
            ->assertDontSee('cache</loc>', false);
    }

    public function test_llms_full_lists_episodes_by_category(): void
    {
        $this->publish('gen_1', 'Les volcans', 'Science', 'on', 'les-volcans');
        $this->publish('gen_2', 'Caché', 'Science', 'off', 'cache');

        $response = $this->get('/llms-full')->assertOk()->assertHeader('Content-Type', 'text/plain; charset=utf-8');
        $this->assertStringContainsString('## Science', $response->getContent());
        $this->assertStringContainsString('[Les volcans](https://vokso.fr/podcast/les-volcans)', $response->getContent());
        $this->assertStringNotContainsString('Caché', $response->getContent());
    }

    public function test_categories_expose_their_page_url(): void
    {
        $this->publish('gen_1', 'Les volcans', 'Santé publique', 'on', 'les-volcans');

        $this->getJson('/categories')->assertOk()
            ->assertJsonPath('data.0.slug', 'sante-publique')
            ->assertJsonPath('data.0.url', '/discotheque/sante-publique');
        $this->getJson('/listing')->assertOk()->assertJsonPath('data.0.slug', 'les-volcans');
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
