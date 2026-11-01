<?php

// app/Models/Directory.php
namespace App\Models\System;


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
use App\Models\Admin\Admin;
use App\Models\Customer\Customer;
use App\Models\Employee\Employee;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphToMany;

class SystemDirectory extends Model implements HasSystemBlueprint
{
    
    use InteractsWithSystemBlueprint;

    /**
     * Digitales Architektur- & Compliance-Typenschild (Living Documentation).
     */
    public static function blueprint(): ModelBlueprint
    {
        return ModelBlueprint::make(
            label: 'Geschütztes Systemverzeichnis',
            description: 'Zugriffsgeschützte Ordner- und Berechtigungsstruktur für interne Dokumente.',
            department: SystemDepartment::System,
            navigationNodes: ['admin.system.directories'],
            lifecycle: LifecycleBehavior::MasterData,
            sensitivity: DataSensitivity::InternalErp,
            retention: RetentionPolicy::PermanentMasterData,
            aiAccess: AiAccessPolicy::ReadOnly,
            actorsRead: [ActorScope::Admin],
            actorsWrite: [ActorScope::Admin],
            regulations: [],
            externalChannels: [],
            dependsOnSettings: [],
            isGdprArt15Exportable: false,
            customerForeignKey: null,
            containsProductionAssets: false,
            assetColumns: [],
        );
    }

use HasFactory;

    protected $table = 'system_directories';

    protected $fillable = ['name', 'path'];

    /**
     * Definiert die polymorphe Many-to-Many-Beziehung zu den Admin-Benutzern.
     */
    public function admins(): MorphToMany
    {
        return $this->morphedByMany(Admin::class, 'user', 'directory_user', 'directory_id', 'user_id');
    }

    /**
     * Definiert die polymorphe Many-to-Many-Beziehung zu den Employee-Benutzern.
     */
    public function employees(): MorphToMany
    {
        return $this->morphedByMany(Employee::class, 'user', 'directory_user', 'directory_id', 'user_id');
    }

    /**
     * Definiert die polymorphe Many-to-Many-Beziehung zu den Customer-Benutzern.
     */
    public function customers(): MorphToMany
    {
        return $this->morphedByMany(Customer::class, 'user', 'directory_user', 'directory_id', 'user_id');
    }
}
