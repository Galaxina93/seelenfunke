<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use App\Models\System\SystemNeuralNode;
use Illuminate\Support\Facades\DB;

class GenerateSystemBrainMap extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'system:brain:generate';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generates a JSON dependency graph of the entire project for the Neural Error Analysis 3D visualization.';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Scanning project for Neural Error Analysis Brain Map...');

        $directories = [
            app_path(),
            resource_path('views'),
            base_path('routes'),
            config_path(),
        ];

        $nodes = [];
        $links = [];
        $fileIndex = []; // maps class names / view names to their node IDs (relative paths)

        $allFiles = [];
        foreach ($directories as $dir) {
            if (File::exists($dir)) {
                $files = File::allFiles($dir);
                foreach ($files as $file) {
                    if (in_array($file->getExtension(), ['php'])) {
                        $allFiles[] = $file;
                    }
                }
            }
        }

        $this->info('Found ' . count($allFiles) . ' files. Parsing nodes...');

        // 1. Build Nodes and Index
        foreach ($allFiles as $file) {
            $relativePath = str_replace(base_path() . '/', '', $file->getRealPath());
            $content = file_get_contents($file->getRealPath());

            // Determine Group based on path
            $group = 1;
            if (str_contains($relativePath, 'app/Models')) $group = 2;
            elseif (str_contains($relativePath, 'app/Http/Controllers')) $group = 3;
            elseif (str_contains($relativePath, 'app/Livewire')) $group = 4;
            elseif (str_contains($relativePath, 'resources/views')) $group = 5;
            elseif (str_contains($relativePath, 'routes/')) $group = 6;
            elseif (str_contains($relativePath, 'config/')) $group = 7;
            elseif (str_contains($relativePath, 'app/Services')) $group = 8;
            elseif (str_contains($relativePath, 'app/Console')) $group = 9;

            // Generate a readable label
            $label = basename($relativePath);
            
            $nodes[] = [
                'id' => $relativePath,
                'name' => $label,
                'path' => $relativePath,
                'val' => 1, // Size of the node
                'group' => $group
            ];

            // Indexing for relationship mapping
            if (str_ends_with($relativePath, '.blade.php')) {
                // e.g. resources/views/livewire/shop/ai/ai-widget.blade.php -> livewire.shop.ai.ai-widget
                $viewName = str_replace(['resources/views/', '.blade.php', '/'], ['', '', '.'], $relativePath);
                $fileIndex['view:' . $viewName] = $relativePath;
            } else {
                // e.g. app/Models/User.php -> App\Models\User
                $namespaceMatch = [];
                preg_match('/namespace\s+([^;]+);/', $content, $namespaceMatch);
                $classNameMatch = [];
                preg_match('/class\s+([A-Za-z0-9_]+)/', $content, $classNameMatch);
                
                if (!empty($namespaceMatch[1]) && !empty($classNameMatch[1])) {
                    $fullClass = trim($namespaceMatch[1]) . '\\' . trim($classNameMatch[1]);
                    $fileIndex['class:' . $fullClass] = $relativePath;
                    // Also index the short name for naive matching
                    $fileIndex['shortclass:' . trim($classNameMatch[1])] = $relativePath;
                }
            }
        }

        $this->info('Parsing relationships (links)...');

        // 2. Build Links (Edges)
        foreach ($allFiles as $file) {
            $relativePath = str_replace(base_path() . '/', '', $file->getRealPath());
            $content = file_get_contents($file->getRealPath());

            if (str_ends_with($relativePath, '.blade.php')) {
                // Check for includes, extends, livewire tags, components
                
                // @include('...') or @extends('...')
                preg_match_all('/@(include|extends)\(\s*[\'"]([^\'"]+)[\'"]/', $content, $matches);
                if (!empty($matches[2])) {
                    foreach ($matches[2] as $viewName) {
                        if (isset($fileIndex['view:' . $viewName])) {
                            $links[] = ['source' => $relativePath, 'target' => $fileIndex['view:' . $viewName]];
                        }
                    }
                }

                // <livewire:component-name />
                preg_match_all('/<livewire:([a-zA-Z0-9\-\.]+)[\s\/>]/', $content, $matches);
                if (!empty($matches[1])) {
                    foreach ($matches[1] as $lwName) {
                        // livewire component names can be 'shop.ai.ai-widget'
                        // Often corresponds to App\Livewire\Shop\Ai\AiWidget or similar
                        // We map it to view first: livewire.shop.ai.ai-widget
                        $expectedView = 'livewire.' . str_replace('/', '.', $lwName);
                        if (isset($fileIndex['view:' . $expectedView])) {
                            $links[] = ['source' => $relativePath, 'target' => $fileIndex['view:' . $expectedView]];
                        }
                    }
                }
                
                // <x-component-name />
                preg_match_all('/<x-([a-zA-Z0-9\-\.]+)[\s\/>]/', $content, $matches);
                if (!empty($matches[1])) {
                    foreach ($matches[1] as $xName) {
                        $expectedView = 'components.' . str_replace('/', '.', $xName);
                        if (isset($fileIndex['view:' . $expectedView])) {
                            $links[] = ['source' => $relativePath, 'target' => $fileIndex['view:' . $expectedView]];
                        }
                    }
                }

            } else {
                // PHP Files: check use statements, injections, instantiations
                preg_match_all('/use\s+([^;]+);/', $content, $matches);
                if (!empty($matches[1])) {
                    foreach ($matches[1] as $usedClass) {
                        $usedClass = trim(str_replace(' as ', '', preg_replace('/ as [A-Za-z0-9_]+/', '', $usedClass)));
                        // sometimes it has traits like 'use TraitName;' which is not full namespace.
                        if (isset($fileIndex['class:' . $usedClass])) {
                            $links[] = ['source' => $relativePath, 'target' => $fileIndex['class:' . $usedClass]];
                        } elseif (isset($fileIndex['shortclass:' . $usedClass])) {
                            $links[] = ['source' => $relativePath, 'target' => $fileIndex['shortclass:' . $usedClass]];
                        }
                    }
                }
                
                // view('view.name')
                preg_match_all('/view\([\'"]([^\'"]+)[\'"]/', $content, $matches);
                if (!empty($matches[1])) {
                    foreach ($matches[1] as $viewName) {
                        if (isset($fileIndex['view:' . $viewName])) {
                            $links[] = ['source' => $relativePath, 'target' => $fileIndex['view:' . $viewName]];
                        }
                    }
                }
            }
        }

        // Deduplicate links
        $uniqueLinks = [];
        $linkKeys = [];
        foreach ($links as $link) {
            // Avoid self links
            if ($link['source'] === $link['target']) continue;

            $key = $link['source'] . '|' . $link['target'];
            if (!in_array($key, $linkKeys)) {
                $linkKeys[] = $key;
                $uniqueLinks[] = $link;
            }
        }

        // Output JSON
        $graph = [
            'nodes' => $nodes,
            'links' => $uniqueLinks,
            'generated_at' => now()->toDateTimeString()
        ];

        $jsonOutput = json_encode($graph, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        File::put(storage_path('app/public/system-brain-map.json'), $jsonOutput);

        $this->info('Success! Brain map generated at storage/app/public/system-brain-map.json');
        $this->info('Nodes: ' . count($nodes));
        $this->info('Links: ' . count($uniqueLinks));

        $this->info('Updating SystemNeuralNode database...');
        
        // Truncate outside transaction (causes implicit commit in MySQL)
        SystemNeuralNode::truncate();
        
        DB::beginTransaction();
        try {
            // Prepare link mapping (source -> array of targets)
            $depsBySource = [];
            foreach ($uniqueLinks as $link) {
                if (!isset($depsBySource[$link['source']])) {
                    $depsBySource[$link['source']] = [];
                }
                $depsBySource[$link['source']][] = $link['target'];
            }

            $dbRecords = [];
            foreach ($nodes as $node) {
                $path = base_path($node['path']);
                $content = file_exists($path) ? file_get_contents($path) : '';
                
                $methods = [];
                $properties = [];
                
                if (str_ends_with($node['path'], '.php') && !str_ends_with($node['path'], '.blade.php')) {
                    // Extract basic methods
                    preg_match_all('/(?:public|protected|private)\s+(?:static\s+)?function\s+([a-zA-Z0-9_]+)\s*\(/', $content, $mMatches);
                    if (!empty($mMatches[1])) {
                        $methods = $mMatches[1];
                    }
                }

                $dbRecords[] = [
                    'id' => Str::uuid()->toString(),
                    'file_path' => $node['path'],
                    'name' => $node['name'],
                    'group_id' => $node['group'],
                    'dependencies' => json_encode($depsBySource[$node['path']] ?? []),
                    'methods' => json_encode($methods),
                    'properties' => json_encode($properties),
                    'content_hash' => md5($content),
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }

            // Insert in chunks to avoid memory limit issues
            foreach (array_chunk($dbRecords, 200) as $chunk) {
                SystemNeuralNode::insert($chunk);
            }

            DB::commit();
            $this->info('Database updated successfully.');
        } catch (\Exception $e) {
            DB::rollBack();
            $this->error('Failed to update database: ' . $e->getMessage());
        }
    }
}
