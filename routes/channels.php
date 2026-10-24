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

// 2. Globale / Shop Channels (CEO & Worker)
Broadcast::channel('shop', function ($user) {
    return true;
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
