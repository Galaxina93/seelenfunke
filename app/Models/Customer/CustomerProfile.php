<?php

namespace App\Models\Customer;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Ramsey\Uuid\Uuid;

class CustomerProfile extends Model
{
    use HasFactory, HasUuids, softDeletes;

    protected $fillable = [
        'id',
        'customer_id',
        'photo_path',
        'about',
        'url',
        'phone_number',
        'street',
        'house_number',
        'postal',
        'city',
        'country',
        'two_factor_is_active',
        'two_factor_secret',
        'two_factor_recovery_codes',
        'email_verified_at',
        'last_seen'
    ];

    public $incrementing = false; // Deaktivieren Sie das Inkrementieren des Primärschlüssels
    protected $keyType = 'string'; // Setzen Sie den Primärschlüsseltyp auf 'string'

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            $model->{$model->getKeyName()} = (string) Uuid::uuid4();
        });
    }

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'rememberToken',
        'two_factor_recovery_codes',
        'two_factor_secret',
    ];

    public function customer(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

}
