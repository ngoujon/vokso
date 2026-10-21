<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Écoutes / téléchargements et registre des choix du bandeau cookies. En
 * production, la migration équivalente est SQL/20261021000000.sql, appliquée
 * à la main.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('podcast_events', function (Blueprint $table) {
            $table->id();
            $table->string('generation_id', 64);
            $table->string('type', 20);
            $table->dateTime('created_at')->useCurrent();
            $table->index(['type', 'created_at'], 'podcast_events_type_created');
            $table->index('generation_id', 'podcast_events_generation');
        });

        Schema::create('cookie_consents', function (Blueprint $table) {
            $table->id();
            $table->char('consent_id', 36);
            $table->string('choice', 10);
            $table->string('banner_version', 20);
            $table->string('page')->nullable();
            $table->string('user_agent')->nullable();
            $table->char('ip_hash', 64)->nullable();
            $table->dateTime('created_at')->useCurrent();
            $table->index('created_at', 'cookie_consents_created');
            $table->index('consent_id', 'cookie_consents_consent_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('podcast_events');
        Schema::dropIfExists('cookie_consents');
    }
};
