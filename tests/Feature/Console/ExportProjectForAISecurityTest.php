<?php

namespace Tests\Feature\Console;

use App\Models\Admin\Admin;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class ExportProjectForAISecurityTest extends TestCase
{
    use RefreshDatabase;

    public function test_export_project_for_ai_sanitizes_output_path_against_traversal()
    {
        $traversalName = '../../export_traversal_test.txt';
        $safeName = 'export_traversal_test.txt';

        // Clean up beforehand if exists
        if (File::exists(base_path($safeName))) {
            File::delete(base_path($safeName));
        }

        $this->artisan('project:export', [
            '--output' => $traversalName,
            '--short' => true,
        ])->assertExitCode(0);

        // It must NOT write outside base_path
        $this->assertFalse(File::exists(base_path('../' . $safeName)));

        // It should write to base_path($safeName)
        $this->assertTrue(File::exists(base_path($safeName)));

        // Cleanup
        File::delete(base_path($safeName));
    }

    public function test_generate_tax_export_validates_month_and_year_parameters()
    {
        Admin::factory()->create();

        // Month 99 is invalid
        $this->artisan('generate-tax-export', [
            '--month' => '99',
            '--year' => '2026',
        ])->assertExitCode(1);

        // Year 1800 is invalid
        $this->artisan('generate-tax-export', [
            '--month' => '5',
            '--year' => '1800',
        ])->assertExitCode(1);
    }
}
