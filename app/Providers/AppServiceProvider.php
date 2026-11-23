<?php

namespace App\Providers;

use App\Models\System\SystemSetting;
use Carbon\Carbon;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Verhindert "touch(): Utime failed: Operation not permitted" und tempnam()-Rechtekonflikte in BladeCompiler::compile(),
        // falls kompilierte Views durch CLI/Cron/Tests unter einer anderen UID (root/1000 statt www-data) angelegt wurden.
        $this->app->singleton('blade.compiler', function ($app) {
            $compiledDir = (string) $app['config']['view.compiled'];
            if ($compiledDir !== '' && ! is_dir($compiledDir)) {
                @mkdir($compiledDir, 0777, true);
                @chmod($compiledDir, 0777);
            }
            if ($compiledDir === '' || ! is_writable($compiledDir)) {
                $uidSuffix = function_exists('posix_geteuid') ? (string) posix_geteuid() : 'default';
                $compiledDir = rtrim(sys_get_temp_dir(), '/\\') . '/sf_views_' . $uidSuffix;
                if (! is_dir($compiledDir)) {
                    @mkdir($compiledDir, 0777, true);
                    @chmod($compiledDir, 0777);
                }
                $app['config']->set('view.compiled', $compiledDir);
            }

            return tap(new class(
                $app['files'],
                $compiledDir,
                $app['config']->get('view.relative_hash', false) ? $app->basePath() : '',
                $app['config']->get('view.cache', true),
                $app['config']->get('view.compiled_extension', 'php'),
                $app['config']->get('view.check_cache_timestamps', true),
            ) extends \Illuminate\View\Compilers\BladeCompiler {
                protected function writeCompiledFileAtomically(string $compiledPath, string $contents): void
                {
                    $dir = dirname($compiledPath);
                    if (! is_dir($dir)) {
                        @mkdir($dir, 0777, true);
                        @chmod($dir, 0777);
                    }
                    if (@file_put_contents($compiledPath, $contents, LOCK_EX) === false) {
                        @unlink($compiledPath);
                        @file_put_contents($compiledPath, $contents);
                    }
                    @chmod($compiledPath, 0666);
                }

                public function compile($path = null)
                {
                    if ($path) {
                        $this->setPath($path);
                    }

                    if (! is_null($this->cachePath)) {
                        $contents = $this->compileString($this->files->get($this->getPath()));

                        if (! empty($this->getPath())) {
                            $contents = $this->appendFilePath($contents);
                        }

                        $compiledPath = $this->getCompiledPath($this->getPath());

                        if (! $this->files->exists($compiledPath)) {
                            $this->writeCompiledFileAtomically($compiledPath, $contents);

                            return;
                        }

                        $compiledHash = $this->files->hash($compiledPath, 'xxh128');

                        if ($compiledHash !== hash('xxh128', $contents)) {
                            $this->writeCompiledFileAtomically($compiledPath, $contents);

                            return;
                        }

                        $lastModified = $this->files->lastModified($this->getPath());

                        if ($lastModified >= $this->files->lastModified($compiledPath)) {
                            if (! @touch($compiledPath, $lastModified + 1)) {
                                $this->writeCompiledFileAtomically($compiledPath, $contents);
                            }
                        }
                    }
                }
            }, function ($blade) {
                $blade->component('dynamic-component', \Illuminate\View\DynamicComponent::class);
            });
        });

        if (class_exists(\Laravel\Reverb\Servers\Reverb\Console\Commands\StartServer::class)) {
            $this->app->bind(
                \Laravel\Reverb\Servers\Reverb\Console\Commands\StartServer::class,
                \App\Console\Commands\ReverbWithTwilioServerCommand::class
            );
        }
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Schema::defaultStringLength(191);

        if (class_exists(\Laravel\Sanctum\Sanctum::class)) {
            \Laravel\Sanctum\Sanctum::usePersonalAccessTokenModel(\App\Models\System\PersonalAccessToken::class);
        }

        \Illuminate\Support\Facades\RateLimiter::for('ota-check', function (\Illuminate\Http\Request $request) {
            return \Illuminate\Cache\RateLimiting\Limit::perMinute(60)
                ->by('ota-check:' . ($request->user()?->getAuthIdentifier() ?: $request->ip()));
        });
        \Illuminate\Support\Facades\RateLimiter::for('ota-download', function (\Illuminate\Http\Request $request) {
            return \Illuminate\Cache\RateLimiting\Limit::perMinute(20)
                ->by('ota-download:' . ($request->user()?->getAuthIdentifier() ?: $request->ip()));
        });
        \Illuminate\Support\Facades\RateLimiter::for('ota-publish', function (\Illuminate\Http\Request $request) {
            return \Illuminate\Cache\RateLimiting\Limit::perMinute(10)
                ->by('ota-publish:' . ($request->user()?->getAuthIdentifier() ?: $request->ip()));
        });

        if (class_exists(\Livewire\Livewire::class)) {
            \Livewire\Livewire::addPersistentMiddleware([
                \App\Http\Middleware\EnforcePasswordChange::class,
                \App\Http\Middleware\EnsureEmployeeRole::class,
            ]);
            \Livewire\Livewire::componentHook(\App\Livewire\Hooks\EmployeeRoleIsolationHook::class);

            // Schutz gegen Livewire-JS Root-Diff Key-Order-Mismatch (updates[""] -> PublicPropertyNotFoundException [$])
            \Livewire\on('request', function ($payload) {
                return function ($requestPayload) {
                    if (!is_array($requestPayload)) {
                        return $requestPayload;
                    }

                    $unwrapLivewireArray = function ($node) use (&$unwrapLivewireArray) {
                        if (
                            is_array($node)
                            && count($node) === 2
                            && array_key_exists(0, $node)
                            && array_key_exists(1, $node)
                            && is_array($node[1])
                            && isset($node[1]['s'])
                        ) {
                            if ($node[1]['s'] === 'arr' && is_array($node[0])) {
                                $out = [];
                                foreach ($node[0] as $k => $v) {
                                    $out[$k] = $unwrapLivewireArray($v);
                                }
                                return $out;
                            }
                            return '__LIVEWIRE_SYNTH__';
                        }
                        return $node;
                    };

                    foreach ($requestPayload as $idx => $componentPayload) {
                        if (!isset($componentPayload['updates']) || !is_array($componentPayload['updates'])) {
                            continue;
                        }

                        if (array_key_exists('', $componentPayload['updates'])) {
                            $rootDump = $componentPayload['updates'][''];
                            unset($requestPayload[$idx]['updates']['']);

                            if (is_array($rootDump) && !empty($componentPayload['snapshot'])) {
                                $snapshot = json_decode((string) $componentPayload['snapshot'], true);
                                $snapshotData = is_array($snapshot['data'] ?? null) ? $snapshot['data'] : [];

                                foreach ($rootDump as $propKey => $propVal) {
                                    if (!is_string($propKey) || $propKey === '' || !array_key_exists($propKey, $snapshotData)) {
                                        continue;
                                    }
                                    if (array_key_exists($propKey, $requestPayload[$idx]['updates'])) {
                                        continue;
                                    }

                                    $unwrapped = $unwrapLivewireArray($snapshotData[$propKey]);
                                    if ($unwrapped === '__LIVEWIRE_SYNTH__') {
                                        continue;
                                    }

                                    if ($unwrapped !== $propVal) {
                                        $hasNestedUpdate = false;
                                        foreach (array_keys($requestPayload[$idx]['updates']) as $existingPath) {
                                            if (str_starts_with((string) $existingPath, $propKey . '.')) {
                                                $hasNestedUpdate = true;
                                                break;
                                            }
                                        }
                                        if (!$hasNestedUpdate) {
                                            $requestPayload[$idx]['updates'][$propKey] = $propVal;
                                        }
                                    }
                                }
                            }
                        }

                        foreach (array_keys($requestPayload[$idx]['updates']) as $updateKey) {
                            $updateKeyStr = (string) $updateKey;
                            if ($updateKeyStr === '' || str_starts_with($updateKeyStr, '.')) {
                                unset($requestPayload[$idx]['updates'][$updateKey]);
                            }
                        }
                    }

                    return $requestPayload;
                };
            });
        }
        // 1. Füge die Morph Map hinzu
        Relation::enforceMorphMap([
            'admin'    => 'App\Models\Admin\Admin',
            'customer' => 'App\Models\Customer\Customer',
            'employee' => 'App\Models\Employee\Employee',
            'directory' => 'App\Models\System\SystemDirectory',
            'financial_special_issue' => 'App\Models\Accounting\AccountingSpecialIssue',
            'order' => 'App\Models\Order\OrderOrder',
            'order_item' => 'App\Models\Order\OrderOrderItem',
            'day_routine' => 'App\Models\Management\ManagementDayRoutine',
            'finance_cost_item' => 'App\Models\Accounting\AccountingCostItem',
            'product' => 'App\Models\Product\Product',
            'page_visit' => 'App\Models\Tracking\PageVisit',
            'ticket' => 'App\Models\Support\SupportTicket',
            'mail_account' => 'App\Models\Management\Mail\MailAccount',
            'mail_message' => 'App\Models\Management\Mail\MailMessage',
            'mail_rule' => 'App\Models\Management\Mail\MailRule',
            'mail_folder' => 'App\Models\Management\Mail\MailFolder',
            'ai_agent' => 'App\Models\Ai\AiAgent',
            'ai_role' => 'App\Models\Ai\AiRole',
            'ai_tool' => 'App\Models\Ai\AiTool',
            'supplier' => 'App\Models\Product\ProductSupplier',
            'product_supplier' => 'App\Models\Product\ProductSupplier',
            'product_idea' => 'App\Models\Product\ProductIdea',
            'product_packaging_item' => 'App\Models\Product\ProductPackagingItem',
            'product_template' => 'App\Models\Product\ProductTemplate',
            'ai-department' => 'App\Models\Ai\AiDepartment',

            'management_mission' => 'App\Models\Management\ManagementMission',

            'marketing_gift_voucher' => 'App\Models\Marketing\MarketingGiftVoucher',
            'marketing_voucher' => 'App\Models\Marketing\MarketingVoucher',
            'marketing_referral' => 'App\Models\Marketing\MarketingReferral',
            'marketing_gift_voucher_log' => 'App\Models\Marketing\MarketingGiftVoucherLog',
            'marketing_linktree' => 'App\Models\Marketing\MarketingLinktree',
            'marketing_linktree_click' => 'App\Models\Marketing\MarketingLinktreeClick',
            'marketing_linktree_visit' => 'App\Models\Marketing\MarketingLinktreeVisit',
            'marketing_tracking_link' => 'App\Models\Marketing\MarketingTrackingLink',
            'marketing_tracking_link_hit' => 'App\Models\Marketing\MarketingTrackingLinkHit',

            // Füge hier bei Bedarf weitere Models hinzu
        ]);

        // 2. Migrationen aus Unterordnern laden
        $migrationsPath = database_path('migrations');
        $directories    = glob($migrationsPath.'/*', GLOB_ONLYDIR);
        $paths          = array_merge([$migrationsPath], $directories);

        $this->loadMigrationsFrom($paths);

        // 2b. Redis-Ausfallsicherung mit lokalem /tmp Circuit-Breaker (DNS- / Verbindungs-Fallback für Stage & Produktion)
        // Verhindert fatale Predis StreamInitException (getaddrinfo failed), falls der Redis-Container
        // temporär nicht auflösbar oder nicht erreichbar ist (inkl. schedule:run / schedule:finish Mutex!), und vermeidet wiederholte DNS-/Disk-Latenz pro Request.
        if (
            Config::get('cache.default') === 'redis'
            || Config::get('session.driver') === 'redis'
            || Config::get('schedule.cache_store') === 'redis'
            || Config::get('queue.default') === 'redis'
        ) {
            $redisHost = Config::get('database.redis.default.host', '127.0.0.1');
            $cbFile = !app()->runningUnitTests()
                ? (rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR) . '/sf_redis_cb_' . md5((string) $redisHost . '|' . (string) Config::get('database.redis.default.port', 6379)) . '.state')
                : null;

            $applyRedisFileFallback = static function (): void {
                if (Config::get('cache.default') === 'redis') {
                    Config::set('cache.default', 'file');
                }
                if (Config::get('cache.limiter') === 'redis') {
                    Config::set('cache.limiter', 'file');
                }
                if (Config::get('schedule.cache_store') === 'redis') {
                    Config::set('schedule.cache_store', 'file');
                }
                if (Config::get('session.driver') === 'redis') {
                    Config::set('session.driver', 'file');
                }
                if (Config::get('queue.default') === 'redis') {
                    Config::set('queue.default', 'database');
                }
                if (Config::get('broadcasting.default') === 'redis') {
                    Config::set('broadcasting.default', 'null');
                }
                try {
                    if (app()->bound('cache')) {
                        app('cache')->forgetDriver('redis');
                    }
                    app()->forgetInstance('cache.store');
                    app()->forgetInstance(\Illuminate\Cache\RateLimiter::class);
                } catch (\Throwable) {
                }
            };

            $skipRedisNetworkCheck = false;
            if ($cbFile !== null && is_file($cbFile)) {
                $cbAge = time() - (int) @filemtime($cbFile);
                if ($cbAge >= 0 && $cbAge < 45) {
                    $cbState = trim((string) @file_get_contents($cbFile));
                    if ($cbState === '0') {
                        $applyRedisFileFallback();
                        $skipRedisNetworkCheck = true;
                    } elseif ($cbState === '1' && $cbAge < 15) {
                        if (is_string($redisHost) && $redisHost !== '' && !filter_var($redisHost, FILTER_VALIDATE_IP) && $redisHost !== 'localhost') {
                            $quickDns = @gethostbyname($redisHost);
                            if ($quickDns === $redisHost) {
                                $skipRedisNetworkCheck = false;
                            } else {
                                Config::set('database.redis.default.host', $quickDns);
                                Config::set('database.redis.cache.host', $quickDns);
                                $skipRedisNetworkCheck = true;
                            }
                        } else {
                            $skipRedisNetworkCheck = true;
                        }
                    }
                }
            }

            if (!$skipRedisNetworkCheck) {
                try {
                    if (is_string($redisHost) && $redisHost !== '' && !filter_var($redisHost, FILTER_VALIDATE_IP) && $redisHost !== 'localhost') {
                        $resolved = @gethostbyname($redisHost);
                        if ($resolved === $redisHost) {
                            throw new \RuntimeException("DNS resolution failed for Redis host: {$redisHost}");
                        }
                        Config::set('database.redis.default.host', $resolved);
                        Config::set('database.redis.cache.host', $resolved);
                    }
                    Cache::store('redis')->get('__redis_health_check__');
                    if ($cbFile !== null) {
                        @file_put_contents($cbFile, '1');
                        @chmod($cbFile, 0666);
                    }
                } catch (\Throwable $redisEx) {
                    if ($cbFile !== null) {
                        @file_put_contents($cbFile, '0');
                        @chmod($cbFile, 0666);
                    }
                    $applyRedisFileFallback();
                    if (Config::get('queue.default') === 'database') {
                        try {
                            Config::set('queue.default', Schema::hasTable('jobs') ? 'database' : 'sync');
                        } catch (\Throwable) {
                            Config::set('queue.default', 'database');
                        }
                    }

                    try {
                        $throttleKey = 'redis_fallback_logged_' . date('Ymd_H');
                        if (!Cache::store('file')->has($throttleKey)) {
                            Cache::store('file')->put($throttleKey, true, 3600);
                            \Illuminate\Support\Facades\Log::warning('Redis nicht erreichbar (' . $redisEx->getMessage() . '): Automatischer Fallback auf File-Cache/Session aktiviert.');

                            if (!app()->runningInConsole() && class_exists(\App\Models\System\SystemLog::class) && Schema::hasTable('system_logs')) {
                                    \App\Models\System\SystemLog::create([
                                        'type' => 'system',
                                        'action_id' => 'system:redis_fallback',
                                        'title' => 'Redis-Fallback aktiviert (File-Cache)',
                                        'message' => 'Redis-Host nicht erreichbar (' . substr($redisEx->getMessage(), 0, 250) . '). System nutzt automatisch File-Cache & File-Session.',
                                        'status' => 'info',
                                        'payload' => [
                                            'redis_host' => $redisHost,
                                            'error' => $redisEx->getMessage(),
                                            'active_cache_driver' => Config::get('cache.default'),
                                            'active_session_driver' => Config::get('session.driver'),
                                            'active_queue_driver' => Config::get('queue.default'),
                                        ],
                                        'started_at' => now(),
                                        'finished_at' => now(),
                                    ]);
                            }
                        }
                    } catch (\Throwable $ignore) {
                    }
                }
            }
        }

        // 3. Shop-Settings & Stripe Config laden
        // WICHTIG: Verhindert, dass Konsolen-Befehle (wie artisan) versuchen die DB abzufragen,
        // bevor Treiber/Datenbank überhaupt existieren!
        if (!app()->runningInConsole()) {
            try {
                // Prüfen, ob die Tabelle existiert, sonst crashen Migrations beim Deployment
                if (Schema::hasTable('shop_settings') || Schema::hasTable('shop-settings')) {

                    // Settings aus dem Cache laden (oder aus DB holen und cachen)
                    $settings = Cache::remember('global_shop_settings', 60, function () {
                        return SystemSetting::pluck('value', 'key');
                    });

                    // Stripe Konfiguration zur Laufzeit überschreiben
                    if (!empty($settings['stripe_publishable_key'])) {
                        Config::set('services.stripe.key', $settings['stripe_publishable_key']);
                    }

                    if (!empty($settings['stripe_secret_key'])) {
                        Config::set('services.stripe.secret', $settings['stripe_secret_key']);
                    }

                    if (!empty($settings['stripe_webhook_secret'])) {
                        // Beide Keys unterstützen (verschachtelt und flach)
                        Config::set('services.stripe.webhook.secret', $settings['stripe_webhook_secret']);
                        Config::set('services.stripe.webhook_secret', $settings['stripe_webhook_secret']);
                    }

                    $backupMail = $settings['owner_email'] ?? $settings['company_email'] ?? null;
                    if (!empty($backupMail)) {
                        Config::set('backup.notifications.mail.to', $backupMail);
                    }
                }
            } catch (\Throwable $e) {
                // Falls die Datenbank noch gar nicht konfiguriert ist, failen wir hier leise,
                // damit die Anwendung / Konsole weiterhin erreichbar bleibt.
            }
        }

        // 4. Überschreibt die Standard-Verifizierungsmail von Laravel
        VerifyEmail::toMailUsing(function (object $notifiable, string $url) {
            return (new MailMessage)
                ->subject('Willkommen! Bitte bestätige deine E-Mail-Adresse ✨')
                // Hier verweist du auf deine neue Blade-Datei:
                ->view('global.mails.auth.new_register_mail_to_customer', [
                    'url' => $url,
                    'name' => $notifiable->first_name
                ]);
        });

        // 5. Zwingt Laravel, eine eigene Route für Kunden zu nutzen!
        VerifyEmail::createUrlUsing(function ($notifiable) {
            return URL::temporarySignedRoute(
                'customer.verification.verify', // Unser eigener, neuer Routen-Name
                Carbon::now()->addMinutes(Config::get('auth.verification.expire', 60)),
                [
                    'id' => $notifiable->getKey(),
                    'hash' => sha1($notifiable->getEmailForVerification()),
                ]
            );
        });

        // 6. Zuverlässiger Queue Worker Heartbeat (schreibt alle paar Sekunden in den Cache)
        $heartbeatCallback = function () {
            try {
                // Heartbeat TTL in Sekunden hochsetzen (30s) weil AI Jobs ggf. länger dauern
                \Illuminate\Support\Facades\Cache::put('ai-worker-heartbeat', now()->timestamp, 45);

                // Fallback für geclusterte Systeme (NFS Storage synchronisiert sich über alle Knoten)
                \Illuminate\Support\Facades\Storage::disk('local')->put('system/ai_worker_heartbeat.txt', now()->timestamp);
            } catch (\Exception $e) {
                // Berechtigungsfehler bei file-cache ignorieren
            }
        };

        \Illuminate\Support\Facades\Queue::looping($heartbeatCallback);
        \Illuminate\Support\Facades\Queue::before($heartbeatCallback);
        \Illuminate\Support\Facades\Queue::after($heartbeatCallback);

        // 7. Lückenlose Fehler-Erfassung ("jede Mücke") für /admin/global-logs (SystemLog):
        //    a) Harter PHP-Shutdown- & Out-of-Memory-Wächter (register_shutdown_function + lokaler Dead-Letter-Spool)
        //    b) Alle abgefangenen Log::warning / Log::error / Log::critical / Log::alert / Log::emergency Aufrufe
        //    c) Fehlgeschlagene Queue-Jobs (JobFailed)
        //    d) Fehlgeschlagene Cron-/Scheduler-Tasks (ScheduledTaskFailed)
        //    e) Alle mit Fehler-Exit-Code beendeten Artisan-/Cron-Befehle (CommandFinished)
        if (!app()->runningUnitTests() && class_exists(\App\Services\System\SystemErrorLogger::class)) {
            \App\Services\System\SystemErrorLogger::registerFatalShutdownHandler();
        }
        \Illuminate\Support\Facades\Event::listen(\Illuminate\Log\Events\MessageLogged::class, function (\Illuminate\Log\Events\MessageLogged $event) {
            $lvl = strtolower((string) $event->level);
            if (!in_array($lvl, ['warning', 'error', 'critical', 'alert', 'emergency'], true)) {
                return;
            }
            $msg = (string) $event->message;
            if ($lvl === 'warning' && (str_contains($msg, 'Deprecated') || str_contains($msg, 'Return type of'))) {
                return;
            }
            if (class_exists(\App\Services\System\SystemErrorLogger::class)) {
                \App\Services\System\SystemErrorLogger::recordLogEvent(
                    $event->level,
                    $msg,
                    is_array($event->context) ? $event->context : []
                );
            }
        });

        \Illuminate\Support\Facades\Queue::failing(function (\Illuminate\Queue\Events\JobFailed $event) {
            if (class_exists(\App\Services\System\SystemErrorLogger::class)) {
                $jobName = method_exists($event->job, 'resolveName') ? $event->job->resolveName() : get_class($event->job);
                \App\Services\System\SystemErrorLogger::recordException(
                    $event->exception,
                    'queue',
                    'queue:job_failed:' . class_basename($jobName),
                    'Queue-Job Fehler [' . class_basename($jobName) . ']',
                    [
                        'job_name' => $jobName,
                        'connection_name' => $event->connectionName,
                        'queue' => $event->job->getQueue(),
                        'attempts' => method_exists($event->job, 'attempts') ? $event->job->attempts() : 1,
                    ]
                );
            }
        });

        \Illuminate\Support\Facades\Event::listen(\Illuminate\Console\Events\ScheduledTaskFailed::class, function (\Illuminate\Console\Events\ScheduledTaskFailed $event) {
            if (class_exists(\App\Services\System\SystemErrorLogger::class)) {
                $cmd = $event->task->command ?? $event->task->description ?? 'scheduled_task';
                \App\Services\System\SystemErrorLogger::recordException(
                    $event->exception,
                    'scheduler',
                    'scheduler:task_failed',
                    'Cron/Scheduler Fehler',
                    [
                        'task_command' => $cmd,
                        'cron_expression' => $event->task->expression ?? null,
                    ]
                );
            }
        });

        \Illuminate\Support\Facades\Event::listen(\Illuminate\Console\Events\CommandFinished::class, function (\Illuminate\Console\Events\CommandFinished $event) {
            try {
                if (app()->runningUnitTests() || app()->environment('testing')) {
                    return;
                }

                $cmdName = trim((string) $event->command);
                $ignoredInteractiveCommands = [
                    '', 'schedule:run', 'schedule:finish', 'schedule:list', 'schedule:work',
                    'list', 'help', 'tinker', 'test', 'dusk', 'inspire', 'serve', 'pail',
                    'about', 'env', 'db', 'db:seed', 'db:show', 'db:table', 'db:monitor',
                    'migrate', 'migrate:status', 'migrate:fresh', 'migrate:refresh', 'migrate:rollback', 'migrate:reset',
                    'vendor:publish', 'package:discover', 'clear-compiled', 'storage:link',
                    'optimize', 'optimize:clear', 'config:cache', 'config:clear', 'cache:clear',
                    'route:cache', 'route:clear', 'route:list', 'view:cache', 'view:clear',
                    'event:cache', 'event:clear', 'ai:generate-map', 'system:check-routes',
                    'system:sentinel-inbox', 'system:ag-inbox', 'system:sentinel-triage',
                ];
                if (in_array($cmdName, $ignoredInteractiveCommands, true) || str_starts_with($cmdName, 'make:') || str_starts_with($cmdName, 'ide-helper:')) {
                    return;
                }

                $cronjob = null;
                if (class_exists(\App\Models\System\SystemCronjob::class) && Schema::hasTable('system_cronjobs')) {
                    $cronjob = \App\Models\System\SystemCronjob::where('command', $cmdName)
                        ->orWhere('command', 'like', $cmdName . ' %')
                        ->first();
                }

                $isOperationalCronCommand = $cronjob !== null || (bool) preg_match('/^(crm:|shop:|dhl:|funki:|backup:|gamification:|support:|system:|tickets:|send-newsletters$|generate-tax-export$)/', $cmdName);

                $detailOutput = class_exists(\App\Services\System\SystemErrorLogger::class)
                    ? \App\Services\System\SystemErrorLogger::getCommandFailureDetail($cmdName)
                    : '';
                if ($detailOutput === '' && $event->exitCode !== 0 && $cronjob) {
                    $cronOutputFile = storage_path('logs/cron-' . md5((string) $cronjob->command) . '.log');
                    if (is_file($cronOutputFile) && is_readable($cronOutputFile)) {
                        $detailOutput = trim(mb_substr((string) @file_get_contents($cronOutputFile), 0, 2000));
                    }
                }

                if ($cronjob && $cmdName !== 'system:heartbeat' && class_exists(\App\Models\System\SystemSchedulerLog::class) && Schema::hasTable('system_scheduler_logs')) {
                    \App\Models\System\SystemSchedulerLog::create([
                        'task_id' => (string) $cronjob->id,
                        'task_name' => (string) ($cronjob->name ?: $cmdName),
                        'started_at' => now(),
                        'finished_at' => now(),
                        'status' => $event->exitCode === 0 ? 'success' : 'error',
                        'output' => $event->exitCode === 0
                            ? 'OK'
                            : ('Exit-Code: ' . (int) $event->exitCode . ($detailOutput !== '' ? ' | ' . $detailOutput : '')),
                    ]);
                }

                if ($event->exitCode !== 0) {
                    if ($cronjob) {
                        $cronjob->update([
                            'status' => 'error',
                            'last_run_at' => now(),
                        ]);
                    }

                    if ($isOperationalCronCommand && class_exists(\App\Services\System\SystemErrorLogger::class)) {
                        \App\Services\System\SystemErrorLogger::recordCommandFailure(
                            $cmdName,
                            (int) $event->exitCode,
                            $detailOutput,
                            [
                                'cronjob_id' => $cronjob?->id,
                                'cronjob_name' => $cronjob?->name ?? $cmdName,
                                'schedule' => $cronjob?->schedule,
                                'trigger' => 'console_command_finished',
                            ]
                        );
                    }

                    if ($isOperationalCronCommand && class_exists(\App\Services\System\SystemAlertTicketService::class)) {
                        $jobTitle = $cronjob?->name ?? $cmdName;
                        \App\Services\System\SystemAlertTicketService::upsertSystemTicket(
                            alertKey: 'cronjob_failed:' . $cmdName,
                            title: "Cronjob fehlgeschlagen: {$jobTitle}",
                            markdownBody: "Der Hintergrund-Job `{$cmdName}` wurde mit Fehler-Code `" . (int) $event->exitCode . "` beendet.\n\n"
                                . ($detailOutput !== '' ? "**Ausgabe-Auszug:**\n```\n" . mb_substr($detailOutput, 0, 1200) . "\n```" : 'Keine Konsolenausgabe vorhanden.'),
                            departmentName: 'System',
                            agentName: 'Systemi',
                            ticketType: 'cronjob_failure',
                            priority: 'high',
                            assignToAntigravity: true,
                            extraPayload: [
                                'command' => $cmdName,
                                'exit_code' => (int) $event->exitCode,
                                'cronjob_id' => $cronjob?->id,
                            ]
                        );
                    }
                } elseif ($cronjob) {
                    $cronjob->update([
                        'status' => 'success',
                        'last_run_at' => now(),
                    ]);
                    if (class_exists(\App\Services\System\SystemAlertTicketService::class)) {
                        \App\Services\System\SystemAlertTicketService::autoResolveByAlertPrefix(
                            'cronjob_failed:' . $cmdName,
                            "Nachfolgender Lauf von `{$cmdName}` ({$cronjob->name}) wurde mit Exit-Code 0 erfolgreich abgeschlossen."
                        );
                    }
                }
            } catch (\Throwable $ignore) {
            }
        });

        // Spatie Backup Events zentral in SystemLog & Universum-Tickets statt E-Mail-Spam routen
        if (class_exists(\Spatie\Backup\Events\BackupHasFailed::class)) {
            \Illuminate\Support\Facades\Event::listen(\Spatie\Backup\Events\BackupHasFailed::class, function ($event) {
                $errMsg = isset($event->exception) && $event->exception instanceof \Throwable
                    ? $event->exception->getMessage()
                    : 'Unbekannter Datenbank-/Speicher-Backup-Fehler';
                if (class_exists(\App\Models\System\SystemLog::class) && Schema::hasTable('system_logs')) {
                    \App\Models\System\SystemLog::create([
                        'type' => 'system',
                        'action_id' => 'backup:failed',
                        'title' => 'Datenbank-Backup fehlgeschlagen',
                        'message' => mb_substr($errMsg, 0, 1000),
                        'status' => 'error',
                        'started_at' => now(),
                        'finished_at' => now(),
                    ]);
                }
                \App\Services\System\SystemAlertTicketService::upsertSystemTicket(
                    alertKey: 'spatie_backup:backup_failed',
                    title: 'Datenbank-Backup fehlgeschlagen',
                    markdownBody: "Das automatische Datenbank-Backup (`backup:run`) ist fehlgeschlagen.\n\n**Fehler-Details:**\n" . $errMsg,
                    departmentName: 'System',
                    agentName: 'Systemi',
                    ticketType: 'backup_alert',
                    priority: 'high',
                    assignToAntigravity: true
                );
            });
        }

        if (class_exists(\Spatie\Backup\Events\CleanupHasFailed::class)) {
            \Illuminate\Support\Facades\Event::listen(\Spatie\Backup\Events\CleanupHasFailed::class, function ($event) {
                $errMsg = isset($event->exception) && $event->exception instanceof \Throwable
                    ? $event->exception->getMessage()
                    : 'Backup-Bereinigung fehlgeschlagen';
                \App\Services\System\SystemAlertTicketService::upsertSystemTicket(
                    alertKey: 'spatie_backup:cleanup_failed',
                    title: 'Backup-Bereinigung fehlgeschlagen',
                    markdownBody: "Die automatische Bereinigung alter Backups (`backup:clean`) meldete einen Fehler:\n\n" . $errMsg,
                    departmentName: 'System',
                    agentName: 'Systemi',
                    ticketType: 'backup_alert',
                    priority: 'normal'
                );
            });
        }

        if (class_exists(\Spatie\Backup\Events\UnhealthyBackupWasFound::class)) {
            \Illuminate\Support\Facades\Event::listen(\Spatie\Backup\Events\UnhealthyBackupWasFound::class, function ($event) {
                \App\Services\System\SystemAlertTicketService::upsertSystemTicket(
                    alertKey: 'spatie_backup:unhealthy_backup',
                    title: 'Backup-Gesundheitsprüfung: Warnung erkannt',
                    markdownBody: 'Die Spatie-Backup-Überwachung hat ein veraltetes oder zu großes Backup-Ziel gemeldet. Bitte `/admin/system-backups` prüfen.',
                    departmentName: 'System',
                    agentName: 'Systemi',
                    ticketType: 'backup_alert',
                    priority: 'high'
                );
            });
        }

        if (class_exists(\Spatie\Backup\Events\BackupWasSuccessful::class)) {
            \Illuminate\Support\Facades\Event::listen(\Spatie\Backup\Events\BackupWasSuccessful::class, function () {
                \App\Services\System\SystemAlertTicketService::autoResolveByAlertPrefix(
                    'spatie_backup:backup_failed',
                    'Nachfolgendes Datenbank-Backup wurde erfolgreich und fehlerfrei erstellt.'
                );
            });
        }
    }
}
