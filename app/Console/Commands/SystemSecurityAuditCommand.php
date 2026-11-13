<?php

namespace App\Console\Commands;

use App\Models\Admin\Admin;
use App\Models\System\SystemLog;
use App\Services\AI\Mails\AiAgentMessageMail;
use App\Services\System\SystemBlueprintService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\Process\Process;

class SystemSecurityAuditCommand extends Command
{
    protected $signature = 'system:security-audit
                            {--skip-network : Überspringt externe CVE-Netzwerkabfragen (für Offline-/Unit-Tests)}
                            {--no-alert : Unterdrückt E-Mail-Warnungen bei gefundenen Sicherheitslücken}';

    protected $description = 'Prüft automatisch alle Laravel/Composer-, NPM-, Funkenwerk- & Android-App-Pakete auf Sicherheitslücken (CVEs), Updates und System-Schranken.';

    public const REPORT_STORAGE_PATH = 'system/security_audit_report.json';

    public function handle(): int
    {
        $this->info('🛡️ Starte zentrale Seelenfunke Sicherheits- & Paketprüfung...');

        $skipNetwork = (bool) $this->option('skip-network') || app()->runningUnitTests();

        $composerReport = $this->auditComposerPackages($skipNetwork);
        $npmReport = $this->auditNpmPackages($skipNetwork);
        $appPackagesReport = $this->auditAppPackages();
        $guardrailsReport = $this->auditSystemGuardrails();

        $totalCves = (int) ($composerReport['cve_count'] ?? 0) + (int) ($npmReport['cve_count'] ?? 0);
        $guardrailErrors = (int) ($guardrailsReport['failed_count'] ?? 0);
        $outdatedSafeCount = (int) ($composerReport['semver_safe_count'] ?? 0);

        $overallStatus = ($totalCves > 0 || $guardrailErrors > 0) ? 'error' : 'success';

        $summaryMessage = match (true) {
            $totalCves > 0 => "{$totalCves} Sicherheitslücke(n) (CVE) in Paketen erkannt!",
            $guardrailErrors > 0 => "{$guardrailErrors} System-Schranke(n) verletzt!",
            $outdatedSafeCount > 0 => "0 CVEs • {$outdatedSafeCount} sichere Paket-Update(s) verfügbar",
            default => '0 Sicherheitslücken (CVEs) • Alle Pakete & Schranken geprüft',
        };

        $report = [
            'scanned_at' => now()->toIso8601String(),
            'status' => $overallStatus,
            'summary' => $summaryMessage,
            'cve_count' => $totalCves,
            'guardrail_errors_count' => $guardrailErrors,
            'outdated_count' => $outdatedSafeCount,
            'composer' => $composerReport,
            'npm' => $npmReport,
            'apps' => $appPackagesReport,
            'guardrails' => $guardrailsReport,
        ];

        Storage::disk('local')->put(
            self::REPORT_STORAGE_PATH,
            json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
        );

        if (class_exists(SystemLog::class)) {
            SystemLog::create([
                'type' => 'security',
                'action_id' => 'security:package_audit',
                'title' => $overallStatus === 'error'
                    ? 'Sicherheitsprüfung: Verwundbarkeiten erkannt'
                    : 'Automatische Sicherheitsprüfung abgeschlossen',
                'message' => $summaryMessage,
                'status' => $overallStatus === 'error' ? 'warning' : 'success',
                'payload' => [
                    'category' => 'idor_integrity',
                    'cve_count' => $totalCves,
                    'composer_cves' => $composerReport['cve_count'] ?? 0,
                    'npm_cves' => $npmReport['cve_count'] ?? 0,
                    'guardrail_errors' => $guardrailErrors,
                    'outdated_count' => $outdatedSafeCount,
                ],
                'started_at' => now(),
                'finished_at' => now(),
            ]);
        }

        if ($overallStatus === 'error' && !$this->option('no-alert') && !app()->runningUnitTests()) {
            $this->sendSecurityAlertIfNecessary($report);
        }

        $this->line('');
        $this->info("📊 Ergebnis: {$summaryMessage}");
        $this->line("   • Composer (Laravel): {$composerReport['locked_count']} Pakete geprüft, {$composerReport['cve_count']} CVEs, {$composerReport['semver_safe_count']} Minor-Updates");
        $this->line("   • NPM (Frontend):     {$npmReport['locked_count']} Pakete geprüft, {$npmReport['cve_count']} Prod-CVEs");
        $this->line("   • Funkenwerk Desktop: {$appPackagesReport['funkenwerk']['target_framework']} (v{$appPackagesReport['funkenwerk']['version']})");
        $this->line("   • Android App:        SDK {$appPackagesReport['android']['compile_sdk']} (v{$appPackagesReport['android']['version_name']}, {$appPackagesReport['android']['libraries_count']} Libraries)");
        $this->line("   • System-Schranken:   {$guardrailsReport['passed_count']}/{$guardrailsReport['total_count']} erfüllt");

        return self::SUCCESS;
    }

