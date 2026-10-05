<?php

namespace Tests\Feature;

use App\Models\GenerationJob;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class GenerationTest extends TestCase
{
    public function test_generation_is_open_without_an_account(): void
    {
        $response = $this->postJson('/generation', ['input' => '<b>Les volcans</b>'])
            ->assertStatus(202)
            ->assertJsonPath('status', 'pending');

        $job = GenerationJob::find($response->json('job_id'));
        $this->assertNull($job->user_id);
        $this->assertSame('Les volcans', $job->input);

        $this->getJson('/generation-status?id='.$job->job_id)
            ->assertOk()
            ->assertJsonPath('status', 'pending')
            ->assertJsonPath('progress', 0);
    }

    public function test_generation_is_attached_to_a_logged_in_user(): void
    {
        $user = $this->createUser();

        $response = $this->postJson('/generation', ['input' => 'Les volcans'], $this->authHeaders($user))->assertStatus(202);

        $this->assertSame($user->id, GenerationJob::find($response->json('job_id'))->user_id);
    }

    public function test_generation_validates_the_subject_without_consuming_the_limit(): void
    {
        config(['vokso.generation_limits.per_ip_hourly' => 1]);

        $this->postJson('/generation', ['input' => '   '])->assertStatus(400);
        $this->postJson('/generation', ['input' => str_repeat('a', 301)])->assertStatus(400);
        $this->postJson('/generation', ['input' => 'Sujet'])->assertStatus(202);
    }

    public function test_generation_is_limited_per_ip_per_hour(): void
    {
        config(['vokso.generation_limits.per_ip_hourly' => 2]);

        $this->postJson('/generation', ['input' => 'Sujet 1'])->assertStatus(202);
        $this->postJson('/generation', ['input' => 'Sujet 2'])->assertStatus(202);
        $this->postJson('/generation', ['input' => 'Sujet 3'])
            ->assertStatus(429)
            ->assertJsonPath('code', 'limit_reached');

        // Une autre IP n'est pas concernée.
        $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.7'])
            ->postJson('/generation', ['input' => 'Sujet 4'])
            ->assertStatus(202);
    }

    public function test_generation_is_limited_per_ip_per_day(): void
    {
        config(['vokso.generation_limits.per_ip_daily' => 1]);

        $this->postJson('/generation', ['input' => 'Sujet 1'])->assertStatus(202);
        $this->travel(2)->hours();
        $this->postJson('/generation', ['input' => 'Sujet 2'])->assertStatus(429);
        $this->travel(23)->hours();
        $this->postJson('/generation', ['input' => 'Sujet 3'])->assertStatus(202);
    }

    public function test_global_daily_cap_ignores_failed_and_old_jobs(): void
    {
        config(['vokso.generation_limits.global_daily' => 2]);

        GenerationJob::create(['job_id' => 'job_failed', 'status' => 'error']);
        GenerationJob::create(['job_id' => 'job_old', 'status' => 'done'])
            ->forceFill(['created_at' => now()->subDays(2)])->save();
        GenerationJob::create(['job_id' => 'job_recent', 'status' => 'done']);

        $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.1'])
            ->postJson('/generation', ['input' => 'Sujet 1'])->assertStatus(202);
        $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.2'])
            ->postJson('/generation', ['input' => 'Sujet 2'])->assertStatus(429);
    }

    public function test_admins_are_not_capped(): void
    {
        config(['vokso.generation_limits.per_ip_hourly' => 0, 'vokso.generation_limits.global_daily' => 0]);

        $this->postJson('/generation', ['input' => 'Sujet'], $this->authHeaders($this->createUser(['role' => 'admin'])))
            ->assertStatus(202);
    }

    public function test_audio_generation_stores_the_upload(): void
    {
        $dir = sys_get_temp_dir().'/vokso-uploads-'.uniqid();
        config(['vokso.upload_dir' => $dir]);

        $this->post('/generation-audio', ['audio' => UploadedFile::fake()->create('voix.exe', 10)])->assertStatus(400);

        $response = $this->post('/generation-audio', ['audio' => UploadedFile::fake()->create('voix.webm', 10)])
            ->assertStatus(202);

        $job = GenerationJob::find($response->json('job_id'));
        $this->assertSame('audio', $job->source_type);
        $this->assertFileExists($job->audio_path);

        array_map('unlink', glob($dir.'/*'));
        rmdir($dir);
    }

    public function test_generation_records_the_requested_format(): void
    {
        $default = $this->postJson('/generation', ['input' => 'Les volcans'])->assertStatus(202);
        $job = GenerationJob::find($default->json('job_id'));
        $this->assertSame(5, $job->duration_minutes);
        $this->assertSame(3, $job->level);

        $custom = $this->postJson('/generation', ['input' => 'Les volcans', 'duration' => 12, 'level' => 5])->assertStatus(202);
        $job = GenerationJob::find($custom->json('job_id'));
        $this->assertSame(12, $job->duration_minutes);
        $this->assertSame(5, $job->level);

        $this->postJson('/generation', ['input' => 'Sujet', 'duration' => 16])->assertStatus(400);
        $this->postJson('/generation', ['input' => 'Sujet', 'duration' => 0])->assertStatus(400);
        $this->postJson('/generation', ['input' => 'Sujet', 'level' => 6])->assertStatus(400);
        $this->postJson('/generation', ['input' => 'Sujet', 'level' => 'expert'])->assertStatus(400);
    }

    public function test_audio_generation_records_the_requested_format(): void
    {
        $dir = sys_get_temp_dir().'/vokso-uploads-'.uniqid();
        config(['vokso.upload_dir' => $dir]);

        $response = $this->post('/generation-audio', [
            'audio' => UploadedFile::fake()->create('voix.webm', 10),
            'duration' => '1',
            'level' => '4',
        ])->assertStatus(202);

        $job = GenerationJob::find($response->json('job_id'));
        $this->assertSame(1, $job->duration_minutes);
        $this->assertSame(4, $job->level);

        array_map('unlink', glob($dir.'/*'));
        rmdir($dir);
    }

    public function test_unknown_job_is_not_found(): void
    {
        $this->getJson('/generation-status?id=job_inconnu')->assertNotFound();
        $this->getJson('/generation-status')->assertStatus(400);
    }
}
