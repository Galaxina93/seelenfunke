<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\File;
use Tests\TestCase;

class StoragePathTest extends TestCase
{
    /**
     * Die erlaubten Haupt-Ordner auf oberster Ebene in storage/app und storage/app/public
     */
    protected array $allowedRootFolders = [
        'dashboard',
        'leitung',
        'shopverwaltung',
        'support',
        'produkte',
        'marketing',
        'bestellungen',
        'buchhaltung',
        'systemsteuerung',
        'agenten',
        'system',
        'public', // system-ordner public
        '.gitignore', // system dateien
        'agents',
        'Mein-Seelenfunke-db-backup',
        'linktree',
        'ai_workspace',
        'dossiers',
        'backup-temp',
        'holidays',
        'maps',
        'places',
        'reports',
        'temp',
        'pdf_texts'
    ];

    /**
     * Teste, ob physisch nur die erlaubten Ordner im lokalen Speicher existieren.
     */
    public function test_local_storage_contains_only_allowed_folders()
    {
        $storagePath = storage_path('app');
        $this->assertOnlyAllowedFolders($storagePath);
    }

    /**
     * Teste, ob physisch nur die erlaubten Ordner im öffentlichen Speicher existieren.
     */
    public function test_public_storage_contains_only_allowed_folders()
    {
        $storagePath = storage_path('app/public');
        if (File::exists($storagePath)) {
            $this->assertOnlyAllowedFolders($storagePath);
        } else {
            $this->assertTrue(true); // Public folder might not exist in blank states
        }
    }

    /**
     * Helper Methode um einen Pfad auf erlaubte Ordner zu prüfen.
     */
    private function assertOnlyAllowedFolders(string $path)
    {
        $directories = File::directories($path);
        
        foreach ($directories as $dir) {
            $folderName = basename($dir);
            $this->assertTrue(
                in_array($folderName, $this->allowedRootFolders),
                "Nicht erlaubter Ordner gefunden: {$folderName} im Pfad {$path}. Erlaubt sind nur: " . implode(', ', $this->allowedRootFolders)
            );
        }
    }

    /**
     * Ein statischer Analyse-Test, der den gesamten 'app' und 'resources' Ordner scannt,
     * um sicherzustellen, dass keine 'Storage::disk(...)->put(...)' Befehle in falsche Ordner schreiben.
     */
    public function test_codebase_does_not_contain_hardcoded_invalid_storage_paths()
    {
        $pathsToScan = [
            app_path(),
            resource_path('views'),
            app_path('Livewire'),
            app_path('Services')
        ];

        $regexesToTest = [
            // Matches Storage::disk('local')->put('invoices/...')
            // We want to capture the string argument inside the put/get/exists/makeDirectory call
            '/Storage::disk\([\'"][a-zA-Z]+[\'"]\)->(?:put|get|exists|makeDirectory|delete|download|files|directories)\(\s*[\'"]([a-zA-Z0-9_\-]+)/',
            
            // Matches storage_path('app/invoices/...')
            '/storage_path\(\s*[\'"]app\/([a-zA-Z0-9_\-]+)/',

            // Matches asset('storage/invoices/...')
            '/asset\(\s*[\'"]storage\/([a-zA-Z0-9_\-]+)/',
            
            // Matches public_path('storage/invoices/...')
            '/public_path\(\s*[\'"]storage\/([a-zA-Z0-9_\-]+)/',
            
            // Matches $file->store('invoices/...') or $file->storeAs('invoices/...')
            '/->(?:store|storeAs|storePublicly|storePubliclyAs)\(\s*[\'"]([a-zA-Z0-9_\-]+)/',
        ];

        $errors = [];

        foreach ($pathsToScan as $dir) {
            if (!File::exists($dir)) continue;

            $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir));
            foreach ($iterator as $file) {
                if ($file->isFile() && in_array($file->getExtension(), ['php', 'blade'])) {
                    $content = file_get_contents($file->getPathname());

                    foreach ($regexesToTest as $regex) {
                        if (preg_match_all($regex, $content, $matches)) {
                            foreach ($matches[1] as $matchedFolder) {
                                // Some folders might be variables e.g., $folder, so we ignore those starting with $
                                if (strpos($matchedFolder, '$') !== false) {
                                    continue;
                                }

                                if (!in_array($matchedFolder, $this->allowedRootFolders) && $matchedFolder !== 'public') {
                                    $errors[] = "Invalid storage root folder '{$matchedFolder}' detected in file: " . $file->getPathname();
                                }
                            }
                        }
                    }
                }
            }
        }

        $this->assertEmpty($errors, "Gefundene illegale Storage Pfade im Code:\n" . implode("\n", array_unique($errors)));
    }
}
