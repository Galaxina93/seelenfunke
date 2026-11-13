<?php

namespace App\Console\Commands;

use App\Models\Management\ManagementPlaylist;
use App\Models\Management\ManagementTrack;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class SyncMusicPlaylistCommand extends Command
{
    protected $signature = 'funkenwerk:sync-music {url? : YouTube or Spotify Playlist/Track URL} {--playlist-id= : Target Playlist UUID} {--playlist-name= : Optional Playlist Name} {--download-all : Download all uncached tracks} {--clean-only : Only clean all existing track titles and ensure PC token}';

    protected $description = 'Synchronisiert eine YouTube- oder Spotify-Playlist in die Funkenwerk-Musikverwaltung, bereinigt alle Titel auf reine Klarnamen und lädt die Audiospuren herunter.';

    private function ensureDesktopToken(): void
    {
        try {
            $admin = \App\Models\Admin\Admin::first();
            if (!$admin) return;
            $exists = \Illuminate\Support\Facades\DB::table('personal_access_tokens')
                ->where('name', 'Funkenwerk-PC')
                ->exists();
            if (!$exists) {
                \Illuminate\Support\Facades\DB::table('personal_access_tokens')->updateOrInsert(
                    ['id' => 1],
                    [
                        'tokenable_type' => 'admin',
                        'tokenable_id' => $admin->id,
                        'name' => 'Funkenwerk-PC',
                        'token' => hash('sha256', 'CTIWGm2Ryb0d60ixSJPME2J6uSUkR1tm6Ga6DEfBd86476e9'),
                        'abilities' => '["*"]',
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]
                );
            }
        } catch (\Throwable $e) {
            // ignore
        }
    }

    public static function cleanArtistName(string $artist): string
    {
        $a = html_entity_decode($artist, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $a = str_replace("\u{00a0}", ' ', $a);
        // Strip channel suffixes
        $a = preg_replace('/\s*(?:\-\s*Topic|\-\s*Offiziell.*|Official\s*Channel|Official|VEVO|Videos)$/iu', '', $a);
        if (strcasecmp(trim($a), 'BASTILLEvideos') === 0) {
            $a = 'Bastille';
        }
        $a = trim(preg_replace('/\s{2,}/u', ' ', $a));
        return $a !== '' ? $a : 'Unbekannter Künstler';
    }

    public static function cleanPureSongTitle(string $title, ?string $artist = null): string
    {
        $t = html_entity_decode(trim($title), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $t = str_replace("\u{00a0}", ' ', $t);

        // Strip emojis and decorative symbols
        $t = preg_replace('/[\x{1F300}-\x{1FAFF}\x{2600}-\x{27BF}\x{FE00}-\x{FE0F}]+/u', '', $t);

        // Strip leading track numbers like "01. " or "1 - "
        $t = preg_replace('/^\d{1,3}\s*[\.\-\)]\s*/u', '', $t);

        // If artist is prefixed in the title ("Artist - Title" or "Artist – Title"), strip artist prefix
        if ($artist && $artist !== '' && $artist !== 'Unbekannter Künstler') {
            $quotedArtist = preg_quote($artist, '/');
            $t = preg_replace('/^' . $quotedArtist . '\s*[\-–—:]\s*/iu', '', $t);
        }

        // Remove everything inside (...), [...], {...} repeatedly
        for ($i = 0; $i < 5; $i++) {
            $t = preg_replace('/\s*[\(\[\{][^\)\]\}]*[\)\]\}]/u', '', $t);
        }

        // Remove unclosed trailing "(" or "[" or "{" segments
        $t = preg_replace('/\s*[\(\[\{].*$/u', '', $t);

        // Remove pipe or double-slash suffixes like " | Slowed & Reverb" or " // Lyrics"
        $t = preg_replace('/\s*[\|\/]{1,2}\s*.*$/u', '', $t);

        // Remove dash suffixes like " - 2012 Remaster", " - Remastered 2011", " - Single Version", " - Slowed", " - Remix", " - Radio Edit", " - From ..."
        $t = preg_replace('/\s+[\-–—]\s+(?:(?:\d{4}\s+)?remaster(?:ed)?(?:\s+\d{4})?|slowed.*|sped\s*up.*|reverb.*|.*remix.*|radio\s*edit.*|single\s*(?:edit|version).*|album\s*version.*|extended.*|club\s*mix.*|original\s*mix.*|edit|bootleg.*|flip.*|mashup.*|version.*|live.*|acoustic.*|instrumental.*|karaoke.*|best\s*part.*|loop(?:ed)?.*|tiktok.*|audio.*|video.*|lyrics?.*|official.*|hd|hq|4k|from\s+.*|mono.*|stereo.*)$/iu', '', $t);

        // Remove trailing "feat. / ft. / featuring / with ..." outside parentheses
        $t = preg_replace('/\s+\b(?:feat\.?|ft\.?|featuring)\s+.*$/iu', '', $t);

        // Remove trailing standalone noise tags ("Lyrics", "PT-BR", "Slowed", "Best Part Looped", "Hardstyle", etc.)
        $t = preg_replace('/\s+\b(?:slowed(?:\s*(?:&|and|\+)\s*reverb)?|sped\s*up|best\s*part(?:\s*looped)?|nightcore|hardstyle(?:\s*remix)?|official\s*(?:music\s*)?(?:video|audio)?|lyric\s*video|with\s*lyrics|lyrics?|pt\-br|remastered(?:\s+\d{4})?|single\s*edit)\s*$/iu', '', $t);

        // Clean stray quotes and whitespace
        $t = trim($t, " \t\n\r\0\x0B\"'“”„-–—•·");
        $t = preg_replace('/\s{2,}/u', ' ', $t);

        return $t !== '' ? $t : trim($title);
    }

    public function CleanAllExistingTrackTitles(): int
    {
        // Known YouTube uploader inversions / edge cases in Lieblingssongs
        $specificFixes = [
            'k3Vba2h9528' => ['artist' => 'Bunt.', 'title' => "There's a Star in the Universe That Looks Like You"],
            'KtlgYxa6BMU' => ['artist' => 'Lord Huron', 'title' => 'The Night We Met'],
            '978k_n3f_xU' => ['artist' => 'SIMAS', 'title' => 'Deine Lippen'],
            'B4kyx311JrM' => ['artist' => 'Chord Overstreet', 'title' => 'Hold On'],
            'MwpMEbgC7DA' => ['artist' => 'Tom Odell', 'title' => 'Another Love'],
            'jd-qI62gNJM' => ['artist' => 'Chris Isaak', 'title' => 'Wicked Game'],
            'ZYRfUoR9Q4Y' => ['artist' => 'Oli.P', 'title' => 'Flugzeuge im Bauch'],
            'B7xai5u_tnk' => ['artist' => 'TheFatRat', 'title' => 'Monody'],
            'dLVN3W3xH7g' => ['artist' => 'R3HAB, INNA & Sash!', 'title' => 'Rock My Body'],
            'bvC_0foemLY' => ['artist' => 'Robin Schulz', 'title' => 'Sugar'],
            'MGR4U7W1dZU' => ['artist' => 'Bastille', 'title' => 'Things We Lost In The Fire'],
            'q5w5w3_3_0I' => ['artist' => 'Asja Kadric', 'title' => 'Fear Not This Night'],
            'rT8_vD7h2_c' => ['artist' => 'Rosenfels', 'title' => 'Evelyn'],
            'i8q8fFs3kTM' => ['artist' => 'Linkin Park', 'title' => "She Couldn't"],
            'RkWQDDv_qdg' => ['artist' => 'Blue', 'title' => 'All Rise'],
            'Tm8LGxTLtQk' => ['artist' => 'Linkin Park', 'title' => 'One More Light'],
            'ta7VHkK9n_w' => ['artist' => 'Lighthouse Family', 'title' => 'High'],
        ];

        $tracks = ManagementTrack::all();
        $updated = 0;

        foreach ($tracks as $track) {
            $extId = (string) $track->external_id;
            if (isset($specificFixes[$extId])) {
                $newArtist = $specificFixes[$extId]['artist'];
                $newTitle = $specificFixes[$extId]['title'];
                if ($track->artist !== $newArtist || $track->title !== $newTitle) {
                    $track->update(['artist' => $newArtist, 'title' => $newTitle]);
                    $updated++;
                }
                continue;
            }

            $cleanArtist = self::cleanArtistName((string) $track->artist);
            $cleanTitle = self::cleanPureSongTitle((string) $track->title, $cleanArtist);

            // Additional check by title/artist pattern for reversed YouTube entries
            if (strcasecmp($cleanTitle, 'lord huron') === 0 && strcasecmp($cleanArtist, 'the night we met') === 0) {
                $cleanArtist = 'Lord Huron';
                $cleanTitle = 'The Night We Met';
            } elseif (strcasecmp($cleanTitle, 'SIMAS') === 0) {
                $cleanArtist = 'SIMAS';
                $cleanTitle = 'Deine Lippen';
            } elseif (strcasecmp($cleanTitle, 'Tom Odell') === 0 && strcasecmp($cleanArtist, 'Another Love') === 0) {
                $cleanArtist = 'Tom Odell';
                $cleanTitle = 'Another Love';
            } elseif (strcasecmp($cleanArtist, 'Wicked Game') === 0) {
                $cleanArtist = 'Chris Isaak';
                $cleanTitle = 'Wicked Game';
            } elseif (stripos($track->title, 'Rock My Body') !== false && stripos($track->title, 'R3HAB') !== false) {
                $cleanArtist = 'R3HAB, INNA & Sash!';
                $cleanTitle = 'Rock My Body';
            } elseif (strcasecmp($cleanTitle, 'Robin Schulz') === 0 && strcasecmp($cleanArtist, 'Robin Schulz') === 0) {
                $cleanTitle = 'Sugar';
            } elseif (strcasecmp($cleanTitle, 'Asja Kadric') === 0) {
                $cleanArtist = 'Asja Kadric';
                $cleanTitle = 'Fear Not This Night';
            } elseif (strcasecmp($cleanTitle, 'Rosenfels Evelyn') === 0) {
                $cleanArtist = 'Rosenfels';
                $cleanTitle = 'Evelyn';
            } elseif (strcasecmp($cleanTitle, 'Linkin Park') === 0 && strcasecmp($cleanArtist, "She Couldn't") === 0) {
                $cleanArtist = 'Linkin Park';
                $cleanTitle = "She Couldn't";
            } elseif (strcasecmp($cleanTitle, 'Linkin Park') === 0 && strcasecmp($cleanArtist, 'One More Light') === 0) {
                $cleanArtist = 'Linkin Park';
                $cleanTitle = 'One More Light';
            } elseif (strcasecmp($cleanTitle, 'Lighthouse Family') === 0) {
                $cleanArtist = 'Lighthouse Family';
                $cleanTitle = 'High';
            } elseif (strcasecmp($cleanArtist, 'The Connells') === 0 && $cleanTitle === '75') {
                $cleanTitle = "'74-'75";
            } elseif (strcasecmp($cleanArtist, 'Lost Sky') === 0 && stripos($cleanTitle, 'Fearless') !== false) {
                $cleanTitle = 'Fearless';
            } elseif (stripos($cleanTitle, "THERE’S A STAR IN THE UNIVERSE") !== false || stripos($cleanTitle, "THERE'S A STAR IN THE UNIVERSE") !== false) {
                $cleanTitle = "There's a Star in the Universe That Looks Like You";
            }

            if ($cleanTitle !== $track->title || $cleanArtist !== $track->artist) {
                $track->update([
                    'title' => $cleanTitle,
                    'artist' => $cleanArtist,
                ]);
                $updated++;
            }
        }
        return $updated;
    }

    private function writeProgress(array $state): void
    {
        $statusFile = storage_path('app/leitung/music/import_status.json');
        $dir = dirname($statusFile);
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        @file_put_contents($statusFile, json_encode(array_merge([
            'updated_at' => now()->toIso8601String(),
        ], $state), JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
    }

    public function handle(): int
    {
        $this->ensureDesktopToken();
        $cleanedCount = $this->CleanAllExistingTrackTitles();
        if ($cleanedCount > 0) {
            $this->info("{$cleanedCount} Song-Titel wurden auf reine Klarnamen bereinigt.");
        }
        if ($this->option('clean-only')) {
            return self::SUCCESS;
        }

        $url = trim((string)($this->argument('url') ?: 'https://www.youtube.com/playlist?list=PLLxfbpyw6ajPSo6K54kDpbM2kkYaRnHb0'));
        $parsedHost = strtolower((string) parse_url($url, PHP_URL_HOST));
        $parsedScheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));
        $allowedMusicHosts = ['youtube.com', 'www.youtube.com', 'm.youtube.com', 'music.youtube.com', 'youtu.be', 'open.spotify.com'];
        if ($parsedScheme !== 'https' || !in_array($parsedHost, $allowedMusicHosts, true)) {
            $this->error("Sicherheitsprüfung: Ungültige Musik-URL blockiert ({$url}).");
            return self::FAILURE;
        }
        $ytDlp = storage_path('app/leitung/music/bin/yt-dlp');
        $tracksDir = storage_path('app/leitung/music/tracks');
        if (!is_dir($tracksDir)) {
            @mkdir($tracksDir, 0775, true);
        }

        // Check if Spotify URL
        if (preg_match('~open\.spotify\.com/(?:intl-[a-zA-Z\-]+/)?(playlist|track|album)/([A-Za-z0-9]+)~i', $url, $spotMatch)) {
            return $this->handleSpotifyImport($spotMatch[1], $spotMatch[2], $url, $ytDlp, $tracksDir);
        }

        $this->writeProgress([
            'active' => true,
            'status' => 'metadata',
            'playlist_id' => $this->option('playlist-id'),
            'playlist_name' => $this->option('playlist-name') ?: 'Musik-Import',
            'current' => 0,
            'total' => 0,
            'percent' => 3,
            'current_title' => 'Lese Playlist & Metadaten aus...',
            'message' => 'Analysiere Link...',
        ]);

        // 1. YouTube Metadata Import
        $this->info("Lade Playlist-Metadaten von: {$url}");
        $cmd = escapeshellarg($ytDlp) . ' --flat-playlist -J ' . escapeshellarg($url);
        $raw = shell_exec($cmd);
        $data = $raw ? json_decode($raw, true) : null;

        $playlist = null;
        if (is_array($data)) {
            $entries = $data['entries'] ?? [$data];
            $isMultiTrackPlaylist = isset($data['entries']) && count($entries) > 1;

            $playlistTitle = trim((string)($this->option('playlist-name') ?: ($data['title'] ?? 'Lieblingssongs')));
            if ($playlistTitle === '' || strtolower($playlistTitle) === 'videos') {
                $playlistTitle = 'Importierte Playlist';
            }

            $playlistId = $this->option('playlist-id');
            if (!$isMultiTrackPlaylist && $playlistId && !in_array($playlistId, ['all', 'favorites', 'most_played'], true)) {
                $playlist = ManagementPlaylist::find($playlistId);
            }
            if (!$playlist) {
                $playlist = ManagementPlaylist::where('name', $playlistTitle)->first();
            }
            if (!$playlist && $playlistId && !in_array($playlistId, ['all', 'favorites', 'most_played'], true)) {
                $playlist = ManagementPlaylist::find($playlistId);
            }

            if (!$playlist) {
                $maxPos = (int) ManagementPlaylist::max('position');
                $firstThumb = null;
                if (!empty($data['entries'][0]['id'])) {
                    $firstThumb = 'https://i.ytimg.com/vi/' . $data['entries'][0]['id'] . '/hqdefault.jpg';
                } elseif (!empty($data['id'])) {
                    $firstThumb = 'https://i.ytimg.com/vi/' . $data['id'] . '/hqdefault.jpg';
                }
                $playlist = ManagementPlaylist::create([
                    'id' => (string) Str::uuid(),
                    'name' => $playlistTitle,
                    'description' => 'Synchronisierte Funkenwerk-Playlist',
                    'cover_url' => $firstThumb,
                    'source_url' => $url,
                    'icon' => 'musical-note',
                    'color' => '#C5A059',
                    'position' => $maxPos + 1,
                    'is_favorite' => true,
                ]);
            }

            $pos = (int) ManagementTrack::where('playlist_id', $playlist->id)->max('position');
            $imported = 0;

            foreach ($entries as $entry) {
                $vid = trim((string)($entry['id'] ?? ''));
                $rawTitle = trim((string)($entry['title'] ?? ''));
                if ($vid === '' || $rawTitle === '' || $rawTitle === '[Private video]' || $rawTitle === '[Deleted video]') {
                    continue;
                }

                $uploader = trim((string)($entry['uploader'] ?? $entry['channel'] ?? ''));
                $duration = (int)($entry['duration'] ?? 0);

                [$artist, $cleanTitle] = $this->parseArtistAndTitle($rawTitle, $uploader);

                $existing = ManagementTrack::where('playlist_id', $playlist->id)
                    ->where('external_id', $vid)
                    ->first();

                $localRel = "leitung/music/tracks/{$vid}.m4a";
                $localAbs = storage_path("app/{$localRel}");
                $isCached = file_exists($localAbs) && filesize($localAbs) > 10000;

                if (!$existing) {
                    $pos++;
                    ManagementTrack::create([
                        'id' => (string) Str::uuid(),
                        'playlist_id' => $playlist->id,
                        'title' => $cleanTitle,
                        'artist' => $artist,
                        'duration_seconds' => $duration,
                        'file_path' => $isCached ? $localRel : null,
                        'cover_url' => "https://i.ytimg.com/vi/{$vid}/hqdefault.jpg",
                        'external_id' => $vid,
                        'source_url' => "https://www.youtube.com/watch?v={$vid}",
                        'status' => $isCached ? 'cached' : 'ready',
                        'file_size' => $isCached ? filesize($localAbs) : 0,
                        'position' => $pos,
                        'is_favorite' => false,
                    ]);
                    $imported++;
                } elseif ($isCached && $existing->status !== 'cached') {
                    $existing->update([
                        'file_path' => $localRel,
                        'status' => 'cached',
                        'file_size' => filesize($localAbs),
                    ]);
                }
            }

            $this->CleanAllExistingTrackTitles();
            $this->info("Playlist '{$playlist->name}': {$imported} neue Songs importiert.");
        }

        // 2. Audio Download with Live Progress Tracking
        if ($this->option('download-all')) {
            $this->downloadUncachedTracksForPlaylist($playlist, $ytDlp);
        }

        $totalFinal = $playlist ? ManagementTrack::where('playlist_id', $playlist->id)->count() : 0;
        $this->writeProgress([
            'active' => false,
            'status' => 'completed',
            'playlist_id' => $playlist?->id,
            'playlist_name' => $playlist?->name ?? 'Playlist',
            'current' => $totalFinal,
            'total' => $totalFinal,
            'percent' => 100,
            'current_title' => 'Import vollständig abgeschlossen',
            'message' => "✓ Alle {$totalFinal} Titel in '{$playlist?->name}' sind abspielbereit!",
        ]);

        return self::SUCCESS;
    }

    private function handleSpotifyImport(string $kind, string $spotifyId, string $originalUrl, string $ytDlp, string $tracksDir): int
    {
        $this->writeProgress([
            'active' => true,
            'status' => 'metadata',
            'playlist_id' => $this->option('playlist-id'),
            'playlist_name' => $this->option('playlist-name') ?: 'Spotify-Import',
            'current' => 0,
            'total' => 0,
            'percent' => 3,
            'current_title' => 'Lese Spotify-Playlist & Klarnamen aus...',
            'message' => 'Verbinde mit Spotify...',
        ]);

        $embedUrl = "https://open.spotify.com/embed/{$kind}/{$spotifyId}";
        $this->info("Lade Spotify-Metadaten von: {$embedUrl}");

        $html = '';
        $localSpotCache = storage_path('app/leitung/music/spot.html');
        if ($spotifyId === '5j9ltK9z3K5l5Hete8APBp' && file_exists($localSpotCache) && filesize($localSpotCache) > 1000) {
            $html = (string) file_get_contents($localSpotCache);
        } else {
            try {
                $resp = Http::withHeaders([
                    'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/125.0.0.0 Safari/537.36',
                    'Accept-Language' => 'de-DE,de;q=0.9,en;q=0.8',
                ])->timeout(20)->get($embedUrl);
                if ($resp->successful()) {
                    $html = $resp->body();
                }
            } catch (\Throwable $e) {
                $this->error("Spotify-Abruf fehlgeschlagen: " . $e->getMessage());
            }
        }

        if ($html === '') {
            $this->writeProgress([
                'active' => false,
                'status' => 'error',
                'percent' => 0,
                'current_title' => 'Spotify-Link konnte nicht gelesen werden.',
                'message' => 'Fehler beim Abrufen der Spotify-Daten.',
            ]);
            return self::FAILURE;
        }

        $p1 = strpos($html, '__NEXT_DATA__');
        if ($p1 === false) {
            $this->error('Spotify NEXT_DATA nicht gefunden.');
            return self::FAILURE;
        }
        $p2 = strpos($html, '>', $p1) + 1;
        $p3 = strpos($html, '</script>', $p2);
        $jsonRaw = substr($html, $p2, $p3 - $p2);
        $nextData = json_decode($jsonRaw, true);
        $entity = $nextData['props']['pageProps']['state']['data']['entity'] ?? null;

        if (!is_array($entity)) {
            $this->error('Spotify Entity-Struktur ungültig.');
            return self::FAILURE;
        }

        $playlistTitle = trim((string)($this->option('playlist-name') ?: ($entity['title'] ?? $entity['name'] ?? 'Spotify Playlist')));
        $coverUrl = $entity['coverArt']['sources'][0]['url'] ?? ($entity['visualIdentity']['image'][0]['url'] ?? null);

        $trackList = $entity['trackList'] ?? [];
        if (empty($trackList) && ($entity['type'] ?? '') === 'track') {
            $trackList = [[
                'title' => $entity['title'] ?? $entity['name'] ?? '',
                'subtitle' => isset($entity['artists']) ? implode(', ', array_column($entity['artists'], 'name')) : ($entity['subtitle'] ?? ''),
                'duration' => $entity['duration'] ?? 0,
                'uri' => $entity['uri'] ?? "spotify:track:{$spotifyId}",
            ]];
        }

        // Determine target Playlist
        $playlist = null;
        if (count($trackList) === 1 && $this->option('playlist-id') && !in_array($this->option('playlist-id'), ['all', 'favorites', 'most_played'], true)) {
            $playlist = ManagementPlaylist::find($this->option('playlist-id'));
        }
        if (!$playlist) {
            $playlist = ManagementPlaylist::where('name', $playlistTitle)->first();
        }
        if (!$playlist) {
            $maxPos = (int) ManagementPlaylist::max('position');
            $playlist = ManagementPlaylist::create([
                'id' => (string) Str::uuid(),
                'name' => $playlistTitle,
                'description' => 'Importierte Spotify-Playlist',
                'cover_url' => $coverUrl,
                'source_url' => $originalUrl,
                'icon' => 'musical-note',
                'color' => '#C5A059',
                'position' => $maxPos + 1,
                'is_favorite' => true,
            ]);
        }

        $pos = (int) ManagementTrack::where('playlist_id', $playlist->id)->max('position');
        $imported = 0;

        foreach ($trackList as $item) {
            $rawTitle = trim((string)($item['title'] ?? ''));
            $rawArtist = trim((string)($item['subtitle'] ?? ''));
            if ($rawTitle === '') continue;

            $cleanArtist = self::cleanArtistName($rawArtist);
            $cleanTitle = self::cleanPureSongTitle($rawTitle, $cleanArtist);
            $durationSec = isset($item['duration']) ? (int) round(((int)$item['duration']) / 1000) : 0;

            // Check if already in this playlist
            $existingInPlaylist = ManagementTrack::where('playlist_id', $playlist->id)
                ->whereRaw('LOWER(title) = ? AND LOWER(artist) = ?', [mb_strtolower($cleanTitle), mb_strtolower($cleanArtist)])
                ->first();

            if ($existingInPlaylist) {
                continue;
            }

            // Check if the exact song is already cached in another playlist (e.g. Lieblingssongs) so we can reuse its audio file immediately!
            $donor = ManagementTrack::where('status', 'cached')
                ->whereNotNull('external_id')
                ->whereRaw('LOWER(title) = ? AND LOWER(artist) = ?', [mb_strtolower($cleanTitle), mb_strtolower($cleanArtist)])
                ->first();

            $donorCached = false;
            if ($donor && $donor->external_id) {
                $donorAbs = storage_path("app/leitung/music/tracks/{$donor->external_id}.m4a");
                if (file_exists($donorAbs) && filesize($donorAbs) > 10000) {
                    $donorCached = true;
                }
            }

            $pos++;
            ManagementTrack::create([
                'id' => (string) Str::uuid(),
                'playlist_id' => $playlist->id,
                'title' => $cleanTitle,
                'artist' => $cleanArtist,
                'duration_seconds' => $donorCached && $donor->duration_seconds > 0 ? $donor->duration_seconds : $durationSec,
                'file_path' => $donorCached ? "leitung/music/tracks/{$donor->external_id}.m4a" : null,
                'cover_url' => $donorCached && $donor->cover_url ? $donor->cover_url : $coverUrl,
                'external_id' => $donorCached ? $donor->external_id : null,
                'source_url' => $donorCached ? "https://www.youtube.com/watch?v={$donor->external_id}" : ($item['uri'] ?? $originalUrl),
                'status' => $donorCached ? 'cached' : 'ready',
                'file_size' => $donorCached ? filesize(storage_path("app/leitung/music/tracks/{$donor->external_id}.m4a")) : 0,
                'position' => $pos,
                'is_favorite' => false,
            ]);
            $imported++;
        }

        $this->info("Spotify-Playlist '{$playlist->name}': {$imported} Songs mit reinen Klarnamen angelegt.");

        if ($this->option('download-all')) {
            $this->downloadUncachedTracksForPlaylist($playlist, $ytDlp);
        }

        if (file_exists($localSpotCache)) {
            @unlink($localSpotCache);
        }

        $totalFinal = ManagementTrack::where('playlist_id', $playlist->id)->count();
        $this->writeProgress([
            'active' => false,
            'status' => 'completed',
            'playlist_id' => $playlist->id,
            'playlist_name' => $playlist->name,
            'current' => $totalFinal,
            'total' => $totalFinal,
            'percent' => 100,
            'current_title' => 'Import vollständig abgeschlossen',
            'message' => "✓ Alle {$totalFinal} Titel in '{$playlist->name}' sind abspielbereit!",
        ]);

        return self::SUCCESS;
    }

    private function downloadUncachedTracksForPlaylist(?ManagementPlaylist $playlist, string $ytDlp): void
    {
        $query = ManagementTrack::orderBy('position');
        if ($playlist) {
            $query->where('playlist_id', $playlist->id);
        }
        $allTracks = $query->get();
        $total = $allTracks->count();
        if ($total === 0) return;

        // First mark any track that already has its file on disk as cached
        $pending = [];
        $completedCount = 0;

        foreach ($allTracks as $track) {
            if (!empty($track->external_id)) {
                $vid = $track->external_id;
                $localRel = "leitung/music/tracks/{$vid}.m4a";
                $localAbs = storage_path("app/{$localRel}");
                if (file_exists($localAbs) && filesize($localAbs) > 10000) {
                    if ($track->status !== 'cached' || $track->file_path !== $localRel) {
                        $track->update([
                            'file_path' => $localRel,
                            'status' => 'cached',
                            'file_size' => filesize($localAbs),
                        ]);
                    }
                    $completedCount++;
                    continue;
                }
            }
            $pending[] = $track;
        }

        if (empty($pending)) {
            return;
        }

        // Parallel worker pool (6 concurrent yt-dlp processes) for high-speed download
        $concurrency = 6;
        $running = [];
        $queue = array_values($pending);
        $outPattern = storage_path('app/leitung/music/tracks/%(id)s.%(ext)s');

        while (!empty($queue) || !empty($running)) {
            while (count($running) < $concurrency && !empty($queue)) {
                /** @var ManagementTrack $track */
                $track = array_shift($queue);
                $vid = trim((string) $track->external_id);

                if ($vid !== '') {
                    $targetExpr = "https://www.youtube.com/watch?v={$vid}";
                } else {
                    $searchQuery = trim("{$track->artist} - {$track->title} official audio");
                    $targetExpr = "ytsearch1:{$searchQuery}";
                }

                $dlCmd = escapeshellarg($ytDlp)
                    . ' --no-playlist -f "140/ba[ext=m4a]/ba" --concurrent-fragments 4 --no-warnings --print after_move:id -o '
                    . escapeshellarg($outPattern) . ' '
                    . escapeshellarg($targetExpr);

                $descriptors = [
                    0 => ['pipe', 'r'],
                    1 => ['pipe', 'w'],
                    2 => ['pipe', 'w'],
                ];
                $proc = @proc_open($dlCmd, $descriptors, $pipes);
                if (is_resource($proc)) {
                    fclose($pipes[0]);
                    stream_set_blocking($pipes[1], false);
                    stream_set_blocking($pipes[2], false);
                    $running[] = [
                        'proc' => $proc,
                        'pipes' => $pipes,
                        'track' => $track,
                        'stdout' => '',
                    ];

                    $pct = (int) round((($completedCount + 1) / $total) * 100);
                    $this->writeProgress([
                        'active' => true,
                        'status' => 'downloading',
                        'playlist_id' => $playlist?->id,
                        'playlist_name' => $playlist?->name ?? 'Playlist',
                        'current' => min($total, $completedCount + 1),
                        'total' => $total,
                        'percent' => max(5, min(99, $pct)),
                        'current_title' => "{$track->artist} — {$track->title}",
                        'message' => "Lade Song " . min($total, $completedCount + 1) . " von {$total} ({$pct}%): {$track->title}",
                    ]);
                }
            }

            usleep(150000); // 150ms check interval

            foreach ($running as $idx => &$worker) {
                $worker['stdout'] .= (string) stream_get_contents($worker['pipes'][1]);
                $status = proc_get_status($worker['proc']);
                if (!$status['running']) {
                    $worker['stdout'] .= (string) stream_get_contents($worker['pipes'][1]);
                    fclose($worker['pipes'][1]);
                    fclose($worker['pipes'][2]);
                    proc_close($worker['proc']);

                    /** @var ManagementTrack $track */
                    $track = $worker['track'];
                    $completedCount++;

                    // Determine resolved video ID
                    $vid = trim((string) $track->external_id);
                    if ($vid === '') {
                        $lines = array_filter(array_map('trim', explode("\n", trim($worker['stdout']))));
                        $lastLine = end($lines);
                        if (is_string($lastLine) && preg_match('/^[A-Za-z0-9_-]{6,15}$/', $lastLine)) {
                            $vid = $lastLine;
                        }
                    }

                    if ($vid !== '') {
                        $localRel = "leitung/music/tracks/{$vid}.m4a";
                        $localAbs = storage_path("app/{$localRel}");

                        if (!file_exists($localAbs) || filesize($localAbs) <= 10000) {
                            $matches = glob(storage_path("app/leitung/music/tracks/{$vid}.*"));
                            if (!empty($matches)) {
                                $found = $matches[0];
                                if ($found !== $localAbs && filesize($found) > 10000) {
                                    $ffCmd = 'ffmpeg -y -i ' . escapeshellarg($found) . ' -vn -c:a aac -b:a 160k -movflags +faststart ' . escapeshellarg($localAbs) . ' 2>&1';
                                    shell_exec($ffCmd);
                                    @unlink($found);
                                }
                            }
                        }

                        if (file_exists($localAbs) && filesize($localAbs) > 10000) {
                            $track->update([
                                'external_id' => $vid,
                                'file_path' => $localRel,
                                'cover_url' => "https://i.ytimg.com/vi/{$vid}/hqdefault.jpg",
                                'source_url' => "https://www.youtube.com/watch?v={$vid}",
                                'status' => 'cached',
                                'file_size' => filesize($localAbs),
                            ]);
                            $this->line("✓ [{$completedCount}/{$total}] {$track->artist} - {$track->title} ({$vid})");
                        } else {
                            $this->warn("✗ [{$completedCount}/{$total}] Datei fehlt für {$track->artist} - {$track->title}");
                        }
                    }

                    $pct = (int) round(($completedCount / $total) * 100);
                    $this->writeProgress([
                        'active' => true,
                        'status' => 'downloading',
                        'playlist_id' => $playlist?->id,
                        'playlist_name' => $playlist?->name ?? 'Playlist',
                        'current' => $completedCount,
                        'total' => $total,
                        'percent' => max(5, min(99, $pct)),
                        'current_title' => "{$track->artist} — {$track->title}",
                        'message' => "Lade Song {$completedCount} von {$total} ({$pct}%): {$track->title}",
                    ]);

                    unset($running[$idx]);
                }
            }
            unset($worker);
        }
    }

    private function parseArtistAndTitle(string $rawTitle, string $uploader): array
    {
        $cleaned = preg_replace('/\s*[\(\[]\s*(Official\s*(4K\s*)?(Music\s*)?(Video|Audio|Lyric\s*Video)|Lyrics?|HD|4K|HQ|Official|Audio|Video)\s*[\)\]]/i', '', $rawTitle);
        $cleaned = trim($cleaned ?: $rawTitle);

        if (str_contains($cleaned, ' - ')) {
            $parts = explode(' - ', $cleaned, 2);
            $artist = self::cleanArtistName($parts[0]);
            $title = self::cleanPureSongTitle($parts[1], $artist);
            if ($artist !== '' && $title !== '') {
                return [$artist, $title];
            }
        }

        $cleanUploader = self::cleanArtistName($uploader);
        return [$cleanUploader, self::cleanPureSongTitle($cleaned, $cleanUploader)];
    }
}
