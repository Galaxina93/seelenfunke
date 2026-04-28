<?php

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Hier können Sie Webrouten für Ihre Anwendung registrieren. Diese
| Routen werden vom RouteServiceProvider geladen und alle werden
| werden der Middleware-Gruppe "web" zugeordnet. Machen Sie etwas Tolles!
|
*/

// Global-Routes
require __DIR__ . '/partials/global_routes.php';

// Admin-Routes
require __DIR__ . '/partials/admin_routes.php';

// Customer-Routes
require __DIR__ . '/partials/customer_routes.php';

// Employee-Routes
require __DIR__ . '/partials/employee_routes.php';
