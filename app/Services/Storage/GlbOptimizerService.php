<?php

namespace App\Services\Storage;

use App\Models\System\SystemLog;
use Illuminate\Support\Facades\Log;

class GlbOptimizerService
{
    /**
     * Fast, zero-overhead PHP binary header inspection of a .glb file.
     */
    public function inspectGlb(string $absolutePath): array
    {
        if (str_contains($absolutePath, '..') || str_contains($absolutePath, "\0") || strtolower(pathinfo($absolutePath, PATHINFO_EXTENSION)) !== 'glb') {
            return [
                'exists' => false,
                'error' => 'Ungültiger Dateipfad oder Dateityp (nur .glb erlaubt).',
            ];
        }

        if (is_link($absolutePath) || !is_file($absolutePath) || !is_readable($absolutePath)) {
            return [
                'exists' => false,
            ];
        }

        $realFile = realpath($absolutePath);
        $publicStorageRoot = realpath(storage_path('app/public'));
        $testingStorageRoot = realpath(storage_path('framework/testing'));
        $publicRoot = realpath(public_path());
        $inAllowedRoot = $realFile && (
            ($publicStorageRoot && str_starts_with($realFile, $publicStorageRoot . DIRECTORY_SEPARATOR))
            || ($testingStorageRoot && str_starts_with($realFile, $testingStorageRoot . DIRECTORY_SEPARATOR))
            || ($publicRoot && str_starts_with($realFile, $publicRoot . DIRECTORY_SEPARATOR))
        );

        if (!$inAllowedRoot) {
            return [
                'exists' => false,
                'error' => '3D-Datei liegt außerhalb des autorisierten Storage-Verzeichnisses.',
            ];
        }

        $sizeBytes = (int) filesize($absolutePath);
        $sizeMb = round($sizeBytes / 1024 / 1024, 2);
        $sizeFormatted = $sizeBytes >= 1048576
            ? number_format($sizeBytes / 1048576, 2, ',', '.') . ' MB'
            : number_format($sizeBytes / 1024, 1, ',', '.') . ' KB';

        $vertices = 0;
        $triangles = 0;
        $texturesCount = 0;
        $texturesBytes = 0;
        $generator = 'Unbekannt';
        $extras = [];

        try {
            $fp = fopen($absolutePath, 'rb');
            if ($fp) {
                $headerRaw = fread($fp, 20);
                if (strlen($headerRaw) === 20) {
                    $header = unpack('a4magic/Vversion/Vlength/VchunkLen/a4chunkType', $headerRaw);
                    if (($header['magic'] ?? '') === 'glTF' && ($header['chunkLen'] ?? 0) > 0 && ($header['chunkLen'] ?? 0) < 10 * 1024 * 1024) {
                        $jsonStr = fread($fp, $header['chunkLen']);
                        $json = json_decode($jsonStr, true);
                        if (is_array($json)) {
                            $generator = $json['asset']['generator'] ?? 'Standard glTF';
                            $extras = $json['asset']['extras'] ?? [];
                            $texturesCount = count($json['images'] ?? []);

                            foreach (($json['images'] ?? []) as $img) {
                                if (isset($img['bufferView'], $json['bufferViews'][$img['bufferView']])) {
                                    $texturesBytes += (int) ($json['bufferViews'][$img['bufferView']]['byteLength'] ?? 0);
                                }
                            }

                            foreach (($json['meshes'] ?? []) as $mesh) {
                                foreach (($mesh['primitives'] ?? []) as $prim) {
                                    if (isset($prim['attributes']['POSITION'], $json['accessors'][$prim['attributes']['POSITION']])) {
                                        $vertices += (int) ($json['accessors'][$prim['attributes']['POSITION']]['count'] ?? 0);
                                    }
                                    if (isset($prim['indices'], $json['accessors'][$prim['indices']])) {
                                        $triangles += (int) round(($json['accessors'][$prim['indices']]['count'] ?? 0) / 3);
                                    }
                                }
                            }
                        }
                    }
                }
                fclose($fp);
            }
        } catch (\Throwable $e) {
            Log::warning('GLB Header Inspection Warning: ' . $e->getMessage());
        }

        $isMarkedOptimized = !empty($extras['seelenfunke_optimized']);
        $needsOptimization = $sizeMb > 3.0 || $triangles > 65000;
        $isOptimized = $isMarkedOptimized || (!$needsOptimization && $sizeBytes > 0);

        $originalSizeMb = isset($extras['original_size_bytes'])
            ? round(((int) $extras['original_size_bytes']) / 1024 / 1024, 2)
            : null;
        $originalTriangles = isset($extras['original_triangles'])
            ? (int) $extras['original_triangles']
            : null;

        $savedPercent = ($originalSizeMb && $originalSizeMb > $sizeMb)
            ? round((1 - ($sizeMb / $originalSizeMb)) * 100, 1)
            : null;

        return [
            'exists' => true,
            'size_bytes' => $sizeBytes,
            'size_mb' => $sizeMb,
            'size_formatted' => $sizeFormatted,
            'vertices' => $vertices,
            'triangles' => $triangles,
            'textures_count' => $texturesCount,
            'textures_size_kb' => round($texturesBytes / 1024, 1),
            'generator' => $generator,
            'is_optimized' => $isOptimized,
            'is_marked_optimized' => $isMarkedOptimized,
            'needs_optimization' => $needsOptimization,
            'original_size_mb' => $originalSizeMb,
            'original_triangles' => $originalTriangles,
            'saved_percent' => $savedPercent,
            'last_modified' => date('d.m.Y H:i', (int) filemtime($absolutePath)),
        ];
    }