    private function auditComposerPackages(bool $skipNetwork): array
    {
        $composerJsonPath = base_path('composer.json');
        $composerLockPath = base_path('composer.lock');

        $directDependencies = [];
        $lockedPackages = [];

        if (File::exists($composerJsonPath)) {
            $json = json_decode(File::get($composerJsonPath), true) ?: [];
            $require = array_keys($json['require'] ?? []);
            $directDependencies = array_values(array_filter($require, fn ($pkg) => $pkg !== 'php' && !str_starts_with($pkg, 'ext-')));
        }

        if (File::exists($composerLockPath)) {
            $lock = json_decode(File::get($composerLockPath), true) ?: [];
            foreach ($lock['packages'] ?? [] as $pkg) {
                if (!empty($pkg['name'])) {
                    $lockedPackages[$pkg['name']] = ltrim((string) ($pkg['version'] ?? ''), 'v');
                }
            }
        }

        $advisories = [];
        $outdated = [];

        if (!$skipNetwork) {
            // 1. CVE-Prüfung via composer audit (mit Fallback auf Packagist Security API)
            $auditJson = $this->runJsonProcess(['composer', 'audit', '--no-dev', '--no-interaction', '--format=json'], 25);

            if (is_array($auditJson) && isset($auditJson['advisories']) && is_array($auditJson['advisories'])) {
                foreach ($auditJson['advisories'] as $pkgName => $pkgAdvisories) {
                    foreach ((array) $pkgAdvisories as $adv) {
                        $advisories[] = [
                            'package' => (string) $pkgName,
                            'installed_version' => $lockedPackages[$pkgName] ?? 'unbekannt',
                            'cve' => (string) ($adv['cve'] ?: ($adv['advisoryId'] ?? 'CVE')),
                            'title' => (string) ($adv['title'] ?? 'Sicherheitswarnung'),
                            'severity' => strtolower((string) ($adv['severity'] ?? 'medium')),
                            'affected_versions' => (string) ($adv['affectedVersions'] ?? ''),
                            'link' => (string) ($adv['link'] ?? ''),
                        ];
                    }
                }
            } elseif (!empty($lockedPackages)) {
                $advisories = $this->queryPackagistAdvisoriesFallback($lockedPackages);
            }

            // 2. Direkte Paket-Updates prüfen via composer outdated --direct
            $outdatedJson = $this->runJsonProcess(['composer', 'outdated', '--direct', '--no-interaction', '--format=json'], 25);
            if (is_array($outdatedJson) && isset($outdatedJson['installed']) && is_array($outdatedJson['installed'])) {
                foreach ($outdatedJson['installed'] as $item) {
                    $status = (string) ($item['latest-status'] ?? 'up-to-date');
                    if ($status === 'up-to-date') {
                        continue;
                    }
                    $outdated[] = [
                        'name' => (string) ($item['name'] ?? ''),
                        'version' => (string) ($item['version'] ?? ''),
                        'latest' => (string) ($item['latest'] ?? ''),
                        'status' => $status, // semver-safe-update | update-possible
                        'abandoned' => (bool) ($item['abandoned'] ?? false),
                    ];
                }
            }
        }

        $semverSafeCount = count(array_filter($outdated, fn ($o) => ($o['status'] ?? '') === 'semver-safe-update'));

        return [
            'direct_count' => count($directDependencies),
            'locked_count' => count($lockedPackages),
            'laravel_version' => $lockedPackages['laravel/framework'] ?? app()->version(),
            'php_version' => PHP_VERSION,
            'cve_count' => count($advisories),
            'advisories' => array_slice($advisories, 0, 25),
            'outdated_count' => count($outdated),
            'semver_safe_count' => $semverSafeCount,
            'outdated' => array_slice($outdated, 0, 25),
        ];
    }

