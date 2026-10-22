<?php

namespace Tests\Unit\Services;

use App\Services\Accounting\ContractDocumentService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

class ContractDocumentServiceTest extends TestCase
{
    protected ContractDocumentService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new ContractDocumentService();
        Storage::fake('local');
    }

    #[Test]
    public function it_generates_standard_logical_filename()
    {
        $fileName = $this->service->generateLogicalFileName(
            '2026-08-11',
            'ADAC Autoversicherung AG',
            'Autoversicherung',
            'pdf'
        );

        $this->assertEquals('2026-08-11_ADAC_Autoversicherung.pdf', $fileName);
    }

    #[Test]
    public function it_generates_logical_path_with_year_and_month_structure()
    {
        $path = $this->service->generateLogicalPath(
            '2026-08-11',
            'ADAC',
            'Autoversicherung',
            'pdf'
        );

        $this->assertEquals('buchhaltung/contracts/2026/08/2026-08-11_ADAC_Autoversicherung.pdf', $path);
    }

    #[Test]
    public function it_sanitizes_german_umlauts_and_special_characters()
    {
        $fileName = $this->service->generateLogicalFileName(
            '2026-10-22',
            'Münchner Rückversicherung AG',
            'Büro & Werkstatt-Schutz',
            'pdf'
        );

        $this->assertEquals('2026-10-22_Muenchner_Rueckversicherung_Buero_Werkstatt_Schutz.pdf', $fileName);
    }

    #[Test]
    public function it_stores_uploaded_contract_file_in_logical_path()
    {
        $file = UploadedFile::fake()->create('random_download_file_xyz123.pdf', 100, 'application/pdf');

        $storedPath = $this->service->storeContractFile($file, [
            'first_payment_date' => '2026-08-11',
            'provider_company' => 'Mittwald CM Service GmbH & Co. KG',
            'name' => 'Managed vServer'
        ]);

        $this->assertEquals('buchhaltung/contracts/2026/08/2026-08-11_Mittwald_CM_Service_Managed_vServer.pdf', $storedPath);
        Storage::disk('local')->assertExists($storedPath);
    }

    #[Test]
    public function it_cleans_up_old_contract_file_when_replaced()
    {
        $oldPath = 'buchhaltung/contracts/2026/08/old_contract.pdf';
        Storage::disk('local')->put($oldPath, 'old content');
        Storage::disk('local')->assertExists($oldPath);

        $file = UploadedFile::fake()->create('new_contract.pdf', 100, 'application/pdf');

        $newPath = $this->service->storeContractFile($file, [
            'first_payment_date' => '2026-08-11',
            'provider_company' => 'Mittwald',
            'name' => 'Cloud'
        ], $oldPath);

        Storage::disk('local')->assertExists($newPath);
        Storage::disk('local')->assertMissing($oldPath);
    }

    #[Test]
    public function it_analyzes_contract_document_or_falls_back_gracefully()
    {
        $realPdfPath = storage_path('app/buchhaltung/contracts/2026/08/2026-08-11_ADAC_Autoversicherung.pdf');
        if (!file_exists($realPdfPath)) {
            $this->markTestSkipped('ADAC contract file does not exist on disk.');
        }

        $analysis = $this->service->analyzeDocument($realPdfPath);

        $this->assertNotNull($analysis);
        $this->assertTrue($analysis['success']);
        $this->assertNotEmpty($analysis['suggested_filename']);
        $this->assertNotEmpty($analysis['suggested_path']);
        $this->assertStringContainsString('ADAC', $analysis['provider_company'] . $analysis['provider_clean']);
    }
}
