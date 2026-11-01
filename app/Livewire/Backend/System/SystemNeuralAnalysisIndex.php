<?php

namespace App\Livewire\Backend\System;

use App\Livewire\Traits\WithDepartmentTheming;
use App\Models\System\SystemNeuralNode;
use App\Services\System\SystemBlueprintService;
use App\Support\Blueprint\Contracts\HasSystemBlueprint;
use App\Support\Blueprint\Enums\AiAccessPolicy;
use App\Support\Blueprint\Enums\DataSensitivity;
use App\Support\Blueprint\Enums\RetentionPolicy;
use App\Support\Blueprint\Enums\SystemDepartment;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\File;
use Livewire\Component;
use Livewire\WithPagination;

class SystemNeuralAnalysisIndex extends Component
{
    use WithPagination, WithDepartmentTheming;

    public string $themingDepartment = 'System';

    public $search = '';
    public $filterGroup = '';
    public $filterDepartment = '';
    public $filterRetention = '';
    public $filterAiAccess = '';
    public $filterSensitivity = '';

    protected $updatesQueryString = [
        'search',
        'filterGroup',
        'filterDepartment',
        'filterRetention',
        'filterAiAccess',
        'filterSensitivity',
    ];

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingFilterGroup(): void
    {
        $this->resetPage();
    }

    public function updatingFilterDepartment(): void
    {
        $this->resetPage();
    }

    public function updatingFilterRetention(): void
    {
        $this->resetPage();
    }

    public function updatingFilterAiAccess(): void
    {
        $this->resetPage();
    }

    public function updatingFilterSensitivity(): void
    {
        $this->resetPage();
    }

    public function resetFilters(): void
    {
        $this->reset([
            'search',
            'filterGroup',
            'filterDepartment',
            'filterRetention',
            'filterAiAccess',
            'filterSensitivity',
        ]);
        $this->resetPage();
    }

    public function boot(): void
    {
        if (!Auth::guard('admin')->check() && !\App\Services\AI\AiAuthHelper::isAdmin() && !\App\Services\AI\AiAuthHelper::isEmployee()) {
            abort(403);
        }
    }

    public function mount()
    {
        if (!Auth::guard('admin')->check() && !\App\Services\AI\AiAuthHelper::isAdmin() && !\App\Services\AI\AiAuthHelper::isEmployee()) {
            abort(403);
        }
    }

    public function rescanBrainMap(): void
    {
        if (!Auth::guard('admin')->check() && !\App\Services\AI\AiAuthHelper::isAdmin() && !\App\Services\AI\AiAuthHelper::isEmployee()) {
            abort(403);
        }

        Artisan::call('system:brain:generate');
        $this->dispatch('notify', [
            'type' => 'success',
            'message' => 'Projekt-Gehirn & alle 127 Model-Blueprints wurden erfolgreich neu synchronisiert.',
        ]);
    }

    public function exportGdprVvt()
    {
        if (!Auth::guard('admin')->check() && !\App\Services\AI\AiAuthHelper::isAdmin() && !\App\Services\AI\AiAuthHelper::isEmployee()) {
            abort(403);
        }

        $service = app(SystemBlueprintService::class);
        $markdown = $service->generateGdprArt30VvtMarkdown(true);

        $this->dispatch('notify', [
            'type' => 'success',
            'message' => 'Verzeichnis von Verarbeitungstätigkeiten (Art. 30 DSGVO) wurde aus den Model-Blueprints generiert.',
        ]);

        return response()->streamDownload(function () use ($markdown) {
            echo $markdown;
        }, 'Verzeichnis_Verarbeitungstaetigkeiten_Art30_DSGVO_' . now()->format('Y-m-d') . '.md', [
            'Content-Type' => 'text/markdown; charset=UTF-8',
        ]);
    }

