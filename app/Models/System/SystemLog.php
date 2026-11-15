<?php

namespace App\Models\System;


use App\Support\Blueprint\Concerns\InteractsWithSystemBlueprint;
use App\Support\Blueprint\Contracts\HasSystemBlueprint;
use App\Support\Blueprint\Enums\ActorScope;
use App\Support\Blueprint\Enums\AiAccessPolicy;
use App\Support\Blueprint\Enums\ComplianceRegulation;
use App\Support\Blueprint\Enums\DataSensitivity;
use App\Support\Blueprint\Enums\ExternalChannel;
use App\Support\Blueprint\Enums\LifecycleBehavior;
use App\Support\Blueprint\Enums\RetentionPolicy;
use App\Support\Blueprint\Enums\SystemDepartment;
use App\Support\Blueprint\ModelBlueprint;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class SystemLog extends Model implements HasSystemBlueprint
{
    use InteractsWithSystemBlueprint;

    /**
     * Kanonische Ökosystem-Bereiche für Spalte 1 & Filter in /admin/global-logs.
     */
    public const PLATFORMS = [
        'portal' => [
            'key' => 'portal',
            'label' => 'Webportal',
            'short' => 'Webportal',
        ],
        'android' => [
            'key' => 'android',
            'label' => 'Android App',
            'short' => 'Android',
        ],
        'minipc_linux' => [
            'key' => 'minipc_linux',
            'label' => 'Mini-PC',
            'short' => 'Mini-PC',
        ],
        'windows_desktop' => [
            'key' => 'windows_desktop',
            'label' => 'Funkenwerk Software',
            'short' => 'Funkenwerk',
        ],
    ];

    /**
     * Digitales Architektur- & Compliance-Typenschild (Living Documentation).
     */
    public static function blueprint(): ModelBlueprint
    {
        return ModelBlueprint::make(
            label: 'System-, Sicherheits- & DSGVO-Audit-Log',
            description: 'Zentrales Ereignisprotokoll für Sicherheitsvorfälle, Hintergrundjobs und DSGVO-Einwilligungsnachweise.',
            department: SystemDepartment::System,
            navigationNodes: ['admin.system.logs'],
            lifecycle: LifecycleBehavior::AppendOnlyLog,
            sensitivity: DataSensitivity::PersonalPII,
            retention: RetentionPolicy::ShortLived_30Days,
            aiAccess: AiAccessPolicy::ReadOnly,
            actorsRead: [ActorScope::Admin],
            actorsWrite: [ActorScope::Admin, ActorScope::SystemCron],
            regulations: [ComplianceRegulation::DSGVO_Art15_Art30],
            externalChannels: [],
            dependsOnSettings: [],
            isGdprArt15Exportable: false,
            customerForeignKey: null,
            containsProductionAssets: false,
            assetColumns: [],
        );
    }

protected $table = 'system_logs';

    protected $fillable = [
        'ai_agent_id',
        'type',
        'action_id',
        'title',
        'message',
        'status',
        'payload',
        'started_at',
        'finished_at'
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
        'payload' => 'array',
    ];

    /**
     * Relation zum ausführenden KI-Agenten
     */
    public function agent()
    {
        return $this->belongsTo(\App\Models\Ai\AiAgent::class, 'ai_agent_id');
    }

    public static function normalizePlatformAlias(?string $raw): ?string
    {
        $clean = strtolower(trim((string) $raw));
        if ($clean === '') {
            return null;
        }

        return match ($clean) {
            'portal', 'web', 'shop', 'laravel', 'frontend', 'frontend_error', 'backend' => 'portal',
            'android', 'android_app' => 'android',
            'minipc_linux', 'minipc', 'mini_pc', 'mini-pc', 'funkenwerk_os', 'atelier' => 'minipc_linux',
            'windows_desktop', 'funkenwerk', 'funkenwerk_desktop', 'desktop', 'windows' => 'windows_desktop',
            default => null,
        };
    }

    public static function detectPlatformKey(?string $type, ?string $actionId, ?array $payload = null): string
    {
        if (is_array($payload) && !empty($payload['platform'])) {
            $fromPayload = self::normalizePlatformAlias((string) $payload['platform']);
            if ($fromPayload !== null) {
                return $fromPayload;
            }
        }

        $fromType = self::normalizePlatformAlias($type);
        if ($fromType !== null && $fromType !== 'portal') {
            return $fromType;
        }

        $actionStr = strtolower(trim((string) $actionId));
        if ($actionStr !== '' && str_contains($actionStr, ':')) {
            $prefix = explode(':', $actionStr, 2)[0];
            $fromPrefix = self::normalizePlatformAlias($prefix);
            if ($fromPrefix !== null && $fromPrefix !== 'portal') {
                return $fromPrefix;
            }
        }

        return 'portal';
    }

    public function resolvePlatformKey(): string
    {
        $payload = is_array($this->payload) ? $this->payload : (json_decode((string) $this->payload, true) ?: []);
        return self::detectPlatformKey($this->type, $this->action_id, $payload);
    }

    public function resolvePlatformMeta(): array
    {
        $key = $this->resolvePlatformKey();
        return self::PLATFORMS[$key] ?? self::PLATFORMS['portal'];
    }

    /**
     * Prüft, ob dieser Log-Eintrag ein schützenswerter DSGVO-, Einwilligungs- oder Sicherheits-Auditnachweis ist
     * (30 Tage Aufbewahrung gemäß RetentionPolicy::ShortLived_30Days statt 7-Tage Kurzzeit-Archiv).
     */
    public function isAuditProtected(): bool
    {
        $actionId = strtolower((string) $this->action_id);
        $type = strtolower((string) $this->type);

        if ($type === 'security' && $actionId !== 'security:package_audit') {
            return true;
        }

        $protectedPrefixes = [
            'gdpr:',
            'consent:',
            'customer:',
            'user:security_update',
            'security:',
            'admin_order_force_delete',
        ];

        foreach ($protectedPrefixes as $prefix) {
            if (str_starts_with($actionId, $prefix) && $actionId !== 'security:package_audit') {
                return true;
            }
        }

        return false;
    }

    public function scopeOpenIssues(Builder $query): Builder
    {
        return $query->whereIn('status', ['error', 'warning']);
    }

    public function scopeArchivedEntries(Builder $query): Builder
    {
        return $query->whereNotIn('status', ['error', 'warning']);
    }

    public function scopeForPlatform(Builder $query, string $platform): Builder
    {
        $normalized = self::normalizePlatformAlias($platform);
        if ($normalized === null) {
            return $query;
        }

        if ($normalized === 'android') {
            return $query->where(function (Builder $q) {
                $q->where('type', 'android')
                    ->orWhere('action_id', 'like', 'android:%')
                    ->orWhere('payload->platform', 'android');
            });
        }

        if ($normalized === 'minipc_linux') {
            return $query->where(function (Builder $q) {
                $q->where('type', 'minipc_linux')
                    ->orWhere('action_id', 'like', 'minipc_linux:%')
                    ->orWhere('action_id', 'like', 'minipc:%')
                    ->orWhere('payload->platform', 'minipc_linux');
            });
        }

        if ($normalized === 'windows_desktop') {
            return $query->where(function (Builder $q) {
                $q->where('type', 'windows_desktop')
                    ->orWhere('action_id', 'like', 'windows_desktop:%')
                    ->orWhere('action_id', 'like', 'funkenwerk:%')
                    ->orWhere('payload->platform', 'windows_desktop');
            });
        }

        return $query->where(function (Builder $q) {
            $q->where(function (Builder $tq) {
                $tq->whereNull('type')
                    ->orWhereNotIn('type', ['android', 'minipc_linux', 'windows_desktop']);
            })
            ->where(function (Builder $aq) {
                $aq->whereNull('action_id')
                    ->orWhere(function (Builder $naq) {
                        $naq->where('action_id', 'not like', 'android:%')
                            ->where('action_id', 'not like', 'minipc_linux:%')
                            ->where('action_id', 'not like', 'minipc:%')
                            ->where('action_id', 'not like', 'windows_desktop:%')
                            ->where('action_id', 'not like', 'funkenwerk:%');
                    });
            })
            ->where(function (Builder $pq) {
                $pq->whereNull('payload')
                    ->orWhereNull('payload->platform')
                    ->orWhere('payload->platform', 'portal');
            });
        });
    }

    public static function isTransientFrontendNoise(string $message, ?string $title = null): bool
    {
        $combined = strtolower($message . ' | ' . ($title ?? ''));
        $noiseMarkers = [
            'could not find livewire component in dom tree',
            'resizeobserver loop',
            'the user aborted a request',
            'aborterror',
            'networkerror when attempting to fetch resource',
            'failed to fetch',
            'unexpected token \'<\', "<!doctype',
            'is not valid json',
        ];

        foreach ($noiseMarkers as $marker) {
            if (str_contains($combined, $marker)) {
                return true;
            }
        }

        if (class_exists(\App\Services\System\SystemErrorLogger::class)
            && \App\Services\System\SystemErrorLogger::isBenignClientCancellation($message, null, $title)) {
            return true;
        }

        return false;
    }

    /**
     * Erstellt einen SystemLog-Eintrag mit automatischer Bereichs-Kennzeichnung (Webportal, Android, Mini-PC, Funkenwerk)
     * und 24h-Deduplizierung (occurrences_count) gegen Log-Flutung.
     */
    public static function create(array $attributes = [])
    {
        $rawPayload = $attributes['payload'] ?? [];
        $payload = is_array($rawPayload)
            ? $rawPayload
            : (is_string($rawPayload) ? (json_decode($rawPayload, true) ?: []) : []);

        $type = (string) ($attributes['type'] ?? 'system');
        $actionId = (string) ($attributes['action_id'] ?? '');
        $title = (string) ($attributes['title'] ?? '');
        $message = (string) ($attributes['message'] ?? '');
        $status = (string) ($attributes['status'] ?? 'info');

        $platformKey = self::detectPlatformKey($type, $actionId, $payload);
        $payload['platform'] = $payload['platform'] ?? $platformKey;
        $payload['platform_label'] = $payload['platform_label'] ?? (self::PLATFORMS[$platformKey]['label'] ?? 'Webportal');

        if ($type === 'frontend_error' && self::isTransientFrontendNoise($message, $title)) {
            $attributes['payload'] = $payload;
            return new static($attributes);
        }

        // 24h-Deduplizierung für offene Fehler/Warnungen ohne vorab berechneten Fingerprint (z. B. /api/log/frontend-error, /api/log/websocket, Security-Events)
        if (in_array($status, ['error', 'warning'], true) && empty($payload['fingerprint'])) {
            $normalizedMsg = preg_replace('/\b\d+\b/', '#', strtolower(trim($message)));
            $fileSig = (string) ($payload['file'] ?? $payload['source'] ?? '');
            $lineSig = (string) ($payload['line'] ?? $payload['lineno'] ?? '');
            $fingerprint = hash('sha256', strtolower($type . '|' . $actionId . '|' . trim($title) . '|' . $fileSig . '|' . $lineSig . '|' . mb_substr((string) $normalizedMsg, 0, 180)));
            $payload['fingerprint'] = $fingerprint;

            try {
                $candidates = static::query()
                    ->where('type', $type)
                    ->where('action_id', $actionId)
                    ->whereIn('status', ['error', 'warning'])
                    ->where(function (Builder $q) {
                        $q->where('started_at', '>=', now()->subHours(24))
                            ->orWhere('created_at', '>=', now()->subHours(24));
                    })
                    ->latest('id')
                    ->limit(15)
                    ->get();

                foreach ($candidates as $candidate) {
                    $cp = is_array($candidate->payload) ? $candidate->payload : [];
                    $candNormMsg = preg_replace('/\b\d+\b/', '#', strtolower(trim((string) $candidate->message)));
                    $candFp = $cp['fingerprint'] ?? hash(
                        'sha256',
                        strtolower(
                            (string) $candidate->type . '|'
                            . (string) $candidate->action_id . '|'
                            . trim((string) $candidate->title) . '|'
                            . (string) ($cp['file'] ?? $cp['source'] ?? '') . '|'
                            . (string) ($cp['line'] ?? $cp['lineno'] ?? '') . '|'
                            . mb_substr((string) $candNormMsg, 0, 180)
                        )
                    );

                    if ($candFp === $fingerprint) {
                        $cp['fingerprint'] = $fingerprint;
                        $cp['platform'] = $cp['platform'] ?? $platformKey;
                        $cp['platform_label'] = $cp['platform_label'] ?? (self::PLATFORMS[$platformKey]['label'] ?? 'Webportal');
                        $cp['occurrences_count'] = ((int) ($cp['occurrences_count'] ?? 1)) + 1;
                        $cp['last_seen_at'] = now()->toIso8601String();
                        $candidate->payload = $cp;
                        $candidate->finished_at = now();
                        if ($status === 'error' && $candidate->status === 'warning') {
                            $candidate->status = 'error';
                        }
                        $candidate->save();

                        return $candidate;
                    }
                }
            } catch (\Throwable $ignore) {
            }

            $payload['occurrences_count'] = (int) ($payload['occurrences_count'] ?? 1);
            $payload['first_seen_at'] = $payload['first_seen_at'] ?? now()->toIso8601String();
            $payload['last_seen_at'] = $payload['last_seen_at'] ?? now()->toIso8601String();
        } elseif (
            in_array($status, ['success', 'info'], true)
            && ($type === 'websocket' || $actionId === 'security:package_audit')
        ) {
            try {
                $existingRoutine = static::query()
                    ->where('type', $type)
                    ->where('action_id', $actionId)
                    ->whereIn('status', ['success', 'info'])
                    ->where('created_at', '>=', now()->subHours(24))
                    ->latest('id')
                    ->first();

                if ($existingRoutine) {
                    $rp = is_array($existingRoutine->payload) ? $existingRoutine->payload : [];
                    $rp['platform'] = $rp['platform'] ?? $platformKey;
                    $rp['platform_label'] = $rp['platform_label'] ?? (self::PLATFORMS[$platformKey]['label'] ?? 'Webportal');
                    $rp['occurrences_count'] = ((int) ($rp['occurrences_count'] ?? 1)) + 1;
                    $rp['last_seen_at'] = now()->toIso8601String();
                    $existingRoutine->message = $message !== '' ? $message : $existingRoutine->message;
                    $existingRoutine->payload = array_merge($rp, $payload, [
                        'occurrences_count' => $rp['occurrences_count'],
                        'last_seen_at' => $rp['last_seen_at'],
                    ]);
                    $existingRoutine->finished_at = now();
                    $existingRoutine->save();

                    return $existingRoutine;
                }
            } catch (\Throwable $ignore) {
            }
        }

        $attributes['payload'] = $payload;

        return static::query()->create($attributes);
    }

    /**
     * Helper um einen Log-Eintrag schnell zu starten
     */
    public static function start($actionId, $title, $type = 'automation', $aiAgentId = null)
    {
        return self::create([
            'ai_agent_id' => $aiAgentId,
            'action_id' => $actionId,
            'title' => $title,
            'type' => $type,
            'status' => 'running',
            'started_at' => now(),
        ]);
    }

    /**
     * Helper um einen laufenden Log zu beenden
     */
    public function finish($status = 'success', $message = null, $payload = null)
    {
        $this->update([
            'status' => $status,
            'message' => $message,
            'payload' => $payload,
            'finished_at' => now(),
        ]);
    }
}
