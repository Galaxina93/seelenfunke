<?php

// app/Models/Directory.php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphToMany;

class Directory extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'path'];

    /**
     * Definiert die polymorphe Many-to-Many-Beziehung zu den Admin-Benutzern.
     */
    public function admins(): MorphToMany
    {
        return $this->morphedByMany(Admin::class, 'user', 'directory_user');
    }

    /**
     * Definiert die polymorphe Many-to-Many-Beziehung zu den Employee-Benutzern.
     */
    public function employees(): MorphToMany
    {
        return $this->morphedByMany(Employee::class, 'user', 'directory_user');
    }

    /**
     * Definiert die polymorphe Many-to-Many-Beziehung zu den Customer-Benutzern.
     */
    public function customers(): MorphToMany
    {
        return $this->morphedByMany(Customer::class, 'user', 'directory_user');
    }
}
