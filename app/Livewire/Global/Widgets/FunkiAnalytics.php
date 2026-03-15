<?php

namespace App\Livewire\Global\Widgets;

use Livewire\Component;
use Livewire\WithFileUploads;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Carbon\Carbon;
use App\Models\SystemCheckConfig;
use App\Models\LoginAttempt;
use App\Models\Product\Product;
use App\Models\Financial\FinanceCostItem;
use App\Models\Financial\FinanceSpecialIssue;
use App\Models\Global\GlobalLog;
use App\Services\FunkiAnalyticsService;

class FunkiAnalytics extends Component
{
    use WithFileUploads;

    public $stats = [];
    public $healthChecks = [];
    public $systemHealth = [];
    public $rangeMode = 'year';

    // Suchfeld für die Historie
    public $searchLogins = '';

    public $showFailedLogins = false;
    public $showFullLogins = false;
    public $dateStart;
    public $dateEnd;
    public $filterType = 'all';

    public $uploadFile;
    public array $stockUpdate = [];
    public ?string $expandedHealthKey = null;
    public array $repairLogs = [];

    public $infoTexts = [
        'trend' => 'Veränderung des Umsatzes im Vergleich zum vorherigen Zeitraum gleicher Länge.',
        'marge' => 'Verhältnis von Gewinn zu Umsatz. Zeigt, wie viel Prozent vom Umsatz als Gewinn verbleiben.',
        'avg_profit' => 'Durchschnittlicher Gewinn pro Zeiteinheit innerhalb des gewählten Zeitraums.',
        'prognose' => 'Hochrechnung des Gewinns auf das Jahr basierend auf der aktuellen Performance.',
        'break_even' => 'Monatlicher Umsatz, der nötig ist, um alle fixen Kosten zu decken.',
        'offene' => 'Summe aller Rechnungen mit Status "Offen", die noch nicht beglichen wurden.',
        'fix_inc' => 'Regelmäßige Einnahmen wie Mieten oder Gehälter.',
        'shop_rev' => 'Summe aller bezahlten Bestellungen über den Online-Shop.',
        'fix_priv' => 'Regelmäßige private Ausgaben (Miete, Versicherungen, Unterhalt).',
        'fix_bus' => 'Regelmäßige geschäftliche Ausgaben (Server, Software, Miete).',
        'variabel' => 'Einmalige Ausgaben und Sonderausgaben ohne festes Intervall.'
    ];

    public function mount(FunkiAnalyticsService $service)
    {
        $this->loadSettings();
        $this->loadStats($service);
        $this->systemHealth = [
            'database' => ['status' => 'checking', 'value' => '...', 'error' => null],
            'storage' => ['status' => 'checking', 'value' => '...', 'error' => null],
            'stripe' => ['status' => 'checking', 'value' => '...', 'error' => null],
            'smtp' => ['status' => 'checking', 'value' => '...', 'error' => null],
            'redis' => ['status' => 'checking', 'value' => '...', 'error' => null],
            'queue' => ['status' => 'checking', 'value' => '...', 'error' => null, 'pending' => 0, 'failed' => 0],
        ];
    }

    /**
     * Gibt zurück, ob alle Systemdienste für die Galaxie-View "gesund" sind.
     */
    public function isSystemHealthy(): bool
    {
        if (empty($this->systemHealth)) {
            return true;
        }

        foreach ($this->systemHealth as $service) {
            if (isset($service['status']) && in_array($service['status'], ['error', 'warning', 'offline'])) {
                return false;
            }
        }
        return true;
    }

    /**
     * Gibt eine Schätzung der aktuell aktiven User/Sessions für die Partikelanzahl zurück.
     */
    public function getActiveSessionsCount(): int
    {
        try {
            // Falls Sessions in der DB liegen
            return DB::table('sessions')->where('last_activity', '>=', now()->subMinutes(15)->timestamp)->count() + 20;
        } catch (\Exception $e) {
            // Fallback: Fake-Partikel für die Optik, falls Redis/File Cache etc.
            return rand(20, 50);
        }
    }

