<?php

namespace App\Models\Product;


use App\Support\Blueprint\Concerns\InteractsWithSystemBlueprint;
use App\Support\Blueprint\Contracts\HasSystemBlueprint;
use App\Support\Blueprint\Enums\ActorScope;
use App\Support\Blueprint\Enums\AiAccessPolicy;
use App\Support\Blueprint\Enums\ComplianceRegulation;
use App\Support\Blueprint\Enums\DataSensitivity;
use App\Support\Blueprint\Enums\ExternalChannel;
use App\Support\Blueprint\Enums\LifecycleBehavior;
use App\Support\Blueprint\Enums\RetentionPolicy;
use App\Support\Blueprint\Enums\SystemDepartment;
use App\Support\Blueprint\ModelBlueprint;
use App\Models\Product\ProductCategory;
use App\Services\PriceCalcService;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;

class Product extends Model implements HasSystemBlueprint
{
    
    use InteractsWithSystemBlueprint;

    /**
     * Digitales Architektur- & Compliance-Typenschild (Living Documentation).
     */
    public static function blueprint(): ModelBlueprint
    {
        return ModelBlueprint::make(
            label: 'Manufaktur-Produkt & Konfigurator-Stammdaten',
            description: 'Produktkatalog inkl. verifizierter Rohling-Maße, 2D-Overlay, 3D-GLB-Modell, EK/VK-Kalkulation, GPSR und KI-Kennzeichnung.',
            department: SystemDepartment::Product,
            navigationNodes: ['admin.products.index', 'shop.product.show', 'shop.product.configurator'],
            lifecycle: LifecycleBehavior::MasterData,
            sensitivity: DataSensitivity::PublicCatalog,
            retention: RetentionPolicy::PermanentMasterData,
            aiAccess: AiAccessPolicy::ReadAndPropose,
            actorsRead: [ActorScope::PublicVisitor, ActorScope::Customer, ActorScope::EmployeeProduction, ActorScope::Admin],
            actorsWrite: [ActorScope::Admin, ActorScope::EmployeeProduction],
            regulations: [ComplianceRegulation::GPSR_EU_2023_988, ComplianceRegulation::EU_AI_Act_Art50, ComplianceRegulation::PAngV, ComplianceRegulation::VerpackG_LUCID, ComplianceRegulation::BGB_312g_CustomGoods],
            externalChannels: [ExternalChannel::LaserMachine_SVG_PDF, ExternalChannel::GoogleGemini_AI],
            dependsOnSettings: ['default_tax_rate', 'reduced_tax_rate', 'is_small_business', 'prices_entered_gross'],
            isGdprArt15Exportable: false,
            customerForeignKey: null,
            containsProductionAssets: false,
            assetColumns: [],
        );
    }

use HasFactory, HasUuids, SoftDeletes;

    protected $guarded = [];

    protected $casts = [
        'media_gallery' => 'array',
        'attributes' => 'array',
        'tier_pricing' => 'array',
        'configurator_settings' => 'array',
        'is_personalizable' => 'boolean',
        'three_d_active' => 'boolean',
        'track_quantity' => 'boolean',
        'continue_selling_when_out_of_stock' => 'boolean',
        'price' => 'integer',
        'compare_at_price' => 'integer',
        'weight' => 'integer',
        'height' => 'integer',
        'width' => 'integer',
        'length' => 'integer',
        'variants_data' => 'array',
        'packaging_weight' => 'integer',
        'marketing_cost_percent' => 'float',
    ];

    protected function priceEuro(): Attribute
    {
        return Attribute::make(
            get: fn ($value, $attributes) => $attributes['price'] / 100,
            set: fn ($value) => [
                'price' => (int) round((float)str_replace(',', '.', (string) $value) * 100)
            ],
        );
    }

    public function getNetPriceAttribute(): int
    {
        $calculator = new PriceCalcService();
        if ($this->tax_included) {
            return $calculator->getNetFromGross($this->price, (float)$this->tax_rate);
        }
        return $this->price;
    }

    public function getGrossPriceAttribute(): int
    {
        $calculator = new PriceCalcService();
        if ($this->tax_included) {
            return $this->price;
        }
        return $calculator->getGrossFromNet($this->price, (float)$this->tax_rate);
    }

    public function getFormattedPriceAttribute()
    {
        return number_format($this->gross_price / 100, 2, ',', '.') . ' €';
    }

    public function getStatusColorAttribute()
    {
        return match($this->status) {
            'active' => 'bg-green-100 text-green-800',
            'draft' => 'bg-amber-100 text-amber-800',
            'archived' => 'bg-gray-100 text-gray-800',
            default => 'bg-gray-100',
        };
    }

    public function getProgressColorAttribute()
    {
        return $this->completion_step >= 4 ? 'text-green-600 bg-green-50' : 'text-red-600 bg-red-50';
    }

    public function getRouteKeyName() { return 'slug'; }

    public function getTaxRateAttribute(): float
    {
        if (shop_setting('is_small_business', false)) {
            return 0.00;
        }

        if ($this->tax_class === 'zero' || $this->slug === 'geschenkgutschein') {
            return 0.00;
        }

        $globalDefault = (float)shop_setting('default_tax_rate', 19.00);

        if ($this->tax_class === 'standard' || empty($this->tax_class)) {
            return $globalDefault;
        }

        if ($this->tax_class === 'reduced') {
            return (float)shop_setting('reduced_tax_rate', 7.00);
        }

        return $globalDefault;
    }

    public function isAvailable(): bool
    {
        if ($this->status !== 'active') {
            return false;
        }

        // Bei digitalen Produkten oder Dienstleistungen ist "Lager" oft irrelevant
        // Wir prüfen trotzdem track_quantity, falls limitierte Plätze/Lizenzen verkauft werden
        if (!$this->track_quantity) {
            return true;
        }

        if ($this->continue_selling_when_out_of_stock) {
            return true;
        }

        return $this->quantity > 0;
    }

    /**
     * Liefert den aktuellen Herkunfts-Siegel-Modus des Produkts:
     * - 'handcrafted_in_germany' -> "Handveredelt in Deutschland" (UWG-konformer Standard für in DE veredelte Rohlinge)
     * - 'made_in_germany'        -> Wird aus UWG-Gründen (§ 5 UWG / Madrider Abkommen) ebenfalls als "Handveredelt in Deutschland" ausgegeben
     * - 'none'                   -> Kein Siegel
     */
    public function getOriginBadgeMode(): string
    {
        $settings = is_array($this->configurator_settings) ? $this->configurator_settings : [];
        $explicit = $settings['origin_badge'] ?? null;

        if ($explicit === 'none') {
            return 'none';
        }

        if ($this->type !== 'physical') {
            return 'none';
        }

        return 'handcrafted_in_germany';
    }

    /**
     * Liefert den sichtbaren Text des Herkunfts-Siegels für Shop-Kacheln & Produktdetailseite (oder null bei 'none').
     */
    public function getOriginBadgeLabel(): ?string
    {
        return match ($this->getOriginBadgeMode()) {
            'made_in_germany', 'handcrafted_in_germany' => 'Handveredelt in Deutschland',
            default                                     => null,
        };
    }

    public function isAtelierLine(): bool
    {
        return $this->type === 'physical' && ($this->collection_line ?? 'manufaktur') === 'atelier';
    }

    public function isManufakturLine(): bool
    {
        return $this->type === 'physical' && ($this->collection_line ?? 'manufaktur') !== 'atelier';
    }

    public function resolveCollectionWorld(): string
    {
        if ($this->slug === 'geschenkgutschein' || $this->type === 'digital') {
            return 'voucher';
        }
        if ($this->isAtelierLine()) {
            return 'atelier';
        }
        return 'manufaktur';
    }

    public function getCollectionLineLabelAttribute(): string
    {
        if ($this->type === 'digital') {
            return 'Geschenkgutscheine & Digital';
        }
        if ($this->type === 'service') {
            return 'Dienstleistung & Service';
        }
        return ($this->collection_line ?? 'manufaktur') === 'atelier'
            ? 'Atelier Echtgold & Silber'
            : 'Manufaktur-Kollektion';
    }

    /**
     * Normalisiert einen einzelnen Eintrag für digitale Begleitdokumente (z. B. Echtheitszertifikat, Pflegehinweise).
     *
     * @param array<string, mixed> $doc
     * @return array{id: string, title: string, path: string, filename: string, extension: string, size_bytes: int, uploaded_at: string}|null
     */
    public static function normalizeDigitalDocumentItem(array $doc, int $index = 0): ?array
    {
        $path = trim((string) ($doc['path'] ?? ''));
        if ($path === '') {
            return null;
        }

        $filename = trim((string) ($doc['filename'] ?? basename($path)));
        if ($filename === '') {
            $filename = basename($path);
        }

        $extension = strtolower(trim((string) ($doc['extension'] ?? pathinfo($filename, PATHINFO_EXTENSION) ?: pathinfo($path, PATHINFO_EXTENSION) ?: 'pdf')));
        $defaultTitle = pathinfo($filename, PATHINFO_FILENAME) ?: ('Dokument ' . ($index + 1));
        $title = trim((string) ($doc['title'] ?? $defaultTitle));
        if ($title === '') {
            $title = $defaultTitle;
        }

        $id = trim((string) ($doc['id'] ?? ''));
        if ($id === '') {
            $id = 'doc_' . substr(md5($path . '|' . $index), 0, 12);
        }

        return [
            'id' => $id,
            'title' => $title,
            'path' => str_replace('\\', '/', $path),
            'filename' => $filename,
            'extension' => $extension,
            'size_bytes' => max(0, (int) ($doc['size_bytes'] ?? 0)),
            'uploaded_at' => trim((string) ($doc['uploaded_at'] ?? '')),
        ];
    }

    /**
     * Liefert alle am Produkt hinterlegten digitalen Begleitdokumente (z. B. Echtheitszertifikat, Pflegeanleitung),
     * die unabhängig vom Produkttyp nach dem Kauf im Kundenportal unter "Dokumente" bereitgestellt werden.
     *
     * @return array<int, array{id: string, title: string, path: string, filename: string, extension: string, size_bytes: int, uploaded_at: string}>
     */
    public function getDigitalDocuments(): array
    {
        $settings = is_array($this->configurator_settings) ? $this->configurator_settings : [];
        $rawDocs = is_array($settings['digital_documents'] ?? null) ? $settings['digital_documents'] : [];

        $normalized = [];
        foreach (array_values($rawDocs) as $index => $doc) {
            if (!is_array($doc)) {
                continue;
            }
            $item = self::normalizeDigitalDocumentItem($doc, $index);
            if ($item !== null) {
                $normalized[] = $item;
            }
        }

        return $normalized;
    }

    /**
     * Prüft, ob für dieses Produkt digitale Begleitdokumente hinterlegt sind.
     */
    public function hasDigitalDocuments(): bool
    {
        return count($this->getDigitalDocuments()) > 0;
    }

    public function tierPrices()
    {
        return $this->hasMany(ProductTierPrice::class)->orderBy('qty', 'asc');
    }

    public function getTaxIncludedAttribute(): bool
    {
        return (bool) shop_setting('prices_entered_gross', true);
    }

    /**
     * Prüft, ob eine einzelne Produktvariante kaufbar / auf Lager ist.
     */
    public function isVariantAvailable(array $variant): bool
    {
        if (!filter_var($variant['is_active'] ?? true, FILTER_VALIDATE_BOOLEAN)) {
            return false;
        }

        if (!$this->track_quantity || $this->continue_selling_when_out_of_stock) {
            return true;
        }

        if (isset($variant['stock']) && $variant['stock'] !== '' && $variant['stock'] !== null) {
            return (int) $variant['stock'] > 0;
        }

        return true;
    }

    /**
     * Liefert alle Varianten-bildenden Eigenschaften (nur Eigenschaften mit >= 2 kommagetrennten Werten,
     * die nicht explizit von der Varianten-Matrix ausgeschlossen wurden).
     *
     * @return array<string, array<int, string>>
     */
    public function getVariantAttributeOptions(): array
    {
        $rawAttributes = $this->getAttribute('attributes');
        if (is_string($rawAttributes)) {
            $rawAttributes = json_decode($rawAttributes, true);
        }
        $attributes = is_array($rawAttributes) ? $rawAttributes : [];
        $settings = is_array($this->configurator_settings) ? $this->configurator_settings : [];
        $excluded = is_array($settings['excluded_variant_attributes'] ?? null) ? $settings['excluded_variant_attributes'] : [];
        $included = is_array($settings['included_variant_attributes'] ?? null) ? $settings['included_variant_attributes'] : [];

        $defaultInfoOnlyKeys = ['Gewicht', 'Lieferzeit', 'Auslieferung', 'Sprache', 'Seiten', 'Dauer', 'Ort', 'Experte', 'Herkunft'];

        $parsed = [];
        foreach ($attributes as $key => $val) {
            if (!is_string($key) || empty($val) || !is_scalar($val)) {
                continue;
            }
            if (in_array($key, $excluded, true)) {
                continue;
            }
            if (in_array($key, $defaultInfoOnlyKeys, true) && !in_array($key, $included, true)) {
                continue;
            }

            $options = array_values(array_unique(array_filter(
                array_map('trim', explode(',', (string) $val)),
                fn ($v) => $v !== ''
            )));

            // WICHTIG: Nur Eigenschaften mit mindestens 2 Optionen bilden Varianten!
            if (count($options) > 1) {
                $parsed[$key] = $options;
            }
        }

        return $parsed;
    }

    /**
     * Bereinigt bestehende Varianten-Einträge in variants_data von Einzelwert-Attributen
     * (wie z. B. "Größe: 180x200x40 mm" oder "Material: Kristallglas", die nur 1 Wert haben),
     * damit nur echte Varianten-Namen (z. B. "Transparent", "Matt", "Blickdicht") übrig bleiben.
     */
    public function getSanitizedVariantsData(bool $persistIfChanged = false): array
    {
        $rawVariants = $this->variants_data;
        if (!is_array($rawVariants) || empty($rawVariants)) {
            return [];
        }

        $validAttrKeys = array_keys($this->getVariantAttributeOptions());
        if (empty($validAttrKeys)) {
            return $rawVariants;
        }

        $changed = false;
        $cleaned = [];

        foreach ($rawVariants as $v) {
            if (!is_array($v)) {
                continue;
            }
            $vAttrs = is_array($v['attributes'] ?? null) ? $v['attributes'] : [];
            if (!empty($vAttrs)) {
                $filteredAttrs = array_intersect_key($vAttrs, array_flip($validAttrKeys));
                if (!empty($filteredAttrs) && count($filteredAttrs) !== count($vAttrs)) {
                    $ksorted = $filteredAttrs;
                    ksort($ksorted);
                    $v['attributes'] = $filteredAttrs;
                    $v['name'] = implode(' - ', $filteredAttrs);
                    $v['signature'] = implode('|', array_map(fn ($k, $val) => "$k:$val", array_keys($ksorted), $ksorted));
                    $changed = true;
                }
            }
            $cleaned[] = $v;
        }

        if ($changed && $persistIfChanged && $this->exists) {
            $this->variants_data = $cleaned;
            $this->saveQuietly();
        }

        return $cleaned;
    }

    /**
     * Liefert die auswählbaren Einzel-Varianten aus der Matrix für Produktbilder unter "Medien" (Schritt 2).
     *
     * @return array<string, string>
     */
    public function getMediaVariantBindingOptions(): array
    {
        $variants = [];

        foreach ($this->getSanitizedVariantsData(true) as $v) {
            if (!is_array($v)) {
                continue;
            }
            if (!empty($v['id']) && !empty($v['name'])) {
                $variants['var:' . $v['id']] = (string) $v['name'];
            }
        }

        return $variants;
    }

    /**
     * Übersetzt einen gespeicherten variant_binding-String in ein lesbares Label.
     */
    public function resolveMediaVariantBindingLabel(?string $binding): ?string
    {
        if (!$binding || trim($binding) === '') {
            return null;
        }

        $varId = str_starts_with($binding, 'var:') ? substr($binding, 4) : $binding;
        $variant = collect($this->getSanitizedVariantsData(false))->firstWhere('id', $varId);

        return $variant['name'] ?? null;
    }

    public function reduceStock(int $amount, ?string $variantId = null): bool
    {
        if ($amount <= 0) {
            return false;
        }

        // 1. Varianten-Bestand reduzieren, falls eine Variante mit eigenem Bestand gekauft wurde
        if ($variantId && is_array($this->variants_data) && count($this->variants_data) > 0) {
            $variants = $this->variants_data;
            $variantUpdated = false;

            foreach ($variants as $idx => $variant) {
                if (($variant['id'] ?? null) === $variantId) {
                    if (isset($variant['stock']) && $variant['stock'] !== '' && $variant['stock'] !== null) {
                        $currentStock = (int) $variant['stock'];
                        if ($this->track_quantity && !$this->continue_selling_when_out_of_stock && $currentStock < $amount) {
                            return false;
                        }
                        $variants[$idx]['stock'] = max(0, $currentStock - $amount);
                        $variantUpdated = true;
                    }
                    break;
                }
            }

            if ($variantUpdated) {
                $this->variants_data = $variants;
                $this->saveQuietly();
            }
        }

        if (!$this->track_quantity) {
            return true;
        }

        if (!$this->continue_selling_when_out_of_stock) {
            $affected = static::where('id', $this->id)
                ->where('quantity', '>=', $amount)
                ->decrement('quantity', $amount);

            if ($affected <= 0) {
                return false;
            }

            $this->quantity = (int) static::where('id', $this->id)->value('quantity');
            return true;
        }

        return (bool) $this->decrement('quantity', $amount);
    }