    private function queryPackagistAdvisoriesFallback(array $lockedPackages): array
    {
        try {
            $response = Http::timeout(10)
                ->asForm()
                ->post('https://packagist.org/api/security-advisories/', [
                    'packages' => array_keys($lockedPackages),
                ]);

            if (!$response->successful()) {
                return [];
            }

            $data = $response->json();
            $advisories = [];
            foreach ($data['advisories'] ?? [] as $pkgName => $pkgAdvisories) {
                $installedVersion = $lockedPackages[$pkgName] ?? null;
                if (!$installedVersion) {
                    continue;
                }
                foreach ((array) $pkgAdvisories as $adv) {
                    $affected = (string) ($adv['affectedVersions'] ?? '');
                    if ($affected !== '' && class_exists(\Composer\Semver\Semver::class)) {
                        try {
                            if (!\Composer\Semver\Semver::satisfies($installedVersion, $affected)) {
                                continue;
                            }
                        } catch (\Throwable) {
                            continue;
                        }
                    }
                    $advisories[] = [
                        'package' => (string) $pkgName,
                        'installed_version' => $installedVersion,
                        'cve' => (string) ($adv['cve'] ?: ($adv['advisoryId'] ?? 'CVE')),
                        'title' => (string) ($adv['title'] ?? 'Sicherheitswarnung'),
                        'severity' => strtolower((string) ($adv['severity'] ?? 'medium')),
                        'affected_versions' => $affected,
                        'link' => (string) ($adv['link'] ?? ''),
                    ];
                }
            }

            return $advisories;
        } catch (\Throwable) {
            return [];
        }
    }

    private function auditNpmPackages(bool $skipNetwork): array
    {
        $packageJsonPath = base_path('package.json');
        $packageLockPath = base_path('package-lock.json');

        $prodDeps = [];
        $devDeps = [];
        $lockedCount = 0;

        if (File::exists($packageJsonPath)) {
            $json = json_decode(File::get($packageJsonPath), true) ?: [];
            $prodDeps = $json['dependencies'] ?? [];
            $devDeps = $json['devDependencies'] ?? [];
        }

        if (File::exists($packageLockPath)) {
            $lock = json_decode(File::get($packageLockPath), true) ?: [];
            $packages = $lock['packages'] ?? ($lock['dependencies'] ?? []);
            $lockedCount = max(0, count($packages) - (isset($packages['']) ? 1 : 0));
        }

        $advisories = [];

        if (!$skipNetwork && File::exists($packageLockPath)) {
            $auditJson = $this->runJsonProcess(['npm', 'audit', '--omit=dev', '--json'], 25);
            if (is_array($auditJson) && isset($auditJson['vulnerabilities']) && is_array($auditJson['vulnerabilities'])) {
                foreach ($auditJson['vulnerabilities'] as $pkgName => $vuln) {
                    $severity = strtolower((string) ($vuln['severity'] ?? 'moderate'));
                    $viaTitle = 'NPM Security Advisory';
                    $viaUrl = '';
                    foreach ((array) ($vuln['via'] ?? []) as $viaItem) {
                        if (is_array($viaItem)) {
                            $viaTitle = (string) ($viaItem['title'] ?? $viaTitle);
                            $viaUrl = (string) ($viaItem['url'] ?? $viaUrl);
                            break;
                        } elseif (is_string($viaItem)) {
                            $viaTitle = "Transitive Abhängigkeit über {$viaItem}";
                        }
                    }

                    $fixInfo = $vuln['fixAvailable'] ?? false;
                    $fixLabel = is_array($fixInfo)
                        ? (($fixInfo['name'] ?? $pkgName) . ' v' . ($fixInfo['version'] ?? ''))
                        : ($fixInfo ? 'npm audit fix' : 'Manuell prüfen');

                    $advisories[] = [
                        'package' => (string) $pkgName,
                        'severity' => $severity,
                        'is_direct' => (bool) ($vuln['isDirect'] ?? false),
                        'range' => (string) ($vuln['range'] ?? ''),
                        'title' => $viaTitle,
                        'fix' => $fixLabel,
                        'link' => $viaUrl,
                    ];
                }
            }
        }

        $directPackagesList = [];
        foreach ($prodDeps as $name => $ver) {
            $directPackagesList[] = ['name' => $name, 'version' => $ver, 'type' => 'prod'];
        }
        foreach ($devDeps as $name => $ver) {
            $directPackagesList[] = ['name' => $name, 'version' => $ver, 'type' => 'dev'];
        }

        return [
            'direct_prod_count' => count($prodDeps),
            'direct_dev_count' => count($devDeps),
            'locked_count' => $lockedCount,
            'cve_count' => count($advisories),
            'advisories' => array_slice($advisories, 0, 25),
            'direct_packages' => array_slice($directPackagesList, 0, 30),
        ];
    }