    /**
     * Hilfsfunktion, um System-Ausfälle in den FunkiLog zu schreiben (Max 1x pro Stunde pro Fehler)
     */
    private function logSystemFailure($serviceName, $message, $payload = [])
    {
        $cacheKey = 'sys_fail_log_' . $serviceName;

        if (!Cache::has($cacheKey)) {
            GlobalLog::create([
                'type' => 'system',
                'action_id' => 'system:health_fail',
                'title' => 'Infrastruktur-Ausfall: ' . ucfirst($serviceName),
                'message' => $message,
                'status' => 'error',
                'payload' => $payload,
                'started_at' => now(),
                'finished_at' => now(),
            ]);

            // Blockiere weiteres Loggen für diesen Dienst für 60 Minuten
            Cache::put($cacheKey, true, now()->addMinutes(60));
        }
    }

    public function checkSystemHealth()
    {
        $health = [];

        // 1. Datenbank Check
        try {
            $start = microtime(true);
            DB::connection()->getPdo();
            $time = round((microtime(true) - $start) * 1000);
            $health['database'] = ['status' => 'connected', 'value' => "Latenz: {$time}ms", 'error' => null];
        } catch (\Exception $e) {
            $health['database'] = ['status' => 'error', 'value' => 'Offline', 'error' => 'Keine Verbindung zum SQL-Server.'];
            $this->logSystemFailure('database', 'Die primäre Datenbank ist nicht erreichbar!', ['exception' => $e->getMessage()]);
        }

        // 2. Speicherplatz (Storage) Check
        try {
            $freeSpace = disk_free_space(base_path());
            $totalSpace = disk_total_space(base_path());
            if ($totalSpace > 0) {
                $percentFree = round(($freeSpace / $totalSpace) * 100);
                $freeGb = round($freeSpace / 1024 / 1024 / 1024, 1);
                $status = $percentFree < 10 ? 'warning' : 'connected';
                $health['storage'] = ['status' => $status, 'value' => "{$percentFree}% frei ({$freeGb} GB)", 'error' => $status === 'warning' ? 'Wenig Speicherplatz!' : null];
                if ($status === 'warning') $this->logSystemFailure('storage', "Kritisch: Nur noch {$percentFree}% ({$freeGb} GB) Speicherplatz auf der Server-Festplatte verfügbar!");
            } else {
                $health['storage'] = ['status' => 'error', 'value' => 'Unbekannt', 'error' => 'Konnte Speicher nicht auslesen.'];
            }
        } catch (\Exception $e) {
            $health['storage'] = ['status' => 'error', 'value' => 'Fehler', 'error' => 'Zugriff auf Dateisystem verweigert.'];
        }

        // 3. Stripe API Check
        try {
            $start = microtime(true);
            \Illuminate\Support\Facades\Http::timeout(3)->get('https://api.stripe.com/healthcheck');
            $time = round((microtime(true) - $start) * 1000);
            $health['stripe'] = ['status' => 'connected', 'value' => "Latenz: {$time}ms", 'error' => null];
        } catch (\Exception $e) {
            $health['stripe'] = ['status' => 'error', 'value' => 'Timeout', 'error' => 'Stripe API nicht erreichbar. Firewall prüfen!'];
            $this->logSystemFailure('stripe', 'Timeout beim Ping zur Stripe API. Zahlungen könnten aktuell fehlschlagen.');
        }

        // 4. SMTP Check
        try {
            $host = config('mail.mailers.smtp.host');
            $port = config('mail.mailers.smtp.port');
            if ($host && $port && !in_array($host, ['127.0.0.1', 'localhost', 'log', 'array'])) {
                $start = microtime(true);
                $fp = @fsockopen($host, $port, $errno, $errstr, 2);
                if ($fp) {
                    $time = round((microtime(true) - $start) * 1000);
                    fclose($fp);
                    $health['smtp'] = ['status' => 'connected', 'value' => "Port offen ({$time}ms)", 'error' => null];
                } else {
                    $health['smtp'] = ['status' => 'error', 'value' => 'Blockiert', 'error' => "Verbindung abgelehnt ($host:$port)"];
                    $this->logSystemFailure('smtp', "Mail-Server ($host:$port) hat die Verbindung abgelehnt.");
                }
            } else {
                $health['smtp'] = ['status' => 'connected', 'value' => 'Lokales Log aktiv', 'error' => null];
            }
        } catch (\Exception $e) {
            $health['smtp'] = ['status' => 'error', 'value' => 'Konfig-Fehler', 'error' => 'Mail-Konfiguration fehlerhaft.'];
        }

        // 5. Redis / Cache Check (100% kugelsicher für Lokal & Stage)
        try {
            $start = microtime(true);
            $cacheDriver = config('cache.default');

            if ($cacheDriver === 'redis') {
                // 1. VORAB-CHECK: Ist der Redis-Treiber auf diesem Server (Lokal/Stage) überhaupt installiert?
                $hasPhpRedis = extension_loaded('redis');
                $hasPredis = class_exists(\Predis\Client::class);

                if (!$hasPhpRedis && !$hasPredis) {
                    // Weder die PHP-Extension noch das Composer-Paket existieren!
                    // Wir brechen sofort sanft ab, BEVOR Laravel den tödlichen Fehler wirft.
                    $health['redis'] = ['status' => 'warning', 'value' => 'Fehlt', 'error' => 'Redis-Erweiterung auf diesem Server nicht installiert.'];
                    $this->logSystemFailure('redis', "Der CACHE_DRIVER steht auf redis, aber die PHP-Erweiterung fehlt. (Wahrscheinlich Lokal oder Stage)");
                } else {
                    // Treiber ist da, wir versuchen den Ping über den Cache-Manager
                    try {
                        \Illuminate\Support\Facades\Cache::store('redis')->get('ping_test');
                        $time = round((microtime(true) - $start) * 1000, 1);
                        $health['redis'] = ['status' => 'connected', 'value' => "Latenz: {$time}ms", 'error' => null];
                    } catch (\Exception $e) {
                        $health['redis'] = ['status' => 'error', 'value' => 'Offline', 'error' => 'Redis-Server nicht erreichbar.'];
                        $this->logSystemFailure('redis', 'Redis ist installiert, aber der Service verweigert die Verbindung.', ['exception' => $e->getMessage()]);
                    }
                }
            } else {
                // Wenn in der .env z.B. CACHE_DRIVER=file oder array steht (Standard für Lokal)
                \Illuminate\Support\Facades\Cache::has('test_ping');
                $time = round((microtime(true) - $start) * 1000, 1);
                $health['redis'] = ['status' => 'connected', 'value' => "{$cacheDriver} ({$time}ms)", 'error' => null];
            }
        } catch (\Exception $e) {
            $health['redis'] = ['status' => 'error', 'value' => 'Fehler', 'error' => 'Kritischer Cache-Fehler.'];
            $this->logSystemFailure('redis', 'Cache-Systemausfall: ' . $e->getMessage());
        }

        // 6. Queue Worker Check
        try {
            $pending = \Illuminate\Support\Facades\Queue::size();
            $failed = \Illuminate\Support\Facades\Schema::hasTable('failed_jobs') ? DB::table('failed_jobs')->count() : 0;
            if ($failed > 0) {
                $health['queue'] = ['status' => 'warning', 'value' => "{$pending} wartend", 'error' => "Achtung: {$failed} fehlgeschlagene Jobs!", 'pending' => $pending, 'failed' => $failed];
                $this->logSystemFailure('queue', "Es gibt {$failed} fehlgeschlagene Hintergrund-Jobs.");
            } else {
                $health['queue'] = ['status' => 'connected', 'value' => "{$pending} wartend", 'error' => null, 'pending' => $pending, 'failed' => $failed];
            }
        } catch (\Exception $e) {
            $health['queue'] = ['status' => 'error', 'value' => 'Fehler', 'error' => 'Job-Tabelle nicht erreichbar.', 'pending' => 0, 'failed' => 0];
        }

        // 7. NEU: Scheduler Check (Lebenszeichen vom Cronjob)
        try {
            $lastRunRaw = \Illuminate\Support\Facades\Cache::get('scheduler_last_run');

            if ($lastRunRaw) {
                // Cache-Wert in Carbon umwandeln (könnte Unix-Timestamp sein)
                $lastRun = is_numeric($lastRunRaw) ? \Carbon\Carbon::createFromTimestamp((int)$lastRunRaw) : \Carbon\Carbon::parse($lastRunRaw);

                // Wir nutzen absoluteTo() oder einfach abs() mit diffInMinutes()
                $diffMinutes = (int) abs(now()->diffInMinutes($lastRun));

                if ($diffMinutes < 10) {
                    $text = $diffMinutes == 1 ? "Minute" : "Minuten";
                    $health['scheduler'] = ['status' => 'connected', 'value' => "Aktiv ({$diffMinutes} {$text})", 'error' => null];
                }
            }

            if (!isset($health['scheduler'])) {
                if (app()->environment('local')) {
                    $health['scheduler'] = ['status' => 'connected', 'value' => 'Inaktiv (Lokal OK)', 'error' => null];
                } else {
                    $health['scheduler'] = ['status' => 'warning', 'value' => 'Inaktiv', 'error' => 'Kein Cronjob in den letzten 10 Minuten gelaufen!'];
                    $this->logSystemFailure('scheduler', 'Der Task-Scheduler hat sich seit über 10 Minuten nicht gemeldet. Cronjobs laufen nicht!');
                }
            }
        } catch (\Exception $e) {
            $health['scheduler'] = ['status' => 'error', 'value' => 'Fehler', 'error' => 'Cache nicht lesbar.'];
        }

        // 8. NEU: Backup Check (Zuverlässig direkt über das Dateisystem)
        try {
            $diskName = config('backup.backup.destination.disks.0', 'local');
            // Holt exakt den Namen, den du gerade in der Config gebaut hast
            $backupName = config('backup.backup.name', config('app.name') . '-db-backup');

            $disk = \Illuminate\Support\Facades\Storage::disk($diskName);
            $lastRun = null;
            $pathInfo = "storage/app/" . $backupName; // Für das Tooltip

            if ($disk->exists($backupName)) {
                $files = $disk->files($backupName);
                $latestTime = 0;

                // Suche die neueste ZIP-Datei in diesem Ordner
                foreach ($files as $file) {
                    if (str_ends_with(strtolower($file), '.zip')) {
                        $time = $disk->lastModified($file);
                        if ($time > $latestTime) {
                            $latestTime = $time;
                        }
                    }
                }

                if ($latestTime > 0) {
                    $lastRun = \Carbon\Carbon::createFromTimestamp($latestTime);
                }
            }

            if ($lastRun) {
                $hoursAgo = abs((int)now()->diffInHours($lastRun));
                $text = $hoursAgo == 1 ? "Stunde" : "Stunden";

                if ($hoursAgo < 48) {
                    $health['backup'] = ['status' => 'connected', 'value' => "Sicher ({$hoursAgo} {$text})", 'error' => null, 'path' => $pathInfo];
                } else {
                    $health['backup'] = ['status' => 'warning', 'value' => "Alt ({$hoursAgo} {$text})", 'error' => 'Letztes Backup ist älter als 48 Stunden!', 'path' => $pathInfo];
                    $this->logSystemFailure('backup', 'Das Datenbank-Backup ist überfällig. Gefahr bei Datenverlust!');
                }
            } else {
                $health['backup'] = ['status' => 'warning', 'value' => 'Kein Backup', 'error' => 'Es wurde noch keine ZIP-Datei gefunden!', 'path' => $pathInfo];
            }
        } catch (\Exception $e) {
            $health['backup'] = ['status' => 'error', 'value' => 'Fehler', 'error' => 'Fehler beim Lesen der Festplatte: ' . $e->getMessage(), 'path' => 'Unbekannt'];
        }

        $this->systemHealth = $health;
    }

