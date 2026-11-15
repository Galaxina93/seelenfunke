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

    /**
     * Ermittelt die strukturierte Manufaktur- & Konfigurator-Checkliste für diese Bestellposition:
     * 1. Besondere Wünsche an die Manufaktur (notes)
     * 2. Geschenkpapier: Ja/Nein + Modus (Fertig verpacken vs. Nur als Bogen dazu legen)
     * 3. Edle Schmuckbox: Ja/Nein
     * 4. Laser-Gravur / Fertigung
     */
    public function resolveManufacturingChecklist(): array
    {
        $conf = is_array($this->configuration) ? $this->configuration : [];
        $checklistState = is_array($conf['manufacturing_checklist'] ?? null) ? $conf['manufacturing_checklist'] : [];

        // 1. Besondere Wünsche an die Manufaktur
        $itemNote = trim((string) ($conf['notes'] ?? ''));
        $orderNote = ($this->relationLoaded('order') && $this->order)
            ? trim((string) ($this->order->notes ?? ''))
            : '';

        if ($itemNote !== '' && $orderNote !== '' && strcasecmp($itemNote, $orderNote) !== 0) {
            $combinedNote = $itemNote . ' (Bestellnotiz: ' . $orderNote . ')';
        } else {
            $combinedNote = $itemNote !== '' ? $itemNote : $orderNote;
        }
        $hasNote = $combinedNote !== '';
        $notesChecked = $hasNote
            ? (bool) ($checklistState['notes'] ?? $this->is_completed)
            : true;

        // 2. Geschenkpapier (Ja/Nein + Wie: Verpacken oder nur dazu legen)
        $selectedAddons = is_array($conf['selected_addons'] ?? null) ? $conf['selected_addons'] : [];
        $addonsSummary = is_array($conf['addons_summary'] ?? null) ? $conf['addons_summary'] : [];

        $giftWrapSelected = in_array('gift_wrap_black_gold', $selectedAddons, true);
        $matchedGiftWrapAddon = null;
        foreach ($addonsSummary as $addon) {
            if (is_array($addon) && Product::isGiftWrapAddon($addon)) {
                $giftWrapSelected = true;
                $matchedGiftWrapAddon = $addon;
                break;
            }
        }

        $giftWrapMode = 'none';
        $giftWrapShortLabel = 'Nein (Kein Geschenkpapier)';
        $giftWrapBadgeLabel = 'Geschenkpapier: Nein';
        $giftWrapInstruction = 'Kein Geschenkpapier ausgewählt.';

        if ($giftWrapSelected) {
            $rawWrapMode = strtolower(trim((string) ($conf['gift_wrap_mode'] ?? ($matchedGiftWrapAddon['wrap_mode'] ?? ''))));
            $addonNameLower = mb_strtolower((string) ($matchedGiftWrapAddon['name'] ?? ''));

            if ($rawWrapMode === 'loose' || str_contains($addonNameLower, 'mitliefern') || str_contains($addonNameLower, 'dazu legen') || str_contains($addonNameLower, 'bogen')) {
                $giftWrapMode = 'loose';
                $giftWrapShortLabel = 'Ja (Nur als Bogen dazu legen)';
                $giftWrapBadgeLabel = 'Geschenkpapier: Nur dazu legen';
                $giftWrapInstruction = 'Schwarz-Gold Geschenkpapier NICHT verpacken, sondern sauber als Bogen zum Selbstverpacken beilegen.';
            } else {
                $giftWrapMode = 'wrapped';
                $giftWrapShortLabel = 'Ja (Fertig als Geschenk verpacken)';
                $giftWrapBadgeLabel = 'Geschenkpapier: Fertig verpacken';
                $giftWrapInstruction = 'Artikel direkt in das Seelenfunke Schwarz-Gold Geschenkpapier fertig einpacken.';
            }
        }

        $giftWrapChecked = $giftWrapSelected
            ? (bool) ($checklistState['gift_wrap'] ?? $this->is_completed)
            : true;

        // 3. Edle Schmuckbox (Ja oder Nein)
        $giftBoxSelected = in_array('gift_box_westpack', $selectedAddons, true);
        $matchedGiftBoxAddon = null;
        foreach ($addonsSummary as $addon) {
            if (!is_array($addon)) {
                continue;
            }
            $addonId = strtolower(trim((string) ($addon['id'] ?? '')));
            $addonName = mb_strtolower(trim((string) ($addon['name'] ?? '')));
            if (
                $addonId === 'gift_box_westpack'
                || str_contains($addonName, 'schmuckbox')
                || str_contains($addonName, 'geschenkbox')
                || str_contains($addonName, 'schmuckschachtel')
                || str_contains($addonName, 'westpack')
                || str_contains($addonName, 'edle box')
            ) {
                $giftBoxSelected = true;
                $matchedGiftBoxAddon = $addon;
                break;
            }
        }

        $giftBoxShortLabel = $giftBoxSelected
            ? 'Ja (Edle Schmuckbox Schwarz-Gold)'
            : 'Nein (Ohne Schmuckbox)';
        $giftBoxBadgeLabel = $giftBoxSelected
            ? 'Edle Schmuckbox: Ja'
            : 'Edle Schmuckbox: Nein';
        $giftBoxInstruction = $giftBoxSelected
            ? 'Artikel in die schwarze Westpack-Schmuckbox mit Goldprägung und Schaumstoffeinlage legen.'
            : 'Keine Schmuckbox ausgewählt (Standard-Versandpolsterung).';

        $giftBoxChecked = $giftBoxSelected
            ? (bool) ($checklistState['gift_box'] ?? $this->is_completed)
            : true;

        // 4. Laser-Gravur / Fertigung
        $engravingChecked = (bool) ($checklistState['engraving'] ?? $this->is_completed);

        $openActionCount = 0;
        if (!$engravingChecked) {
            $openActionCount++;
        }
        if ($hasNote && !$notesChecked) {
            $openActionCount++;
        }
        if ($giftWrapSelected && !$giftWrapChecked) {
            $openActionCount++;
        }
        if ($giftBoxSelected && !$giftBoxChecked) {
            $openActionCount++;
        }

        return [
            'notes' => [
                'has_note' => $hasNote,
                'text' => $combinedNote,
                'item_note' => $itemNote,
                'order_note' => $orderNote,
                'short_label' => $hasNote ? 'Ja (Kundenwunsch liegt vor)' : 'Nein (Keine besonderen Wünsche)',
                'badge_label' => $hasNote ? 'Manufaktur-Wunsch beachten' : 'Kein Sonderwunsch',
                'instruction' => $hasNote
                    ? 'Kundenwunsch an die Manufaktur: "' . $combinedNote . '"'
                    : 'Keine besonderen Wünsche an die Manufaktur angegeben.',
                'checked' => $notesChecked,
                'required' => $hasNote,
            ],
            'gift_wrap' => [
                'selected' => $giftWrapSelected,
                'mode' => $giftWrapMode, // 'wrapped' | 'loose' | 'none'
                'short_label' => $giftWrapShortLabel,
                'badge_label' => $giftWrapBadgeLabel,
                'instruction' => $giftWrapInstruction,
                'addon_name' => $matchedGiftWrapAddon['name'] ?? null,
                'checked' => $giftWrapChecked,
                'required' => $giftWrapSelected,
            ],
            'gift_box' => [
                'selected' => $giftBoxSelected,
                'short_label' => $giftBoxShortLabel,
                'badge_label' => $giftBoxBadgeLabel,
                'instruction' => $giftBoxInstruction,
                'addon_name' => $matchedGiftBoxAddon['name'] ?? null,
                'checked' => $giftBoxChecked,
                'required' => $giftBoxSelected,
            ],
            'engraving' => [
                'checked' => $engravingChecked,
                'quantity' => max(1, (int) $this->quantity),
                'completed_quantity' => (int) ($this->completed_quantity ?? ($this->is_completed ? $this->quantity : 0)),
                'required' => true,
            ],
            'all_checked' => $openActionCount === 0 && (bool) $this->is_completed,
            'open_action_count' => $openActionCount,
        ];
    }

    /**
     * Schaltet einen einzelnen Checklisten-Punkt ('notes', 'gift_wrap', 'gift_box', 'engraving')
     * für diese Bestellposition um und synchronisiert is_completed automatisch.
     */
    public function toggleChecklistTask(string $taskKey, ?bool $forceState = null): array
    {
        $allowedKeys = ['notes', 'gift_wrap', 'gift_box', 'engraving'];
        if (!in_array($taskKey, $allowedKeys, true)) {
            return $this->resolveManufacturingChecklist();
        }

        $conf = is_array($this->configuration) ? $this->configuration : [];
        $currentChecklist = $this->resolveManufacturingChecklist();
        $checklistState = is_array($conf['manufacturing_checklist'] ?? null) ? $conf['manufacturing_checklist'] : [];

        // Initialisiere alle aktuellen Stände
        foreach ($allowedKeys as $key) {
            if (!array_key_exists($key, $checklistState)) {
                $checklistState[$key] = (bool) ($currentChecklist[$key]['checked'] ?? false);
            }
        }

        $newState = $forceState !== null ? $forceState : !((bool) ($checklistState[$taskKey] ?? false));
        $checklistState[$taskKey] = $newState;
        $conf['manufacturing_checklist'] = $checklistState;
        $this->configuration = $conf;

        // Re-evaluieren, ob jetzt alle relevanten Pflichtpunkte für diese Position erledigt sind
        $updated = $this->resolveManufacturingChecklist();
        $allRequiredDone = $updated['engraving']['checked']
            && (!$updated['notes']['required'] || $updated['notes']['checked'])
            && (!$updated['gift_wrap']['required'] || $updated['gift_wrap']['checked'])
            && (!$updated['gift_box']['required'] || $updated['gift_box']['checked']);

        if ($taskKey === 'engraving' && $newState) {
            $this->completed_quantity = max(1, (int) $this->quantity);
        } elseif ($taskKey === 'engraving' && !$newState) {
            $this->completed_quantity = 0;
        }

        if ($allRequiredDone) {
            $this->is_completed = true;
            $this->completed_quantity = max(1, (int) $this->quantity);
        } else {
            $this->is_completed = false;
        }

        $this->save();

        return $this->resolveManufacturingChecklist();
    }

    /**
     * Markiert alle Pflichtpunkte der Manufaktur-Checkliste dieser Position geschlossen als erledigt oder offen.
     */
    public function markAllChecklistTasks(bool $completed = true): void
    {
        $conf = is_array($this->configuration) ? $this->configuration : [];
        $conf['manufacturing_checklist'] = [
            'engraving' => $completed,
            'notes' => $completed,
            'gift_wrap' => $completed,
            'gift_box' => $completed,
        ];
        $this->configuration = $conf;
        $this->is_completed = $completed;
        $this->completed_quantity = $completed ? max(1, (int) $this->quantity) : 0;
        $this->save();
    }
}
