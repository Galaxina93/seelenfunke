<?php

namespace Tests\Feature\Api;

use App\Models\Admin\Admin;
use App\Models\Customer\Customer;
use App\Models\Employee\Employee;
use App\Services\System\AntigravityDirectInboxService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AntigravityDirectInboxTest extends TestCase
{
    use RefreshDatabase;

    private string $isolatedPrivateRoot;

    private AntigravityDirectInboxService $inboxService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->isolatedPrivateRoot = storage_path('app/private/testing_ag_inbox_' . Str::lower(Str::random(10)));
        $this->inboxService = new AntigravityDirectInboxService($this->isolatedPrivateRoot);
        $this->app->instance(AntigravityDirectInboxService::class, $this->inboxService);
    }

    protected function tearDown(): void
    {
        if (isset($this->isolatedPrivateRoot) && File::isDirectory($this->isolatedPrivateRoot)) {
            File::deleteDirectory($this->isolatedPrivateRoot);
        }

        parent::tearDown();
    }

    private function createAdmin(): Admin
    {
        return Admin::create([
            'first_name' => 'Alina',
            'last_name' => 'Steinhauer',
            'email' => 'alina-ag-' . Str::lower(Str::random(6)) . '@mein-seelenfunke.de',
            'password' => bcrypt('Secret123!'),
        ]);
    }

    public function test_default_service_uses_strictly_private_storage_outside_public_directory(): void
    {
        $defaultService = new AntigravityDirectInboxService();

        $this->assertSame(
            storage_path('app/private/antigravity_direct_inbox.json'),
            $defaultService->inboxFilePath()
        );
        $this->assertSame(
            storage_path('app/private/ag_direct_media'),
            $defaultService->mediaDirectoryPath()
        );
        $this->assertStringNotContainsString(
            str_replace('\\', '/', public_path()),
            str_replace('\\', '/', $defaultService->inboxFilePath())
        );
        $this->assertStringNotContainsString(
            str_replace('\\', '/', storage_path('app/public')),
            str_replace('\\', '/', $defaultService->mediaDirectoryPath())
        );
    }

    public function test_complete_authorization_matrix_enforces_401_403_and_admin_scopes(): void
    {
        $sampleId = 'ag_20261116_105200_a1b2c3d4';
        $endpoints = [
            ['GET', '/api/system/ag-direct-inbox', []],
            ['POST', '/api/system/ag-direct-inbox', ['title' => 'Unautorisierter Versuch']],
            ['GET', "/api/system/ag-direct-inbox/{$sampleId}/attachment", []],
            ['DELETE', "/api/system/ag-direct-inbox/{$sampleId}", []],
        ];

        // 1. Guest -> 401
        foreach ($endpoints as [$method, $uri, $payload]) {
            $this->json($method, $uri, $payload)->assertStatus(401);
        }

        // 2. Customer -> 403
        $customer = Customer::factory()->create();
        Sanctum::actingAs($customer, ['*']);
        foreach ($endpoints as [$method, $uri, $payload]) {
            $this->json($method, $uri, $payload)->assertStatus(403);
        }

        // 3. Employee roles (internal, influencer, tax_advisor) -> 403
        foreach (['internal', 'influencer', 'tax_advisor'] as $role) {
            $employee = Employee::create([
                'first_name' => ucfirst($role),
                'last_name' => 'Isolation',
                'email' => $role . '-' . Str::lower(Str::random(5)) . '@mein-seelenfunke.de',
                'role' => $role,
                'password' => bcrypt('Secret123!'),
            ]);

            Sanctum::actingAs($employee, ['*']);
            foreach ($endpoints as [$method, $uri, $payload]) {
                $this->json($method, $uri, $payload)->assertStatus(403);
            }
        }

        // 4. Restricted monitor:pulse Admin token -> 403 (both actingAs and real Bearer PAT)
        $admin = $this->createAdmin();
        Sanctum::actingAs($admin, ['monitor:pulse']);
        foreach ($endpoints as [$method, $uri, $payload]) {
            $this->json($method, $uri, $payload)->assertStatus(403);
        }

        $this->app['auth']->forgetGuards();
        $monitorBearer = $admin->createToken('WatchtowerMonitor', ['monitor:pulse'])->plainTextToken;
        foreach ($endpoints as [$method, $uri, $payload]) {
            $this->withHeader('Authorization', 'Bearer ' . $monitorBearer)
                ->json($method, $uri, $payload)
                ->assertStatus(403);
        }

        // 5. Full Admin token -> 200 / 201
        $this->app['auth']->forgetGuards();
        $fullBearer = $admin->createToken('AndroidAppFull', ['*'])->plainTextToken;

        $listResp = $this->withHeader('Authorization', 'Bearer ' . $fullBearer)
            ->getJson('/api/system/ag-direct-inbox');

        $listResp->assertStatus(200)
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertJson([
                'success' => true,
                'counts' => ['pending' => 0, 'delivered' => 0, 'completed' => 0],
                'items' => [],
            ]);

        $cacheControl = (string) $listResp->headers->get('Cache-Control');
        $this->assertStringContainsString('private', $cacheControl);
        $this->assertStringContainsString('no-store', $cacheControl);
        $this->assertStringContainsString('no-cache', $cacheControl);
        $this->assertStringContainsString('must-revalidate', $cacheControl);
    }

    public function test_post_json_and_multipart_with_checklist_image_streaming_idempotency_and_mime_hardening(): void
    {
        $admin = $this->createAdmin();
        Sanctum::actingAs($admin, ['*']);

        // 1. JSON POST with client_uuid + base64 PNG + checklist array with blank entries filtered
        $fakePngFile = UploadedFile::fake()->image('konzept_skizze.png', 24, 24);
        $pngBytes = (string) file_get_contents($fakePngFile->getRealPath());
        $pngBase64 = base64_encode($pngBytes);

        $clientUuid = (string) Str::uuid();
        $jsonResp = $this->postJson('/api/system/ag-direct-inbox', [
            'client_uuid' => $clientUuid,
            'channel' => 'shop',
            'title' => '  Neues Laser-Layout fuer Schieferplatte  ',
            'body' => 'Bitte erst kurz konzeptionieren.',
            'checklist' => ['  Randabstand 4mm pruefen ', '', '   ', 'Gravurzeit kalkulieren'],
            'execution_mode' => 'direct',
            'attachment_base64' => $pngBase64,
            'attachment_mime' => 'image/png',
            'attachment_name' => 'konzept_skizze.png',
        ]);

        $jsonResp->assertStatus(201)
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertJsonPath('success', true)
            ->assertJsonPath('idempotent_replay', false)
            ->assertJsonPath('data.client_uuid', $clientUuid)
            ->assertJsonPath('data.channel', 'shop')
            ->assertJsonPath('data.title', 'Neues Laser-Layout fuer Schieferplatte')
            ->assertJsonPath('data.body', 'Bitte erst kurz konzeptionieren.')
            ->assertJsonPath('data.checklist', ['Randabstand 4mm pruefen', 'Gravurzeit kalkulieren'])
            ->assertJsonPath('data.execution_mode', 'direct')
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonPath('data.source', 'android_app')
            ->assertJsonPath('data.has_attachment', true)
            ->assertJsonPath('data.attachment_mime', 'image/png');

        $firstItem = $jsonResp->json('data');
        $firstId = (string) $firstItem['id'];
        $this->assertMatchesRegularExpression('/^ag_[0-9]{8}_[0-9]{6}_[a-f0-9]{8}$/', $firstId);

        // Verify schema keys including channel
        $expectedKeys = [
            'id', 'client_uuid', 'channel', 'title', 'body', 'checklist', 'execution_mode',
            'status', 'source', 'has_attachment', 'attachment_name', 'attachment_mime',
            'attachment_rel_path', 'ag_response_summary', 'created_at', 'delivered_at', 'completed_at',
        ];
        foreach ($expectedKeys as $key) {
            $this->assertArrayHasKey($key, $firstItem);
        }

        // 2. Idempotent Replay with identical client_uuid -> HTTP 200 without duplicating
        $replayResp = $this->postJson('/api/system/ag-direct-inbox', [
            'client_uuid' => $clientUuid,
            'title' => 'Neues Laser-Layout fuer Schieferplatte',
            'execution_mode' => 'direct',
        ]);

        $replayResp->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('idempotent_replay', true)
            ->assertJsonPath('data.id', $firstId);

        // 3. Multipart POST with checklist as JSON-encoded string + UploadedFile image
        $multipartImage = UploadedFile::fake()->image('android_screenshot.png', 40, 30);
        $multipartBytes = (string) file_get_contents($multipartImage->getRealPath());

        $multipartResp = $this->post('/api/system/ag-direct-inbox', [
            'title' => 'Android Widget Abstand optimieren',
            'channel' => 'unknown_channel_xyz',
            'checklist' => json_encode(['Padding oben 12dp', '  ', 'Gold-Akzent #C5A059']),
            'attachment' => $multipartImage,
        ], [
            'Accept' => 'application/json',
        ]);

        $multipartResp->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.channel', 'leitung')
            ->assertJsonPath('data.execution_mode', 'concept')
            ->assertJsonPath('data.checklist', ['Padding oben 12dp', 'Gold-Akzent #C5A059'])
            ->assertJsonPath('data.has_attachment', true)
            ->assertJsonPath('data.attachment_mime', 'image/png');

        $secondId = (string) $multipartResp->json('data.id');

        // 4. Stream private attachment for lightbox & verify newest-first ordering in GET
        $streamResp = $this->get("/api/system/ag-direct-inbox/{$secondId}/attachment");
        $streamResp->assertStatus(200)
            ->assertHeader('Content-Type', 'image/png')
            ->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->assertStringContainsString('inline;', (string) $streamResp->headers->get('Content-Disposition'));
        $this->assertSame($multipartBytes, $streamResp->getContent());

        // Path traversal / non-existent IDs must return 404
        $this->getJson('/api/system/ag-direct-inbox/ag_nonexistent_1234/attachment')->assertStatus(404);
        $this->getJson('/api/system/ag-direct-inbox/..%5C..%5C.env/attachment')->assertStatus(404);

        $indexResp = $this->getJson('/api/system/ag-direct-inbox?limit=10');
        $indexResp->assertStatus(200)
            ->assertJsonPath('counts.pending', 2)
            ->assertJsonPath('items.0.id', $secondId)
            ->assertJsonPath('items.1.id', $firstId);

        // 5. Reject dangerous file uploads (.php script, disguised .png with PHP code, and base64 PHP payload)
        $evilPhpUpload = UploadedFile::fake()->createWithContent('shell.php', "<?php echo 'pwned'; ?>");
        $this->post('/api/system/ag-direct-inbox', [
            'title' => 'Evil Upload 1',
            'attachment' => $evilPhpUpload,
        ], ['Accept' => 'application/json'])->assertStatus(422);

        $disguisedPng = UploadedFile::fake()->createWithContent('disguised.png', "<?php system('id'); ?>");
        $this->post('/api/system/ag-direct-inbox', [
            'title' => 'Evil Upload 2',
            'attachment' => $disguisedPng,
        ], ['Accept' => 'application/json'])->assertStatus(422);

        $this->postJson('/api/system/ag-direct-inbox', [
            'title' => 'Evil Base64 Upload',
            'attachment_base64' => base64_encode("<?php phpinfo(); ?>"),
            'attachment_mime' => 'image/png',
            'attachment_name' => 'fake.png',
        ])->assertStatus(422);
    }

    public function test_cli_lifecycle_peek_mark_delivered_pull_complete_import_delete_and_rotation(): void
    {
        $admin = $this->createAdmin();
        Sanctum::actingAs($admin, ['*']);

        $shot = UploadedFile::fake()->image('stage_bridge.png', 16, 16);
        $shotBytes = (string) file_get_contents($shot->getRealPath());

        // Create 2 messages (First with attachment, Second without)
        $r1 = $this->post('/api/system/ag-direct-inbox', [
            'title' => 'Erste Nachricht an AG',
            'body' => 'Details zu Nachricht 1',
            'execution_mode' => 'concept',
            'attachment' => $shot,
        ], ['Accept' => 'application/json'])->assertStatus(201);
        $id1 = (string) $r1->json('data.id');
        $relPath1 = (string) $r1->json('data.attachment_rel_path');

        $r2 = $this->postJson('/api/system/ag-direct-inbox', [
            'title' => 'Zweite Nachricht an AG',
            'checklist' => ['Punkt A', 'Punkt B'],
            'execution_mode' => 'direct',
        ])->assertStatus(201);
        $id2 = (string) $r2->json('data.id');

        // 1. --peek-pending returns oldest first + attachment_base64 WITHOUT mutating status
        $exitPeek = Artisan::call('system:ag-inbox', ['--peek-pending' => true]);
        $this->assertSame(0, $exitPeek);
        $peekData = json_decode(trim(Artisan::output()), true);

        $this->assertSame('success', $peekData['status'] ?? null);
        $this->assertSame(2, $peekData['count'] ?? null);
        $this->assertSame($id1, $peekData['items'][0]['id'] ?? null);
        $this->assertSame($id2, $peekData['items'][1]['id'] ?? null);
        $this->assertSame(base64_encode($shotBytes), $peekData['items'][0]['attachment_base64'] ?? null);
        $this->assertSame('pending', $peekData['items'][0]['status'] ?? null);

        // Verify still pending in --list
        Artisan::call('system:ag-inbox', ['--list' => true]);
        $listBefore = json_decode(trim(Artisan::output()), true);
        $this->assertSame(2, $listBefore['counts']['pending'] ?? null);

        // 2. --mark-delivered=<id1> transitions only id1 to delivered
        $exitDeliver = Artisan::call('system:ag-inbox', ['--mark-delivered' => $id1]);
        $this->assertSame(0, $exitDeliver);
        $deliverData = json_decode(trim(Artisan::output()), true);
        $this->assertSame('success', $deliverData['status'] ?? null);
        $this->assertSame(1, $deliverData['updated'] ?? null);

        // 3. --pull-pending now only pulls id2 and atomically marks it delivered
        $exitPull = Artisan::call('system:ag-inbox', ['--pull-pending' => true]);
        $this->assertSame(0, $exitPull);
        $pullData = json_decode(trim(Artisan::output()), true);
        $this->assertSame(1, $pullData['count'] ?? null);
        $this->assertSame($id2, $pullData['items'][0]['id'] ?? null);
        $this->assertSame('delivered', $pullData['items'][0]['status'] ?? null);
        $this->assertNotEmpty($pullData['items'][0]['delivered_at'] ?? null);

        // 4. --complete=<id1> --summary-b64=<base64> marks id1 completed with summary
        $summaryText = 'Konzept erstellt und im Leitstand bereitgestellt.';
        $exitComplete = Artisan::call('system:ag-inbox', [
            '--complete' => $id1,
            '--summary-b64' => base64_encode($summaryText),
        ]);
        $this->assertSame(0, $exitComplete);
        $completeData = json_decode(trim(Artisan::output()), true);
        $this->assertSame('success', $completeData['status'] ?? null);
        $this->assertSame('completed', $completeData['item']['status'] ?? null);
        $this->assertSame($summaryText, $completeData['item']['ag_response_summary'] ?? null);
        $this->assertNotEmpty($completeData['item']['completed_at'] ?? null);

        // 5. --import-b64 into a separate local inbox instance (idempotent merge + media file creation)
        $importRoot = storage_path('app/private/testing_ag_import_' . Str::lower(Str::random(8)));
        try {
            $importService = new AntigravityDirectInboxService($importRoot);
            $this->app->instance(AntigravityDirectInboxService::class, $importService);

            $exportPayload = [$peekData['items'][0]];
            $b64Import = base64_encode((string) json_encode($exportPayload));

            $exitImport1 = Artisan::call('system:ag-inbox', ['--import-b64' => $b64Import]);
            $this->assertSame(0, $exitImport1);
            $importRes1 = json_decode(trim(Artisan::output()), true);
            $this->assertSame('success', $importRes1['status'] ?? null);
            $this->assertSame(1, $importRes1['merged'] ?? null);

            $resolvedMedia = $importService->resolveAttachmentForStreaming($id1);
            $this->assertNotNull($resolvedMedia);
            $this->assertFileExists($resolvedMedia['path']);
            $this->assertSame($shotBytes, file_get_contents($resolvedMedia['path']));

            // Second identical import must be idempotent (merged = 0, no duplicate)
            $exitImport2 = Artisan::call('system:ag-inbox', ['--import-b64' => $b64Import]);
            $this->assertSame(0, $exitImport2);
            $importRes2 = json_decode(trim(Artisan::output()), true);
            $this->assertSame(0, $importRes2['merged'] ?? null);
            $this->assertCount(1, $importService->listInbox()['items']);

            // Verify > 30 day completed rotation cleans up expired completed entry
            $expiredPayload = [[
                'id' => 'ag_20250101_100000_deadbeef',
                'title' => 'Abgelaufener Alt-Eintrag',
                'status' => 'completed',
                'created_at' => now()->subDays(40)->toIso8601String(),
                'completed_at' => now()->subDays(35)->toIso8601String(),
            ]];
            $importService->importItems($expiredPayload);
            $remainingIds = array_column($importService->listInbox()['items'], 'id');
            $this->assertNotContains('ag_20250101_100000_deadbeef', $remainingIds);
            $this->assertContains($id1, $remainingIds);
        } finally {
            $this->app->instance(AntigravityDirectInboxService::class, $this->inboxService);
            if (File::isDirectory($importRoot)) {
                File::deleteDirectory($importRoot);
            }
        }

        // 6. DELETE /api/system/ag-direct-inbox/{id1} removes entry AND private media file
        $mediaAbsBeforeDelete = $this->isolatedPrivateRoot . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relPath1);
        $this->assertFileExists($mediaAbsBeforeDelete);

        $this->deleteJson("/api/system/ag-direct-inbox/{$id1}")
            ->assertStatus(200)
            ->assertJson(['success' => true]);

        $this->assertFileDoesNotExist($mediaAbsBeforeDelete);
        $this->getJson("/api/system/ag-direct-inbox/{$id1}/attachment")->assertStatus(404);
        $this->deleteJson("/api/system/ag-direct-inbox/{$id1}")->assertStatus(404);
    }
}