    public function restoreStock(int $amount, ?string $variantId = null): void
    {
        if ($amount <= 0) {
            return;
        }

        if ($variantId && is_array($this->variants_data) && count($this->variants_data) > 0) {
            $variants = $this->variants_data;
            $variantUpdated = false;

            foreach ($variants as $idx => $variant) {
                if (($variant['id'] ?? null) === $variantId) {
                    if (isset($variant['stock']) && $variant['stock'] !== '' && $variant['stock'] !== null) {
                        $variants[$idx]['stock'] = max(0, (int) $variant['stock'] + $amount);
                        $variantUpdated = true;
                    }
                    break;
                }
            }

            if ($variantUpdated) {
                $this->variants_data = $variants;
                $this->saveQuietly();
            }
        }

        if ($this->track_quantity) {
            $this->increment('quantity', $amount);
        }
    }

    public function increaseStock(int $amount, ?string $variantId = null): void
    {
        $this->restoreStock($amount, $variantId);
    }

    /**
     * Ermittelt den aktuell verfügbaren Lagerbestand (unter Berücksichtigung einer optionalen Varianten-ID).
     */
    public function getAvailableStockQuantity(?string $variantId = null): int
    {
        if ($variantId !== null && $variantId !== '' && is_array($this->variants_data) && count($this->variants_data) > 0) {
            foreach ($this->variants_data as $variant) {
                if (is_array($variant) && ($variant['id'] ?? null) === $variantId) {
                    if (isset($variant['stock']) && $variant['stock'] !== '' && $variant['stock'] !== null) {
                        return (int) $variant['stock'];
                    }
                    break;
                }
            }
        }

        return (int) ($this->quantity ?? 0);
    }

    /**
     * Prüft, ob dieses physische Produkt bei der angefragten Menge eine Rohling-Nachbestellung beim Lieferanten benötigt
     * (d. h. Bestandsführung aktiv, Überverkauf erlaubt und verfügbarer Bestand < angefragte Menge).
     */
    public function requiresSupplierProcurement(int $requestedQty = 1, ?string $variantId = null): bool
    {
        if (!$this->isPhysical() || $this->isGiftVoucher()) {
            return false;
        }

        if (!$this->track_quantity || !$this->continue_selling_when_out_of_stock) {
            return false;
        }

        $needed = max(1, $requestedQty);
        return $this->getAvailableStockQuantity($variantId) < $needed;
    }

    /**
     * Ermittelt die fehlende Rohling-Menge, die beim Lieferanten nachbestellt werden muss.
     */
    public function getMissingStockQuantity(int $requestedQty = 1, ?string $variantId = null): int
    {
        if (!$this->requiresSupplierProcurement($requestedQty, $variantId)) {
            return 0;
        }

        $available = max(0, $this->getAvailableStockQuantity($variantId));
        return max(0, max(1, $requestedQty) - $available);
    }

    /**
     * Ermittelt die zusätzliche Lieferanten-Vorlaufzeit (Min/Max in Werktagen) bei Bestand 0:
     * 1. Produktspezifischer Override aus configurator_settings['out_of_stock_lead_days_min' / 'max']
     * 2. Automatischer Fallback aus dem verknüpften ProductSupplier (lead_time_land_days / lead_time_air_days)
     * 3. Sicherheits-Fallback (4-6 Werktage für Manufaktur, 5-7 Werktage für Atelier)
     *
     * @return array{min: int, max: int, source: string, supplier_name: ?string}
     */
    public function getSupplierLeadDaysRange(): array
    {
        $settings = is_array($this->configurator_settings) ? $this->configurator_settings : [];
        $customMin = (isset($settings['out_of_stock_lead_days_min']) && $settings['out_of_stock_lead_days_min'] !== '' && $settings['out_of_stock_lead_days_min'] !== null)
            ? max(0, (int) $settings['out_of_stock_lead_days_min'])
            : 0;
        $customMax = (isset($settings['out_of_stock_lead_days_max']) && $settings['out_of_stock_lead_days_max'] !== '' && $settings['out_of_stock_lead_days_max'] !== null)
            ? max(0, (int) $settings['out_of_stock_lead_days_max'])
            : 0;

        $supplier = $this->relationLoaded('supplier')
            ? $this->supplier
            : (!empty($this->product_supplier_id) ? $this->supplier()->first() : null);
        $supplierName = $supplier?->name;

        if ($customMin > 0 || $customMax > 0) {
            $min = $customMin > 0 ? $customMin : max(1, $customMax - 2);
            $max = $customMax >= $min ? $customMax : ($min + 2);

            return [
                'min' => $min,
                'max' => $max,
                'source' => 'custom',
                'supplier_name' => $supplierName,
            ];
        }

        if ($supplier) {
            $landDays = max(0, (int) ($supplier->lead_time_land_days ?? 0));
            $airDays = max(0, (int) ($supplier->lead_time_air_days ?? 0));
            $baseDays = $landDays > 0 ? $landDays : ($airDays > 0 ? $airDays : 0);

            if ($baseDays > 0) {
                return [
                    'min' => $baseDays,
                    'max' => $baseDays + 2,
                    'source' => 'supplier',
                    'supplier_name' => $supplierName,
                ];
            }
        }

        $defaultMin = $this->isAtelierLine() ? 5 : 4;
        $defaultMax = $this->isAtelierLine() ? 7 : 6;

        return [
            'min' => $defaultMin,
            'max' => $defaultMax,
            'source' => 'default',
            'supplier_name' => $supplierName,
        ];
    }

    /**
     * Liefert die effektiven Lieferzeit-Details (inkl. Lieferanten-Vorlaufzeit bei Bestand 0 + Manufakturzeit).
     */
    public function getEffectiveDeliveryDetails(int $requestedQty = 1, ?string $variantId = null): array
    {
        return \App\Models\Delivery\DeliverySetting::getDeliveryDetailsForProduct($this, $requestedQty, $variantId);
    }

    // --- NEUE TYP-LOGIK ---

    public function isPersonalizable(): bool
    {
        return (bool) $this->is_personalizable;
    }

    /**
     * Gibt den Personalisierungs-Modus zurück:
     * - 'studio': Interaktiver 2D/3D-Live-Konfigurator (Standard)
     * - 'custom_fields': Schlanke Schnell-Personalisierung über dynamische Eingabefelder (mit !-Info-Tooltips)
     */
    public function getPersonalizationMode(): string
    {
        if (!$this->isPersonalizable()) {
            return 'none';
        }

        $settings = is_array($this->configurator_settings) ? $this->configurator_settings : [];
        $mode = strtolower(trim((string) ($settings['personalization_mode'] ?? 'studio')));

        return in_array($mode, ['studio', 'custom_fields'], true) ? $mode : 'studio';
    }

    public function usesCustomFieldsPersonalization(): bool
    {
        return $this->isPersonalizable() && $this->getPersonalizationMode() === 'custom_fields';
    }

    public function usesStudioConfigurator(): bool
    {
        return $this->isPersonalizable() && $this->getPersonalizationMode() === 'studio';
    }

    /**
     * Normalisiert ein einzelnes dynamisches Eingabefeld für den Schnell-Personalisierungs-Modus.
     */
    public static function normalizeCustomFieldItem(array $field, int $index = 0): ?array
    {
        $label = trim((string) ($field['label'] ?? ''));
        if ($label === '') {
            return null;
        }

        $rawId = trim((string) ($field['id'] ?? ''));
        $id = $rawId !== '' ? preg_replace('/[^a-zA-Z0-9_-]/', '_', $rawId) : ('cf_' . ($index + 1));

        $type = strtolower(trim((string) ($field['type'] ?? 'text')));
        if (!in_array($type, ['text', 'textarea', 'select', 'font', 'date'], true)) {
            $type = 'text';
        }
        if ($id === 'cf_wunschtext' && $type === 'text') {
            $type = 'textarea';
        }

        $placeholder = trim((string) ($field['placeholder'] ?? ''));
        $infoText = trim((string) ($field['info_text'] ?? ''));
        $required = filter_var($field['required'] ?? true, FILTER_VALIDATE_BOOLEAN);

        $defaultMaxLen = $type === 'textarea' ? 150 : ($type === 'font' ? 60 : 30);
        $maxLength = isset($field['max_length']) && (int) $field['max_length'] > 0
            ? min(500, max(1, (int) $field['max_length']))
            : $defaultMaxLen;
        if ($id === 'cf_wunschtext' && $maxLength < 80) {
            $maxLength = 80;
        }

        // Auswahl-Optionen für 'select' und 'font'
        $options = [];
        if (isset($field['options_raw']) && is_string($field['options_raw']) && trim($field['options_raw']) !== '') {
            $parts = preg_split('/[,;\r\n]+/', $field['options_raw']) ?: [];
            foreach ($parts as $part) {
                $trimmed = trim($part);
                if ($trimmed !== '') {
                    $options[] = $trimmed;
                }
            }
        } elseif (isset($field['options']) && is_array($field['options'])) {
            foreach ($field['options'] as $opt) {
                $trimmed = trim((string) $opt);
                if ($trimmed !== '') {
                    $options[] = $trimmed;
                }
            }
        } elseif (isset($field['options']) && is_string($field['options'])) {
            $parts = preg_split('/[,;\r\n]+/', $field['options']) ?: [];
            foreach ($parts as $part) {
                $trimmed = trim($part);
                if ($trimmed !== '') {
                    $options[] = $trimmed;
                }
            }
        }
        $options = array_values(array_unique($options));

        if ($type === 'font') {
            $availableFonts = array_keys(app(\App\Services\ConfiguratorService::class)->getFonts());
            $fontLookup = [];
            foreach ($availableFonts as $fName) {
                $fontLookup[mb_strtolower($fName)] = $fName;
            }
            $validFonts = [];
            foreach ($options as $opt) {
                $key = mb_strtolower(trim((string) $opt));
                if (isset($fontLookup[$key])) {
                    $validFonts[] = $fontLookup[$key];
                }
            }
            $options = !empty($validFonts) ? array_values(array_unique($validFonts)) : $availableFonts;
            $maxLength = max(60, $maxLength);
            // Bei Schriftart-Dropdowns ist immer „Arial“ (bzw. die erste aktive Schrift) fest vorausgewählt:
            // Weder ein „!“-Hinweis noch eine Pflichtfeld-Markierung (*) ergeben hier Sinn.
            $infoText = '';
            $required = false;
            $placeholder = '';
        }

        // Optionaler Aufpreis (in Cent + formatiert in Euro)
        if (isset($field['surcharge_price']) && $field['surcharge_price'] !== '' && $field['surcharge_price'] !== null) {
            $surchargeCents = max(0, (int) round(((float) str_replace(',', '.', (string) $field['surcharge_price'])) * 100));
        } elseif (isset($field['surcharge_cents'])) {
            $surchargeCents = max(0, (int) $field['surcharge_cents']);
        } else {
            $surchargeCents = 0;
        }

        if ($type === 'font') {
            $surchargeCents = 0;
        } elseif ($type === 'date') {
            $placeholder = '';
            $maxLength = 20;
        } elseif ($type === 'select') {
            $maxLength = max(120, $maxLength);
        }

        // PAngV-Schutz: Ein Freitext- oder Datumsfeld mit Zusatz-Aufpreis (> 0 €) darf niemals ein Pflichtfeld sein,
        // da der Aufpreis sonst unumgänglich wäre.
        if ($surchargeCents > 0 && in_array($type, ['text', 'textarea', 'date'], true)) {
            $required = false;
        }

        return [
            'id'              => $id,
            'label'           => $label,
            'type'            => $type,
            'placeholder'     => $placeholder,
            'info_text'       => $infoText,
            'required'        => $required,
            'max_length'      => $maxLength,
            'options'         => $options,
            'options_raw'     => implode(', ', $options),
            'surcharge_cents' => $surchargeCents,
            'surcharge_price' => $surchargeCents > 0 ? number_format($surchargeCents / 100, 2, '.', '') : '0.00',
        ];
    }

    /**
     * Liefert die bereinigte Liste aller definierten Schnell-Personalisierungs-Eingabefelder.
     * Wenn $fallbackToDefaults = true ist und noch keine eigenen Felder hinterlegt wurden,
     * wird für den „Neuling“-Startmodus automatisch ein passendes Standard-Set (Wunschtext + Symbol + Schriftart) geliefert.
     */
    public function getCustomFieldsDefinition(bool $fallbackToDefaults = false): array
    {
        $settings = is_array($this->configurator_settings) ? $this->configurator_settings : [];
        $rawFields = isset($settings['custom_fields']) && is_array($settings['custom_fields'])
            ? $settings['custom_fields']
            : [];

        $normalized = [];
        foreach (array_values($rawFields) as $idx => $field) {
            if (!is_array($field)) {
                continue;
            }
            $norm = self::normalizeCustomFieldItem($field, $idx);
            if ($norm !== null) {
                $normalized[] = $norm;
            }
        }

        if (empty($normalized) && $fallbackToDefaults && $this->isPersonalizable()) {
            return $this->getDefaultBeginnerCustomFields();
        }

        return $normalized;
    }

    /**
     * Liefert die passenden Standard-Eingabefelder für den „Neuling“-Modus (Einfache Variante: Symbol + Wunschtext),
     * falls im Produkt noch keine individuellen custom_fields hinterlegt wurden.
     */
    public function getDefaultBeginnerCustomFields(): array
    {
        $slug = (string) ($this->slug ?? '');
        $allFonts = array_keys(app(\App\Services\ConfiguratorService::class)->getFonts());
        $allFontsRaw = implode(', ', $allFonts);

        if ($slug === 'seelen-pfote-hundemarke') {
            $raw = [
                [
                    'id' => 'cf_wunschsymbol',
                    'label' => '1. Wunsch-Symbol (oben mittig)',
                    'type' => 'select',
                    'options_raw' => 'Pfote 🐾, Herz ♥, Stern ★, Krone 👑, Schutzengel 👼, Kein Symbol',
                    'required' => true,
                    'max_length' => 40,
                    'surcharge_price' => '0.00',
                    'info_text' => 'Wir platzieren dein gewähltes Symbol harmonisch über dem Namen deines Vierbeiners.',
                ],
                [
                    'id' => 'cf_tiername',
                    'label' => '2. Name des Vierbeiners (Vorderseite)',
                    'type' => 'text',
                    'placeholder' => 'z. B. Balu',
                    'required' => true,
                    'max_length' => 18,
                    'surcharge_price' => '0.00',
                    'info_text' => 'Wird gut lesbar und abriebfest mittig auf der Vorderseite der Marke eingraviert.',
                ],
                [
                    'id' => 'cf_notfallnummer',
                    'label' => '3. Telefonnummer / Notfall-Info (Optional)',
                    'type' => 'text',
                    'placeholder' => 'z. B. +49 170 1234567',
                    'required' => false,
                    'max_length' => 26,
                    'surcharge_price' => '0.00',
                    'info_text' => 'Optional: Deine Telefonnummer gravieren wir zusätzlich unter den Namen oder auf Wunsch auf die Rückseite.',
                ],
                [
                    'id' => 'cf_schriftart',
                    'label' => '4. Schriftart wählen',
                    'type' => 'font',
                    'options' => $allFonts,
                    'options_raw' => $allFontsRaw,
                    'required' => false,
                    'max_length' => 60,
                    'surcharge_price' => '0.00',
                ],
            ];
        } else {
            $raw = [
                [
                    'id' => 'cf_wunschsymbol',
                    'label' => '1. Wunsch-Symbol (oben mittig)',
                    'type' => 'select',
                    'options_raw' => 'Herz ♥, Unendlichkeit ∞, Stern ★, Schutzengel 👼, Pfote 🐾, Lebensbaum 🌳, Kleeblatt 🍀, Kein Symbol',
                    'required' => true,
                    'max_length' => 40,
                    'surcharge_price' => '0.00',
                    'info_text' => 'Wähle ein feines Motiv-Symbol, das wir wie in der Vorschau mittig über deinem Wunschtext gravieren.',
                ],
                [
                    'id' => 'cf_wunschtext',
                    'label' => '2. Dein Wunschtext / Name',
                    'type' => 'textarea',
                    'placeholder' => "z. B. Für immer\nim Herzen",
                    'required' => true,
                    'max_length' => 80,
                    'surcharge_price' => '0.00',
                    'info_text' => 'Gib hier deinen gewünschten Namen, ein kurzes Datum oder deine Botschaft ein (Zeilenumbruch mit Enter möglich). Wir richten alles millimetergenau mittig für dich aus.',
                ],
                [
                    'id' => 'cf_schriftart',
                    'label' => '3. Schriftart wählen',
                    'type' => 'font',
                    'options' => $allFonts,
                    'options_raw' => $allFontsRaw,
                    'required' => false,
                    'max_length' => 60,
                    'surcharge_price' => '0.00',
                ],
            ];
        }

        $normalized = [];
        foreach ($raw as $idx => $item) {
            $norm = self::normalizeCustomFieldItem($item, $idx);
            if ($norm !== null) {
                $normalized[] = $norm;
            }
        }

        return $normalized;
    }

