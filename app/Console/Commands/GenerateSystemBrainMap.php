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
            $blueprintData = null;

            // Indexing for relationship mapping
            if (str_ends_with($relativePath, '.blade.php')) {
                // e.g. resources/views/livewire/shop/ai/ai-widget.blade.php -> livewire.shop.ai.ai-widget
                $viewName = str_replace(['resources/views/', '.blade.php', '/'], ['', '', '.'], $relativePath);
                $fileIndex['view:' . $viewName] = $relativePath;
            } else {
                // e.g. app/Models/User.php -> App\Models\User (supports class, trait, interface, enum)
                $namespaceMatch = [];
                preg_match('/namespace\s+([^;]+);/', $content, $namespaceMatch);
                $classNameMatch = [];
                preg_match('/\b(?:class|trait|interface|enum)\s+([A-Za-z0-9_]+)/', $content, $classNameMatch);
                
                if (!empty($namespaceMatch[1]) && !empty($classNameMatch[1])) {
                    $fullClass = trim($namespaceMatch[1]) . '\\' . trim($classNameMatch[1]);
                    $fileIndex['class:' . $fullClass] = $relativePath;
                    // Also index the short name for naive matching
                    $fileIndex['shortclass:' . trim($classNameMatch[1])] = $relativePath;

                    if (
                        str_starts_with($relativePath, 'app/Models/')
                        && class_exists($fullClass)
                        && is_subclass_of($fullClass, \App\Support\Blueprint\Contracts\HasSystemBlueprint::class)
                    ) {
                        try {
                            $blueprintData = $fullClass::blueprint()->toArray();
                        } catch (\Throwable) {
                            $blueprintData = null;
                        }
                    }
                }
            }

            $nodeEntry = [
                'id' => $relativePath,
                'name' => $label,
                'path' => $relativePath,
                'val' => 1, // Size of the node
                'group' => $group,
            ];
            if ($blueprintData !== null) {
                $nodeEntry['blueprint'] = $blueprintData;
            }
            $nodes[] = $nodeEntry;
        }

        $this->info('Parsing relationships (links)...');

        // 2. Build Links (Edges)
        foreach ($allFiles as $file) {
            $relativePath = str_replace(base_path() . '/', '', $file->getRealPath());
            $content = file_get_contents($file->getRealPath());

            // Inline FQCN references (works in both PHP, Routes, and Blade @php blocks)
            preg_match_all('/\\\\?(App\\\\[A-Za-z0-9_\\\\]+)/', $content, $fqcnMatches);
            if (!empty($fqcnMatches[1])) {
                foreach ($fqcnMatches[1] as $fqcnRaw) {
                    $cleanFqcn = trim($fqcnRaw, '\\');
                    if (isset($fileIndex['class:' . $cleanFqcn])) {
                        $links[] = ['source' => $relativePath, 'target' => $fileIndex['class:' . $cleanFqcn]];
                    }
                }
            }

            if (str_ends_with($relativePath, '.blade.php')) {
                // @include('...'), @includeIf('...'), @extends('...')
                preg_match_all('/@(?:include|includeIf|extends)\(\s*[\'"]([^\'"]+)[\'"]/', $content, $matches);
                if (!empty($matches[1])) {
                    foreach ($matches[1] as $viewName) {
                        if (isset($fileIndex['view:' . $viewName])) {
                            $links[] = ['source' => $relativePath, 'target' => $fileIndex['view:' . $viewName]];
                        }
                    }
                }

                // <livewire:component-name /> and @livewire('component-name')
                $lwNames = [];
                if (preg_match_all('/<livewire:([a-zA-Z0-9\-\.\/]+)[\s\/>]/', $content, $matches)) {
                    $lwNames = array_merge($lwNames, $matches[1]);
                }
                if (preg_match_all('/@livewire\(\s*[\'"]([a-zA-Z0-9\-\.\/]+)[\'"]/', $content, $matchesAt)) {
                    $lwNames = array_merge($lwNames, $matchesAt[1]);
                }
                foreach (array_unique($lwNames) as $lwName) {
                    $normalizedLw = str_replace('/', '.', $lwName);
                    $expectedView = 'livewire.' . $normalizedLw;
                    if (isset($fileIndex['view:' . $expectedView])) {
                        $links[] = ['source' => $relativePath, 'target' => $fileIndex['view:' . $expectedView]];
                    }
                    $studlyParts = array_map(fn ($seg) => Str::studly($seg), explode('.', $normalizedLw));
                    $expectedLwClass = 'App\\Livewire\\' . implode('\\', $studlyParts);
                    if (isset($fileIndex['class:' . $expectedLwClass])) {
                        $links[] = ['source' => $relativePath, 'target' => $fileIndex['class:' . $expectedLwClass]];
                    }
                }

                // <x-component-name />
                preg_match_all('/<x-([a-zA-Z0-9\-\.]+)[\s\/>]/', $content, $matches);
                if (!empty($matches[1])) {
                    foreach ($matches[1] as $xName) {
                        if (str_starts_with($xName, 'heroicon-')) {
                            continue;
                        }
                        $expectedView = 'components.' . str_replace('/', '.', $xName);
                        if (isset($fileIndex['view:' . $expectedView])) {
                            $links[] = ['source' => $relativePath, 'target' => $fileIndex['view:' . $expectedView]];
                        }
                    }
                }
            } else {
                // PHP Files: check use statements, injections, instantiations
                preg_match_all('/\buse\s+([A-Za-z0-9_\\\\\s,{}]+);/', $content, $matches);
                if (!empty($matches[1])) {
                    foreach ($matches[1] as $usedClause) {
                        // Support grouped imports like App\Models\{A, B}
                        if (str_contains($usedClause, '{') && preg_match('/^([A-Za-z0-9_\\\\]+)\\\\\{([^}]+)\}/', trim($usedClause), $grp)) {
                            $prefix = rtrim(trim($grp[1]), '\\') . '\\';
                            foreach (explode(',', $grp[2]) as $sub) {
                                $subClean = trim((string) preg_replace('/\s+as\s+[A-Za-z0-9_]+/i', '', trim($sub)));
                                if ($subClean !== '' && isset($fileIndex['class:' . $prefix . $subClean])) {
                                    $links[] = ['source' => $relativePath, 'target' => $fileIndex['class:' . $prefix . $subClean]];
                                }
                            }
                            continue;
                        }

                        foreach (explode(',', $usedClause) as $singleUse) {
                            $usedClass = trim((string) preg_replace('/\s+as\s+[A-Za-z0-9_]+/i', '', trim($singleUse)), " \t\n\r\0\x0B\\");
                            if ($usedClass === '') {
                                continue;
                            }
                            if (isset($fileIndex['class:' . $usedClass])) {
                                $links[] = ['source' => $relativePath, 'target' => $fileIndex['class:' . $usedClass]];
                            } elseif (isset($fileIndex['shortclass:' . $usedClass])) {
                                $links[] = ['source' => $relativePath, 'target' => $fileIndex['shortclass:' . $usedClass]];
                            }
                        }
                    }
                }

                // view('view.name') or ->layout('components.layouts....')
                preg_match_all('/(?:view|->layout)\(\s*[\'"]([^\'"]+)[\'"]/', $content, $matches);
                if (!empty($matches[1])) {
                    foreach ($matches[1] as $viewName) {
                        if (isset($fileIndex['view:' . $viewName])) {
                            $links[] = ['source' => $relativePath, 'target' => $fileIndex['view:' . $viewName]];
                        }
                    }
                }

                // require / require_once __DIR__ . '/...' (e.g. routes/web.php -> routes/partials/*.php)
                preg_match_all('/(?:require|require_once|include|include_once)\s+__DIR__\s*\.\s*[\'"]([^\'"]+\.php)[\'"]/', $content, $reqMatches);
                if (!empty($reqMatches[1])) {
                    $dirRel = dirname($relativePath);
                    foreach ($reqMatches[1] as $subRel) {
                        $candRel = ltrim(($dirRel === '.' ? '' : $dirRel . '/') . ltrim($subRel, '/'), '/');
                        if (File::exists(base_path($candRel))) {
                            $normRel = str_replace(base_path() . '/', '', realpath(base_path($candRel)) ?: base_path($candRel));
                            $links[] = ['source' => $relativePath, 'target' => $normRel];
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
        $privateDir = storage_path('app/private');
        if (!File::exists($privateDir)) {
            File::makeDirectory($privateDir, 0755, true);
        }
        File::put($privateDir . '/system-brain-map.json', $jsonOutput);
        if (File::exists(storage_path('app/public/system-brain-map.json'))) {
            File::delete(storage_path('app/public/system-brain-map.json'));
        }

        $this->info('Success! Brain map generated at storage/app/private/system-brain-map.json');
        $this->info('Nodes: ' . count($nodes));
        $this->info('Links: ' . count($uniqueLinks));

        $this->info('Updating SystemNeuralNode database...');
        
        // Delete all nodes without DDL implicit commit so active transactions stay intact
        SystemNeuralNode::query()->delete();
        
        DB::beginTransaction();
        try {
            // Prepare bidirectional link mappings (outgoing targets & incoming callers)
            $outgoingByNode = [];
            $incomingByNode = [];
            foreach ($uniqueLinks as $link) {
                $outgoingByNode[$link['source']][] = $link['target'];
                $incomingByNode[$link['target']][] = $link['source'];
            }

            $dbRecords = [];
            foreach ($nodes as $node) {
                $path = base_path($node['path']);
                $content = file_exists($path) ? file_get_contents($path) : '';

                $outgoing = array_values(array_unique($outgoingByNode[$node['path']] ?? []));
                $incoming = array_values(array_unique($incomingByNode[$node['path']] ?? []));
                sort($outgoing);
                sort($incoming);
                $allConnected = array_values(array_unique(array_merge($outgoing, $incoming)));
                sort($allConnected);

                $methods = [];
                $properties = [
                    'outgoing_dependencies' => $outgoing,
                    'incoming_dependents' => $incoming,
                ];
                if (isset($node['blueprint']) && is_array($node['blueprint'])) {
                    $properties['blueprint'] = $node['blueprint'];
                }
                
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
                    'dependencies' => json_encode($allConnected),
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

            try {
                $bpService = app(\App\Services\System\SystemBlueprintService::class);
                $audit = $bpService->auditArchitecture();
                if (!$audit['is_fully_compliant']) {
                    $bpService->reportArchitectureAuditViolations($audit);
                }
                $bpService->generateGdprArt30VvtMarkdown(true);
                $this->info('Living VVT (Art. 30 DSGVO) synchronized with Model Blueprints.');
            } catch (\Throwable $e) {
                $this->warn('Could not sync VVT Markdown: ' . $e->getMessage());
            }
        } catch (\Exception $e) {
            DB::rollBack();
            $this->error('Failed to update database: ' . $e->getMessage());
        }
    }
}
