<?php

use Illuminate\Support\Facades\Broadcast;


// ----------------------------------------------------
// ADMIN CHANNELS (Wichtig: ['guards' => ['admin']])
// ----------------------------------------------------

Broadcast::channel('admin.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
}, ['guards' => ['admin']]);

// Falls du auch den generischen Admin-Channel nutzt:
Broadcast::channel('admin', function ($user) {
    return true; // Der Guard-Check reicht hier als Authentifizierung
}, ['guards' => ['admin']]);


// ----------------------------------------------------
// CUSTOMER CHANNELS (Wichtig: ['guards' => ['customer']])
// ----------------------------------------------------
Broadcast::routes(['middleware' => ['web', 'auth:customer,admin,web']]);

// Der Kunden-Kanal
Broadcast::channel('customer.{id}', function ($user, $id) {
    return (string) $user->id === (string) $id;
}, ['guards' => ['customer']]);

// Der Admin-Kanal
Broadcast::channel('admin.tickets', function ($user) {
    return true;
}, ['guards' => ['admin']]);
