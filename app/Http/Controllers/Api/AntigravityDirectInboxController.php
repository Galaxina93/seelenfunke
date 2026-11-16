<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Admin\Admin;
use App\Services\System\AntigravityDirectInboxService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use Laravel\Sanctum\PersonalAccessToken;
use Symfony\Component\HttpFoundation\Response;

class AntigravityDirectInboxController extends Controller
{
    public function __construct(
        private readonly AntigravityDirectInboxService $inboxService
    ) {
    }

    /**
     * GET /api/system/ag-direct-inbox
     */
    public function index(Request $request): JsonResponse
    {
        $this->authorizeAdmin($request);

        $validated = $request->validate([
            'limit' => 'nullable|integer|min:1|max:200',
        ]);

        $limit = isset($validated['limit']) ? (int) $validated['limit'] : 50;
        $snapshot = $this->inboxService->listInbox($limit);
        $blockades = $this->inboxService->getPendingBlockades();

        return $this->withSecurityHeaders(response()->json([
            'success' => true,
            'counts' => $snapshot['counts'],
            'pending_blockades' => $blockades,
            'items' => $snapshot['items'],
        ], 200));
    }

    /**
     * POST /api/system/ag-direct-inbox
     */
    public function store(Request $request): JsonResponse
    {
        $this->authorizeAdmin($request);

        $validated = $request->validate([
            'client_uuid' => 'nullable|string|max:64',
            'channel' => ['nullable', 'string', 'max:32'],
            'title' => 'required|string|max:500',
            'body' => 'nullable|string|max:5000',
            'execution_mode' => 'nullable|string|in:concept,direct',
            'source' => 'nullable|string|max:40',
            'attachment' => 'nullable|file|mimes:png,jpg,jpeg,webp|max:8192',
            'attachment_base64' => 'nullable|string|max:12000000',
            'attachment_mime' => 'nullable|string|max:64',
            'attachment_name' => 'nullable|string|max:180',
        ]);

        $title = trim((string) ($validated['title'] ?? ''));
        if ($title === '') {
            throw ValidationException::withMessages([
                'title' => ['Der Titel darf nicht leer sein.'],
            ]);
        }

        try {
            $checklist = $this->inboxService->normalizeChecklist($request->input('checklist'));
        } catch (InvalidArgumentException $e) {
            throw ValidationException::withMessages([
                'checklist' => [$e->getMessage()],
            ]);
        }

        $validatedAttachment = null;
        if ($request->hasFile('attachment')) {
            try {
                $validatedAttachment = $this->inboxService->validateUploadedAttachment($request->file('attachment'));
            } catch (InvalidArgumentException $e) {
                throw ValidationException::withMessages([
                    'attachment' => [$e->getMessage()],
                ]);
            }
        } elseif ($request->filled('attachment_base64')) {
            try {
                $validatedAttachment = $this->inboxService->validateBase64Attachment(
                    base64Payload: (string) $request->input('attachment_base64'),
                    originalName: $request->input('attachment_name') !== null ? (string) $request->input('attachment_name') : null,
                    declaredMime: $request->input('attachment_mime') !== null ? (string) $request->input('attachment_mime') : null
                );
            } catch (InvalidArgumentException $e) {
                throw ValidationException::withMessages([
                    'attachment_base64' => [$e->getMessage()],
                ]);
            }
        }

        $result = $this->inboxService->enqueueMessage([
            'client_uuid' => $validated['client_uuid'] ?? null,
            'channel' => $validated['channel'] ?? 'leitung',
            'title' => $title,
            'body' => $validated['body'] ?? '',
            'checklist' => $checklist,
            'execution_mode' => $validated['execution_mode'] ?? 'concept',
            'source' => $validated['source'] ?? 'android_app',
        ], $validatedAttachment);

        $isReplay = (bool) ($result['idempotent_replay'] ?? false);
        $statusCode = $isReplay ? 200 : 201;

        return $this->withSecurityHeaders(response()->json([
            'success' => true,
            'idempotent_replay' => $isReplay,
            'auto_resolved_ids' => $result['auto_resolved_ids'] ?? [],
            'data' => $result['item'],
        ], $statusCode));
    }

