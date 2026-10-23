<?php

namespace Tests\Feature\Accounting;

use App\Livewire\Shop\Accounting\AccountingTax;
use App\Models\Accounting\AccountingCostItem;
use App\Models\Accounting\AccountingGroup;
use App\Models\Accounting\AccountingSpecialIssue;
use App\Models\Admin\Admin;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

class AccountingTaxReceiptSecurityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
    }

    public function test_upload_missing_receipt_enforces_mime_whitelist()
    {
        $admin = Admin::factory()->create();
        $this->actingAs($admin, 'admin');

        $issue = AccountingSpecialIssue::create([
            'id' => Str::uuid(),
            'admin_id' => $admin->id,
            'title' => 'Büromaterial',
            'amount' => -50.00,
            'is_business' => true,
            'execution_date' => now()->startOfMonth(),
        ]);

        $issueId = (string) $issue->id;
        $fakePhp = UploadedFile::fake()->create('malicious.php', 10, 'text/x-php');

        Livewire::test(AccountingTax::class)
            ->set('receiptFiles.' . $issueId, $fakePhp)
            ->call('uploadMissingReceipt', $issueId, 'variable')
            ->assertHasErrors(['receiptFiles.' . $issueId]);

        $issue->refresh();
        $this->assertEmpty($issue->file_paths);
    }

    public function test_admin_cannot_upload_receipt_to_another_admins_expense()
    {
        $adminA = Admin::factory()->create();
        $adminB = Admin::factory()->create();

        $issueB = AccountingSpecialIssue::create([
            'id' => Str::uuid(),
            'admin_id' => $adminB->id,
            'title' => 'Admin B Expense',
            'amount' => -100.00,
            'is_business' => true,
            'execution_date' => now()->startOfMonth(),
        ]);

        // Admin A logged in
        $this->actingAs($adminA, 'admin');

        $validPdf = UploadedFile::fake()->create('rechnung.pdf', 50, 'application/pdf');
        $issueBId = (string) $issueB->id;

        Livewire::test(AccountingTax::class)
            ->set('receiptFiles.' . $issueBId, $validPdf)
            ->call('uploadMissingReceipt', $issueBId, 'variable');

        $issueB->refresh();
        $this->assertEmpty($issueB->file_paths, 'Admin A must not be able to attach receipts to Admin B expense');
    }

    public function test_delete_export_only_deletes_zip_files()
    {
        $admin = Admin::factory()->create();
        $this->actingAs($admin, 'admin');

        Storage::disk('local')->put('buchhaltung/tax_exports/important_data.txt', 'do not delete');
        Storage::disk('local')->put('buchhaltung/tax_exports/TaxExport_2026_10.zip', 'zip content');

        Livewire::test(AccountingTax::class)
            ->call('deleteExport', 'important_data.txt')
            ->call('deleteExport', 'TaxExport_2026_10.zip');

        $this->assertTrue(Storage::disk('local')->exists('buchhaltung/tax_exports/important_data.txt'));
        $this->assertFalse(Storage::disk('local')->exists('buchhaltung/tax_exports/TaxExport_2026_10.zip'));
    }
}
