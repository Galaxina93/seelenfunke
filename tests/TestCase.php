<?php

namespace App\Services\AI {
    if (!function_exists('App\Services\AI\curl_setopt')) {
        function curl_setopt($ch, $option, $value) {
            if (isset(\Tests\TestCase::$useCurlMock) && \Tests\TestCase::$useCurlMock) {
                $chId = is_object($ch) ? spl_object_id($ch) : (int)$ch;
                \Tests\Feature\Services\AI\CurlMockRegistry::$writeCallbacks[$chId] = $value;
            }
            return \curl_setopt($ch, $option, $value);
        }
    }

    if (!function_exists('App\Services\AI\curl_exec')) {
        function curl_exec($ch) {
            if (isset(\Tests\TestCase::$useCurlMock) && \Tests\TestCase::$useCurlMock) {
                $chId = is_object($ch) ? spl_object_id($ch) : (int)$ch;
                if (isset(\Tests\Feature\Services\AI\CurlMockRegistry::$writeCallbacks[$chId])) {
                    $callback = \Tests\Feature\Services\AI\CurlMockRegistry::$writeCallbacks[$chId];
                    $responseStrings = \Tests\Feature\Services\AI\CurlMockRegistry::$responseStrings;
                    $responseString = $responseStrings[$chId] ?? (!empty($responseStrings) ? reset($responseStrings) : '');
                    
                    $chunks = explode("\n", $responseString);
                    foreach ($chunks as $chunk) {
                        $callback($ch, $chunk . "\n");
                    }
                }
                return true;
            }
            return \curl_exec($ch);
        }
    }

    if (!function_exists('App\Services\AI\curl_getinfo')) {
        function curl_getinfo($ch, $opt = null) {
            if (isset(\Tests\TestCase::$useCurlMock) && \Tests\TestCase::$useCurlMock) {
                if ($opt === CURLINFO_HTTP_CODE) {
                    return 200;
                }
            }
            return \curl_getinfo($ch, $opt);
        }
    }

    if (!function_exists('App\Services\AI\curl_error')) {
        function curl_error($ch) {
            if (isset(\Tests\TestCase::$useCurlMock) && \Tests\TestCase::$useCurlMock) {
                return '';
            }
            return \curl_error($ch);
        }
    }
}

namespace Tests {
    use Illuminate\Foundation\Testing\RefreshDatabase;
    use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
    use Tests\Support\TestingTmpPathGuard;

    abstract class TestCase extends BaseTestCase
    {
        use CreatesApplication;

        public static bool $useCurlMock = false;
        protected static bool $testingSchemaSynchronized = false;

        private ?string $baseSnapshotInbox = null;
        private ?string $baseSnapshotHandoff = null;

        protected function setUp(): void
        {
            if (!$this->app) {
                $this->refreshApplication();
            }

            $inboxPath = storage_path('app/private/antigravity_inbox.json');
            $handoffPath = storage_path('app/private/antigravity_error_handoff.json');
            $this->baseSnapshotInbox = is_file($inboxPath) ? (string) @file_get_contents($inboxPath) : null;
            $this->baseSnapshotHandoff = is_file($handoffPath) ? (string) @file_get_contents($handoffPath) : null;

            if (!static::$testingSchemaSynchronized) {
                static::$testingSchemaSynchronized = true;
                $usesRefreshDatabase = isset(class_uses_recursive(static::class)[RefreshDatabase::class]);
                if (!$usesRefreshDatabase) {
                    TestingTmpPathGuard::ensureTestingDatabaseMigrated();
                }
            }

            parent::setUp();
            \Illuminate\Support\Facades\Cache::forget('global_shop_settings');
        }

        protected function tearDown(): void
        {
            $inboxPath = storage_path('app/private/antigravity_inbox.json');
            $handoffPath = storage_path('app/private/antigravity_error_handoff.json');
            $testingDiskRoot = storage_path('framework/testing');

            parent::tearDown();

            TestingTmpPathGuard::relaxOwnedTreePermissions($testingDiskRoot);

            if ($this->baseSnapshotInbox !== null) {
                @file_put_contents($inboxPath, $this->baseSnapshotInbox);
                TestingTmpPathGuard::safeChmod($inboxPath, 0666);
            } elseif (is_file($inboxPath)) {
                @unlink($inboxPath);
            }

            if ($this->baseSnapshotHandoff !== null) {
                @file_put_contents($handoffPath, $this->baseSnapshotHandoff);
                TestingTmpPathGuard::safeChmod($handoffPath, 0666);
            } elseif (is_file($handoffPath)) {
                @unlink($handoffPath);
            }
        }
    }
}
