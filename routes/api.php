<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Hier werden die verschiedenen API-Bereiche modular geladen.
| Dies hält die Hauptdatei schlank und wartbar.
|
*/

// --- 1. Authentifizierung (Ungeschützt) ---
require __DIR__ . '/api/auth.php';

// --- Lokale KI API ---
require __DIR__ . '/api/ai.php';


// --- 2. Geschützte API-Routen (Sanctum) ---
Route::middleware('auth:sanctum')->group(function () {

    // Basis User- & Geräte-Funktionen
    require __DIR__ . '/api/core.php';

    // Finanz-Manager
    require __DIR__ . '/api/finance.php';

    // Tagesroutine
    require __DIR__ . '/api/routine.php';

    // Tasks (Aufgaben)
    require __DIR__ . '/api/tasks.php';

    // Calender & Termine
    require __DIR__ . '/api/calendar.php';

});