    public function loadSettings()
    {
        $config = SystemCheckConfig::where('user_id', auth()->id())->first();
        if ($config) {
            $this->filterType = $config->filter_type;
            $this->dateStart = $config->date_start;
            $this->dateEnd = $config->date_end;
            $this->rangeMode = $config->range_mode ?? 'custom';
        } else {
            $this->setWholeYear(false);
        }
    }

    public function saveSettings($rangeMode = 'custom')
    {
        $this->rangeMode = $rangeMode;
        SystemCheckConfig::updateOrCreate(
            ['user_id' => auth()->id()],
            [
                'filter_type' => $this->filterType,
                'date_start' => $this->dateStart,
                'date_end' => $this->dateEnd,
                'range_mode' => $rangeMode
            ]
        );
    }

    public function setCurrentMonth($save = true)
    {
        $this->dateStart = now()->startOfMonth()->format('Y-m-d');
        $this->dateEnd = now()->endOfMonth()->format('Y-m-d');
        if ($save) {
            $this->saveSettings('current_month');
            $this->loadStats(app(FunkiAnalyticsService::class));
        }
    }

    public function setWholeYear($save = true)
    {
        $this->dateStart = now()->startOfYear()->format('Y-m-d');
        $this->dateEnd = now()->endOfYear()->format('Y-m-d');
        if ($save) {
            $this->saveSettings('year');
            $this->loadStats(app(FunkiAnalyticsService::class));
        }
    }