    public function generateStructure($id)
    {
        if (!Auth::guard('admin')->check() && !\App\Services\AI\AiAuthHelper::isAdmin() && !\App\Services\AI\AiAuthHelper::isEmployee()) {
            abort(403);
        }

        $node = SystemNeuralNode::findOrFail($id);
        $this->createMarkdown($node);
        $this->dispatch('notify', ['type' => 'success', 'message' => 'Struktur & Blueprint für ' . $node->name . ' wurde generiert.']);
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
        if (str_contains($absolutePath, "\0")) {
            return null;
        }

        $base = rtrim(base_path(), '/\\') . DIRECTORY_SEPARATOR;
        $filePathStr = str_replace($base, '', $absolutePath);
        $filePathStr = ltrim(str_replace('\\', '/', $filePathStr), '/');

        if ($filePathStr === '' || str_contains($filePathStr, '..') || str_starts_with(basename($filePathStr), '.env')) {
            return null;
        }

        $node = SystemNeuralNode::where('file_path', $filePathStr)->first();

        $resolvedBase = realpath(base_path());
        $resolvedTarget = File::exists(base_path($filePathStr)) ? realpath(base_path($filePathStr)) : false;
        if (
            !$node
            && $resolvedBase !== false
            && $resolvedTarget !== false
            && str_starts_with($resolvedTarget, $resolvedBase . DIRECTORY_SEPARATOR)
            && is_file($resolvedTarget)
        ) {
            $methods = [];
            $properties = [];
            if (str_ends_with($filePathStr, '.php') && !str_ends_with($filePathStr, '.blade.php')) {
                $contentCode = file_get_contents(base_path($filePathStr));
                preg_match_all('/(?:public|protected|private)\s+(?:static\s+)?function\s+([a-zA-Z0-9_]+)\s*\(/', $contentCode, $mMatches);
                if (!empty($mMatches[1])) {
                    $methods = $mMatches[1];
                }

                if (
                    str_starts_with($filePathStr, 'app/Models/')
                    && preg_match('/namespace\s+([^;]+);/', $contentCode, $nsM)
                    && preg_match('/class\s+([A-Za-z0-9_]+)/', $contentCode, $clM)
                ) {
                    $fqcn = trim($nsM[1]) . '\\' . trim($clM[1]);
                    if (class_exists($fqcn) && is_subclass_of($fqcn, HasSystemBlueprint::class)) {
                        $properties['blueprint'] = $fqcn::blueprint()->toArray();
                    }
                }
            }

            $dependencies = [];
            $jsonPath = storage_path('app/private/system-brain-map.json');
            if (!File::exists($jsonPath)) {
                $jsonPath = storage_path('app/public/system-brain-map.json');
            }
            if (File::exists($jsonPath)) {
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

            $node = new SystemNeuralNode([
                'file_path' => $filePathStr,
                'name' => basename($filePathStr),
                'group_id' => str_starts_with($filePathStr, 'app/Models/') ? 2 : 1,
                'content_hash' => md5_file(base_path($filePathStr)),
                'dependencies' => $dependencies,
                'methods' => $methods,
                'properties' => $properties,
            ]);
        }

        if (!$node) {
            return null;
        }

        $content = "# Neurale Struktur-Analyse & Architektur-Blueprint\n\n";
        $content .= "**Datei:** `{$node->file_path}`\n";
        $content .= "**Modul-Typ:** " . self::getGroupNameStatic($node->group_id) . "\n";
        $content .= "**Letzter Scan (Hash):** `{$node->content_hash}`\n\n";

        $bp = $node->blueprint_data;
        if (!$bp && str_starts_with($node->file_path, 'app/Models/') && File::exists(base_path($node->file_path))) {
            $rawCode = file_get_contents(base_path($node->file_path));
            if (
                preg_match('/namespace\s+([^;]+);/', $rawCode, $nsM)
                && preg_match('/class\s+([A-Za-z0-9_]+)/', $rawCode, $clM)
            ) {
                $fqcn = trim($nsM[1]) . '\\' . trim($clM[1]);
                if (class_exists($fqcn) && is_subclass_of($fqcn, HasSystemBlueprint::class)) {
                    $bp = $fqcn::blueprint()->toArray();
                }
            }
        }

        if (is_array($bp)) {
            $content .= "## Digitales Architektur- & Compliance-Typenschild (Blueprint)\n";
            $content .= "- **Bezeichnung:** {$bp['label']}\n";
            $content .= "- **Fachlicher Zweck:** {$bp['description']}\n";
            $content .= "- **Fachabteilung:** {$bp['department_label']}\n";
            $content .= "- **Lebenszyklus:** {$bp['lifecycle_label']}\n";
            $content .= "- **DSGVO-Sensibilität:** {$bp['sensitivity_label']} ({$bp['legal_basis']})\n";
            $content .= "- **Aufbewahrung / Löschregel:** {$bp['retention_label']}\n";
            $content .= "- **GoBD-Festschreibung (10 Jahre):** " . (!empty($bp['is_gobd_protected']) ? 'Ja (Sperrung statt Löschung)' : 'Nein') . "\n";
            $content .= "- **Art. 15 DSGVO 1-Klick-Export:** " . (!empty($bp['is_gdpr_art15_exportable']) ? "Aktiv (FK: `{$bp['customer_foreign_key']}`)" : 'Nein') . "\n";
            $content .= "- **KI-Guardrail (Funki):** {$bp['ai_access_label']}\n";
            if (!empty($bp['regulation_labels'])) {
                $content .= "- **Gesetze & Verordnungen:** " . implode(', ', $bp['regulation_labels']) . "\n";
            }
            if (!empty($bp['external_channel_labels'])) {
                $content .= "- **Externe Kanäle & Hardware:** " . implode(', ', $bp['external_channel_labels']) . "\n";
            }
            $content .= "\n";
        }

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

        $safeName = str_replace(['/', '\\'], '_', $node->file_path);

        $privDir = storage_path('app/agenten/workspace/Dokumente/Berichte');
        if (!File::exists($privDir)) {
            File::makeDirectory($privDir, 0755, true);
        }
        $filePath = $privDir . '/Struktur_' . $safeName . '.md';
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

    public function render()
    {
        $query = SystemNeuralNode::query();

        if ($this->search) {
            $escaped = addcslashes((string) $this->search, '%_\\');
            $query->where(function ($q) use ($escaped) {
                $q->where('file_path', 'like', '%' . $escaped . '%')
                  ->orWhere('name', 'like', '%' . $escaped . '%')
                  ->orWhere('properties', 'like', '%' . $escaped . '%');
            });
        }

        if ($this->filterGroup) {
            $query->where('group_id', $this->filterGroup);
        }

        if ($this->filterDepartment) {
            $query->where('group_id', 2)
                  ->where('properties', 'like', '%"department":"' . addcslashes($this->filterDepartment, '%_\\"') . '"%');
        }

        if ($this->filterRetention) {
            $query->where('group_id', 2)
                  ->where('properties', 'like', '%"retention":"' . addcslashes($this->filterRetention, '%_\\"') . '"%');
        }

        if ($this->filterAiAccess) {
            $query->where('group_id', 2)
                  ->where('properties', 'like', '%"ai_access":"' . addcslashes($this->filterAiAccess, '%_\\"') . '"%');
        }

        if ($this->filterSensitivity) {
            $query->where('group_id', 2)
                  ->where('properties', 'like', '%"sensitivity":"' . addcslashes($this->filterSensitivity, '%_\\"') . '"%');
        }

        $nodes = $query->orderBy('file_path')->paginate(50);
        $audit = app(SystemBlueprintService::class)->auditArchitecture();

        return view('livewire.backend.system.system-neural-analysis-index', [
            'nodes' => $nodes,
            'audit' => $audit,
            'departments' => SystemDepartment::cases(),
            'retentions' => RetentionPolicy::cases(),
            'aiPolicies' => AiAccessPolicy::cases(),
            'sensitivities' => DataSensitivity::cases(),
        ])->layout('components.layouts.backend_layout');
    }
}
