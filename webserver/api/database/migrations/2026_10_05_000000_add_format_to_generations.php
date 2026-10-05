<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Format choisi à la création (durée visée, niveau de profondeur). En
 * production, la migration équivalente est SQL/20261005000000.sql, appliquée
 * à la main.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (['generations', 'generation_jobs'] as $name) {
            Schema::table($name, function (Blueprint $table) {
                $table->unsignedTinyInteger('duration_minutes')->nullable();
                $table->unsignedTinyInteger('level')->nullable();
            });
        }
    }

    public function down(): void
    {
        foreach (['generations', 'generation_jobs'] as $name) {
            Schema::table($name, function (Blueprint $table) {
                $table->dropColumn(['duration_minutes', 'level']);
            });
        }
    }
};