    /**
     * POST /api/system/ag-direct-inbox/{id}/resolve
     * POST /api/funkenwerk/ag-inbox/{id}/resolve
     */
    public function resolve(Request $request, string $id): JsonResponse
    {
        $admin = $this->authorizeAdmin($request);

        $validated = $request->validate([
            'decision' => 'nullable|string|max:32',
            'answer' => 'nullable|string|max:4000',
            'answer_text' => 'nullable|string|max:4000',
        ]);

        $decision = trim((string) ($validated['decision'] ?? 'approved')) ?: 'approved';
        $answer = trim((string) ($validated['answer_text'] ?? $validated['answer'] ?? ''));

        try {
            $resolution = $this->inboxService->resolveBlockadeItem(
                id: $id,
                decision: $decision,
                answerText: $answer,
                actor: $admin->first_name ?: 'Alina'
            );
        } catch (InvalidArgumentException $e) {
            throw ValidationException::withMessages([
                'decision' => [$e->getMessage()],
            ]);
        }

        if ($resolution === null) {
            return $this->withSecurityHeaders(response()->json([
                'success' => false,
                'message' => 'Blockade-Eintrag nicht gefunden.',
            ], 404));
        }

        $snapshot = $this->inboxService->listInbox(1);

        return $this->withSecurityHeaders(response()->json([
            'success' => true,
            'decision' => $resolution['decision'],
            'resolved_item' => $resolution['resolved_item'],
            'followup_item' => $resolution['followup_item'],
            'counts' => $snapshot['counts'],
        ], 200));
    }

    /**
     * GET /api/system/ag-direct-inbox/{id}/attachment
     */
    public function attachment(Request $request, string $id): Response
    {
        $this->authorizeAdmin($request);

        if (session_status() === PHP_SESSION_ACTIVE) {
            @session_write_close();
        }

        $resolved = $this->inboxService->resolveAttachmentForStreaming($id);
        if ($resolved === null) {
            return $this->withSecurityHeaders(response()->json([
                'success' => false,
                'message' => 'Bildanhang nicht gefunden.',
            ], 404));
        }

        $bytes = @file_get_contents($resolved['path']);
        if (!is_string($bytes) || $bytes === '') {
            return $this->withSecurityHeaders(response()->json([
                'success' => false,
                'message' => 'Bildanhang konnte nicht gelesen werden.',
            ], 404));
        }

        $response = response($bytes, 200, [
            'Content-Type' => $resolved['mime'],
            'Content-Disposition' => 'inline; filename="' . $resolved['name'] . '"',
            'Content-Length' => (string) strlen($bytes),
        ]);

        return $this->withSecurityHeaders($response);
    }

    /**
     * DELETE /api/system/ag-direct-inbox/{id}
     */
    public function destroy(Request $request, string $id): JsonResponse
    {
        $this->authorizeAdmin($request);

        $deleted = $this->inboxService->deleteMessage($id);
        if (!$deleted) {
            return $this->withSecurityHeaders(response()->json([
                'success' => false,
                'message' => 'Nachricht nicht gefunden.',
            ], 404));
        }

        return $this->withSecurityHeaders(response()->json([
            'success' => true,
        ], 200));
    }

    protected function authorizeAdmin(Request $request): Admin
    {
        $user = $request->user();
        if (!$user) {
            abort(401, 'Nicht authentifiziert.');
        }

        if (!$user instanceof Admin) {
            abort(403, 'Zugriff verweigert. Nur Administratoren dürfen auf das direkte Antigravity-Postfach zugreifen.');
        }

        if ($request->bearerToken()) {
            $pat = PersonalAccessToken::findToken((string) $request->bearerToken());
            if ($pat) {
                if ($pat->expires_at && $pat->expires_at->isPast()) {
                    abort(401, 'Token abgelaufen.');
                }

                if (!$pat->can('*') && !$pat->can('ai:funkira')) {
                    abort(403, 'Unzureichende Token-Berechtigung für das direkte Antigravity-Postfach.');
                }
            }
        }

        if (
            method_exists($user, 'currentAccessToken')
            && $user->currentAccessToken()
            && !$user->tokenCan('*')
            && !$user->tokenCan('ai:funkira')
        ) {
            abort(403, 'Unzureichende Token-Berechtigung für das direkte Antigravity-Postfach.');
        }

        return $user;
    }

    /**
     * @template T of Response
     *
     * @param  T  $response
     * @return T
     */
    private function withSecurityHeaders(Response $response): Response
    {
        $response->headers->set('Cache-Control', 'private, no-store, no-cache, must-revalidate');
        $response->headers->set('X-Content-Type-Options', 'nosniff');

        return $response;
    }
}