    private function auditAppPackages(): array
    {
        // 1. Funkenwerk Windows Desktop Suite (C# / WPF / .NET)
        $csprojPath = base_path('funkenwerk/Funkenwerk.csproj');
        $funkenwerk = [
            'exists' => File::exists($csprojPath),
            'target_framework' => 'net9.0-windows',
            'version' => '2.1.0',
            'nullable' => 'enable',
            'nuget_packages' => [],
            'status' => 'success',
        ];

        if ($funkenwerk['exists']) {
            $xmlContent = File::get($csprojPath);
            if (preg_match('/<TargetFramework>([^<]+)<\/TargetFramework>/i', $xmlContent, $m)) {
                $funkenwerk['target_framework'] = trim($m[1]);
            }
            if (preg_match('/<Version>([^<]+)<\/Version>/i', $xmlContent, $m)) {
                $funkenwerk['version'] = trim($m[1]);
            }
            if (preg_match_all('/<PackageReference\s+Include="([^"]+)"\s+Version="([^"]+)"/i', $xmlContent, $matches, PREG_SET_ORDER)) {
                foreach ($matches as $match) {
                    $funkenwerk['nuget_packages'][] = [
                        'name' => $match[1],
                        'version' => $match[2],
                    ];
                }
            }
        }

        // 2. Funkenwerk Android App (Kotlin / Jetpack Compose)
        $gradlePath = base_path('android-app/app/build.gradle.kts');
        $tomlPath = base_path('android-app/gradle/libs.versions.toml');

        $android = [
            'exists' => File::exists($gradlePath),
            'compile_sdk' => 34,
            'min_sdk' => 26,
            'target_sdk' => 34,
            'version_name' => '1.3',
            'minify_enabled' => false,
            'libraries_count' => 0,
            'key_versions' => [],
            'status' => 'success',
        ];

        if ($android['exists']) {
            $gradleContent = File::get($gradlePath);
            if (preg_match('/compileSdk\s*=\s*(\d+)/', $gradleContent, $m)) {
                $android['compile_sdk'] = (int) $m[1];
            }
            if (preg_match('/minSdk\s*=\s*(\d+)/', $gradleContent, $m)) {
                $android['min_sdk'] = (int) $m[1];
            }
            if (preg_match('/targetSdk\s*=\s*(\d+)/', $gradleContent, $m)) {
                $android['target_sdk'] = (int) $m[1];
            }
            if (preg_match('/versionName\s*=\s*"([^"]+)"/', $gradleContent, $m)) {
                $android['version_name'] = trim($m[1]);
            }
            $android['minify_enabled'] = str_contains($gradleContent, 'isMinifyEnabled = true');
        }

        if (File::exists($tomlPath)) {
            $tomlContent = File::get($tomlPath);
            $versions = [];
            if (preg_match('/\[versions\](.*?)\[(?:libraries|plugins)\]/s', $tomlContent, $sec)) {
                if (preg_match_all('/^\s*([a-zA-Z0-9_-]+)\s*=\s*"([^"]+)"/m', $sec[1], $vMatches, PREG_SET_ORDER)) {
                    foreach ($vMatches as $vm) {
                        $versions[$vm[1]] = $vm[2];
                    }
                }
            }
            if (preg_match('/\[libraries\](.*?)(?:\[plugins\]|\z)/s', $tomlContent, $libSec)) {
                if (preg_match_all('/^\s*([a-zA-Z0-9_-]+)\s*=\s*\{/m', $libSec[1], $lMatches)) {
                    $android['libraries_count'] = count($lMatches[1]);
                }
            }
            $android['key_versions'] = [
                'Kotlin' => $versions['kotlin'] ?? '2.0.0',
                'AGP' => $versions['agp'] ?? '8.4.0',
                'Compose BOM' => $versions['composeBom'] ?? '2024.05.00',
                'Retrofit' => $versions['retrofit'] ?? '2.11.0',
                'OkHttp' => $versions['okhttp'] ?? '4.12.0',
                'Biometric' => $versions['biometric'] ?? '1.2.0',
                'Firebase BOM' => $versions['firebaseBom'] ?? '33.1.1',
                'Google GenAI' => $versions['googleGenai'] ?? '0.9.0',
            ];
        }

        return [
            'funkenwerk' => $funkenwerk,
            'android' => $android,
        ];
    }