    public function updated($property)
    {
        if (in_array($property, ['dateStart', 'dateEnd', 'filterType'])) {
            $this->saveSettings('custom');
            $this->loadStats(app(FunkiAnalyticsService::class));
        }
    }

    public function loadStats(FunkiAnalyticsService $service)
    {
        $allLogins = $service->getAllLoginsCollection();
        $rawStats = $service->getStats($this->dateStart, $this->dateEnd, $this->filterType, $allLogins);

        $this->stats = json_decode(json_encode($rawStats), true);

        $rawChecks = $service->getHealthChecks();
        $checks = json_decode(json_encode($rawChecks), true);

        if (class_exists(\App\Models\Ticket::class)) {
            $openTickets = \App\Models\Ticket::where('status', 'open')->with('customer')->get();
            $tCount = $openTickets->count();

            $checks['open_tickets'] = [
                'status' => $tCount > 0 ? 'error' : 'success',
                'icon' => 'bi-ticket-detailed',
                'title' => 'Offene Tickets',
                'message' => $tCount > 0 ? $tCount . ' Kundenanfragen warten' : 'Alles beantwortet',
                'count' => $tCount,
                'data' => $openTickets->map(function($t) {
                    return [
                        'id' => $t->id,
                        'ticket_number' => $t->ticket_number,
                        'subject' => $t->subject,
                        'customer_name' => $t->customer ? $t->customer->first_name : 'Kunde'
                    ];
                })->values()->toArray()
            ];
        }

        if (class_exists(\App\Models\Product\ProductReview::class)) {
            $pendingReviews = \App\Models\Product\ProductReview::where('status', 'pending')->with('product')->get();
            $rCount = $pendingReviews->count();

            $checks['product_reviews'] = [
                'status' => $rCount > 0 ? 'error' : 'success',
                'icon' => 'bi-star-half',
                'title' => 'Produkt-Reviews',
                'message' => $rCount > 0 ? $rCount . ' Bewertungen prüfen' : 'Alle geprüft',
                'count' => $rCount,
                'data' => $pendingReviews->map(function($r) {
                    return [
                        'id' => $r->id,
                        'product_name' => $r->product ? $r->product->name : 'Produkt',
                        'rating' => $r->rating
                    ];
                })->values()->toArray()
            ];
        }

        $this->healthChecks = $checks;
        $this->dispatch('update-charts', stats: $this->stats);
    }

