<?php
/**
 * Python Environment Diagnostic & Installer Script
 * Designed for Mittwald Managed Hosting (seelenfunke-stage)
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

$projectRoot = dirname(__DIR__);

// Virtual Env Paths
$venvPath = $projectRoot . '/venv';
$venvPython = $venvPath . '/bin/python3';
$venvPip = $venvPath . '/bin/pip3';

// Standalone Python Paths
$standalonePath = $projectRoot . '/python';
$standalonePython = $standalonePath . '/bin/python3';
$standalonePip = $standalonePath . '/bin/pip3';

// Simple password protection or token access
define('ACCESS_TOKEN', 'sf_python_diagnostics_2026');
$token = $_GET['token'] ?? $_POST['token'] ?? '';
$authorized = ($token === ACCESS_TOKEN);

$action = $_POST['action'] ?? '';
$output = '';

if ($authorized && $action) {
    if ($action === 'create_venv') {
        $cmd = "cd " . escapeshellarg($projectRoot) . " && python3 -m venv venv 2>&1";
        $output .= "Creating virtual environment...\n$ Command: $cmd\n";
        $output .= shell_exec($cmd);
    } elseif ($action === 'install_docx') {
        $cmd = escapeshellarg($venvPip) . " install python-docx 2>&1";
        $output .= "Installing python-docx in venv...\n$ Command: $cmd\n";
        $output .= shell_exec($cmd);
    } elseif ($action === 'pip_user_install') {
        $cmd = "python3 -m pip install --user python-docx 2>&1";
        $output .= "Installing python-docx globally for user...\n$ Command: $cmd\n";
        $output .= shell_exec($cmd);
    } elseif ($action === 'download_standalone') {
        // Detect libc (musl/glibc) to download the correct build
        $isMusl = false;
        if (file_exists('/lib/ld-musl-x86_64.so.1') || file_exists('/lib64/ld-musl-x86_64.so.1')) {
            $isMusl = true;
        } else {
            $lddOutput = @shell_exec('ldd --version 2>&1') ?: '';
            if (stripos($lddOutput, 'musl') !== false) {
                $isMusl = true;
            }
        }
        
        $flavor = $isMusl ? 'musl' : 'gnu';
        $tarballUrl = "https://github.com/astral-sh/python-build-standalone/releases/download/20240107/cpython-3.10.13+20240107-x86_64-unknown-linux-{$flavor}-install_only.tar.gz";
        $tarballPath = $projectRoot . '/python_standalone.tar.gz';
        
        $output .= "Detected libc: " . ($isMusl ? "musl (Alpine Linux)" : "gnu (glibc / Ubuntu / Debian)") . "\n";
        $output .= "Downloading standalone CPython 3.10.13 (approx. 30MB) [flavor: $flavor]...\n";
        $cmdDownload = "curl -L -o " . escapeshellarg($tarballPath) . " " . escapeshellarg($tarballUrl) . " 2>&1";
        $output .= "$ Command: $cmdDownload\n";
        $output .= shell_exec($cmdDownload);
        
        if (file_exists($tarballPath) && filesize($tarballPath) > 5000000) {
            $output .= "Extracting tarball...\n";
            // Make sure the target directory exists and is clean
            if (file_exists($standalonePath)) {
                shell_exec("rm -rf " . escapeshellarg($standalonePath));
            }
            $cmdExtract = "tar -xzf " . escapeshellarg($tarballPath) . " -C " . escapeshellarg($projectRoot) . " 2>&1";
            $output .= "$ Command: $cmdExtract\n";
            $output .= shell_exec($cmdExtract);
            
            if (file_exists($tarballPath)) {
                unlink($tarballPath);
            }
            $output .= "\nExtraction complete! Standalone Python is located at: $standalonePython\n";
        } else {
            $output .= "Error: Download failed or file size too small (" . (file_exists($tarballPath) ? filesize($tarballPath) : 0) . " bytes).\n";
        }
    } elseif ($action === 'install_docx_standalone') {
        $cmd = escapeshellarg($standalonePip) . " install python-docx 2>&1";
        $output .= "Installing python-docx inside standalone Python...\n$ Command: $cmd\n";
        $output .= shell_exec($cmd);
    }
}

// Perform diagnostics
$diag = [];

// 1. Check system python3
$systemPython = shell_exec("which python3 2>&1");
$diag['system_python_path'] = trim($systemPython);
if ($diag['system_python_path'] && strpos($diag['system_python_path'], 'no python3') === false) {
    $diag['system_python_version'] = trim(shell_exec("python3 --version 2>&1"));
} else {
    $diag['system_python_version'] = 'Not found';
}

// 2. Check if venv exists
$diag['venv_exists'] = file_exists($venvPath) && is_dir($venvPath);
if ($diag['venv_exists'] && file_exists($venvPython)) {
    $diag['venv_python_version'] = trim(shell_exec(escapeshellarg($venvPython) . " --version 2>&1"));
} else {
    $diag['venv_python_version'] = 'Not configured';
}

// 3. Check if Standalone Python exists
$diag['standalone_exists'] = file_exists($standalonePython);
if ($diag['standalone_exists']) {
    $diag['standalone_version'] = trim(shell_exec(escapeshellarg($standalonePython) . " --version 2>&1"));
} else {
    $diag['standalone_version'] = 'Not configured';
}

// 4. Test docx library imports
$diag['docx_import_global'] = false;
$diag['docx_import_venv'] = false;
$diag['docx_import_standalone'] = false;

if ($diag['system_python_version'] !== 'Not found') {
    $globalImportTest = shell_exec("python3 -c \"import docx; print('ok')\" 2>&1");
    $diag['docx_import_global'] = (trim($globalImportTest) === 'ok');
}

if ($diag['venv_exists'] && file_exists($venvPython)) {
    $venvImportTest = shell_exec(escapeshellarg($venvPython) . " -c \"import docx; print('ok')\" 2>&1");
    $diag['docx_import_venv'] = (trim($venvImportTest) === 'ok');
}

if ($diag['standalone_exists']) {
    $standaloneImportTest = shell_exec(escapeshellarg($standalonePython) . " -c \"import docx; print('ok')\" 2>&1");
    $diag['docx_import_standalone'] = (trim($standaloneImportTest) === 'ok');
}

?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Python-Umgebung Diagnose & Setup</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Outfit:wght@400;500;600;700&family=Fira+Code:wght@400;500&display=swap" rel="stylesheet">
    <style>
        :root {
            --bg-color: #0b0f19;
            --card-bg: #111827;
            --border-color: #1f2937;
            --text-color: #f3f4f6;
            --text-muted: #9ca3af;
            --primary: #c5a059;
            --primary-hover: #b48e48;
            --success: #10b981;
            --error: #ef4444;
            --warning: #f59e0b;
        }

        body {
            font-family: 'Inter', sans-serif;
            background-color: var(--bg-color);
            color: var(--text-color);
            margin: 0;
            padding: 2rem 1rem;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
        }

        .container {
            max-width: 800px;
            width: 100%;
            background-color: var(--card-bg);
            border: 1px solid var(--border-color);
            border-radius: 16px;
            padding: 2.5rem;
            box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.3), 0 8px 10px -6px rgba(0, 0, 0, 0.3);
        }

        h1 {
            font-family: 'Outfit', sans-serif;
            font-size: 2.2rem;
            margin-top: 0;
            margin-bottom: 0.5rem;
            background: linear-gradient(135deg, #fff 0%, #c5a059 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        p.subtitle {
            color: var(--text-muted);
            margin-bottom: 2rem;
            font-size: 1.1rem;
        }

        .alert {
            background-color: rgba(197, 160, 89, 0.1);
            border-left: 4px solid var(--primary);
            padding: 1rem;
            border-radius: 0 8px 8px 0;
            margin-bottom: 2rem;
            color: #f5e9d3;
        }

        .diag-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1.5rem;
            margin-bottom: 2.5rem;
        }

        .diag-card {
            background-color: rgba(31, 41, 55, 0.5);
            border: 1px solid var(--border-color);
            border-radius: 12px;
            padding: 1.25rem;
        }

        .diag-card h3 {
            margin-top: 0;
            margin-bottom: 0.75rem;
            font-size: 0.9rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: var(--text-muted);
        }

        .diag-value {
            font-size: 1.2rem;
            font-weight: 600;
        }

        .status-badge {
            display: inline-flex;
            align-items: center;
            padding: 0.25rem 0.75rem;
            border-radius: 9999px;
            font-size: 0.85rem;
            font-weight: 500;
        }

        .status-success {
            background-color: rgba(16, 185, 129, 0.1);
            color: var(--success);
            border: 1px solid rgba(16, 185, 129, 0.2);
        }

        .status-error {
            background-color: rgba(239, 68, 68, 0.1);
            color: var(--error);
            border: 1px solid rgba(239, 68, 68, 0.2);
        }

        .status-warning {
            background-color: rgba(245, 158, 11, 0.1);
            color: var(--warning);
            border: 1px solid rgba(245, 158, 11, 0.2);
        }

        .actions-section {
            border-top: 1px solid var(--border-color);
            padding-top: 2rem;
            margin-bottom: 2rem;
        }

        h2 {
            font-family: 'Outfit', sans-serif;
            font-size: 1.5rem;
            margin-top: 0;
            margin-bottom: 1.25rem;
        }

        .btn-group {
            display: flex;
            gap: 1rem;
            flex-wrap: wrap;
            margin-bottom: 1.5rem;
        }

        button {
            font-family: 'Inter', sans-serif;
            font-weight: 500;
            font-size: 0.95rem;
            padding: 0.75rem 1.5rem;
            border-radius: 8px;
            border: none;
            cursor: pointer;
            transition: all 0.2s;
        }

        .btn-primary {
            background-color: var(--primary);
            color: #0b0f19;
        }

        .btn-primary:hover {
            background-color: var(--primary-hover);
        }

        .btn-secondary {
            background-color: transparent;
            color: var(--text-color);
            border: 1px solid var(--border-color);
        }

        .btn-secondary:hover {
            background-color: rgba(255, 255, 255, 0.05);
            border-color: var(--text-muted);
        }

        .output-box {
            background-color: #05070c;
            border: 1px solid var(--border-color);
            border-radius: 8px;
            padding: 1.25rem;
            font-family: 'Fira Code', monospace;
            font-size: 0.9rem;
            color: #10b981;
            white-space: pre-wrap;
            max-height: 300px;
            overflow-y: auto;
            margin-top: 1.5rem;
        }

        .form-auth {
            display: flex;
            flex-direction: column;
            gap: 1rem;
            max-width: 400px;
            margin: 0 auto;
            text-align: center;
        }

        .form-auth input[type="password"] {
            background-color: rgba(31, 41, 55, 0.5);
            border: 1px solid var(--border-color);
            border-radius: 8px;
            padding: 0.75rem 1rem;
            color: white;
            font-size: 1rem;
            text-align: center;
            outline: none;
        }

        .form-auth input[type="password"]:focus {
            border-color: var(--primary);
        }
    </style>
</head>
<body>

<div class="container">
    <?php if (!$authorized): ?>
        <div class="form-auth">
            <h1>Verifizierung erforderlich</h1>
            <p style="color: var(--text-muted);">Geben Sie das Passwort ein, um die Python-Diagnose zu starten.</p>
            <form method="POST">
                <input type="password" name="token" placeholder="Passwort eingeben" required autocomplete="current-password">
                <button type="submit" class="btn-primary" style="margin-top: 1rem; width: 100%;">Anmelden</button>
            </form>
        </div>
    <?php else: ?>
        <h1>Python-Umgebung Diagnose</h1>
        <p class="subtitle">Selbsthilfe-Konfigurationsassistent für den Staging-Server</p>

        <div class="alert">
            <strong>Hinweis zur Serverumgebung:</strong> Da der Standard-PHP-Container von Mittwald kein globales `python3` besitzt und wir keine systemweiten Pakete installieren können, verwenden wir eine portable Standalone-Python-Distribution.
        </div>

        <div class="diag-grid">
            <div class="diag-card">
                <h3>Globales Python 3</h3>
                <div class="diag-value">
                    <?php if ($diag['system_python_version'] !== 'Not found'): ?>
                        <span class="status-badge status-success">Aktiv</span>
                        <div style="font-size: 0.85rem; margin-top: 0.5rem; color: var(--text-muted);">
                            Version: <?= htmlspecialchars($diag['system_python_version']) ?><br>
                            Pfad: <code><?= htmlspecialchars($diag['system_python_path']) ?></code>
                        </div>
                    <?php else: ?>
                        <span class="status-badge status-error">Fehlt</span>
                    <?php endif; ?>
                </div>
            </div>

            <div class="diag-card">
                <h3>Standalone Python</h3>
                <div class="diag-value">
                    <?php if ($diag['standalone_exists']): ?>
                        <span class="status-badge status-success">Aktiv</span>
                        <div style="font-size: 0.85rem; margin-top: 0.5rem; color: var(--text-muted);">
                            Version: <?= htmlspecialchars($diag['standalone_version']) ?><br>
                            Pfad: <code>python/bin/python3</code>
                        </div>
                    <?php else: ?>
                        <span class="status-badge status-error">Fehlt</span>
                        <div style="font-size: 0.85rem; margin-top: 0.5rem; color: var(--text-muted);">
                            Pfad: <code><?= htmlspecialchars($standalonePath) ?></code>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="diag-card">
                <h3>docx-Bibliothek (Standalone)</h3>
                <div class="diag-value">
                    <?php if ($diag['docx_import_standalone']): ?>
                        <span class="status-badge status-success">Verfügbar</span>
                    <?php else: ?>
                        <span class="status-badge status-error">Nicht importierbar</span>
                    <?php endif; ?>
                </div>
            </div>

            <div class="diag-card">
                <h3>Virtual Env (System Python)</h3>
                <div class="diag-value">
                    <?php if ($diag['venv_exists']): ?>
                        <span class="status-badge status-success">Gefunden</span>
                        <div style="font-size: 0.85rem; margin-top: 0.5rem; color: var(--text-muted);">
                            Version: <?= htmlspecialchars($diag['venv_python_version']) ?>
                        </div>
                    <?php else: ?>
                        <span class="status-badge status-warning">Inaktiv</span>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="actions-section">
            <h2>Empfohlener Setup-Weg (Standalone Python)</h2>
            
            <div class="alert" style="background-color: rgba(16, 185, 129, 0.05); border-left-color: var(--success);">
                <strong>Installations-Anleitung:</strong>
                <ol style="margin: 0.5rem 0 0 1.25rem; padding: 0; line-height: 1.6;">
                    <li>Klicke auf <strong>"1. Standalone Python herunterladen & entpacken"</strong>. Dies lädt ein vorkompiliertes CPython 3.10 (30MB) herunter und entpackt es lokal in den Ordner <code>python/</code>.</li>
                    <li>Klicke auf <strong>"2. python-docx im Standalone-Python installieren"</strong>. Dies installiert das DOCX-Paket im lokalen Python.</li>
                    <li>Trage <code>PYTHON_BINARY=/home/p-g27wim/html/seelenfunke-stage/python/bin/python3</code> in Deine <strong>.env</strong> ein.</li>
                </ol>
            </div>

            <div class="btn-group">
                <form method="POST">
                    <input type="hidden" name="token" value="<?= htmlspecialchars($token) ?>">
                    <input type="hidden" name="action" value="download_standalone">
                    <button type="submit" class="btn-primary">1. Standalone Python herunterladen & entpacken</button>
                </form>

                <form method="POST">
                    <input type="hidden" name="token" value="<?= htmlspecialchars($token) ?>">
                    <input type="hidden" name="action" value="install_docx_standalone">
                    <button type="submit" class="btn-primary" <?= !$diag['standalone_exists'] ? 'disabled style="opacity:0.5; cursor:not-allowed;"' : '' ?>>2. python-docx im Standalone-Python installieren</button>
                </form>
            </div>

            <h2>Alternative: System Python (Erfordert python3 auf dem Server)</h2>
            <div class="btn-group">
                <form method="POST">
                    <input type="hidden" name="token" value="<?= htmlspecialchars($token) ?>">
                    <input type="hidden" name="action" value="create_venv">
                    <button type="submit" class="btn-secondary" <?= $diag['system_python_version'] === 'Not found' ? 'disabled style="opacity:0.5; cursor:not-allowed;"' : '' ?>>venv über System-Python erstellen</button>
                </form>

                <form method="POST">
                    <input type="hidden" name="token" value="<?= htmlspecialchars($token) ?>">
                    <input type="hidden" name="action" value="install_docx">
                    <button type="submit" class="btn-secondary" <?= !$diag['venv_exists'] ? 'disabled style="opacity:0.5; cursor:not-allowed;"' : '' ?>>python-docx im venv installieren</button>
                </form>
            </div>
        </div>

        <?php if ($output): ?>
            <h2>Skript-Ausgabe</h2>
            <div class="output-box"><?= htmlspecialchars($output) ?></div>
        <?php endif; ?>

        <div style="text-align: center; margin-top: 3rem;">
            <a href="?" class="btn-secondary" style="text-decoration: none; padding: 0.5rem 1rem; border-radius: 6px;">Seite aktualisieren</a>
        </div>
    <?php endif; ?>
</div>

</body>
</html>
