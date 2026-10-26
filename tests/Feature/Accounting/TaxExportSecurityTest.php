<?php

namespace Tests\Feature\Accounting;

use App\Models\Admin\Admin;
use App\Services\Export\FileDownloadService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

class TaxExportSecurityTest extends TestCase
{
    use RefreshDatabase;

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_blocks_directory_traversal_in_tax_export_download()
    {
        $service = new FileDownloadService();

        // Path traversal with ../
        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        $service->downloadTaxExport('../../.env');
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_blocks_disallowed_file_extensions_in_tax_export_download()
    {
        $service = new FileDownloadService();

        // Non-whitelisted extension like .php or .sh
        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        $service->downloadTaxExport('exploit.php');
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_allows_valid_tax_export_file_download()
    {
        $admin = Admin::factory()->create();
        $this->actingAs($admin, 'admin');

        $service = new FileDownloadService();

        $dir = storage_path('app/buchhaltung/tax_exports');
        if (!File::exists($dir)) {
            File::makeDirectory($dir, 0755, true);
        }

        $testFile = 'tax_export_test_' . Str::random(8) . '.csv';
        $fullPath = $dir . '/' . $testFile;
        File::put($fullPath, 'date,amount,vat');

        try {
            $response = $service->downloadTaxExport($testFile);
            $this->assertEquals(200, $response->getStatusCode());
        } finally {
            if (File::exists($fullPath)) {
                File::delete($fullPath);
            }
        }
    }
}
