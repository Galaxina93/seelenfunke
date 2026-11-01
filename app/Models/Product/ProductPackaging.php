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
use Illuminate\Database\Eloquent\Model;

class ProductPackaging extends Model implements HasSystemBlueprint
{
    
    use InteractsWithSystemBlueprint;

    /**
     * Digitales Architektur- & Compliance-Typenschild (Living Documentation).
     */
    public static function blueprint(): ModelBlueprint
    {
        return ModelBlueprint::make(
            label: 'Produkt-Verpackungsanteil (LUCID VerpackG)',
            description: 'Grammgenaue Aufschlüsselung der Verpackungsmaterialien (Papier, Kunststoff, Verbund, Glas) je Produkt für die LUCID-Jahresmeldung.',
            department: SystemDepartment::Product,
            navigationNodes: ['admin.product-packaging'],
            lifecycle: LifecycleBehavior::MasterData,
            sensitivity: DataSensitivity::InternalErp,
            retention: RetentionPolicy::GoBD_10Years,
            aiAccess: AiAccessPolicy::ReadAndPropose,
            actorsRead: [ActorScope::Admin, ActorScope::EmployeeProduction],
            actorsWrite: [ActorScope::Admin],
            regulations: [ComplianceRegulation::VerpackG_LUCID, ComplianceRegulation::GoBD],
            externalChannels: [ExternalChannel::LUCID_VerpackG],
            dependsOnSettings: [],
            isGdprArt15Exportable: false,
            customerForeignKey: null,
            containsProductionAssets: false,
            assetColumns: [],
        );
    }

protected $guarded = [];

    protected $casts = [
        'weight_grams' => 'integer',
    ];

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public static function getMaterialTypes()
    {
        return [
            'paper' => 'Pappe, Papier & Karton',
            'plastic' => 'Kunststoffe',
            'glass' => 'Glas',
            'wood' => 'Holz',
            'tin' => 'Weißblech',
            'alu' => 'Aluminium',
            'composite' => 'Verbundmaterialien',
            'other' => 'Sonstige (Naturmaterialien)',
        ];
    }
}
