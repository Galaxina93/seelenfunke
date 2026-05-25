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

    public static function getCustomerId()
    {
        $user = Auth::user();
        if ($user instanceof Customer) {
            return $user->id;
        }
        return Auth::guard('customer')->id();
    }
}
