<?php

namespace App\Models\Customer;

use App\Models\Role;
use Illuminate\Auth\Authenticatable as AuthenticatableTrait;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Ramsey\Uuid\Uuid;

class Customer extends Model implements Authenticatable
{
    use HasFactory, HasUuids, softDeletes, AuthenticatableTrait;

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

        // Event-Listener für das erstellen eines Customer Profiles
        static::created(function (Customer $customer) {
            $customerProfile = new CustomerProfile();
            $customer->profile()->save($customerProfile);

            $customerRole = Role::where('name', 'customer')->first();
            $customer->roles()->attach($customerRole->id);
        });

        // Event-Listener für das Löschen eines Customer Profiles
        static::deleting(function (Customer $customer) {
            $customer->profile()->delete();
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

}
