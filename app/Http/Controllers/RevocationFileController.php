<?php

namespace App\Http\Controllers;

use App\Models\Order\OrderRevocation;
use App\Services\AI\AiAuthHelper;
use Illuminate\Support\Facades\Storage;

class RevocationFileController extends Controller
{
    public function download(OrderRevocation $revocation, $fileName)
    {
        if (!AiAuthHelper::isAdmin() && !AiAuthHelper::isEmployee() && !auth()->guard('admin')->check()) {
            abort(403, 'Zugriff verweigert.');
        }

        if (!is_string($fileName) || str_contains($fileName, '..') || str_contains($fileName, '/') || str_contains($fileName, '\\') || str_contains($fileName, "\0")) {
            abort(404, 'Ungültiger Dateiname.');
        }

        $path = "bestellungen/private/revocations/{$revocation->id}/{$fileName}";

        if (!is_array($revocation->attachments) || !in_array($path, $revocation->attachments, true)) {
            // Fallback für legacy Pfad
            $legacyPath = "revocations/{$revocation->id}/{$fileName}";
            if (is_array($revocation->attachments) && in_array($legacyPath, $revocation->attachments, true)) {
                $path = $legacyPath;
            } else {
                abort(404, 'Datei nicht gefunden oder gehört nicht zu diesem Widerruf.');
            }
        }

        if (!Storage::disk('local')->exists($path)) {
            abort(404, 'Die angeforderte Datei existiert physisch nicht mehr auf dem Server.');
        }

        $fullPath = Storage::disk('local')->path($path);
        if (is_link($fullPath)) {
            abort(404, 'Ungültige Datei.');
        }

        return Storage::disk('local')->download($path);
    }
}
