<?php

namespace Tests\Feature;

use App\Enums\TaskStatus;
use App\Models\ProcessingTask;
use App\Models\ToolSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

class ProductionReadinessTest extends TestCase
{
    use RefreshDatabase;

    public function test_security_headers_and_accessibility_markup_are_present(): void
    {
        $this->get('/')->assertOk()
            ->assertHeader('X-Frame-Options', 'DENY')
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin')
            ->assertHeader('Permissions-Policy')
            ->assertHeader('Content-Security-Policy')
            ->assertSee('Skip to content')->assertSee('aria-label="Main navigation"', false);
    }

    public function test_sitemap_metadata_categories_and_legal_pages_render(): void
    {
        $this->get('/sitemap.xml')->assertOk()->assertHeader('Content-Type', 'application/xml')->assertSee(route('tools.show', 'seo-audit'));
        $this->get('/categories')->assertOk()->assertSee('Tool categories');
        $this->get('/legal/privacy')->assertOk()->assertSee('Dynamic QR analytics');
        $this->get('/tools/image-compressor')->assertOk()->assertSee('application/ld+json')->assertSee('Frequently asked questions')->assertSee('canonical', false);
    }

    public function test_disabled_tool_is_hidden_and_returns_not_found(): void
    {
        ToolSetting::create(['tool' => 'image-compressor', 'enabled' => false]);
        $this->get('/')->assertDontSee('Image Compressor &amp; Resizer', false);
        $this->get('/sitemap.xml')->assertDontSee('/tools/image-compressor');
        $this->get('/tools/image-compressor')->assertNotFound();
    }

    public function test_admin_requires_authentication_and_admin_role(): void
    {
        $this->get('/admin')->assertRedirect('/admin/login');
        $user = User::factory()->create(['is_admin' => false]);
        $this->actingAs($user)->get('/admin')->assertForbidden();
        $admin = User::factory()->create(['is_admin' => true]);
        $this->actingAs($admin)->get('/admin')->assertOk()->assertSee('Operations dashboard')->assertDontSee('input_metadata');
    }

    public function test_admin_login_rejects_non_admin_and_accepts_admin(): void
    {
        $user = User::factory()->create(['email' => 'user@example.test', 'password' => 'StrongPassword!123', 'is_admin' => false]);
        $this->post('/admin/login', ['email' => $user->email, 'password' => 'StrongPassword!123'])->assertSessionHasErrors('email');
        $admin = User::factory()->create(['email' => 'admin@example.test', 'password' => 'StrongPassword!123', 'is_admin' => true]);
        $this->post('/admin/login', ['email' => $admin->email, 'password' => 'StrongPassword!123'])->assertRedirect('/admin');
    }

    public function test_concurrent_task_limit_rejects_more_work(): void
    {
        config(['image-tools.max_concurrent_tasks' => 1]);
        ProcessingTask::create(['id' => (string) Str::uuid(), 'tool' => 'image-compressor', 'status' => TaskStatus::Processing, 'input_metadata' => ['files' => []], 'options' => [], 'session_hash' => hash('sha256', 'owner'), 'ip_hash' => hash_hmac('sha256', '127.0.0.1', config('app.key')), 'expires_at' => now()->addMinutes(10)]);
        $this->postJson('/tools/image-compressor/tasks', [])->assertTooManyRequests();
    }

    public function test_stale_tasks_are_recovered_without_exposing_details(): void
    {
        $task = ProcessingTask::create(['id' => (string) Str::uuid(), 'tool' => 'image-compressor', 'status' => TaskStatus::Processing, 'input_metadata' => ['files' => []], 'options' => [], 'session_hash' => hash('sha256', 'owner'), 'ip_hash' => hash('sha256', 'ip'), 'expires_at' => now()->addMinutes(10), 'updated_at' => now()->subHour()]);
        ProcessingTask::whereKey($task->id)->update(['updated_at' => now()->subHour()]);
        $this->artisan('tasks:recover-stale')->assertSuccessful();
        $this->assertSame('processing_timeout', $task->fresh()->error_code);
    }

    public function test_deployment_health_reports_required_and_optional_checks(): void
    {
        Http::fake(['*/health' => Http::response(['status' => 'ok'])]);
        $this->getJson('/health')->assertOk()->assertJsonStructure(['status', 'checks' => ['database', 'storage', 'ai_optional']]);
    }

    public function test_optional_captcha_is_enforced_and_verified_server_side(): void
    {
        config(['image-tools.captcha.enabled' => true, 'image-tools.captcha.secret' => 'test-secret']);
        Http::fake(['*' => Http::response(['success' => true])]);

        $this->postJson('/tools/image-compressor/tasks', [])->assertUnprocessable()->assertJsonPath('message', 'Human verification failed. Refresh the page and try again.');
        $this->postJson('/tools/image-compressor/tasks', ['cf-turnstile-response' => 'valid-token'])->assertUnprocessable();

        Http::assertSent(fn ($request) => $request['secret'] === 'test-secret' && $request['response'] === 'valid-token');
    }
}
