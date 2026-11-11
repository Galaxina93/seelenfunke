<?php

namespace App\Livewire\Shop\Management;

use App\Livewire\Traits\WithDepartmentTheming;
use App\Models\Management\ManagementPlaylist;
use App\Models\Management\ManagementTrack;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithFileUploads;

#[Layout('components.layouts.backend_layout')]
class ManagementMusic extends Component
{
    use WithDepartmentTheming;
    use WithFileUploads;

    public string $themingDepartment = 'Leitung';

    // Active Playlist UUID or Smart View ('favorites', 'most_played', 'all')
    public ?string $activePlaylistId = null;
    public string $searchQuery = '';
    protected bool $autoPlayRandomOnSync = false;

    // New Playlist creation
    public string $newPlaylistName = '';

    // Import URL (YouTube Song or Playlist)
    public string $importUrl = '';
    public ?string $importFeedback = null;

    // Upload Audio File
    public $uploadedAudioFile = null;

    // Right-side Track Inspector / Editor
    public ?string $selectedTrackId = null;
    public string $editTitle = '';
    public string $editArtist = '';
    public ?string $editPlaylistId = null;

    public function mount(): void
    {
        $first = ManagementPlaylist::orderBy('position')->first();
        $this->activePlaylistId = $first ? $first->id : 'all';
    }

    public function selectPlaylist(string $id): void
    {
        $this->activePlaylistId = $id;
        $this->searchQuery = '';
        $this->importFeedback = null;
        $this->autoPlayRandomOnSync = true;
    }

    public function createPlaylist(): void
    {
        $name = trim($this->newPlaylistName);
        if ($name === '') return;

        // If user pasted a YouTube URL into the New Playlist box, auto-trigger import!
        if (str_starts_with($name, 'http://') || str_starts_with($name, 'https://')) {
            $this->importUrl = $name;
            $this->newPlaylistName = '';
            $this->importYoutubeUrl();
            return;
        }

        $maxPos = (int) ManagementPlaylist::max('position');
        $pl = ManagementPlaylist::create([
            'id' => (string) Str::uuid(),
            'name' => $name,
            'icon' => 'musical-note',
            'color' => '#C5A059',
            'position' => $maxPos + 1,
        ]);

        $this->newPlaylistName = '';
        $this->activePlaylistId = $pl->id;
    }

    public function importPlaylistFromSidebar(): void
    {
        $val = trim($this->newPlaylistName);
        if ($val === '') return;
        $this->importUrl = $val;
        $this->newPlaylistName = '';
        $this->importYoutubeUrl();
    }

    public function deletePlaylist(string $id): void
    {
        $pl = ManagementPlaylist::find($id);
        if ($pl) {
            $pl->delete();
        }
        $first = ManagementPlaylist::orderBy('position')->first();
        $this->activePlaylistId = $first ? $first->id : 'all';
    }

