<?php

namespace App\Http\Controllers;

use App\Models\Order\OrderRevocation;
use App\Services\AI\AiAuthHelper;
use Illuminate\Support\Facades\Storage;

class RevocationFileController extends Controller
{
    public function download(OrderRevocation $revocation, $fileName)
    {
        if (!AiAuthHelper::isAdmin() && !auth()->guard('admin')->check() && !auth()->guard('employee')->check()) {
            abort(403, 'Zugriff verweigert.');
        }

        if (str_contains($fileName, '..') || str_contains($fileName, '/') || str_contains($fileName, '\\')) {
            abort(404, 'Ungültiger Dateiname.');
        }

        $path = "bestellungen/private/revocations/{$revocation->id}/{$fileName}";

        if (!is_array($revocation->attachments) || !in_array($path, $revocation->attachments)) {
            // Fallback für legacy Pfad
            $legacyPath = "revocations/{$revocation->id}/{$fileName}";
            if (is_array($revocation->attachments) && in_array($legacyPath, $revocation->attachments)) {
                $path = $legacyPath;
            } else {
                abort(404, 'Datei nicht gefunden oder gehört nicht zu diesem Widerruf.');
            }
        }

        if (!Storage::disk('local')->exists($path)) {
            abort(404, 'Die angeforderte Datei existiert physisch nicht mehr auf dem Server.');
        }

        return Storage::disk('local')->download($path);
    }
}