    public function updateStock($productId, FunkiAnalyticsService $service)
    {
        $newQty = $this->stockUpdate[$productId] ?? null;
        if ($newQty === null || $newQty < 0) return;

        $product = Product::find($productId);
        if ($product) {
            $product->update(['quantity' => $newQty]);
            unset($this->stockUpdate[$productId]);
            session()->flash('success', 'Bestand aktualisiert.');
            $this->loadStats($service);
        }
    }

    public function uploadContract($itemId, FunkiAnalyticsService $service)
    {
        $this->validate(['uploadFile' => 'required|file|max:10240']);
        $item = FinanceCostItem::find($itemId);
        if ($item) {
            $path = $this->uploadFile->store('contracts', 'public');
            $item->update(['contract_file_path' => $path]);
            $this->reset('uploadFile');
            session()->flash('success', 'Vertrag erfolgreich hochgeladen.');
            $this->loadStats($service);
        }
    }

    public function uploadSpecialReceipt($issueId, FunkiAnalyticsService $service)
    {
        $this->validate(['uploadFile' => 'required|file|max:10240']);
        $issue = FinanceSpecialIssue::find($issueId);
        if ($issue) {
            $path = $this->uploadFile->store('financial/receipts', 'public');
            $files = $issue->file_paths ?? [];
            $files[] = $path;
            $issue->update(['file_paths' => $files]);
            $this->reset('uploadFile');
            session()->flash('success', 'Beleg erfolgreich hochgeladen.');
            $this->loadStats($service);
        }
    }

