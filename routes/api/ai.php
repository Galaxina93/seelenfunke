<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AIController;

/*
|--------------------------------------------------------------------------
| AI Remote Control API Routes
|--------------------------------------------------------------------------
|
| Diese Routen sind für lokale LLMs (z.B. Ollama) oder externe Skripte
| gedacht, die mit dem Laravelsystem interagieren wollen (Tool Calling).
| 
| HINWEIS: Hier sollte später ein Middleware-Schutz (z.B. Token) 
| eingebaut werden.
|
*/

Route::prefix('ai')->group(function () {
    // Gibt das JSON-Schema für die Ollama Function-Calling Tools zurück
    Route::get('/schema', [AIController::class, 'schema']);
    
    // Führt eine der registrierten Tools aus
    Route::post('/execute', [AIController::class, 'execute'])->middleware(['web', 'auth:sanctum,web']);

    // Speichert einen Kamera-Snapshot auf dem Dateisystem
    Route::post('/camera/snapshot', [AIController::class, 'saveCameraSnapshot'])->middleware(['web', 'auth:sanctum,web']);

    // Endpunkt für das Frontend (nimmt Prompt entgegen und schickt es an Ollama)
    Route::post('/chat', [AIController::class, 'chat'])->middleware(['web', 'auth:sanctum,web']);

    // Endpunkt für den Multimodal Live API Mode (WebSockets)
    Route::get('/live-credentials', [AIController::class, 'liveCredentials'])->middleware(['web', 'auth:sanctum,web']);
});
