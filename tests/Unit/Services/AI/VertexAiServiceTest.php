<?php

namespace Tests\Unit\Services\AI;

use App\Services\AI\VertexAiService;
use App\Services\System\SystemErrorLogger;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class VertexAiServiceTest extends TestCase
{
    protected ?string $tempCredentialsFile = null;

    protected function tearDown(): void
    {
        if ($this->tempCredentialsFile && is_file($this->tempCredentialsFile)) {
            @unlink($this->tempCredentialsFile);
        }
        parent::tearDown();
    }

    protected function createEphemeralServiceAccountFile(): string
    {
        $res = openssl_pkey_new([
            'private_key_bits' => 2048,
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
        ]);
        $privateKeyPem = '';
        openssl_pkey_export($res, $privateKeyPem);

        $path = sys_get_temp_dir() . '/vertex_sa_test_' . uniqid('', true) . '.json';
        file_put_contents($path, json_encode([
            'type' => 'service_account',
            'project_id' => 'gen-lang-client-0670786367',
            'private_key_id' => 'test-key-id',
            'private_key' => $privateKeyPem,
            'client_email' => 'laravel-backend@gen-lang-client-0670786367.iam.gserviceaccount.com',
            'token_uri' => 'https://oauth2.googleapis.com/token',
        ]));

        $this->tempCredentialsFile = $path;

        return $path;
    }

    public function test_vertex_remains_disabled_by_default_in_phpunit_for_legacy_mocks(): void
    {
        $saPath = $this->createEphemeralServiceAccountFile();
        config([
            'services.vertex.enabled' => true,
            'services.vertex.allow_in_tests' => false,
            'services.vertex.credentials_file' => $saPath,
            'services.gemini.key' => 'AIzaSyDummyUnitTestKey123456',
        ]);

        $this->assertFalse(VertexAiService::isEnabled());

        $endpoint = VertexAiService::resolveGenerateContentEndpoint('gemini-2.5-flash');
        $this->assertFalse($endpoint['is_vertex']);
        $this->assertSame(
            'https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash:generateContent',
            $endpoint['url']
        );
    }

    public function test_vertex_signs_jwt_caches_oauth_token_and_normalizes_payload_and_models(): void
    {
        Cache::flush();
        $saPath = $this->createEphemeralServiceAccountFile();

        config([
            'services.vertex.enabled' => true,
            'services.vertex.allow_in_tests' => true,
            'services.vertex.project_id' => 'gen-lang-client-0670786367',
            'services.vertex.location' => 'global',
            'services.vertex.live_location' => 'europe-west4',
            'services.vertex.live_model' => 'gemini-live-2.5-flash-native-audio',
            'services.vertex.credentials_file' => $saPath,
        ]);

        $oauthCalls = 0;
        $vertexRequests = [];

        Http::fake(function ($request) use (&$oauthCalls, &$vertexRequests) {
            if ($request->url() === 'https://oauth2.googleapis.com/token') {
                $oauthCalls++;
                return Http::response([
                    'access_token' => 'ya29.MockVertexAccessToken1234567890',
                    'expires_in' => 3600,
                    'token_type' => 'Bearer',
                ], 200);
            }

            $vertexRequests[] = $request;
            return Http::response([
                'candidates' => [
                    ['content' => ['role' => 'model', 'parts' => [['text' => '{"ok":true}']]]],
                ],
            ], 200);
        });

        $this->assertTrue(VertexAiService::isEnabled());
        $this->assertSame('gemini-3.5-flash', VertexAiService::resolveModel('gemini-2.5-flash'));
        $this->assertSame('gemini-3.1-pro-preview', VertexAiService::resolveModel('gemini-2.5-pro'));
        $this->assertSame('google/gemini-3.5-flash', VertexAiService::formatOpenAiModel('gemini-2.5-flash', true));

        $resp1 = VertexAiService::generateContent('gemini-2.5-flash', [
            'system_instruction' => ['parts' => [['text' => 'System']]],
            'contents' => [
                [
                    'parts' => [
                        ['text' => 'Hallo'],
                        ['inline_data' => ['mime_type' => 'application/pdf', 'data' => 'dGVzdA==']],
                    ],
                ],
            ],
        ]);
        $resp2 = VertexAiService::generateContent('gemini-3-flash-preview', [
            'contents' => [['role' => 'user', 'parts' => [['text' => 'Zweiter Aufruf']]]],
        ]);

        $this->assertTrue($resp1->successful());
        $this->assertTrue($resp2->successful());
        $this->assertSame(1, $oauthCalls, 'OAuth2 token must be cached across consecutive Vertex calls.');
        $this->assertCount(2, $vertexRequests);

        $firstReq = $vertexRequests[0];
        $this->assertSame(
            'https://aiplatform.googleapis.com/v1beta1/projects/gen-lang-client-0670786367/locations/global/publishers/google/models/gemini-3.5-flash:generateContent',
            $firstReq->url()
        );
        $this->assertTrue($firstReq->hasHeader('Authorization', 'Bearer ya29.MockVertexAccessToken1234567890'));

        $sentBody = $firstReq->data();
        $this->assertSame('user', $sentBody['contents'][0]['role'] ?? null);
        $this->assertSame('application/pdf', $sentBody['contents'][0]['parts'][1]['inlineData']['mimeType'] ?? null);
        $this->assertArrayHasKey('systemInstruction', $sentBody);

        $live = VertexAiService::resolveLiveWebSocketEndpoint();
        $this->assertTrue($live['is_vertex']);
        $this->assertSame('europe-west4-aiplatform.googleapis.com', $live['host']);
        $this->assertSame(
            'projects/gen-lang-client-0670786367/locations/europe-west4/publishers/google/models/gemini-live-2.5-flash-native-audio',
            $live['model']
        );

        $scrubbed = SystemErrorLogger::sanitizeSensitiveText('Failed token ya29.MockVertexAccessToken1234567890 in URL');
        $this->assertStringNotContainsString('MockVertexAccessToken1234567890', $scrubbed);
    }
}
