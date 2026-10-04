<?php

namespace App\Console\Commands;

use App\Support\EpisodeSlug;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Attribue un slug (/podcast/{slug}) aux épisodes qui n'en ont pas encore,
 * du plus ancien au plus récent : en cas de titres identiques, le premier
 * publié garde l'URL sans suffixe. Sans effet sur les épisodes déjà migrés,
 * la commande peut donc être relancée.
 */
class BackfillEpisodeSlugs extends Command
{
    protected $signature = 'vokso:episode-slugs';

    protected $description = 'Attribue une URL lisible (slug) aux épisodes qui n\'en ont pas';

    public function handle(): int
    {
        $episodes = DB::table('generations')
            ->whereNull('slug')
            ->orderBy('created_at')
            ->orderBy('id')
            ->get(['generation_id', 'title']);

        foreach ($episodes as $episode) {
            $slug = EpisodeSlug::forTitle((string) $episode->title, $episode->generation_id);
            DB::table('generations')->where('generation_id', $episode->generation_id)->update(['slug' => $slug]);
            $this->line("{$episode->generation_id} -> /podcast/{$slug}");
        }

        $this->info(count($episodes).' épisode(s) mis à jour.');

        return self::SUCCESS;
    }
}
