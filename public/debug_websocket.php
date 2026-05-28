<?php
/**
 * WebSocket & Laravel Reverb Diagnostics
 * Staging Environment Debugger
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

// Sicherheit: Nur auf Staging/Local erlauben, nicht im Live-Betrieb (oder einfach IP checken)
// Wir erlauben es vorerst, da es auf stage.mein-seelenfunke.de läuft.

$webAppPath = dirname(__DIR__); // /html/seelenfunke-stage
$workerAppPath = dirname($webAppPath) . '/worker-stage-3'; // /html/worker-stage-3

$webEnvFile = $webAppPath . '/.env';
$workerEnvFile = $workerAppPath . '/.env';

// Hilfsfunktion zum Lesen einer .env Datei
function parseEnvFile($path) {
    if (!file_exists($path)) {
        return null;
    }
    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    $data = [];
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) continue;
        $parts = explode('=', $line, 2);
        if (count($parts) === 2) {
            $key = trim($parts[0]);
            $val = trim($parts[1], " \t\n\r\0\x0B\"'");
            $data[$key] = $val;
        }
    }
    return $data;
}

// Hilfsfunktion zum Schreiben/Aktualisieren einer .env Datei
function updateEnvFile($path, $updates) {
    if (!file_exists($path)) {
        return false;
    }
    $content = file_get_contents($path);
    foreach ($updates as $key => $value) {
        // Suchmuster für existierende Keys
        $pattern = "/^" . preg_quote($key, '/') . "=(.*)$/m";
        if (preg_match($pattern, $content)) {
            $content = preg_replace($pattern, $key . '="' . $value . '"', $content);
        } else {
            $content .= "\n" . $key . '="' . $value . '"';
        }
    }
    return file_put_contents($path, $content) !== false;
}

$webEnv = parseEnvFile($webEnvFile) ?? [];
$workerEnv = parseEnvFile($workerEnvFile) ?? [];

$action = $_GET['action'] ?? null;
$message = '';
$messageType = '';

if ($action === 'get_bridge_log') {
    header('Content-Type: text/plain; charset=utf-8');
    // Die Node-App läuft in /html/twilio-bridge/
    // debug_websocket.php liegt in /html/seelenfunke-stage/public/
    // Also ist der relative Pfad zu twilio-bridge: ../../twilio-bridge
    $logPath = dirname(dirname(__DIR__)) . '/twilio-bridge/bridge.log';
    if (file_exists($logPath)) {
        $file = file($logPath);
        if ($file === false) {
            echo "Fehler beim Lesen der Datei " . $logPath;
        } else {
            // Zeige die letzten 150 Zeilen
            $lines = array_slice($file, -150);
            echo implode("", $lines);
        }
    } else {
        echo "Die Logdatei " . $logPath . " existiert nicht oder ist für PHP unzugänglich.\n";
        echo "Stelle sicher, dass die Bridge bereits gestartet wurde und in dieses Verzeichnis schreibt.";
    }
    exit;
}

if ($action === 'fix_worker_env') {
    $updates = [
        'REVERB_SERVER_HOST' => '0.0.0.0', // Ganz wichtig: Auf allen Schnittstellen lauschen
        'REVERB_SERVER_PORT' => '6001',
        'REVERB_PORT' => '6001',
        'REVERB_HOST' => 'a-iurgq8',
        'BROADCAST_CONNECTION' => 'reverb',
        'BROADCAST_DRIVER' => 'reverb',
        // Sicherstellen, dass App-Credentials synchron sind
        'REVERB_APP_ID' => $webEnv['REVERB_APP_ID'] ?? ($webEnv['PUSHER_APP_ID'] ?? ''),
        'REVERB_APP_KEY' => $webEnv['REVERB_APP_KEY'] ?? ($webEnv['PUSHER_APP_KEY'] ?? ''),
        'REVERB_APP_SECRET' => $webEnv['REVERB_APP_SECRET'] ?? ($webEnv['PUSHER_APP_SECRET'] ?? ''),
    ];

    if (updateEnvFile($workerEnvFile, $updates)) {
        $message = "Worker .env erfolgreich aktualisiert! Bitte starten Sie nun den Worker-Job per SSH neu.";
        $messageType = "success";
        // Erneut auslesen
        $workerEnv = parseEnvFile($workerEnvFile) ?? [];
    } else {
        $message = "Fehler beim Schreiben der Worker .env Datei unter " . htmlspecialchars($workerEnvFile);
        $messageType = "error";
    }
}

// TCP Connection Test zu Reverb-Worker
$workerHost = $webEnv['REVERB_HOST'] ?? 'a-iurgq8';
$workerPort = $webEnv['REVERB_PORT'] ?? 6001;

$connectionStatus = 'testing';
$connectionError = '';

$fp = @fsockopen($workerHost, $workerPort, $errno, $errstr, 2.0);
if ($fp) {
    $connectionStatus = 'connected';
    fclose($fp);
} else {
    $connectionStatus = 'failed';
    $connectionError = "[$errno] $errstr";
}

// PHP CLI Diagnostik & Interpreter-Checks
$cliDiagnostics = [];
$interpretersToTest = [
    '/usr/bin/php',
    '/usr/bin/php8.4',
    '/usr/bin/php8.3',
    '/usr/bin/php8.2',
    'php'
];
$artisanFile = $webAppPath . '/artisan';
$artisanExists = file_exists($artisanFile);

if (function_exists('exec')) {
    foreach ($interpretersToTest as $interpreter) {
        $outputLines = [];
        $exitCode = -1;
        @exec("$interpreter -v 2>&1", $outputLines, $exitCode);
        if ($exitCode === 0 && !empty($outputLines)) {
            $versionOutput = $outputLines[0];
            
            // Test artisan call command
            $artisanOutputLines = [];
            $artisanExitCode = -1;
            if ($artisanExists) {
                @exec("$interpreter " . escapeshellarg($artisanFile) . " -V 2>&1", $artisanOutputLines, $artisanExitCode);
            }
            
            // Test proc_open availability under this interpreter
            $procOpenOutput = [];
            $procOpenExitCode = -1;
            @exec("$interpreter -r \"echo function_exists('proc_open') ? 'yes' : 'no';\" 2>&1", $procOpenOutput, $procOpenExitCode);
            $procOpenStatus = ($procOpenExitCode === 0 && implode('', $procOpenOutput) === 'yes') ? 'ok' : 'disabled';
            
            $cliDiagnostics[$interpreter] = [
                'status' => 'ok',
                'version' => $versionOutput,
                'artisan_status' => $artisanExitCode === 0 ? 'ok' : 'error',
                'artisan_output' => implode("\n", $artisanOutputLines),
                'proc_open' => $procOpenStatus
            ];
        } else {
            $cliDiagnostics[$interpreter] = [
                'status' => 'not_found',
                'version' => 'Nicht gefunden oder nicht ausführbar',
                'artisan_status' => 'n/a',
                'artisan_output' => '',
                'proc_open' => 'n/a'
            ];
        }
    }
} else {
    $cliDiagnostics['error'] = 'PHP-Funktion `exec` ist deaktiviert!';
}

// Laravel initialisieren
$laravelLoaded = false;
$laravelLoadError = '';
$schedulerActive = false;
$schedulerLastRun = null;
$schedulerDiffMin = 0;
$cronjobList = [];

try {
    require_once $webAppPath . '/vendor/autoload.php';
    $app = require_once $webAppPath . '/bootstrap/app.php';
    $kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
    $kernel->bootstrap();
    $laravelLoaded = true;
    
    // Überlappende Sperren löschen
    if ($action === 'clear_scheduler_locks') {
        try {
            $clearedLocks = 0;
            if (\Illuminate\Support\Facades\Schema::hasTable('system_cronjobs')) {
                $cronjobs = \Illuminate\Support\Facades\DB::table('system_cronjobs')->get();
                foreach ($cronjobs as $job) {
                    $mutexKey = 'framework/schedule-' . sha1($job->command);
                    if (\Illuminate\Support\Facades\Cache::has($mutexKey)) {
                        \Illuminate\Support\Facades\Cache::forget($mutexKey);
                        $clearedLocks++;
                    }
                }
            }
            $message = "Hängende Scheduler-Sperren erfolgreich bereinigt! Gelöschte Sperren: $clearedLocks";
            $messageType = "success";
        } catch (\Exception $ex) {
            $message = "Fehler beim Bereinigen der Sperren: " . $ex->getMessage();
            $messageType = "error";
        }
    }

    // Manuelles Ausführen des Schedulers via GET Parameter (im Web Context)
    if ($action === 'run_scheduler') {
        try {
            \Illuminate\Support\Facades\Artisan::call('schedule:run');
            $message = "Scheduler manuell ausgeführt (im Web-Prozess)!\n\nAusgabe:\n" . \Illuminate\Support\Facades\Artisan::output();
            $messageType = "success";
        } catch (\Exception $ex) {
            $message = "Fehler beim Ausführen des Schedulers: " . $ex->getMessage();
            $messageType = "error";
        }
    }

    // CLI Simulation ausführen
    if ($action === 'run_scheduler_cli') {
        $interpreter = $_GET['interpreter'] ?? '/usr/bin/php';
        if (!in_array($interpreter, $interpretersToTest)) {
            $interpreter = '/usr/bin/php';
        }
        
        if (function_exists('exec')) {
            $cliOutput = [];
            $cliExitCode = -1;
            $cmd = "$interpreter " . escapeshellarg($artisanFile) . " schedule:run 2>&1";
            @exec($cmd, $cliOutput, $cliExitCode);
            
            $message = "CLI Simulation ausgeführt!\nBefehl: $cmd\nExit-Code: $cliExitCode\n\nAusgabe:\n" . implode("\n", $cliOutput);
            $messageType = $cliExitCode === 0 ? "success" : "error";
        } else {
            $message = "Fehler: PHP `exec` Funktion steht auf diesem Server nicht zur Verfügung.";
            $messageType = "error";
        }
    }
    
    // Cache auslesen
    $lastRunRaw = \Illuminate\Support\Facades\Cache::get('scheduler_last_run');
    if ($lastRunRaw) {
        $lastRun = is_numeric($lastRunRaw) ? \Carbon\Carbon::createFromTimestamp((int)$lastRunRaw) : \Carbon\Carbon::parse($lastRunRaw);
        $schedulerLastRun = $lastRun->toDateTimeString();
        $schedulerDiffMin = (int) abs(now()->diffInMinutes($lastRun));
        if ($schedulerDiffMin < 10) {
            $schedulerActive = true;
        }
    }
    
    // Cronjobs aus der DB laden und Sperr-Status prüfen
    if (\Illuminate\Support\Facades\Schema::hasTable('system_cronjobs')) {
        $cronjobs = \Illuminate\Support\Facades\DB::table('system_cronjobs')->get();
        foreach ($cronjobs as $job) {
            $mutexKey = 'framework/schedule-' . sha1($job->command);
            $job->is_locked = \Illuminate\Support\Facades\Cache::has($mutexKey);
            $cronjobList[] = $job;
        }
    }
} catch (\Exception $e) {
    $laravelLoaded = false;
    $laravelLoadError = $e->getMessage();
}

?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>WebSocket Diagnostic Tool - Seelenfunke Staging</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Outfit:wght@400;600;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --bg-color: #0b0f19;
            --card-bg: #111827;
            --primary: #8b5cf6;
            --primary-hover: #a78bfa;
            --success: #10b981;
            --error: #ef4444;
            --warning: #f59e0b;
            --text: #f3f4f6;
            --text-muted: #9ca3af;
            --border: #1f2937;
        }

        body {
            background-color: var(--bg-color);
            color: var(--text);
            font-family: 'Inter', sans-serif;
            margin: 0;
            padding: 2rem 1rem;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            align-items: center;
        }

        .container {
            width: 100%;
            max-width: 900px;
        }

        header {
            margin-bottom: 2rem;
            text-align: center;
        }

        h1 {
            font-family: 'Outfit', sans-serif;
            font-size: 2.5rem;
            margin: 0 0 0.5rem;
            background: linear-gradient(135deg, #a78bfa 0%, #8b5cf6 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .subtitle {
            color: var(--text-muted);
            font-size: 1rem;
        }

        .alert {
            padding: 1rem;
            border-radius: 12px;
            margin-bottom: 1.5rem;
            font-weight: 500;
            border-left: 4px solid transparent;
        }

        .alert-success {
            background-color: rgba(16, 185, 129, 0.1);
            color: var(--success);
            border-left-color: var(--success);
        }

        .alert-error {
            background-color: rgba(239, 68, 68, 0.1);
            color: var(--error);
            border-left-color: var(--error);
        }

        .alert-warning {
            background-color: rgba(245, 158, 11, 0.1);
            color: var(--warning);
            border-left-color: var(--warning);
        }

        .card {
            background-color: var(--card-bg);
            border: 1px solid var(--border);
            border-radius: 16px;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.3);
        }

        .card-title {
            font-family: 'Outfit', sans-serif;
            font-size: 1.25rem;
            font-weight: 600;
            margin-top: 0;
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .badge {
            font-size: 0.75rem;
            padding: 0.25rem 0.75rem;
            border-radius: 9999px;
            font-weight: 600;
        }

        .badge-success {
            background-color: rgba(16, 185, 129, 0.2);
            color: var(--success);
        }

        .badge-error {
            background-color: rgba(239, 68, 68, 0.2);
            color: var(--error);
        }

        .badge-warning {
            background-color: rgba(245, 158, 11, 0.2);
            color: var(--warning);
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 0.5rem;
        }

        th, td {
            text-align: left;
            padding: 0.75rem;
            border-bottom: 1px solid var(--border);
        }

        th {
            color: var(--text-muted);
            font-weight: 500;
            font-size: 0.875rem;
            width: 40%;
        }

        td {
            font-family: monospace;
            font-size: 0.95rem;
        }

        .btn {
            background-color: var(--primary);
            color: white;
            border: none;
            padding: 0.75rem 1.5rem;
            border-radius: 10px;
            font-weight: 600;
            cursor: pointer;
            transition: background-color 0.2s;
            text-decoration: none;
            display: inline-block;
            font-family: 'Inter', sans-serif;
        }

        .btn:hover {
            background-color: var(--primary-hover);
        }

        .icon {
            margin-right: 0.5rem;
        }

        .code-block {
            background: #030712;
            padding: 1rem;
            border-radius: 8px;
            overflow-x: auto;
            margin-top: 0.5rem;
            font-family: monospace;
            border: 1px solid var(--border);
        }

        .flex-container {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1.5rem;
        }

        @media (max-width: 768px) {
            .flex-container {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <header>
            <h1>Seelenfunke WebSockets</h1>
            <div class="subtitle">Staging-Server Diagnose & Reparaturtool</div>
        </header>Document

        <?php if ($message): ?>
            <div class="alert alert-<?php echo $messageType; ?>" style="white-space: pre-wrap; font-family: monospace;">
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>

        <!-- NETZWERK-STATUS -->
        <div class="card">
            <div class="card-title">
                <span>🌐 Netzwerk-Verbindungstest (Web-App zu Worker)</span>
                <?php if ($connectionStatus === 'connected'): ?>
                    <span class="badge badge-success">ERFOLGREICH VERBUNDEN</span>
                <?php else: ?>
                    <span class="badge badge-error">VERBINDUNG FEHLGESCHLAGEN</span>
                <?php endif; ?>
            </div>
            <p style="margin-top: 0; color: var(--text-muted);">
                Die Web-App versucht, eine TCP-Socket-Verbindung aufzubauen zu: <strong style="color: var(--text);"><?php echo htmlspecialchars($workerHost) . ':' . htmlspecialchars($workerPort); ?></strong>
            </p>
            <?php if ($connectionStatus === 'connected'): ?>
                <div class="alert alert-success" style="margin-bottom: 0;">
                    ✓ Der Reverb-Server antwortet auf dem Worker-Container <strong><?php echo htmlspecialchars($workerHost); ?></strong>! Die Firewall und Portfreigaben sind in Ordnung.
                </div>
            <?php else: ?>
                <div class="alert alert-error" style="margin-bottom: 0;">
                    ✗ Fehler beim Verbinden zu <strong><?php echo htmlspecialchars($workerHost) . ':' . htmlspecialchars($workerPort); ?></strong>.<br>
                    <strong>Details:</strong> <?php echo htmlspecialchars($connectionError); ?><br><br>
                    <strong>Mögliche Ursachen:</strong><br>
                    1. Reverb läuft nicht auf dem Worker (SSH-Befehl <code>mittnitectl job status</code> prüfen).<br>
                    2. Reverb lauscht im Worker nur auf <code>127.0.0.1</code> statt <code>0.0.0.0</code> (Worker-.env prüfen und fixen).<br>
                    3. Der Worker-Container hat den falschen Shortcode.
                </div>
            <?php endif; ?>
        </div>

        <!-- CLIENTSEITIGER WSS-TEST -->
        <div class="card">
            <div class="card-title">
                <span>💻 Browser WSS-Verbindungstest</span>
                <span id="wss-badge" class="badge badge-warning">TESTE...</span>
            </div>
            <p style="margin-top: 0; color: var(--text-muted);">
                Der Browser versucht, eine direkte WebSocket-Verbindung (WSS) aufzubauen zu: <strong style="color: var(--text);">wss://ws.mein-seelenfunke.de</strong>
            </p>
            <div id="wss-log" class="code-block" style="font-size: 0.85rem; max-height: 200px; overflow-y: auto;">
                Starte Client-Verbindungstest...<br>
            </div>
        </div>

        <!-- BROWSER GEMINI LIVE WS TEST -->
        <div class="card">
            <div class="card-title">
                <span>🎙️ Browser Gemini-Live (Proxy) Verbindungstest</span>
                <span id="gemini-badge" class="badge badge-warning">TESTE...</span>
            </div>
            <p style="margin-top: 0; color: var(--text-muted);">
                Der Browser versucht, eine direkte WebSocket-Verbindung zum Gemini-Live Proxy aufzubauen zu: <strong style="color: var(--text);"><?php echo htmlspecialchars($webEnv['GEMINI_PROXY_WS_URL'] ?? 'n/a'); ?></strong>
            </p>
            
            <?php 
            $currentHost = $_SERVER['HTTP_HOST'] ?? '';
            $isLocalHost = preg_match('/(localhost|127\.0\.0\.1|seelenfunke\.test)/i', $currentHost);
            $geminiUrl = $webEnv['GEMINI_PROXY_WS_URL'] ?? '';
            
            if (!$isLocalHost && $geminiUrl && (strpos($geminiUrl, '127.0.0.1') !== false || strpos($geminiUrl, 'localhost') !== false || strpos($geminiUrl, '8089') !== false)): 
            ?>
                <div class="alert alert-warning" style="margin-bottom: 1rem;">
                    ⚠️ <strong>Falsche Konfiguration für Staging/Produktion erkannt!</strong><br>
                    Die Variable <code>GEMINI_PROXY_WS_URL</code> zeigt auf <code><?php echo htmlspecialchars($geminiUrl); ?></code>. 
                    Da dieser WebSocket-Verbindungstest im Browser des Benutzers ausgeführt wird, versucht der Browser eine Verbindung zu sich selbst aufzubauen (localhost), was fehlschlägt.<br><br>
                    <strong>Lösung:</strong> Bitte tragen Sie in der <code>.env</code> auf dem Staging-Server folgenden Wert ein:<br>
                    <code>GEMINI_PROXY_WS_URL=wss://api-live-bridge.mein-seelenfunke.de/gemini-live</code>
                </div>
            <?php endif; ?>

            <div id="gemini-log" class="code-block" style="font-size: 0.85rem; max-height: 200px; overflow-y: auto;">
                Starte Gemini-Live Verbindungstest...<br>
            </div>
        </div>

        <!-- NODE.JS BRIDGE LOGS -->
        <div class="card">
            <div class="card-title" style="display: flex; justify-content: space-between; align-items: center;">
                <span>📋 Node.js Gemini Live Bridge Logs (letzte 150 Zeilen)</span>
                <button onclick="loadBridgeLogs()" class="btn" style="padding: 0.25rem 0.5rem; font-size: 0.8rem; margin: 0; background-color: var(--primary); color: white; border: none; border-radius: 4px; cursor: pointer;">Aktualisieren</button>
            </div>
            <p style="margin-top: 0; color: var(--text-muted);">
                Logs der Twilio-Gemini-Live-Bridge aus <code>/html/twilio-bridge/bridge.log</code>.
            </p>
            <div id="bridge-log-content" class="code-block" style="font-size: 0.85rem; max-height: 350px; overflow-y: auto; white-space: pre-wrap; font-family: monospace; line-height: 1.4;">
                Lade Logs...
            </div>
        </div>

        <!-- SCHEDULER-STATUS -->
        <div class="card">
            <div class="card-title">
                <span>⏰ Task-Scheduler & Cronjob Status</span>
                <?php if ($schedulerActive): ?>
                    <span class="badge badge-success">AKTIV (Heartbeat OK)</span>
                <?php else: ?>
                    <span class="badge badge-error">INAKTIV / FEHLER</span>
                <?php endif; ?>
            </div>
            
            <?php if (!$laravelLoaded): ?>
                <div class="alert alert-error" style="margin-bottom: 0;">
                    ✗ Laravel konnte nicht geladen werden! Das deutet auf einen fatalen Fehler beim Bootstrappen (Datenbank, ENV, Syntax) hin.<br>
                    <strong>Fehlermeldung:</strong> <?php echo htmlspecialchars($laravelLoadError); ?>
                </div>
            <?php else: ?>
                <p style="margin-top: 0; color: var(--text-muted);">
                    Letzter erfolgreicher Scheduler-Lauf (im Cache): 
                    <strong style="color: var(--text);">
                        <?php echo $schedulerLastRun ? $schedulerLastRun . ' (vor ' . $schedulerDiffMin . ' Minuten)' : 'Bisher kein Eintrag im Cache'; ?>
                    </strong>
                </p>
                
                <?php if (!$schedulerActive): ?>
                    <div class="alert alert-warning">
                        ⚠️ Der Scheduler hat sich seit über 10 Minuten nicht gemeldet. Wenn der Cronjob im Mittwald-Panel aktiv ist, kann es sein, dass die Ausführung durch PHP-Fehler blockiert wird. Klicke unten auf "Scheduler manuell ausführen", um Fehler zu sehen!
                    </div>
                <?php endif; ?>
                
                <h3 style="font-family: 'Outfit', sans-serif; font-size: 1.1rem; margin-top: 1.5rem; margin-bottom: 0.75rem;">Datenbank-Cronjobs (system_cronjobs):</h3>
                <div style="overflow-x: auto;">
                    <table style="font-size: 0.85rem;">
                        <thead>
                            <tr>
                                <th style="width: 30%;">Befehl</th>
                                <th>Schedule</th>
                                <th>Aktiv?</th>
                                <th>Letzter Lauf</th>
                                <th>Status</th>
                                <th>Sperre</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($cronjobList)): ?>
                                <tr>
                                    <td colspan="6" style="text-align: center; color: var(--text-muted);">Keine Cronjobs in der Tabelle gefunden oder Tabelle leer.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($cronjobList as $job): ?>
                                    <tr>
                                        <td>
                                            <strong><?php echo htmlspecialchars($job->name); ?></strong><br>
                                            <small style="color: var(--text-muted);">php artisan <?php echo htmlspecialchars($job->command); ?> <?php echo htmlspecialchars($job->parameters ?? ''); ?></small>
                                        </td>
                                        <td><code><?php echo htmlspecialchars($job->schedule); ?></code></td>
                                        <td>
                                            <?php if ($job->is_active): ?>
                                                <span style="color: var(--success); font-weight: bold;">JA</span>
                                            <?php else: ?>
                                                <span style="color: var(--error);">NEIN</span>
                                            <?php endif; ?>
                                        </td>
                                        <td><?php echo $job->last_run_at ? htmlspecialchars($job->last_run_at) : '<em style="color: var(--text-muted);">nie</em>'; ?></td>
                                        <td>
                                            <?php if ($job->status === 'success'): ?>
                                                <span class="badge badge-success">success</span>
                                            <?php elseif ($job->status === 'error'): ?>
                                                <span class="badge badge-error">error</span>
                                            <?php else: ?>
                                                <span class="badge badge-warning"><?php echo htmlspecialchars($job->status ?? 'pending'); ?></span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if (isset($job->is_locked) && $job->is_locked): ?>
                                                <span class="badge badge-error" title="Sperr-Mutex in Cache vorhanden">Gesperrt</span>
                                            <?php else: ?>
                                                <span class="badge badge-success">Frei</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>

        <!-- PHP CLI DIAGNOSTIK & INTERPRETER -->
        <div class="card">
            <div class="card-title">
                <span>💻 PHP CLI & Interpreter Diagnostik</span>
                <?php if (function_exists('exec')): ?>
                    <span class="badge badge-success">EXEC() AKTIV</span>
                <?php else: ?>
                    <span class="badge badge-error">EXEC() INAKTIV</span>
                <?php endif; ?>
            </div>
            
            <p style="margin-top: 0; color: var(--text-muted);">
                Der Laravel-Scheduler wird über Cronjobs auf der Kommandozeile (CLI) ausgeführt. Hier sind die auf dem Server getesteten PHP-Interpreter:
            </p>

            <?php if (!function_exists('exec')): ?>
                <div class="alert alert-error" style="margin-bottom: 0;">
                    Die PHP-Funktion <code>exec()</code> ist für den Web-Prozess gesperrt. Eine CLI-Diagnose kann nicht durchgeführt werden.
                </div>
            <?php else: ?>
                <div style="overflow-x: auto;">
                    <table style="font-size: 0.85rem; width: 100%;">
                        <thead>
                            <tr>
                                <th style="width: 20%;">Interpreter</th>
                                <th style="width: 40%;">CLI PHP Version</th>
                                <th style="width: 15%;">artisan -V</th>
                                <th style="width: 15%;">proc_open</th>
                                <th style="width: 10%;">Aktion</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($cliDiagnostics as $path => $diag): ?>
                                <tr>
                                    <td><strong><?php echo htmlspecialchars($path); ?></strong></td>
                                    <td>
                                        <?php if ($diag['status'] === 'ok'): ?>
                                            <span style="color: var(--text);"><?php echo htmlspecialchars($diag['version']); ?></span>
                                            <?php 
                                            if (preg_match('/PHP\s+([0-9.]+)/i', $diag['version'], $m)) {
                                                $vNum = $m[1];
                                                if (version_compare($vNum, '8.4.0', '<')) {
                                                    echo '<br><small style="color: var(--warning); font-weight: bold;">⚠️ Inkompatibel! Erfordert PHP ^8.4</small>';
                                                } else {
                                                    echo '<br><small style="color: var(--success); font-weight: bold;">✓ Kompatibel (PHP 8.4+)</small>';
                                                }
                                            }
                                            ?>
                                        <?php else: ?>
                                            <span style="color: var(--text-muted); font-style: italic;"><?php echo htmlspecialchars($diag['version']); ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($diag['artisan_status'] === 'ok'): ?>
                                            <span class="badge badge-success">OK</span>
                                        <?php elseif ($diag['artisan_status'] === 'error'): ?>
                                            <span class="badge badge-error" title="<?php echo htmlspecialchars($diag['artisan_output']); ?>">FEHLER</span>
                                            <div style="font-size: 0.75rem; color: var(--error); margin-top: 0.25rem; font-family: monospace; max-height: 80px; overflow-y: auto; white-space: pre-wrap;"><?php echo htmlspecialchars(substr($diag['artisan_output'], 0, 150)); ?>...</div>
                                        </td>
                                    <?php else: ?>
                                        <span class="badge badge-warning">n/a</span>
                                    <?php endif; ?>
                                    <td>
                                        <?php if (($diag['proc_open'] ?? '') === 'ok'): ?>
                                            <span style="color: var(--success); font-weight: bold;">JA</span>
                                        <?php elseif (($diag['proc_open'] ?? '') === 'disabled'): ?>
                                            <span style="color: var(--error); font-weight: bold;">NEIN</span>
                                        <?php else: ?>
                                            <span style="color: var(--text-muted);">n/a</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($diag['status'] === 'ok'): ?>
                                            <a href="?action=run_scheduler_cli&interpreter=<?php echo urlencode($path); ?>" class="btn" style="padding: 0.25rem 0.5rem; font-size: 0.75rem; font-weight: 500; border-radius: 6px;">Simulation</a>
                                        <?php else: ?>
                                            -
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>

        <div class="flex-container">
            <!-- WEB APP CONFIG -->
            <div class="card">
                <div class="card-title">
                    <span>💻 Web-App .env</span>
                    <span class="badge badge-warning" style="background-color: rgba(245, 158, 11, 0.1); color: var(--warning);">seelenfunke-stage</span>
                </div>
                <table>
                    <tr>
                        <th>APP_URL</th>
                        <td><?php echo htmlspecialchars($webEnv['APP_URL'] ?? 'n/a'); ?></td>
                    </tr>
                    <tr>
                        <th>REVERB_HOST</th>
                        <td><?php echo htmlspecialchars($webEnv['REVERB_HOST'] ?? 'n/a'); ?></td>
                    </tr>
                    <tr>
                        <th>REVERB_PORT</th>
                        <td><?php echo htmlspecialchars($webEnv['REVERB_PORT'] ?? 'n/a'); ?></td>
                    </tr>
                    <tr>
                        <th>REVERB_SERVER_PORT</th>
                        <td><?php echo htmlspecialchars($webEnv['REVERB_SERVER_PORT'] ?? 'n/a'); ?></td>
                    </tr>
                    <tr>
                        <th>BROADCAST_CONNECTION</th>
                        <td><?php echo htmlspecialchars($webEnv['BROADCAST_CONNECTION'] ?? 'n/a'); ?></td>
                    </tr>
                    <tr>
                        <th>REVERB_APP_KEY</th>
                        <td><?php echo htmlspecialchars($webEnv['REVERB_APP_KEY'] ?? ($webEnv['PUSHER_APP_KEY'] ?? 'n/a')); ?></td>
                    </tr>
                    <tr>
                        <th>GEMINI_PROXY_WS_URL</th>
                        <td style="<?php echo (strpos($webEnv['GEMINI_PROXY_WS_URL'] ?? '', '127.0.0.1') !== false || strpos($webEnv['GEMINI_PROXY_WS_URL'] ?? '', 'localhost') !== false) ? 'color: var(--warning); font-weight: bold;' : ''; ?>">
                            <?php echo htmlspecialchars($webEnv['GEMINI_PROXY_WS_URL'] ?? 'n/a'); ?>
                        </td>
                    </tr>
                    <tr>
                        <th>TWILIO_WSS_URL</th>
                        <td><?php echo htmlspecialchars($webEnv['TWILIO_WSS_URL'] ?? 'n/a'); ?></td>
                    </tr>
                </table>
            </div>

            <!-- WORKER CONFIG -->
            <div class="card">
                <div class="card-title">
                    <span>👷 Worker-App .env</span>
                    <span class="badge badge-warning" style="background-color: rgba(245, 158, 11, 0.1); color: var(--warning);">worker-stage-3</span>
                </div>
                <?php if (!$workerEnv): ?>
                    <p style="color: var(--error);">.env Datei unter <code><?php echo htmlspecialchars($workerEnvFile); ?></code> konnte nicht gelesen werden!</p>
                <?php else: ?>
                    <table>
                        <tr>
                            <th>REVERB_SERVER_HOST</th>
                            <td style="<?php echo ($workerEnv['REVERB_SERVER_HOST'] ?? '') !== '0.0.0.0' ? 'color: var(--error); font-weight: bold;' : ''; ?>">
                                <?php echo htmlspecialchars($workerEnv['REVERB_SERVER_HOST'] ?? 'n/a'); ?>
                                <?php if (($workerEnv['REVERB_SERVER_HOST'] ?? '') !== '0.0.0.0'): ?> (Sollte 0.0.0.0 sein!)<?php endif; ?>
                            </td>
                        </tr>
                        <tr>
                            <th>REVERB_SERVER_PORT</th>
                            <td><?php echo htmlspecialchars($workerEnv['REVERB_SERVER_PORT'] ?? 'n/a'); ?></td>
                        </tr>
                        <tr>
                            <th>REVERB_PORT</th>
                            <td><?php echo htmlspecialchars($workerEnv['REVERB_PORT'] ?? 'n/a'); ?></td>
                        </tr>
                        <tr>
                            <th>REVERB_HOST</th>
                            <td><?php echo htmlspecialchars($workerEnv['REVERB_HOST'] ?? 'n/a'); ?></td>
                        </tr>
                        <tr>
                            <th>REVERB_APP_KEY</th>
                            <td style="<?php echo ($workerEnv['REVERB_APP_KEY'] ?? '') !== ($webEnv['REVERB_APP_KEY'] ?? '') ? 'color: var(--error); font-weight: bold;' : ''; ?>">
                                <?php echo htmlspecialchars($workerEnv['REVERB_APP_KEY'] ?? 'n/a'); ?>
                                <?php if (($workerEnv['REVERB_APP_KEY'] ?? '') !== ($webEnv['REVERB_APP_KEY'] ?? '')): ?> (Mismatch!)<?php endif; ?>
                            </td>
                        </tr>
                    </table>
                <?php endif; ?>
            </div>
        </div>

        <!-- AKTIONEN -->
        <div class="card" style="text-align: center;">
            <div class="card-title" style="justify-content: center;">🛠️ Systemaktionen</div>
            <p>Falls die Worker-Konfiguration Fehler aufweist (z.B. falsches <code>REVERB_SERVER_HOST</code> oder Key-Mismatch), können Sie dies hier beheben.</p>
            <div style="display: flex; justify-content: center; gap: 0.75rem; flex-wrap: wrap; margin-top: 1.5rem;">
                <a href="?action=fix_worker_env" class="btn">Worker .env automatisch reparieren</a>
                <a href="?action=clear_scheduler_locks" class="btn" style="background-color: var(--error);">Hängende Sperren bereinigen</a>
                <a href="?action=run_scheduler" class="btn" style="background-color: var(--warning);">Scheduler manuell ausführen</a>
                <a href="?" class="btn" style="background-color: var(--card-bg); border: 1px solid var(--border); color: var(--text);">Status aktualisieren</a>
            </div>
        </div>

        <!-- NÄCHSTE MANUELLE SCHRITTE -->
        <div class="card">
            <div class="card-title">📋 Nächste manuelle Schritte</div>
            <ol style="line-height: 1.6; padding-left: 1.25rem;">
                <li>
                    <strong>Duplikate im Mittwald-Panel löschen:</strong> Bitte logge dich ins Mittwald-mStudio ein. Stelle sicher, dass unter der App <code>seelenfunke-stage</code> <strong>kein</strong> Cronjob mehr läuft, der <code>reverb:start</code> ausführt. Nur der Scheduler (<code>schedule:run</code>) darf als Cronjob eingerichtet sein.
                </li>
                <li>
                    <strong>Worker-Job neu starten:</strong> Logge dich per SSH in den Worker-Container <code>a-iurgq8</code> ein und führe folgenden Befehl aus:
                    <div class="code-block">mittnitectl job restart</div>
                </li>
                <li>
                    <strong>Status des Workers prüfen:</strong>
                    <div class="code-block">mittnitectl job status</div>
                    Der Job <code>web</code> (oder wie auch immer er unter mittnite heißt) sollte im Status <code>running</code> sein.
                </li>
            </ol>
        </div>

    </div>

    <script>
        (function() {
            // 1. REVERB WSS TEST
            const wssLog = document.getElementById('wss-log');
            const wssBadge = document.getElementById('wss-badge');
            
            function log(msg, type = 'info') {
                const colors = {
                    info: '#9ca3af',
                    success: '#10b981',
                    error: '#ef4444',
                    warning: '#f59e0b'
                };
                const color = colors[type] || colors.info;
                wssLog.innerHTML += `<span style="color: ${color}">[${new Date().toLocaleTimeString()}] ${msg}</span><br>`;
                wssLog.scrollTop = wssLog.scrollHeight;
            }

            const appKey = '<?php echo htmlspecialchars($webEnv['REVERB_APP_KEY'] ?? ($webEnv['PUSHER_APP_KEY'] ?? 'seelenfunke-key')); ?>';
            const url = `wss://ws.mein-seelenfunke.de/app/${appKey}?protocol=7&client=js&version=8.4.0-rc2&flash=false`;
            
            log(`Verbinde mit ${url}...`, 'info');
            
            try {
                const ws = new WebSocket(url);
                
                ws.onopen = function() {
                    log('✓ Verbindung erfolgreich geöffnet!', 'success');
                    wssBadge.className = 'badge badge-success';
                    wssBadge.textContent = 'ERFOLGREICH VERBUNDEN';
                    ws.send(JSON.stringify({event: 'pusher:ping', data: {}}));
                };
                
                ws.onmessage = function(evt) {
                    log(`→ Nachricht empfangen: ${evt.data}`, 'info');
                };
                
                ws.onerror = function(err) {
                    log('✗ WebSocket-Fehler aufgetreten! (Verbindung blockiert, Proxy-Fehler oder SSL-Problem)', 'error');
                    console.error(err);
                };
                
                ws.onclose = function(evt) {
                    log(`ℹ Verbindung geschlossen. Code: ${evt.code}, Grund: ${evt.reason || 'keiner'}, Sauber beendet: ${evt.wasClean}`, 'warning');
                    if (wssBadge.textContent === 'TESTE...') {
                        wssBadge.className = 'badge badge-error';
                        wssBadge.textContent = 'VERBINDUNG FEHLGESCHLAGEN';
                    }
                };
            } catch(e) {
                log(`✗ Ausnahme beim Erstellen des WebSockets: ${e.message}`, 'error');
                wssBadge.className = 'badge badge-error';
                wssBadge.textContent = 'AUSNAHME';
            }

            // 2. GEMINI LIVE PROXY WSS TEST
            const geminiLog = document.getElementById('gemini-log');
            const geminiBadge = document.getElementById('gemini-badge');
            const geminiUrl = '<?php echo htmlspecialchars($webEnv['GEMINI_PROXY_WS_URL'] ?? ''); ?>';

            function logGemini(msg, type = 'info') {
                const colors = {
                    info: '#9ca3af',
                    success: '#10b981',
                    error: '#ef4444',
                    warning: '#f59e0b'
                };
                const color = colors[type] || colors.info;
                geminiLog.innerHTML += `<span style="color: ${color}">[${new Date().toLocaleTimeString()}] ${msg}</span><br>`;
                geminiLog.scrollTop = geminiLog.scrollHeight;
            }

            if (!geminiUrl) {
                logGemini('✗ Keine GEMINI_PROXY_WS_URL in der .env konfiguriert!', 'error');
                geminiBadge.className = 'badge badge-error';
                geminiBadge.textContent = 'NICHT KONFIGURIERT';
            } else {
                let testGeminiUrl = geminiUrl;
                if (window.location.protocol === 'https:' && testGeminiUrl.startsWith('ws://')) {
                    logGemini(`⚠️ Seite läuft über HTTPS, aber URL startet mit ws://. Passe temporär im Browser an wss:// an...`, 'warning');
                    testGeminiUrl = testGeminiUrl.replace('ws://', 'wss://');
                }

                logGemini(`Verbinde mit ${testGeminiUrl} (Handshake-Test)...`, 'info');
                
                try {
                    const gws = new WebSocket(testGeminiUrl);
                    
                    gws.onopen = function() {
                        logGemini('✓ Verbindung erfolgreich geöffnet! Warte auf Handshake-Antwort...', 'success');
                    };
                    
                    gws.onmessage = function(evt) {
                        logGemini(`→ Nachricht empfangen: ${evt.data}`, 'info');
                    };
                    
                    gws.onerror = function(err) {
                        logGemini('✗ WebSocket-Fehler aufgetreten! (Verbindung blockiert, DNS-Fehler, SSL-Problem oder Port nicht erreichbar)', 'error');
                        console.error(err);
                    };
                    
                    gws.onclose = function(evt) {
                        // Der Proxy schließt ohne Token mit 4001 oder 4003 (oder ähnlichen custom codes)
                        if (evt.code === 4001 || evt.code === 4003 || evt.code === 1000) {
                            logGemini(`✓ Server hat geantwortet! Verbindung geschlossen mit Code ${evt.code} (${evt.reason || 'Token fehlt / Normal'}). Dies bestätigt, dass der Server ERREICHBAR und AKTIV ist!`, 'success');
                            geminiBadge.className = 'badge badge-success';
                            geminiBadge.textContent = 'ERFOLGREICH VERBUNDEN';
                        } else {
                            logGemini(`ℹ Verbindung geschlossen. Code: ${evt.code}, Grund: ${evt.reason || 'keiner'}, Sauber beendet: ${evt.wasClean}`, 'warning');
                            if (geminiBadge.textContent === 'TESTE...') {
                                geminiBadge.className = 'badge badge-error';
                                geminiBadge.textContent = 'VERBINDUNG FEHLGESCHLAGEN';
                            }
                        }
                    };
                } catch(e) {
                    logGemini(`✗ Ausnahme beim Erstellen des WebSockets: ${e.message}`, 'error');
                    geminiBadge.className = 'badge badge-error';
                    geminiBadge.textContent = 'AUSNAHME';
                }
            }
        })();

        function loadBridgeLogs() {
            const container = document.getElementById('bridge-log-content');
            container.innerText = "Lade Logs...";
            fetch('?action=get_bridge_log')
                .then(res => res.text())
                .then(text => {
                    container.innerText = text;
                    container.scrollTop = container.scrollHeight;
                })
                .catch(err => {
                    container.innerText = "Fehler beim Laden der Logs: " + err.message;
                });
        }
        // Logs beim Laden der Seite initial abrufen
        window.addEventListener('DOMContentLoaded', loadBridgeLogs);
    </script>
</body>
</html>