    /**
     * Optimizes a .glb file in-place using scripts/optimize-glb.mjs (weld + MeshoptSimplifier + sharp texture resize).
     */
    public function optimizeGlb(string $absolutePath, int $targetMaxTriangles = 55000, int $maxTextureSize = 2048): array
    {
        if (str_contains($absolutePath, '..') || str_contains($absolutePath, "\0") || strtolower(pathinfo($absolutePath, PATHINFO_EXTENSION)) !== 'glb') {
            return [
                'success' => false,
                'error' => 'Ungültiger Dateipfad oder Dateityp (nur .glb erlaubt).',
            ];
        }

        if (is_link($absolutePath) || !is_file($absolutePath) || !is_writable($absolutePath)) {
            return [
                'success' => false,
                'error' => '3D-Datei existiert nicht oder ist schreibgeschützt: ' . basename($absolutePath),
            ];
        }

        $realFile = realpath($absolutePath);
        $publicStorageRoot = realpath(storage_path('app/public'));
        $testingStorageRoot = realpath(storage_path('framework/testing'));
        $publicRoot = realpath(public_path());
        $inAllowedRoot = $realFile && (
            ($publicStorageRoot && str_starts_with($realFile, $publicStorageRoot . DIRECTORY_SEPARATOR))
            || ($testingStorageRoot && str_starts_with($realFile, $testingStorageRoot . DIRECTORY_SEPARATOR))
            || ($publicRoot && str_starts_with($realFile, $publicRoot . DIRECTORY_SEPARATOR))
        );

        if (!$inAllowedRoot) {
            return [
                'success' => false,
                'error' => '3D-Datei liegt außerhalb des autorisierten Storage-Verzeichnisses.',
            ];
        }

        $scriptPath = base_path('scripts/optimize-glb.mjs');
        if (!is_file($scriptPath)) {
            return [
                'success' => false,
                'error' => 'Optimierungs-Skript (scripts/optimize-glb.mjs) wurde nicht gefunden.',
            ];
        }

        $nodeBinary = trim((string) shell_exec('which node 2>/dev/null')) ?: 'node';

        $cmd = sprintf(
            '%s %s %s %s %d %d 2>&1',
            escapeshellarg($nodeBinary),
            escapeshellarg($scriptPath),
            escapeshellarg($absolutePath),
            escapeshellarg($absolutePath),
            $targetMaxTriangles,
            $maxTextureSize
        );

        $rawOutput = (string) shell_exec($cmd);
        $lines = array_filter(array_map('trim', explode("\n", $rawOutput)));

        $jsonResult = null;
        foreach (array_reverse($lines) as $line) {
            if (str_starts_with($line, '{') && str_ends_with($line, '}')) {
                $decoded = json_decode($line, true);
                if (is_array($decoded)) {
                    $jsonResult = $decoded;
                    break;
                }
            }
        }

        if (!$jsonResult || empty($jsonResult['success'])) {
            $errMsg = $jsonResult['error'] ?? ('Unbekannter Fehler bei GLB-Optimierung: ' . substr($rawOutput, 0, 300));
            Log::error('GLB Optimization failed for ' . $absolutePath . ': ' . $errMsg);
            return [
                'success' => false,
                'error' => $errMsg,
            ];
        }

        clearstatcache(true, $absolutePath);

        if (class_exists(SystemLog::class)) {
            try {
                SystemLog::create([
                    'type' => 'system',
                    'action_id' => 'storage:glb_optimized',
                    'title' => '3D-Modell (.glb) optimiert: ' . basename($absolutePath),
                    'message' => sprintf(
                        'Größe reduziert von %s MB auf %s MB (-%s%%). Dreiecke: %s -> %s.',
                        $jsonResult['original_size_mb'] ?? '?',
                        $jsonResult['new_size_mb'] ?? '?',
                        $jsonResult['saved_percent'] ?? '0',
                        number_format((int) ($jsonResult['original_triangles'] ?? 0), 0, ',', '.'),
                        number_format((int) ($jsonResult['new_triangles'] ?? 0), 0, ',', '.')
                    ),
                    'status' => 'success',
                    'started_at' => now(),
                    'finished_at' => now(),
                ]);
            } catch (\Throwable $e) {
                // Ignore logging errors
            }
        }

        return $jsonResult;
    }
}