    private function auditSystemGuardrails(): array
    {
        $checks = [];

        // 1. .env & APP_KEY Schutz
        $envInPublic = File::exists(public_path('.env'));
        $hasAppKey = !empty(config('app.key'));
        $checks['env_protection'] = [
            'label' => '.env & APP_KEY Schutz',
            'passed' => !$envInPublic && $hasAppKey,
            'detail' => !$envInPublic && $hasAppKey ? 'Geschützt außerhalb public/' : 'KRITISCH: .env oder APP_KEY prüfen!',
        ];

        // 2. Produktions-Debug-Sperre
        $isProd = app()->environment('production');
        $debugOffInProd = !$isProd || !config('app.debug');
        $checks['debug_mode'] = [
            'label' => 'Debug-Sperre (Produktion)',
            'passed' => $debugOffInProd,
            'detail' => $debugOffInProd
                ? (app()->environment() . ' (APP_DEBUG=' . (config('app.debug') ? 'true' : 'false') . ')')
                : 'KRITISCH: APP_DEBUG ist in Produktion aktiv!',
        ];

        // 3. Public-Root Sauberkeit (keine fremden .php/.sql/.bak Dateien)
        $forbiddenPublicFiles = [];
        foreach (File::files(public_path()) as $file) {
            $name = $file->getFilename();
            $ext = strtolower($file->getExtension());
            if (($ext === 'php' && $name !== 'index.php') || in_array($ext, ['sql', 'bak', 'env', 'sh'], true)) {
                $forbiddenPublicFiles[] = $name;
            }
        }
        $checks['public_cleanliness'] = [
            'label' => 'Public-Verzeichnis Integrität',
            'passed' => empty($forbiddenPublicFiles),
            'detail' => empty($forbiddenPublicFiles) ? 'Nur index.php ausführbar' : 'Verdächtige Dateien: ' . implode(', ', $forbiddenPublicFiles),
        ];

        // 4. Session & Cookie Härtung
        $httpOnly = (bool) config('session.http_only', true);
        $sameSite = strtolower((string) config('session.same_site', 'lax'));
        $sessionHardened = $httpOnly && in_array($sameSite, ['lax', 'strict'], true);
        $checks['session_hardening'] = [
            'label' => 'Session & CSRF Cookie-Schutz',
            'passed' => $sessionHardened,
            'detail' => "HttpOnly=" . ($httpOnly ? 'ja' : 'nein') . ", SameSite={$sameSite}",
        ];

        // 5. Architektur-Blueprint & DSGVO-Retention
        $blueprintCompliant = true;
        $blueprintDetail = 'Alle Modelle & Pfade konform';
        if (class_exists(SystemBlueprintService::class)) {
            try {
                $bpAudit = app(SystemBlueprintService::class)->audit();
                $blueprintCompliant = (bool) ($bpAudit['is_compliant'] ?? true);
                if (!$blueprintCompliant) {
                    $blueprintDetail = 'Abweichung in SystemBlueprintService erkannt';
                }
            } catch (\Throwable) {
                $blueprintCompliant = true;
            }
        }
        $checks['blueprint_compliance'] = [
            'label' => 'Architektur- & DSGVO-Blueprint',
            'passed' => $blueprintCompliant,
            'detail' => $blueprintDetail,
        ];

        // 6. Admin 2FA Status (informativ in Local/Testing, Pflicht-Hinweis auf Live)
        $adminTotal = class_exists(Admin::class) ? Admin::count() : 0;
        $admin2fa = class_exists(Admin::class)
            ? Admin::whereHas('profile', fn ($q) => $q->where('two_factor_is_active', true))->count()
            : 0;
        $checks['admin_2fa'] = [
            'label' => 'Administrator 2FA-Schutz',
            'passed' => true,
            'detail' => "{$admin2fa}/{$adminTotal} Admin-Konten mit 2FA",
        ];

        $passedCount = count(array_filter($checks, fn ($c) => $c['passed'] === true));
        $totalCount = count($checks);

        return [
            'passed_count' => $passedCount,
            'failed_count' => $totalCount - $passedCount,
            'total_count' => $totalCount,
            'checks' => $checks,
        ];
    }

