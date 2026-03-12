<?php

namespace App\Services\AI\Functions;

use App\Models\Ticket;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use ZipArchive;

trait SettingsFunctions
{
    public static function getSettingsFunctionsSchema(): array
    {
        return [
            [
                'name' => 'read_wiki_files',
                'description' => 'Liest die hochgeladenen Dokumente aus dem Wiki aus. Nutze dieses Tool IMMER KOMPLETT OHNE PARAMETER, wenn du nach einer generellen Information (z.B. "Wer bin ich?", "Rentenversicherungsnummer" usw.) suchst. Das Tool liefert dir dann den Text aller Dateien zurück, in denen du die Info selbst finden kannst.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'filename_query' => [
                            'type' => 'string',
                            'description' => 'EXAKTER Dateiname. ACHTUNG: Nutze dies NUR, wenn du eine ganz bestimmte Datei meinst (z.B. "Richtlinien.pdf") und deren Name exakt kennst. Wenn du eine Information / ein Thema suchst, lass diesen Parameter ZWINGEND LEER!'
                        ]
                    ], 
                ],
                'callable' => [self::class, 'executeReadWikiFiles']
            ],
            [
                'name' => 'get_tickets',
                'description' => 'Gibt alle offenen Kundensupport-Tickets zurück. Nutze dies, wenn nach Support, Kundenmeldungen oder Tickets gefragt wird.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => new \stdClass(),
                ],
                'callable' => [self::class, 'executeGetTickets']
            ],
            [
                'name' => 'get_system_map',
                'description' => 'Generiert eine Architekturkarte des gesamten Systems durch Scannen aller berechtigten Datenbankmodelle. Nutze dies, wenn du gefragt wirst, welche Daten existieren, worauf du Zugriff hast, oder welche Funktionen dir zur vollständigen Systemverwaltung noch fehlen. Vergleiche diese Karte mit deinen aktuell verfügbaren Werkzeugen.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => new \stdClass(),
                ],
                'callable' => [self::class, 'executeGetSystemMap']
            ]
        ];
    }

    public static function executeReadWikiFiles(array $args)
    {
        try {
            $query = $args['filename_query'] ?? null;
            $files = Storage::disk('public')->files('wiki');
            
            if (empty($files)) {
                return ['status' => 'error', 'message' => "Es befinden sich aktuell keine Dateien im Wiki-Ordner. Der Benutzer muss erst Dateien hochladen."];
            }
            
            $output = "Gefundene Dateien im Wiki:\n\n";
            $contentFound = false;
            
            foreach ($files as $file) {
                $filename = basename($file);
                
                if ($query && stripos($filename, $query) === false) continue;
                
                \Illuminate\Support\Facades\Log::info("Funkira liest Wiki-Datei: " . $filename);
                $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
                $output .= "### Datei: $filename\n";
                $contentFound = true;
                
                if (in_array($ext, ['txt', 'md', 'csv', 'json', 'log'])) {
                    $content = Storage::disk('public')->get($file);
                    $content = Str::limit($content, 8000); 
                    $output .= "- Inhalt:\n" . $content . "\n\n";
                } elseif ($ext === 'docx') {
                    $zip = new ZipArchive;
                    $absPath = Storage::disk('public')->path($file);
                    if ($zip->open($absPath) === true) {
                        if (($index = $zip->locateName('word/document.xml')) !== false) {
                            $data = $zip->getFromIndex($index);
                            $zip->close();
                            
                            // Remove all XML tags except for w:p (paragraphs) to create clean breaks
                            $data = str_replace('</w:p>', "\n\n", $data);
                            $data = str_replace('</w:tr>', "\n", $data); // Table rows
                            $data = strip_tags($data);
                            
                            $text = html_entity_decode($data, ENT_QUOTES, 'UTF-8');
                            // Clean up multiple newlines
                            $text = preg_replace("/\n{3,}/", "\n\n", $text);
                            
                            $text = Str::limit(trim($text), 8000);
                            $output .= "- Inhalt:\n" . $text . "\n\n";
                        } else {
                            $zip->close();
                            $output .= "- Fehler: Konnte den Text nicht aus der DOCX-Datei extrahieren.\n\n";
                        }
                    } else {
                        $output .= "- Fehler: Konnte die DOCX-Datei nicht öffnen.\n\n";
                    }
                } elseif ($ext === 'doc') {
                    $output .= "- (DOC Format): Das veraltete '.doc' Format kann ich nicht direkt lesen. Bitte weise den Benutzer an, die Datei als '.docx' zu speichern.\n\n";
                } elseif ($ext === 'pdf') {
                    $output .= "- (PDF Format): Aktuell kann ich PDFs nicht nativ lesen. Bitte als TXT/MD hochladen.\n\n";
                } else {
                    $output .= "- Format `.$ext` wird aktuell nicht von der KI unterstützt.\n\n";
                }
            }
            
            if (!$contentFound) {
                return ['status' => 'error', 'message' => "Es wurde keine Datei gefunden, die auf '$query' passt."];
            }
            
            return ['status' => 'success', 'content' => $output];

        } catch (\Exception $e) {
            return ['status' => 'error', 'message' => 'Fehler beim Auslesen des Wikis: ' . $e->getMessage()];
        }
    }

    public static function executeGetTickets(array $args)
    {
        try {
            $query = Ticket::where('status', '!=', 'closed');
            $count = $query->count();
            $tickets = $query->orderBy('created_at', 'desc')->take(5)->get();

            if ($tickets->isEmpty()) {
                return ['status' => 'success', 'message' => 'Es gibt aktuell keine offenen Support-Tickets. Alles super!'];
            }

            $formatted = [];
            foreach ($tickets as $t) {
                $formatted[] = [
                    'id' => $t->id,
                    'subject' => $t->subject,
                    'status' => $t->status,
                    'priority' => $t->priority,
                    'date' => $t->created_at->format('d.m.Y H:i')
                ];
            }

            return ['status' => 'success', 'open_tickets_count' => $count, 'tickets' => $formatted];
        } catch (\Exception $e) {
            return ['status' => 'error', 'message' => 'Tickets konnten nicht geladen werden: ' . $e->getMessage()];
        }
    }

    public static function executeGetSystemMap(array $args)
    {
        try {
            $modelsPath = app_path('Models');
            
            if (!is_dir($modelsPath)) {
                return ['status' => 'error', 'message' => 'Models Verzeichnis nicht gefunden.'];
            }

            $map = [];
            $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($modelsPath));
            
            foreach ($iterator as $file) {
                if ($file->isDir()) continue;
                
                if ($file->getExtension() === 'php') {
                    $relativePath = str_replace($modelsPath . '/', '', $file->getPathname());
                    $parts = explode('/', $relativePath);
                    
                    if (count($parts) > 1) {
                        $module = $parts[0];
                        $modelName = str_replace('.php', '', $parts[1]);
                        
                        if (!isset($map[$module])) {
                            $map[$module] = [];
                        }
                        $map[$module][] = $modelName;
                    } else {
                        $modelName = str_replace('.php', '', $parts[0]);
                        if (!isset($map['Core'])) {
                            $map['Core'] = [];
                        }
                        $map['Core'][] = $modelName;
                    }
                }
            }

            $output = "System Architektur (Datenstruktur):\n";
            ksort($map);
            
            foreach ($map as $module => $models) {
                $output .= "\n[$module]\n";
                foreach ($models as $model) {
                    $output .= "- $model\n";
                }
            }
            
            $output .= "\nINFO FÜR FUNKIRA: Vergleiche diese Entitäten mit deinen verfügbaren Werkzeugen (tools). Wenn in der App Daten existieren (z.B. Returns, Newsletter, Tracking), für die dir noch Werkzeuge fehlen, weise den Benutzer darauf hin, dass diese programmiert werden müssen, damit du darüber Kontrolle erlangst.";

            // --- NEU: Admin Routen (Navigation) parsen ---
            $routesPath = base_path('routes/partials/admin_routes.php');
            if (file_exists($routesPath)) {
                $routesContent = file_get_contents($routesPath);
                $output .= "\n\nVERFÜGBARE SEITEN (NAVIGATION):\nFolgende Seiten existieren im System und können von dir mit dem Tool 'open_nav_item' aufgerufen werden:\n";
                
                // Extrahiere Route::get('/admin/...', function
                preg_match_all("/Route::get\('(\/admin\/[^']+)'/i", $routesContent, $routeMatches);
                
                if (!empty($routeMatches[1])) {
                    $uniqueRoutes = array_unique($routeMatches[1]);
                    foreach ($uniqueRoutes as $routeUrl) {
                        $output .= "- $routeUrl\n";
                    }
                }
            }

            return [
                'status' => 'success',
                'system_map' => $output
            ];

        } catch (\Exception $e) {
            return ['status' => 'error', 'message' => 'Fehler beim Generieren der System-Map: ' . $e->getMessage()];
        }
    }
}
