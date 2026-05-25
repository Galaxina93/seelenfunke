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
    use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

    abstract class TestCase extends BaseTestCase
    {
        use CreatesApplication;

        public static bool $useCurlMock = false;
    }
}