    public function isPhysical(): bool
    {
        return $this->type === 'physical';
    }

    public function isDigital(): bool
    {
        return $this->type === 'digital';
    }

    public function isService(): bool
    {
        return $this->type === 'service';
    }

    public function hasDigitalFile(): bool
    {
        return !empty($this->digital_download_path);
    }

    /**
     * Prüft, ob dieses physische Produkt in die flache Westpack Frankfurt ECO Schmuckschachtel (86 x 86 x 17 mm) passt.
     */
    public function supportsJewelryGiftBox(): bool
    {
        if (!$this->isPhysical()) {
            return false;
        }

        if (in_array((string) $this->slug, ['seelen-kristall', 'weizenglas-personalisiert'], true)) {
            return false;
        }

        return ($this->shipping_class === 'brief')
            || in_array((string) $this->slug, [
                'seelen-anhaenger',
                'seelen-talisman',
                'seelen-pfote-hundemarke',
                'goldenes-seelen-herz',
            ], true)
            || ((int) $this->weight > 0 && (int) $this->weight <= 100);
    }

    /**
     * Liefert die Standard-Verpackungs-/Geschenk-Upgrades im einheitlichen Schwarz-Gold-Look
     * ausschließlich für physische Produkte ($this->type === 'physical').
     * Digitale Produkte und Dienstleistungen erhalten niemals Verpackungs-Addons.
     */
    public function getDefaultPhysicalAddons(): array
    {
        if (!$this->isPhysical()) {
            return [];
        }

        $addons = [];

        // Für Schmuck-, Schlüssel- & Herzanhänger zusätzlich die Westpack Frankfurt ECO Schmuckschachtel (+4,90 €) anbieten
        if ($this->supportsJewelryGiftBox()) {
            $addons[] = [
                'id'          => 'gift_box_westpack',
                'name'        => 'Edle Schmuckbox (Schwarz-Gold)',
                'description' => 'Mattschwarze Schmuckschachtel mit Schaumstoffeinlage & Gold-Logo statt Versandtasche',
                'price'       => '4.90',
                'price_cents' => 490,
                'image_path'  => 'produkte/addons/edle-schmuckbox-schwarz-gold.jpg',
                'is_active'   => true,
            ];
        }

        // Jedes physische Produkt erhält den einheitlichen, edlen Schwarz-Gold Geschenkpapier-Service (+3,90 €)
        $addons[] = [
            'id'          => 'gift_wrap_black_gold',
            'name'        => 'Geschenkpapier (Schwarz-Gold-Look)',
            'description' => 'Edles Schwarz-Gold-Geschenkpapier: Wahlweise fertig verpackt oder als Bogen zum Selbstverpacken beigelegt',
            'price'       => '3.90',
            'price_cents' => 390,
            'image_path'  => 'produkte/addons/geschenkpapier-schwarz-gold.jpg',
            'is_active'   => true,
        ];

        return $addons;
    }

    /**
     * Prüft, ob es sich bei einem Addon um das Geschenkpapier-Upgrade handelt.
     */
    public static function isGiftWrapAddon(array|string $addonOrId): bool
    {
        if (is_string($addonOrId)) {
            $id = strtolower(trim($addonOrId));
            return $id === 'gift_wrap_black_gold' || str_contains($id, 'geschenkpapier') || str_contains($id, 'gift_wrap');
        }

        $id = strtolower(trim((string) ($addonOrId['id'] ?? '')));
        $name = mb_strtolower(trim((string) ($addonOrId['name'] ?? '')));

        return $id === 'gift_wrap_black_gold'
            || str_contains($id, 'gift_wrap')
            || str_contains($name, 'geschenkpapier')
            || str_contains($name, 'geschenkverpackung');
    }

    /**
     * Liefert die lesbare Bezeichnung für die gewählte Geschenkpapier-Ausführung.
     */
    public static function resolveGiftWrapModeLabel(?string $mode): string
    {
        return ($mode === 'loose') ? 'Papier nur mitliefern' : 'Fertig verpackt';
    }

    /**
     * Ermittelt den relativen, manipulationssicheren Bildpfad für ein Aufpreis-Extra / Verpackungs-Upgrade
     * gemäß Projektstruktur (storage/app/public/produkte/addons/...).
     */
    public static function resolveAddonImagePath(array $addon): ?string
    {
        $rawPath = isset($addon['image_path']) && is_string($addon['image_path']) ? trim($addon['image_path']) : '';

        // Automatische Migration alter Platzhalter-Pfade auf die neuen fotorealistischen Studio-Assets in produkte/addons/
        if ($rawPath === 'shop/frontend/process/edle_verpackung.webp') {
            return 'produkte/addons/edle-schmuckbox-schwarz-gold.jpg';
        }
        if ($rawPath === 'shop/product/geschenkpapier.webp') {
            return 'produkte/addons/geschenkpapier-schwarz-gold.jpg';
        }

        if (
            $rawPath !== ''
            && !str_contains($rawPath, '..')
            && !str_contains($rawPath, "\0")
            && !str_contains($rawPath, '\\')
            && !str_contains($rawPath, ':')
            && !preg_match('#^(https?:)?//#i', $rawPath)
        ) {
            $cleanRel = ltrim(preg_replace('#^/?storage/#', '', $rawPath), '/');
            if (str_starts_with($cleanRel, 'products/addons/')) {
                $cleanRel = 'produkte/addons/' . substr($cleanRel, strlen('products/addons/'));
            }
            if (str_starts_with($cleanRel, 'produkte/') || str_starts_with($cleanRel, 'shop/')) {
                return $cleanRel;
            }
        }

        $id = strtolower(trim((string) ($addon['id'] ?? '')));
        $name = mb_strtolower(trim((string) ($addon['name'] ?? '')));

        if ($id === 'gift_box_westpack' || str_contains($name, 'schmuckbox') || str_contains($name, 'geschenkbox') || str_contains($name, 'edle box') || str_contains($name, 'schachtel')) {
            return 'produkte/addons/edle-schmuckbox-schwarz-gold.jpg';
        }

        if ($id === 'gift_wrap_black_gold' || str_contains($name, 'geschenkpapier') || str_contains($name, 'geschenkverpackung')) {
            return 'produkte/addons/geschenkpapier-schwarz-gold.jpg';
        }

        return null;
    }

    /**
     * Wandelt ein Addon-Array oder einen relativen Addon-Bildpfad in eine lokale Asset-/Storage-URL um (SSRF- & LFI-sicher).
     */
    public static function resolveAddonImageUrl(array|string|null $addonOrPath): ?string
    {
        $relPath = is_array($addonOrPath)
            ? self::resolveAddonImagePath($addonOrPath)
            : (is_string($addonOrPath) ? self::resolveAddonImagePath(['image_path' => $addonOrPath]) : null);

        if (!$relPath) {
            return null;
        }

        if (str_starts_with($relPath, 'shop/')) {
            return asset($relPath);
        }

        $cleanStorageRel = ltrim(preg_replace('#^/?storage/#', '', $relPath), '/');
        return asset('storage/' . $cleanStorageRel);
    }

    /**
     * Löst die optionale Kurzbeschreibung eines Aufpreis-Extras auf (inkl. Fallback auf die Produktdefinition für bestehende Warenkorb-/Bestellpositionen).
     */
    public static function resolveAddonDescription(array $addon, ?self $product = null): string
    {
        $directDesc = trim((string) ($addon['description'] ?? ''));
        if ($directDesc !== '') {
            return $directDesc;
        }

        $addonId = trim((string) ($addon['id'] ?? ''));
        $addonNameLower = mb_strtolower(trim((string) ($addon['name'] ?? '')));

        if ($product) {
            foreach ($product->getEffectiveAddons(false) as $eff) {
                $effId = trim((string) ($eff['id'] ?? ''));
                $effNameLower = mb_strtolower(trim((string) ($eff['name'] ?? '')));
                if (($addonId !== '' && $effId === $addonId) || ($addonNameLower !== '' && $effNameLower === $addonNameLower)) {
                    $effDesc = trim((string) ($eff['description'] ?? ''));
                    if ($effDesc !== '') {
                        return $effDesc;
                    }
                }
            }
        }

        if ($addonId === 'gift_box_westpack' || str_contains($addonNameLower, 'schmuckbox') || str_contains($addonNameLower, 'geschenkbox')) {
            return 'Mattschwarze Schmuckschachtel mit Schaumstoffeinlage & Gold-Logo statt Versandtasche';
        }

        if ($addonId === 'gift_wrap_black_gold' || str_contains($addonNameLower, 'geschenkpapier')) {
            return 'Edles Schwarz-Gold-Geschenkpapier: Wahlweise fertig verpackt oder als Bogen zum Selbstverpacken beigelegt';
        }

        return '';
    }

    /**
     * Liefert die bereinigten, deduplizierten Aufpreis-Extras / Geschenkverpackungen für dieses Produkt.
     * WICHTIG: Nur physische Produkte ($this->type === 'physical') vertreten diese Option!
     */
    public function getEffectiveAddons(bool $onlyActive = true): array
    {
        if (!$this->isPhysical()) {
            return [];
        }

        $settings = is_array($this->configurator_settings) ? $this->configurator_settings : [];
        $rawAddons = (isset($settings['addons']) && is_array($settings['addons']) && count($settings['addons']) > 0)
            ? $settings['addons']
            : $this->getDefaultPhysicalAddons();

        $supportsBox = $this->supportsJewelryGiftBox();

        // 1. Kanonisierung & Deduplizierung bestehender Einträge (verhindert doppelte Geschenkbox- oder Geschenkpapier-Kacheln)
        $normalizedById = [];
        $hasAnyBoxAddon = false;

        foreach ($rawAddons as $addon) {
            if (!is_array($addon) || empty($addon['name'])) {
                continue;
            }

            $rawId = trim((string) ($addon['id'] ?? ''));
            $rawName = trim((string) $addon['name']);
            $lowerName = mb_strtolower($rawName);
            $rawDesc = trim((string) ($addon['description'] ?? ''));
            $isActive = (bool) ($addon['is_active'] ?? true);

            $priceCents = isset($addon['price_cents'])
                ? max(0, (int) $addon['price_cents'])
                : max(0, (int) round(((float) str_replace(',', '.', (string) ($addon['price'] ?? 0))) * 100));

            $looksLikeBox = str_contains($lowerName, 'geschenkbox')
                || str_contains($lowerName, 'schmuckbox')
                || str_contains($lowerName, 'schmuckschachtel')
                || str_contains($lowerName, 'juwelierschachtel')
                || str_contains($lowerName, 'edle box');

            // Standard-Schmuckbox ('gift_box_westpack') sowie Legacy-UUID-Geschenkboxen auf 'gift_box_westpack' vereinheitlichen
            $isPresetBoxAddon = ($rawId === 'gift_box_westpack')
                || ($supportsBox && (
                    str_contains($lowerName, 'schmuckbox')
                    || str_contains($lowerName, 'schmuckschachtel')
                    || str_contains($lowerName, 'juwelierschachtel')
                    || (str_contains($lowerName, 'geschenkbox') && ($rawId === '' || \Illuminate\Support\Str::isUuid($rawId)))
                ));

            $isPresetWrapAddon = ($rawId === 'gift_wrap_black_gold')
                || str_contains($lowerName, 'geschenkpapier')
                || ($rawName === 'Geschenkpapier (Schwarz-Gold-Look)');

            if ($isPresetBoxAddon) {
                if (!$supportsBox || $hasAnyBoxAddon) {
                    continue;
                }
                $canonicalId = 'gift_box_westpack';
                $rawName = 'Edle Schmuckbox (Schwarz-Gold)';
                $rawDesc = ($rawDesc !== '' && !str_contains($rawDesc, 'Luftpolstertasche') && !str_contains($rawDesc, 'Samteinlage'))
                    ? $rawDesc
                    : 'Mattschwarze Schmuckschachtel mit Schaumstoffeinlage & Gold-Logo statt Versandtasche';
                if ($priceCents === 290 || $priceCents === 0) {
                    $priceCents = 490;
                }
                $hasAnyBoxAddon = true;
            } elseif ($isPresetWrapAddon) {
                $canonicalId = 'gift_wrap_black_gold';
                $rawName = 'Geschenkpapier (Schwarz-Gold-Look)';
                $rawDesc = ($rawDesc !== '' && !str_contains($rawDesc, ' – '))
                    ? $rawDesc
                    : 'Edles Schwarz-Gold-Geschenkpapier: Wahlweise fertig verpackt oder als Bogen zum Selbstverpacken beigelegt';
                if ($priceCents === 0 || $priceCents === 290) {
                    $priceCents = 390;
                }
            } else {
                if (in_array((string) $this->slug, ['seelen-kristall', 'weizenglas-personalisiert'], true) && $looksLikeBox) {
                    continue;
                }
                if ($looksLikeBox) {
                    if ($hasAnyBoxAddon) {
                        continue;
                    }
                    $hasAnyBoxAddon = true;
                }
                $canonicalId = $rawId !== '' ? $rawId : \Illuminate\Support\Str::uuid()->toString();
            }

            if (isset($normalizedById[$canonicalId])) {
                continue;
            }

            $imagePath = self::resolveAddonImagePath(array_merge($addon, [
                'id'   => $canonicalId,
                'name' => $rawName,
            ]));

            $normalizedById[$canonicalId] = [
                'id'          => $canonicalId,
                'name'        => $rawName,
                'description' => $rawDesc,
                'price'       => number_format($priceCents / 100, 2, '.', ''),
                'price_cents' => $priceCents,
                'image_path'  => $imagePath,
                'image_url'   => self::resolveAddonImageUrl($imagePath),
                'is_active'   => $isActive,
            ];
        }

        // 2. Sicherstellen, dass alle Standard-Optionen (Schmuckbox 4,90 € bei Schmuck & Geschenkpapier 2,90 € bei allen physischen Produkten) vorhanden sind
        foreach ($this->getDefaultPhysicalAddons() as $defaultAddon) {
            $defId = $defaultAddon['id'];
            if ($defId === 'gift_box_westpack' && $hasAnyBoxAddon) {
                continue;
            }
            if (!isset($normalizedById[$defId])) {
                $defImgPath = self::resolveAddonImagePath($defaultAddon);
                $normalizedById[$defId] = array_merge($defaultAddon, [
                    'image_path' => $defImgPath,
                    'image_url'  => self::resolveAddonImageUrl($defImgPath),
                ]);
            }
        }

        // 3. Sinnvolle Reihenfolge: Zuerst Schmuckbox (falls vorhanden), dann Geschenkpapier, danach sonstige Extras
        $ordered = [];
        if (isset($normalizedById['gift_box_westpack'])) {
            $ordered[] = $normalizedById['gift_box_westpack'];
            unset($normalizedById['gift_box_westpack']);
        }
        if (isset($normalizedById['gift_wrap_black_gold'])) {
            $ordered[] = $normalizedById['gift_wrap_black_gold'];
            unset($normalizedById['gift_wrap_black_gold']);
        }
        foreach ($normalizedById as $extra) {
            $ordered[] = $extra;
        }

        if ($onlyActive) {
            $ordered = array_values(array_filter($ordered, fn(array $a) => !empty($a['is_active'])));
        }

        return $ordered;
    }

