<?php

namespace Tests\Feature\Livewire\Shop\Accounting;

use App\Livewire\Shop\Accounting\AccountingQuickEntry;
use App\Models\Accounting\AccountingSpecialIssue;
use App\Models\Admin\Admin;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AccountingQuickEntryTest extends TestCase
{
    use RefreshDatabase;

    protected Admin $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $adminId = (string) Str::uuid();
        DB::table('admins')->insert([
            'id' => $adminId,
            'first_name' => 'Test',
            'last_name' => 'Admin',
            'email' => 'admin-' . uniqid() . '@example.com',
            'password' => bcrypt('password123'),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->admin = Admin::find($adminId);
        $this->actingAs($this->admin, 'admin');

        Storage::fake('local');
    }

    #[Test]
    public function it_renders_successfully_with_camera_scan_ui()
    {
        Livewire::test(AccountingQuickEntry::class)
            ->assertStatus(200)
            ->assertSee('Foto-Scan mit KI')
            ->assertSee('Beleg fotografieren')
            ->assertSee('sofort automatisch in Buchhaltung speichern');
    }

    #[Test]
    public function it_processes_photo_with_ai_and_populates_form_fields()
    {
        // Fake Gemini API response
        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response([
                'candidates' => [
                    [
                        'content' => [
                            'parts' => [
                                [
                                    'text' => json_encode([
                                        'merchant' => 'Bauhaus',
                                        'description' => 'Schrauben & Dübel',
                                        'title' => 'Bauhaus - Schrauben & Dübel',
                                        'amount' => 45.80,
                                        'date' => '2026-10-15',
                                        'location' => 'Gifhorn',
                                        'category' => 'Werkzeug',
                                        'is_business' => true,
                                        'tax_rate' => 19,
                                        'invoice_number' => 'BH-99182',
                                    ])
                                ]
                            ]
                        ]
                    ]
                ]
            ], 200),
        ]);

        $photo = UploadedFile::fake()->image('kassenbon.jpg', 600, 800);

        Livewire::test(AccountingQuickEntry::class)
            ->set('specialPhoto', $photo)
            ->assertSet('specialTitle', 'Bauhaus - Schrauben & Dübel')
            ->assertSet('specialAmount', '-45.80')
            ->assertSet('specialCategory', 'Werkzeug')
            ->assertSet('specialDate', '2026-10-15')
            ->assertSet('specialLocation', 'Gifhorn')
            ->assertSet('specialIsBusiness', true)
            ->assertSet('specialTaxRate', 19.0)
            ->assertSet('specialInvoiceNumber', 'BH-99182')
            ->assertSet('isAutoFilled', true)
            ->assertSee('Beleg erkannt: Bauhaus - Schrauben & Dübel (-45.80 €)');
    }

    #[Test]
    public function it_automatically_saves_special_issue_when_autosave_is_enabled()
    {
        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response([
                'candidates' => [
                    [
                        'content' => [
                            'parts' => [
                                [
                                    'text' => json_encode([
                                        'merchant' => 'Shell',
                                        'description' => 'Diesel',
                                        'title' => 'Shell - Diesel',
                                        'amount' => 74.50,
                                        'date' => '2026-10-18',
                                        'location' => 'Wolfsburg',
                                        'category' => 'Tanken',
                                        'is_business' => true,
                                        'tax_rate' => 19,
                                        'invoice_number' => 'SH-4421',
                                    ])
                                ]
                            ]
                        ]
                    ]
                ]
            ], 200),
        ]);

        $photo = UploadedFile::fake()->image('tankbeleg.jpg', 500, 700);

        Livewire::test(AccountingQuickEntry::class)
            ->set('autoSaveAfterScan', true)
            ->set('specialPhoto', $photo)
            ->assertDispatched('special-issue-created');

        $this->assertDatabaseHas('accounting_special_issues', [
            'admin_id' => $this->admin->id,
            'title' => 'Shell - Diesel',
            'category' => 'Tanken',
            'amount' => -74.50,
            'execution_date' => '2026-10-18',
            'location' => 'Wolfsburg',
            'is_business' => true,
            'tax_rate' => 19,
            'invoice_number' => 'SH-4421',
        ]);

        $created = AccountingSpecialIssue::where('title', 'Shell - Diesel')->first();
        $this->assertNotNull($created);
        $this->assertNotEmpty($created->file_paths);
        $this->assertStringContainsString('buchhaltung/receipts/', $created->file_paths[0]);
    }

    #[Test]
    public function it_handles_pdf_uploads_without_preview_exception()
    {
        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response([
                'candidates' => [
                    [
                        'content' => [
                            'parts' => [
                                [
                                    'text' => json_encode([
                                        'merchant' => 'Telekom',
                                        'description' => 'Internetanschluss',
                                        'title' => 'Telekom - Internetanschluss',
                                        'amount' => 49.95,
                                        'date' => '2026-10-10',
                                        'location' => 'Bonn',
                                        'category' => 'Telefon & Internet',
                                        'is_business' => true,
                                        'tax_rate' => 19,
                                        'invoice_number' => 'TEL-8812',
                                    ])
                                ]
                            ]
                        ]
                    ]
                ]
            ], 200),
        ]);

        $pdf = UploadedFile::fake()->create('rechnung.pdf', 100, 'application/pdf');

        Livewire::test(AccountingQuickEntry::class)
            ->set('specialPhoto', $pdf)
            ->assertStatus(200)
            ->assertSet('specialTitle', 'Telekom - Internetanschluss')
            ->assertSet('specialAmount', '-49.95')
            ->assertSee('PDF');
    }
}
