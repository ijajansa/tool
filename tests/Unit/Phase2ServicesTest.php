<?php

namespace Tests\Unit;

use App\Services\BarcodeService;
use App\Services\GstCalculator;
use App\Services\InvoiceCalculator;
use App\Services\PdfToolkitService;
use App\Services\SafeUrlValidator;
use App\Services\WhatsAppLinkService;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class Phase2ServicesTest extends TestCase
{
    public function test_invoice_totals_include_discount_tax_and_charges(): void
    {
        $result = (new InvoiceCalculator)->calculate([['quantity' => 2, 'price' => 100, 'discount' => 10, 'tax' => 18]], 20, 5);
        $this->assertSame(237.4, $result['total']);
        $this->assertSame(20.0, $result['discount']);
        $this->assertSame(32.4, $result['tax']);
    }

    public function test_gst_intrastate_interstate_and_rounding(): void
    {
        $calculator = new GstCalculator;
        $intra = $calculator->calculate([['quantity' => 1, 'price' => 999.99, 'discount' => 0, 'gst_rate' => 18]], false);
        $this->assertSame(90.0, $intra['cgst']);
        $this->assertSame(90.0, $intra['sgst']);
        $this->assertSame(0.0, $intra['igst']);
        $this->assertSame(1180.0, $intra['total']);
        $inter = $calculator->calculate([['quantity' => 1, 'price' => 999.99, 'discount' => 0, 'gst_rate' => 18]], true);
        $this->assertSame(180.0, $inter['igst']);
        $this->assertSame(1180.0, $inter['total']);
    }

    public function test_pdf_ranges_are_strictly_validated(): void
    {
        $service = app(PdfToolkitService::class);
        $this->assertSame([[1, 2, 3], [5], [8, 9, 10]], $service->parseRanges('1-3, 5, 8-10', 10));
        $this->expectException(InvalidArgumentException::class);
        $service->parseRanges('1-99', 10);
    }

    public function test_barcode_checksums_and_formats_are_validated(): void
    {
        $service = new BarcodeService;
        $service->validate('4006381333931', 'EAN13');
        $service->validate('036000291452', 'UPCA');
        $this->assertStringContainsString('<svg', $service->render('PRODUCT-123', 'C128'));
        $this->expectException(InvalidArgumentException::class);
        $service->validate('4006381333932', 'EAN13');
    }

    public function test_whatsapp_phone_normalization_and_encoding(): void
    {
        $service = new WhatsAppLinkService;
        $this->assertSame('919876543210', $service->normalize('+91 98765-43210'));
        $this->assertSame('https://wa.me/919876543210?text=Hello%20world', $service->link('+91 98765 43210', 'Hello world'));
        $this->expectException(InvalidArgumentException::class);
        $service->normalize('9876543210');
    }

    #[DataProvider('blockedUrls')]
    public function test_ssrf_validator_blocks_non_public_destinations(string $url): void
    {
        $this->expectException(InvalidArgumentException::class);
        (new SafeUrlValidator)->validate($url);
    }

    public static function blockedUrls(): array
    {
        return [['http://127.0.0.1'], ['http://10.0.0.1'], ['http://169.254.169.254/latest/meta-data'], ['http://[::1]'], ['file:///etc/passwd'], ['http://metadata.google.internal']];
    }
}