    /**
     * Berechnet den echten Reife- & Erstell-Status (0–100 %) des Produkts über alle Bereiche
     * (Basisdaten, Kalkulation, Lieferant, Medien, Kategorien, Maße, LUCID-Verpackung, Lager, 3D/2D & Gravur-Vorlagen).
     */
    public function getReadinessReport(): array
    {
        $isPhysical = $this->isPhysical();
        $isPersonalizable = $this->isPersonalizable();
        $isVoucher = $this->isGiftVoucher();

        $checks = [];

        // 1. Basisdaten & VK (Schritt 1)
        $hasValidName = !empty(trim((string) $this->name)) && !str_starts_with(trim((string) $this->name), 'Neues Produkt ');
        $hasValidSlug = !empty($this->slug) && !str_starts_with((string) $this->slug, 'draft-');
        $hasValidPrice = ((int) $this->price > 0) || $isVoucher;
        $hasValidSku = !empty(trim((string) $this->sku));
        $hasDescription = !empty(trim(strip_tags((string) $this->description)));
        $basicsPassed = $hasValidName && $hasValidSlug && $hasValidPrice && $hasValidSku && $hasDescription;

        $basicsMissingParts = [];
        if (!$hasValidName || !$hasValidSlug) $basicsMissingParts[] = 'Name/Slug';
        if (!$hasValidPrice) $basicsMissingParts[] = 'VK-Preis';
        if (!$hasValidSku) $basicsMissingParts[] = 'SKU';
        if (!$hasDescription) $basicsMissingParts[] = 'Beschreibung';

        $checks[] = [
            'key'           => 'basics',
            'step'          => 1,
            'sub_step'      => 2,
            'section'       => 'collection',
            'label'         => 'Basisdaten & VK',
            'short_missing' => 'Basisdaten / VK',
            'passed'        => $basicsPassed,
            'detail'        => $basicsPassed
                ? 'Name, SKU, Beschreibung & VK vollständig'
                : 'Fehlt: ' . implode(', ', $basicsMissingParts),
        ];

        if ($isPhysical) {
            // 2. Kalkulation / EK (Schritt 1 -> Unter-Step 2)
            $ekPassed = ((int) $this->purchase_price > 0);
            $checks[] = [
                'key'           => 'economics',
                'step'          => 1,
                'sub_step'      => 2,
                'section'       => 'collection',
                'label'         => 'EK & Kalkulation',
                'short_missing' => 'EK-Preis fehlt',
                'passed'        => $ekPassed,
                'detail'        => $ekPassed
                    ? 'Netto-EK (' . number_format($this->purchase_price / 100, 2, ',', '.') . ' €) hinterlegt'
                    : 'Netto-Einkaufspreis für Margen-Kalkulation fehlt',
            ];

            // 3. Lieferant & Nachbestell-Link (Schritt 1 -> Unter-Step 5)
            $hasSupplier = !empty($this->product_supplier_id);
            $hasReorderUrl = !empty(trim((string) $this->reorder_url));
            $supplierPassed = $hasSupplier && $hasReorderUrl;
            $supplierName = $this->relationLoaded('supplier') ? ($this->supplier?->name ?? 'Lieferant') : 'Lieferant';

            $checks[] = [
                'key'           => 'supplier',
                'step'          => 1,
                'sub_step'      => 5,
                'section'       => 'suppliers',
                'label'         => 'Lieferant & Link',
                'short_missing' => !$hasSupplier ? 'Kein Lieferant' : 'Kein Bestell-Link',
                'passed'        => $supplierPassed,
                'detail'        => $supplierPassed
                    ? $supplierName . ' + B2B-Link verknüpft'
                    : (!$hasSupplier && !$hasReorderUrl ? 'Lieferant & Nachbestell-Link fehlen' : (!$hasSupplier ? 'Lieferant nicht zugewiesen' : 'B2B-Nachbestell-Link fehlt')),
            ];
        }

        // 4. Medien / Hauptbild & Saison-Bildersatz (Schritt 2)
        $hasGalleryImage = false;
        foreach ($this->media_gallery ?? [] as $media) {
            if (is_array($media) && ($media['type'] ?? '') === 'image' && !empty($media['path'])) {
                $hasGalleryImage = true;
                break;
            }
        }
        $activeTemplates = $this->relationLoaded('templates')
            ? $this->templates->where('is_active', true)
            : $this->templates()->where('is_active', true)->get();

        $customSeasonalMap = is_array($this->configurator_settings['seasonal_images'] ?? null)
            ? $this->configurator_settings['seasonal_images']
            : [];
        $hasCustomSeasonalImage = collect($customSeasonalMap)->contains(function ($entry) {
            $p = is_array($entry) ? ($entry['path'] ?? null) : (is_string($entry) ? $entry : null);
            return !empty($p);
        });

        $hasSeasonalImage = $hasCustomSeasonalImage || $activeTemplates->filter(fn ($t) => !empty($t->preview_image))->isNotEmpty();
        $hasNonOverlayPreview = !empty($this->preview_image_path)
            && !str_ends_with((string) $this->preview_image_path, '/overlay.png')
            && !str_ends_with((string) $this->preview_image_path, '/envelope.png');
        $hasAnyMainImage = $hasGalleryImage || $hasSeasonalImage || $hasNonOverlayPreview;
        $seasonalSetStatus = $this->getSeasonalImageSetStatus();
        $mediaPassed = $hasAnyMainImage && $seasonalSetStatus['is_complete'];

        $checks[] = [
            'key'           => 'media',
            'step'          => 2,
            'sub_step'      => 1,
            'section'       => 'collection',
            'label'         => 'Medien & Galerie',
            'short_missing' => !$hasAnyMainImage
                ? 'Hauptbild fehlt'
                : 'Saison-Bilder (' . $seasonalSetStatus['filled_count'] . '/' . $seasonalSetStatus['total_count'] . ')',
            'passed'        => $mediaPassed,
            'detail'        => $mediaPassed
                ? ($seasonalSetStatus['auto_enabled'] ? 'Hauptbild & alle 12 Saisonbilder gepflegt' : 'Produktbilder vorhanden')
                : (!$hasAnyMainImage ? 'Mindestens 1 Galeriebild oder Saison-Vorlagenbild erforderlich' : $seasonalSetStatus['tooltip']),
        ];

        // 5. Kategorien (Schritt 3)
        $categoryCount = $this->relationLoaded('categories')
            ? $this->categories->count()
            : $this->categories()->count();
        $categoriesPassed = $categoryCount > 0;

        $checks[] = [
            'key'           => 'categories',
            'step'          => 3,
            'sub_step'      => 1,
            'section'       => 'collection',
            'label'         => 'Kategorien',
            'short_missing' => 'Keine Kategorie',
            'passed'        => $categoriesPassed,
            'detail'        => $categoriesPassed
                ? $categoryCount . ' Shop-' . ($categoryCount === 1 ? 'Kategorie' : 'Kategorien') . ' zugewiesen'
                : 'Noch keiner Shop-Kategorie zugeordnet',
        ];

        if ($isPhysical) {
            // 6. Verpackung & LUCID (Schritt 3 -> Unter-Step 4 / Tab Verpackung)
            $packagings = $this->relationLoaded('packagings')
                ? $this->packagings
                : $this->packagings()->get();
            $hasPackagingRows = $packagings->isNotEmpty();
            $hasCatalogLink = $packagings->whereNotNull('packaging_item_id')->isNotEmpty();
            $packagingPassed = $hasPackagingRows && ((int) $this->packaging_weight > 0 || $packagings->sum('weight_grams') > 0);

            $checks[] = [
                'key'           => 'packaging',
                'step'          => 3,
                'sub_step'      => 4,
                'section'       => 'packaging',
                'label'         => 'Verpackung & LUCID',
                'short_missing' => 'Keine Verpackung',
                'passed'        => $packagingPassed,
                'detail'        => $packagingPassed
                    ? ($hasCatalogLink ? 'Katalog-Verpackung & LUCID-Gewichte verknüpft' : 'LUCID-Gewichte hinterlegt')
                    : 'Verpackungsmaterial & LUCID-Grammatur fehlen',
            ];

            // 7. Physische Maße & Gewicht (Schritt 3 -> Unter-Step 4)
            $dimensionsPassed = ((int) $this->weight > 0) && ((float) $this->width > 0) && ((float) $this->height > 0);
            $checks[] = [
                'key'           => 'dimensions',
                'step'          => 3,
                'sub_step'      => 4,
                'section'       => 'collection',
                'label'         => 'Maße & Gewicht',
                'short_missing' => 'Maße / Gewicht',
                'passed'        => $dimensionsPassed,
                'detail'        => $dimensionsPassed
                    ? "{$this->width}×{$this->height}×{$this->length} mm · {$this->weight} g"
                    : 'Produktmaße (B×H) oder Eigengewicht fehlen',
            ];
        }

        if ($isPhysical && $isPersonalizable) {
            if ($this->usesCustomFieldsPersonalization()) {
                // 8. Schnell-Personalisierung: Dynamische Eingabefelder (Schritt 1 -> Unter-Step 1)
                $customFields = $this->getCustomFieldsDefinition();
                $customFieldsCount = count($customFields);
                $customFieldsPassed = $customFieldsCount > 0;

                $checks[] = [
                    'key'           => 'custom_fields',
                    'step'          => 1,
                    'sub_step'      => 1,
                    'section'       => 'collection',
                    'label'         => 'Schnell-Personalisierung (Felder)',
                    'short_missing' => 'Keine Eingabefelder',
                    'passed'        => $customFieldsPassed,
                    'detail'        => $customFieldsPassed
                        ? $customFieldsCount . ' dynamische ' . ($customFieldsCount === 1 ? 'Eingabefeld' : 'Eingabefelder') . ' aktiv (kein 3D/2D nötig)'
                        : 'Mindestens 1 Eingabefeld für die Schnell-Personalisierung in Schritt 1.1 anlegen',
                ];
            } else {
                // 8. 3D/2D Konfigurator Setup (Schritt 4 -> Unter-Step 1)
                $has3dOr2d = (!$this->three_d_active || !empty($this->three_d_model_path)) && !empty($this->preview_image_path);
                $checks[] = [
                    'key'           => 'configurator',
                    'step'          => 4,
                    'sub_step'      => 1,
                    'section'       => 'collection',
                    'label'         => '3D / 2D Konfigurator',
                    'short_missing' => '3D/2D Datei fehlt',
                    'passed'        => $has3dOr2d,
                    'detail'        => $has3dOr2d
                        ? ($this->three_d_active ? '3D-Modell (.glb) & Overlay aktiv' : '2D-Konfigurator aktiv')
                        : '3D-Modell (.glb) oder Konfigurator-Vorschaubild fehlt',
                ];
            }

            // 9. xTool Laser-Parameter & Bearbeitungs-Anleitung (Schritt 4 -> Unter-Step 2)
            $laserSummary = $this->getLaserInstructionSummary();
            $checks[] = [
                'key'           => 'xtool_laser',
                'step'          => 4,
                'sub_step'      => 2,
                'section'       => 'collection',
                'label'         => 'xTool Laser-Anleitung & Werte',
                'short_missing' => 'Laser-Werte fehlen',
                'passed'        => $laserSummary['is_complete'],
                'detail'        => $laserSummary['is_complete']
                    ? ($laserSummary['equipment_title'] . ' · ' . $laserSummary['focus_formatted'] . ' · ' . $laserSummary['power_percent'] . ' % · ' . $laserSummary['dpi'] . ' DPI')
                    : 'Fehlt in Schritt 4.2: ' . implode(', ', array_slice($laserSummary['missing_labels'], 0, 4)),
            ];

            if (!$this->usesCustomFieldsPersonalization()) {
                // 10. Gravur-Vorlagen (Schritt 4 -> Unter-Step 4 / Tab Vorlagen)
                $activeTplCount = $activeTemplates->count();
                $templatesPassed = $activeTplCount > 0;
                $checks[] = [
                    'key'           => 'templates',
                    'step'          => 4,
                    'sub_step'      => 4,
                    'section'       => 'templates',
                    'label'         => 'Gravur-Vorlagen',
                    'short_missing' => 'Keine Vorlage',
                    'passed'        => $templatesPassed,
                    'detail'        => $templatesPassed
                        ? $activeTplCount . ' aktive ' . ($activeTplCount === 1 ? 'Gravur-Vorlage' : 'Gravur-Vorlagen')
                        : 'Noch keine Design-/Saison-Vorlage für dieses Produkt angelegt',
                ];
            }
        }

        $totalCount = count($checks);
        $passedCount = count(array_filter($checks, fn (array $c) => $c['passed']));
        $score = $totalCount > 0 ? (int) round(($passedCount / $totalCount) * 100) : 100;
        $missing = array_values(array_filter($checks, fn (array $c) => !$c['passed']));

        $byStep = [1 => [], 2 => [], 3 => [], 4 => []];
        foreach ($checks as $check) {
            $byStep[$check['step']][] = $check;
        }

        return [
            'score'        => $score,
            'passed_count' => $passedCount,
            'total_count'  => $totalCount,
            'is_ready'     => $passedCount === $totalCount,
            'checks'       => $checks,
            'missing'      => $missing,
            'by_step'      => $byStep,
        ];
    }

    /**
     * Liefert die 3 zentral im System hinterlegten xTool F2 Ultra UV PDF-Anleitungen
     * (gespeichert unter storage/app/public/produkte/laser-anleitungen/ sowie im KI-Workspace unter Berufsleben/Laser-Anleitungen/).
     *
     * @return array<int, array{number: int, equipment_key: string, title: string, badge: string, filename: string, asset_path: string, url: string}>
     */
    public static function getLaserPdfGuides(): array
    {
        return [
            [
                'number'        => 1,
                'equipment_key' => 'rotary_ra3',
                'title'         => '360° Gravur (xTool RA3)',
                'badge'         => 'Anleitung 1 · RA3 Rotationsaufsatz',
                'filename'      => 'Anleitung_1_360_Grad_Gravur_xTool_RA3.pdf',
                'asset_path'    => 'storage/produkte/laser-anleitungen/Anleitung_1_360_Grad_Gravur_xTool_RA3.pdf',
                'url'           => asset('storage/produkte/laser-anleitungen/Anleitung_1_360_Grad_Gravur_xTool_RA3.pdf'),
            ],
            [
                'number'        => 2,
                'equipment_key' => 'conveyor_batch',
                'title'         => 'Laufband Serienproduktion',
                'badge'         => 'Anleitung 2 · Förderband',
                'filename'      => 'Anleitung_2_Laufband_Massenproduktion_xTool_F2_Ultra_UV.pdf',
                'asset_path'    => 'storage/produkte/laser-anleitungen/Anleitung_2_Laufband_Massenproduktion_xTool_F2_Ultra_UV.pdf',
                'url'           => asset('storage/produkte/laser-anleitungen/Anleitung_2_Laufband_Massenproduktion_xTool_F2_Ultra_UV.pdf'),
            ],
            [
                'number'        => 3,
                'equipment_key' => 'inner_3d',
                'title'         => '3D-Innengravur Effekt',
                'badge'         => 'Anleitung 3 · Gold-Linse + Riser',
                'filename'      => 'Anleitung_3_3D_Innengravur_xTool_F2_Ultra_UV.pdf',
                'asset_path'    => 'storage/produkte/laser-anleitungen/Anleitung_3_3D_Innengravur_xTool_F2_Ultra_UV.pdf',
                'url'           => asset('storage/produkte/laser-anleitungen/Anleitung_3_3D_Innengravur_xTool_F2_Ultra_UV.pdf'),
            ],
        ];
    }

    /**
     * Katalog der verfügbaren xTool F2 Ultra UV Equipment-Aufbauten inkl. verknüpfter PDF-Kurzanleitungen.
     *
     * @return array<string, array{key: string, title: string, badge: string, default_process_mode: string, pdf_file: ?string, pdf_filename: ?string, pdf_label: ?string, tooltip: string}>
     */
    public static function getLaserEquipmentCatalog(): array
    {
        return [
            'flatbed_standard' => [
                'key'                  => 'flatbed_standard',
                'title'                => 'Flachbett Standard',
                'badge'                => 'Standard-Linse · Lamellenrost',
                'default_process_mode' => 'Prozess auf flacher Oberfläche',
                'pdf_file'             => null,
                'pdf_filename'         => null,
                'pdf_label'            => null,
                'tooltip'              => 'Schwarze Standard-Linse im Laserkopf und schwarzes Lamellenrost auf der Bodenplatte.',
            ],
            'rotary_ra3' => [
                'key'                  => 'rotary_ra3',
                'title'                => '360° Rotation (xTool RA3)',
                'badge'                => 'Anleitung 1 · RA3 Aufsatz',
                'default_process_mode' => 'Walze - Rotationsaufsatz',
                'pdf_file'             => 'storage/produkte/laser-anleitungen/Anleitung_1_360_Grad_Gravur_xTool_RA3.pdf',
                'pdf_filename'         => 'Anleitung_1_360_Grad_Gravur_xTool_RA3.pdf',
                'pdf_label'            => 'Anleitung 1: 360° RA3',
                'tooltip'              => 'Für Gläser, Becher, Tassen, Flaschen und Ringe mit dem xTool RA3 Rotationsaufsatz.',
            ],
            'conveyor_batch' => [
                'key'                  => 'conveyor_batch',
                'title'                => 'Laufband Serie',
                'badge'                => 'Anleitung 2 · Förderband',
                'default_process_mode' => 'Gleiches Design - Stapelverarbeitung',
                'pdf_file'             => 'storage/produkte/laser-anleitungen/Anleitung_2_Laufband_Massenproduktion_xTool_F2_Ultra_UV.pdf',
                'pdf_filename'         => 'Anleitung_2_Laufband_Massenproduktion_xTool_F2_Ultra_UV.pdf',
                'pdf_label'            => 'Anleitung 2: Laufband',
                'tooltip'              => 'Für Seriengravuren (Kugelschreiber, Anhänger, Marken) auf dem xTool Förderband.',
            ],
            'inner_3d' => [
                'key'                  => 'inner_3d',
                'title'                => '3D-Innengravur',
                'badge'                => 'Anleitung 3 · Gold-Linse + Riser',
                'default_process_mode' => 'Innengravur - 3D-Miniatur',
                'pdf_file'             => 'storage/produkte/laser-anleitungen/Anleitung_3_3D_Innengravur_xTool_F2_Ultra_UV.pdf',
                'pdf_filename'         => 'Anleitung_3_3D_Innengravur_xTool_F2_Ultra_UV.pdf',
                'pdf_label'            => 'Anleitung 3: 3D-Innengravur',
                'tooltip'              => 'Für tiefe K9-Kristallblöcke mit goldener Innengravur-Linse und schwarzem Riser-Tisch.',
            ],
        ];
    }