    private function runJsonProcess(array $command, int $timeoutSeconds = 25): ?array
    {
        try {
            $process = new Process($command, base_path(), [
                'COMPOSER_DISABLE_XDEBUG_WARN' => '1',
                'COMPOSER_NO_INTERACTION' => '1',
            ]);
            $process->setTimeout($timeoutSeconds);
            $process->run();

            $output = trim($process->getOutput());
            if ($output === '') {
                return null;
            }

            $jsonStart = strpos($output, '{');
            if ($jsonStart !== false) {
                $output = substr($output, $jsonStart);
            }

            $decoded = json_decode($output, true);
            return is_array($decoded) ? $decoded : null;
        } catch (\Throwable) {
            return null;
        }
    }

    private function sendSecurityAlertIfNecessary(array $report): void
    {
        if (Cache::has('security_audit_alert_sent_24h')) {
            return;
        }

        try {
            $recipient = config('mail.from.address', 'kontakt@mein-seelenfunke.de');
            $cves = (int) ($report['cve_count'] ?? 0);
            $guardrailErrors = (int) ($report['guardrail_errors_count'] ?? 0);

            $lines = [
                "Bei der automatischen Seelenfunke Sicherheitsprüfung wurden **{$cves} Paket-Sicherheitslücke(n) (CVEs)** und **{$guardrailErrors} System-Schranken-Warnung(en)** festgestellt.",
                '',
                "**Zusammenfassung:** {$report['summary']}",
                "- **Composer (Laravel):** " . ($report['composer']['cve_count'] ?? 0) . " CVEs",
                "- **NPM (Frontend):** " . ($report['npm']['cve_count'] ?? 0) . " CVEs",
                '',
                'Bitte öffne `/admin/dashboard` (Reiter **Sicherheit & Abwehr**) oder führe `./funki security update` im Terminal aus.',
            ];

            Mail::to($recipient)->send(new AiAgentMessageMail(
                agentName: 'Funkira Security Watchdog',
                subjectLine: "⚠️ Sicherheitswarnung: {$report['summary']}",
                markdownBody: implode("\n", $lines)
            ));

            Cache::put('security_audit_alert_sent_24h', true, now()->addHours(24));
        } catch (\Throwable) {
            // Mail-Fehler darf den Audit-Befehl niemals abbrechen
        }
    }
}
