<?php

namespace Tests\Support;

/**
 * Pro-User-Isolation der PHPUnit-Temp-Pfade (LOG_SINGLE_PATH, VIEW_COMPILED_PATH),
 * Cross-UID-Berechtigungsschutz fuer Storage::fake() (storage/framework/testing)
 * sowie prozessuebergreifende Serialisierung paralleler PHPUnit-Laeufe auf seelenfunke_testing.
 */
final class TestingTmpPathGuard
{
    public const LOG_ENV = 'LOG_SINGLE_PATH';
    public const VIEW_ENV = 'VIEW_COMPILED_PATH';
    public const MUTEX_DIR = '/tmp/sf_phpunit_mutex';
    public const MUTEX_OWNER_ENV = 'SF_PHPUNIT_MUTEX_OWNER_PID';

    /** @var resource|null */
    private static $processLockHandle = null;

    private static bool $shutdownRegistered = false;

    public static function apply(): void
    {
        self::acquireProcessDatabaseMutex();
        self::registerCrossUidShutdownCleanup();

        $uid = self::effectiveUid();

        $log = self::readEnv(self::LOG_ENV);
        if ($log !== null) {
            $resolved = self::resolveLogPath($log, $uid, [self::class, 'canAppend']);
            if ($resolved !== $log) {
                self::writeEnv(self::LOG_ENV, $resolved);
            }
        }

        $views = self::readEnv(self::VIEW_ENV);
        if ($views !== null) {
            $resolved = self::resolveViewPath($views, $uid, static fn (string $dir): ?int => is_dir($dir) ? (@fileowner($dir) ?: null) : null);
            if ($resolved !== $views) {
                self::writeEnv(self::VIEW_ENV, $resolved);
            }
            if (str_starts_with($resolved, '/tmp/') && !is_dir($resolved)) {
                @mkdir($resolved, 0775, true);
                self::safeChmod($resolved, 0775);
            }
        }
    }

    /**
     * Erwirbt einmalig pro PHPUnit-Betriebssystemprozess einen exklusiven Kernel-Advisory-Lock (flock)
     * fuer die aktive Testdatenbank, kompatibel zwischen root (UID 0), www-data (UID 33) und WSL-User.
     */
    public static function acquireProcessDatabaseMutex(?string $customMutexDir = null): bool
    {
        if (self::$processLockHandle !== null && $customMutexDir === null) {
            return true;
        }

        $dbName = self::readEnv('DB_DATABASE') ?? 'seelenfunke_testing';
        if ($dbName === ':memory:' && $customMutexDir === null) {
            return false;
        }

        $ownerPid = self::readEnv(self::MUTEX_OWNER_ENV);
        if (
            $customMutexDir === null
            && $ownerPid !== null
            && function_exists('posix_getppid')
            && (int) $ownerPid === posix_getppid()
        ) {
            return true;
        }

        $mutexDir = $customMutexDir ?? self::MUTEX_DIR;
        if (!is_dir($mutexDir)) {
            @mkdir($mutexDir, 0777, true);
        }
        // Explizites chmod 0777 ohne Sticky-Bit (+t), nur wenn eigener Owner oder root (keine EPERM-Warnung!)
        self::safeChmod($mutexDir, 0777);

        $safeDbKey = preg_replace('/[^A-Za-z0-9_.-]/', '_', $dbName) ?: 'testing';
        $lockPath = rtrim($mutexDir, '/') . '/db_' . $safeDbKey . '.lock';

        $handle = @fopen($lockPath, 'cb');
        if ($handle === false) {
            return false;
        }

        self::safeChmod($lockPath, 0666);

        if (!@flock($handle, LOCK_EX)) {
            @fclose($handle);

            return false;
        }

        if ($customMutexDir !== null) {
            @flock($handle, LOCK_UN);
            @fclose($handle);

            return true;
        }

        self::$processLockHandle = $handle;
        self::writeEnv(self::MUTEX_OWNER_ENV, (string) getmypid());

        return true;
    }