    /**
     * Wertet ein configurator_settings-Array hinsichtlich Geometrie, Equipment-Voraussetzung und xTool Studio Parametern aus.
     */
    public static function evaluateLaserInstructionSettings(array $settings, bool $requireStudioGeometry = true): array
    {
        $catalog = self::getLaserEquipmentCatalog();
        $overlayType = (string) ($settings['overlay_type'] ?? 'plane');

        $hasGeometry = $overlayType === 'cylinder'
            ? ((float) ($settings['xtool_d_top'] ?? 0) > 0 && (float) ($settings['xtool_height'] ?? 0) > 0)
            : ((float) ($settings['xtool_plane_width'] ?? 0) > 0 && (float) ($settings['xtool_plane_height'] ?? 0) > 0);

        $equipmentKey = trim((string) ($settings['xtool_equipment_key'] ?? ''));
        $equipmentMeta = $catalog[$equipmentKey] ?? null;
        $setupNote = trim((string) ($settings['xtool_setup_note'] ?? ''));
        $processMode = trim((string) ($settings['xtool_process_mode'] ?? ''));
        $material = trim((string) ($settings['xtool_material'] ?? ''));
        $focusRaw = str_replace(',', '.', trim((string) ($settings['xtool_focus_mm'] ?? '')));
        $focusMm = is_numeric($focusRaw) ? (float) $focusRaw : 0.0;

        $elementType = trim((string) ($settings['xtool_element_type'] ?? 'Bild')) ?: 'Bild';
        $engraveTab = trim((string) ($settings['xtool_engrave_tab'] ?? 'Gravieren')) ?: 'Gravieren';
        $presetMode = trim((string) ($settings['xtool_preset_mode'] ?? 'Benutzerdefiniert')) ?: 'Benutzerdefiniert';

        $dotDurationUs = (int) ($settings['xtool_dot_duration_us'] ?? 0);
        $powerPercent = (int) ($settings['xtool_power_percent'] ?? 0);
        $dpi = (int) ($settings['xtool_dpi'] ?? 0);
        $passes = (int) ($settings['xtool_passes'] ?? 0);
        $frequencyKhz = (int) ($settings['xtool_frequency_khz'] ?? 0);
        $bitmapMode = trim((string) ($settings['xtool_bitmap_mode'] ?? ''));
        $engraveMode = trim((string) ($settings['xtool_engrave_mode'] ?? ''));
        $vectorNote = trim((string) ($settings['xtool_vector_note'] ?? ''));

        $missingLabels = [];
        if ($requireStudioGeometry && !$hasGeometry) {
            $missingLabels[] = 'SVG-Geometrie (mm)';
        }
        if ($equipmentMeta === null) {
            $missingLabels[] = 'Voraussetzung / Equipment';
        }
        if ($processMode === '') {
            $missingLabels[] = 'Modus';
        }
        if ($material === '') {
            $missingLabels[] = 'Material';
        }
        if ($focusMm <= 0) {
            $missingLabels[] = 'Fokus (mm)';
        }
        if ($dotDurationUs <= 0) {
            $missingLabels[] = 'Punktdauer (µs)';
        }
        if ($powerPercent <= 0) {
            $missingLabels[] = 'Leistung (%)';
        }
        if ($dpi <= 0) {
            $missingLabels[] = 'DPI';
        }
        if ($passes <= 0) {
            $missingLabels[] = 'Bearbeitungsanzahl';
        }
        if ($frequencyKhz <= 0) {
            $missingLabels[] = 'Frequenz (kHz)';
        }
        if ($bitmapMode === '') {
            $missingLabels[] = 'Bitmap-Modus';
        }
        if ($engraveMode === '') {
            $missingLabels[] = 'Gravurmodus';
        }

        $isComplete = count($missingLabels) === 0;
        $focusFormatted = $focusMm > 0 ? number_format($focusMm, 1, ',', '.') . ' mm' : '';
        $pdfUrl = !empty($equipmentMeta['pdf_file']) ? asset($equipmentMeta['pdf_file']) : null;

        $copyLines = [];
        if ($equipmentMeta !== null) {
            $copyLines[] = 'Equipment: ' . $equipmentMeta['title'] . ' (' . $equipmentMeta['badge'] . ')';
        }
        if ($setupNote !== '') {
            $copyLines[] = 'Hinweis: ' . $setupNote;
        }
        if ($processMode !== '') {
            $copyLines[] = 'Modus: ' . $processMode;
        }
        if ($material !== '') {
            $copyLines[] = 'Material: ' . $material;
        }
        if ($focusMm > 0) {
            $copyLines[] = 'Fokus: ' . $focusFormatted;
        }
        if ($dotDurationUs > 0 || $powerPercent > 0 || $dpi > 0) {
            $copyLines[] = "{$elementType} ({$engraveTab} / {$presetMode}): {$dotDurationUs} µs · {$powerPercent} % · {$dpi} DPI · {$passes}x · {$frequencyKhz} kHz · {$bitmapMode} · {$engraveMode}";
        }
        if ($vectorNote !== '') {
            $copyLines[] = 'Vektor: ' . $vectorNote;
        }

        return [
            'is_complete'       => $isComplete,
            'has_geometry'      => $hasGeometry,
            'missing_labels'    => $missingLabels,
            'equipment_key'     => $equipmentKey,
            'equipment_title'   => $equipmentMeta['title'] ?? '',
            'equipment_badge'   => $equipmentMeta['badge'] ?? '',
            'pdf_file'          => $equipmentMeta['pdf_file'] ?? null,
            'pdf_filename'      => $equipmentMeta['pdf_filename'] ?? null,
            'pdf_url'           => $pdfUrl,
            'pdf_label'         => $equipmentMeta['pdf_label'] ?? null,
            'setup_note'        => $setupNote,
            'process_mode'      => $processMode,
            'material'          => $material,
            'focus_mm'          => $focusMm,
            'focus_formatted'   => $focusFormatted,
            'element_type'      => $elementType,
            'engrave_tab'       => $engraveTab,
            'preset_mode'       => $presetMode,
            'dot_duration_us'   => $dotDurationUs,
            'power_percent'     => $powerPercent,
            'dpi'               => $dpi,
            'passes'            => $passes,
            'frequency_khz'     => $frequencyKhz,
            'bitmap_mode'       => $bitmapMode,
            'engrave_mode'      => $engraveMode,
            'vector_note'       => $vectorNote,
            'copy_summary'      => implode("\n", $copyLines),
        ];
    }

    /**
     * Liefert die aufbereitete xTool Laser-Anleitung inkl. Vollständigkeitsprüfung für dieses Produkt.
     */
    public function getLaserInstructionSummary(): array
    {
        $settings = is_array($this->configurator_settings) ? $this->configurator_settings : [];
        return self::evaluateLaserInstructionSettings($settings, $this->usesStudioConfigurator());
    }

    /**
     * Zentrale Prüfung, ob es sich um einen Geschenkgutschein handelt (entweder über den Slug oder die Artikelkonfiguration).
     */
    public function isGiftVoucher(?array $configuration = null): bool
    {
        return ($this->slug === 'geschenkgutschein') || (!empty($configuration['is_gift_voucher']));
    }

    /**
     * Zentrale Prüfung, ob das Produkt aktuell im automatischen Feiertags-/Aktions-Autopilot-Sale reduziert ist.
     * Solche Artikel sind laut Shop-Regelwerk nicht mit zusätzlichen Rabattcodes kombinierbar.
     */
    public function isInAutopilotSale(): bool
    {
        return !empty($this->configurator_settings['autopilot_base_price'])
            && (int) $this->compare_at_price > (int) $this->price;
    }

    /**
     * Zentrale Prüfung, ob auf dieses Produkt ein prozentualer oder fester Rabattcode (inkl. Influencer-Follower-Code)
     * im Warenkorb angewendet werden darf.
     */
    public function isDiscountableWithCoupon(?array $configuration = null): bool
    {
        return !$this->isGiftVoucher($configuration) && !$this->isInAutopilotSale();
    }

    /**
     * Zentrale Prüfung, ob ein Produkt aktuell von Influencern/Creatorn beworben werden kann:
     * Aktiv, kaufbar/auf Lager (isAvailable) und kein Geschenkgutschein.
     */
    public function isPromotableByInfluencer(): bool
    {
        return $this->status === 'active'
            && $this->isAvailable()
            && !$this->isGiftVoucher();
    }

    // Relationship: Product belongs to many Categories
    public function categories()
    {
        return $this->belongsToMany(ProductCategory::class);
    }

    public function supplier()
    {
        return $this->belongsTo(ProductSupplier::class, 'product_supplier_id');
    }

    public function packagings()
    {
        return $this->hasMany(ProductPackaging::class);
    }

    public function templates()
    {
        return $this->hasMany(ProductTemplate::class);
    }

    public function reviews()
    {
        return $this->hasMany(ProductReview::class);
    }

    public function orderItems()
    {
        return $this->hasMany(\App\Models\Order\OrderOrderItem::class, 'product_id');
    }

    public function getAverageRatingAttribute()
    {
        if (array_key_exists('approved_reviews_avg', $this->attributes)) {
            return round((float) ($this->attributes['approved_reviews_avg'] ?? 0), 1);
        }
        if ($this->relationLoaded('reviews')) {
            return round((float) ($this->reviews->where('status', 'approved')->avg('rating') ?? 0), 1);
        }
        // Nur freigegebene Bewertungen zählen!
        return round($this->reviews()->where('status', 'approved')->avg('rating') ?? 0, 1);
    }

    public function getReviewCountAttribute()
    {
        if (array_key_exists('approved_reviews_count', $this->attributes)) {
            return (int) ($this->attributes['approved_reviews_count'] ?? 0);
        }
        if ($this->relationLoaded('reviews')) {
            return $this->reviews->where('status', 'approved')->count();
        }
        // Nur freigegebene Bewertungen zählen!
        return $this->reviews()->where('status', 'approved')->count();
    }

    public function landingPage()
    {
        return $this->hasOne(\App\Models\Marketing\MarketingLandingPage::class);
    }

    /**
     * Prüft, ob für dieses Produkt das Hauptbild automatisch passend zur Jahreszeit / zum Feiertag
     * aus den Produktvorlagen gesetzt werden soll.
     */
    public function isAutoSeasonalMainImageEnabled(): bool
    {
        $settings = is_array($this->configurator_settings) ? $this->configurator_settings : [];
        if (array_key_exists('auto_seasonal_main_image', $settings)) {
            return filter_var($settings['auto_seasonal_main_image'], FILTER_VALIDATE_BOOLEAN);
        }

        return $this->isPersonalizable();
    }

    /**
     * Liefert die priorisierte Liste der Feiertags-/Anlass-Namen (aus ProductTemplate::holiday)
     * passend zum aktuellen Feiertagsfenster bzw. zur aktiven Jahreszeit (oder zu einem historischen Kaufdatum $referenceDate).
     */
    public static function resolvePreferredHolidayCandidates(?string $explicitHoliday = null, ?\Carbon\CarbonInterface $referenceDate = null): array
    {
        $candidates = [];

        if ($explicitHoliday !== null && trim($explicitHoliday) !== '') {
            $candidates[] = trim($explicitHoliday);
        }

        try {
            $autopilot = app(\App\Services\Marketing\HolidayOfferAutopilotService::class);
            $now = $referenceDate ? \Carbon\Carbon::instance($referenceDate) : \Carbon\Carbon::now();

            // 1. Prüfe aktives Angebotsfenster oder Feiertag bis inkl. Feiertags-Tag zum Zeitpunkt $now
            foreach ($autopilot->getEventDefinitions() as $eventKey => $def) {
                foreach ([$now->year, $now->year - 1, $now->year + 1] as $yr) {
                    $eventDate = $autopilot->getHolidayDate($eventKey, $yr);
                    if (!$eventDate) {
                        continue;
                    }
                    $startDaysBefore = max(14, (int) ($def['start_days_before'] ?? 14));
                    $winStart = $eventDate->copy()->subDays($startDaysBefore)->startOfDay();
                    $winEnd = $eventDate->copy()->addDays(1)->endOfDay();

                    if ($now->between($winStart, $winEnd)) {
                        $mapped = match ($eventKey) {
                            'valentines'  => ['Valentinstag', 'Winter', 'Hochzeit'],
                            'womens_day'  => ['Frühling', 'Muttertag', 'Valentinstag', 'Ostern'],
                            'easter'      => ['Ostern', 'Frühling', 'Muttertag'],
                            'mothers_day' => ['Muttertag', 'Frühling', 'Hochzeit'],
                            'fathers_day' => ['Vatertag', 'Frühling', 'Geburtstag'],
                            'sale_summer' => ['Sommer', 'Hochzeit', 'Geburtstag'],
                            'halloween'   => ['Halloween', 'Herbst', 'Geburtstag'],
                            'advent_1', 'christmas' => ['Weihnachten', 'Winter'],
                            'sale_winter' => ['Winter', 'Weihnachten'],
                            'new_year'    => ['Winter', 'Geburtstag', 'Weihnachten'],
                            default       => [],
                        };
                        foreach ($mapped as $m) {
                            $candidates[] = $m;
                        }
                    }
                }
            }

            // 2. Fallback über die aktive Jahreszeit (heute aus /admin/configuration, bei historischen Bestellungen aus dem Kaufmonat)
            if ($referenceDate === null || $now->isSameDay(\Carbon\Carbon::now())) {
                $seasonKey = app(\App\Services\ConfiguratorService::class)->getActiveSeasonKey();
            } else {
                $seasonKey = match ((int) $now->month) {
                    3, 4, 5  => 'ostern',
                    6, 7, 8  => 'sommer',
                    9, 10, 11 => 'herbst',
                    default  => 'winter',
                };
            }
            $seasonFallback = match ($seasonKey) {
                'ostern' => ['Frühling', 'Ostern', 'Muttertag', 'Vatertag', 'Hochzeit'],
                'sommer' => ['Sommer', 'Hochzeit', 'Geburtstag', 'Vatertag', 'Muttertag'],
                'herbst' => ['Herbst', 'Halloween', 'Geburtstag', 'Hochzeit'],
                'winter' => ['Winter', 'Weihnachten', 'Valentinstag', 'Geburtstag'],
                default  => ['Winter', 'Sommer', 'Herbst', 'Frühling', 'Weihnachten', 'Ostern', 'Hochzeit'],
            };
            foreach ($seasonFallback as $s) {
                $candidates[] = $s;
            }
        } catch (\Throwable $e) {
            // Fallback bei DB-/Service-Bootstrapping
            $candidates = array_merge($candidates, ['Winter', 'Sommer', 'Herbst', 'Frühling', 'Halloween', 'Weihnachten', 'Ostern', 'Hochzeit', 'Geburtstag']);
        }

        return array_values(array_unique($candidates));
    }

    /**
     * Findet die passende aktive Produktvorlage für das saisonale / Feiertags-Hauptbild.
     * Optional kann nach einer spezifischen Produktvariante ($variantId) oder einem Referenzdatum ($referenceDate) gefiltert werden.
     */
    public function resolveSeasonalTemplate(?string $explicitHoliday = null, array $excludeImagePaths = [], ?string $variantId = null, ?\Carbon\CarbonInterface $referenceDate = null): ?ProductTemplate
    {
        if ($explicitHoliday === null && !$this->isAutoSeasonalMainImageEnabled()) {
            return null;
        }

        $activeTemplates = $this->relationLoaded('templates')
            ? $this->templates->where('is_active', true)->filter(fn($t) => !empty($t->preview_image))
            : $this->templates()->where('is_active', true)->whereNotNull('preview_image')->where('preview_image', '!=', '')->get();

        if ($activeTemplates->isEmpty()) {
            return null;
        }

        if ($variantId !== null && $variantId !== '') {
            $activeTemplates = $activeTemplates->filter(function (ProductTemplate $tpl) use ($variantId) {
                $cfg = is_array($tpl->configuration) ? $tpl->configuration : [];
                return ($cfg['variant_id'] ?? null) === $variantId;
            });

            if ($activeTemplates->isEmpty()) {
                return null;
            }
        }

        $candidates = self::resolvePreferredHolidayCandidates($explicitHoliday, $referenceDate);

        foreach ($candidates as $candidate) {
            $normCandidate = mb_strtolower(trim($candidate));
            $found = $activeTemplates->first(function (ProductTemplate $tpl) use ($normCandidate, $excludeImagePaths) {
                if (in_array($tpl->preview_image, $excludeImagePaths, true)) {
                    return false;
                }
                return mb_strtolower(trim((string) $tpl->holiday)) === $normCandidate;
            });

            if ($found) {
                return $found;
            }
        }

        // Letzter Fallback: Erste aktive Vorlage, deren Bild noch nicht ausgeschlossen ist
        return $activeTemplates->first(fn(ProductTemplate $tpl) => !in_array($tpl->preview_image, $excludeImagePaths, true));
    }

