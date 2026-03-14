<?php

namespace App\Models\Funki;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PersonProfile extends Model
{
    use HasFactory;

    protected $table = 'person_profiles';

    protected $fillable = [
        'is_favorite',
        'first_name',
        'last_name',
        'nickname',
        'relation_type',
        'avatar_path',
        'links',
        'birthday',
        'email',
        'phone',
        'system_instructions',
        'ai_learned_facts',
        'street',
        'postal_code',
        'city',
        'country'
    ];

    protected $casts = [
        'birthday' => 'date',
        'links' => 'array',
        'is_favorite' => 'boolean'
    ];

    /**
     * Get the full name of the person.
     */
    public function getFullNameAttribute()
    {
        return trim("{$this->first_name} {$this->last_name}");
    }
}
