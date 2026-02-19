<?php

namespace App\Models\Customer;

use App\Models\Role;
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
    ];

    public $incrementing = false; // Deaktivieren Sie das Inkrementieren des Primärschlüssels
    protected $keyType = 'string'; // Setzen Sie den Primärschlüsseltyp auf 'string'

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

            $customerRole = Role::where('name', 'customer')->first();
            if ($customerRole) {
                $customer->roles()->attach($customerRole->id);
            }
        });

        // Event-Listener für das Löschen eines Customer Profiles
        static::deleting(function (Customer $customer) {
            if ($customer->profile) {
                $customer->profile()->delete();
            }
        });
    }

    public function roles(): MorphToMany
    {
        return $this->morphToMany(Role::class, 'roleable');
    }

    public function directories(): MorphToMany
    {
        return $this->morphToMany(\App\Models\Directory::class, 'user', 'directory_user');
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
}
