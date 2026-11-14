<?php

namespace App\Console\Commands;

use App\Models\Marketing\MarketingBlogPost;
use App\Models\Product\Product;
use App\Models\Product\ProductTemplate;
use App\Services\ProductConfiguratorService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class SystemShopAssetManifestCommand extends Command
{
    protected $signature = 'system:shop-asset-manifest
                            {--json : Gibt das Manifest als reines JSON auf stdout aus}
                            {--write : Schreibt das Manifest atomar nach storage/app/private/shop-asset-manifest.json}';

    protected $description = 'Erzeugt das streng auf öffentliche Shop-, Produkt-, 3D-, Konfigurator- und Blog-Medien begrenzte SHA-256-Manifest für den Stage-Delta-Sync (Musik, Buchhaltung, Leitung & Privatdaten bleiben 100 % gesperrt).';

    /**
     * Verbindliche Positivliste erlaubter Wurzelpräfixe relativ zu storage/app/.
     */
    public const ALLOWED_SHOP_PREFIXES = [
        'public/produkte/',
        'produkte/products-secure/',
        'public/marketing/blog/',
        'public/shopverwaltung/',
    ];

    /**
     * Harte Sperr-Wurzelpfade relativ zu storage/app/ (100 % Lokal-Sperre).
     */
    public const BLOCKED_ROOT_PREFIXES = [
        'leitung/',
        'public/leitung/',
        'buchhaltung/',
        'public/buchhaltung/',
        'agenten/',
        'public/agenten/',
        'support/',
        'public/support/',
        'bestellungen/',
        'public/bestellungen/',
        'private/',
        'system/',
        'public/system/',
        'temp/',
        'print_queue/',
        'dhl_labels/',
    ];

    /**
     * Harte Sperr-Ordnersegmente auf jeder Pfad-Ebene.
     */
    public const BLOCKED_SEGMENTS = [
        'music',
        'buchhaltung',
        'wiki',
        'health',
        'gesundheit',
        'kameraaufnahmen',
        'person_profiles',
        'backup',
        'backups',
        'reviews',
        'documents',
        'echtgold-seelen-herz',
        '.git',
    ];

    public const ALLOWED_EXTENSIONS = [
        'png', 'jpg', 'jpeg', 'webp', 'svg', 'gif',
        'glb', 'gltf', 'mp4', 'webm', 'pdf', 'zip', 'json',
    ];

    /**
     * Prüft rein deterministisch, ob ein relativer Pfad (ab storage/app/) ein zulässiges Shop-Asset ist.
     */
    public static function isAllowedShopAssetRelativePath(string $relPath): bool
    {
        $normalized = str_replace('\\', '/', trim($relPath));

        if (
            $normalized === ''
            || strlen($normalized) > 240
            || str_starts_with($normalized, '/')
            || str_ends_with($normalized, '/')
            || str_contains($normalized, '//')
            || str_contains($normalized, '..')
            || str_contains($normalized, ':')
            || str_contains($normalized, "\0")
            || preg_match('/[\x00-\x1F\x7F]/', $normalized)
        ) {
            return false;
        }

        if (!preg_match('#^[A-Za-z0-9_./\-]+$#', $normalized)) {
            return false;
        }

        $lower = strtolower($normalized);

        foreach (self::BLOCKED_ROOT_PREFIXES as $blockedPrefix) {
            if (str_starts_with($lower, $blockedPrefix)) {
                return false;
            }
        }

        $segments = explode('/', $lower);
        foreach ($segments as $segment) {
            if ($segment === '' || $segment === '.' || $segment === '..') {
                return false;
            }
            if (in_array($segment, self::BLOCKED_SEGMENTS, true)) {
                return false;
            }
            if (str_starts_with($segment, '.env') || str_starts_with($segment, 'firebase')) {
                return false;
            }
        }

        $fileNameLower = end($segments) ?: '';
        if (
            str_contains($fileNameLower, '_orig_backup.')
            || str_ends_with($fileNameLower, '.bak')
            || str_starts_with($fileNameLower, 'echtgold-seelen-herz-')
        ) {
            return false;
        }

        $matchesAllowedPrefix = false;
        foreach (self::ALLOWED_SHOP_PREFIXES as $prefix) {
            if (str_starts_with($lower, $prefix)) {
                $matchesAllowedPrefix = true;
                break;
            }
        }

        if (!$matchesAllowedPrefix) {
            return false;
        }

        $ext = strtolower((string) pathinfo($normalized, PATHINFO_EXTENSION));
        if ($ext === '' || !in_array($ext, self::ALLOWED_EXTENSIONS, true)) {
            return false;
        }

        // .json ist ausschließlich für Vektor-/Spruch-/Preset-Manifeste des Konfigurators zulässig
        if ($ext === 'json') {
            if (
                !str_starts_with($lower, 'public/produkte/configurator/')
                && !str_starts_with($lower, 'public/produkte/configurator-presets/')
            ) {
                return false;
            }
        }

        // .zip ist ausschließlich für offizielle digitale Shop-Produkte direkt unter produkte/products-secure/ zulässig
        if ($ext === 'zip') {
            if (!preg_match('#^produkte/products-secure/[a-z0-9_\-]+\.zip$#i', $normalized)) {
                return false;
            }
        }

        return true;
    }

    public function handle(ProductConfiguratorService $configuratorService): int
    {
        $storageRoot = rtrim(str_replace('\\', '/', storage_path('app')), '/');
        $candidates = [];

        $addCandidate = function (?string $relFromStorageApp, string $reason) use (&$candidates, $storageRoot): void {
            if ($relFromStorageApp === null || trim($relFromStorageApp) === '') {
                return;
            }

            $clean = ltrim(str_replace('\\', '/', trim($relFromStorageApp)), '/');
            if (!self::isAllowedShopAssetRelativePath($clean)) {
                return;
            }

            $fullPath = $storageRoot . '/' . $clean;
            if (is_link($fullPath) || !is_file($fullPath)) {
                return;
            }

            $size = (int) @filesize($fullPath);
            if ($size <= 0 || $size > 60 * 1024 * 1024) {
                return;
            }

            if (!isset($candidates[$clean])) {
                $candidates[$clean] = [
                    'rel_path' => $clean,
                    'full_path' => $fullPath,
                    'size' => $size,
                    'reason' => $reason,
                ];
            }
        };

        $scanAllowedDirectory = function (string $relDirFromStorageApp, string $reason) use ($addCandidate, $storageRoot): void {
            $cleanDir = trim(str_replace('\\', '/', $relDirFromStorageApp), '/');
            $absDir = $storageRoot . '/' . $cleanDir;
            if (!is_dir($absDir) || is_link($absDir)) {
                return;
            }

            foreach (File::allFiles($absDir) as $fileInfo) {
                if ($fileInfo->isLink()) {
                    continue;
                }
                $absFile = str_replace('\\', '/', $fileInfo->getPathname());
                if (!Str::startsWith($absFile, $storageRoot . '/')) {
                    continue;
                }
                $rel = substr($absFile, strlen($storageRoot) + 1);
                $addCandidate($rel, $reason);
            }
        };

        // 1. Aktive Produkte (DB + Seeder-Slugs)
        $activeSlugs = [
            'seelen-kristall',
            'seelen-anhaenger',
            'weizenglas-personalisiert',
            'seelen-talisman',
            'seelen-pfote-hundemarke',
            'goldenes-seelen-herz',
            'id-armband-silber-herz',
            'id-armband-gold-herz',
            'geschenkgutschein',
            'ki-agenten-playbook',
        ];

        if (Schema::hasTable('products')) {
            $products = Product::query()->where('status', 'active')->get();
            foreach ($products as $product) {
                if (!empty($product->slug)) {
                    $activeSlugs[] = (string) $product->slug;
                }

                foreach (['preview_image_path', 'three_d_model_path', 'three_d_background_path', 'laser_runtime_path'] as $col) {
                    $val = $product->{$col} ?? null;
                    if (is_string($val) && $val !== '') {
                        $rel = str_starts_with($val, 'public/') ? $val : 'public/' . ltrim($val, '/');
                        $addCandidate($rel, 'product:' . $product->slug);
                    }
                }

                if (!empty($product->digital_file_path) && is_string($product->digital_file_path)) {
                    $dig = ltrim($product->digital_file_path, '/');
                    $addCandidate($dig, 'digital_product:' . $product->slug);
                }

                $gallery = is_array($product->media_gallery) ? $product->media_gallery : [];
                foreach ($gallery as $mediaItem) {
                    $mPath = is_array($mediaItem) ? ($mediaItem['path'] ?? $mediaItem['url'] ?? null) : (is_string($mediaItem) ? $mediaItem : null);
                    if (is_string($mPath) && $mPath !== '') {
                        $cleanMedia = preg_replace('#^/?storage/#', '', ltrim($mPath, '/'));
                        $rel = str_starts_with((string) $cleanMedia, 'public/') ? $cleanMedia : 'public/' . ltrim((string) $cleanMedia, '/');
                        $addCandidate($rel, 'product_gallery:' . $product->slug);
                    }
                }
            }
        }

        foreach (array_values(array_unique($activeSlugs)) as $slug) {
            if ($slug === '' || $slug === 'echtgold-seelen-herz') {
                continue;
            }
            $scanAllowedDirectory('public/produkte/products/' . $slug, 'active_product_dir:' . $slug);
        }

        // Digitales Hauptprodukt aus ProductSeeder
        $addCandidate('produkte/products-secure/KI_Agenten_Management_Paket.zip', 'seeder:digital_product');

        // 2. Aktive Produkt-Vorlagen (ProductTemplate / ProductTemplateSeeder)
        if (Schema::hasTable('product_templates')) {
            foreach (ProductTemplate::query()->get(['id', 'preview_image', 'configuration']) as $tpl) {
                if (!empty($tpl->preview_image) && is_string($tpl->preview_image)) {
                    $cleanImg = preg_replace('#^/?storage/#', '', ltrim($tpl->preview_image, '/'));
                    $rel = str_starts_with((string) $cleanImg, 'public/') ? $cleanImg : 'public/' . ltrim((string) $cleanImg, '/');
                    $addCandidate($rel, 'product_template');
                }
                $cfg = is_array($tpl->configuration) ? $tpl->configuration : [];
                foreach (['logo_path', 'custom_logo', 'image_path'] as $k) {
                    if (!empty($cfg[$k]) && is_string($cfg[$k])) {
                        $cleanCfg = preg_replace('#^/?storage/#', '', ltrim($cfg[$k], '/'));
                        $rel = str_starts_with((string) $cleanCfg, 'public/') ? $cleanCfg : 'public/' . ltrim((string) $cleanCfg, '/');
                        $addCandidate($rel, 'product_template_cfg');
                    }
                }
            }
        }
        $scanAllowedDirectory('public/produkte/product-templates', 'product_templates_dir');

        // 3. Konfigurator-Stammdaten (Vektoren, Sprüche, aktives Tutorial, Presets, Addons, Laser-Anleitungen)
        $scanAllowedDirectory('public/produkte/configurator/vectors', 'configurator_vectors');
        $scanAllowedDirectory('public/produkte/configurator/quotes', 'configurator_quotes');
        $scanAllowedDirectory('public/produkte/configurator-presets', 'configurator_presets');
        $scanAllowedDirectory('public/produkte/addons', 'product_addons');
        $scanAllowedDirectory('public/produkte/laser-anleitungen', 'product_laser_guides');

        $addCandidate('public/' . ProductConfiguratorService::TUTORIAL_STORAGE_DIR . '/' . ProductConfiguratorService::DEFAULT_TUTORIAL_FILENAME, 'configurator_tutorial_default');
        try {
            $tutorialCfg = $configuratorService->getTutorialConfig();
            if (!empty($tutorialCfg['path']) && is_string($tutorialCfg['path'])) {
                $addCandidate('public/' . ltrim($tutorialCfg['path'], '/'), 'configurator_tutorial_active');
            }
        } catch (\Throwable) {
            // Fallback auf Default-Video bereits registriert
        }

        // 4. Aktive Blog-Beitragsbilder (MarketingBlogPost / MarketingBlogSeeder)
        if (Schema::hasTable('marketing_blog_posts')) {
            foreach (MarketingBlogPost::query()->get(['slug', 'featured_image', 'header_image']) as $post) {
                foreach (['featured_image', 'header_image'] as $imgCol) {
                    $val = $post->{$imgCol} ?? null;
                    if (is_string($val) && $val !== '') {
                        $cleanBlog = preg_replace('#^/?storage/#', '', ltrim($val, '/'));
                        $rel = str_starts_with((string) $cleanBlog, 'public/') ? $cleanBlog : 'public/' . ltrim((string) $cleanBlog, '/');
                        $addCandidate($rel, 'blog:' . $post->slug);
                    }
                }
            }
        }
        $scanAllowedDirectory('public/marketing/blog', 'marketing_blog_dir');

        // 5. Öffentliche Shop-Über-Uns-/Gründer-Medien
        $scanAllowedDirectory('public/shopverwaltung', 'shop_about_media');

        ksort($candidates);

        $filesPayload = [];
        $totalBytes = 0;

        foreach ($candidates as $entry) {
            $sha256 = strtolower((string) @hash_file('sha256', $entry['full_path']));
            if (strlen($sha256) !== 64) {
                continue;
            }
            $totalBytes += $entry['size'];
            $filesPayload[] = [
                'rel_path' => $entry['rel_path'],
                'size' => $entry['size'],
                'sha256' => $sha256,
                'reason' => $entry['reason'],
            ];
        }

        $manifest = [
            'version' => 2,
            'scope' => 'pure_shop_assets_only',
            'generated_at' => now()->toIso8601String(),
            'policy' => 'Musik, Buchhaltung, Vertraege, Leitung, Agenten, Support und Privatdaten sind zu 100 Prozent lokal gesperrt.',
            'allowed_prefixes' => self::ALLOWED_SHOP_PREFIXES,
            'total_files' => count($filesPayload),
            'total_bytes' => $totalBytes,
            'total_mb' => round($totalBytes / (1024 * 1024), 2),
            'files' => $filesPayload,
        ];

        $encoded = json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if (!is_string($encoded)) {
            $this->error('Manifest konnte nicht als JSON serialisiert werden.');
            return self::FAILURE;
        }

        if ((bool) $this->option('write')) {
            $privateDir = storage_path('app/private');
            File::ensureDirectoryExists($privateDir);
            $targetFile = $privateDir . '/shop-asset-manifest.json';
            $tmpFile = $targetFile . '.tmp.' . bin2hex(random_bytes(4));
            File::put($tmpFile, $encoded);
            @chmod($tmpFile, 0664);
            rename($tmpFile, $targetFile);
        }

        if ((bool) $this->option('json')) {
            $this->line($encoded);
        } else {
            $this->info(sprintf(
                'Reines Shop-Asset-Manifest (v2) erstellt: %d Dateien (%s MB). Musik, Buchhaltung & Privatdaten = 0 Byte (100 %% lokal gesperrt).',
                $manifest['total_files'],
                number_format($manifest['total_mb'], 2, ',', '.')
            ));
            if ((bool) $this->option('write')) {
                $this->line('Gespeichert unter: storage/app/private/shop-asset-manifest.json');
            }
        }

        return self::SUCCESS;
    }
}
