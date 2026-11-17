<?php

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Tests\Support\TestingTmpPathGuard;

class TestingTmpPathGuardTest extends TestCase
{
    #[Test]
    public function keeps_canonical_log_path_when_appendable(): void
    {
        $this->assertSame(
            '/tmp/sf_phpunit_testing.log',
            TestingTmpPathGuard::resolveLogPath('/tmp/sf_phpunit_testing.log', 0, static fn () => true)
        );
    }

    #[Test]
    public function falls_back_to_per_uid_log_when_foreign_owned_file_blocks_append(): void
    {
        // Simuliert protected_regular=2: root (uid 0) darf die www-data-Datei nicht mit O_CREAT oeffnen
        $this->assertSame(
            '/tmp/sf_phpunit_testing.uid0.log',
            TestingTmpPathGuard::resolveLogPath('/tmp/sf_phpunit_testing.log', 0, static fn () => false)
        );
        $this->assertSame(
            '/tmp/sf_phpunit_testing.uid33.log',
            TestingTmpPathGuard::resolveLogPath('/tmp/sf_phpunit_testing.log', 33, static fn () => false)
        );
    }

    #[Test]
    public function never_touches_paths_outside_tmp(): void
    {
        $storage = '/var/www/html/storage/logs/laravel.log';
        $this->assertSame($storage, TestingTmpPathGuard::resolveLogPath($storage, 0, static fn () => false));
        $this->assertSame('/var/www/views', TestingTmpPathGuard::resolveViewPath('/var/www/views', 0, static fn () => 33));
    }

    #[Test]
    public function isolates_compiled_views_per_uid_when_directory_is_foreign_owned(): void
    {
        $this->assertSame('/tmp/sf_test_views', TestingTmpPathGuard::resolveViewPath('/tmp/sf_test_views', 0, static fn () => null));
        $this->assertSame('/tmp/sf_test_views', TestingTmpPathGuard::resolveViewPath('/tmp/sf_test_views', 33, static fn () => 33));
        $this->assertSame('/tmp/sf_test_views_uid0', TestingTmpPathGuard::resolveViewPath('/tmp/sf_test_views', 0, static fn () => 33));
    }

    #[Test]
    public function real_probe_appends_to_own_temp_file(): void
    {
        $path = sys_get_temp_dir() . '/sf_guard_probe_' . bin2hex(random_bytes(4)) . '.log';
        try {
            $this->assertTrue(TestingTmpPathGuard::canAppend($path));
            $this->assertFileExists($path);
        } finally {
            @unlink($path);
        }
    }

    #[Test]
    public function acquires_cross_uid_non_sticky_database_mutex_cleanly(): void
    {
        $customDir = sys_get_temp_dir() . '/sf_mutex_probe_' . bin2hex(random_bytes(4));
        try {
            $this->assertTrue(TestingTmpPathGuard::acquireProcessDatabaseMutex($customDir));
            $this->assertDirectoryExists($customDir);

            $dirMode = fileperms($customDir) & 01777;
            // Kein Sticky-Bit (01000), damit protected_regular=2 Cross-UID-Locks zwischen root und www-data zulaesst
            $this->assertSame(0, $dirMode & 01000);
        } finally {
            foreach (glob($customDir . '/*') ?: [] as $f) {
                @unlink($f);
            }
            @rmdir($customDir);
        }
    }

    #[Test]
    public function relaxes_flysystem_0700_testing_directories_for_cross_uid_runs(): void
    {
        $base = sys_get_temp_dir() . '/sf_fake_disk_' . bin2hex(random_bytes(4));
        $nested = $base . '/local/seelenfunke-test-backup';
        $file = $nested . '/fresh.zip';

        try {
            mkdir($nested, 0700, true);
            chmod($nested, 0700);
            file_put_contents($file, 'PK');
            chmod($file, 0600);

            TestingTmpPathGuard::relaxOwnedTreePermissions($base);

            $this->assertSame(0777, fileperms($nested) & 0777);
            $this->assertSame(0666, fileperms($file) & 0666);
        } finally {
            @unlink($file);
            @rmdir($nested);
            @rmdir($base . '/local');
            @rmdir($base);
        }
    }
}
