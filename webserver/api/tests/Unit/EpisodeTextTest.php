<?php

namespace Tests\Unit;

use App\Support\EpisodeText;
use PHPUnit\Framework\TestCase;

class EpisodeTextTest extends TestCase
{
    public function test_slugify_transliterates_and_cuts_on_a_word(): void
    {
        $this->assertSame('les-arbres-parlent-ils-sous-terre', EpisodeText::slugify('Les arbres parlent-ils sous terre ?'));
        $this->assertSame('psyche-le-voyage-vers-le-coeur-d-un-asteroide', EpisodeText::slugify('Psyché : le voyage vers le cœur d’un astéroïde'));

        $long = EpisodeText::slugify(str_repeat('astronomie ', 20));
        $this->assertLessThanOrEqual(EpisodeText::SLUG_MAX_LENGTH, strlen($long));
        $this->assertStringEndsWith('astronomie', $long);
    }

    public function test_meta_description_keeps_whole_sentences(): void
    {
        $text = 'Imaginez une forteresse. Votre corps se défend chaque jour contre des milliers d\'envahisseurs invisibles, '
            .'virus et bactéries. Les vaccins lui apprennent à les reconnaître avant même la première rencontre, et cela change tout.';
        $description = EpisodeText::metaDescription($text);

        $this->assertLessThanOrEqual(155, mb_strlen($description));
        $this->assertStringEndsWith('bactéries.', $description);
        $this->assertSame('Court.', EpisodeText::metaDescription('Court.'));
    }

    public function test_episode_path_prefers_the_slug(): void
    {
        $this->assertSame('/podcast/les-volcans', EpisodeText::episodePath('les-volcans', 'gen_1', 'Les volcans'));
        $this->assertSame('/podcast/gen_1-les-volcans', EpisodeText::episodePath(null, 'gen_1', 'Les volcans'));
        $this->assertSame('/discotheque/sante-publique', EpisodeText::categoryPath('Santé publique'));
    }
}