    /**
     * Ermittelt das passende Produktbild für eine bestimmte Produktvariante ($variantId):
     * 1. Aktive (saisonale) Produktvorlage, die explizit dieser Variante zugewiesen ist
     * 2. Galeriebild unter "Medien" mit direkter Varianten-Zuweisung ("var:{variantId}")
     * 3. Galeriebild unter "Medien" mit Eigenschafts-Gruppen-Zuweisung ("attr:{Attribut}:{Wert}")
     */
    public function resolveVariantImageMeta(?string $variantId, ?string $explicitHoliday = null, ?\Carbon\CarbonInterface $referenceDate = null): ?array
    {
        if (!$variantId || trim($variantId) === '') {
            return null;
        }

        $variant = collect($this->variants_data ?? [])->firstWhere('id', $variantId);
        if (!$variant || !is_array($variant)) {
            return null;
        }

        // 1. Prüfe aktive Produktvorlage speziell für diese Variante
        if ($this->isAutoSeasonalMainImageEnabled()) {
            $variantTemplate = $this->resolveSeasonalTemplate($explicitHoliday, [], $variantId, $referenceDate);
            if ($variantTemplate && !empty($variantTemplate->preview_image)) {
                return [
                    'path'            => $variantTemplate->preview_image,
                    'url'             => ProductTemplate::resolveImageUrl($variantTemplate->preview_image),
                    'is_ai_generated' => (bool) ($variantTemplate->is_ai_generated ?? true),
                    'label'           => $variantTemplate->name . ' (' . ($variant['name'] ?? '') . ')',
                ];
            }
        }

        $gallery = is_array($this->media_gallery) ? $this->media_gallery : [];

        // 2. Prüfe Galeriebilder mit direkter Einzelvarianten-Bindung ("var:{variantId}")
        $targetVarBinding = 'var:' . $variantId;
        foreach ($gallery as $media) {
            if (!is_array($media) || ($media['type'] ?? 'image') !== 'image' || empty($media['path'])) {
                continue;
            }
            if (($media['variant_binding'] ?? '') === $targetVarBinding) {
                return [
                    'path'            => $media['path'],
                    'url'             => ProductTemplate::resolveImageUrl($media['path']),
                    'is_ai_generated' => filter_var($media['is_ai_generated'] ?? true, FILTER_VALIDATE_BOOLEAN),
                    'label'           => $variant['name'] ?? $this->name,
                ];
            }
        }

        // 3. Prüfe Galeriebilder mit Eigenschafts-Gruppen-Bindung ("attr:{Key}:{Value}")
        $variantAttrs = is_array($variant['attributes'] ?? null) ? $variant['attributes'] : [];
        foreach ($variantAttrs as $attrKey => $attrVal) {
            $targetAttrBinding = 'attr:' . trim((string) $attrKey) . ':' . trim((string) $attrVal);
            foreach ($gallery as $media) {
                if (!is_array($media) || ($media['type'] ?? 'image') !== 'image' || empty($media['path'])) {
                    continue;
                }
                if (($media['variant_binding'] ?? '') === $targetAttrBinding) {
                    return [
                        'path'            => $media['path'],
                        'url'             => ProductTemplate::resolveImageUrl($media['path']),
                        'is_ai_generated' => filter_var($media['is_ai_generated'] ?? true, FILTER_VALIDATE_BOOLEAN),
                        'label'           => "{$attrKey}: {$attrVal}",
                    ];
                }
            }
        }

        return null;
    }

    /**
     * Die 4 festen Jahreszeiten-Kacheln für das „Automatische Saison- & Feiertags-Hauptbild“
     * in der gewünschten Reihenfolge: Winter, Sommer, Herbst, Frühling.
     */
    public const SEASONAL_SEASON_SLOTS = [
        'Winter'   => ['label' => 'Winter',   'hint' => '01.12. bis 28.02.', 'icon' => '❄️', 'group' => 'season'],
        'Sommer'   => ['label' => 'Sommer',   'hint' => '01.06. bis 31.08.', 'icon' => '☀️', 'group' => 'season'],
        'Herbst'   => ['label' => 'Herbst',   'hint' => '01.09. bis 30.11.', 'icon' => '🍂', 'group' => 'season'],
        'Frühling' => ['label' => 'Frühling', 'hint' => '01.03. bis 31.05.', 'icon' => '🌸', 'group' => 'season'],
    ];

    /**
     * Die 8 festen Feiertags- & Anlass-Kacheln für das „Automatische Saison- & Feiertags-Hauptbild“.
     */
    public const SEASONAL_HOLIDAY_SLOTS = [
        'Weihnachten'  => ['label' => 'Weihnachten',  'hint' => 'Advent bis 26.12.',   'icon' => '🎄', 'group' => 'holiday'],
        'Ostern'       => ['label' => 'Ostern',       'hint' => 'Osterzeit',           'icon' => '🐰', 'group' => 'holiday'],
        'Valentinstag' => ['label' => 'Valentinstag', 'hint' => '04.02. bis 15.02.',   'icon' => '❤️', 'group' => 'holiday'],
        'Muttertag'    => ['label' => 'Muttertag',    'hint' => 'Mai (2. Sonntag)',    'icon' => '💐', 'group' => 'holiday'],
        'Vatertag'     => ['label' => 'Vatertag',     'hint' => 'Christi Himmelfahrt', 'icon' => '🍻', 'group' => 'holiday'],
        'Halloween'    => ['label' => 'Halloween',    'hint' => '19.10. bis 01.11.',   'icon' => '🎃', 'group' => 'holiday'],
        'Hochzeit'     => ['label' => 'Hochzeit',     'hint' => 'Ganzjährig',          'icon' => '💍', 'group' => 'holiday'],
        'Geburtstag'   => ['label' => 'Geburtstag',   'hint' => 'Ganzjährig',          'icon' => '🎂', 'group' => 'holiday'],
    ];

    /**
     * Liefert alle 12 Saison- & Feiertags-Kacheln (4 Jahreszeiten + 8 Feiertage).
     */
    public static function getAllSeasonalAndHolidaySlots(): array
    {
        return array_merge(self::SEASONAL_SEASON_SLOTS, self::SEASONAL_HOLIDAY_SLOTS);
    }

    /**
     * Liefert strukturierte Metadaten zum aufgelösten Hauptbild (Pfad, URL, KI-Kennzeichnung, Label).
     */
    public function resolveMainImageMeta(?string $explicitHoliday = null, bool $allowSeasonal = true, array $excludeImagePaths = [], ?\Carbon\CarbonInterface $referenceDate = null): array
    {
        if ($allowSeasonal) {
            // 0. Prüfe zuerst direkt unter "Medien -> Saison- & Feiertags-Hauptbilder" gepflegte Bilder (configurator_settings['seasonal_images'])
            if ($explicitHoliday !== null || $this->isAutoSeasonalMainImageEnabled()) {
                $settings = is_array($this->configurator_settings) ? $this->configurator_settings : [];
                $customSeasonalMap = isset($settings['seasonal_images']) && is_array($settings['seasonal_images'])
                    ? $settings['seasonal_images']
                    : [];

                if (!empty($customSeasonalMap)) {
                    $candidates = self::resolvePreferredHolidayCandidates($explicitHoliday, $referenceDate);
                    foreach ($candidates as $candidate) {
                        $normCandidate = mb_strtolower(trim($candidate));
                        foreach ($customSeasonalMap as $hKey => $sEntry) {
                            if (mb_strtolower(trim((string) $hKey)) !== $normCandidate) {
                                continue;
                            }
                            $sPath = is_array($sEntry) ? ($sEntry['path'] ?? null) : (is_string($sEntry) ? $sEntry : null);
                            if (empty($sPath) || in_array($sPath, $excludeImagePaths, true)) {
                                continue;
                            }
                            $sUrl = ProductTemplate::resolveImageUrl($sPath);
                            if ($sUrl) {
                                return [
                                    'path'            => $sPath,
                                    'url'             => $sUrl,
                                    'is_ai_generated' => is_array($sEntry) ? filter_var($sEntry['is_ai_generated'] ?? true, FILTER_VALIDATE_BOOLEAN) : true,
                                    'label'           => $this->name . ' (' . $hKey . ')',
                                ];
                            }
                        }
                    }

                    // Falls noch nicht alle Jahreszeiten befüllt sind, nutze das erste gepflegte Saisonbild als Fallback
                    if ($explicitHoliday === null) {
                        foreach ($customSeasonalMap as $hKey => $sEntry) {
                            $sPath = is_array($sEntry) ? ($sEntry['path'] ?? null) : (is_string($sEntry) ? $sEntry : null);
                            if (empty($sPath) || in_array($sPath, $excludeImagePaths, true)) {
                                continue;
                            }
                            $sUrl = ProductTemplate::resolveImageUrl($sPath);
                            if ($sUrl) {
                                return [
                                    'path'            => $sPath,
                                    'url'             => $sUrl,
                                    'is_ai_generated' => is_array($sEntry) ? filter_var($sEntry['is_ai_generated'] ?? true, FILTER_VALIDATE_BOOLEAN) : true,
                                    'label'           => $this->name . ' (' . $hKey . ')',
                                ];
                            }
                        }
                    }
                }
            }

            // 1. Prüfe aktive Produktvorlagen (ProductTemplate)
            $seasonalTpl = $this->resolveSeasonalTemplate($explicitHoliday, $excludeImagePaths, null, $referenceDate);
            if ($seasonalTpl && !empty($seasonalTpl->preview_image)) {
                return [
                    'path'            => $seasonalTpl->preview_image,
                    'url'             => ProductTemplate::resolveImageUrl($seasonalTpl->preview_image),
                    'is_ai_generated' => (bool) ($seasonalTpl->is_ai_generated ?? true),
                    'label'           => $seasonalTpl->name . ($seasonalTpl->holiday ? ' (' . $seasonalTpl->holiday . ')' : ''),
                ];
            }

            // 2. Falls keine Vorlagen existieren (z. B. beim Geschenkgutschein): Prüfe saisonale Bilder in der media_gallery
            if ($this->isAutoSeasonalMainImageEnabled() && is_array($this->media_gallery) && count($this->media_gallery) > 0) {
                $candidates = self::resolvePreferredHolidayCandidates($explicitHoliday, $referenceDate);
                foreach ($candidates as $candidate) {
                    $normCandidate = mb_strtolower(trim($candidate));
                    foreach ($this->media_gallery as $media) {
                        if (!is_array($media) || ($media['type'] ?? 'image') !== 'image' || empty($media['path'])) {
                            continue;
                        }
                        if (in_array($media['path'], $excludeImagePaths, true)) {
                            continue;
                        }
                        $mediaHoliday = mb_strtolower(trim((string) ($media['holiday'] ?? '')));
                        $filename = mb_strtolower(basename($media['path']));
                        if ($mediaHoliday === $normCandidate || str_contains($filename, '-' . $normCandidate . '.')) {
                            return [
                                'path'            => $media['path'],
                                'url'             => ProductTemplate::resolveImageUrl($media['path']),
                                'is_ai_generated' => filter_var($media['is_ai_generated'] ?? true, FILTER_VALIDATE_BOOLEAN),
                                'label'           => $media['holiday'] ?? ucfirst($normCandidate),
                            ];
                        }
                    }
                }
            }
        }

        if (is_array($this->media_gallery) && count($this->media_gallery) > 0) {
            // Zuerst explizit als Hauptbild (is_main = true) markiertes Bild bevorzugen
            foreach ($this->media_gallery as $media) {
                if (is_array($media) && ($media['type'] ?? 'image') === 'image' && !empty($media['path']) && !empty($media['is_main'])) {
                    if (!in_array($media['path'], $excludeImagePaths, true)) {
                        return [
                            'type'            => 'image',
                            'path'            => $media['path'],
                            'url'             => ProductTemplate::resolveImageUrl($media['path']),
                            'is_ai_generated' => filter_var($media['is_ai_generated'] ?? true, FILTER_VALIDATE_BOOLEAN),
                            'label'           => $media['alt'] ?? $this->name,
                        ];
                    }
                }
            }

            foreach ($this->media_gallery as $media) {
                if (is_array($media) && ($media['type'] ?? 'image') === 'image' && !empty($media['path'])) {
                    if (!in_array($media['path'], $excludeImagePaths, true)) {
                        return [
                            'type'            => 'image',
                            'path'            => $media['path'],
                            'url'             => ProductTemplate::resolveImageUrl($media['path']),
                            'is_ai_generated' => filter_var($media['is_ai_generated'] ?? true, FILTER_VALIDATE_BOOLEAN),
                            'label'           => $media['alt'] ?? $this->name,
                        ];
                    }
                } elseif (is_string($media) && $media !== '' && !in_array($media, $excludeImagePaths, true)) {
                    return [
                        'type'            => 'image',
                        'path'            => $media,
                        'url'             => ProductTemplate::resolveImageUrl($media),
                        'is_ai_generated' => true,
                        'label'           => $this->name,
                    ];
                }
            }
        }

        $isConfiguratorCutout = !empty($this->preview_image_path)
            && (str_ends_with((string) $this->preview_image_path, '/overlay.png')
                || str_ends_with((string) $this->preview_image_path, '/envelope.png'));

        if (!empty($this->preview_image_path) && !$isConfiguratorCutout && !in_array($this->preview_image_path, $excludeImagePaths, true)) {
            return [
                'type'            => 'image',
                'path'            => $this->preview_image_path,
                'url'             => ProductTemplate::resolveImageUrl($this->preview_image_path),
                'is_ai_generated' => false,
                'label'           => $this->name,
            ];
        }

        return [
            'type'            => 'image',
            'path'            => null,
            'url'             => null,
            'is_ai_generated' => false,
            'label'           => null,
        ];
    }

    /**
     * Liefert universelle strukturierte Metadaten zur Hauptansicht des Produkts (unterstützt sowohl Bild als auch Video).
     * Wurde in "2. Medien -> Galerie & Marketing" ein Video als Hauptansicht (is_main = true bzw. Position 0) definiert,
     * liefert diese Methode type = 'video' samt Poster-Fallback-URL.
     *
     * @return array{type: string, path: ?string, url: ?string, poster_url: ?string, is_ai_generated: bool, label: ?string}
     */
    public function resolveMainMediaMeta(?string $explicitHoliday = null, bool $allowSeasonal = true, array $excludePaths = [], ?\Carbon\CarbonInterface $referenceDate = null): array
    {
        $gallery = is_array($this->media_gallery) ? $this->media_gallery : [];

        // 1. Prüfen, ob explizit ein Video als Hauptansicht (is_main = true oder Position 0 ohne anderweitiges is_main) gesetzt wurde
        $explicitMainItem = null;
        foreach ($gallery as $media) {
            if (is_array($media) && !empty($media['is_main']) && !empty($media['path'])) {
                $explicitMainItem = $media;
                break;
            }
        }
        if ($explicitMainItem === null && isset($gallery[0]) && is_array($gallery[0]) && !empty($gallery[0]['path'])) {
            $explicitMainItem = $gallery[0];
        }

        if (
            is_array($explicitMainItem)
            && ($explicitMainItem['type'] ?? 'image') === 'video'
            && !in_array($explicitMainItem['path'], $excludePaths, true)
        ) {
            $fallbackImgMeta = $this->resolveMainImageMeta($explicitHoliday, $allowSeasonal, $excludePaths, $referenceDate);
            return [
                'type'            => 'video',
                'path'            => $explicitMainItem['path'],
                'url'             => ProductTemplate::resolveImageUrl($explicitMainItem['path']),
                'poster_url'      => $fallbackImgMeta['url'] ?? null,
                'is_ai_generated' => filter_var($explicitMainItem['is_ai_generated'] ?? false, FILTER_VALIDATE_BOOLEAN),
                'label'           => $explicitMainItem['alt'] ?? $this->name,
            ];
        }

        // 2. Reguläre Hauptbild-Auflösung (inkl. Saison-/Feiertags-Automatik)
        $imgMeta = $this->resolveMainImageMeta($explicitHoliday, $allowSeasonal, $excludePaths, $referenceDate);
        if (!empty($imgMeta['url'])) {
            return [
                'type'            => 'image',
                'path'            => $imgMeta['path'] ?? null,
                'url'             => $imgMeta['url'],
                'poster_url'      => $imgMeta['url'],
                'is_ai_generated' => (bool) ($imgMeta['is_ai_generated'] ?? false),
                'label'           => $imgMeta['label'] ?? $this->name,
            ];
        }

        // 3. Fallback: Falls überhaupt kein Bild existiert, aber ein Video in der Galerie vorhanden ist
        foreach ($gallery as $media) {
            if (is_array($media) && ($media['type'] ?? '') === 'video' && !empty($media['path']) && !in_array($media['path'], $excludePaths, true)) {
                return [
                    'type'            => 'video',
                    'path'            => $media['path'],
                    'url'             => ProductTemplate::resolveImageUrl($media['path']),
                    'poster_url'      => null,
                    'is_ai_generated' => filter_var($media['is_ai_generated'] ?? false, FILTER_VALIDATE_BOOLEAN),
                    'label'           => $media['alt'] ?? $this->name,
                ];
            }
        }

        return [
            'type'            => 'image',
            'path'            => null,
            'url'             => null,
            'poster_url'      => null,
            'is_ai_generated' => false,
            'label'           => null,
        ];
    }

