<?php

namespace Tests\Feature\Livewire\Shop\Product;

use App\Livewire\Shop\Product\ProductConfigurator\ProductConfigurator;
use App\Models\Product\Product;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ProductConfiguratorFileUploadSecurityTest extends TestCase
{
    use DatabaseTransactions;

    protected Product $product;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        Cache::put('shop_capacity_level', 0);

        DB::table('tax_rates')->insertOrIgnore([
            ['name' => 'Standard DE', 'rate' => 19.00, 'country_code' => 'DE', 'tax_class' => 'standard', 'is_default' => true, 'created_at' => now(), 'updated_at' => now()],
        ]);

        $this->product = Product::create([
            'name' => 'Sicherheits-Testbox',
            'slug' => 'security-testbox-' . uniqid(),
            'status' => 'active',
            'type' => 'physical',
            'price' => 3000,
            'weight' => 0.5,
            'is_personalizable' => true,
            'configurator_settings' => [
                'allow_logo' => true,
                'has_back_side' => true,
            ],
        ]);

        Storage::fake('public');
    }

    #[Test]
    public function valid_png_image_is_accepted_and_converted_with_laser_effect()
    {
        $file = UploadedFile::fake()->image('test_logo.png', 100, 100);

        $component = Livewire::test(ProductConfigurator::class, [
            'product' => $this->product,
        ]);

        $component->set('new_files', [$file]);

        $component->assertHasNoErrors();
        $uploaded = $component->get('uploaded_files');
        $this->assertCount(1, $uploaded);
        $this->assertStringStartsWith('bestellungen/cart-uploads/', $uploaded[0]);
        $this->assertStringEndsWith('.png', $uploaded[0]);
        Storage::disk('public')->assertExists($uploaded[0]);
    }

    #[Test]
    public function valid_jpg_image_is_accepted_and_reencoded_to_png()
    {
        $file = UploadedFile::fake()->image('test_photo.jpg', 150, 150);

        $component = Livewire::test(ProductConfigurator::class, [
            'product' => $this->product,
        ]);

        $component->set('new_files', [$file]);

        $component->assertHasNoErrors();
        $uploaded = $component->get('uploaded_files');
        $this->assertCount(1, $uploaded);
        $this->assertStringEndsWith('.png', $uploaded[0]);
        Storage::disk('public')->assertExists($uploaded[0]);
    }

    #[Test]
    public function valid_svg_is_accepted_and_stored()
    {
        $svgContent = '<svg xmlns="http://www.w3.org/2000/svg" width="100" height="100"><circle cx="50" cy="50" r="40" fill="green" /></svg>';
        $file = UploadedFile::fake()->createWithContent('valid.svg', $svgContent);

        $component = Livewire::test(ProductConfigurator::class, [
            'product' => $this->product,
        ]);

        $component->set('new_files', [$file]);

        $component->assertHasNoErrors();
        $uploaded = $component->get('uploaded_files');
        $this->assertCount(1, $uploaded);
        $this->assertStringEndsWith('.svg', $uploaded[0]);
        Storage::disk('public')->assertExists($uploaded[0]);
    }

    #[Test]
    public function svg_with_script_tag_has_script_stripped_cleanly()
    {
        $maliciousSvg = '<svg xmlns="http://www.w3.org/2000/svg" width="100" height="100">'
            . '<script type="text/javascript">alert("XSS")</script>'
            . '<rect width="100" height="100" fill="red" />'
            . '</svg>';

        $file = UploadedFile::fake()->createWithContent('script_attack.svg', $maliciousSvg);

        $component = Livewire::test(ProductConfigurator::class, [
            'product' => $this->product,
        ]);

        $component->set('new_files', [$file]);

        $component->assertHasNoErrors();
        $uploaded = $component->get('uploaded_files');
        $this->assertCount(1, $uploaded);
        $savedContent = Storage::disk('public')->get($uploaded[0]);
        $this->assertStringNotContainsString('<script', $savedContent);
        $this->assertStringNotContainsString('alert', $savedContent);
        $this->assertStringContainsString('<rect', $savedContent);
    }

    #[Test]
    public function svg_with_event_handler_has_handler_stripped()
    {
        $maliciousSvg = '<svg xmlns="http://www.w3.org/2000/svg" onload="alert(document.cookie)" width="100" height="100">'
            . '<circle cx="50" cy="50" r="40" onclick="alert(1)" />'
            . '</svg>';

        $file = UploadedFile::fake()->createWithContent('event_attack.svg', $maliciousSvg);

        $component = Livewire::test(ProductConfigurator::class, [
            'product' => $this->product,
        ]);

        $component->set('new_files', [$file]);

        $component->assertHasNoErrors();
        $uploaded = $component->get('uploaded_files');
        $this->assertCount(1, $uploaded);
        $savedContent = Storage::disk('public')->get($uploaded[0]);
        $this->assertStringNotContainsString('onload', $savedContent);
        $this->assertStringNotContainsString('onclick', $savedContent);
        $this->assertStringNotContainsString('document.cookie', $savedContent);
    }

    #[Test]
    public function svg_with_foreign_object_has_it_stripped()
    {
        $maliciousSvg = '<svg xmlns="http://www.w3.org/2000/svg">'
            . '<foreignObject width="100" height="100">'
            . '<body xmlns="http://www.w3.org/1999/xhtml">'
            . '<iframe src="http://evil.com"></iframe>'
            . '</body>'
            . '</foreignObject>'
            . '<circle cx="10" cy="10" r="5" />'
            . '</svg>';

        $file = UploadedFile::fake()->createWithContent('foreign_obj.svg', $maliciousSvg);

        $component = Livewire::test(ProductConfigurator::class, [
            'product' => $this->product,
        ]);

        $component->set('new_files', [$file]);

        $component->assertHasNoErrors();
        $uploaded = $component->get('uploaded_files');
        $this->assertCount(1, $uploaded);
        $savedContent = Storage::disk('public')->get($uploaded[0]);
        $this->assertStringNotContainsString('foreignObject', $savedContent);
        $this->assertStringNotContainsString('iframe', $savedContent);
        $this->assertStringNotContainsString('evil.com', $savedContent);
    }

    #[Test]
    public function svg_with_javascript_href_has_href_stripped()
    {
        $maliciousSvg = '<svg xmlns="http://www.w3.org/2000/svg">'
            . '<a href="javascript:alert(1)"><text x="10" y="20">Click</text></a>'
            . '</svg>';

        $file = UploadedFile::fake()->createWithContent('href_attack.svg', $maliciousSvg);

        $component = Livewire::test(ProductConfigurator::class, [
            'product' => $this->product,
        ]);

        $component->set('new_files', [$file]);

        $component->assertHasNoErrors();
        $uploaded = $component->get('uploaded_files');
        $this->assertCount(1, $uploaded);
        $savedContent = Storage::disk('public')->get($uploaded[0]);
        $this->assertStringNotContainsString('javascript:', $savedContent);
    }

    #[Test]
    public function non_svg_xml_file_is_rejected()
    {
        $htmlContent = '<html><body><h1>Not an SVG</h1></body></html>';
        $file = UploadedFile::fake()->createWithContent('fake.svg', $htmlContent);

        $component = Livewire::test(ProductConfigurator::class, [
            'product' => $this->product,
        ]);

        $component->set('new_files', [$file]);
        $component->assertHasErrors(['new_files.0']);
        $this->assertCount(0, $component->get('uploaded_files'));
    }

    #[Test]
    public function valid_pdf_is_accepted_and_stored()
    {
        $fpdf = new \FPDF();
        $fpdf->AddPage();
        $fpdf->SetFont('Arial', 'B', 16);
        $fpdf->Cell(40, 10, 'Test PDF Document');
        $validPdf = $fpdf->Output('S');

        $file = UploadedFile::fake()->createWithContent('document.pdf', $validPdf, 'application/pdf');

        $component = Livewire::test(ProductConfigurator::class, [
            'product' => $this->product,
        ]);

        $component->set('new_files', [$file]);

        $component->assertHasNoErrors();
        $uploaded = $component->get('uploaded_files');
        $this->assertCount(1, $uploaded);
        $this->assertStringEndsWith('.pdf', $uploaded[0]);
        Storage::disk('public')->assertExists($uploaded[0]);
    }

    #[Test]
    public function pdf_with_embedded_javascript_is_rejected()
    {
        $maliciousPdf = "%PDF-1.4\n"
            . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Names << /JavaScript 4 0 R >> >>\nendobj\n"
            . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
            . "3 0 obj\n<< /Type /Page /Parent 2 0 R >>\nendobj\n"
            . "4 0 obj\n<< /Names [(JS) << /S /JavaScript /JS (app.alert('PWNED')) >>] >>\nendobj\n"
            . "xref\n0 5\n"
            . "trailer\n<< /Size 5 /Root 1 0 R >>\nstartxref\n200\n%%EOF";

        $file = UploadedFile::fake()->createWithContent('malicious_js.pdf', $maliciousPdf);

        $component = Livewire::test(ProductConfigurator::class, [
            'product' => $this->product,
        ]);

        $component->set('new_files', [$file]);

        $component->assertHasErrors(['new_files.0']);
        $this->assertCount(0, $component->get('uploaded_files'));
    }

    #[Test]
    public function pdf_with_launch_action_is_rejected()
    {
        $maliciousPdf = "%PDF-1.4\n"
            . "1 0 obj\n<< /Type /Action /S /Launch /F (cmd.exe) >>\nendobj\n"
            . "trailer\n<< /Root 1 0 R >>\n%%EOF";

        $file = UploadedFile::fake()->createWithContent('launch.pdf', $maliciousPdf);

        $component = Livewire::test(ProductConfigurator::class, [
            'product' => $this->product,
        ]);

        $component->set('new_files', [$file]);

        $component->assertHasErrors(['new_files.0']);
        $this->assertCount(0, $component->get('uploaded_files'));
    }

    #[Test]
    public function fake_pdf_not_starting_with_magic_bytes_is_rejected()
    {
        $fakePdf = "This is not a real PDF file, just plain text.";
        $file = UploadedFile::fake()->createWithContent('fake.pdf', $fakePdf);

        $component = Livewire::test(ProductConfigurator::class, [
            'product' => $this->product,
        ]);

        $component->set('new_files', [$file]);

        $component->assertHasErrors(['new_files.0']);
        $this->assertCount(0, $component->get('uploaded_files'));
    }

    #[Test]
    public function double_extension_executable_is_rejected()
    {
        $file = UploadedFile::fake()->createWithContent('shell.php.jpg', '<?php phpinfo(); ?>');

        $component = Livewire::test(ProductConfigurator::class, [
            'product' => $this->product,
        ]);

        $component->set('new_files', [$file]);

        $component->assertHasErrors(['new_files.0']);
        $this->assertCount(0, $component->get('uploaded_files'));
    }

    #[Test]
    public function php_file_disguised_with_image_mime_is_rejected()
    {
        $file = UploadedFile::fake()->createWithContent('payload.php', '<?php system($_GET["c"]); ?>');

        $component = Livewire::test(ProductConfigurator::class, [
            'product' => $this->product,
        ]);

        $component->set('new_files', [$file]);

        $component->assertHasErrors(['new_files.0']);
        $this->assertCount(0, $component->get('uploaded_files'));
    }

    #[Test]
    public function filename_with_path_traversal_is_rejected()
    {
        $file = UploadedFile::fake()->createWithContent('../../../evil.png', 'fake image bytes');

        $component = Livewire::test(ProductConfigurator::class, [
            'product' => $this->product,
        ]);

        $component->set('new_files', [$file]);

        $component->assertHasErrors(['new_files.0']);
        $this->assertCount(0, $component->get('uploaded_files'));
    }

    #[Test]
    public function total_uploaded_files_cannot_exceed_ten()
    {
        $existing = array_map(fn($i) => "bestellungen/cart-uploads/file_{$i}.png", range(1, 8));

        $component = Livewire::test(ProductConfigurator::class, [
            'product' => $this->product,
            'initialData' => ['files' => $existing],
        ]);

        // Trying to upload 3 more (total 11 > 10)
        $files = [
            UploadedFile::fake()->image('img1.png', 50, 50),
            UploadedFile::fake()->image('img2.png', 50, 50),
            UploadedFile::fake()->image('img3.png', 50, 50),
        ];

        $component->set('new_files', $files);

        $component->assertHasErrors(['new_files']);
        // Uploaded files should stay at 8
        $this->assertCount(8, $component->get('uploaded_files'));
    }

    #[Test]
    public function remove_file_does_not_delete_files_outside_cart_uploads()
    {
        // Place a critical file in system directory
        Storage::disk('public')->put('system/important.json', '{"do_not_delete": true}');

        $component = Livewire::test(ProductConfigurator::class, [
            'product' => $this->product,
            'initialData' => ['files' => ['system/important.json']],
        ]);

        // Attempting to call removeFile on an external path
        $component->call('removeFile', 0);

        // The critical file must NOT be deleted!
        Storage::disk('public')->assertExists('system/important.json');
    }

    #[Test]
    public function save_with_snapshot_prevents_path_traversal_in_side()
    {
        $dummyBase64 = 'data:image/jpeg;base64,/9j/4AAQSkZJRgABAQEASABIAAD/2wBDAP//////////////////////////////////////////////////////////////////////////////////////wgALCAABAAEBAREA/8QAFBABAAAAAAAAAAAAAAAAAAAAAP/aAAgBAQABPxA=';

        $component = Livewire::test(ProductConfigurator::class, [
            'product' => $this->product,
            'context' => 'calculator',
        ]);

        // Malicious side name trying path traversal
        $component->call('saveWithSnapshot', [
            '../../traversal' => $dummyBase64,
        ]);

        $component->assertHasNoErrors();
        // Ensure no file was created with .. traversal
        $allPublicFiles = Storage::disk('public')->allFiles();
        foreach ($allPublicFiles as $file) {
            $this->assertStringNotContainsString('..', $file);
        }
    }
}
