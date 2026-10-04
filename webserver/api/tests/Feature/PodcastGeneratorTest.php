<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Generation;
use App\Models\GenerationJob;
use App\Services\Ai\AiProviderFactory;
use App\Services\Ai\ImageGeneratorInterface;
use App\Services\Ai\SpeechSynthesizerInterface;
use App\Services\Ai\TextGeneratorInterface;
use App\Services\PodcastGenerator;
use Exception;
use GuzzleHttp\Promise\Create;
use GuzzleHttp\Promise\PromiseInterface;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PodcastGeneratorTest extends TestCase
{
    private string $outputDir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->outputDir = sys_get_temp_dir().'/vokso-output-'.uniqid();

        foreach (['texte', 'injection', 'keyword', 'titre', 'image', 'categorie_icone', 'categorie_cover'] as $type) {
            DB::table('prompt')->insert(['type' => $type, 'content' => $type.' : ###REPLACE###', 'statut' => 'on']);
        }
    }

    protected function tearDown(): void
    {
        exec('rm -rf '.escapeshellarg($this->outputDir));
        parent::tearDown();
    }

    private function fakeAi(bool $imageFails = false, string $imageBytes = 'png'): AiProviderFactory
    {
        $text = new class implements TextGeneratorInterface
        {
            public function generateText(string $systemPrompt, string $userPrompt): string
            {
                return str_starts_with($userPrompt, 'categorie_icone') ? 'bi-rocket' : 'Réponse';
            }

            public function generateTextAsync(string $systemPrompt, string $userPrompt): PromiseInterface
            {
                return Create::promiseFor(match (true) {
                    str_starts_with($userPrompt, 'keyword') => 'Espace.',
                    str_starts_with($userPrompt, 'titre') => '**Voyage vers Mars**',
                    default => 'Narration du podcast.',
                });
            }
        };

        $image = new class($imageFails, $imageBytes) implements ImageGeneratorInterface
        {
            public function __construct(private bool $fails, private string $bytes) {}

            public function generateImage(string $prompt): string
            {
                return $this->bytes;
            }

            public function generateImageAsync(string $prompt): PromiseInterface
            {
                return $this->fails ? Create::rejectionFor(new Exception('quota image')) : Create::promiseFor($this->bytes);
            }
        };

        $speech = new class implements SpeechSynthesizerInterface
        {
            public function synthesize(string $text): string
            {
                return 'mp3';
            }

            public function synthesizeAsync(string $text): PromiseInterface
            {
                return Create::promiseFor('mp3');
            }

            public function audioExtension(): string
            {
                return 'mp3';
            }
        };

        return new class($text, $image, $speech) extends AiProviderFactory
        {
            public function __construct(private $text, private $image, private $speech)
            {
                parent::__construct(['mistral' => ['text_model' => 'mistral-small-latest']]);
            }

            public function textGenerator(): TextGeneratorInterface
            {
                return $this->text;
            }

            public function researchTextGenerator(): TextGeneratorInterface
            {
                return $this->text;
            }

            public function imageGenerator(): ImageGeneratorInterface
            {
                return $this->image;
            }

            public function speechSynthesizer(): SpeechSynthesizerInterface
            {
                return $this->speech;
            }
        };
    }

    public function test_a_job_produces_a_published_episode(): void
    {
        $user = $this->createUser();
        GenerationJob::create(['job_id' => 'job_1', 'user_id' => $user->id, 'input' => 'Mars', 'status' => 'pending']);

        (new PodcastGenerator($this->fakeAi(), $this->outputDir))->process('job_1');

        $job = GenerationJob::find('job_1');
        $this->assertSame('done', $job->status);
        $this->assertSame(100, $job->progress);

        $generation = Generation::where('generation_id', $job->generation_id)->first();
        $this->assertSame('Voyage vers Mars', $generation->title);
        $this->assertSame('voyage-vers-mars', $generation->slug);
        $this->assertSame($user->id, $generation->user_id);
        $this->assertFileExists($this->outputDir.'/audios/'.$generation->audio_url);
        $this->assertGreaterThan(0, (float) $generation->cost_total);

        $category = Category::find($generation->idcategorie);
        $this->assertSame('Espace', $category->label);
        $this->assertSame('rocket', $category->icon);

        // Même titre pour un second épisode : slug départagé, pas de collision d'URL.
        GenerationJob::create(['job_id' => 'job_bis', 'input' => 'Mars encore', 'status' => 'pending']);
        (new PodcastGenerator($this->fakeAi(), $this->outputDir))->process('job_bis');
        $this->assertSame('voyage-vers-mars-2', Generation::where('generation_id', GenerationJob::find('job_bis')->generation_id)->value('slug'));
    }

    public function test_multi_word_rubriques_are_recognised(): void
    {
        $method = new \ReflectionMethod(PodcastGenerator::class, 'sanitizeCategory');
        $generator = new PodcastGenerator($this->fakeAi(), $this->outputDir);

        $this->assertSame('Nature et environnement', $method->invoke($generator, 'nature et environnement.'));
        $this->assertSame('Culture et arts', $method->invoke($generator, 'Culture et Arts'));
        $this->assertSame('Santé', $method->invoke($generator, 'sante'));
        $this->assertSame('Volcanologie', $method->invoke($generator, 'Volcanologie'));
    }

    public function test_a_failing_step_marks_the_job_as_failed(): void
    {
        GenerationJob::create(['job_id' => 'job_2', 'input' => 'Mars', 'status' => 'pending']);

        (new PodcastGenerator($this->fakeAi(imageFails: true), $this->outputDir))->process('job_2');

        $job = GenerationJob::find('job_2');
        $this->assertSame('error', $job->status);
        $this->assertStringContainsString('image', $job->error_message);
        $this->assertSame(0, Generation::count());
    }

    public function test_a_jpeg_returned_by_the_provider_is_converted_to_webp(): void
    {
        if (! function_exists('imagewebp') && ! extension_loaded('imagick')) {
            $this->markTestSkipped('Aucune extension WebP disponible.');
        }

        $canvas = imagecreatetruecolor(8, 8);
        ob_start();
        imagejpeg($canvas);
        $jpeg = (string) ob_get_clean();

        GenerationJob::create(['job_id' => 'job_3', 'input' => 'Mars', 'status' => 'pending']);
        (new PodcastGenerator($this->fakeAi(imageBytes: $jpeg), $this->outputDir))->process('job_3');

        $generation = Generation::where('generation_id', GenerationJob::find('job_3')->generation_id)->first();
        $this->assertStringEndsWith('.webp', $generation->image_url);
        $this->assertFileExists($this->outputDir.'/images/'.$generation->image_url);
    }

    public function test_seed_command_generates_topics_and_skips_those_already_done(): void
    {
        config(['vokso.output_dir' => $this->outputDir]);
        $this->app->instance(AiProviderFactory::class, $this->fakeAi());

        $this->artisan('vokso:seed-podcasts', ['sujets' => ['Mars', 'Les volcans']])->assertSuccessful();
        $this->assertSame(2, GenerationJob::where('status', 'done')->count());

        $this->artisan('vokso:seed-podcasts', ['sujets' => ['Mars']])
            ->expectsOutputToContain('déjà généré')
            ->assertSuccessful();
        $this->assertSame(2, GenerationJob::count());
    }

    public function test_seed_command_can_force_a_category_and_unpublish_replaced_episodes(): void
    {
        config(['vokso.output_dir' => $this->outputDir]);
        $this->app->instance(AiProviderFactory::class, $this->fakeAi());
        $astronomy = Category::create(['label' => 'Astronomie']);
        Generation::create(['generation_id' => 'gen_old', 'title' => 'Ancien', 'text_content' => 'x', 'image_url' => 'a.png', 'audio_url' => 'a.mp3', 'idcategorie' => $astronomy->idcategorie, 'statut' => 'on']);

        $this->artisan('vokso:seed-podcasts', ['sujets' => ['Jupiter'], '--categorie' => 'Astronomie', '--remplace' => ['gen_old']])
            ->expectsOutputToContain('1 ancien(s) épisode(s) dépublié(s)')
            ->assertSuccessful();

        $new = Generation::where('generation_id', GenerationJob::where('input', 'Jupiter')->value('generation_id'))->first();
        $this->assertSame($astronomy->idcategorie, (int) $new->idcategorie);
        $this->assertSame('off', Generation::where('generation_id', 'gen_old')->value('statut'));
        $this->assertNotNull($astronomy->fresh()->cover_image);
    }
}
