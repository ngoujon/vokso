<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * Écoutes, téléchargements et registre des consentements : tables créées par
 * SQL/20261021000000.sql. Tant qu'elles n'existent pas, l'enregistrement est
 * ignoré sans erreur et les statistiques valent zéro.
 */
class Stats
{
    public const EVENT_TYPES = ['play', 'download_audio', 'download_pdf'];

    private static array $tables = [];

    public static function ready(string $table): bool
    {
        return self::$tables[$table] ??= Schema::hasTable($table);
    }

    /** Enregistre une écoute ou un téléchargement ; ne fait jamais échouer la requête. */
    public static function recordEvent(string $generationId, string $type): void
    {
        if (! in_array($type, self::EVENT_TYPES, true) || ! self::ready('podcast_events')) {
            return;
        }
        try {
            DB::table('podcast_events')->insert([
                'generation_id' => $generationId,
                'type' => $type,
                'created_at' => now()->format('Y-m-d H:i:s'),
            ]);
        } catch (Throwable $e) {
            report($e);
        }
    }

    /** Oublie l'état mémorisé des tables (tests). */
    public static function reset(): void
    {
        self::$tables = [];
    }
}
