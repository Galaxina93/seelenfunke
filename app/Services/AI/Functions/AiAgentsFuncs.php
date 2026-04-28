<?php

namespace App\Services\AI\Functions;

use App\Models\Ai\AiAgent;
use App\Models\Ai\AiDepartment;
use App\Models\Ai\AiRole;
use App\Models\Ai\AiInteraction;

trait AiAgentsFuncs
{
    public static function getAiAgentsFuncsSchema(): array
    {
        return [
            [
                'name' => 'get_ai_company_structure',
                'description' => 'Ruft das komplette KI-Organigramm des Unternehmens mit allen Abteilungen und den darin zugewiesenen Agenten ab.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => new \stdClass()
                ],
                'callable' => [self::class, 'executeGetAiCompanyStructure']
            ],
            [
                'name' => 'move_agent_to_department',
                'description' => 'Verschiebt einen existierenden KI-Agenten in eine andere Abteilung innerhalb des Organigramms.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'agent_name' => ['type' => 'string', 'description' => 'Der Name des Agenten (z.B. Marketi, Buchi).'],
                        'department_name' => ['type' => 'string', 'description' => 'Der Name der Ziel-Abteilung (z.B. Support, Leitung, Produkte) oder "null" fuer Freie Agenten.']
                    ],
                    'required' => ['agent_name', 'department_name']
                ],
                'callable' => [self::class, 'executeMoveAgentToDepartment']
            ],
            [
                'name' => 'get_agent_roles',
                'description' => 'Ruft die vordefinierten KI-Rollen und Instruktionen ab (Rollenverwaltung).',
                'parameters' => [
                    'type' => 'object',
                    'properties' => new \stdClass()
                ],
                'callable' => [self::class, 'executeGetAgentRoles']
            ],
            [
                'name' => 'analyze_agents_activity',
                'description' => 'Analysiert die Aktivitaet und Performance der KI-Agenten basierend auf Chat Logs, Interaktionen und verbrauchten System-Tokens.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'days' => ['type' => 'integer', 'description' => 'Anzahl der vergangenen Tage für die Analyse.', 'default' => 7]
                    ]
                ],
                'callable' => [self::class, 'executeAnalyzeAgentsActivity']
            ],
            [
                'name' => 'workspace_create_folder',
                'description' => 'Erstellt einen neuen Ordner im KI-Arbeitsbereich (agenten/workspace).',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'folder_path' => ['type' => 'string', 'description' => 'Der Pfad des neuen Ordners, beginnend mit agenten/workspace/ (z.B. agenten/workspace/projekte/neu).']
                    ],
                    'required' => ['folder_path']
                ],
                'callable' => [self::class, 'executeWorkspaceCreateFolder']
            ],
            [
                'name' => 'workspace_rename_folder',
                'description' => 'Benennt einen Ordner im KI-Arbeitsbereich um.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'folder_path' => ['type' => 'string', 'description' => 'Aktueller Pfad des Ordners (z.B. agenten/workspace/alt).'],
                        'new_name' => ['type' => 'string', 'description' => 'Der neue Name des Ordners (nur der Name, kein Pfad).']
                    ],
                    'required' => ['folder_path', 'new_name']
                ],
                'callable' => [self::class, 'executeWorkspaceRenameFolder']
            ],
            [
                'name' => 'workspace_delete_folder',
                'description' => 'Löscht einen Ordner und dessen gesamten Inhalt.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'folder_path' => ['type' => 'string', 'description' => 'Pfad des zu löschenden Ordners.']
                    ],
                    'required' => ['folder_path']
                ],
                'callable' => [self::class, 'executeWorkspaceDeleteFolder']
            ],
            [
                'name' => 'workspace_move_folder',
                'description' => 'Verschiebt einen Ordner an einen anderen Ort innerhalb des Workspaces.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'source_path' => ['type' => 'string', 'description' => 'Aktueller Pfad des Ordners.'],
                        'target_path' => ['type' => 'string', 'description' => 'Zielpfad (Ordner, in den verschoben wird).']
                    ],
                    'required' => ['source_path', 'target_path']
                ],
                'callable' => [self::class, 'executeWorkspaceMoveFolder']
            ],
            [
                'name' => 'workspace_archive_folder',
                'description' => 'Erstellt ein ZIP-Archiv des Ordners und legt es daneben ab.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'folder_path' => ['type' => 'string', 'description' => 'Pfad des zu archivierenden Ordners.']
                    ],
                    'required' => ['folder_path']
                ],
                'callable' => [self::class, 'executeWorkspaceArchiveFolder']
            ],
            [
                'name' => 'workspace_get_folder_size',
                'description' => 'Liest die Gesamtgröße eines Ordners (inkl. Unterordner) in Bytes und MB aus.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'folder_path' => ['type' => 'string', 'description' => 'Pfad des Ordners.']
                    ],
                    'required' => ['folder_path']
                ],
                'callable' => [self::class, 'executeWorkspaceGetFolderSize']
            ],
            [
                'name' => 'workspace_move_folder_content',
                'description' => 'Verschiebt den kompletten Inhalt eines Ordners in einen anderen Ordner, ohne den Quellordner selbst zu löschen.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'source_path' => ['type' => 'string', 'description' => 'Pfad des Quellordners.'],
                        'target_path' => ['type' => 'string', 'description' => 'Pfad des Zielordners.']
                    ],
                    'required' => ['source_path', 'target_path']
                ],
                'callable' => [self::class, 'executeWorkspaceMoveFolderContent']
            ],
            [
                'name' => 'workspace_delete_folder_content',
                'description' => 'Löscht alle Dateien und Unterordner innerhalb eines Ordners, behält aber den Ordner selbst.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'folder_path' => ['type' => 'string', 'description' => 'Pfad des Ordners, dessen Inhalt gelöscht werden soll.']
                    ],
                    'required' => ['folder_path']
                ],
                'callable' => [self::class, 'executeWorkspaceDeleteFolderContent']
            ],
            [
                'name' => 'workspace_archive_folder_content',
                'description' => 'Erstellt ein ZIP-Archiv aus dem Inhalt eines Ordners und speichert es in diesem Ordner.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'folder_path' => ['type' => 'string', 'description' => 'Pfad des Ordners.'],
                        'archive_name' => ['type' => 'string', 'description' => 'Name der ZIP-Datei (z.B. archiv.zip).']
                    ],
                    'required' => ['folder_path', 'archive_name']
                ],
                'callable' => [self::class, 'executeWorkspaceArchiveFolderContent']
            ],
            [
                'name' => 'workspace_search_files',
                'description' => 'Sucht rekursiv nach Dateien oder Ordnern im gesamten Workspace anhand eines Namens (Fuzzy Search).',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'query' => ['type' => 'string', 'description' => 'Der Suchbegriff (z.B. Dateiname, Dateiendung, Teil eines Namens).']
                    ],
                    'required' => ['query']
                ],
                'callable' => [self::class, 'executeWorkspaceSearchFiles']
            ]
        ];
    }

    public static function executeGetAiCompanyStructure(array $args): array
    {
        $departments = AiDepartment::with('agents:id,name,role_description,ai_department_id')->orderBy('order_index')->get();
        $freeAgents = AiAgent::whereNull('ai_department_id')->get(['id', 'name', 'role_description']);
        
        $result = [
            'Organigramm_Abteilungen' => $departments->toArray(), 
            'Stabsstelle_Freie_Agenten' => $freeAgents->toArray()
        ];
        return ['status' => 'success', 'data' => $result];
    }

    public static function executeMoveAgentToDepartment(array $args): array
    {
        $agentName = $args['agent_name'] ?? null;
        $deptName = $args['department_name'] ?? null;

        if (!$agentName) return ['status' => 'error', 'message' => "Fehler: agent_name fehlt."];

        $agent = AiAgent::where('name', 'LIKE', '%' . $agentName . '%')->first();
        if (!$agent) return ['status' => 'error', 'message' => "Fehler: Agent '$agentName' nicht gefunden."];

        if ($deptName && strtolower($deptName) !== 'null' && strtolower($deptName) !== 'freie agenten' && strtolower($deptName) !== 'stabsstelle') {
            $dept = AiDepartment::where('name', 'LIKE', '%' . $deptName . '%')->first();
            if (!$dept) {
                return ['status' => 'error', 'message' => "Fehler: Abteilung '$deptName' nicht gefunden. Prüfe das Organigramm per get_ai_company_structure."];
            }
            
            $agent->update(['ai_department_id' => $dept->id]);
            return ['status' => 'success', 'message' => "Erfolg: Agent {$agent->name} wurde erfolgreich in die Abteilung {$dept->name} verschoben.", 'ui_action' => 'reload_organigram'];
        } else {
            $agent->update(['ai_department_id' => null]);
            return ['status' => 'success', 'message' => "Erfolg: Agent {$agent->name} wurde aus seiner Abteilung entfernt und ist nun ein freier Agent (Stabsstelle).", 'ui_action' => 'reload_organigram'];
        }
    }

    public static function executeGetAgentRoles(array $args): array
    {
        if (class_exists(AiRole::class)) {
            $roles = AiRole::select('id', 'name', 'description')->get();
            return ['status' => 'success', 'data' => ['Rollen' => $roles->toArray()]];
        }
        return ['status' => 'error', 'message' => "AiRole Modell nicht gefunden."];
    }

    public static function executeAnalyzeAgentsActivity(array $args): array
    {
        $days = $args['days'] ?? 7;
        $startDate = now()->subDays($days);
        
        if (class_exists(AiInteraction::class)) {
            $interactions = AiInteraction::where('created_at', '>=', $startDate)
                ->selectRaw('ai_agent_id, count(id) as total_messages, sum(total_tokens) as total_tokens_used')
                ->groupBy('ai_agent_id')
                ->with('agent:id,name')
                ->get();
                
            return ['status' => 'success', 'data' => ['Aktivitaets_Analyse' => $interactions->toArray(), 'Zeitraum_Tage' => $days]];
        }
        return ['status' => 'error', 'message' => "AiInteraction Logs nicht verfuegbar oder System loggt aktuell keine Tokens."];
    }

    private static function secureWorkspacePath(string $path): string
    {
        $path = trim($path, '/');
        // Verhindern von Verzeichnisausbrüchen
        $path = str_replace(['../', '..\\'], '', $path);
        
        if (!str_starts_with($path, 'agenten/workspace')) {
            $path = 'agenten/workspace/' . $path;
        }
        return $path;
    }

    public static function executeWorkspaceCreateFolder(array $args): array
    {
        $path = self::secureWorkspacePath($args['folder_path'] ?? '');
        if (\Illuminate\Support\Facades\Storage::disk('public')->exists($path)) {
            return ['status' => 'error', 'message' => "Ordner existiert bereits: $path"];
        }
        \Illuminate\Support\Facades\Storage::disk('public')->makeDirectory($path);
        return ['status' => 'success', 'message' => "Ordner erfolgreich erstellt: $path", 'ui_action' => 'reload_filemanager'];
    }

    public static function executeWorkspaceRenameFolder(array $args): array
    {
        $path = self::secureWorkspacePath($args['folder_path'] ?? '');
        $newName = trim($args['new_name'] ?? '');
        if (empty($newName) || str_contains($newName, '/')) {
            return ['status' => 'error', 'message' => "Ungültiger neuer Name."];
        }

        if (!\Illuminate\Support\Facades\Storage::disk('public')->exists($path)) {
            return ['status' => 'error', 'message' => "Quellordner existiert nicht: $path"];
        }

        $newPath = dirname($path) . '/' . $newName;
        // Fix dirname behaviour at root
        if (dirname($path) === '.' || dirname($path) === '\\') {
            $newPath = 'agenten/workspace/' . $newName;
        }

        if (\Illuminate\Support\Facades\Storage::disk('public')->exists($newPath)) {
            return ['status' => 'error', 'message' => "Ein Ordner oder eine Datei mit dem neuen Namen existiert bereits."];
        }

        $oldFullPath = \Illuminate\Support\Facades\Storage::disk('public')->path($path);
        $newFullPath = \Illuminate\Support\Facades\Storage::disk('public')->path($newPath);

        if (is_dir($oldFullPath)) {
            rename($oldFullPath, $newFullPath);
        } else {
            \Illuminate\Support\Facades\Storage::disk('public')->move($path, $newPath);
        }

        return ['status' => 'success', 'message' => "Erfolgreich umbenannt von $path zu $newPath", 'ui_action' => 'reload_filemanager'];
    }

    public static function executeWorkspaceDeleteFolder(array $args): array
    {
        $path = self::secureWorkspacePath($args['folder_path'] ?? '');
        if ($path === 'agenten/workspace') {
            return ['status' => 'error', 'message' => "Das Hauptverzeichnis darf nicht gelöscht werden."];
        }

        if (!\Illuminate\Support\Facades\Storage::disk('public')->exists($path) && !in_array($path, \Illuminate\Support\Facades\Storage::disk('public')->directories(dirname($path)))) {
             return ['status' => 'error', 'message' => "Ordner nicht gefunden: $path"];
        }

        if (in_array($path, \Illuminate\Support\Facades\Storage::disk('public')->directories(dirname($path)))) {
            \Illuminate\Support\Facades\Storage::disk('public')->deleteDirectory($path);
        } else {
            \Illuminate\Support\Facades\Storage::disk('public')->delete($path);
        }

        return ['status' => 'success', 'message' => "Erfolgreich gelöscht: $path", 'ui_action' => 'reload_filemanager'];
    }

    public static function executeWorkspaceMoveFolder(array $args): array
    {
        $sourcePath = self::secureWorkspacePath($args['source_path'] ?? '');
        $targetPath = self::secureWorkspacePath($args['target_path'] ?? '');

        if (!\Illuminate\Support\Facades\Storage::disk('public')->exists($sourcePath)) {
            return ['status' => 'error', 'message' => "Quellordner nicht gefunden: $sourcePath"];
        }

        // Prevent moving inside itself
        if (str_starts_with($targetPath, $sourcePath . '/')) {
             return ['status' => 'error', 'message' => "Ordner kann nicht in sich selbst verschoben werden."];
        }

        $fileName = basename($sourcePath);
        $newPath = $targetPath . '/' . $fileName;

        if (\Illuminate\Support\Facades\Storage::disk('public')->exists($newPath)) {
            return ['status' => 'error', 'message' => "Ziel existiert bereits: $newPath"];
        }

        $oldFullPath = \Illuminate\Support\Facades\Storage::disk('public')->path($sourcePath);
        $newFullPath = \Illuminate\Support\Facades\Storage::disk('public')->path($newPath);

        if (!\Illuminate\Support\Facades\Storage::disk('public')->exists($targetPath)) {
            \Illuminate\Support\Facades\Storage::disk('public')->makeDirectory($targetPath);
        }

        if (is_dir($oldFullPath)) {
            rename($oldFullPath, $newFullPath);
        } else {
            \Illuminate\Support\Facades\Storage::disk('public')->move($sourcePath, $newPath);
        }

        return ['status' => 'success', 'message' => "Erfolgreich verschoben nach: $newPath", 'ui_action' => 'reload_filemanager'];
    }

    public static function executeWorkspaceArchiveFolder(array $args): array
    {
        $path = self::secureWorkspacePath($args['folder_path'] ?? '');
        if (!\Illuminate\Support\Facades\Storage::disk('public')->exists($path)) {
            return ['status' => 'error', 'message' => "Ordner nicht gefunden: $path"];
        }

        $fullPath = \Illuminate\Support\Facades\Storage::disk('public')->path($path);
        $zipPath = \Illuminate\Support\Facades\Storage::disk('public')->path($path . '.zip');

        $zip = new \ZipArchive();
        if ($zip->open($zipPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) === true) {
            if (is_dir($fullPath)) {
                $files = new \RecursiveIteratorIterator(
                    new \RecursiveDirectoryIterator($fullPath),
                    \RecursiveIteratorIterator::LEAVES_ONLY
                );

                foreach ($files as $name => $file) {
                    if (!$file->isDir()) {
                        $filePath = $file->getRealPath();
                        $relativePath = substr($filePath, strlen($fullPath) + 1);
                        $zip->addFile($filePath, $relativePath);
                    }
                }
            } else {
                $zip->addFile($fullPath, basename($fullPath));
            }
            $zip->close();
            return ['status' => 'success', 'message' => "Ordner erfolgreich als " . basename($zipPath) . " archiviert.", 'ui_action' => 'reload_filemanager'];
        }
        return ['status' => 'error', 'message' => "Fehler beim Erstellen des ZIP-Archivs."];
    }

    public static function executeWorkspaceGetFolderSize(array $args): array
    {
        $path = self::secureWorkspacePath($args['folder_path'] ?? '');
        if (!\Illuminate\Support\Facades\Storage::disk('public')->exists($path)) {
            return ['status' => 'error', 'message' => "Ordner nicht gefunden: $path"];
        }

        $size = 0;
        $files = \Illuminate\Support\Facades\Storage::disk('public')->allFiles($path);
        foreach ($files as $file) {
            $size += \Illuminate\Support\Facades\Storage::disk('public')->size($file);
        }

        return [
            'status' => 'success', 
            'data' => [
                'path' => $path,
                'size_bytes' => $size,
                'size_mb' => round($size / 1024 / 1024, 2),
                'file_count' => count($files)
            ]
        ];
    }

    public static function executeWorkspaceMoveFolderContent(array $args): array
    {
        $sourcePath = self::secureWorkspacePath($args['source_path'] ?? '');
        $targetPath = self::secureWorkspacePath($args['target_path'] ?? '');

        if (!\Illuminate\Support\Facades\Storage::disk('public')->exists($sourcePath)) {
            return ['status' => 'error', 'message' => "Quellordner nicht gefunden: $sourcePath"];
        }

        if (!\Illuminate\Support\Facades\Storage::disk('public')->exists($targetPath)) {
            \Illuminate\Support\Facades\Storage::disk('public')->makeDirectory($targetPath);
        }

        $files = \Illuminate\Support\Facades\Storage::disk('public')->files($sourcePath);
        $directories = \Illuminate\Support\Facades\Storage::disk('public')->directories($sourcePath);

        $moved = 0;
        foreach ($files as $file) {
            $newPath = $targetPath . '/' . basename($file);
            \Illuminate\Support\Facades\Storage::disk('public')->move($file, $newPath);
            $moved++;
        }

        foreach ($directories as $dir) {
            $newPath = $targetPath . '/' . basename($dir);
            $oldFullPath = \Illuminate\Support\Facades\Storage::disk('public')->path($dir);
            $newFullPath = \Illuminate\Support\Facades\Storage::disk('public')->path($newPath);
            rename($oldFullPath, $newFullPath);
            $moved++;
        }

        return ['status' => 'success', 'message' => "Es wurden $moved Elemente von $sourcePath nach $targetPath verschoben.", 'ui_action' => 'reload_filemanager'];
    }

    public static function executeWorkspaceDeleteFolderContent(array $args): array
    {
        $path = self::secureWorkspacePath($args['folder_path'] ?? '');
        if (!\Illuminate\Support\Facades\Storage::disk('public')->exists($path)) {
            return ['status' => 'error', 'message' => "Ordner nicht gefunden: $path"];
        }

        $files = \Illuminate\Support\Facades\Storage::disk('public')->files($path);
        $directories = \Illuminate\Support\Facades\Storage::disk('public')->directories($path);

        $deleted = 0;
        foreach ($files as $file) {
            \Illuminate\Support\Facades\Storage::disk('public')->delete($file);
            $deleted++;
        }

        foreach ($directories as $dir) {
            \Illuminate\Support\Facades\Storage::disk('public')->deleteDirectory($dir);
            $deleted++;
        }

        return ['status' => 'success', 'message' => "Ordnerinhalt erfolgreich gelöscht. $deleted Elemente entfernt.", 'ui_action' => 'reload_filemanager'];
    }

    public static function executeWorkspaceArchiveFolderContent(array $args): array
    {
        $path = self::secureWorkspacePath($args['folder_path'] ?? '');
        $archiveName = $args['archive_name'] ?? 'archiv.zip';
        if (!str_ends_with(strtolower($archiveName), '.zip')) {
            $archiveName .= '.zip';
        }

        if (!\Illuminate\Support\Facades\Storage::disk('public')->exists($path)) {
            return ['status' => 'error', 'message' => "Ordner nicht gefunden: $path"];
        }

        $fullPath = \Illuminate\Support\Facades\Storage::disk('public')->path($path);
        $zipPath = \Illuminate\Support\Facades\Storage::disk('public')->path($path . '/' . $archiveName);

        $zip = new \ZipArchive();
        if ($zip->open($zipPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) === true) {
            $files = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($fullPath),
                \RecursiveIteratorIterator::LEAVES_ONLY
            );

            foreach ($files as $name => $file) {
                if (!$file->isDir() && $file->getFilename() !== $archiveName) {
                    $filePath = $file->getRealPath();
                    $relativePath = substr($filePath, strlen($fullPath) + 1);
                    $zip->addFile($filePath, $relativePath);
                }
            }
            $zip->close();
            return ['status' => 'success', 'message' => "Ordnerinhalt erfolgreich in $archiveName archiviert.", 'ui_action' => 'reload_filemanager'];
        }
        return ['status' => 'error', 'message' => "Fehler beim Erstellen des ZIP-Archivs."];
    }

    public static function executeWorkspaceSearchFiles(array $args): array
    {
        $query = strtolower($args['query'] ?? '');
        if (empty($query)) {
            return ['status' => 'error', 'message' => "Suchbegriff darf nicht leer sein."];
        }

        $allFiles = \Illuminate\Support\Facades\Storage::disk('public')->allFiles('agenten/workspace');
        $allDirs = \Illuminate\Support\Facades\Storage::disk('public')->allDirectories('agenten/workspace');

        $matches = [];

        foreach ($allDirs as $dir) {
            if (str_contains(strtolower(basename($dir)), $query) || str_contains(strtolower($dir), $query)) {
                $matches[] = ['type' => 'folder', 'path' => $dir, 'name' => basename($dir)];
            }
        }

        foreach ($allFiles as $file) {
            if (str_contains(strtolower(basename($file)), $query) || str_contains(strtolower($file), $query)) {
                $matches[] = [
                    'type' => 'file', 
                    'path' => $file, 
                    'name' => basename($file),
                    'size' => \Illuminate\Support\Facades\Storage::disk('public')->size($file),
                    'last_modified' => date('Y-m-d H:i:s', \Illuminate\Support\Facades\Storage::disk('public')->lastModified($file))
                ];
            }
        }

        return [
            'status' => 'success', 
            'data' => [
                'query' => $query,
                'matches_count' => count($matches),
                'matches' => $matches
            ]
        ];
    }
}
