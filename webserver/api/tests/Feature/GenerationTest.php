<?php

namespace Tests\Feature;

use App\Models\GenerationJob;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class GenerationTest extends TestCase
{
    public function test_generation_requires_an_account(): void
    {
        $this->postJson('/generation', ['input' => 'Les volcans'])->assertStatus(401);
        $this->assertSame(0, GenerationJob::count());
    }

    public function test_generation_is_queued_for_the_user(): void
    {
        $user = $this->createUser();

        $response = $this->postJson('/generation', ['input' => '<b>Les volcans</b>'], $this->authHeaders($user))
            ->assertStatus(202)
            ->assertJsonPath('status', 'pending');

        $job = GenerationJob::find($response->json('job_id'));
        $this->assertSame($user->id, $job->user_id);
        $this->assertSame('Les volcans', $job->input);

        $this->getJson('/generation-status?id='.$job->job_id)
            ->assertOk()
            ->assertJsonPath('status', 'pending')
            ->assertJsonPath('progress', 0);
    }

    public function test_generation_validates_the_subject(): void
    {
        $headers = $this->authHeaders($this->createUser());

        $this->postJson('/generation', ['input' => '   '], $headers)->assertStatus(400);
        $this->postJson('/generation', ['input' => str_repeat('a', 301)], $headers)->assertStatus(400);
    }

    public function test_monthly_quota_counts_pending_jobs_but_not_failed_ones(): void
    {
        config(['vokso.generation_monthly_quota' => 2, 'vokso.rate_limit.max_requests' => 100]);
        $user = $this->createUser();
        $headers = $this->authHeaders($user);

        GenerationJob::create(['job_id' => 'job_failed', 'user_id' => $user->id, 'status' => 'error']);
        GenerationJob::create(['job_id' => 'job_last_month', 'user_id' => $user->id, 'status' => 'done'])
            ->forceFill(['created_at' => now()->subMonthNoOverflow()->startOfMonth()])->save();

        $this->postJson('/generation', ['input' => 'Sujet 1'], $headers)->assertStatus(202);
        $this->postJson('/generation', ['input' => 'Sujet 2'], $headers)->assertStatus(202);
        $this->postJson('/generation', ['input' => 'Sujet 3'], $headers)
            ->assertStatus(429)
            ->assertJsonPath('code', 'quota_reached');

        $this->getJson('/user-usage', $headers)->assertOk()->assertJson(['used' => 2, 'limit' => 2, 'unlimited' => false]);
    }

    public function test_admins_are_not_capped(): void
    {
        config(['vokso.generation_monthly_quota' => 0]);

        $this->postJson('/generation', ['input' => 'Sujet'], $this->authHeaders($this->createUser(['role' => 'admin'])))
            ->assertStatus(202);
    }

    public function test_generation_is_rate_limited_per_ip(): void
    {
        config(['vokso.rate_limit.max_requests' => 1]);
        $headers = $this->authHeaders($this->createUser());

        $this->postJson('/generation', ['input' => 'Sujet 1'], $headers)->assertStatus(202);
        $this->postJson('/generation', ['input' => 'Sujet 2'], $headers)->assertStatus(429);
    }

    public function test_audio_generation_stores_the_upload(): void
    {
        $dir = sys_get_temp_dir().'/vokso-uploads-'.uniqid();
        config(['vokso.upload_dir' => $dir]);
        $headers = $this->authHeaders($this->createUser());

        $this->post('/generation-audio', ['audio' => UploadedFile::fake()->create('voix.exe', 10)], $headers)->assertStatus(400);

        $response = $this->post('/generation-audio', ['audio' => UploadedFile::fake()->create('voix.webm', 10)], $headers)
            ->assertStatus(202);

        $job = GenerationJob::find($response->json('job_id'));
        $this->assertSame('audio', $job->source_type);
        $this->assertFileExists($job->audio_path);

        array_map('unlink', glob($dir.'/*'));
        rmdir($dir);
    }

    public function test_unknown_job_is_not_found(): void
    {
        $this->getJson('/generation-status?id=job_inconnu')->assertNotFound();
        $this->getJson('/generation-status')->assertStatus(400);
    }
}
