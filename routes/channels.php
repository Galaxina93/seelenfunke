<?php

use Illuminate\Support\Facades\Broadcast;

// ----------------------------------------------------
// ADMIN CHANNELS
// ----------------------------------------------------

// 1. Spezifische Kanäle ZUERST!
Broadcast::channel('admin.tickets', function ($user) {
    return true;
}, ['guards' => ['admin']]);

Broadcast::channel('admin', function ($user) {
    return true;
}, ['guards' => ['admin']]);

// 2. Globale / Shop Channels (CEO & interne aktive Mitarbeiter)
Broadcast::channel('shop', function ($user) {
    if ($user instanceof \App\Models\Admin\Admin) {
        return true;
    }
    if ($user instanceof \App\Models\Employee\Employee) {
        return $user->isInternalEmployee() && $user->isAccountActive();
    }
    return false;
}, ['guards' => ['admin', 'employee']]);

// 2b. Sensible CEO-/Management-Kanäle (Nur Admin)
Broadcast::channel('workspace', function ($user) {
    return true;
}, ['guards' => ['admin']]);

Broadcast::channel('telephony', function ($user) {
    return true;
}, ['guards' => ['admin']]);

Broadcast::channel('crm-inbox', function ($user) {
    return true;
}, ['guards' => ['admin']]);

// 3. Wildcard-Kanäle (mit {id}) GANZ NACH UNTEN!
Broadcast::channel('admin.{id}', function ($user, $id) {
    return (string) $user->id === (string) $id;
}, ['guards' => ['admin']]);


// ----------------------------------------------------
// CUSTOMER CHANNELS
// ----------------------------------------------------
Broadcast::channel('customer.{id}', function ($user, $id) {
    return (string) $user->id === (string) $id;
}, ['guards' => ['customer']]);
