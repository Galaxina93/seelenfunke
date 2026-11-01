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

class MailAttachment extends Model implements HasSystemBlueprint
{
    
    use InteractsWithSystemBlueprint;

    /**
     * Digitales Architektur- & Compliance-Typenschild (Living Documentation).
     */
    public static function blueprint(): ModelBlueprint
    {
        return ModelBlueprint::make(
            label: 'E-Mail-Dateianhang',
            description: 'Empfangene und gesendete Dateianhänge der geschäftlichen E-Mail-Korrespondenz.',
            department: SystemDepartment::Management,
            navigationNodes: ['admin.management.mail'],
            lifecycle: LifecycleBehavior::ImmutableAfterFinalized,
            sensitivity: DataSensitivity::PersonalPII,
            retention: RetentionPolicy::TaxBusinessLetters_6Years,
            aiAccess: AiAccessPolicy::ReadOnly,
            actorsRead: [ActorScope::Admin],
            actorsWrite: [ActorScope::Admin, ActorScope::SystemCron],
            regulations: [ComplianceRegulation::GoBD, ComplianceRegulation::DSGVO_Art15_Art30],
            externalChannels: [ExternalChannel::IMAP_SMTP_Mail],
            dependsOnSettings: [],
            isGdprArt15Exportable: false,
            customerForeignKey: null,
            containsProductionAssets: false,
            assetColumns: [],
        );
    }

protected $guarded = [];

    protected static function booted(): void
    {
        static::deleting(function (MailAttachment $attachment) {
            if (!empty($attachment->path) && !str_contains((string) $attachment->path, '..') && \Illuminate\Support\Facades\Storage::disk('local')->exists($attachment->path)) {
                \Illuminate\Support\Facades\Storage::disk('local')->delete($attachment->path);
            }
        });
    }

    public function message()
    {
        return $this->belongsTo(MailMessage::class, 'mail_message_id');
    }
    
    /**
     * Get the URL to stream the attachment.
     */
    public function getStreamUrlAttribute()
    {
        return route('crm.mail-attachment', ['id' => $this->id]);
    }
}
