<?php

namespace App\Models\Customer;

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

class Customer extends Model implements Authenticatable, MustVerifyEmail
{
    use HasFactory, HasUuids, softDeletes, AuthenticatableTrait, HasApiTokens, Notifiable;

    protected $fillable = [
        'first_name',
        'last_name',
        'email',
        'password',
        'needs_password_change',
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

    // -------------------------------------------------------------------------
    // MUST VERIFY EMAIL IMPLEMENTIERUNG (Angepasst für CustomerProfile)
    // -------------------------------------------------------------------------

    public function hasVerifiedEmail()
    {
        // Prüft, ob im zugehörigen Profil das Verifizierungsdatum gesetzt ist
        return ! is_null($this->profile->email_verified_at ?? null);
    }

    public function markEmailAsVerified()
    {
        // Speichert den aktuellen Zeitstempel im Profil ab
        if ($this->profile) {
            $this->profile->email_verified_at = $this->freshTimestamp();
            return $this->profile->save();
        }
        return false;
    }

    public function markEmailAsUnverified()
    {
        // Setzt das Verifizierungsdatum im Profil zurück (Neu ab Laravel 12/13)
        if ($this->profile) {
            $this->profile->email_verified_at = null;
            return $this->profile->save();
        }
        return false;
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
        $requireConsent = shop_setting('cart_abandoned_require_consent', true);
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
}
