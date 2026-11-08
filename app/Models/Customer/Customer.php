<?php

namespace App\Models\Customer;


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
use Illuminate\Auth\Authenticatable as AuthenticatableTrait;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Notifications\Notifiable;
use Ramsey\Uuid\Uuid;
use Laravel\Sanctum\HasApiTokens;

class Customer extends Model implements Authenticatable, MustVerifyEmail, HasSystemBlueprint
{
    
    use InteractsWithSystemBlueprint;

    /**
     * Digitales Architektur- & Compliance-Typenschild (Living Documentation).
     */
    public static function blueprint(): ModelBlueprint
    {
        return ModelBlueprint::make(
            label: 'Kundenkonto',
            description: 'Registriertes oder über Gastbestellung angelegtes Kundenkonto für Shop, Auftragsstatus und Kundenportal.',
            department: SystemDepartment::Customer,
            navigationNodes: ['admin.customers.index', 'customer.dashboard'],
            lifecycle: LifecycleBehavior::MasterData,
            sensitivity: DataSensitivity::PersonalPII,
            retention: RetentionPolicy::StatutoryWarranty_3Years,
            aiAccess: AiAccessPolicy::ReadOnly,
            actorsRead: [ActorScope::Customer, ActorScope::Admin, ActorScope::EmployeeProduction],
            actorsWrite: [ActorScope::Customer, ActorScope::Admin],
            regulations: [ComplianceRegulation::DSGVO_Art15_Art30, ComplianceRegulation::GoBD],
            externalChannels: [ExternalChannel::IMAP_SMTP_Mail],
            dependsOnSettings: [],
            isGdprArt15Exportable: true,
            customerForeignKey: 'id',
            containsProductionAssets: false,
            assetColumns: [],
        );
    }

use HasFactory, HasUuids, softDeletes, AuthenticatableTrait, HasApiTokens, Notifiable;

    protected $fillable = [
        'first_name',
        'last_name',
        'email',
        'password',
        'google_id',
        'email_verified_at',
        'needs_password_change',
        'temporary_password',
    ];

    protected $hidden = [
        'password',
        'remember_token',
        'temporary_password',
    ];

    public $incrementing = false; // Deaktivieren Sie das Inkrementieren des Primärschlüssels
    protected $keyType = 'string'; // Setzen Sie den Primärschlüsseltyp auf 'string'

