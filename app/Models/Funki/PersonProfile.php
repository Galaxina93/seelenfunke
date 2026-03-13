<?php

namespace App\Models\Funki;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PersonProfile extends Model
{
    use HasFactory;

    protected $table = 'person_profiles';

    protected $fillable = [
        'first_name',
        'last_name',
        'nickname',
        'relation_type',
        'birthday',
        'email',
        'phone',
        'system_instructions',
        'ai_learned_facts',
    ];

    protected $casts = [
        'birthday' => 'date',
    ];

    /**
     * Get the full name of the person.
     */
    public function getFullNameAttribute()
    {
        return trim("{$this->first_name} {$this->last_name}");
    }
}