    public function importYoutubeUrl(): void
    {
        $url = trim($this->importUrl);
        $parsedHost = strtolower((string) parse_url($url, PHP_URL_HOST));
        $parsedScheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));
        $allowedMusicHosts = ['youtube.com', 'www.youtube.com', 'm.youtube.com', 'music.youtube.com', 'youtu.be', 'open.spotify.com'];
        if ($url === '' || !filter_var($url, FILTER_VALIDATE_URL) || $parsedScheme !== 'https' || !in_array($parsedHost, $allowedMusicHosts, true)) {
            $this->importFeedback = 'Bitte eine gültige HTTPS-URL von YouTube oder Spotify eingeben.';
            return;
        }

        $ytDlp = storage_path('app/bin/yt-dlp');
        if (!file_exists($ytDlp)) {
            $this->importFeedback = 'Fehler: yt-dlp Binary nicht verfügbar.';
            return;
        }

        $statusFile = storage_path('app/leitung/music/import_status.json');
        if (!is_dir(dirname($statusFile))) {
            @mkdir(dirname($statusFile), 0775, true);
        }
        @file_put_contents($statusFile, json_encode([
            'active' => true,
            'status' => 'starting',
            'playlist_id' => (!in_array($this->activePlaylistId, ['all', 'favorites', 'most_played', null], true)) ? $this->activePlaylistId : null,
            'playlist_name' => 'YouTube-Import',
            'current' => 0,
            'total' => 0,
            'percent' => 4,
            'current_title' => 'Lese YouTube-Playlist & Metadaten aus...',
            'message' => 'Starte Hintergrund-Download auf dem Seelenfunke-Server...',
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
        if (!in_array($this->activePlaylistId, ['all', 'favorites', 'most_played', null], true)) {
            $cmdParts[] = '--playlist-id=' . escapeshellarg((string) $this->activePlaylistId);
        }
        $logFile = escapeshellarg(storage_path('logs/funkenwerk_music_import.log'));
        $bgCmd = 'nohup ' . implode(' ', $cmdParts) . ' > ' . $logFile . ' 2>&1 &';
        exec($bgCmd);

        $this->importUrl = '';
        $this->importFeedback = 'YouTube-Import gestartet! Fortschritt wird live angezeigt.';
    }

    public function updatedUploadedAudioFile(): void
    {
        if (!$this->uploadedAudioFile) return;

        $targetPlaylist = (!in_array($this->activePlaylistId, ['all', 'favorites', 'most_played', null], true))
            ? ManagementPlaylist::find($this->activePlaylistId)
            : ManagementPlaylist::orderBy('position')->first();

        if (!$targetPlaylist) {
            $targetPlaylist = ManagementPlaylist::create([
                'id' => (string) Str::uuid(),
                'name' => 'Eigene Uploads',
                'icon' => 'musical-note',
                'color' => '#C5A059',
                'position' => 1,
            ]);
        }

        $ext = strtolower($this->uploadedAudioFile->getClientOriginalExtension());
        if (!in_array($ext, ['mp3', 'm4a', 'aac', 'wav', 'ogg', 'flac'], true)) {
            $this->importFeedback = 'Nicht unterstütztes Dateiformat.';
            return;
        }

        $trackId = (string) Str::uuid();
        $storedPath = $this->uploadedAudioFile->storeAs('leitung/music/tracks', $trackId . '.' . $ext, 'local');

        $rawName = pathinfo($this->uploadedAudioFile->getClientOriginalName(), PATHINFO_FILENAME);
        $artist = 'Eigener Upload';
        $title = $rawName;
        if (str_contains($rawName, ' - ')) {
            [$a, $t] = explode(' - ', $rawName, 2);
            $artist = trim($a);
            $title = trim($t);
        }

        $maxPos = (int) ManagementTrack::where('playlist_id', $targetPlaylist->id)->max('position');
        ManagementTrack::create([
            'id' => $trackId,
            'playlist_id' => $targetPlaylist->id,
            'title' => $title,
            'artist' => $artist,
            'duration_seconds' => 0,
            'file_path' => $storedPath,
            'status' => 'cached',
            'file_size' => $this->uploadedAudioFile->getSize(),
            'position' => $maxPos + 1,
        ]);

        $this->uploadedAudioFile = null;
        $this->importFeedback = "Audiodatei '{$title}' hochgeladen.";
    }

    public function toggleFavorite(string $trackId): void
    {
        $t = ManagementTrack::find($trackId);
        if ($t) {
            $newFav = !$t->is_favorite;
            $t->update(['is_favorite' => $newFav]);
            if (!empty($t->external_id)) {
                ManagementTrack::where('external_id', $t->external_id)->update(['is_favorite' => $newFav]);
            } else {
                ManagementTrack::where('title', $t->title)->where('artist', $t->artist)->update(['is_favorite' => $newFav]);
            }
            foreach ([storage_path('app/leitung/music/playlists_catalog.json'), base_path('funkenwerk/playlists_cache.json')] as $jsonFile) {
                if (file_exists($jsonFile) && is_writable($jsonFile)) {
                    $decoded = json_decode((string) @file_get_contents($jsonFile), true);
                    if (is_array($decoded) && isset($decoded['data']) && is_array($decoded['data'])) {
                        foreach ($decoded['data'] as &$pl) {
                            if (!isset($pl['tracks']) || !is_array($pl['tracks'])) continue;
                            foreach ($pl['tracks'] as &$tr) {
                                $sameId = ($tr['id'] ?? '') === $t->id;
                                $sameExt = !empty($t->external_id) && ($tr['external_id'] ?? '') === $t->external_id;
                                if ($sameId || $sameExt) {
                                    $tr['is_favorite'] = $newFav;
                                }
                            }
                        }
                        unset($pl, $tr);
                        @file_put_contents($jsonFile, json_encode($decoded, JSON_UNESCAPED_UNICODE));
                    }
                }
            }
        }
    }

    public function selectTrackForEdit(string $trackId): void
    {
        $t = ManagementTrack::find($trackId);
        if (!$t) return;
        $this->selectedTrackId = $t->id;
        $this->editTitle = $t->title;
        $this->editArtist = (string) $t->artist;
        $this->editPlaylistId = $t->playlist_id;
    }

    public function closeTrackInspector(): void
    {
        $this->selectedTrackId = null;
    }

    public function saveTrackDetails(): void
    {
        if (!$this->selectedTrackId) return;
        $t = ManagementTrack::find($this->selectedTrackId);
        if (!$t) return;

        $t->update([
            'title' => trim($this->editTitle) ?: $t->title,
            'artist' => trim($this->editArtist) ?: 'Unbekannter Künstler',
            'playlist_id' => $this->editPlaylistId ?: $t->playlist_id,
        ]);
    }

    public function deleteTrack(string $trackId): void
    {
        $t = ManagementTrack::find($trackId);
        if ($t) {
            if ($this->selectedTrackId === $t->id) {
                $this->selectedTrackId = null;
            }
            $t->delete();
        }
    }

    public function render()
    {
        $playlists = ManagementPlaylist::withCount('tracks')->orderBy('position')->get();

        $query = ManagementTrack::query()->with('playlist');

        if ($this->searchQuery !== '') {
            $q = '%' . trim($this->searchQuery) . '%';
            $query->where(function ($sub) use ($q) {
                $sub->where('title', 'like', $q)
                    ->orWhere('artist', 'like', $q);
            });
        } else {
            if ($this->activePlaylistId === 'favorites') {
                $query->where('is_favorite', true);
            } elseif ($this->activePlaylistId === 'most_played') {
                $query->orderByDesc('play_count');
            } elseif ($this->activePlaylistId !== 'all' && $this->activePlaylistId !== null) {
                $query->where('playlist_id', $this->activePlaylistId);
            }
        }

        if ($this->activePlaylistId !== 'most_played') {
            $query->orderBy('position', 'asc')->orderBy('created_at', 'asc');
        }

        $tracks = $query->get();
        $tracksPayload = $tracks->map(fn($t) => [
            'id' => $t->id,
            'title' => $t->title,
            'artist' => $t->artist ?: 'Unbekannter Künstler',
            'duration' => (int) $t->duration_seconds,
            'cover' => $t->cover_url ?: '/shop/projekt/logo/funkenwerk.png',
            'url' => '/admin/music/stream/' . $t->id,
        ])->values()->all();

        $this->dispatch(
            'music-view-tracks-synced',
            tracks: $tracksPayload,
            playlistId: $this->activePlaylistId,
            autoPlayRandom: $this->autoPlayRandomOnSync
        );
        $this->autoPlayRandomOnSync = false;

        $activePlaylist = (!in_array($this->activePlaylistId, ['all', 'favorites', 'most_played', null], true))
            ? $playlists->firstWhere('id', $this->activePlaylistId)
            : null;

        $selectedTrack = $this->selectedTrackId ? ManagementTrack::find($this->selectedTrackId) : null;

        $smartCounts = [
            'all' => ManagementTrack::count(),
            'favorites' => ManagementTrack::where('is_favorite', true)->count(),
            'cached' => ManagementTrack::where('status', 'cached')->count(),
        ];

        $statusFile = storage_path('app/leitung/music/import_status.json');
        $importStatus = ['active' => false, 'percent' => 0, 'message' => '', 'current_title' => ''];
        if (file_exists($statusFile)) {
            $decoded = json_decode((string) @file_get_contents($statusFile), true);
            if (is_array($decoded)) {
                $importStatus = array_merge($importStatus, $decoded);
            }
        }

        return view('livewire.shop.management.management-music', [
            'playlists' => $playlists,
            'tracks' => $tracks,
            'tracksPayload' => $tracksPayload,
            'activePlaylist' => $activePlaylist,
            'selectedTrack' => $selectedTrack,
            'smartCounts' => $smartCounts,
            'importStatus' => $importStatus,
        ]);
    }
}
