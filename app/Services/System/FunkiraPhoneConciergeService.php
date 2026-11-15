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
    private const CACHE_DIVERTED_WHITELIST = 'funkira_phone_concierge_diverted_v1';

    /**
     * Normalisiert deutsche und internationale Telefonnummern (inkl. VoWiFi/IMS SIP-URIs) für sicheren Whitelist-Abgleich.
     * Beispiel: "+49 176 57793016", "0176-57793016" und "sip:+4917657793016@ims..." -> "4917657793016".
     */
    public static function normalizePhoneDigits(?string $raw): string
    {
        if (empty($raw)) {
            return '';
        }

        $decoded = str_ireplace('%2B', '+', trim((string) $raw));
        $decoded = preg_replace('/^(?:tel:|sips?:)/i', '', $decoded) ?? $decoded;
        $decoded = explode('@', $decoded)[0] ?? $decoded;

        // Falls mehrere vollständige Nummern durch ";", "," oder "/" getrennt sind (beide Seiten >= 7 Ziffern), erste nehmen.
        // Deutsche Vorwahl-Schreibweisen wie "0176 / 57793016" oder "05371 / 123456" bleiben vollständig erhalten.
        $firstClause = trim(preg_split('/[;,]/', $decoded)[0] ?? $decoded);
        $slashParts = explode('/', $firstClause);
        if (count($slashParts) >= 2
            && strlen(preg_replace('/\D+/', '', $slashParts[0]) ?? '') >= 7
            && strlen(preg_replace('/\D+/', '', $slashParts[1]) ?? '') >= 7
        ) {
            $trimmed = trim($slashParts[0]);
        } else {
            $trimmed = $firstClause;
        }
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
        $hasWhitelistCol = \Illuminate\Support\Facades\Schema::hasTable('management_contacts')
            && \Illuminate\Support\Facades\Schema::hasColumn('management_contacts', 'funkira_call_whitelist');

        $contacts = $hasWhitelistCol
            ? ManagementContact::query()
                ->where('funkira_call_whitelist', true)
                ->orderBy('is_favorite', 'desc')
                ->orderBy('first_name', 'asc')
                ->get()
            : collect();

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
            'gateway_phone' => (string) config('services.twilio.phone_number', '+4953717269023'),
            'whitelist_count' => count($whitelist),
            'whitelist_contacts' => $whitelist,
        ];
    }

    /**
     * Ermittelt die natürliche gesprochene Anrede am Telefon (z. B. "Papa" für Jan Steinhauer, aber "Philip" statt "Liebster Mensch" für Philip Goik).
     */
    public static function resolveSpokenGreetingName(ManagementContact $contact): string
    {
        $nickFirst = trim(explode(',', explode('/', (string) ($contact->nickname ?? ''))[0])[0]);
        $directKinship = ['papa', 'mama', 'mum', 'mutter', 'oma', 'opa', 'vati', 'mutti', 'dad', 'mom'];
        if ($nickFirst !== '' && in_array(mb_strtolower($nickFirst), $directKinship, true)) {
            return $nickFirst;
        }

        $first = trim((string) ($contact->first_name ?? ''));
        if ($first !== '') {
            return $first;
        }

        if ($nickFirst !== '') {
            return $nickFirst;
        }

        return trim($contact->full_name) ?: 'Hallo';
    }

    public static function buildOpeningGreetingText(string $greetingName): string
    {
        $who = trim($greetingName) !== '' ? trim($greetingName) : 'du';
        return "Hallo {$who}, hier ist Funkira, Alinas persönliche Assistentin. Alina kann gerade leider nicht persönlich ans Telefon gehen, aber sag mir sehr gerne direkt, worum es geht, ich gebe ihr sofort Bescheid!";
    }

    public static function preWarmGreetingAudio(string $greetingName): ?string
    {
        try {
            return \App\Services\AI\GeminiTtsService::synthesizeWav(
                self::buildOpeningGreetingText($greetingName),
                'Aoede'
            );
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * Prüft eingehende/umgeleitete Twilio-Anrufe strikt gegen Alinas Whitelist (inkl. Mobilfunk-Weiterleitungs-Fallback).
     */
    public static function matchIncomingTwilioCaller(?string $from, ?string $caller = null, ?string $forwardedFrom = null): ?ManagementContact
    {
        $direct = self::matchWhitelistedCaller($from);
        if ($direct) {
            return $direct;
        }

        if (!empty($caller) && $caller !== $from) {
            $byCaller = self::matchWhitelistedCaller($caller);
            if ($byCaller) {
                return $byCaller;
            }
        }

        // Falls ein Mobilfunknetz bei Rufumleitung (SRS/CLIP) Alinas eigene Handynummer als "From" setzt,
        // nutze ausschließlich ein frisches (< 55s) vom Pixel vor-signalisiertes Whitelist-Weiterleitungs-Token.
        $fromNorm = self::normalizePhoneDigits($from);
        $ownerNorm = self::normalizePhoneDigits((string) (function_exists('shop_setting') ? shop_setting('owner_phone', '+49 1590 1966864') : '+49 1590 1966864'));
        $defaultAlinaNorm = '4915901966864';
        if ($fromNorm !== '' && ($fromNorm === $ownerNorm || $fromNorm === $defaultAlinaNorm)) {
            if (!empty($forwardedFrom) && $forwardedFrom !== $from) {
                $byForwarded = self::matchWhitelistedCaller($forwardedFrom);
                if ($byForwarded) {
                    return $byForwarded;
                }
            }
            $diverted = Cache::get(self::CACHE_DIVERTED_WHITELIST);
            if (is_array($diverted) && !empty($diverted['contact_id']) && Str::isUuid((string) $diverted['contact_id'])) {
                $cachedContact = ManagementContact::find($diverted['contact_id']);
                if ($cachedContact && $cachedContact->funkira_call_whitelist) {
                    return $cachedContact;
                }
            }
        }

        return null;
    }

    /**
     * Sucht einen freigegebenen Whitelist-Kontakt anhand einer eingehenden Rufnummer oder Kontakt-ID.
     */
    public static function matchWhitelistedCaller(?string $incomingNumber, ?string $contactId = null): ?ManagementContact
    {
        if (!\Illuminate\Support\Facades\Schema::hasTable('management_contacts')
            || !\Illuminate\Support\Facades\Schema::hasColumn('management_contacts', 'funkira_call_whitelist')) {
            return null;
        }

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

        if (!$isSimulation && $matchedContact && $matchedContact->funkira_call_whitelist) {
            $spokenName = self::resolveSpokenGreetingName($matchedContact);
            Cache::put(self::CACHE_DIVERTED_WHITELIST, [
                'contact_id' => $matchedContact->id,
                'spoken_name' => $spokenName,
                'signaled_at' => now()->timestamp,
            ], now()->addSeconds(55));
            self::preWarmGreetingAudio($spokenName);
        }

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
        $callerPhone = trim((string) ($data['caller_phone'] ?? ($active['caller_phone'] ?? '')));
        $handledBy = strtolower(trim((string) ($data['handled_by'] ?? '')));
        if ((empty($contactId) || !Str::isUuid((string) $contactId)) && $callerPhone !== '') {
            $matchedByPhone = self::matchWhitelistedCaller($callerPhone, null);
            if ($matchedByPhone) {
                $contactId = $matchedByPhone->id;
            }
        }
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

        $isFallbackStub = (bool) ($data['is_fallback_stub'] ?? false);
        $dedupKey = 'funkira_concierge_completed_' . md5($callId);
        $existingCompleted = Cache::get($dedupKey);
        if (is_array($existingCompleted)) {
            // Falls zuerst ein kurzer Status-Fallback ankam und kurz darauf das echte KI-Transkript eintrifft, Aufgabe aktualisieren!
            if (!empty($existingCompleted['is_fallback_stub']) && !$isFallbackStub && !empty($existingCompleted['task_id'])) {
                $existingTask = ManagementTask::find($existingCompleted['task_id']);
                if ($existingTask && $summary !== '') {
                    $displayLabelUpd = $greetingName !== '' && $greetingName !== $callerName
                        ? "{$greetingName} ({$callerName})"
                        : $callerName;
                    $prefixUpd = $isSimulation ? '📞 [Test-Anruf] ' : '📞 Anruf von ';
                    $existingTask->title = Str::limit($prefixUpd . $displayLabelUpd . ': ' . $summary, 240, '...');
                    $existingTask->priority = ($urgency === 'hoch' || $callbackRequested) ? 'hoch' : $urgency;
                    $existingTask->ai_plan = implode("\n", [
                        "Aufgenommen von Funkira (Telefon-Assistent) am " . now()->format('d.m.Y H:i') . " Uhr.",
                        "Anrufer: {$displayLabelUpd}",
                        "Dringlichkeit: " . ucfirst($urgency),
                        "Rückruf gewünscht: " . ($callbackRequested ? 'Ja' : 'Nein'),
                        "Anliegen: {$summary}",
                    ]);
                    $existingTask->save();
                    $existingCompleted['summary'] = $summary;
                    $existingCompleted['is_fallback_stub'] = false;
                    Cache::put($dedupKey, $existingCompleted, now()->addMinutes(30));
                }
            }
            return $existingCompleted;
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
        $sourceLabel = match ($handledBy) {
            'android_secretary' => 'Smartphone-Sekretärin',
            'twilio_secretary' => 'Telefon-Sekretärin',
            default => 'Telefon-Assistent',
        };
        $aiPlanLines = [
            "Aufgenommen von Funkira ({$sourceLabel}) am {$timestamp} Uhr.",
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
            'is_fallback_stub' => $isFallbackStub,
            'caller_name' => $displayLabel,
            'summary' => $summary,
            'urgency' => $urgency,
            'callback_requested' => $callbackRequested,
            'task_id' => $task->id,
            'completed_at' => now()->toIso8601String(),
        ];

        Cache::put($dedupKey, $completedPayload, now()->addMinutes(30));
        Cache::put(self::CACHE_LAST_SUMMARY, $completedPayload, now()->addMinutes(30));

        if (!$isSimulation) {
            try {
                resolve(\App\Services\FirebaseService::class)->sendToAdmins(
                    "📞 Funkira · {$displayLabel}",
                    $summary,
                    [
                        'type' => 'phone_secretary',
                        'task_id' => (string) $task->id,
                        'call_id' => $callId,
                    ]
                );
            } catch (\Throwable $pushEx) {
                // Push ist Best-Effort und blockiert niemals die Dashboard-Aufgabe
            }
        }

        return $completedPayload;
    }
}
