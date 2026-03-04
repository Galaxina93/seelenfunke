<?php

use Illuminate\Support\Facades\Broadcast;

// DAS IST DER MAGISCHE SCHLÜSSEL!
// Er erlaubt es dem Customer- und Admin-Guard, sich beim WebSocket-Server zu authentifizieren.
Broadcast::routes(['middleware' => ['web', 'auth:customer,admin,web']]);

// Der Kunden-Kanal
Broadcast::channel('customer.{id}', function ($user, $id) {
    return (string) $user->id === (string) $id;
}, ['guards' => ['customer']]);

// Der Admin-Kanal
Broadcast::channel('admin.tickets', function ($user) {
    return true;
}, ['guards' => ['admin']]);
