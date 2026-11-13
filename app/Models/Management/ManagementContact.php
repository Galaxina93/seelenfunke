<?php

namespace App\Models\Management;


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
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class ManagementContact extends Model implements HasSystemBlueprint
{
    
    use InteractsWithSystemBlueprint;

    /**
     * Digitales Architektur- & Compliance-Typenschild (Living Documentation).
     */
    public static function blueprint(): ModelBlueprint
    {
        return ModelBlueprint::make(
            label: 'Geschäftskontakt & Adressbucheintrag',
            description: 'Kontaktdaten von Lieferanten, Dienstleistern, Steuerberatern und Kooperationspartnern.',
            department: SystemDepartment::Management,
            navigationNodes: ['admin.management.contacts'],
            lifecycle: LifecycleBehavior::MasterData,
            sensitivity: DataSensitivity::PersonalPII,
            retention: RetentionPolicy::StatutoryWarranty_3Years,
            aiAccess: AiAccessPolicy::AutonomousReadWrite,
            actorsRead: [ActorScope::Admin],
            actorsWrite: [ActorScope::Admin],
            regulations: [ComplianceRegulation::DSGVO_Art15_Art30],
            externalChannels: [],
            dependsOnSettings: [],
            isGdprArt15Exportable: false,
            customerForeignKey: null,
            containsProductionAssets: false,
            assetColumns: [],
        );
    }

use HasFactory, HasUuids;

    protected $table = 'management_contacts';

    protected $fillable = [
        'is_favorite',
        'funkira_call_whitelist',
        'first_name',
        'last_name',
        'nickname',
        'relation_type',
        'avatar_path',
        'links',
        'birthday',
        'email',
        'phone',
        'system_instructions',
        'ai_learned_facts',
        'street',
        'postal_code',
        'city',
        'country'
    ];

    protected $casts = [
        'birthday' => 'date',
        'links' => 'array',
        'is_favorite' => 'boolean',
        'funkira_call_whitelist' => 'boolean'
    ];

    /**
     * Get the full name of the person.
     */
    public function getFullNameAttribute()
    {
        return trim("{$this->first_name} {$this->last_name}");
    }

    protected static function booted(): void
    {
        static::deleting(function (ManagementContact $contact) {
            if (!empty($contact->avatar_path) && !str_contains((string) $contact->avatar_path, '..') && \Illuminate\Support\Facades\Storage::disk('public')->exists($contact->avatar_path)) {
                \Illuminate\Support\Facades\Storage::disk('public')->delete($contact->avatar_path);
            }

            if ($contact->id) {
                ManagementCalendarEvent::where('ics_uid', 'contact_birthday_' . $contact->id)->delete();
            }
        });
    }
}
