<?php

namespace Tests\Feature\Accounting;

use App\Models\Admin\Admin;
use App\Models\Accounting\AccountingSpecialIssue;
use App\Services\FinancialService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FinancialServiceTaxExportSecurityTest extends TestCase
{
    use RefreshDatabase;

    public function test_tax_export_writes_to_private_storage_not_public_disk()
    {
        $admin = Admin::create([
            'email' => 'admin-tax@example.com',
            'password' => bcrypt('password123'),
            'first_name' => 'Admin',
            'last_name' => 'Tax',
        ]);

        AccountingSpecialIssue::create([
            'admin_id' => $admin->id,
            'title' => 'Software Lizenz',
            'category' => 'Software',
            'amount' => 119.00,
            'tax_rate' => 19.0,
            'execution_date' => now(),
            'is_business' => true,
        ]);

        $service = new FinancialService();
        $zipPath = $service->generateTaxExport($admin->id, 10, 2026);

        // Path must be located inside private storage, NEVER public storage
        $this->assertStringContainsString('private/exports', str_replace('\\', '/', $zipPath));
        $this->assertStringNotContainsString('public/systemsteuerung', str_replace('\\', '/', $zipPath));
        $this->assertFileExists($zipPath);

        // Cleanup
        if (file_exists($zipPath)) {
            @unlink($zipPath);
        }
    }
}
