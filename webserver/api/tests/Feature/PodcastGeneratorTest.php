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

    /** Appels reçus par les faux fournisseurs (prompts de texte, morceaux synthétisés). */
    public static array $calls = [];

    private function fakeAi(bool $imageFails = false, string $imageBytes = 'png', string $narration = 'Narration du podcast.'): AiProviderFactory
    {
        self::$calls = ['text' => [], 'speech' => [], 'expand' => []];

        $text = new class($narration) implements TextGeneratorInterface
        {
            public function __construct(private string $narration) {}

            public function generateText(string $systemPrompt, string $userPrompt): string
            {
                if (str_starts_with($userPrompt, 'Below is the French narration')) {
                    PodcastGeneratorTest::$calls['expand'][] = $userPrompt;

                    return trim(str_repeat('Narration développée. ', 400));
                }

                return str_starts_with($userPrompt, 'categorie_icone') ? 'bi-rocket' : 'Réponse';
            }

            public function generateTextAsync(string $systemPrompt, string $userPrompt): PromiseInterface
            {
                PodcastGeneratorTest::$calls['text'][] = $userPrompt;

                return Create::promiseFor(match (true) {
                    str_starts_with($userPrompt, 'keyword') => 'Espace.',
                    str_starts_with($userPrompt, 'titre') => '**Voyage vers Mars**',
                    default => $this->narration,
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
                $i = count(PodcastGeneratorTest::$calls['speech']);
                PodcastGeneratorTest::$calls['speech'][] = $text;

                // MP3 factice : étiquette ID3v2 de 4 octets puis « trames ».
                return Create::promiseFor("ID3\x04\x00\x00\x00\x00\x00\x04TAGS" . 'frames' . $i);
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

    public function test_the_requested_format_drives_the_narration_and_is_saved(): void
    {
        GenerationJob::create(['job_id' => 'job_f', 'input' => 'Les trous noirs', 'status' => 'pending', 'duration_minutes' => 12, 'level' => 5]);

        (new PodcastGenerator($this->fakeAi(), $this->outputDir))->process('job_f');

        $generation = Generation::where('generation_id', GenerationJob::find('job_f')->generation_id)->first();
        $this->assertSame(12, (int) $generation->duration_minutes);
        $this->assertSame(5, (int) $generation->level);

        $narration = collect(self::$calls['text'])->first(fn ($p) => str_contains($p, 'Topic: Les trous noirs'));
        $this->assertNotNull($narration);
        $this->assertStringContainsString('about 12 minutes', $narration);
        $this->assertStringContainsString('Level 5/5', $narration);
        $this->assertStringContainsString('no mandatory outline', $narration);
    }

    public function test_a_too_short_narration_is_expanded_once(): void
    {
        GenerationJob::create(['job_id' => 'job_s', 'input' => 'Mars', 'status' => 'pending', 'duration_minutes' => 5, 'level' => 2]);

        (new PodcastGenerator($this->fakeAi(), $this->outputDir))->process('job_s');

        $this->assertCount(1, self::$calls['expand']);
        $this->assertStringContainsString('between 674 and 761 words', self::$calls['expand'][0]);
        $generation = Generation::where('generation_id', GenerationJob::find('job_s')->generation_id)->first();
        $this->assertStringStartsWith('Narration développée.', $generation->text_content);
    }

    public function test_a_long_narration_is_synthesized_in_chunks_and_joined(): void
    {
        $paragraph = str_repeat('Une phrase de narration assez longue pour remplir le texte. ', 30);
        $narration = implode("\n\n", array_fill(0, 8, trim($paragraph)));
        GenerationJob::create(['job_id' => 'job_long', 'input' => 'Mars', 'status' => 'pending', 'duration_minutes' => 15, 'level' => 3]);

        (new PodcastGenerator($this->fakeAi(narration: $narration), $this->outputDir))->process('job_long');

        $chunks = self::$calls['speech'];
        $this->assertGreaterThan(1, count($chunks));
        foreach ($chunks as $chunk) {
            $this->assertLessThanOrEqual(5000, mb_strlen($chunk));
        }
        $this->assertSame(preg_replace('/\s+/', ' ', $narration), preg_replace('/\s+/', ' ', implode(' ', $chunks)));

        $generation = Generation::where('generation_id', GenerationJob::find('job_long')->generation_id)->first();
        $audio = file_get_contents($this->outputDir.'/audios/'.$generation->audio_url);
        // Une seule étiquette ID3 en tête, puis les trames de chaque morceau dans l'ordre.
        $this->assertSame(1, substr_count($audio, 'ID3'));
        $this->assertStringEndsWith(implode('', array_map(fn ($i) => 'frames'.$i, range(1, count($chunks) - 1))), substr($audio, 14 + strlen('frames0')));
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

    public function test_a_failing_image_falls_back_to_the_category_cover(): void
    {
        mkdir($this->outputDir.'/images', 0775, true);
        file_put_contents($this->outputDir.'/images/category_1_cover.webp', 'cover');
        Category::create(['label' => 'Espace', 'cover_image' => 'category_1_cover.webp']);
        GenerationJob::create(['job_id' => 'job_4', 'input' => 'Mars', 'status' => 'pending']);

        (new PodcastGenerator($this->fakeAi(imageFails: true), $this->outputDir))->process('job_4');

        $job = GenerationJob::find('job_4');
        $this->assertSame('done', $job->status);
        $generation = Generation::where('generation_id', $job->generation_id)->first();
        $this->assertNotSame('category_1_cover.webp', $generation->image_url);
        $this->assertStringEqualsFile($this->outputDir.'/images/'.$generation->image_url, 'cover');
        $this->assertEquals(0, $generation->cost_image);
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
