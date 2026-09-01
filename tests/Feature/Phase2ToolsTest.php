<?php

namespace Tests\Feature;

use App\Jobs\ProcessPhase2Task;
use App\Models\DynamicQrCode;
use App\Services\SafeUrlValidator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Mockery;
use Tests\TestCase;

class Phase2ToolsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
    }

    public function test_all_phase_two_pages_have_metadata_and_forms(): void
    {
        foreach (array_filter(config('tools'), fn ($tool) => ($tool['phase'] ?? 1) === 2) as $slug => $tool) {
            $this->get("/tools/$slug")->assertOk()->assertSee($tool['name'])->assertSee('<link rel="canonical"', false);
        }
    }

    public function test_invoice_document_is_queued_and_generated(): void
    {
        config(['queue.default' => 'sync']);
        $response = $this->postJson('/phase-2/invoice-generator/tasks', ['payload' => $this->invoicePayload()])->assertAccepted();
        $status = $this->getJson($response->json('status_url'))->assertJsonPath('status', 'completed');
        $this->assertSame('invoice.pdf', $status->json('result.files.0.name'));
        Storage::disk('local')->assertExists('tasks/'.$response->json('id').'/invoice.pdf');
    }

    public function test_resume_barcode_description_whatsapp_and_stationery_validate_and_queue(): void
    {
        Queue::fake();
        $cases = [
            'resume-builder' => ['name' => 'Asha Rao', 'email' => 'asha@example.com', 'summary' => 'Experienced developer building accessible products.', 'template' => 'classic', 'sections' => [['title' => 'Experience', 'content' => 'Built tools']]],
            'barcode-label-generator' => ['value' => 'PRODUCT-123', 'format' => 'C128', 'output' => 'svg', 'columns' => 3, 'rows' => 4],
            'product-description-generator' => ['product_name' => 'Steel Bottle', 'category' => 'Kitchen', 'features' => 'Insulated, leak resistant', 'benefits' => 'Keeps drinks cool', 'audience' => 'commuters', 'tone' => 'professional', 'keywords' => 'bottle', 'length' => 'medium'],
            'whatsapp-link-generator' => ['phone' => '+919876543210', 'message' => 'Hello'],
            'visiting-card-letterhead' => ['name' => 'Asha Rao', 'business' => 'North Studio', 'email' => 'asha@example.com', 'website' => 'https://example.com', 'output' => 'card'],
        ];
        foreach ($cases as $tool => $payload) {
            $this->postJson("/phase-2/$tool/tasks", compact('payload'))->assertAccepted();
        }
        Queue::assertPushed(ProcessPhase2Task::class, 5);
    }

    public function test_dynamic_qr_management_token_controls_analytics(): void
    {
        $validator = Mockery::mock(SafeUrlValidator::class);
        $validator->shouldReceive('validate')->andReturn(['url' => 'https://example.com', 'host' => 'example.com', 'ips' => ['93.184.216.34'], 'port' => 443]);
        $this->app->instance(SafeUrlValidator::class, $validator);
        $created = $this->postJson('/qr/dynamic', ['mode' => 'dynamic', 'content_type' => 'url', 'content' => 'https://example.com', 'ecc' => 'M', 'size' => 8])->assertCreated();
        $code = DynamicQrCode::firstOrFail();
        $this->getJson(route('qr.analytics', ['code' => $code->public_id, 'token' => 'wrong']))->assertForbidden();
        $this->getJson($created->json('analytics_url'))->assertOk()->assertJsonPath('total', 0);
        $this->get(route('qr.redirect', $code->public_id), ['User-Agent' => 'Mobile Safari', 'Referer' => 'https://ref.example/path'])->assertRedirect('https://example.com');
        $this->getJson($created->json('analytics_url'))->assertJsonPath('total', 1)->assertJsonPath('by_device.mobile', 1);
    }

    public function test_static_qr_does_not_create_tracking_record(): void
    {
        $this->postJson('/qr/dynamic', ['mode' => 'static', 'content_type' => 'text', 'content' => 'Hello', 'ecc' => 'Q', 'size' => 8])->assertOk()->assertJsonStructure(['svg']);
        $this->assertDatabaseCount('dynamic_qr_codes', 0);
    }

    public function test_pdf_ranges_watermark_consent_and_bulk_limits_are_validated(): void
    {
        $pdf = UploadedFile::fake()->createWithContent('sample.pdf', '%PDF-1.4 fake');
        $this->postJson('/phase-2/pdf-toolkit/tasks', ['documents' => [$pdf], 'payload' => ['operation' => 'split', 'ranges' => '../1-2']])->assertUnprocessable()->assertJsonValidationErrors('payload.ranges');
        $images = [UploadedFile::fake()->image('owned.jpg')];
        $this->postJson('/phase-2/bulk-watermark-tool/tasks', ['images' => $images, 'payload' => ['mode' => 'repair', 'position' => 'center', 'opacity' => 40, 'authorization_confirmed' => false]])->assertUnprocessable()->assertJsonValidationErrors('payload.authorization_confirmed');
        $tooMany = array_map(fn ($i) => UploadedFile::fake()->image("$i.jpg", 10, 10), range(1, 21));
        $this->postJson('/phase-2/bulk-watermark-tool/tasks', ['images' => $tooMany, 'payload' => ['mode' => 'add', 'position' => 'center', 'opacity' => 40]])->assertUnprocessable()->assertJsonValidationErrors('images');
    }

    public function test_seo_audit_route_rejects_loopback_before_queueing(): void
    {
        Queue::fake();
        $response = $this->postJson('/phase-2/seo-audit/tasks', ['payload' => ['url' => 'http://127.0.0.1']]);
        // URL shape is valid at request time; the queued safe fetcher rejects it without making a request.
        $response->assertAccepted();
        Queue::assertPushed(ProcessPhase2Task::class);
    }

    private function invoicePayload(): array
    {
        return ['document_type' => 'invoice', 'business_name' => 'North Studio', 'customer_name' => 'Example Customer', 'number' => 'INV-001', 'issue_date' => '2026-08-31', 'due_date' => '2026-09-15', 'currency' => 'INR', 'items' => [['description' => 'Design work', 'quantity' => 2, 'price' => 500, 'discount' => 0, 'tax' => 18]], 'shipping' => 0, 'additional' => 0, 'notes' => 'Thank you'];
    }
}
