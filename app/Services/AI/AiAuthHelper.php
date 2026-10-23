<?php

namespace App\Services\AI;

use Illuminate\Support\Facades\Auth;
use App\Models\Admin\Admin;
use App\Models\Customer\Customer;

class AiAuthHelper
{
    public static function isAdmin(): bool
    {
        $user = Auth::user();
        return ($user instanceof Admin) || Auth::guard('admin')->check();
    }

    public static function isEmployee(): bool
    {
        $user = Auth::user();
        return ($user instanceof \App\Models\Employee\Employee) || Auth::guard('employee')->check();
    }

    public static function isCustomer(): bool
    {
        $user = Auth::user();
        return ($user instanceof Customer) || Auth::guard('customer')->check();
    }

    public static function getAdminId()
    {
        $user = Auth::user();
        if ($user instanceof Admin) {
            return $user->id;
        }
        return Auth::guard('admin')->id();
    }

    public static function getEmployeeId()
    {
        $user = Auth::user();
        if ($user instanceof \App\Models\Employee\Employee) {
            return $user->id;
        }
        return Auth::guard('employee')->id();
    }

    public static function getCustomerId()
    {
        $user = Auth::user();
        if ($user instanceof Customer) {
            return $user->id;
        }
        return Auth::guard('customer')->id();
    }

    public static function check(): bool
    {
        return self::isAdmin() || self::isEmployee() || self::isCustomer() || Auth::check();
    }

    public static function getUserId()
    {
        if (self::isAdmin()) {
            return self::getAdminId();
        }
        if (self::isEmployee()) {
            return self::getEmployeeId();
        }
        if (self::isCustomer()) {
            return self::getCustomerId();
        }
        return Auth::id();
    }

    public static function getUser()
    {
        if (self::isAdmin()) {
            return (Auth::user() instanceof Admin) ? Auth::user() : Auth::guard('admin')->user();
        }
        if (self::isEmployee()) {
            return (Auth::user() instanceof \App\Models\Employee\Employee) ? Auth::user() : Auth::guard('employee')->user();
        }
        if (self::isCustomer()) {
            return (Auth::user() instanceof Customer) ? Auth::user() : Auth::guard('customer')->user();
        }
        return Auth::user();
    }
}
