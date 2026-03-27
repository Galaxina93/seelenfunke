<?php

namespace App\Http\Controllers;

use App\Models\Order\OrderRevocation;
use Illuminate\Support\Facades\Storage;

class RevocationFileController extends Controller
{
    public function download(OrderRevocation $revocation, $fileName)
    {
        $path = "revocations/{$revocation->id}/{$fileName}";

        if (!is_array($revocation->attachments) || !in_array($path, $revocation->attachments)) {
            abort(404, 'Datei nicht gefunden oder gehört nicht zu diesem Widerruf.');
        }

        if (!Storage::disk('private')->exists($path)) {
            abort(404, 'Die angeforderte Datei existiert physisch nicht mehr auf dem Server.');
        }

        return Storage::disk('private')->download($path);
    }
}
