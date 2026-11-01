<?php

namespace App\Models\Ai;


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
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AiAgent extends Model implements HasSystemBlueprint
{
    
    use InteractsWithSystemBlueprint;

    /**
     * Digitales Architektur- & Compliance-Typenschild (Living Documentation).
     */
    public static function blueprint(): ModelBlueprint
    {
        return ModelBlueprint::make(
            label: 'KI-Agent (Funki & Fachabteilungs-KI)',
            description: 'Definition der autonomen KI-Agenten inklusive System-Prompt, Modellwahl und Abteilungszuordnung.',
            department: SystemDepartment::Ai,
            navigationNodes: ['admin.ai.agents'],
            lifecycle: LifecycleBehavior::MasterData,
            sensitivity: DataSensitivity::InternalErp,
            retention: RetentionPolicy::PermanentMasterData,
            aiAccess: AiAccessPolicy::ReadOnly,
            actorsRead: [ActorScope::Admin, ActorScope::EmployeeProduction],
            actorsWrite: [ActorScope::Admin],
            regulations: [ComplianceRegulation::EU_AI_Act_Art50],
            externalChannels: [ExternalChannel::GoogleGemini_AI],
            dependsOnSettings: [],
            isGdprArt15Exportable: false,
            customerForeignKey: null,
            containsProductionAssets: false,
            assetColumns: [],
        );
    }

use HasFactory, HasUuids;

    protected $fillable = [
        'ai_role_id',
        'ai_department_id',
        'name',
        'wake_word',
        'role_description',
        'system_prompt',
        'model',
        'temperature',
        'is_active',
        'is_in_chat',
        'color',
        'icon',
        'profile_picture',
        'tts_enabled',
        'tts_provider',
        'tts_voice',
        'tts_api_url',
        'tts_speed',
        'telegram_bot_token',
        'telegram_allowed_chat_ids',
    ];

    protected $hidden = [
        'telegram_bot_token',
        'telegram_allowed_chat_ids',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'is_in_chat' => 'boolean',
        'tts_enabled' => 'boolean',
        'telegram_allowed_chat_ids' => 'array',
    ];

    public function department()
    {
        return $this->belongsTo(AiDepartment::class, 'ai_department_id');
    }

    public function role()
    {
        return $this->belongsTo(AiRole::class, 'ai_role_id');
    }

    protected $appends = [
        'profile_picture_url',
    ];

    public function getProfilePictureUrlAttribute()
    {
        if (!$this->profile_picture) {
            return null;
        }
        if (\Illuminate\Support\Str::startsWith($this->profile_picture, 'http://') || \Illuminate\Support\Str::startsWith($this->profile_picture, 'https://')) {
            return $this->profile_picture;
        }
        if (\Illuminate\Support\Str::startsWith($this->profile_picture, 'shop/') || \Illuminate\Support\Str::startsWith($this->profile_picture, 'shopverwaltung/images/') || \Illuminate\Support\Str::startsWith($this->profile_picture, '/')) {
            return asset($this->profile_picture);
        }
        return \Illuminate\Support\Facades\Storage::url($this->profile_picture);
    }

    // Accessor for backward compatibility and role inheritance
    public function getToolsAttribute($value = null)
    {
        if ($this->relationLoaded('tools')) {
            $rel = $this->getRelation('tools');
            if ($rel instanceof \Illuminate\Support\Collection) {
                return $rel;
            }
        }
        if ($value instanceof \Illuminate\Support\Collection) {
            return $value;
        }
        return $this->role ? $this->role->tools : collect();
    }
}