    /**
     * Liefert die universellen Metadaten zum definierten Hover-Medium (Bild oder Video aus media_gallery mit is_hover = true).
     * Gibt null zurück, falls kein Hover-Medium definiert ist oder dessen Pfad identisch zur aktiven Hauptansicht ist.
     *
     * @return ?array{type: string, path: string, url: ?string, is_ai_generated: bool, label: string}
     */
    public function resolveHoverMediaMeta(?string $excludeMainPath = null): ?array
    {
        if (!is_array($this->media_gallery) || count($this->media_gallery) === 0) {
            return null;
        }

        foreach ($this->media_gallery as $media) {
            if (!is_array($media) || empty($media['is_hover']) || empty($media['path'])) {
                continue;
            }

            if ($excludeMainPath !== null && $media['path'] === $excludeMainPath) {
                continue;
            }

            $type = (($media['type'] ?? 'image') === 'video') ? 'video' : 'image';
            $url = ProductTemplate::resolveImageUrl($media['path']);
            if (!$url) {
                continue;
            }

            return [
                'type'            => $type,
                'path'            => $media['path'],
                'url'             => $url,
                'is_ai_generated' => filter_var($media['is_ai_generated'] ?? ($type === 'image'), FILTER_VALIDATE_BOOLEAN),
                'label'           => $media['alt'] ?? ($this->name . ': Hover-Ansicht'),
            ];
        }

        return null;
    }

    /**
     * Liefert die Metadaten zum definierten „Neulingsbild“ (media_gallery-Eintrag mit is_beginner = true),
     * auf das die Produktgalerie im Shop automatisch springt, sobald der Kunde „Neuling (Einfache Variante)“ wählt.
     *
     * @return ?array{type: string, path: string, url: ?string, is_ai_generated: bool, label: string}
     */
    public function resolveBeginnerMediaMeta(): ?array
    {
        if (is_array($this->media_gallery) && count($this->media_gallery) > 0) {
            foreach ($this->media_gallery as $media) {
                if (!is_array($media) || empty($media['is_beginner']) || empty($media['path'])) {
                    continue;
                }

                $type = (($media['type'] ?? 'image') === 'video') ? 'video' : 'image';
                $url = ProductTemplate::resolveImageUrl($media['path']);
                if (!$url) {
                    continue;
                }

                return [
                    'type'            => $type,
                    'path'            => $media['path'],
                    'url'             => $url,
                    'is_ai_generated' => filter_var($media['is_ai_generated'] ?? ($type === 'image'), FILTER_VALIDATE_BOOLEAN),
                    'label'           => $media['alt'] ?? ($this->name . ': Neulingsvorschau'),
                ];
            }
        }

        // Fallback: Erste aktive Vorlage oder saisonales/reguläres Hauptbild
        $seasonalTpl = $this->resolveSeasonalTemplate(null, [], null, null);
        if ($seasonalTpl && !empty($seasonalTpl->preview_image)) {
            $url = ProductTemplate::resolveImageUrl($seasonalTpl->preview_image);
            if ($url) {
                return [
                    'type'            => 'image',
                    'path'            => $seasonalTpl->preview_image,
                    'url'             => $url,
                    'is_ai_generated' => (bool) ($seasonalTpl->is_ai_generated ?? true),
                    'label'           => $this->name . ': Neulingsvorschau',
                ];
            }
        }

        $mainMeta = $this->resolveMainImageMeta(null, true);
        if (!empty($mainMeta['url']) && !empty($mainMeta['path'])) {
            return [
                'type'            => 'image',
                'path'            => $mainMeta['path'],
                'url'             => $mainMeta['url'],
                'is_ai_generated' => (bool) ($mainMeta['is_ai_generated'] ?? true),
                'label'           => $this->name . ': Neulingsvorschau',
            ];
        }

        return null;
    }

    /**
     * Liefert die strukturierte Übersicht aller 12 Saison- & Feiertags-Hauptbilder (4 Jahreszeiten + 8 Feiertage)
     * für Schritt 2 ("Medien"). Berücksichtigt sowohl direkt im Produkt hinterlegte Saisonbilder
     * (configurator_settings['seasonal_images'] / media_gallery) als auch vorhandene Produktvorlagen (ProductTemplate).
     *
     * @return array<string, array{holiday: string, label: string, hint: string, icon: string, group: string, path: ?string, url: ?string, is_ai_generated: bool, source: ?string, is_active_today: bool}>
     */
    public function getSeasonalMainImagesOverview(): array
    {
        $settings = is_array($this->configurator_settings) ? $this->configurator_settings : [];
        $customSeasonalMap = isset($settings['seasonal_images']) && is_array($settings['seasonal_images'])
            ? $settings['seasonal_images']
            : [];
        $gallery = is_array($this->media_gallery) ? $this->media_gallery : [];
        $templates = $this->relationLoaded('templates')
            ? $this->templates
            : $this->templates()->get();

        $activeMeta = $this->isAutoSeasonalMainImageEnabled()
            ? $this->resolveMainImageMeta(null, true)
            : null;
        $activePathToday = $activeMeta['path'] ?? null;

        $overview = [];

        foreach (self::getAllSeasonalAndHolidaySlots() as $holidayKey => $slotMeta) {
            $normKey = mb_strtolower(trim($holidayKey));
            $resolvedPath = null;
            $resolvedIsAi = true;
            $resolvedSource = null;

            // 1. Direkt gepflegtes Saisonbild in configurator_settings['seasonal_images']
            foreach ($customSeasonalMap as $cKey => $cEntry) {
                if (mb_strtolower(trim((string) $cKey)) === $normKey) {
                    $cPath = is_array($cEntry) ? ($cEntry['path'] ?? null) : (is_string($cEntry) ? $cEntry : null);
                    if (!empty($cPath)) {
                        $resolvedPath = $cPath;
                        $resolvedIsAi = is_array($cEntry) ? filter_var($cEntry['is_ai_generated'] ?? true, FILTER_VALIDATE_BOOLEAN) : true;
                        $resolvedSource = 'custom';
                        break;
                    }
                }
            }

            // 2. Aktive Produktvorlage (ProductTemplate)
            if ($resolvedPath === null) {
                $matchedTpl = $templates->first(fn($t) => mb_strtolower(trim((string) ($t->holiday ?? ''))) === $normKey && !empty($t->preview_image));
                if ($matchedTpl) {
                    $resolvedPath = $matchedTpl->preview_image;
                    $resolvedIsAi = (bool) ($matchedTpl->is_ai_generated ?? true);
                    $resolvedSource = 'template';
                }
            }

            // 3. Galerie-Eintrag mit holiday-Tag
            if ($resolvedPath === null) {
                foreach ($gallery as $media) {
                    if (!is_array($media) || ($media['type'] ?? 'image') !== 'image' || empty($media['path'])) {
                        continue;
                    }
                    $mediaHoliday = mb_strtolower(trim((string) ($media['holiday'] ?? '')));
                    $filename = mb_strtolower(basename($media['path']));
                    if ($mediaHoliday === $normKey || str_contains($filename, '-' . $normKey . '.')) {
                        $resolvedPath = $media['path'];
                        $resolvedIsAi = filter_var($media['is_ai_generated'] ?? true, FILTER_VALIDATE_BOOLEAN);
                        $resolvedSource = 'gallery';
                        break;
                    }
                }
            }

            $resolvedUrl = $resolvedPath ? ProductTemplate::resolveImageUrl($resolvedPath) : null;

            $overview[$holidayKey] = [
                'holiday'         => $holidayKey,
                'label'           => $slotMeta['label'],
                'hint'            => $slotMeta['hint'],
                'icon'            => $slotMeta['icon'] ?? '',
                'group'           => $slotMeta['group'] ?? 'holiday',
                'path'            => $resolvedPath,
                'url'             => $resolvedUrl,
                'is_ai_generated' => $resolvedIsAi,
                'source'          => $resolvedSource,
                'is_active_today' => ($resolvedPath !== null && $activePathToday !== null && $resolvedPath === $activePathToday),
            ];
        }

        return $overview;
    }

    /**
     * Berechnet den Vollständigkeits-Status des Saison- & Feiertags-Bildersatzes (für die ! / ✓ Anzeige in der Produktübersicht).
     * - Ist der Schalter "Automatisch nach Jahreszeit / Feiertag" deaktiviert, ist der Status automatisch grün (is_complete = true).
     * - Ist der Schalter aktiviert, müssen alle 4 Jahreszeiten + alle 8 Feiertage mindestens 1 Bild besitzen.
     *
     * @return array{is_complete: bool, auto_enabled: bool, filled_count: int, total_count: int, seasons_filled: int, seasons_total: int, holidays_filled: int, holidays_total: int, missing_labels: array<int, string>, tooltip: string}
     */
    public function getSeasonalImageSetStatus(): array
    {
        $autoEnabled = $this->isAutoSeasonalMainImageEnabled();
        $overview = $this->getSeasonalMainImagesOverview();

        $seasonsTotal = count(self::SEASONAL_SEASON_SLOTS);
        $holidaysTotal = count(self::SEASONAL_HOLIDAY_SLOTS);
        $totalCount = $seasonsTotal + $holidaysTotal;

        $seasonsFilled = 0;
        $holidaysFilled = 0;
        $missingLabels = [];

        foreach ($overview as $slot) {
            $hasImg = !empty($slot['path']);
            $isSeason = ($slot['group'] ?? 'holiday') === 'season';
            if ($hasImg) {
                if ($isSeason) {
                    $seasonsFilled++;
                } else {
                    $holidaysFilled++;
                }
            } else {
                $missingLabels[] = $slot['label'];
            }
        }

        $filledCount = $seasonsFilled + $holidaysFilled;

        if (!$autoEnabled) {
            return [
                'is_complete'     => true,
                'auto_enabled'    => false,
                'filled_count'    => $filledCount,
                'total_count'     => $totalCount,
                'seasons_filled'  => $seasonsFilled,
                'seasons_total'   => $seasonsTotal,
                'holidays_filled' => $holidaysFilled,
                'holidays_total'  => $holidaysTotal,
                'missing_labels'  => [],
                'tooltip'         => 'Saison-Automatik aus (Kein Saison-Bildersatz erforderlich)',
            ];
        }

        $isComplete = ($filledCount === $totalCount);
        $tooltip = $isComplete
            ? "Bildersatz komplett ({$filledCount}/{$totalCount} Jahreszeiten- & Feiertagsbilder gepflegt)"
            : "Bildersatz unvollständig ({$filledCount}/{$totalCount} gepflegt) | Offen: " . implode(', ', $missingLabels);

        return [
            'is_complete'     => $isComplete,
            'auto_enabled'    => true,
            'filled_count'    => $filledCount,
            'total_count'     => $totalCount,
            'seasons_filled'  => $seasonsFilled,
            'seasons_total'   => $seasonsTotal,
            'holidays_filled' => $holidaysFilled,
            'holidays_total'  => $holidaysTotal,
            'missing_labels'  => $missingLabels,
            'tooltip'         => $tooltip,
        ];
    }

    /**
     * Liefert den relativen Storage-Pfad des Hauptbildes (entweder saisonale Vorlage oder manuelles Galerie-Hauptbild).
     */
    public function resolveMainImagePath(?string $explicitHoliday = null, bool $allowSeasonal = true, array $excludeImagePaths = [], ?\Carbon\CarbonInterface $referenceDate = null): ?string
    {
        return $this->resolveMainImageMeta($explicitHoliday, $allowSeasonal, $excludeImagePaths, $referenceDate)['path'];
    }

    /**
     * Liefert die fertige, Cache-sichere öffentliche URL des Produkt-Hauptbildes.
     */
    public function resolveMainImageUrl(?string $explicitHoliday = null, bool $allowSeasonal = true, array $excludeImagePaths = [], ?\Carbon\CarbonInterface $referenceDate = null): ?string
    {
        return $this->resolveMainImageMeta($explicitHoliday, $allowSeasonal, $excludeImagePaths, $referenceDate)['url'];
    }

    /**
     * Ermittelt den relativen Bildpfad unter Berücksichtigung einer Artikel-Konfiguration
     * (eingefrorenes Kaufbild 'purchased_image_path', gewählte Variante 'variant_id' oder saisonales Hauptbild zum Referenzdatum).
     */
    public function resolveConfigurationImagePath(?array $configuration = null, ?\Carbon\CarbonInterface $referenceDate = null): ?string
    {
        $conf = is_array($configuration) ? $configuration : [];

        if (!empty($conf['purchased_image_path']) && is_string($conf['purchased_image_path'])) {
            return $conf['purchased_image_path'];
        }

        $explicitHoliday = !empty($conf['purchased_holiday']) && is_string($conf['purchased_holiday'])
            ? $conf['purchased_holiday']
            : null;

        if (!empty($conf['variant_id']) && is_string($conf['variant_id'])) {
            $varMeta = $this->resolveVariantImageMeta($conf['variant_id'], $explicitHoliday, $referenceDate);
            if (!empty($varMeta['path'])) {
                return $varMeta['path'];
            }
        }

        return $this->resolveMainImagePath($explicitHoliday, true, [], $referenceDate);
    }

    /**
     * Ermittelt die fertige Bild-URL unter Berücksichtigung einer Artikel-Konfiguration.
     */
    public function resolveConfigurationImageUrl(?array $configuration = null, ?\Carbon\CarbonInterface $referenceDate = null): ?string
    {
        $path = $this->resolveConfigurationImagePath($configuration, $referenceDate);
        return $path ? ProductTemplate::resolveImageUrl($path) : null;
    }

    /**
     * Ermittelt verkaufspsychologisch sortierte Empfehlungen für die Produktdetailseite:
     * 1. Stufe ("Das könnte dir auch gefallen"): Nur aktive Produkte, die mindestens eine Kategorie
     *    mit dem aktuellen Produkt teilen (sortiert nach Verfügbarkeit, Kategorie-Überschneidungstiefe,
     *    Produkttyp-Affinität und Bewertung).
     * 2. Stufe Fallback ("Weitere Produkte aus unserem Sortiment"): Falls keine anderen aktiven Produkte
     *    in derselben Kategorie existieren, werden andere verfügbare Produkte aus dem Gesamtsortiment geladen.
     *
     * @return array{mode: string, title: string, subtitle: string, kicker: string, products: \Illuminate\Support\Collection}
     */
    public function resolveCrossSellProducts(int $limit = 4): array
    {
        $categoryIds = $this->relationLoaded('categories')
            ? $this->categories->pluck('id')->all()
            : $this->categories()->pluck('product_categories.id')->all();

        $baseQuery = fn () => static::where('status', 'active')
            ->where('id', '!=', $this->id)
            ->with([
                'categories',
                'tierPrices',
                'templates' => fn ($q) => $q->where('is_active', true),
            ])
            ->withCount([
                'reviews as approved_reviews_count' => fn ($q) => $q->where('status', 'approved'),
            ])
            ->withAvg([
                'reviews as approved_reviews_avg' => fn ($q) => $q->where('status', 'approved'),
            ], 'rating');

        if (!empty($categoryIds)) {
            $categoryMatches = $baseQuery()
                ->whereHas('categories', function ($q) use ($categoryIds) {
                    $q->whereIn('product_categories.id', $categoryIds);
                })
                ->get();

            if ($categoryMatches->isNotEmpty()) {
                $sortedMatches = $categoryMatches->sortByDesc(function (self $candidate) use ($categoryIds) {
                    $availableScore = $candidate->isAvailable() ? 10000 : 0;
                    $overlapCount = $candidate->categories->whereIn('id', $categoryIds)->count();
                    $sameTypeScore = ($candidate->type === $this->type && $candidate->slug !== 'geschenkgutschein') ? 500 : 0;
                    $ratingScore = (int) round(((float) ($candidate->approved_reviews_avg ?? 0)) * 20)
                                 + min(50, (int) ($candidate->approved_reviews_count ?? 0));

                    return $availableScore + ($overlapCount * 1000) + $sameTypeScore + $ratingScore;
                })->take($limit)->values();

                $categoryNames = $this->relationLoaded('categories')
                    ? $this->categories->pluck('name')->take(2)->implode(' & ')
                    : $this->categories()->pluck('name')->take(2)->implode(' & ');

                return [
                    'mode'     => 'category',
                    'kicker'   => $categoryNames !== '' ? 'Passend zu ' . $categoryNames : 'Passend ausgewählt',
                    'title'    => 'Das könnte dir auch gefallen',
                    'subtitle' => 'Entdecke weitere liebevoll veredelte Geschenkideen aus derselben Kategorie.',
                    'products' => $sortedMatches,
                ];
            }
        }

        // Fallback: Keine Produkte in derselben Kategorie vorhanden -> "Weitere Produkte aus unserem Sortiment"
        $fallbackProducts = $baseQuery()
            ->get()
            ->sortByDesc(function (self $candidate) {
                $availableScore = $candidate->isAvailable() ? 10000 : 0;
                $physicalScore = ($candidate->type === 'physical' && $candidate->slug !== 'geschenkgutschein') ? 500 : 0;
                $ratingScore = (int) round(((float) ($candidate->approved_reviews_avg ?? 0)) * 20)
                             + min(50, (int) ($candidate->approved_reviews_count ?? 0));

                return $availableScore + $physicalScore + $ratingScore;
            })
            ->take($limit)
            ->values();

        return [
            'mode'     => 'assortment',
            'kicker'   => 'Blick in die Manufaktur',
            'title'    => 'Weitere Produkte aus unserem Sortiment',
            'subtitle' => 'Entdecke weitere handveredelte Unikate und Geschenkideen aus unserer Kollektion.',
            'products' => $fallbackProducts,
        ];
    }

