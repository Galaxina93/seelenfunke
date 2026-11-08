<?php

namespace Database\Seeders;

use App\Models\Management\ManagementPlaylist;
use App\Models\Management\ManagementTrack;
use Illuminate\Database\Seeder;

class ManagementMusicSeeder extends Seeder
{
    public function run(): void
    {
        $catalogPath = storage_path('app/leitung/music/playlists_catalog.json');
        if (!file_exists($catalogPath)) {
            return;
        }

        $raw = file_get_contents($catalogPath);
        $decoded = json_decode((string) $raw, true);
        $playlists = $decoded['data'] ?? (is_array($decoded) ? $decoded : []);
        if (empty($playlists)) {
            return;
        }

        foreach ($playlists as $pIdx => $plData) {
            if (empty($plData['id']) || empty($plData['name'])) {
                continue;
            }

            $playlist = ManagementPlaylist::updateOrCreate(
                ['id' => $plData['id']],
                [
                    'name' => $plData['name'],
                    'description' => $plData['description'] ?? null,
                    'cover_url' => $plData['cover_url'] ?? null,
                    'source_url' => $plData['source_url'] ?? null,
                    'icon' => $plData['icon'] ?? 'musical-note',
                    'color' => $plData['color'] ?? '#C5A059',
                    'position' => (int) ($plData['position'] ?? ($pIdx + 1)),
                    'is_favorite' => (bool) ($plData['is_favorite'] ?? false),
                ]
            );

            $tracks = $plData['tracks'] ?? [];
            foreach ($tracks as $tIdx => $trData) {
                if (empty($trData['id']) || empty($trData['title'])) {
                    continue;
                }

                $extId = $trData['external_id'] ?? null;
                $filePath = $extId ? "leitung/music/tracks/{$extId}.m4a" : ($trData['file_path'] ?? null);

                ManagementTrack::updateOrCreate(
                    ['id' => $trData['id']],
                    [
                        'playlist_id' => $playlist->id,
                        'title' => $trData['title'],
                        'artist' => $trData['artist'] ?? 'Unbekannter Künstler',
                        'duration_seconds' => (int) ($trData['duration_seconds'] ?? 0),
                        'file_path' => $filePath,
                        'cover_url' => $trData['cover_url'] ?? null,
                        'external_id' => $extId,
                        'source_url' => $trData['source_url'] ?? null,
                        'status' => $trData['status'] ?? 'cached',
                        'file_size' => (int) ($trData['file_size'] ?? 0),
                        'position' => (int) ($trData['position'] ?? ($tIdx + 1)),
                        'is_favorite' => (bool) ($trData['is_favorite'] ?? false),
                        'play_count' => (int) ($trData['play_count'] ?? 0),
                    ]
                );
            }
        }
    }
}
