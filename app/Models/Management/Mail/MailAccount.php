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
        'is_default' => 'boolean',
        'is_commercial' => 'boolean',
        'last_sync_at' => 'datetime',
    ];

    protected function password(): \Illuminate\Database\Eloquent\Casts\Attribute
    {
        return \Illuminate\Database\Eloquent\Casts\Attribute::make(
            get: function (?string $value): string {
                if ($value === null || $value === '') {
                    return '';
                }
                try {
                    return \Illuminate\Support\Facades\Crypt::decryptString($value);
                } catch (\Throwable) {
                    if (str_starts_with($value, 'eyJpdiI6')) {
                        return '';
                    }
                    // Fallback for legacy plaintext values before re-encryption
                    return $value;
                }
            },
            set: function (?string $value): string {
                if ($value === null || $value === '') {
                    return '';
                }
                try {
                    // Avoid double-encrypting if already encrypted
                    \Illuminate\Support\Facades\Crypt::decryptString($value);
                    return $value;
                } catch (\Throwable) {
                    return \Illuminate\Support\Facades\Crypt::encryptString($value);
                }
            }
        );
    }

    protected static function booted(): void
    {
        static::saved(function (MailAccount $account) {
            if (!app()->runningUnitTests()) {
                $account->syncToEncryptedVault();
            }
        });

        static::deleting(function (MailAccount $account) {
            foreach ($account->messages()->get() as $message) {
                $message->delete();
            }
        });
    }

    public function syncToEncryptedVault(): void
    {
        $plainPw = trim((string) $this->password);
        $emailKey = strtolower(trim((string) $this->email));
        if ($plainPw === '' || $emailKey === '') {
            return;
        }

        try {
            $vaultPath = storage_path('app/leitung/mail/mail_accounts_vault.json');
            if (!is_dir(dirname($vaultPath))) {
                @mkdir(dirname($vaultPath), 0775, true);
            }
            $vaultMap = file_exists($vaultPath)
                ? (json_decode((string) file_get_contents($vaultPath), true) ?: [])
                : [];
            $vaultMap[$emailKey] = \Illuminate\Support\Facades\Crypt::encryptString(json_encode([
                'name' => $this->name,
                'email' => $this->email,
                'imap_host' => $this->imap_host,
                'imap_port' => (int) $this->imap_port,
                'imap_encryption' => $this->imap_encryption,
                'imap_username' => $this->imap_username ?: $this->email,
                'smtp_host' => $this->smtp_host,
                'smtp_port' => (int) $this->smtp_port,
                'smtp_encryption' => $this->smtp_encryption,
                'smtp_username' => $this->smtp_username ?: $this->email,
                'signature' => $this->signature,
                'is_default' => (bool) $this->is_default,
                'is_commercial' => (bool) $this->is_commercial,
                'password' => $plainPw,
            ], JSON_UNESCAPED_UNICODE));
            @file_put_contents($vaultPath, json_encode($vaultMap, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), LOCK_EX);
            @chmod($vaultPath, 0600);
        } catch (\Throwable) {
            // Ignore filesystem write issues so account saving never breaks
        }
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