    /**
     * Berechnet die kalkulatorische Zahlungs-/Stripe-Gebühr in Cent auf Basis des Brutto-Verkaufspreises.
     * Standard: 2,5 % + 0,30 € (30 Cent) als Mischsatz für Stripe (Kreditkarte, Klarna, Apple Pay, Google Pay).
     */
    public static function computeStripeFeeCents(int $grossCents): int
    {
        if ($grossCents <= 0) {
            return 0;
        }

        $rawPercent = shop_setting('stripe_fee_percent', 2.50);
        $feePercent = (float) str_replace(',', '.', (string) $rawPercent);

        $rawFixed = shop_setting('stripe_fee_fixed_cents', 30);
        if (is_string($rawFixed) && (str_contains($rawFixed, '.') || str_contains($rawFixed, ','))) {
            $feeFixedCents = (int) round(((float) str_replace(',', '.', $rawFixed)) * 100);
        } else {
            $feeFixedCents = (int) $rawFixed;
        }

        return (int) round(($grossCents * ($feePercent / 100)) + $feeFixedCents);
    }

    public function calculatePaymentFeeCents(?int $grossPriceCents = null): int
    {
        $gross = $grossPriceCents ?? (int) $this->gross_price;
        return self::computeStripeFeeCents($gross);
    }

    /**
     * Erzeugt den vollständigen Schema.org JSON-LD Graphen (BreadcrumbList + Product inkl. Offer,
     * OfferShippingDetails, MerchantReturnPolicy, AggregateRating & Reviews) für Produkt- und Landingpages.
     */
    public function buildStructuredDataSchema(?string $canonicalUrl = null): array
    {
        $this->loadMissing([
            'categories',
            'tierPrices',
            'reviews' => fn ($q) => $q->where('status', 'approved')->with('customer')->latest(),
        ]);

        $canonicalUrl = $canonicalUrl ?: route('product.show', $this->slug);
        $mainImageUrl = $this->resolveMainImageUrl(null, false)
            ?: ($this->resolveMainImageUrl() ?: asset('shop/projekt/logo/mein-seelenfunke-logo.png'));

        $schemaImages = [$mainImageUrl];
        if (is_array($this->media_gallery)) {
            foreach ($this->media_gallery as $media) {
                if (is_array($media) && ($media['type'] ?? 'image') === 'image' && !empty($media['path'])) {
                    $imgUrl = ProductTemplate::resolveImageUrl($media['path']);
                    if ($imgUrl && !in_array($imgUrl, $schemaImages, true)) {
                        $schemaImages[] = $imgUrl;
                    }
                }
            }
        }

        $additionalProperties = [];
        $essentialAttributes = \App\Models\Marketing\MarketingLandingPage::extractEssentialAttributes($this);
        foreach ($essentialAttributes as $attrKey => $attrVal) {
            if (is_scalar($attrVal) && trim((string) $attrVal) !== '') {
                $additionalProperties[] = [
                    '@type' => 'PropertyValue',
                    'name' => (string) $attrKey,
                    'value' => (string) $attrVal,
                ];
            }
        }

        $freeThresholdCents = (int) shop_setting('shipping_free_threshold', 5000);
        $defaultShipCents = (int) shop_setting('shipping_cost', 490);
        $effectiveShipCents = (!$this->isPhysical() || (int) $this->gross_price >= $freeThresholdCents)
            ? 0
            : $defaultShipCents;
        $shippingCostEur = number_format($effectiveShipCents / 100, 2, '.', '');

        $returnPolicy = $this->isPersonalizable()
            ? [
                '@type' => 'MerchantReturnPolicy',
                'applicableCountry' => 'DE',
                'returnPolicyCategory' => 'https://schema.org/MerchantReturnNotPermitted',
                'description' => 'Individuell nach Kundenwunsch gravierte Unikate sind gemäß § 312g Abs. 2 Nr. 1 BGB vom Widerruf ausgeschlossen; bei Transportschäden oder Mängeln erfolgt kostenfreier Ersatz.',
            ]
            : [
                '@type' => 'MerchantReturnPolicy',
                'applicableCountry' => 'DE',
                'returnPolicyCategory' => 'https://schema.org/MerchantReturnFiniteReturnWindow',
                'merchantReturnDays' => 14,
                'returnMethod' => 'https://schema.org/ReturnByMail',
            ];

        $shippingDetails = [
            '@type' => 'OfferShippingDetails',
            'shippingRate' => [
                '@type' => 'MonetaryAmount',
                'value' => $shippingCostEur,
                'currency' => 'EUR',
            ],
            'shippingDestination' => [
                '@type' => 'DefinedRegion',
                'addressCountry' => 'DE',
            ],
            'deliveryTime' => [
                '@type' => 'ShippingDeliveryTime',
                'handlingTime' => [
                    '@type' => 'QuantitativeValue',
                    'minValue' => 1,
                    'maxValue' => 3,
                    'unitCode' => 'DAY',
                ],
                'transitTime' => [
                    '@type' => 'QuantitativeValue',
                    'minValue' => 1,
                    'maxValue' => 3,
                    'unitCode' => 'DAY',
                ],
            ],
        ];

        $effectiveGrossCents = ($this->slug === 'geschenkgutschein' && (int) $this->gross_price <= 0)
            ? 500
            : (int) $this->gross_price;
        $basePriceEur = number_format($effectiveGrossCents / 100, 2, '.', '');
        $priceValidUntil = now()->addYear()->format('Y-m-d');
        $availabilityUrl = $this->isAvailable()
            ? 'https://schema.org/InStock'
            : 'https://schema.org/OutOfStock';

        $activeVariants = array_values(array_filter(
            $this->getSanitizedVariantsData(false),
            fn ($v) => is_array($v) && filter_var($v['is_active'] ?? true, FILTER_VALIDATE_BOOLEAN)
        ));

        if (count($activeVariants) > 1) {
            $variantOffers = [];
            $pricesFloat = [];
            $calculator = new PriceCalcService();
            foreach ($activeVariants as $v) {
                $surchargeCents = (isset($v['price']) && $v['price'] !== '')
                    ? (int) round(((float) str_replace(',', '.', (string) $v['price'])) * 100)
                    : 0;
                $varRawCents = (int) $this->price + $surchargeCents;
                $varGrossCents = $this->tax_included
                    ? $varRawCents
                    : $calculator->getGrossFromNet($varRawCents, (float) $this->tax_rate);
                $varPriceFloat = $varGrossCents / 100;
                $pricesFloat[] = $varPriceFloat;

                $varShipCents = (!$this->isPhysical() || $varGrossCents >= $freeThresholdCents)
                    ? 0
                    : $defaultShipCents;
                $varShippingDetails = $shippingDetails;
                $varShippingDetails['shippingRate']['value'] = number_format($varShipCents / 100, 2, '.', '');

                $variantOffers[] = [
                    '@type' => 'Offer',
                    'name' => (string) ($v['name'] ?? $this->name),
                    'sku' => (string) ($v['sku'] ?? ($this->sku ?: ('MSF-' . strtoupper(substr((string) $this->id, 0, 8))))),
                    'url' => $canonicalUrl,
                    'priceCurrency' => 'EUR',
                    'price' => number_format($varPriceFloat, 2, '.', ''),
                    'priceValidUntil' => $priceValidUntil,
                    'availability' => $this->isVariantAvailable($v)
                        ? 'https://schema.org/InStock'
                        : 'https://schema.org/OutOfStock',
                    'itemCondition' => 'https://schema.org/NewCondition',
                    'seller' => [
                        '@id' => url('/#organization'),
                    ],
                    'hasMerchantReturnPolicy' => $returnPolicy,
                    'shippingDetails' => $varShippingDetails,
                ];
            }

            $offersNode = [
                '@type' => 'AggregateOffer',
                'url' => $canonicalUrl,
                'priceCurrency' => 'EUR',
                'lowPrice' => number_format(min($pricesFloat), 2, '.', ''),
                'highPrice' => number_format(max($pricesFloat), 2, '.', ''),
                'price' => $basePriceEur,
                'offerCount' => count($variantOffers),
                'availability' => $availabilityUrl,
                'offers' => $variantOffers,
            ];
        } else {
            $offersNode = [
                '@type' => 'Offer',
                'url' => $canonicalUrl,
                'priceCurrency' => 'EUR',
                'price' => $basePriceEur,
                'priceValidUntil' => $priceValidUntil,
                'availability' => $availabilityUrl,
                'itemCondition' => 'https://schema.org/NewCondition',
                'seller' => [
                    '@id' => url('/#organization'),
                ],
                'hasMerchantReturnPolicy' => $returnPolicy,
                'shippingDetails' => $shippingDetails,
            ];
        }

        $resolvedSku = $this->sku ?: ('MSF-' . strtoupper(substr((string) $this->id, 0, 8)));

        $productNode = [
            '@type' => 'Product',
            '@id' => $canonicalUrl . '#product',
            'name' => $this->name,
            'description' => trim(preg_replace('/\s+/', ' ', strip_tags((string) ($this->description ?: $this->short_description)))),
            'sku' => $resolvedSku,
            'mpn' => $resolvedSku,
            'isFamilyFriendly' => true,
            'url' => $canonicalUrl,
            'image' => $schemaImages,
            'brand' => [
                '@type' => 'Brand',
                'name' => shop_setting('company_name', shop_setting('owner_name', 'Mein Seelenfunke')),
            ],
            'manufacturer' => [
                '@id' => url('/#organization'),
            ],
            'countryOfOrigin' => 'DE',
            'offers' => $offersNode,
        ];

        if ($this->width > 0) {
            $productNode['width'] = [
                '@type' => 'QuantitativeValue',
                'value' => (float) $this->width,
                'unitCode' => 'MMT',
            ];
        }
        if ($this->height > 0) {
            $productNode['height'] = [
                '@type' => 'QuantitativeValue',
                'value' => (float) $this->height,
                'unitCode' => 'MMT',
            ];
        }
        if ($this->length > 0) {
            $productNode['depth'] = [
                '@type' => 'QuantitativeValue',
                'value' => (float) $this->length,
                'unitCode' => 'MMT',
            ];
        }
        if ($this->weight > 0) {
            $productNode['weight'] = [
                '@type' => 'QuantitativeValue',
                'value' => (float) $this->weight,
                'unitCode' => 'GRM',
            ];
        }

        if (is_array($this->media_gallery)) {
            foreach ($this->media_gallery as $media) {
                if (is_array($media) && ($media['type'] ?? '') === 'video' && !empty($media['path'])) {
                    $videoUrl = asset('storage/' . ltrim((string) $media['path'], '/'));
                    $productNode['subjectOf'] = [
                        '@type' => 'VideoObject',
                        'name' => $media['alt'] ?? ($this->name . ' – Produktvideo & Lasergravur'),
                        'description' => trim(preg_replace('/\s+/', ' ', strip_tags((string) ($this->short_description ?: $this->description)))),
                        'thumbnailUrl' => [$mainImageUrl],
                        'contentUrl' => $videoUrl,
                        'uploadDate' => optional($this->updated_at ?? $this->created_at)->toAtomString() ?: now()->toAtomString(),
                        'duration' => 'PT8S',
                        'isFamilyFriendly' => true,
                    ];
                    break;
                }
            }
        }

        if (!empty($additionalProperties)) {
            $productNode['additionalProperty'] = $additionalProperties;
            if (!empty($essentialAttributes['Material'])) {
                $productNode['material'] = (string) $essentialAttributes['Material'];
            }
        }

        if ($this->categories->isNotEmpty()) {
            $productNode['category'] = $this->categories->pluck('name')->implode(', ');
        }

        $approvedReviews = $this->relationLoaded('reviews')
            ? $this->reviews->where('status', 'approved')
            : $this->reviews()->where('status', 'approved')->get();
        if ($approvedReviews && $approvedReviews->count() > 0) {
            $productNode['aggregateRating'] = [
                '@type' => 'AggregateRating',
                'ratingValue' => (string) round($approvedReviews->avg('rating'), 1),
                'reviewCount' => (int) $approvedReviews->count(),
                'bestRating' => '5',
                'worstRating' => '1',
            ];

            $reviewNodes = [];
            foreach ($approvedReviews->take(5) as $rev) {
                $authorName = $rev->is_anonymous
                    ? 'Anonymer Kunde'
                    : ($rev->customer_name
                        ?: ($rev->customer ? trim($rev->customer->first_name . ' ' . mb_substr(trim((string) $rev->customer->last_name), 0, 1) . '.') : 'Verifizierter Kunde'));
                $cleanAuthorName = trim(strip_tags((string) $authorName));
                $cleanReviewTitle = trim(strip_tags((string) ($rev->title ?: $this->name)));
                $reviewNodes[] = [
                    '@type' => 'Review',
                    'author' => [
                        '@type' => 'Person',
                        'name' => $cleanAuthorName ?: 'Verifizierter Kunde',
                    ],
                    'datePublished' => optional($rev->created_at)->format('Y-m-d') ?: now()->format('Y-m-d'),
                    'reviewBody' => trim(strip_tags((string) ($rev->comment ?? $rev->content ?? ''))),
                    'name' => $cleanReviewTitle ?: $this->name,
                    'reviewRating' => [
                        '@type' => 'Rating',
                        'ratingValue' => (string) ($rev->rating ?? 5),
                        'bestRating' => '5',
                        'worstRating' => '1',
                    ],
                ];
            }
            $productNode['review'] = $reviewNodes;
        }

        $materialText = !empty($essentialAttributes['Material']) ? (string) $essentialAttributes['Material'] : 'hochwertigem Grundmaterial';
        $dimText = ($this->width > 0 && $this->height > 0)
            ? "{$this->width} × {$this->height}" . ($this->length > 0 ? " × {$this->length} mm" : ' mm') . ($this->weight > 0 ? " (Gewicht ca. {$this->weight} g)" : '')
            : 'den in den Produkteigenschaften angegebenen Originalmaßen';

        $companyCityFaq = shop_setting('company_city', shop_setting('owner_city', 'Gifhorn'));
        if ($this->slug === 'geschenkgutschein') {
            $deliveryFaqAnswer = 'Digitale Geschenkgutscheine stehen nach Zahlungseingang sofort als PDF-Download bereit. Bei Wahl des Postversands wird die lasergravierte Aluminiumkarte im schwarzen Edel-Umschlag mit goldenem Siegel innerhalb von 3 bis 5 Werktagen versendet.';
        } elseif ($this->type === 'digital') {
            $deliveryFaqAnswer = 'Digitale Produkte stehen unmittelbar nach Zahlungseingang als Sofort-Download bereit.';
        } else {
            $deliveryFaqAnswer = 'Jedes Unikat wird in unserer Manufaktur in ' . $companyCityFaq . ' (Deutschland) einzeln per Laser veredelt, handgeprüft und innerhalb von 3–5 Werktagen sorgfältig gepolstert per DHL mit Sendungsverfolgung versendet.';
        }

        $faqNode = [
            '@type' => 'FAQPage',
            '@id' => $canonicalUrl . '#faq',
            'mainEntity' => [
                [
                    '@type' => 'Question',
                    'name' => "Welche genauen Maße und welches Material hat „{$this->name}“?",
                    'acceptedAnswer' => [
                        '@type' => 'Answer',
                        'text' => "„{$this->name}“ besteht aus {$materialText} und besitzt die exakten Abmessungen von {$dimText}. Ein detailliertes technisches Maßblatt befindet sich auf der letzten Position der Produktbild-Galerie.",
                    ],
                ],
                [
                    '@type' => 'Question',
                    'name' => "Ist die Lasergravur bei „{$this->name}“ im Preis inbegriffen?",
                    'acceptedAnswer' => [
                        '@type' => 'Answer',
                        'text' => 'Ja, die individuelle Personalisierung im interaktiven 2D-Editor und 3D-Echtzeit-Konfigurator (Wunschtext, Schriftart, Motiv oder eigenes Logo) ist im angezeigten Preis von ' . number_format($effectiveGrossCents / 100, 2, ',', '.') . ' € bereits vollständig enthalten.',
                    ],
                ],
                [
                    '@type' => 'Question',
                    'name' => 'Wie schnell erfolgt die Anfertigung und Lieferung?',
                    'acceptedAnswer' => [
                        '@type' => 'Answer',
                        'text' => $deliveryFaqAnswer,
                    ],
                ],
            ],
        ];

        return [
            '@context' => 'https://schema.org',
            '@graph' => [
                [
                    '@type' => 'BreadcrumbList',
                    '@id' => $canonicalUrl . '#breadcrumb',
                    'itemListElement' => [
                        [
                            '@type' => 'ListItem',
                            'position' => 1,
                            'name' => 'Startseite',
                            'item' => route('home'),
                        ],
                        [
                            '@type' => 'ListItem',
                            'position' => 2,
                            'name' => 'Shop',
                            'item' => route('shop'),
                        ],
                        [
                            '@type' => 'ListItem',
                            'position' => 3,
                            'name' => $this->name,
                            'item' => $canonicalUrl,
                        ],
                    ],
                ],
                $productNode,
                $faqNode,
            ],
        ];
    }
}
