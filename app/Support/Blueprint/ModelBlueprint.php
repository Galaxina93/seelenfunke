<?php

namespace App\Support\Blueprint;

use App\Support\Blueprint\Enums\ActorScope;
use App\Support\Blueprint\Enums\AiAccessPolicy;
use App\Support\Blueprint\Enums\ComplianceRegulation;
use App\Support\Blueprint\Enums\DataSensitivity;
use App\Support\Blueprint\Enums\ExternalChannel;
use App\Support\Blueprint\Enums\LifecycleBehavior;
use App\Support\Blueprint\Enums\RetentionPolicy;
use App\Support\Blueprint\Enums\SystemDepartment;

final readonly class ModelBlueprint
{
    /**
     * @param string $label Sprechender deutscher Name des Models im Projekt-Gehirn & VVT
     * @param string $description Fachliche Kurzbeschreibung des Zwecks im System
     * @param SystemDepartment $department Zugehörige Fachabteilung (synchron mit AiDepartment & Theming)
     * @param array<int, string> $navigationNodes Zugeordnete Routen- oder Navigationsknoten
     * @param LifecycleBehavior $lifecycle Zeitliches Verhalten & Festschreibungs-Charakter
     * @param DataSensitivity $sensitivity DSGVO-Datenkategorie
     * @param RetentionPolicy $retention Gesetzliche Aufbewahrungs- & Löschregel
     * @param AiAccessPolicy $aiAccess KI-Sicherheits-Leitplanke (Funki & Agenten)
     * @param array<int, ActorScope> $actorsRead Welche Akteure/Portale dieses Model lesen
     * @param array<int, ActorScope> $actorsWrite Welche Akteure/Portale dieses Model verändern
     * @param array<int, ComplianceRegulation> $regulations Greifende Gesetze & Verordnungen
     * @param array<int, ExternalChannel> $externalChannels Angeschlossene Hardware- & Drittanbieter-Kanäle
     * @param array<int, string> $dependsOnSettings Gesteuert durch shop_setting()-Keys
     * @param bool $isGdprArt15Exportable Muss beim 1-Klick-Kundenexport nach Art. 15 DSGVO enthalten sein
     * @param string|null $customerForeignKey Fremdschlüssel auf Customer (z. B. 'customer_id' oder 'id') für Art. 15 Export
     * @param bool $containsProductionAssets Besitzt temporäre Druck-/Laser-/Upload-Dateien für den 90-Tage-Cleaner
     * @param array<int, string> $assetColumns Spaltennamen mit Dateipfaden für das Asset-Pruning
     */
    public function __construct(
        public string $label,
        public string $description,
        public SystemDepartment $department,
        public array $navigationNodes = [],
        public LifecycleBehavior $lifecycle = LifecycleBehavior::TransactionalWorkflow,
        public DataSensitivity $sensitivity = DataSensitivity::InternalErp,
        public RetentionPolicy $retention = RetentionPolicy::PermanentMasterData,
        public AiAccessPolicy $aiAccess = AiAccessPolicy::ReadOnly,
        public array $actorsRead = [ActorScope::Admin],
        public array $actorsWrite = [ActorScope::Admin],
        public array $regulations = [],
        public array $externalChannels = [],
        public array $dependsOnSettings = [],
        public bool $isGdprArt15Exportable = false,
        public ?string $customerForeignKey = null,
        public bool $containsProductionAssets = false,
        public array $assetColumns = [],
    ) {
    }

    public static function make(
        string $label,
        string $description,
        SystemDepartment $department,
        array $navigationNodes = [],
        LifecycleBehavior $lifecycle = LifecycleBehavior::TransactionalWorkflow,
        DataSensitivity $sensitivity = DataSensitivity::InternalErp,
        RetentionPolicy $retention = RetentionPolicy::PermanentMasterData,
        AiAccessPolicy $aiAccess = AiAccessPolicy::ReadOnly,
        array $actorsRead = [ActorScope::Admin],
        array $actorsWrite = [ActorScope::Admin],
        array $regulations = [],
        array $externalChannels = [],
        array $dependsOnSettings = [],
        bool $isGdprArt15Exportable = false,
        ?string $customerForeignKey = null,
        bool $containsProductionAssets = false,
        array $assetColumns = [],
    ): self {
        return new self(
            label: $label,
            description: $description,
            department: $department,
            navigationNodes: $navigationNodes,
            lifecycle: $lifecycle,
            sensitivity: $sensitivity,
            retention: $retention,
            aiAccess: $aiAccess,
            actorsRead: $actorsRead,
            actorsWrite: $actorsWrite,
            regulations: $regulations,
            externalChannels: $externalChannels,
            dependsOnSettings: $dependsOnSettings,
            isGdprArt15Exportable: $isGdprArt15Exportable,
            customerForeignKey: $customerForeignKey,
            containsProductionAssets: $containsProductionAssets,
            assetColumns: $assetColumns,
        );
    }

    public function isGoBdProtected(): bool
    {
        return $this->retention === RetentionPolicy::GoBD_10Years
            || in_array(ComplianceRegulation::GoBD, $this->regulations, true);
    }

    public function toArray(): array
    {
        return [
            'label' => $this->label,
            'description' => $this->description,
            'department' => $this->department->value,
            'department_label' => $this->department->label(),
            'department_color' => $this->department->colorHex(),
            'navigation_nodes' => $this->navigationNodes,
            'lifecycle' => $this->lifecycle->value,
            'lifecycle_label' => $this->lifecycle->label(),
            'sensitivity' => $this->sensitivity->value,
            'sensitivity_label' => $this->sensitivity->label(),
            'sensitivity_badge' => $this->sensitivity->badgeColor(),
            'legal_basis' => $this->sensitivity->legalBasis(),
            'retention' => $this->retention->value,
            'retention_label' => $this->retention->label(),
            'retention_days' => $this->retention->days(),
            'is_gobd_protected' => $this->isGoBdProtected(),
            'ai_access' => $this->aiAccess->value,
            'ai_access_label' => $this->aiAccess->label(),
            'allows_ai_read' => $this->aiAccess->allowsAiRead(),
            'allows_ai_write' => $this->aiAccess->allowsAiWrite(),
            'actors_read' => array_map(fn (ActorScope $a) => $a->value, $this->actorsRead),
            'actors_read_labels' => array_map(fn (ActorScope $a) => $a->label(), $this->actorsRead),
            'actors_write' => array_map(fn (ActorScope $a) => $a->value, $this->actorsWrite),
            'actors_write_labels' => array_map(fn (ActorScope $a) => $a->label(), $this->actorsWrite),
            'regulations' => array_map(fn (ComplianceRegulation $r) => $r->value, $this->regulations),
            'regulation_labels' => array_map(fn (ComplianceRegulation $r) => $r->shortLabel(), $this->regulations),
            'external_channels' => array_map(fn (ExternalChannel $c) => $c->value, $this->externalChannels),
            'external_channel_labels' => array_map(fn (ExternalChannel $c) => $c->label(), $this->externalChannels),
            'depends_on_settings' => $this->dependsOnSettings,
            'is_gdpr_art15_exportable' => $this->isGdprArt15Exportable,
            'customer_foreign_key' => $this->customerForeignKey,
            'contains_production_assets' => $this->containsProductionAssets,
            'asset_columns' => $this->assetColumns,
        ];
    }
}
