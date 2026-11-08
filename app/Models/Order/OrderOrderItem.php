<?php

namespace App\Models\Order;


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
use App\Models\Product\Product;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OrderOrderItem extends Model implements HasSystemBlueprint
{
    
    use InteractsWithSystemBlueprint;

    /**
     * Digitales Architektur- & Compliance-Typenschild (Living Documentation).
     */
    public static function blueprint(): ModelBlueprint
    {
        return ModelBlueprint::make(
            label: 'Bestellposition & Laser-Gravurauftrag',
            description: 'Einzelposition einer Bestellung inklusive maßstabsgetreuer 2D/3D-Gravur-Konfiguration und Produktionsstatus.',
            department: SystemDepartment::Order,
            navigationNodes: ['admin.orders.index', 'admin.production.laser'],
            lifecycle: LifecycleBehavior::ImmutableAfterFinalized,
            sensitivity: DataSensitivity::PersonalPII,
            retention: RetentionPolicy::GoBD_10Years,
            aiAccess: AiAccessPolicy::ReadOnly,
            actorsRead: [ActorScope::Customer, ActorScope::EmployeeProduction, ActorScope::Admin],
            actorsWrite: [ActorScope::EmployeeProduction, ActorScope::Admin],
            regulations: [ComplianceRegulation::GoBD, ComplianceRegulation::DSGVO_Art15_Art30, ComplianceRegulation::BGB_312g_CustomGoods],
            externalChannels: [ExternalChannel::LaserMachine_SVG_PDF],
            dependsOnSettings: [],
            isGdprArt15Exportable: false,
            customerForeignKey: null,
            containsProductionAssets: true,
            assetColumns: ['configuration'],
        );
    }

use HasFactory, HasUuids;

    protected $table = 'order_order_items';

    protected $guarded = [];

    protected $casts = [
        'configuration' => 'array',
        'unit_price' => 'integer',
        'total_price' => 'integer',
        'is_completed' => 'boolean',
        'completed_quantity' => 'integer',
    ];

    public function order()
    {
        return $this->belongsTo(OrderOrder::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class)->withTrashed();
    }

    /**
     * Ermittelt den zum Kaufzeitpunkt gültigen Produktbild-Pfad (eingefrorenes 'purchased_image_path',
     * Variantenbild oder saisonales/Feiertags-Hauptbild zum Bestelldatum), damit sich Bestellbilder
     * bei späterem Jahreszeiten-/Feiertagswechsel niemals rückwirkend verändern.
     */
    public function resolveDisplayImagePath(): ?string
    {
        $conf = is_array($this->configuration) ? $this->configuration : [];

        if (!empty($conf['purchased_image_path']) && is_string($conf['purchased_image_path'])) {
            return $conf['purchased_image_path'];
        }

        if ($this->product) {
            $purchaseDate = ($this->relationLoaded('order') && $this->order && $this->order->created_at)
                ? $this->order->created_at
                : $this->created_at;

            return $this->product->resolveConfigurationImagePath($conf, $purchaseDate);
        }

        return null;
    }

    /**
     * Ermittelt die fertige öffentliche Produktbild-URL zum Kaufzeitpunkt.
     */
    public function resolveDisplayImageUrl(): ?string
    {
        $path = $this->resolveDisplayImagePath();
        return $path ? \App\Models\Product\ProductTemplate::resolveImageUrl($path) : null;
    }

    /**
     * Liefert alle digitalen Begleitdokumente (z. B. Echtheitszertifikat, Pflegehinweise) zu dieser Bestellposition.
     * Kombiniert den Kaufzeitpunkt-Snapshot aus configuration['digital_documents'] mit den aktuell am Produkt
     * hinterlegten Dokumenten (dedupliziert nach ID und Dateipfad).
     *
     * @return array<int, array{id: string, title: string, path: string, filename: string, extension: string, size_bytes: int, uploaded_at: string}>
     */
    public function resolveDigitalDocuments(): array
    {
        $conf = is_array($this->configuration) ? $this->configuration : [];
        $snapshotDocs = is_array($conf['digital_documents'] ?? null) ? $conf['digital_documents'] : [];
        $productDocs = ($this->product && method_exists($this->product, 'getDigitalDocuments'))
            ? $this->product->getDigitalDocuments()
            : [];

        $mergedById = [];
        $pathToId = [];

        foreach (array_values($snapshotDocs) as $idx => $rawDoc) {
            if (!is_array($rawDoc)) {
                continue;
            }
            $norm = Product::normalizeDigitalDocumentItem($rawDoc, $idx);
            if (!$norm) {
                continue;
            }
            $mergedById[$norm['id']] = $norm;
            $pathToId[$norm['path']] = $norm['id'];
        }

        foreach (array_values($productDocs) as $idx => $rawDoc) {
            if (!is_array($rawDoc)) {
                continue;
            }
            $norm = Product::normalizeDigitalDocumentItem($rawDoc, $idx);
            if (!$norm) {
                continue;
            }
            if (isset($pathToId[$norm['path']])) {
                $existingId = $pathToId[$norm['path']];
                $mergedById[$existingId] = array_merge($mergedById[$existingId], $norm, ['id' => $existingId]);
            } else {
                $mergedById[$norm['id']] = $norm;
                $pathToId[$norm['path']] = $norm['id'];
            }
        }

        return array_values($mergedById);
    }

    /**
     * Prüft, ob dieser Bestellposition digitale Begleitdokumente zugeordnet sind.
     */
    public function hasDigitalDocuments(): bool
    {
        return count($this->resolveDigitalDocuments()) > 0;
    }

    /**
     * Liefert die zum Kaufzeitpunkt gespeicherten Beschaffungsdaten für Rohlinge (Auftragsfertigung bei Bestand 0).
     */
    public function getSupplierProcurementData(): ?array
    {
        $conf = is_array($this->configuration) ? $this->configuration : [];
        return is_array($conf['supplier_procurement'] ?? null) ? $conf['supplier_procurement'] : null;
    }

    /**
     * Prüft, ob für diese Bestellposition eine Rohling-Nachbestellung beim Lieferanten erforderlich ist.
     */
    public function requiresSupplierProcurement(): bool
    {
        $data = $this->getSupplierProcurementData();
        return !empty($data['required']);
    }

    /**
     * Prüft, ob der Rohling für diese Bestellposition bereits beim Lieferanten bestellt wurde.
     */
    public function isSupplierOrderPlaced(): bool
    {
        $data = $this->getSupplierProcurementData();
        return !empty($data['ordered_from_supplier']);
    }

    /**
     * Liefert die fehlende Rohling-Stückzahl, die beim Lieferanten nachbestellt werden muss.
     */
    public function getMissingSupplierQuantity(): int
    {
        $data = $this->getSupplierProcurementData();
        return max(1, (int) ($data['missing_quantity'] ?? $this->quantity ?? 1));
    }

    /**
     * Liefert den Direkt-Kauflink zum Rohling beim Lieferanten.
     */
    public function getSupplierReorderUrl(): ?string
    {
        $data = $this->getSupplierProcurementData();
        $url = trim((string) ($data['reorder_url'] ?? ''));
        if ($url !== '') {
            return $url;
        }

        if ($this->product) {
            if (!empty($this->product->reorder_url)) {
                return (string) $this->product->reorder_url;
            }
            $supplier = $this->product->relationLoaded('supplier')
                ? $this->product->supplier
                : (!empty($this->product->product_supplier_id) ? $this->product->supplier()->first() : null);
            if (!empty($supplier?->website)) {
                return (string) $supplier->website;
            }
        }

        return null;
    }

    /**
     * Liefert den Namen des Lieferanten für die Rohling-Beschaffung.
     */
    public function getSupplierName(): ?string
    {
        $data = $this->getSupplierProcurementData();
        $name = trim((string) ($data['supplier_name'] ?? ''));
        if ($name !== '') {
            return $name;
        }

        if ($this->product) {
            $supplier = $this->product->relationLoaded('supplier')
                ? $this->product->supplier
                : (!empty($this->product->product_supplier_id) ? $this->product->supplier()->first() : null);
            if (!empty($supplier?->name)) {
                return (string) $supplier->name;
            }
        }

        return null;
    }

    /**
     * Markiert die Rohling-Nachbestellung beim Lieferanten als erledigt oder offen.
     */
    public function markSupplierOrdered(bool $ordered = true): void
    {
        $conf = is_array($this->configuration) ? $this->configuration : [];
        $procurement = is_array($conf['supplier_procurement'] ?? null)
            ? $conf['supplier_procurement']
            : ['required' => true, 'missing_quantity' => max(1, (int) $this->quantity)];

        $procurement['ordered_from_supplier'] = $ordered;
        $procurement['ordered_at'] = $ordered ? now()->format('d.m.Y H:i') : null;
        $conf['supplier_procurement'] = $procurement;

        $this->configuration = $conf;
        $this->save();
    }
}
