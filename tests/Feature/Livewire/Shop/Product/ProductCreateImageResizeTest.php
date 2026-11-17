<?php

namespace Tests\Feature\Livewire\Shop\Product;

use App\Livewire\Shop\Product\ProductCreate;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Regression: SystemLog WARNING livewire_component_error vom 17.11.2026
 * "Implicit conversion from float 1445.647... to int loses precision" in
 * ProductCreate::resizeAndSaveImage (imagecreatetruecolor mit Float-Maßen).
 */
class ProductCreateImageResizeTest extends TestCase
{
    private function invokeResize(string $source, string $destination): void
    {
        $component = new ProductCreate();
        $method = new \ReflectionMethod(ProductCreate::class, 'resizeAndSaveImage');
        $method->setAccessible(true);

        set_error_handler(function (int $errno, string $errstr, string $errfile = '', int $errline = 0) {
            throw new \ErrorException($errstr, 0, $errno, $errfile, $errline);
        }, E_DEPRECATED | E_USER_DEPRECATED | E_WARNING);

        try {
            $method->invoke($component, $source, $destination);
        } finally {
            restore_error_handler();
        }
    }

    private function makePng(int $w, int $h): string
    {
        $path = sys_get_temp_dir() . '/sf_resize_' . bin2hex(random_bytes(6)) . '.png';
        $img = imagecreatetruecolor($w, $h);
        imagefill($img, 0, 0, imagecolorallocate($img, 200, 160, 90));
        imagepng($img, $path);
        imagedestroy($img);

        return $path;
    }

    #[Test]
    public function portrait_upload_with_non_integer_ratio_resizes_without_float_deprecation(): void
    {
        if (!function_exists('imagecreatetruecolor')) {
            $this->markTestSkipped('GD nicht verfügbar');
        }

        Storage::fake('public');
        $source = $this->makePng(2550, 3387);

        try {
            $this->invokeResize($source, 'produkte/test/portrait.jpg');
        } finally {
            @unlink($source);
        }

        Storage::disk('public')->assertExists('produkte/test/portrait.jpg');
        [$w, $h] = getimagesizefromstring(Storage::disk('public')->get('produkte/test/portrait.jpg'));
        $this->assertSame(1920, $h);
        $this->assertSame((int) round(1920 * 2550 / 3387), $w);
    }

    #[Test]
    public function landscape_upload_is_capped_at_1920_width(): void
    {
        if (!function_exists('imagecreatetruecolor')) {
            $this->markTestSkipped('GD nicht verfügbar');
        }

        Storage::fake('public');
        $source = $this->makePng(3001, 2000);

        try {
            $this->invokeResize($source, 'produkte/test/landscape.jpg');
        } finally {
            @unlink($source);
        }

        [$w, $h] = getimagesizefromstring(Storage::disk('public')->get('produkte/test/landscape.jpg'));
        $this->assertSame(1920, $w);
        $this->assertSame((int) round(1920 * 2000 / 3001), $h);
    }

    #[Test]
    public function unreadable_source_is_ignored_silently(): void
    {
        Storage::fake('public');
        $source = sys_get_temp_dir() . '/sf_resize_bad_' . bin2hex(random_bytes(6)) . '.png';
        file_put_contents($source, 'kein bild');

        try {
            $this->invokeResize($source, 'produkte/test/broken.jpg');
        } finally {
            @unlink($source);
        }

        Storage::disk('public')->assertMissing('produkte/test/broken.jpg');
    }
}
