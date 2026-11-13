<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Models\Management\ManagementPlaylist;
use App\Models\Management\ManagementTrack;
use Illuminate\Support\Str;

Route::prefix('funki/music')->group(function () {

    // 1. Get all Playlists (with nested Tracks)
    Route::get('/playlists', function () {
        $playlists = ManagementPlaylist::with(['tracks' => function ($q) {
            $q->orderBy('position', 'asc')->orderBy('created_at', 'asc');
        }])
            ->orderBy('position', 'asc')
            ->orderBy('created_at', 'asc')
            ->get()
            ->map(function (ManagementPlaylist $pl) {
                $tracks = $pl->tracks->map(function (ManagementTrack $t) {
                    $absPath = $t->file_path ? storage_path('app/' . $t->file_path) : ($t->external_id ? storage_path("app/leitung/music/tracks/{$t->external_id}.m4a") : null);
                    $isCached = $absPath && file_exists($absPath) && filesize($absPath) > 10000;
                    if ($isCached && $t->status !== 'cached') {
                        $t->status = 'cached';
                        $t->file_path = "leitung/music/tracks/{$t->external_id}.m4a";
                        $t->file_size = filesize($absPath);
                    }
                    return [
                        'id' => $t->id,
                        'playlist_id' => $t->playlist_id,
                        'title' => $t->title,
                        'artist' => $t->artist ?: 'Unbekannter Künstler',
                        'duration_seconds' => (int) $t->duration_seconds,
                        'cover_url' => $t->cover_url,
                        'external_id' => $t->external_id,
                        'status' => $isCached ? 'cached' : $t->status,
                        'is_cached' => $isCached,
                        'file_size' => (int) $t->file_size,
                        'position' => (int) $t->position,
                        'is_favorite' => (bool) $t->is_favorite,
                        'play_count' => (int) $t->play_count,
                    ];
                })->values();

                return [
                    'id' => $pl->id,
                    'name' => $pl->name,
                    'description' => $pl->description,
                    'cover_url' => $pl->cover_url ?: ($tracks->first()['cover_url'] ?? null),
                    'source_url' => $pl->source_url,
                    'icon' => $pl->icon,
                    'color' => $pl->color,
                    'position' => (int) $pl->position,
                    'is_favorite' => (bool) $pl->is_favorite,
                    'tracks_count' => $tracks->count(),
                    'tracks' => $tracks,
                ];
            });

        return response()->json(['success' => true, 'data' => $playlists]);
    });

    // 2. Create Playlist
    Route::post('/playlists', function (Request $request) {
        $data = $request->validate([
            'name' => 'required|string|max:120',
            'description' => 'nullable|string|max:500',
            'color' => 'nullable|string|max:20',
        ]);

        $maxPos = (int) ManagementPlaylist::max('position');
        $pl = ManagementPlaylist::create([
            'id' => (string) Str::uuid(),
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'icon' => 'musical-note',
            'color' => $data['color'] ?? '#C5A059',
            'position' => $maxPos + 1,
            'is_favorite' => false,
        ]);

        return response()->json(['success' => true, 'data' => $pl]);
    });

    // 3. Update Playlist
    Route::put('/playlists/{id}', function (Request $request, $id) {
        $pl = ManagementPlaylist::findOrFail($id);
        $data = $request->validate([
            'name' => 'sometimes|required|string|max:120',
            'description' => 'nullable|string|max:500',
            'color' => 'nullable|string|max:20',
            'position' => 'sometimes|integer',
            'is_favorite' => 'sometimes|boolean',
        ]);
        $pl->update($data);
        return response()->json(['success' => true, 'data' => $pl]);
    });

    // 4. Delete Playlist
    Route::delete('/playlists/{id}', function ($id) {
        $pl = ManagementPlaylist::findOrFail($id);
        $pl->delete();
        return response()->json(['success' => true]);
    });

    // 5. Import YouTube Song or Playlist URL directly (with live background progress)
    Route::post('/import-url', function (Request $request) {
        $data = $request->validate([
            'url' => 'required|url|max:1000',
            'playlist_id' => 'nullable|string|max:64',
            'playlist_name' => 'nullable|string|max:120',
        ]);

        $url = trim($data['url']);
        $parsedHost = strtolower((string) parse_url($url, PHP_URL_HOST));
        $parsedScheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));
        $allowedMusicHosts = ['youtube.com', 'www.youtube.com', 'm.youtube.com', 'music.youtube.com', 'youtu.be', 'open.spotify.com'];
        if ($parsedScheme !== 'https' || !in_array($parsedHost, $allowedMusicHosts, true)) {
            return response()->json([
                'success' => false,
                'message' => 'Sicherheitsprüfung: Nur HTTPS-Links von YouTube oder Spotify sind zulässig.',
            ], 422);
        }

        $ytDlp = storage_path('app/leitung/music/bin/yt-dlp');
        if (!file_exists($ytDlp)) {
            return response()->json(['success' => false, 'message' => 'yt-dlp Binary nicht gefunden.'], 500);
        }

        $statusFile = storage_path('app/leitung/music/import_status.json');
        if (!is_dir(dirname($statusFile))) {
            @mkdir(dirname($statusFile), 0775, true);
        }
        @file_put_contents($statusFile, json_encode([
            'active' => true,
            'status' => 'starting',
            'playlist_id' => $data['playlist_id'] ?? null,
            'playlist_name' => $data['playlist_name'] ?? 'YouTube-Import',
            'current' => 0,
            'total' => 0,
            'percent' => 3,
            'current_title' => 'Starte YouTube-Analyse & Audio-Extraktion...',
            'message' => 'Verbinde mit YouTube & lese Playlist-Struktur aus...',
            'updated_at' => now()->toIso8601String(),
        ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));

        $artisan = base_path('artisan');
        $cmdParts = [
            'php',
            escapeshellarg($artisan),
            'funkenwerk:sync-music',
            escapeshellarg($url),
            '--download-all',
        ];
        if (!empty($data['playlist_id']) && !in_array($data['playlist_id'], ['all', 'favorites'], true)) {
            $cmdParts[] = '--playlist-id=' . escapeshellarg($data['playlist_id']);
        }
        if (!empty($data['playlist_name'])) {
            $cmdParts[] = '--playlist-name=' . escapeshellarg($data['playlist_name']);
        }
        $logFile = escapeshellarg(storage_path('logs/funkenwerk_music_import.log'));
        $bgCmd = 'nohup ' . implode(' ', $cmdParts) . ' > ' . $logFile . ' 2>&1 &';
        exec($bgCmd);

        return response()->json([
            'success' => true,
            'async' => true,
            'message' => 'YouTube-Import gestartet. Fortschritt wird live übertragen.',
        ]);
    });

    // 5b. Live Import Progress Status (for ProgressBar in Desktop, Web & Android)
    Route::get('/import-status', function () {
        $statusFile = storage_path('app/leitung/music/import_status.json');
        if (!file_exists($statusFile)) {
            return response()->json([
                'active' => false,
                'status' => 'idle',
                'current' => 0,
                'total' => 0,
                'percent' => 0,
                'current_title' => '',
                'message' => '',
            ]);
        }
        $raw = @file_get_contents($statusFile);
        $data = $raw ? json_decode($raw, true) : null;
        return response()->json($data ?: ['active' => false, 'status' => 'idle', 'percent' => 0]);
    });

    // 6. Upload Local Audio File
    Route::post('/playlists/{id}/upload', function (Request $request, $id) {
        $playlist = ManagementPlaylist::findOrFail($id);
        $request->validate([
            'file' => 'required|file|max:51200|mimes:mp3,m4a,aac,wav,ogg,flac,mp4', // up to 50MB + server-side MIME verification
            'title' => 'nullable|string|max:255',
            'artist' => 'nullable|string|max:255',
        ]);

        $file = $request->file('file');
        $ext = strtolower($file->getClientOriginalExtension());
        $mime = strtolower((string) $file->getMimeType());
        $blockedMimes = ['text/x-php', 'application/x-httpd-php', 'text/html', 'application/xhtml+xml', 'application/x-executable', 'application/x-msdownload'];
        if (!in_array($ext, ['mp3', 'm4a', 'aac', 'wav', 'ogg', 'flac'], true) || in_array($mime, $blockedMimes, true)) {
            return response()->json(['success' => false, 'message' => 'Nicht unterstütztes Audioformat.'], 422);
        }

        $trackId = (string) Str::uuid();
        $filename = $trackId . '.' . $ext;
        $storedPath = $file->storeAs('leitung/music/tracks', $filename, 'local');

        $rawName = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
        $artist = $request->input('artist') ?: 'Eigener Upload';
        $title = $request->input('title') ?: $rawName;
        if (!$request->filled('title') && str_contains($rawName, ' - ')) {
            [$a, $t] = explode(' - ', $rawName, 2);
            $artist = trim($a);
            $title = trim($t);
        }

        $maxPos = (int) ManagementTrack::where('playlist_id', $playlist->id)->max('position');
        $track = ManagementTrack::create([
            'id' => $trackId,
            'playlist_id' => $playlist->id,
            'title' => $title,
            'artist' => $artist,
            'duration_seconds' => 0,
            'file_path' => $storedPath,
            'status' => 'cached',
            'file_size' => $file->getSize(),
            'position' => $maxPos + 1,
        ]);

        return response()->json(['success' => true, 'data' => $track]);
    });

    // 7. Update Track
    Route::put('/tracks/{id}', function (Request $request, $id) {
        $track = ManagementTrack::findOrFail($id);
        $data = $request->validate([
            'title' => 'sometimes|required|string|max:255',
            'artist' => 'nullable|string|max:255',
            'playlist_id' => 'sometimes|string|exists:management_playlists,id',
            'position' => 'sometimes|integer',
            'is_favorite' => 'sometimes|boolean',
        ]);
        $track->update($data);
        return response()->json(['success' => true, 'data' => $track]);
    });

    // 8. Toggle Favorite Track (100% Sync across Windows, Android & Web Portal)
    Route::match(['put', 'post'], '/tracks/{id}/favorite', function (Request $request, $id) {
        $track = ManagementTrack::find($id);
        if (!$track && $request->filled('external_id')) {
            $track = ManagementTrack::where('external_id', (string) $request->input('external_id'))->first();
        }
        if (!$track && $request->filled('title')) {
            $q = ManagementTrack::whereRaw('LOWER(title) = ?', [ mb_strtolower(trim((string) $request->input('title'))) ]);
            if ($request->filled('artist')) {
                $q->whereRaw('LOWER(artist) = ?', [ mb_strtolower(trim((string) $request->input('artist'))) ]);
            }
            $track = $q->first();
        }
        if (!$track) {
            return response()->json(['success' => false, 'error' => 'Track not found'], 404);
        }

        $newFav = $request->has('is_favorite')
            ? filter_var($request->input('is_favorite'), FILTER_VALIDATE_BOOLEAN)
            : !$track->is_favorite;

        $track->update(['is_favorite' => $newFav]);

        // Synchronize identical track rows across all playlists (by external_id or title+artist)
        if (!empty($track->external_id)) {
            ManagementTrack::where('external_id', $track->external_id)->update(['is_favorite' => $newFav]);
        }
        ManagementTrack::whereRaw('LOWER(title) = ?', [ mb_strtolower((string) $track->title) ])
            ->whereRaw('LOWER(COALESCE(artist, "")) = ?', [ mb_strtolower((string) ($track->artist ?? '')) ])
            ->update(['is_favorite' => $newFav]);

        // Persist favorite status in playlists_catalog.json & funkenwerk/playlists_cache.json
        foreach ([storage_path('app/leitung/music/playlists_catalog.json'), base_path('funkenwerk/playlists_cache.json')] as $jsonFile) {
            if (file_exists($jsonFile) && is_writable($jsonFile)) {
                $decoded = json_decode((string) @file_get_contents($jsonFile), true);
                if (is_array($decoded) && isset($decoded['data']) && is_array($decoded['data'])) {
                    foreach ($decoded['data'] as &$pl) {
                        if (!isset($pl['tracks']) || !is_array($pl['tracks'])) continue;
                        foreach ($pl['tracks'] as &$tr) {
                            $sameId = ($tr['id'] ?? '') === $track->id;
                            $sameExt = !empty($track->external_id) && ($tr['external_id'] ?? '') === $track->external_id;
                            $sameName = strcasecmp((string) ($tr['title'] ?? ''), (string) $track->title) === 0
                                && strcasecmp((string) ($tr['artist'] ?? ''), (string) $track->artist) === 0;
                            if ($sameId || $sameExt || $sameName) {
                                $tr['is_favorite'] = $newFav;
                            }
                        }
                    }
                    unset($pl, $tr);
                    @file_put_contents($jsonFile, json_encode($decoded, JSON_UNESCAPED_UNICODE));
                }
            }
        }

        return response()->json(['success' => true, 'is_favorite' => $newFav, 'data' => $track->fresh()]);
    });

    // 9. Reorder Tracks (Drag & Drop)
    Route::post('/tracks/reorder', function (Request $request) {
        $ids = $request->input('track_ids', []);
        if (is_array($ids)) {
            foreach (array_values($ids) as $idx => $tid) {
                ManagementTrack::where('id', $tid)->update(['position' => $idx + 1]);
            }
        }
        return response()->json(['success' => true]);
    });

    // 10. Delete Track
    Route::delete('/tracks/{id}', function ($id) {
        $track = ManagementTrack::findOrFail($id);
        $track->delete();
        return response()->json(['success' => true]);
    });

});
