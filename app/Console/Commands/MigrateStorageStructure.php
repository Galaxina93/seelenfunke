<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\File;

class MigrateStorageStructure extends Command
{
    protected $signature = 'storage:migrate-structure';
    protected $description = 'Migrates existing storage folders to the new 11-category structure and updates DB records.';

    protected $localMappings = [
        'ai' => 'agenten/ai',
        'ai-artifacts' => 'agenten/ai-artifacts',
        'ai-artifacts-broken' => 'agenten/ai-artifacts-broken',
        'ai-chat-uploads' => 'agenten/ai-chat-uploads',
        'crm' => 'leitung/crm',
        'erictresor' => 'buchhaltung/erictresor',
        'invoices' => 'buchhaltung/invoices',
        'livewire-tmp' => 'system/livewire-tmp',
        // 'marketing' => 'marketing/marketing', // removed because it causes rename error and is already in root
        'products-secure' => 'produkte/products-secure',
        'reports' => 'shopverwaltung/reports',
        'tax_exports' => 'buchhaltung/tax_exports',
        'Mein-Seelenfunke-db-backup' => 'system/Mein-Seelenfunke-db-backup',
    ];

    // Mapping for public storage (storage/app/public)
    protected $publicMappings = [
        'agents/avatars' => 'agenten/avatars',
        'ai-chat-uploads' => 'agenten/ai-chat-uploads',
        'blog' => 'marketing/blog',
        'cart-uploads' => 'bestellungen/cart-uploads',
        'contracts' => 'leitung/contracts',
        'dhl_labels' => 'bestellungen/dhl_labels',
        'exports' => 'systemsteuerung/exports',
        'financial' => 'buchhaltung/financial',
        'images' => 'shopverwaltung/images',
        'invoices' => 'buchhaltung/invoices',
        'person_profiles' => 'leitung/person_profiles',
        'product-templates' => 'produkte/product-templates',
        'products' => 'products',
        'receipts' => 'buchhaltung/receipts',
        'reviews' => 'produkte/reviews',
        'snapshots' => 'system/snapshots',
        'testdata' => 'system/testdata',
        'tickets' => 'support/tickets',
        'tmp' => 'system/tmp',
        'user' => 'dashboard/user',
        'wiki' => 'support/wiki',
    ];

    public function handle()
    {
        $this->info('Starting Storage Structure Migration...');

        // 1. Move Physical Directories
        $this->moveDirectories('local', $this->localMappings, storage_path('app'));
        $this->moveDirectories('public', $this->publicMappings, storage_path('app/public'));

        // 2. Update Database Records
        $this->updateDatabase();

        $this->info('Migration completed successfully!');
    }

    protected function moveDirectories($disk, $mappings, $basePath)
    {
        $this->info("Moving {$disk} directories...");
        foreach ($mappings as $oldPath => $newPath) {
            $oldFullPath = "{$basePath}/{$oldPath}";
            $newFullPath = "{$basePath}/{$newPath}";

            if (File::exists($oldFullPath)) {
                // Create parent directory of new path if it doesn't exist
                $newParent = dirname($newFullPath);
                if (!File::exists($newParent)) {
                    File::makeDirectory($newParent, 0755, true);
                }

                if (!File::exists($newFullPath)) {
                    File::move($oldFullPath, $newFullPath);
                    $this->info("Moved: {$oldPath} -> {$newPath}");
                } else {
                    $this->warn("Target exists, skipping: {$newPath}");
                }
            }
        }
    }

    protected function updateDatabase()
    {
        $this->info("Updating Database Records...");
        
        $replacements = array_merge($this->localMappings, $this->publicMappings);

        // Define which columns in which tables need to be updated
        $updates = [
            'products' => ['preview_image_path', 'three_d_model_path', 'three_d_background_path', 'digital_download_path', 'preview_image', 'image_url'],
            'product_templates' => ['preview_image'],
            'ai_agents' => ['profile_picture', 'image_path'],
            'users' => ['profile_photo_path'],
            'marketing_blog_posts' => ['featured_image', 'header_image', 'image_url'],
            'marketing_landing_pages' => ['header_image'],
            'accounting_invoices' => ['pdf_path', 'xml_path'],
            'accounting_contracts' => ['contract_file_path'],
            'management_tickets' => ['path'],
            'system_users' => ['photo_path'],
            'orders' => ['shipping_label_path']
        ];

        foreach ($updates as $table => $columns) {
            if (DB::getSchemaBuilder()->hasTable($table)) {
                $this->info("Updating table: {$table}");
                foreach ($columns as $column) {
                    if (DB::getSchemaBuilder()->hasColumn($table, $column)) {
                        foreach ($replacements as $oldPath => $newPath) {
                            // Update values that start with the old path
                            DB::table($table)
                                ->where($column, 'like', $oldPath . '/%')
                                ->update([
                                    $column => DB::raw("REPLACE({$column}, '{$oldPath}/', '{$newPath}/')")
                                ]);
                                
                            // Update exact matches
                            DB::table($table)
                                ->where($column, $oldPath)
                                ->update([$column => $newPath]);
                        }
                    }
                }
            }
        }
        
        $this->info('Database updates finished.');
    }
}
