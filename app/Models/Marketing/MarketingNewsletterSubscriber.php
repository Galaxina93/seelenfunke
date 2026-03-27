<?php

namespace App\Models\Marketing;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MarketingNewsletterSubscriber extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'marketing_newsletter_subscribers';

    protected $fillable = [
        'email',
        'ip_address',
        'privacy_accepted',
        'is_verified',
        'verification_token',
        'verified_at'
    ];

    protected $casts = [
        'privacy_accepted' => 'boolean',
        'is_verified' => 'boolean',
        'subscribed_at' => 'datetime',
        'verified_at' => 'datetime',
    ];
}
