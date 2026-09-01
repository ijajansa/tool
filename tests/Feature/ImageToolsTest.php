<?php

namespace Tests\Feature;

use App\Enums\TaskStatus;
use App\Jobs\ProcessImageTask;
use App\Models\ProcessingTask;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Tests\TestCase;

class ImageToolsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
    }

    public function test_home_and_every_tool_page_render(): void
    {
        $this->get('/')->assertOk()->assertSee(config('site.name'));
        foreach (config('tools') as $slug => $tool) {
            $this->get("/tools/$slug")->assertOk()->assertSee($tool['name']);
        }
        $this->get('/tools/not-real')->assertNotFound();
    }

    public function test_upload_validation_rejects_non_images_and_invalid_options(): void
    {
        $this->postJson('/tools/image-compressor/tasks', [
            'images' => [UploadedFile::fake()->create('attack.jpg', 2, 'text/plain')],
            'quality' => 101,
            'format' => '../png',
        ])->assertUnprocessable()->assertJsonValidationErrors(['images.0', 'quality', 'format']);
    }

    public function test_valid_upload_creates_private_task_and_dispatches_job(): void
    {
        Queue::fake();
        $response = $this->postJson('/tools/image-compressor/tasks', $this->compressPayload());
        $response->assertAccepted()->assertJsonPath('status', 'pending');
        $task = ProcessingTask::firstOrFail();
        $this->assertStringStartsWith("tasks/{$task->id}/input/", $task->input_metadata['files'][0]['path']);
        Storage::disk('local')->assertExists($task->input_metadata['files'][0]['path']);
        Queue::assertPushed(ProcessImageTask::class, fn ($job) => $job->taskId === $task->id);
    }

    public function test_every_server_image_tool_accepts_its_valid_payload(): void
    {
        config(['image-tools.max_concurrent_tasks' => 10]);
        Queue::fake();
        $cases = [
            'background-remover' => ['background_mode' => 'transparent'],
            'image-compressor' => ['quality' => 80, 'width' => 40, 'preserve_aspect' => true, 'prevent_enlarge' => true, 'format' => 'webp'],
            'product-photo-enhancer' => ['brightness' => 0, 'contrast' => 0, 'sharpness' => 20, 'saturation' => 0],
            'passport-photo-maker' => ['photo_width_mm' => 35, 'photo_height_mm' => 45, 'paper' => '4x6', 'margin_mm' => 5, 'gap_mm' => 2, 'background_color' => '#ffffff'],
            'logo-transparency' => ['background_color' => '#ffffff', 'tolerance' => 20],
            'image-to-webp' => ['quality' => 80],
        ];

        foreach ($cases as $tool => $options) {
            $payload = ['images' => [UploadedFile::fake()->image("$tool.png", 40, 40)]] + $options;
            $this->postJson("/tools/$tool/tasks", $payload)->assertAccepted();
        }

        Queue::assertPushed(ProcessImageTask::class, 6);
    }

    public function test_status_is_only_available_to_owning_session(): void
    {
        Queue::fake();
        $response = $this->postJson('/tools/image-compressor/tasks', $this->compressPayload());
        $this->getJson($response->json('status_url'))->assertOk();

        $this->app['session']->invalidate();
        $this->withSession(['new' => true])->getJson($response->json('status_url'))->assertForbidden();
    }

    public function test_successful_local_processing_and_signed_download(): void
    {
        config(['queue.default' => 'sync']);
        $response = $this->postJson('/tools/image-compressor/tasks', $this->compressPayload());
        $status = $this->getJson($response->json('status_url'))
            ->assertOk()->assertJsonPath('status', 'completed');
        $download = $status->json('result.files.0.download_url');
        $this->get($download)->assertOk()->assertDownload('optimized.webp');
        $this->get(preg_replace('/signature=[^&]+/', 'signature=bad', $download))->assertForbidden();
    }

    public function test_expired_download_is_rejected(): void
    {
        $this->get('/');
        $task = $this->task([
            'status' => TaskStatus::Completed,
            'expires_at' => now()->subMinute(),
            'output_metadata' => ['files' => [['path' => 'tasks/'.$this->uuid().'/unused', 'name' => 'result.png']]],
        ]);
        $url = URL::temporarySignedRoute('tasks.download', now()->addMinute(), ['task' => $task, 'file' => 0]);
        $this->get($url)->assertGone();
    }

    public function test_cleanup_command_removes_only_expired_task_files(): void
    {
        $expired = $this->task(['expires_at' => now()->subMinute()]);
        $active = $this->task(['expires_at' => now()->addMinutes(10)]);
        Storage::disk('local')->put("tasks/{$expired->id}/input/a.jpg", 'old');
        Storage::disk('local')->put("tasks/{$active->id}/input/a.jpg", 'new');

        $this->artisan('images:cleanup')->assertSuccessful();
        $this->assertDatabaseMissing('processing_tasks', ['id' => $expired->id]);
        $this->assertDatabaseHas('processing_tasks', ['id' => $active->id]);
        $this->assertFalse(Storage::disk('local')->directoryExists("tasks/{$expired->id}"));
        Storage::disk('local')->assertExists("tasks/{$active->id}/input/a.jpg");
    }

    public function test_python_service_failure_becomes_safe_failed_task(): void
    {
        config(['queue.default' => 'sync']);
        Http::fake(['*' => Http::response(['error' => ['message' => 'AI unavailable']], 503)]);
        $response = $this->postJson('/tools/background-remover/tasks', [
            'images' => [UploadedFile::fake()->image('person.png', 60, 60)],
            'background_mode' => 'transparent',
        ]);
        $this->getJson($response->json('status_url'))
            ->assertOk()
            ->assertJsonPath('status', 'failed')
            ->assertJsonPath('error.code', 'processing_failed');
    }

    public function test_download_metadata_cannot_traverse_storage(): void
    {
        $this->get('/');
        $task = $this->task([
            'status' => TaskStatus::Completed,
            'output_metadata' => ['files' => [['path' => '../.env', 'name' => '.env']]],
        ]);
        $url = URL::temporarySignedRoute('tasks.download', now()->addMinute(), ['task' => $task, 'file' => 0]);
        $this->get($url)->assertForbidden();
    }

    public function test_rate_limit_is_applied_by_ip(): void
    {
        config(['image-tools.rate_limit_per_minute' => 2]);
        Queue::fake();
        $this->postJson('/tools/image-compressor/tasks', $this->compressPayload())->assertAccepted();
        $this->postJson('/tools/image-compressor/tasks', $this->compressPayload())->assertAccepted();
        $this->postJson('/tools/image-compressor/tasks', $this->compressPayload())->assertTooManyRequests();
    }

    private function compressPayload(): array
    {
        return [
            'images' => [UploadedFile::fake()->image('photo.jpg', 80, 60)],
            'quality' => 80,
            'width' => 40,
            'height' => null,
            'preserve_aspect' => true,
            'prevent_enlarge' => true,
            'format' => 'webp',
        ];
    }

    private function task(array $attributes = []): ProcessingTask
    {
        $id = $attributes['id'] ?? $this->uuid();
        $token = 'test-owner-token';
        $this->app['session']->put('image_tools_owner_token', $token);

        return ProcessingTask::create(array_merge([
            'id' => $id,
            'tool' => 'image-compressor',
            'status' => TaskStatus::Pending,
            'input_metadata' => ['files' => []],
            'options' => [],
            'session_hash' => hash('sha256', $token),
            'ip_hash' => hash('sha256', 'test'),
            'expires_at' => now()->addMinutes(30),
        ], $attributes));
    }

    private function uuid(): string
    {
        return (string) Str::uuid();
    }
}