    public function approveReview($id, FunkiAnalyticsService $service)
    {
        if (class_exists(\App\Models\Product\ProductReview::class)) {
            $review = \App\Models\Product\ProductReview::find($id);
            if ($review) {
                $review->status = 'approved';
                $review->save();
                session()->flash('success', 'Bewertung erfolgreich freigegeben.');
                $this->loadStats($service);
            }
        }
    }

    public function rejectReview($id, FunkiAnalyticsService $service)
    {
        if (class_exists(\App\Models\Product\ProductReview::class)) {
            $review = \App\Models\Product\ProductReview::find($id);
            if ($review) {
                $review->delete();
                session()->flash('success', 'Bewertung abgelehnt und gelöscht.');
                $this->loadStats($service);
            }
        }
    }

    public function toggleHealthCard($key)
    {
        if ($this->expandedHealthKey === $key) {
            $this->expandedHealthKey = null;
        } else {
            $this->expandedHealthKey = $key;
        }
    }

    public function getActiveLoginsProperty()
    {
        $service = app(FunkiAnalyticsService::class);
        $allLogins = $service->getAllLoginsCollection()->sortByDesc('last_seen')->values();

        if (!empty($this->searchLogins)) {
            $search = mb_strtolower($this->searchLogins);
            $allLogins = $allLogins->filter(function($login) use ($search) {
                $fullName = mb_strtolower(($login['first_name'] ?? '') . ' ' . ($login['last_name'] ?? ''));
                $type = mb_strtolower($login['type'] ?? '');
                return str_contains($fullName, $search) || str_contains($type, $search);
            });
        }

        return $allLogins->take(30);
    }

    // Vereinter SystemLog (Zieht FunkiLog und fehlerhafte Logins zusammen)
    public function getSystemLogsProperty()
    {
        $logs = collect();

        // 1. Echte Funki Logs holen
        if (class_exists(GlobalLog::class)) {
            $funki = GlobalLog::orderByDesc('started_at')->limit(30)->get()->map(function($log) {
                return [
                    'id' => 'fl_'.$log->id,
                    'title' => $log->title,
                    'message' => $log->message,
                    'status' => $log->status, // running, success, error, info, warning
                    'type' => $log->type, // automation, ai, marketing, system
                    'timestamp' => $log->started_at,
                ];
            });
            $logs = $logs->concat($funki);
        }

        // 2. Sicherheitswarnungen (Fehlerhafte Logins) als Fake-Logs einfügen
        if (class_exists(LoginAttempt::class)) {
            $failedLogins = LoginAttempt::where('success', false)->orderByDesc('attempted_at')->limit(30)->get()->map(function($fail) {
                return [
                    'id' => 'la_'.$fail->id,
                    'title' => 'Fehlgeschlagener Login',
                    'message' => 'Versuch mit: ' . $fail->email . ' (IP: ' . $fail->ip_address . ')',
                    'status' => 'error', // Löst rote Formatierung aus
                    'type' => 'security', // Eigenes Typ-Label
                    'timestamp' => $fail->attempted_at,
                ];
            });
            $logs = $logs->concat($failedLogins);
        }

        // 3. Zusammen nach Zeit sortieren und nur die neusten 30 zurückgeben
        return $logs->sortByDesc('timestamp')->values()->take(30);
    }

