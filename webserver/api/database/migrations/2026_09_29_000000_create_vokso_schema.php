<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Schéma complet de Vokso, pour une installation neuve (poste de dev) et les
 * tests. La base de production, antérieure à Laravel, a été construite par
 * les scripts historiques de SQL/ : ceux-ci restent appliqués à la main et
 * cette migration ne crée que les tables absentes (hasTable), elle ne
 * modifie jamais une table existante.
 */
return new class extends Migration
{
    public function up(): void
    {
        $this->create('users', function (Blueprint $table) {
            $table->increments('id');
            $table->string('email', 190)->unique();
            $table->string('password_hash');
            $table->enum('role', ['user', 'admin'])->default('user');
            $table->enum('status', ['active', 'disabled'])->default('active');
            $table->boolean('must_change_password')->default(false);
            $table->string('totp_secret', 64)->nullable();
            $table->boolean('totp_enabled')->default(false);
            $table->dateTime('created_at')->useCurrent();
        });

        $this->create('auth_tokens', function (Blueprint $table) {
            $table->string('token', 64)->primary();
            $table->unsignedInteger('user_id')->index();
            $table->dateTime('expires_at');
            $table->dateTime('created_at')->useCurrent();
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
        });

        $this->create('categorie', function (Blueprint $table) {
            $table->increments('idcategorie');
            $table->string('label', 100);
            $table->string('icon', 50)->nullable();
            $table->string('cover_image')->nullable();
            $table->enum('statut', ['on', 'off'])->default('on');
            $table->dateTime('update_date')->nullable()->useCurrent();
        });

        $this->create('generations', function (Blueprint $table) {
            $table->increments('id');
            $table->string('generation_id')->unique();
            $table->string('title')->default('');
            $table->string('unique_code')->nullable();
            $table->text('text_content');
            $table->string('image_url');
            $table->string('audio_url');
            $table->integer('idcategorie')->default(0);
            $table->unsignedInteger('user_id')->nullable()->index();
            $table->decimal('cost_text', 10, 5)->default(0);
            $table->decimal('cost_image', 10, 5)->default(0);
            $table->decimal('cost_audio', 10, 5)->default(0);
            $table->decimal('cost_total', 10, 5)->default(0);
            $table->dateTime('created_at')->useCurrent();
            $table->enum('statut', ['on', 'off'])->default('on');
            $table->dateTime('update_date')->nullable()->useCurrent();
        });

        $this->create('generation_jobs', function (Blueprint $table) {
            $table->string('job_id', 64)->primary();
            $table->unsignedInteger('user_id')->nullable();
            $table->enum('source_type', ['text', 'audio'])->default('text');
            $table->enum('status', ['pending', 'processing', 'done', 'error'])->default('pending');
            $table->string('step', 50)->default('queued');
            $table->unsignedTinyInteger('progress')->default(0);
            $table->string('input', 300)->nullable();
            $table->string('audio_path')->nullable();
            $table->string('generation_id', 64)->nullable();
            $table->text('error_message')->nullable();
            $table->dateTime('created_at')->useCurrent();
            $table->dateTime('updated_at')->useCurrent();
            $table->index(['user_id', 'created_at'], 'generation_jobs_user_created');
        });

        $this->create('prompt', function (Blueprint $table) {
            $table->increments('idprompt');
            $table->string('type', 20)->nullable();
            $table->text('content');
            $table->enum('statut', ['on', 'off'])->default('on');
        });

        $this->create('rate_limit', function (Blueprint $table) {
            $table->increments('id');
            $table->string('ip_address', 45);
            $table->string('route', 50);
            $table->dateTime('requested_at');
            $table->index(['ip_address', 'route', 'requested_at'], 'idx_rate_limit_ip_route_time');
        });

        $this->create('newsletter_subscribers', function (Blueprint $table) {
            $table->increments('id');
            $table->string('email')->unique('uniq_email');
            $table->dateTime('subscribed_at')->useCurrent();
        });
    }

    public function down(): void
    {
        foreach (['newsletter_subscribers', 'rate_limit', 'prompt',
            'generation_jobs', 'generations', 'categorie', 'auth_tokens', 'users'] as $table) {
            Schema::dropIfExists($table);
        }
    }

    private function create(string $table, Closure $definition): void
    {
        if (! Schema::hasTable($table)) {
            Schema::create($table, $definition);
        }
    }
};
