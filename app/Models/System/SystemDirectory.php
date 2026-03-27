<?php

// app/Models/Directory.php
namespace App\Models\System;

use App\Models\Admin\Admin;
use App\Models\Customer\Customer;
use App\Models\Employee\Employee;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphToMany;

class SystemDirectory extends Model
{
    use HasFactory;

    protected $table = 'system_directories';

    protected $fillable = ['name', 'path'];

    /**
     * Definiert die polymorphe Many-to-Many-Beziehung zu den Admin-Benutzern.
     */
    public function admins(): MorphToMany
    {
        return $this->morphedByMany(Admin::class, 'user', 'directory_user', 'directory_id', 'user_id');
    }

    /**
     * Definiert die polymorphe Many-to-Many-Beziehung zu den Employee-Benutzern.
     */
    public function employees(): MorphToMany
    {
        return $this->morphedByMany(Employee::class, 'user', 'directory_user', 'directory_id', 'user_id');
    }

    /**
     * Definiert die polymorphe Many-to-Many-Beziehung zu den Customer-Benutzern.
     */
    public function customers(): MorphToMany
    {
        return $this->morphedByMany(Customer::class, 'user', 'directory_user', 'directory_id', 'user_id');
    }
}
