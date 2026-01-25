<?php

namespace App\Models;

use Ramsey\Uuid\Uuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class EmployeeProfile extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'id',
        'employee_id',
        'photo_path',
        'about',
        'url',
        'phone_number',
        'street',
        'house_number',
        'postal',
        'city',
        'two_factor_is_active',
        'two_factor_secret',
        'two_factor_recovery_codes',
        'email_verified_at',
        'last_seen'
    ];

    public $incrementing = false;
    protected $keyType = 'string';

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            $model->{$model->getKeyName()} = (string) Uuid::uuid4();
        });
    }

    protected $hidden = [
        'password',
        'rememberToken',
        'two_factor_recovery_codes',
        'two_factor_secret',
    ];

    public function employee(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}
