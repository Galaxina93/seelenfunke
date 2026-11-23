<?php

namespace App\Models\Product;

use App\Support\Blueprint\Concerns\InteractsWithSystemBlueprint;
use App\Support\Blueprint\Contracts\HasSystemBlueprint;
use App\Support\Blueprint\Enums\ActorScope;
use App\Support\Blueprint\Enums\AiAccessPolicy;
use App\Support\Blueprint\Enums\DataSensitivity;
use App\Support\Blueprint\Enums\ExternalChannel;
use App\Support\Blueprint\Enums\LifecycleBehavior;
use App\Support\Blueprint\Enums\RetentionPolicy;
use App\Support\Blueprint\Enums\SystemDepartment;
use App\Support\Blueprint\ModelBlueprint;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LaserStudioProject extends Model implements HasSystemBlueprint
{
    use HasUuids;
    use InteractsWithSystemBlueprint;

    public static function blueprint(): ModelBlueprint
    {
        return ModelBlueprint::make(
            label: 'Backend Laser-Studio Projekt (Freier Laser-Konfigurator)',
            description: 'Universelle interne Manufaktur- & Laserprojekte (Geschenkkarten, QR-Werbekarten, Muster und Kunden-Unikate) auf Live-Kamera-Ansicht des xTool F2 Ultra UV ohne Buchhaltungs-Scheinbestellung.',
            department: SystemDepartment::Product,
            navigationNodes: ['admin.product-configurator'],
            lifecycle: LifecycleBehavior::MasterData,
            sensitivity: DataSensitivity::InternalErp,
            retention: RetentionPolicy::PermanentMasterData,
            aiAccess: AiAccessPolicy::ReadAndPropose,
            actorsRead: [ActorScope::Admin, ActorScope::EmployeeProduction],
            actorsWrite: [ActorScope::Admin, ActorScope::EmployeeProduction],
            regulations: [],
            externalChannels: [ExternalChannel::LaserMachine_SVG_PDF],
            dependsOnSettings: [],
            isGdprArt15Exportable: false,
            customerForeignKey: null,
            containsProductionAssets: false,
            assetColumns: [],
        );
    }

    protected $fillable = [
        'title',
        'status',
        'is_favorite',
        'mode',
        'product_id',
        'width_mm',
        'height_mm',
        'contour_shape',
        'has_back_side',
        'surface_tone',
        'configuration',
        'last_lasered_at',
    ];

    protected $casts = [
        'is_favorite' => 'boolean',
        'width_mm' => 'float',
        'height_mm' => 'float',
        'has_back_side' => 'boolean',
        'configuration' => 'array',
        'last_lasered_at' => 'datetime',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    /**
     * Kompatibilitäts-Accessor für XtoolLaserCockpitService & FileDownloadService.
     */
    public function getProductNameAttribute(): string
    {
        return (string) ($this->title ?: 'Laser-Studio Unikat');
    }

    /**
     * Virtueller Order-Accessor, damit .xcs / .xf Dateinamen und Queue-Metadaten in XtoolLaserCockpitService
     * nahtlos wie bei einer Bestellposition erzeugt werden, ohne eine echte Order in der Buchhaltung anzulegen.
     */
    public function getOrderAttribute(): object
    {
        $shortId = strtoupper(substr(str_replace('-', '', (string) $this->id), 0, 6)) ?: 'STUDIO';

        return (object) [
            'id' => null,
            'order_number' => 'STUDIO-' . $shortId,
            'customer_id' => null,
        ];
    }

    /**
     * Synchronisiert die physischen Projektmaße & Konturvorgaben in das `configuration`-JSON,
     * damit `FileDownloadService::resolvePhysicalBlankCalibration` 1:1 maßstabsgetreu arbeitet.
     */
    public function syncStudioDimensionsToConfig(): void
    {
        $conf = is_array($this->configuration) ? $this->configuration : [];
        $conf['is_laser_studio_project'] = true;
        $conf['studio_width_mm'] = round(max(5.0, min(200.0, (float) $this->width_mm)), 2);
        $conf['studio_height_mm'] = round(max(5.0, min(200.0, (float) $this->height_mm)), 2);
        $conf['studio_contour_shape'] = (string) ($this->contour_shape ?: 'rect');
        $this->configuration = $conf;
    }

    /**
     * Baut eine transiente, in-memory `Product`-Instanz für den echten Seelenfunke-`ProductConfigurator`
     * im Modus `context = 'laser_freehand'` (ohne Datenbank-Verschmutzung in `products`!).
     *
     * Im freien Kamera-Bett-Modus (`free_bed`, 200x200 mm) ist die gesamte 2D-Fläche (0..100 %) freigeschaltet
     * und bildet 1:1 den 200x200 mm xTool-Kamerascreenshot ab. Im Karten-/Eigenmaß-Modus (`custom_card`) wird
     * der Arbeitsrahmen (z. B. 86x54 mm = 43% x 27%) maßstabsgetreu auf der exakten Kameraposition eingeblendet.
     */
    public function toVirtualConfiguratorProduct(string $side = 'front'): Product
    {
        $conf = is_array($this->configuration) ? $this->configuration : [];
        $wMm = max(5.0, min(200.0, (float) ($this->width_mm ?: 200.0)));
        $hMm = max(5.0, min(200.0, (float) ($this->height_mm ?: 200.0)));
        $isFreeBed = ($this->mode === 'free_bed') || ($wMm >= 198.0 && $hMm >= 198.0);
        $effectiveSide = ($side === 'back') ? 'back' : 'front';

        if ($isFreeBed) {
            $areaLeft = 0.0;
            $areaTop = 0.0;
            $areaWidth = 100.0;
            $areaHeight = 100.0;
        } else {
            $coords = is_array($conf['laser_cockpit_coordinates'][$effectiveSide] ?? null)
                ? $conf['laser_cockpit_coordinates'][$effectiveSide]
                : (is_array($conf['laser_cockpit_coordinates']['front'] ?? null) ? $conf['laser_cockpit_coordinates']['front'] : []);
            $cxMm = max(5.0, min(195.0, (float) ($coords['center_x_mm'] ?? 100.0)));
            $cyMm = max(5.0, min(195.0, (float) ($coords['center_y_mm'] ?? 100.0)));
            $areaWidth = round(max(3.0, min(100.0, ($wMm / 200.0) * 100.0)), 3);
            $areaHeight = round(max(3.0, min(100.0, ($hMm / 200.0) * 100.0)), 3);
            $areaLeft = round(max(0.0, min(100.0 - $areaWidth, (($cxMm / 200.0) * 100.0) - ($areaWidth / 2.0))), 3);
            $areaTop = round(max(0.0, min(100.0 - $areaHeight, (($cyMm / 200.0) * 100.0) - ($areaHeight / 2.0))), 3);
        }

        $areaShape = ($this->contour_shape === 'circle') ? 'circle' : 'rect';
        $hasCameraFile = \Illuminate\Support\Facades\Storage::disk('public')->exists('bestellungen/xtool/background.jpg');

        $virtual = new Product();
        $virtual->forceFill([
            'id' => (string) $this->id,
            'name' => (string) ($this->title ?: 'Laser-Studio Unikat'),
            'slug' => 'laser-studio-freehand',
            'status' => 'active',
            'type' => 'physical',
            'price' => 0,
            'compare_at_price' => 0,
            'tax_included' => true,
            'tax_rate' => 19.0,
            'width' => $wMm,
            'height' => $hMm,
            'three_d_active' => false,
            'three_d_model_path' => null,
            'three_d_background_path' => null,
            'preview_image_path' => $hasCameraFile ? 'bestellungen/xtool/background.jpg' : null,
            'media_gallery' => [],
            'variants_data' => [],
            'tier_pricing' => [],
            'addons_data' => [],
            'configurator_settings' => [
                'personalization_mode' => 'studio',
                'allow_logo' => true,
                'enable_background_video' => false,
                'has_back_side' => (bool) $this->has_back_side,
                'overlay_type' => 'plane',
                'area_shape' => $areaShape,
                'area_left' => $areaLeft,
                'area_top' => $areaTop,
                'area_width' => $areaWidth,
                'area_height' => $areaHeight,
                'xtool_plane_width' => $wMm,
                'xtool_plane_height' => $hMm,
                'template_package' => 'custom',
            ],
        ]);

        return $virtual;
    }
}
