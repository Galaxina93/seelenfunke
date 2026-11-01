<?php

namespace App\Models\Management\Mail;


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

class MailAccount extends Model implements HasSystemBlueprint
{
    
    use InteractsWithSystemBlueprint;

    /**
     * Digitales Architektur- & Compliance-Typenschild (Living Documentation).
     */
    public static function blueprint(): ModelBlueprint
    {
        return ModelBlueprint::make(
            label: 'E-Mail-Serverkonto (IMAP/SMTP)',
            description: 'Zugangsdaten und Verschlüsselungsparameter der angebundenen Geschäftspostfächer.',
            department: SystemDepartment::Management,
            navigationNodes: ['admin.management.mail'],
            lifecycle: LifecycleBehavior::MasterData,
            sensitivity: DataSensitivity::FinancialConfidential,
            retention: RetentionPolicy::PermanentMasterData,
            aiAccess: AiAccessPolicy::None,
            actorsRead: [ActorScope::Admin],
            actorsWrite: [ActorScope::Admin],
            regulations: [ComplianceRegulation::DSGVO_Art15_Art30],
            externalChannels: [ExternalChannel::IMAP_SMTP_Mail],
            dependsOnSettings: [],
            isGdprArt15Exportable: false,
            customerForeignKey: null,
            containsProductionAssets: false,
            assetColumns: [],
        );
    }

protected $fillable = [
        'name',
        'email',
        'password',
        'imap_host',
        'imap_port',
        'imap_encryption',
        'imap_username',
        'smtp_host',
        'smtp_port',
        'smtp_encryption',
        'smtp_username',
        'signature',
        'is_default',
        'is_commercial',
        'status',
        'last_sync_at',
    ];

    protected $hidden = [
        'password',
        'imap_password',
        'smtp_password',
        'access_token',
        'refresh_token',
    ];

    protected $casts = [
        'password' => 'encrypted',
        'is_default' => 'boolean',
        'is_commercial' => 'boolean',
        'last_sync_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::deleting(function (MailAccount $account) {
            foreach ($account->messages()->get() as $message) {
                $message->delete();
            }
        });
    }

    public function messages()
    {
        return $this->hasMany(MailMessage::class);
    }

    public function rules()
    {
        return $this->hasMany(MailRule::class);
    }

    public function folders()
    {
        return $this->hasMany(MailFolder::class);
    }
}
