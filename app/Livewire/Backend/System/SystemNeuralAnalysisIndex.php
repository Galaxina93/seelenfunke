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
        $content = "# Neurale Struktur-Analyse\n\n";
        $content .= "**Datei:** `{$node->file_path}`\n";
        $content .= "**Modul-Typ:** " . $this->getGroupName($node->group_id) . "\n";
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
    }

    private function getGroupName($groupId)
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
