<?php

namespace App\Models\Marketing;


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

class MarketingNewsletterSubscriber extends Model implements HasSystemBlueprint
{
    
    use InteractsWithSystemBlueprint;

    /**
     * Digitales Architektur- & Compliance-Typenschild (Living Documentation).
     */
    public static function blueprint(): ModelBlueprint
    {
        return ModelBlueprint::make(
            label: 'Newsletter-Abonnent (Double-Opt-In)',
            description: 'Newsletter-Empfänger inklusive Zeitstempel des Double-Opt-In-Nachweises nach Art. 7 DSGVO.',
            department: SystemDepartment::Marketing,
            navigationNodes: ['admin.marketing.newsletter'],
            lifecycle: LifecycleBehavior::TransactionalWorkflow,
            sensitivity: DataSensitivity::PersonalPII,
            retention: RetentionPolicy::UntilConsentRevoked,
            aiAccess: AiAccessPolicy::ReadOnly,
            actorsRead: [ActorScope::Admin],
            actorsWrite: [ActorScope::PublicVisitor, ActorScope::Customer, ActorScope::Admin],
            regulations: [ComplianceRegulation::DSGVO_Art15_Art30],
            externalChannels: [ExternalChannel::IMAP_SMTP_Mail],
            dependsOnSettings: [],
            isGdprArt15Exportable: false,
            customerForeignKey: null,
            containsProductionAssets: false,
            assetColumns: [],
        );
    }

use HasFactory, HasUuids;

    protected $table = 'marketing_newsletter_subscribers';

    protected $fillable = [
        'email',
        'ip_address',
        'privacy_accepted',
        'is_verified',
        'verification_token',
        'verified_at',
        'receive_newsletter',
        'receive_birthday_emails',
        'receive_anniversary_emails',
    ];

    protected $hidden = [
        'verification_token',
    ];

    protected $casts = [
        'privacy_accepted' => 'boolean',
        'is_verified' => 'boolean',
        'subscribed_at' => 'datetime',
        'verified_at' => 'datetime',
        'receive_newsletter' => 'boolean',
        'receive_birthday_emails' => 'boolean',
        'receive_anniversary_emails' => 'boolean',
    ];
}