    /**
     * Zieht auf der Testdatenbank (seelenfunke_testing) einmalig ausstehende Migrationen nach,
     * bevor reine DatabaseTransactions-Tests starten.
     */
    public static function ensureTestingDatabaseMigrated(): void
    {
        try {
            $dbName = (string) \Illuminate\Support\Facades\DB::connection()->getDatabaseName();
            if ($dbName === '' || $dbName === ':memory:' || !str_contains(strtolower($dbName), 'testing')) {
                return;
            }

            if (!\App\Services\System\SystemErrorLogger::isLocalDatabaseIncompleteOrMigrating()) {
                return;
            }

            try {
                \Illuminate\Support\Facades\Artisan::call('migrate', [
                    '--force' => true,
                    '--no-interaction' => true,
                ]);
            } catch (\Throwable) {
                \Illuminate\Support\Facades\Artisan::call('migrate:fresh', [
                    '--force' => true,
                    '--no-interaction' => true,
                ]);
            }
        } catch (\Throwable) {
        }
    }

    /**
     * Oeffnet von Flysystem (Storage::fake) mit 0700 angelegte Verzeichnisse fuer nachfolgende Laeufe
     * unter einem anderen Container-User (z. B. root -> www-data oder www-data -> WSL).
     */
    public static function relaxOwnedTreePermissions(string $directory): void
    {
        if ($directory === '' || !is_dir($directory)) {
            return;
        }

        self::safeChmod($directory, 0777);

        try {
            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS),
                \RecursiveIteratorIterator::SELF_FIRST
            );

            foreach ($iterator as $item) {
                $itemPath = $item->getPathname();
                if (is_link($itemPath)) {
                    continue;
                }
                self::safeChmod($itemPath, $item->isDir() ? 0777 : 0666);
            }
        } catch (\Throwable) {
        }
    }

    public static function safeChmod(string $path, int $mode): void
    {
        if ($path === '' || (!is_file($path) && !is_dir($path))) {
            return;
        }

        $uid = self::effectiveUid();
        if ($uid === null || $uid === 0 || @fileowner($path) === $uid) {
            @chmod($path, $mode);
        }
    }

    private static function registerCrossUidShutdownCleanup(): void
    {
        if (self::$shutdownRegistered) {
            return;
        }
        self::$shutdownRegistered = true;

        $testingStorageDir = dirname(__DIR__, 2) . '/storage/framework/testing';
        register_shutdown_function(static function () use ($testingStorageDir): void {
            self::relaxOwnedTreePermissions($testingStorageDir);
        });
    }

    /**
     * @param callable(string):bool $canAppend
     */
    public static function resolveLogPath(string $configured, ?int $uid, callable $canAppend): string
    {
        if ($uid === null || !str_starts_with($configured, '/tmp/')) {
            return $configured;
        }

        if ($canAppend($configured)) {
            return $configured;
        }

        $base = preg_replace('/\.log$/', '', $configured);

        return $base . '.uid' . $uid . '.log';
    }

    /**
     * @param callable(string):?int $ownerOf liefert die Owner-UID des Verzeichnisses oder null, falls es nicht existiert
     */
    public static function resolveViewPath(string $configured, ?int $uid, callable $ownerOf): string
    {
        if ($uid === null || !str_starts_with($configured, '/tmp/')) {
            return $configured;
        }

        $owner = $ownerOf($configured);
        if ($owner === null || $owner === $uid) {
            return $configured;
        }

        return rtrim($configured, '/') . '_uid' . $uid;
    }

    public static function canAppend(string $path): bool
    {
        $handle = @fopen($path, 'ab');
        if ($handle === false) {
            return false;
        }
        fclose($handle);

        self::safeChmod($path, 0666);

        return true;
    }

    private static function effectiveUid(): ?int
    {
        return function_exists('posix_geteuid') ? posix_geteuid() : null;
    }

    private static function readEnv(string $key): ?string
    {
        $value = $_ENV[$key] ?? $_SERVER[$key] ?? getenv($key);

        return is_string($value) && $value !== '' ? $value : null;
    }

    private static function writeEnv(string $key, string $value): void
    {
        $_ENV[$key] = $value;
        $_SERVER[$key] = $value;
        putenv($key . '=' . $value);
    }
}
