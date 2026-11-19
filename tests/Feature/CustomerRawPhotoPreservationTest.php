<?php

namespace Tests\Feature;

use App\Livewire\Shop\Product\ProductConfigurator\ProductConfigurator;
use App\Models\Cart\Cart;
use App\Models\Cart\CartItem;
use App\Models\Order\OrderOrder;
use App\Models\Order\OrderOrderItem;
use App\Models\Product\Product;
use App\Services\CartFileCleanupService;
use App\Services\CartService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class CustomerRawPhotoPreservationTest extends TestCase
{
    use RefreshDatabase;

    protected Product $product;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        Cache::put('shop_capacity_level', 0);

        $this->product = Product::create([
            'id' => (string) Str::uuid(),
            'sku' => 'TEST-RAW-PHOTO-01',
            'name' => 'Seelen-Kristall Fotogravur Test',
            'slug' => 'seelen-kristall-raw-test-' . Str::lower(Str::random(6)),
            'status' => 'active',
            'type' => 'physical',
            'price' => 3990,
            'quantity' => 15,
            'track_quantity' => true,
            'is_personalizable' => true,
            'configurator_settings' => [
                'allow_logo' => true,
                'has_back_side' => true,
                'area_shape' => 'rect',
                'area_left' => 15,
                'area_top' => 15,
                'area_width' => 70,
                'area_height' => 70,
            ],
        ]);
    }

    /**
     * Erzeugt ein echtes farbiges RGB-Portraet-Testbild mit feinen Hauttoenen RGB(235, 195, 175).
     */
    private function buildColorPortraitBytes(int $width = 1600, int $height = 1200, string $format = 'png'): string
    {
        $im = imagecreatetruecolor($width, $height);
        $bg = imagecolorallocate($im, 42, 68, 112);
        $skin = imagecolorallocate($im, 235, 195, 175);
        $warmShadow = imagecolorallocate($im, 198, 148, 126);

        imagefill($im, 0, 0, $bg);
        // Gesichtsflaeche in der Bildmitte mit exaktem Hautton RGB(235, 195, 175)
        imagefilledrectangle($im, (int) ($width * 0.25), (int) ($height * 0.20), (int) ($width * 0.75), (int) ($height * 0.80), $skin);
        // Feine Kontur am unteren Rand
        imagefilledrectangle($im, (int) ($width * 0.35), (int) ($height * 0.70), (int) ($width * 0.65), (int) ($height * 0.78), $warmShadow);

        ob_start();
        if ($format === 'jpg' || $format === 'jpeg') {
            imagejpeg($im, null, 95);
        } else {
            imagepng($im, null, 6);
        }
        $bytes = (string) ob_get_clean();
        imagedestroy($im);

        return $bytes;
    }

    #[Test]
    public function upload_in_product_configurator_preserves_byte_identical_1600x1200_raw_photo_and_creates_preview(): void
    {
        // 1. Verlustfreies 1600x1200 RGB-PNG mit exakten Hauttoenen RGB(235, 195, 175)
        $originalPngBytes = $this->buildColorPortraitBytes(1600, 1200, 'png');
        $pngUpload = UploadedFile::fake()->createWithContent('kunden_portraet.png', $originalPngBytes, 'image/png');

        $component = Livewire::test(ProductConfigurator::class, [
            'product' => $this->product,
            'context' => 'add',
        ]);

        $component->set('new_files', [$pngUpload]);
        $component->assertHasNoErrors();

        $uploadedFiles = $component->get('uploaded_files');
        $rawFiles = $component->get('raw_files');
        $logos = $component->get('logos');

        $this->assertCount(1, $uploadedFiles);
        $procPath = $uploadedFiles[0];
        $this->assertStringStartsWith('bestellungen/cart-uploads/', $procPath);
        $this->assertStringEndsWith('_proc.png', $procPath);
        Storage::disk('public')->assertExists($procPath);

        // Vorschaubild ist fuer die Konfigurator-Buehne auf max. 1200px skaliert
        $procInfo = getimagesize(Storage::disk('public')->path($procPath));
        $this->assertNotFalse($procInfo);
        $this->assertSame(1200, $procInfo[0]);
        $this->assertSame(900, $procInfo[1]);

        // Unberuehrtes Originalbild (_raw.png) existiert physisch und ist Byte-fuer-Byte identisch!
        $this->assertArrayHasKey($procPath, $rawFiles);
        $rawPath = $rawFiles[$procPath];
        $this->assertStringStartsWith('bestellungen/cart-uploads/', $rawPath);
        $this->assertStringEndsWith('_raw.png', $rawPath);
        Storage::disk('public')->assertExists($rawPath);

        $storedRawBytes = Storage::disk('public')->get($rawPath);
        $this->assertSame(hash('sha256', $originalPngBytes), hash('sha256', $storedRawBytes));
        $this->assertSame($originalPngBytes, $storedRawBytes);

        // Exakt 1600x1200 Pixel und identischer RGB-Hautton (235, 195, 175) in der Bildmitte
        $rawFullPath = Storage::disk('public')->path($rawPath);
        $rawInfo = getimagesize($rawFullPath);
        $this->assertNotFalse($rawInfo);
        $this->assertSame(1600, $rawInfo[0]);
        $this->assertSame(1200, $rawInfo[1]);

        $rawGd = imagecreatefrompng($rawFullPath);
        $centerPixel = imagecolorat($rawGd, 800, 500);
        imagedestroy($rawGd);
        $this->assertSame(235, ($centerPixel >> 16) & 0xFF);
        $this->assertSame(195, ($centerPixel >> 8) & 0xFF);
        $this->assertSame(175, $centerPixel & 0xFF);

        // Logo-Eintrag traegt raw_value
        $this->assertNotEmpty($logos);
        $this->assertSame($procPath, $logos[0]['value']);
        $this->assertSame($rawPath, $logos[0]['raw_value'] ?? null);

        // 2. Auch 1600x1200 JPG-Upload bleibt Byte-fuer-Byte als _raw.jpg erhalten
        $originalJpgBytes = $this->buildColorPortraitBytes(1600, 1200, 'jpg');
        $jpgUpload = UploadedFile::fake()->createWithContent('familienfoto.jpg', $originalJpgBytes, 'image/jpeg');
        $component->set('new_files', [$jpgUpload]);
        $component->assertHasNoErrors();

        $updatedUploads = $component->get('uploaded_files');
        $updatedRawFiles = $component->get('raw_files');
        $this->assertCount(2, $updatedUploads);
        $jpgProcPath = $updatedUploads[1];
        $jpgRawPath = $updatedRawFiles[$jpgProcPath] ?? null;
        $this->assertNotNull($jpgRawPath);
        $this->assertStringEndsWith('_raw.jpg', $jpgRawPath);
        Storage::disk('public')->assertExists($jpgRawPath);
        $this->assertSame($originalJpgBytes, Storage::disk('public')->get($jpgRawPath));

        $jpgRawInfo = getimagesize(Storage::disk('public')->path($jpgRawPath));
        $this->assertSame(1600, $jpgRawInfo[0]);
        $this->assertSame(1200, $jpgRawInfo[1]);

        // 3. Speichern in den Warenkorb & Wiederherstellung im Edit-Modus
        $component->set('config_confirmed', true);
        $component->call('save');
        $component->assertHasNoErrors();

        $cart = app(CartService::class)->getCart(false);
        $this->assertNotNull($cart);
        $cartItem = $cart->items()->first();
        $this->assertNotNull($cartItem);

        $savedConfig = $cartItem->configuration;
        $this->assertSame($rawPath, $savedConfig['raw_files'][$procPath] ?? null);
        $this->assertSame($jpgRawPath, $savedConfig['raw_files'][$jpgProcPath] ?? null);
        $this->assertSame($rawPath, $savedConfig['logos'][0]['raw_value'] ?? null);

        $editComp = Livewire::test(ProductConfigurator::class, [
            'product' => $this->product,
            'context' => 'edit',
            'cartItem' => $cartItem,
        ]);
        $this->assertSame($rawPath, $editComp->get('raw_files')[$procPath] ?? null);
        $this->assertSame($rawPath, $editComp->get('logos')[0]['raw_value'] ?? null);
    }

    #[Test]
    public function filerobot_save_edited_logo_preserves_edited_color_raw_and_keeps_initial_untrimmed_raw(): void
    {
        $initialBytes = $this->buildColorPortraitBytes(1600, 1200, 'jpg');
        $upload = UploadedFile::fake()->createWithContent('portraet_initial.jpg', $initialBytes, 'image/jpeg');

        $component = Livewire::test(ProductConfigurator::class, [
            'product' => $this->product,
            'context' => 'add',
        ]);

        $component->set('new_files', [$upload]);
        $oldProcPath = $component->get('uploaded_files')[0];
        $initialRawPath = $component->get('raw_files')[$oldProcPath];

        $croppedColorBytes = $this->buildColorPortraitBytes(800, 800, 'png');
        $b64Edited = 'data:image/png;base64,' . base64_encode($croppedColorBytes);

        $component->call('saveEditedLogo', $oldProcPath, $b64Edited);

        $newProcPath = $component->get('uploaded_files')[0];
        $rawMap = $component->get('raw_files');
        $newRawPath = $rawMap[$newProcPath] ?? null;

        $this->assertNotNull($newRawPath);
        $this->assertNotSame($initialRawPath, $newRawPath);
        $this->assertSame($initialRawPath, $rawMap[$newProcPath . ':initial'] ?? null);

        // Sowohl das neue farbige Filerobot-Ergebnis als auch das allererste Original bleiben physisch erhalten!
        Storage::disk('public')->assertExists($newRawPath);
        Storage::disk('public')->assertExists($initialRawPath);
        $this->assertSame($croppedColorBytes, Storage::disk('public')->get($newRawPath));
        $this->assertSame($initialBytes, Storage::disk('public')->get($initialRawPath));
        $this->assertSame($newRawPath, $component->get('logos')[0]['raw_value'] ?? null);
    }

    #[Test]
    public function cart_service_sanitize_configuration_preserves_raw_files_raw_value_and_laser_adjustments(): void
    {
        $cartService = app(CartService::class);
        $uuid = (string) Str::uuid();
        $proc = "bestellungen/cart-uploads/{$uuid}_proc.png";
        $raw = "bestellungen/cart-uploads/{$uuid}_raw.jpg";
        $initialRaw = "bestellungen/cart-uploads/{$uuid}_init_raw.jpg";
        $editedLaser = "bestellungen/cart-uploads/{$uuid}_laser_edit.png";

        $sanitized = $cartService->sanitizeConfiguration($this->product, [
            'files' => [$proc],
            'raw_files' => [
                $proc => $raw,
                $proc . ':initial' => $initialRaw,
                'bestellungen/cart-uploads/evil.png' => 'bestellungen/cart-uploads/../../etc/passwd',
                'outside/folder.png' => $raw,
            ],
            'logos' => [
                [
                    'id' => 'logo-front-1',
                    'type' => 'saved',
                    'value' => $proc,
                    'raw_value' => $raw,
                    'x' => 50,
                    'y' => 50,
                    'size' => 120,
                    'rotation' => 0,
                ],
            ],
            'logos_back' => [
                [
                    'id' => 'logo-back-1',
                    'type' => 'saved',
                    'value' => $proc,
                    'raw_value' => '../../etc/shadow',
                    'x' => 50,
                    'y' => 50,
                    'size' => 100,
                    'rotation' => 0,
                ],
            ],
            'laser_photo_adjustments' => [
                $proc => [
                    'brightness' => 12,
                    'contrast' => 18,
                    'edited_path' => $editedLaser,
                ],
            ],
            'laser_camera_light_mode' => 'dark_room',
        ]);

        $this->assertSame([
            $proc => $raw,
            $proc . ':initial' => $initialRaw,
        ], $sanitized['raw_files']);
        $this->assertSame($raw, $sanitized['logos'][0]['raw_value']);
        $this->assertArrayNotHasKey('raw_value', $sanitized['logos_back'][0]);
        $this->assertSame($editedLaser, $sanitized['laser_photo_adjustments'][$proc]['edited_path']);
        $this->assertSame(12, $sanitized['laser_photo_adjustments'][$proc]['brightness']);
        $this->assertSame('dark_room', $sanitized['laser_camera_light_mode']);
    }

    #[Test]
    public function cart_file_cleanup_service_never_deletes_raw_photo_while_cart_or_order_exists(): void
    {
        $cleanupService = app(CartFileCleanupService::class);

        // 1. Aktiv im Warenkorb (ueber Konfigurator angelegt)
        $cartUuid = (string) Str::uuid();
        $cartProc = "bestellungen/cart-uploads/{$cartUuid}_proc.png";
        $cartRaw = "bestellungen/cart-uploads/{$cartUuid}_raw.jpg";
        $cartEdited = "bestellungen/cart-uploads/{$cartUuid}_laser_edited.png";

        Storage::disk('public')->put($cartProc, 'proc-bytes');
        Storage::disk('public')->put($cartRaw, 'raw-bytes');
        Storage::disk('public')->put($cartEdited, 'edited-bytes');

        $cart = Cart::create(['session_id' => 'raw_test_session_1']);
        $cartConfig = [
            'files' => [$cartProc],
            'raw_files' => [$cartProc => $cartRaw],
            'logos' => [['id' => 'l1', 'type' => 'saved', 'value' => $cartProc, 'raw_value' => $cartRaw]],
            'laser_photo_adjustments' => [$cartProc => ['edited_path' => $cartEdited]],
        ];
        CartItem::create([
            'id' => (string) Str::uuid(),
            'cart_id' => $cart->id,
            'product_id' => $this->product->id,
            'quantity' => 1,
            'unit_price' => 3990,
            'total_price' => 3990,
            'configuration' => $cartConfig,
        ]);

        // Extraktions-Pruefung
        $extracted = $cleanupService->extractManagedFilePathsFromConfig($cartConfig);
        $this->assertContains($cartProc, $extracted);
        $this->assertContains($cartRaw, $extracted);
        $this->assertContains($cartEdited, $extracted);

        // 2. Aktiv in einer Bestellung, die NUR <uuid>_proc.png referenziert (UUID-Stamm-Schutz!)
        $orderUuid = (string) Str::uuid();
        $orderProc = "bestellungen/cart-uploads/{$orderUuid}_proc.png";
        $orderRaw = "bestellungen/cart-uploads/{$orderUuid}_raw.jpg";

        Storage::disk('public')->put($orderProc, 'order-proc-bytes');
        Storage::disk('public')->put($orderRaw, 'order-raw-bytes');

        $order = OrderOrder::create([
            'id' => (string) Str::uuid(),
            'order_number' => 'ORD-RAW-001',
            'status' => 'pending',
            'payment_status' => 'paid',
            'payment_method' => 'stripe',
            'email' => 'kunde@example.de',
            'subtotal_price' => 3990,
            'tax_amount' => 637,
            'total_price' => 3990,
            'billing_address' => ['first_name' => 'Alina', 'last_name' => 'Kundin'],
            'shipping_address' => ['first_name' => 'Alina', 'last_name' => 'Kundin'],
        ]);
        OrderOrderItem::create([
            'id' => (string) Str::uuid(),
            'order_id' => $order->id,
            'product_id' => $this->product->id,
            'product_name' => $this->product->name,
            'quantity' => 1,
            'unit_price' => 3990,
            'total_price' => 3990,
            'configuration' => [
                // Bewusst OHNE raw_files, um den UUID-Stamm-Fallback fuer _raw.<ext> zu beweisen!
                'files' => [$orderProc],
            ],
        ]);

        // 3. Echte verwaiste Datei ohne jede Referenz
        $orphanRaw = 'bestellungen/cart-uploads/' . Str::uuid() . '_raw.jpg';
        Storage::disk('public')->put($orphanRaw, 'orphan-bytes');

        $result = $cleanupService->cleanupOrphanedFiles(0);

        // Aktive _raw-, _proc- und edited-Dateien duerfen NIEMALS geloescht werden!
        Storage::disk('public')->assertExists($cartRaw);
        Storage::disk('public')->assertExists($cartProc);
        Storage::disk('public')->assertExists($cartEdited);
        Storage::disk('public')->assertExists($orderRaw);
        Storage::disk('public')->assertExists($orderProc);

        // Nur die echte Waise wurde entfernt
        Storage::disk('public')->assertMissing($orphanRaw);
        $this->assertContains($orphanRaw, $result['deleted_files']);
    }
}
