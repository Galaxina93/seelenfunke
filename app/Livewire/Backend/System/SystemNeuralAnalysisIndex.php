<?php

namespace App\Livewire\Backend\System;

use Livewire\Component;
use Livewire\WithPagination;
use App\Models\System\SystemNeuralNode;
use Illuminate\Support\Facades\File;

class SystemNeuralAnalysisIndex extends Component
{
    use WithPagination;

    public $search = '';
    public $filterGroup = '';

    protected $updatesQueryString = ['search', 'filterGroup'];

    public function generateStructure($id)
    {
        $node = SystemNeuralNode::findOrFail($id);
        $this->createMarkdown($node);
        $this->dispatch('notify', ['type' => 'success', 'message' => 'Struktur für ' . $node->name . ' wurde generiert.']);
    }

    public function createMarkdown(SystemNeuralNode $node)
    {
        self::generateMarkdownForFile($node->file_path);
    }

    /**
     * Extracts and generates the neural structure markdown for a given absolute or relative path.
     * Returns the absolute path to the generated markdown file, or null if failed.
     */
    public static function generateMarkdownForFile(string $absolutePath): ?string
    {
        // Wandle in relativen Pfad um (falls absolut)
        $base = rtrim(base_path(), '/\\') . DIRECTORY_SEPARATOR;
        $filePathStr = str_replace($base, '', $absolutePath);
        $filePathStr = str_replace('\\', '/', $filePathStr); // Normalize

        $node = \App\Models\System\SystemNeuralNode::where('file_path', $filePathStr)->first();

        // Fallback: Wenn die Datei existiert, aber nicht in der DB ist
        if (!$node && \Illuminate\Support\Facades\File::exists(base_path($filePathStr))) {
            $methods = [];
            if (str_ends_with($filePathStr, '.php') && !str_ends_with($filePathStr, '.blade.php')) {
                $contentCode = file_get_contents(base_path($filePathStr));
                preg_match_all('/(?:public|protected|private)\s+(?:static\s+)?function\s+([a-zA-Z0-9_]+)\s*\(/', $contentCode, $mMatches);
                if (!empty($mMatches[1])) {
                    $methods = $mMatches[1];
                }
            }

            $dependencies = [];
            $jsonPath = storage_path('app/public/system-brain-map.json');
            if (\Illuminate\Support\Facades\File::exists($jsonPath)) {
                $graph = json_decode(file_get_contents($jsonPath), true);
                if (isset($graph['links'])) {
                    foreach ($graph['links'] as $link) {
                        $source = is_array($link['source']) ? ($link['source']['id'] ?? '') : $link['source'];
                        $target = is_array($link['target']) ? ($link['target']['id'] ?? '') : $link['target'];

                        if ($source === $filePathStr && !empty($target)) {
                            $dependencies[] = basename($target);
                        } elseif ($target === $filePathStr && !empty($source)) {
                            $dependencies[] = basename($source);
                        }
                    }
                    $dependencies = array_values(array_unique($dependencies));
                    sort($dependencies);
                }
            }

            $node = new \App\Models\System\SystemNeuralNode([
                'file_path' => $filePathStr,
                'name' => basename($filePathStr),
                'group_id' => 1,
                'content_hash' => md5_file(base_path($filePathStr)),
                'dependencies' => $dependencies,
                'methods' => $methods,
            ]);
        }

        if (!$node) {
            return null; // Konnte keine Node erzeugen oder finden
        }

        $content = "# Neurale Struktur-Analyse\n\n";
        $content .= "**Datei:** `{$node->file_path}`\n";
        $content .= "**Modul-Typ:** " . self::getGroupNameStatic($node->group_id) . "\n";
        $content .= "**Letzter Scan (Hash):** `{$node->content_hash}`\n\n";

        $content .= "## Abhängigkeiten (Dependencies)\n";
        if (empty($node->dependencies)) {
            $content .= "- *Keine Abhängigkeiten im Index gefunden.*\n";
        } else {
            foreach ($node->dependencies as $dep) {
                $content .= "- `{$dep}`\n";
            }
        }

        if (!empty($node->methods)) {
            $content .= "\n## Methoden\n";
            foreach ($node->methods as $method) {
                $content .= "- `{$method}()`\n";
            }
        }

        $dir = storage_path('app/public/agenten/workspace/md');
        if (!File::exists($dir)) {
            File::makeDirectory($dir, 0755, true);
        }

        $safeName = str_replace(['/', '\\'], '_', $node->file_path);
        $filePath = $dir . '/Struktur_' . $safeName . '.md';

        File::put($filePath, $content);

        return $filePath;
    }

    private static function getGroupNameStatic($groupId)
    {
        return match ($groupId) {
            2 => 'Models',
            3 => 'Controllers',
            4 => 'Livewire',
            5 => 'Views',
            6 => 'Routes',
            7 => 'Config',
            8 => 'Services',
            9 => 'Console/Commands',
            default => 'Allgemein',
        };
    }

    private function getGroupName($groupId)
    {
        return self::getGroupNameStatic($groupId);
    }

    public function render()
    {
        $query = SystemNeuralNode::query();

        if ($this->search) {
            $query->where('file_path', 'like', '%' . $this->search . '%')
                  ->orWhere('name', 'like', '%' . $this->search . '%');
        }

        if ($this->filterGroup) {
            $query->where('group_id', $this->filterGroup);
        }

        $nodes = $query->orderBy('file_path')->paginate(50);

        return view('livewire.backend.system.system-neural-analysis-index', [
            'nodes' => $nodes
        ])->layout('components.layouts.backend_layout');
    }
}