    protected $casts = [
        'needs_password_change' => 'boolean',
    ];

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function ($model) {
            $model->{$model->getKeyName()} = (string) Uuid::uuid4();
        });

        // Event-Listener für das Erstellen eines Customer Profiles
        static::created(function (Customer $customer) {
            $customerProfile = new CustomerProfile();
            $customer->profile()->save($customerProfile);
        });

        // Event-Listener für das Löschen eines Customer Profiles
        static::deleting(function (Customer $customer) {
            if ($customer->isForceDeleting()) {
                $customer->profile()->withTrashed()->forceDelete();
            } elseif ($customer->profile) {
                $customer->profile()->delete();
            }
        });

        // Event-Listener für das Wiederherstellen eines Customer Profiles
        static::restoring(function (Customer $customer) {
            $customer->profile()->withTrashed()->restore();
        });
    }

    
    public function directories(): MorphToMany
    {
        return $this->morphToMany(\App\Models\System\SystemDirectory::class, 'user', 'directory_user');
    }

    public function profile(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(CustomerProfile::class);
    }

    public function gamification(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(CustomerGamification::class, 'customer_id');
    }

    public function orders(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(\App\Models\Order\OrderOrder::class, 'customer_id');
    }

    // -------------------------------------------------------------------------
    // MUST VERIFY EMAIL IMPLEMENTIERUNG (Angepasst für CustomerProfile)
    // -------------------------------------------------------------------------

    public function hasVerifiedEmail()
    {
        // Prüft, ob auf dem Kundenmodell oder im zugehörigen Profil das Verifizierungsdatum gesetzt ist
        return ! is_null($this->email_verified_at) || ! is_null($this->profile->email_verified_at ?? null);
    }

    public function markEmailAsVerified()
    {
        $ts = $this->freshTimestamp();
        $this->forceFill(['email_verified_at' => $ts])->save();

        // Speichert den aktuellen Zeitstempel im Profil ab
        if ($this->profile) {
            $this->profile->email_verified_at = $ts;
            return $this->profile->save();
        }
        return true;
    }

    public function markEmailAsUnverified()
    {
        $this->forceFill(['email_verified_at' => null])->save();

        // Setzt das Verifizierungsdatum im Profil zurück (Neu ab Laravel 12/13)
        if ($this->profile) {
            $this->profile->email_verified_at = null;
            return $this->profile->save();
        }
        return true;
    }

    public function sendEmailVerificationNotification()
    {
        // Nutzt das Standard-Benachrichtigungssystem von Laravel für die Verifizierungs-Mail.
        // Das funktioniert, weil wir oben den "Notifiable" Trait eingebunden haben.
        $this->notify(new \Illuminate\Auth\Notifications\VerifyEmail);
    }

    public function getEmailForVerification()
    {
        // Gibt zurück, an welche E-Mail-Adresse die Verifizierung gesendet werden soll
        return $this->email;
    }

    public function productReviews()
    {
        return $this->hasMany(\App\Models\Product\ProductReview::class);
    }

    /**
     * Prüft, ob der Kunde eine nachweisbare Werbeeinwilligung (Double-Opt-In) hat
     * und nicht widersprochen hat.
     */
    public function hasMarketingConsent(): bool
    {
        if ($this->profile?->cart_reminder_unsubscribed_at !== null) {
            return false;
        }

        return \App\Models\Marketing\MarketingNewsletterSubscriber::where('email', $this->email)
            ->where('is_verified', true)
            ->exists();
    }

    /**
     * Prüft, ob der Kunde rechtssicher für Warenkorb-Erinnerungen kontaktiert werden darf.
     */
    public function allowsCartReminders(): bool
    {
        // 1. Wenn der Kunde ausdrücklich widersprochen hat (Opt-Out nach § 7 UWG / Art. 21 DSGVO)
        if ($this->profile?->cart_reminder_unsubscribed_at !== null) {
            return false;
        }

        // 2. Rechtssicherheits-Schalter (Standard: true, nur mit Einwilligung)
        $requireConsent = filter_var(shop_setting('cart_abandoned_require_consent', true), FILTER_VALIDATE_BOOLEAN);
        if ($requireConsent) {
            return $this->hasMarketingConsent();
        }

        return true;
    }

    /**
     * Generiert einen kryptografischen Hash für den One-Click-Widerspruchslink (Opt-Out).
     */
    public function getCartReminderUnsubscribeHash(): string
    {
        return hash_hmac('sha256', $this->id . '|' . $this->email, config('app.key'));
    }

    /**
     * Prüft, ob der Kunde dem DSGVO-konformen KI-Datenzugriff (Art. 6 Abs. 1 lit. a DSGVO) zugestimmt hat.
     */
    public function hasAiDataConsent(): bool
    {
        return (bool) ($this->profile?->ai_data_consent ?? false);
    }

    /**
     * Aktualisiert die DSGVO-Einwilligung für den KI-Datenzugriff im Profil inkl. Nachweis-Log (Art. 7 Abs. 1 DSGVO).
     */
    public function updateAiDataConsent(bool $consent, string $source = 'profile'): void
    {
        $profile = $this->profile ?: $this->profile()->create([]);
        $profile->update([
            'ai_data_consent' => $consent,
            'ai_data_consent_at' => $consent ? now() : null,
        ]);
        $this->setRelation('profile', $profile->fresh());

        if (class_exists(\App\Models\System\SystemLog::class)) {
            \App\Models\System\SystemLog::create([
                'type' => 'system',
                'action_id' => $consent ? 'gdpr:ai_consent_granted' : 'gdpr:ai_consent_revoked',
                'title' => $consent ? 'DSGVO KI-Datenzugriff erteilt' : 'DSGVO KI-Datenzugriff widerrufen',
                'message' => sprintf('Kundenkonto [%s] hat die KI-Datenzugriffs-Freigabe über [%s] %s.', substr((string) $this->id, 0, 8), $source, $consent ? 'erteilt' : 'deaktiviert'),
                'status' => 'success',
                'started_at' => now(),
                'finished_at' => now(),
            ]);
        }
    }

    /**
     * Pfad zum privaten Manifest für vom Admin individuell hinterlegte Kundendokumente.
     */
    public function getAdminDocumentsManifestPath(): string
    {
        return 'produkte/products-secure/customer-files/' . $this->id . '/manifest.json';
    }

    /**
     * Liefert alle vom Admin direkt beim Kunden hinterlegten Dokumente (optional gefiltert nach Bestellung + allgemeine Dokumente).
     */
    public function getAdminUploadedDocuments(?string $orderId = null): array
    {
        if (empty($this->id)) {
            return [];
        }

        $manifestPath = $this->getAdminDocumentsManifestPath();
        $disk = \Illuminate\Support\Facades\Storage::disk('local');

        if (!$disk->exists($manifestPath)) {
            return [];
        }

        $decoded = json_decode((string) $disk->get($manifestPath), true);
        if (!is_array($decoded)) {
            return [];
        }

        $normalized = [];
        foreach ($decoded as $item) {
            if (!is_array($item)) {
                continue;
            }
            $norm = \App\Models\Product\Product::normalizeDigitalDocumentItem($item);
            if (!$norm) {
                continue;
            }
            $norm['order_id'] = !empty($item['order_id']) ? (string) $item['order_id'] : null;
            $norm['order_number'] = !empty($item['order_number']) ? (string) $item['order_number'] : null;
            $norm['uploaded_by_admin'] = true;

            if ($orderId !== null && $orderId !== '') {
                if (!empty($norm['order_id']) && (string) $norm['order_id'] !== (string) $orderId) {
                    continue;
                }
            }

            $normalized[] = $norm;
        }

        return $normalized;
    }

    /**
     * Fügt ein neues vom Admin hochgeladenes Dokument zum Kunden-Manifest hinzu.
     */
    public function addAdminUploadedDocument(array $doc): array
    {
        $existing = $this->getAdminUploadedDocuments();
        $norm = \App\Models\Product\Product::normalizeDigitalDocumentItem($doc);
        if ($norm) {
            $norm['order_id'] = !empty($doc['order_id']) ? (string) $doc['order_id'] : null;
            $norm['order_number'] = !empty($doc['order_number']) ? (string) $doc['order_number'] : null;
            $norm['uploaded_by_admin'] = true;
            array_unshift($existing, $norm);

            \Illuminate\Support\Facades\Storage::disk('local')->put(
                $this->getAdminDocumentsManifestPath(),
                json_encode(array_values($existing), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
            );
        }

        return $existing;
    }

    /**
     * Entfernt ein vom Admin hochgeladenes Kundendokument inkl. Datei auf dem privaten Storage.
     */
    public function removeAdminUploadedDocument(string $docId): bool
    {
        $existing = $this->getAdminUploadedDocuments();
        $found = false;
        $remaining = [];
        $disk = \Illuminate\Support\Facades\Storage::disk('local');

        foreach ($existing as $doc) {
            if (($doc['id'] ?? '') === $docId) {
                $found = true;
                $path = ltrim(str_replace('\\', '/', (string) ($doc['path'] ?? '')), '/');
                if (
                    $path !== ''
                    && !str_contains($path, '..')
                    && str_starts_with($path, 'produkte/products-secure/customer-files/' . $this->id . '/')
                    && $disk->exists($path)
                ) {
                    $disk->delete($path);
                }
                continue;
            }
            $remaining[] = $doc;
        }

        if ($found) {
            $disk->put(
                $this->getAdminDocumentsManifestPath(),
                json_encode(array_values($remaining), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
            );
        }

        return $found;
    }
}