    public function fixSystem($service = null)
    {
        // Start des Repair-Logs
        $this->repairLogs = [];
        $this->addRepairLog("--- SYSTEM HEALING INITIERT ---", 'info');

        $targets = $service ? [$service] : array_keys($this->systemHealth);

        // Gehe alle Targets durch
        foreach ($targets as $target) {

            // Wenn man selektiv klickt ODER wenn im universellen Modus der Status NICHT connected ist
            $healthStatus = $this->systemHealth[$target]['status'] ?? 'connected';

            if ($service || $healthStatus !== 'connected') {
                $this->addRepairLog("Analysiere Problem bei: " . strtoupper($target), 'warning');

                try {
                    switch ($target) {
                        case 'storage':
                            $this->addRepairLog("Räume Log-Files & Framework Caches auf...");
                            // Wir leeren die Standard-Cache Ordner, nicht die Nutzerdaten!
                            \Illuminate\Support\Facades\Artisan::call('view:clear');
                            $this->addRepairLog("✓ Blade Views geleert.", 'success');
                            \Illuminate\Support\Facades\Artisan::call('cache:clear');
                            $this->addRepairLog("✓ Application Cache geleert.", 'success');
                            break;

                        case 'redis':
                        case 'database':
                            $this->addRepairLog("Führe Config & Cache Reset durch...");
                            \Illuminate\Support\Facades\Artisan::call('config:clear');
                            $this->addRepairLog("✓ Config Cache geleert.", 'success');
                            \Illuminate\Support\Facades\Artisan::call('cache:clear');
                            $this->addRepairLog("✓ Application Cache geleert.", 'success');
                            break;

                        case 'queue':
                            $this->addRepairLog("Sende Restart-Signal an Queue Worker...");
                            \Illuminate\Support\Facades\Artisan::call('queue:restart');
                            $this->addRepairLog("✓ Signal gesendet. Supervisor/Daemon sollte Worker neu starten.", 'success');

                            $failed = \Illuminate\Support\Facades\Schema::hasTable('failed_jobs') ? DB::table('failed_jobs')->count() : 0;
                            if ($failed > 0) {
                                $this->addRepairLog("Versuche $failed fehlgeschlagene Jobs neu zu starten...");
                                \Illuminate\Support\Facades\Artisan::call('queue:retry', ['id' => 'all']);
                                $this->addRepairLog("✓ Retry-Befehl für alle fehlgeschlagenen Jobs ausgeführt.", 'success');
                            }
                            break;

                        case 'scheduler':
                            $this->addRepairLog("Erzwinge manuellen Scheduler-Lauf...");
                            \Illuminate\Support\Facades\Artisan::call('schedule:run');
                            $this->addRepairLog("✓ Scheduler manuell getriggert.", 'success');
                            break;

                        case 'backup':
                            $this->addRepairLog("Starte Notfall-Datenbank-Backup im Hintergrund...");
                            // Queueing the backup command to not block the UI
                            \Illuminate\Support\Facades\Artisan::queue('backup:run', ['--only-db' => true]);
                            $this->addRepairLog("✓ Backup-Auftrag erfolgreich in die Warteschlange eingereiht.", 'success');
                            break;

                        case 'stripe':
                        case 'smtp':
                            $this->addRepairLog("Führe Netzwerk Reset durch (Lösche Config Caches)...");
                            \Illuminate\Support\Facades\Artisan::call('config:clear');
                            $this->addRepairLog("✓ Caches geleert. Die API/SMTP Schlüssel werden neu geladen.", 'success');
                            break;

                        case 'ws':
                            $this->addRepairLog("WebSocket Daemon muss serverseitig neugestartet werden.", 'error');
                            $this->addRepairLog("Hinweis: Logge dich via SSH ein und führe 'php artisan reverb:start' oder 'pm2 restart reverb' aus.", 'warning');
                            break;

                        default:
                            $this->addRepairLog("Keine automatisierte Heilung für $target verfügbar.", 'error');
                            break;
                    }
                } catch (\Exception $e) {
                    $this->addRepairLog("Fehler beim Reparieren von $target: " . $e->getMessage(), 'error');
                }
            }
        }

        $this->addRepairLog("Heilungsprozess abgeschlossen. Überprüfe Systemstatus...", 'info');

        // Nach einer Sekunde die System-Health neu abfragen
        sleep(1);
        $this->checkSystemHealth();

        $this->addRepairLog("Systemstatus wurde aktualisiert. Lade Ansicht neu...", 'success');

        // Die Komponente/Seite automatisch neuladen lassen (mit kurzer Verzögerung, damit man den Log lesen kann)
        $this->js('setTimeout(() => window.location.reload(), 2500)');
    }

    private function addRepairLog($message, $type = 'info')
    {
        $this->repairLogs[] = [
            'time' => now()->format('H:i:s'),
            'message' => $message,
            'type' => $type
        ];
    }

    public function render()
    {
        return view('livewire.global.widgets.funki-analytics.funki-analytics', [
            'activeLogins' => $this->activeLogins,
            'systemLogs' => $this->systemLogs,
        ]);
    }
}
