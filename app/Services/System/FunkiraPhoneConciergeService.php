<?php

namespace App\Services\System;

use App\Models\Management\ManagementContact;
use App\Models\Management\ManagementTask;
use App\Models\Management\ManagementTaskList;
use App\Models\System\SystemSetting;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class FunkiraPhoneConciergeService
{
    public const SETTING_ENABLED = 'funkira_phone_concierge_enabled';
    public const SETTING_RING_DELAY = 'funkira_phone_concierge_ring_delay';

    private const CACHE_ACTIVE_CALL = 'funkira_phone_concierge_active_call_v1';
    private const CACHE_LAST_SUMMARY = 'funkira_phone_concierge_last_summary_v1';

    /**
     * Normalisiert deutsche und internationale Telefonnummern für sicheren Whitelist-Abgleich.
     * Beispiel: "+49 176 57793016" und "0176-57793016" -> "4917657793016".
     */
    public static function normalizePhoneDigits(?string $raw): string
    {
        if (empty($raw)) {
            return '';
        }

        // Falls mehrere Nummern getrennt durch "/" hinterlegt sind, erste Nummer nehmen
        $primary = explode('/', (string) $raw)[0] ?? (string) $raw;
        $trimmed = trim($primary);
        $hasPlus = str_starts_with($trimmed, '+');
        $digits = preg_replace('/\D+/', '', $trimmed) ?? '';

        if ($digits === '') {
            return '';
        }

        if ($hasPlus) {
            return ltrim($digits, '0');
        }

        if (str_starts_with($digits, '00') && strlen($digits) > 4) {
            return substr($digits, 2);
        }

        if (str_starts_with($digits, '0') && strlen($digits) >= 7) {
            return '49' . substr($digits, 1);
        }

        return $digits;
    }

    /**
     * Liefert die letzten 8 Ziffern als robusten Mobilfunk-/Festnetz-Suffix für Rufnummernübermittlung.
     */
    public static function extractMatchSuffix(?string $raw): string
    {
        $norm = self::normalizePhoneDigits($raw);
        if ($norm === '') {
            return '';
        }

        return strlen($norm) > 8 ? substr($norm, -8) : $norm;
    }

    public static function isEnabled(): bool
    {
        $val = SystemSetting::get(self::SETTING_ENABLED, '1');
        return in_array((string) $val, ['1', 'true', 'yes', 'on'], true);
    }

    public static function getRingDelaySeconds(): int
    {
        $val = (int) SystemSetting::get(self::SETTING_RING_DELAY, '12');
        return max(0, min(30, $val));
    }

    public static function updateConfig(?bool $enabled = null, ?int $ringDelaySeconds = null): array
    {
        if ($enabled !== null) {
            SystemSetting::set(self::SETTING_ENABLED, $enabled ? '1' : '0');
        }

        if ($ringDelaySeconds !== null) {
            $clamped = max(0, min(30, $ringDelaySeconds));
            SystemSetting::set(self::SETTING_RING_DELAY, (string) $clamped);
        }

        return self::getConfigPayload();
    }

    public static function getConfigPayload(): array
    {
        $contacts = ManagementContact::query()
            ->where('funkira_call_whitelist', true)
            ->orderBy('is_favorite', 'desc')
            ->orderBy('first_name', 'asc')
            ->get();

        $whitelist = $contacts->map(function (ManagementContact $c) {
            $norm = self::normalizePhoneDigits($c->phone);
            return [
                'id' => $c->id,
                'first_name' => (string) $c->first_name,
                'last_name' => (string) ($c->last_name ?? ''),
                'full_name' => $c->full_name,
                'nickname' => (string) ($c->nickname ?? ''),
                'relation_type' => (string) ($c->relation_type ?? ''),
                'phone' => (string) ($c->phone ?? ''),
                'normalized_phone' => $norm,
                'match_suffix' => self::extractMatchSuffix($c->phone),
                'system_instructions' => Str::limit((string) ($c->system_instructions ?? ''), 900, ''),
            ];
        })->values()->all();

        return [
            'enabled' => self::isEnabled(),
            'ring_delay_seconds' => self::getRingDelaySeconds(),
            'whitelist_count' => count($whitelist),
            'whitelist_contacts' => $whitelist,
        ];
    }

    /**
     * Sucht einen freigegebenen Whitelist-Kontakt anhand einer eingehenden Rufnummer oder Kontakt-ID.
     */
    public static function matchWhitelistedCaller(?string $incomingNumber, ?string $contactId = null): ?ManagementContact
    {
        if (!empty($contactId) && Str::isUuid($contactId)) {
            $byId = ManagementContact::find($contactId);
            if ($byId && $byId->funkira_call_whitelist) {
                return $byId;
            }
        }

        $incomingNorm = self::normalizePhoneDigits($incomingNumber);
        $incomingSuffix = self::extractMatchSuffix($incomingNumber);
        if ($incomingNorm === '' && $incomingSuffix === '') {
            return null;
        }

        $whitelisted = ManagementContact::query()
            ->where('funkira_call_whitelist', true)
            ->whereNotNull('phone')
            ->get();

        foreach ($whitelisted as $contact) {
            $cNorm = self::normalizePhoneDigits($contact->phone);
            $cSuffix = self::extractMatchSuffix($contact->phone);
            if ($cNorm !== '' && $cNorm === $incomingNorm) {
                return $contact;
            }
            if (strlen($cSuffix) >= 7 && $cSuffix === $incomingSuffix) {
                return $contact;
            }
        }

        return null;
    }

    /**
     * Verarbeitet Status-Signale vom Android-Smartphone oder dem Werkstatt-Simulator.
     */
    public static function signalCallState(array $data): array
    {
        $state = strtolower(trim((string) ($data['state'] ?? 'ringing')));
        if (!in_array($state, ['ringing', 'accepted', 'cancelled', 'ended'], true)) {
            $state = 'ringing';
        }

        $existing = Cache::get(self::CACHE_ACTIVE_CALL);
        $callId = trim((string) ($data['call_id'] ?? ($existing['call_id'] ?? '')));
        if ($callId === '') {
            $callId = (string) Str::uuid();
        }

        if (in_array($state, ['cancelled', 'ended'], true)) {
            if (is_array($existing) && ($existing['call_id'] ?? '') === $callId) {
                $existing['state'] = $state;
                $existing['hangup_requested'] = true;
                $existing['updated_at'] = now()->toIso8601String();
                Cache::put(self::CACHE_ACTIVE_CALL, $existing, now()->addSeconds(25));
                return $existing;
            }
            Cache::forget(self::CACHE_ACTIVE_CALL);
            return [
                'call_id' => $callId,
                'state' => $state,
                'hangup_requested' => true,
                'updated_at' => now()->toIso8601String(),
            ];
        }

        $isSimulation = (bool) ($data['is_simulation'] ?? ($existing['is_simulation'] ?? false));
        $incomingPhone = trim((string) ($data['caller_phone'] ?? ($existing['caller_phone'] ?? '')));
        $contactId = trim((string) ($data['contact_id'] ?? ($existing['contact_id'] ?? '')));

        $matchedContact = null;
        if (!empty($contactId) && Str::isUuid($contactId)) {
            $matchedContact = ManagementContact::find($contactId);
        }
        if (!$matchedContact && $incomingPhone !== '') {
            $matchedContact = self::matchWhitelistedCaller($incomingPhone, null);
        }

        // Sicherheitsschranke: Echte Anrufe (nicht Simulation) werden nur akzeptiert, wenn Kontakt auf Whitelist steht
        if (!$isSimulation && (!$matchedContact || !$matchedContact->funkira_call_whitelist)) {
            return [
                'rejected' => true,
                'reason' => 'not_whitelisted',
                'call_id' => $callId,
                'state' => 'ignored',
            ];
        }

        $callerName = trim((string) ($data['caller_name'] ?? ''));
        $nickname = trim((string) ($data['caller_nickname'] ?? ''));
        $relation = trim((string) ($data['relation_type'] ?? ''));
        $instructions = trim((string) ($data['system_instructions'] ?? ''));

        if ($matchedContact) {
            $callerName = $matchedContact->full_name ?: $callerName;
            $nickname = (string) ($matchedContact->nickname ?: $nickname);
            $relation = (string) ($matchedContact->relation_type ?: $relation);
            $instructions = (string) ($matchedContact->system_instructions ?: $instructions);
            $contactId = $matchedContact->id;
        }

        if ($callerName === '') {
            $callerName = $nickname !== '' ? $nickname : 'Vertrauter Kontakt';
        }

        $greetingName = $nickname !== ''
            ? trim(explode(',', explode('/', $nickname)[0])[0])
            : (explode(' ', $callerName)[0] ?? $callerName);

        $session = [
            'call_id' => $callId,
            'state' => $state,
            'is_simulation' => $isSimulation,
            'contact_id' => $contactId !== '' ? $contactId : null,
            'caller_name' => $callerName,
            'greeting_name' => $greetingName,
            'relation_type' => $relation,
            'caller_phone' => $incomingPhone,
            'system_instructions' => Str::limit($instructions, 900, ''),
            'audio_mode' => trim((string) ($data['audio_mode'] ?? ($existing['audio_mode'] ?? 'bluetooth_hfp'))),
            'hangup_requested' => false,
            'started_at' => $existing['started_at'] ?? now()->toIso8601String(),
            'updated_at' => now()->toIso8601String(),
        ];

        Cache::put(self::CACHE_ACTIVE_CALL, $session, now()->addMinutes(4));

        return $session;
    }

    public static function pollState(): array
    {
        $active = Cache::get(self::CACHE_ACTIVE_CALL);
        $lastSummary = Cache::get(self::CACHE_LAST_SUMMARY);

        return [
            'enabled' => self::isEnabled(),
            'ring_delay_seconds' => self::getRingDelaySeconds(),
            'active_call' => is_array($active) ? $active : null,
            'hangup_requested' => is_array($active) ? (bool) ($active['hangup_requested'] ?? false) : false,
            'last_completed_call' => is_array($lastSummary) ? $lastSummary : null,
        ];
    }

    /**
     * Schließt das Gespräch ab, erstellt sofort ein To-Do auf /admin/dashboard und signalisiert Auflegen.
     */
    public static function completeCall(array $data): array
    {
        $active = Cache::get(self::CACHE_ACTIVE_CALL);
        $callId = trim((string) ($data['call_id'] ?? ($active['call_id'] ?? Str::uuid()->toString())));
        $isSimulation = (bool) ($data['is_simulation'] ?? ($active['is_simulation'] ?? false));
        $contactId = $data['contact_id'] ?? ($active['contact_id'] ?? null);
        $callerName = trim((string) ($data['caller_name'] ?? ($active['caller_name'] ?? 'Kontakt')));
        $greetingName = trim((string) ($data['greeting_name'] ?? ($active['greeting_name'] ?? $callerName)));
        $summary = trim((string) ($data['summary'] ?? ''));
        $urgency = strtolower(trim((string) ($data['urgency'] ?? 'mittel')));
        if (!in_array($urgency, ['hoch', 'mittel', 'niedrig'], true)) {
            $urgency = 'mittel';
        }
        $callbackRequested = (bool) ($data['callback_requested'] ?? true);

        if ($summary === '') {
            $summary = 'Hat angerufen und bittet bei Gelegenheit um kurzen Rückruf.';
        }

        // 1. Aktives Gespräch auf completed + hangup_requested setzen (damit Android sofort auflegt)
        if (is_array($active)) {
            $active['state'] = 'completed';
            $active['hangup_requested'] = true;
            $active['summary'] = $summary;
            $active['updated_at'] = now()->toIso8601String();
            Cache::put(self::CACHE_ACTIVE_CALL, $active, now()->addSeconds(30));
        }

        // 2. To-Do in Alinas primärer Aufgabenliste auf /admin/dashboard anlegen
        $defaultList = ManagementTaskList::where('is_archived', false)
            ->orderBy('position', 'asc')
            ->orderBy('created_at', 'asc')
            ->first();

        if (!$defaultList) {
            $defaultList = ManagementTaskList::create([
                'name' => 'Allgemein',
                'icon' => 'clipboard-document-list',
                'color' => '#C5A059',
                'position' => 0,
            ]);
        }

        $displayLabel = $greetingName !== '' && $greetingName !== $callerName
            ? "{$greetingName} ({$callerName})"
            : $callerName;

        $prefix = $isSimulation ? '📞 [Test-Anruf] ' : '📞 Anruf von ';
        $taskTitle = Str::limit($prefix . $displayLabel . ': ' . $summary, 240, '...');
        $priority = ($urgency === 'hoch' || $callbackRequested) ? 'hoch' : $urgency;

        $timestamp = now()->format('d.m.Y H:i');
        $aiPlanLines = [
            "Aufgenommen von Funkira (Telefon-Assistent) am {$timestamp} Uhr.",
            "Anrufer: {$displayLabel}",
            "Dringlichkeit: " . ucfirst($urgency),
            "Rückruf gewünscht: " . ($callbackRequested ? 'Ja' : 'Nein'),
            "Anliegen: {$summary}",
        ];

        $task = ManagementTask::create([
            'task_list_id' => $defaultList->id,
            'title' => $taskTitle,
            'is_completed' => false,
            'is_archived' => false,
            'position' => 0,
            'priority' => $priority,
            'ai_plan' => implode("\n", $aiPlanLines),
        ]);

        // 3. Optionaler Eintrag im Kontakt-Gedächtnis (nur bei echten Anrufen, kein Test-Spam)
        if (!$isSimulation && !empty($contactId) && Str::isUuid((string) $contactId)) {
            $contact = ManagementContact::find($contactId);
            if ($contact) {
                $existingFacts = trim((string) ($contact->ai_learned_facts ?? ''));
                $entry = "[{$timestamp} Anrufnotiz] {$summary}";
                $contact->ai_learned_facts = trim($existingFacts . "\n" . $entry);
                $contact->save();
            }
        }

        $completedPayload = [
            'call_id' => $callId,
            'is_simulation' => $isSimulation,
            'caller_name' => $displayLabel,
            'summary' => $summary,
            'urgency' => $urgency,
            'callback_requested' => $callbackRequested,
            'task_id' => $task->id,
            'completed_at' => now()->toIso8601String(),
        ];

        Cache::put(self::CACHE_LAST_SUMMARY, $completedPayload, now()->addMinutes(30));

        return $completedPayload;
    }
}
