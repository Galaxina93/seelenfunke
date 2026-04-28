<?php

namespace App\Models\Admin;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Ramsey\Uuid\Uuid;

class AdminProfile extends Model
{
    use HasFactory, softDeletes;

    protected $fillable = [
        'id', 'admin_id', 'is_business', 'company_name', 'vat_id', 'internal_note',
        'photo_path', 'about', 'url', 'phone_number', 'street', 'house_number',
        'postal', 'city', 'country', 'two_factor_is_active', 'two_factor_secret',
        'two_factor_recovery_codes', 'email_verified_at', 'last_seen'
    ];

    public $incrementing = false;
    protected $keyType = 'string';

    protected $casts = [
        'is_business' => 'boolean',
    ];

    protected static function boot()
    {
        parent::boot();
        static::creating(function ($model) {
            $model->{$model->getKeyName()} = (string) Uuid::uuid4();
        });
    }

    protected $hidden = [
        'password', 'rememberToken', 'two_factor_recovery_codes', 'two_factor_secret',
    ];

    public function admin(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Admin::class);
    }
}
