<?php

namespace App\Services\System;

use App\Events\SupportTelephonyUpdated;
use App\Events\TicketMessageSent;
use App\Models\Ai\AiAgent;
use App\Models\Ai\AiDepartment;
use App\Models\Ai\AiUniverseTicket;
use App\Models\Ai\AiUniverseTicketMessage;
use App\Models\Customer\Customer;
use App\Models\Customer\CustomerProfile;
use App\Models\Management\ManagementCalendarEvent;
use App\Models\Management\ManagementContact;
use App\Models\Management\ManagementTask;
use App\Models\Management\ManagementTaskList;
use App\Models\Order\OrderOrder;
use App\Models\Support\SupportContactRequest;
use App\Models\Support\SupportContactRequestMessage;
use App\Models\Support\SupportTicket;
use App\Models\Support\SupportTicketMessage;
use App\Models\SupportTelephonyCall;
use App\Models\System\SystemSetting;
use App\Services\AI\FunkiraPresenceContext;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class FunkiraPhoneConciergeService
{
    public const SETTING_ENABLED = 'funkira_phone_concierge_enabled';
    public const SETTING_RING_DELAY = 'funkira_phone_concierge_ring_delay';
    public const SETTING_CUSTOMER_SERVICE_ENABLED = 'funkira_phone_customer_service_enabled';

    private const CACHE_ACTIVE_CALL = 'funkira_phone_concierge_active_call_v1';
    private const CACHE_LAST_SUMMARY = 'funkira_phone_concierge_last_summary_v1';
    private const CACHE_DIVERTED_WHITELIST = 'funkira_phone_concierge_diverted_v1';

    /**
     * Bereinigt KI-/Anrufertexte zuverlässig von Steuerzeichen und Gedankenstrichen.
     */
    public static function sanitizeCleanText(?string $raw, int $maxLength = 1000): string
    {
        $clean = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]+/u', ' ', (string) ($raw ?? '')) ?? '';
        $clean = str_replace([' – ', ' — ', '–', '—'], [', ', ', ', '-', '-'], $clean);
        $clean = trim(preg_replace('/\s{2,}/u', ' ', $clean) ?? $clean);

        return $maxLength > 0 ? Str::limit($clean, $maxLength, '') : $clean;
    }

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
            $baseNormalized = ltrim($digits, '0');
        } elseif (str_starts_with($digits, '00') && strlen($digits) > 4) {
            $baseNormalized = substr($digits, 2);
        } elseif (str_starts_with($digits, '0') && strlen($digits) >= 7) {
            $baseNormalized = '49' . substr($digits, 1);
        } else {
            $baseNormalized = $digits;
        }

        if (str_starts_with($baseNormalized, '490') && strlen($baseNormalized) >= 10) {
            return '49' . substr($baseNormalized, 3);
        }

        return $baseNormalized;
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
        return in_array(strtolower(trim((string) $val)), ['1', 'true', 'yes', 'on'], true);
    }

    public static function isCustomerServiceEnabled(): bool
    {
        $val = SystemSetting::get(self::SETTING_CUSTOMER_SERVICE_ENABLED, '1');
        return in_array(strtolower(trim((string) $val)), ['1', 'true', 'yes', 'on'], true);
    }

    public static function getRingDelaySeconds(): int
    {
        $val = (int) SystemSetting::get(self::SETTING_RING_DELAY, '12');
        return max(0, min(30, $val));
    }

    public static function updateConfig(?bool $enabled = null, ?int $ringDelaySeconds = null, ?bool $customerServiceEnabled = null): array
    {
        if ($enabled !== null) {
            SystemSetting::set(self::SETTING_ENABLED, $enabled ? '1' : '0');
        }

        if ($ringDelaySeconds !== null) {
            $clamped = max(0, min(30, $ringDelaySeconds));
            SystemSetting::set(self::SETTING_RING_DELAY, (string) $clamped);
        }

        if ($customerServiceEnabled !== null) {
            SystemSetting::set(self::SETTING_CUSTOMER_SERVICE_ENABLED, $customerServiceEnabled ? '1' : '0');
        }

        return self::getConfigPayload();
    }

    public static function getConfigPayload(): array
    {
        $hasWhitelistCol = Schema::hasTable('management_contacts')
            && Schema::hasColumn('management_contacts', 'funkira_call_whitelist');

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
            'customer_service_enabled' => self::isCustomerServiceEnabled(),
            'ring_delay_seconds' => self::getRingDelaySeconds(),
            'gateway_phone' => (string) config('services.twilio.phone_number', '+4953717269023'),
            'whitelist_count' => count($whitelist),
            'whitelist_contacts' => $whitelist,
            'recent_calls' => self::getRecentSecretaryCalls(15),
        ];
    }

    public static function isOwnerPhone(?string $rawPhone): bool
    {
        $norm = self::normalizePhoneDigits($rawPhone);
        if (strlen($norm) < 7) {
            return false;
        }

        $ownerNorm = self::normalizePhoneDigits((string) (function_exists('shop_setting') ? shop_setting('owner_phone', '+49 1590 1966864') : '+49 1590 1966864'));
        $defaultAlinaNorm = '4915901966864';

        return ($ownerNorm !== '' && $norm === $ownerNorm) || $norm === $defaultAlinaNorm;
    }

    /**
     * Sucht serverseitig und ohne PII-Ausgabe am Telefon, ob die Rufnummer zu einem Shop-Kundenkonto gehört.
     *
     * @return array{customer_id: string|null, source: string|null}
     */
    public static function matchCustomerContextByPhone(?string $rawPhone): array
    {
        $incomingNorm = self::normalizePhoneDigits($rawPhone);
        $incomingSuffix = self::extractMatchSuffix($rawPhone);
        if (strlen($incomingNorm) < 7 && strlen($incomingSuffix) < 7) {
            return ['customer_id' => null, 'source' => null];
        }

        try {
            if (!Schema::hasTable('customer_profiles') || !Schema::hasColumn('customer_profiles', 'phone_number')) {
                return ['customer_id' => null, 'source' => null];
            }

            $profiles = CustomerProfile::query()
                ->whereNotNull('phone_number')
                ->where('phone_number', '!=', '')
                ->select(['customer_id', 'phone_number'])
                ->limit(1000)
                ->get();

            $suffixCandidate = null;
            foreach ($profiles as $profile) {
                $cid = (string) ($profile->customer_id ?? '');
                if ($cid === '' || !Str::isUuid($cid)) {
                    continue;
                }
                $pNorm = self::normalizePhoneDigits($profile->phone_number);
                $pSuffix = self::extractMatchSuffix($profile->phone_number);
                if ($pNorm !== '' && $pNorm === $incomingNorm) {
                    if (Customer::where('id', $cid)->exists()) {
                        return ['customer_id' => $cid, 'source' => 'customer_profile'];
                    }
                }
                if ($suffixCandidate === null && strlen($pSuffix) >= 8 && $pSuffix === $incomingSuffix) {
                    $suffixCandidate = $cid;
                }
            }

            if ($suffixCandidate !== null && Customer::where('id', $suffixCandidate)->exists()) {
                return ['customer_id' => $suffixCandidate, 'source' => 'customer_profile_suffix'];
            }

            if (Schema::hasTable('order_orders')) {
                $orders = OrderOrder::query()
                    ->whereNotNull('customer_id')
                    ->orderByDesc('created_at')
                    ->select(['customer_id', 'billing_address', 'shipping_address'])
                    ->limit(300)
                    ->get();

                $orderSuffixCandidate = null;
                foreach ($orders as $order) {
                    $cid = (string) ($order->customer_id ?? '');
                    if ($cid === '' || !Str::isUuid($cid)) {
                        continue;
                    }
                    $phones = [];
                    if (is_array($order->billing_address) && !empty($order->billing_address['phone'])) {
                        $phones[] = (string) $order->billing_address['phone'];
                    }
                    if (is_array($order->shipping_address) && !empty($order->shipping_address['phone'])) {
                        $phones[] = (string) $order->shipping_address['phone'];
                    }
                    foreach ($phones as $ordPhone) {
                        $oNorm = self::normalizePhoneDigits($ordPhone);
                        $oSuffix = self::extractMatchSuffix($ordPhone);
                        if ($oNorm !== '' && $oNorm === $incomingNorm && Customer::where('id', $cid)->exists()) {
                            return ['customer_id' => $cid, 'source' => 'order_address'];
                        }
                        if ($orderSuffixCandidate === null && strlen($oSuffix) >= 8 && $oSuffix === $incomingSuffix) {
                            $orderSuffixCandidate = $cid;
                        }
                    }
                }
                if ($orderSuffixCandidate !== null && Customer::where('id', $orderSuffixCandidate)->exists()) {
                    return ['customer_id' => $orderSuffixCandidate, 'source' => 'order_address_suffix'];
                }
            }
        } catch (\Throwable $e) {
            // Niemals einen eingehenden Anruf durch einen Lookup-Fehler blockieren
        }

        return ['customer_id' => null, 'source' => null];
    }

    /**
     * Erzeugt eine streng anonymisierte Übersicht belegter Kalender-Zeitfenster der nächsten 7 Tage,
     * damit Funkira am Telefon Terminvorschläge abgleichen kann, ohne private Termintitel preiszugeben.
     */
    public static function buildSafeCalendarSlotsForSecretary(): string
    {
        try {
            if (!\Illuminate\Support\Facades\Schema::hasTable('management_calendar_events')) {
                return 'Keine geblockten Termine eingetragen.';
            }

            $now = now();
            $end = now()->addDays(7)->endOfDay();
            $events = ManagementCalendarEvent::query()
                ->where('start_date', '<=', $end)
                ->where(function ($q) use ($now) {
                    $q->where('end_date', '>=', $now)
                        ->orWhere(function ($q2) use ($now) {
                            $q2->whereNull('end_date')->where('start_date', '>=', $now->copy()->startOfDay());
                        });
                })
                ->orderBy('start_date', 'asc')
                ->limit(15)
                ->get();

            if ($events->isEmpty()) {
                return 'In den nächsten 7 Tagen sind keine festen Sperrzeiten eingetragen.';
            }

            $slots = [];
            foreach ($events as $ev) {
                $s = Carbon::parse($ev->start_date);
                if ($ev->is_all_day) {
                    $slots[] = $s->format('d.m.') . ' ganztägig belegt';
                } else {
                    $e = $ev->end_date ? Carbon::parse($ev->end_date) : $s->copy()->addHour();
                    $slots[] = $s->format('d.m. H:i') . '-' . $e->format('H:i') . ' belegt';
                }
            }

            return implode('; ', $slots);
        } catch (\Throwable $e) {
            return 'Keine Kalenderdaten verfügbar.';
        }
    }

    /**
     * Liefert die letzten abgeschlossenen Gespräche der Telefon-Sekretärin inkl. Besprochenem, Rahmenbedingungen und Aufgabe.
     */
    public static function getRecentSecretaryCalls(int $limit = 10): array
    {
        try {
            if (!\Illuminate\Support\Facades\Schema::hasTable('support_telephony_calls')) {
                return [];
            }

            $rows = SupportTelephonyCall::query()
                ->where('status', 'completed')
                ->where(function ($q) {
                    $q->where('objective', 'like', 'Funkira Telefon-Sekretärin:%')
                        ->orWhere('objective', 'like', 'Funkira Kundenservice:%');
                })
                ->orderByDesc('id')
                ->limit(max(1, min(25, $limit)))
                ->get();

            $todayStr = now()->format('Y-m-d');
            $yesterdayStr = now()->subDay()->format('Y-m-d');

            return $rows->map(function (SupportTelephonyCall $call) use ($todayStr, $yesterdayStr) {
                $meta = is_string($call->next_steps)
                    ? (json_decode($call->next_steps, true) ?: [])
                    : (is_array($call->next_steps) ? $call->next_steps : []);

                $transcriptArr = is_string($call->transcript)
                    ? (json_decode($call->transcript, true) ?: [])
                    : (is_array($call->transcript) ? $call->transcript : []);

                $rahmen = [];
                foreach (($meta['rahmenbedingungen'] ?? []) as $item) {
                    $clean = trim((string) $item);
                    if ($clean !== '') {
                        $rahmen[] = $clean;
                    }
                }

                $summary = trim((string) ($call->summary ?? ''));
                $actionRequired = trim((string) ($meta['action_required'] ?? ''));
                if ($actionRequired === '' && !empty($meta['steps'][0])) {
                    $actionRequired = trim((string) $meta['steps'][0]);
                }
                if ($actionRequired === '') {
                    $actionRequired = ($meta['callback_requested'] ?? true)
                        ? 'Bei Gelegenheit zurückrufen.'
                        : 'Zur Kenntnis nehmen.';
                }

                $dt = $call->created_at ? Carbon::parse($call->created_at)->timezone(config('app.timezone', 'Europe/Berlin')) : now();
                $dayStr = $dt->format('Y-m-d');
                if ($dayStr === $todayStr) {
                    $createdLabel = 'Heute, ' . $dt->format('H:i');
                } elseif ($dayStr === $yesterdayStr) {
                    $createdLabel = 'Gestern, ' . $dt->format('H:i');
                } else {
                    $createdLabel = $dt->format('d.m.Y, H:i');
                }

                return [
                    'id' => (string) $call->id,
                    'client_call_id' => !empty($meta['client_call_id']) ? (string) $meta['client_call_id'] : null,
                    'caller_name' => (string) ($call->contact_name ?: 'Kontakt'),
                    'caller_phone' => (string) ($call->phone ?: ''),
                    'status' => (string) ($call->status ?: 'completed'),
                    'created_at_label' => $createdLabel,
                    'duration_seconds' => (int) ($call->duration_seconds ?? 0),
                    'summary' => $summary !== '' ? $summary : 'Telefonat mit Funkira geführt.',
                    'rahmenbedingungen' => array_values($rahmen),
                    'action_required' => $actionRequired,
                    'urgency' => (string) ($meta['urgency'] ?? 'mittel'),
                    'callback_requested' => (bool) ($meta['callback_requested'] ?? true),
                    'transcript' => array_values(array_map('strval', array_slice($transcriptArr, 0, 120))),
                    'handled_by' => (string) ($meta['handled_by'] ?? ($meta['mode'] ?? 'twilio_secretary')),
                    'call_mode' => (string) ($meta['call_mode'] ?? ($meta['mode'] ?? 'inbound_secretary')),
                    'verification' => is_array($meta['verification'] ?? null) ? $meta['verification'] : null,
                    'task_id' => !empty($meta['task_id']) ? (string) $meta['task_id'] : null,
                    'ticket_id' => !empty($meta['ticket_id']) ? (string) $meta['ticket_id'] : null,
                    'ticket_number' => !empty($meta['ticket_number']) ? (string) $meta['ticket_number'] : null,
                    'support_ticket_id' => !empty($meta['support_ticket_id']) ? (string) $meta['support_ticket_id'] : null,
                    'support_ticket_type' => !empty($meta['support_ticket_type']) ? (string) $meta['support_ticket_type'] : null,
                    'support_ticket_number' => !empty($meta['support_ticket_number']) ? (string) $meta['support_ticket_number'] : null,
                    'category' => !empty($meta['category']) ? (string) $meta['category'] : null,
                    'caller_email' => !empty($meta['caller_email']) ? (string) $meta['caller_email'] : null,
                    'order_number' => !empty($meta['order_number']) ? (string) $meta['order_number'] : null,
                    'suggested_reply' => !empty($meta['suggested_reply']) ? (string) $meta['suggested_reply'] : null,
                    'last_reply' => !empty($meta['last_reply']) ? (string) $meta['last_reply'] : null,
                    'last_reply_at' => !empty($meta['last_reply_at']) ? (string) $meta['last_reply_at'] : null,
                    'resolved' => (bool) ($meta['resolved'] ?? false),
                ];
            })->values()->all();
        } catch (\Throwable $e) {
            return [];
        }
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
            $diverted = RedisResilienceService::safeCacheGet(self::CACHE_DIVERTED_WHITELIST);
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

        $existing = RedisResilienceService::safeCacheGet(self::CACHE_ACTIVE_CALL);
        $callId = trim((string) ($data['call_id'] ?? (is_array($existing) ? ($existing['call_id'] ?? '') : '')));
        if ($callId === '') {
            $callId = (string) Str::uuid();
        }

        if (in_array($state, ['cancelled', 'ended'], true)) {
            if (is_array($existing) && ($existing['call_id'] ?? '') === $callId) {
                $existing['state'] = $state;
                $existing['hangup_requested'] = true;
                $existing['updated_at'] = now()->toIso8601String();
                RedisResilienceService::safeCachePut(self::CACHE_ACTIVE_CALL, $existing, now()->addSeconds(25));
                return $existing;
            }
            RedisResilienceService::safeCacheForget(self::CACHE_ACTIVE_CALL);
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

        RedisResilienceService::safeCachePut(self::CACHE_ACTIVE_CALL, $session, now()->addMinutes(4));

        if (!$isSimulation && $matchedContact && $matchedContact->funkira_call_whitelist) {
            $spokenName = self::resolveSpokenGreetingName($matchedContact);
            RedisResilienceService::safeCachePut(self::CACHE_DIVERTED_WHITELIST, [
                'contact_id' => $matchedContact->id,
                'spoken_name' => $spokenName,
                'signaled_at' => now()->timestamp,
            ], now()->addSeconds(55));
        }

        return $session;
    }

    public static function pollState(): array
    {
        $active = RedisResilienceService::safeCacheGet(self::CACHE_ACTIVE_CALL);
        $lastSummary = RedisResilienceService::safeCacheGet(self::CACHE_LAST_SUMMARY);

        return [
            'enabled' => self::isEnabled(),
            'customer_service_enabled' => self::isCustomerServiceEnabled(),
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
        $active = RedisResilienceService::safeCacheGet(self::CACHE_ACTIVE_CALL);
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
        $callerName = self::sanitizeCleanText((string) ($data['caller_name'] ?? ($active['caller_name'] ?? 'Kontakt')), 160);
        if ($callerName === '') {
            $callerName = 'Kontakt';
        }
        $greetingName = self::sanitizeCleanText((string) ($data['greeting_name'] ?? ($active['greeting_name'] ?? $callerName)), 120);
        if ($greetingName === '') {
            $greetingName = $callerName;
        }
        $summary = self::sanitizeCleanText((string) ($data['summary'] ?? ''), 1900);
        $actionRequired = self::sanitizeCleanText((string) ($data['action_required'] ?? ''), 480);
        $rawRahmen = is_array($data['rahmenbedingungen'] ?? null) ? $data['rahmenbedingungen'] : [];
        $rahmenbedingungen = [];
        foreach (array_slice($rawRahmen, 0, 30) as $rb) {
            $cleanRb = self::sanitizeCleanText((string) $rb, 300);
            if ($cleanRb !== '') {
                $rahmenbedingungen[] = $cleanRb;
            }
        }
        $durationSeconds = max(0, (int) ($data['duration_seconds'] ?? 0));
        $rawTranscript = is_array($data['transcript'] ?? null) ? $data['transcript'] : [];
        $transcriptLines = [];
        foreach (array_slice($rawTranscript, 0, 120) as $line) {
            $cleanLine = self::sanitizeCleanText((string) $line, 2000);
            if ($cleanLine !== '') {
                $transcriptLines[] = $cleanLine;
            }
        }
        $verification = is_array($data['verification'] ?? null) ? $data['verification'] : null;
        $urgency = strtolower(trim((string) ($data['urgency'] ?? 'mittel')));
        if (!in_array($urgency, ['hoch', 'mittel', 'niedrig'], true)) {
            $urgency = 'mittel';
        }
        $callbackRequested = (bool) ($data['callback_requested'] ?? true);

        $rawCallMode = strtolower(trim((string) ($data['call_mode'] ?? ($active['call_mode'] ?? ''))));
        $effectiveHandledBy = $handledBy !== '' ? $handledBy : ($rawCallMode === 'inbound_customer_service' ? 'twilio_customer_service' : 'android_secretary');
        $isCustomerService = $rawCallMode === 'inbound_customer_service' || str_contains($effectiveHandledBy, 'customer_service');
        $effectiveCallMode = $isCustomerService ? 'inbound_customer_service' : 'inbound_secretary';

        $rawEmail = strtolower(trim((string) ($data['caller_email'] ?? '')));
        $callerEmail = ($rawEmail !== '' && filter_var($rawEmail, FILTER_VALIDATE_EMAIL)) ? Str::limit($rawEmail, 180, '') : '';
        $rawOrderNo = trim((string) ($data['order_number'] ?? ''));
        $orderNumber = ($rawOrderNo !== '' && preg_match('/^[A-Za-z0-9\-_]{3,40}$/', $rawOrderNo)) ? $rawOrderNo : '';
        $rawCategory = strtolower(trim((string) ($data['category'] ?? 'support')));
        $category = in_array($rawCategory, ['support', 'return', 'order', 'product', 'general', 'spam'], true) ? $rawCategory : 'support';
        $isSpam = $isCustomerService && ((bool) ($data['is_spam'] ?? false) || $category === 'spam');
        if ($category === 'spam') {
            $category = 'general';
        }
        $ticketSubject = self::sanitizeCleanText((string) ($data['ticket_subject'] ?? ''), 180);
        $suggestedReply = $isSpam ? '' : self::sanitizeCleanText((string) ($data['suggested_reply'] ?? ''), 1500);

        $customerId = !empty($data['customer_id']) && Str::isUuid((string) $data['customer_id'])
            ? (string) $data['customer_id']
            : (!empty($active['customer_id']) && Str::isUuid((string) $active['customer_id']) ? (string) $active['customer_id'] : null);
        if ($isCustomerService && $customerId === null && $callerEmail !== '' && Schema::hasTable('customers')) {
            $matchedCustomerId = Customer::where('email', $callerEmail)->value('id');
            if ($matchedCustomerId) {
                $customerId = (string) $matchedCustomerId;
            }
        }
        if ($isCustomerService && $customerId === null && $callerPhone !== '') {
            $phoneLookup = self::matchCustomerContextByPhone($callerPhone);
            if (!empty($phoneLookup['customer_id'])) {
                $customerId = (string) $phoneLookup['customer_id'];
            }
        }

        if ($summary === '') {
            $summary = $isSpam
                ? 'Unerwünschter Werbe- oder Robocall automatisch herausgefiltert.'
                : ($isCustomerService
                    ? 'Kunde hat angerufen und um Bearbeitung seines Anliegens gebeten.'
                    : 'Hat angerufen und bittet bei Gelegenheit um kurzen Rückruf.');
        }
        if ($actionRequired === '') {
            $actionRequired = $isSpam
                ? 'Keine Aktion nötig (Spam gefiltert).'
                : ($callbackRequested
                    ? ($isCustomerService ? 'Kundenanliegen prüfen und zeitnah zurückrufen.' : "{$greetingName} zurückrufen.")
                    : 'Zur Kenntnis nehmen.');
        }

        $sourceLabel = match ($effectiveHandledBy) {
            'android_secretary' => 'Smartphone-Sekretärin',
            'twilio_secretary' => 'Telefon-Sekretärin',
            'twilio_customer_service' => 'KI-Telefon-Kundenservice',
            'android_customer_service' => 'Smartphone-Kundenservice',
            default => 'Telefon-Assistent',
        };
        $displayLabel = $isCustomerService
            ? ($callerName !== 'Kontakt' && $callerName !== 'Kundenanruf'
                ? $callerName
                : ($callerPhone !== '' ? "Kundenanruf ({$callerPhone})" : 'Telefon-Kunde'))
            : ($greetingName !== '' && $greetingName !== $callerName
                ? "{$greetingName} ({$callerName})"
                : $callerName);

        $matchedContactForScope = (!$isCustomerService && !empty($contactId) && Str::isUuid((string) $contactId))
            ? ManagementContact::find($contactId)
            : null;
        $isBusinessCall = $isCustomerService
            ? true
            : self::classifyBusinessCall($matchedContactForScope, $data['relation_type'] ?? ($active['relation_type'] ?? ''));

        $persistAppCallRecord = function (
            bool $fallbackFlag,
            ?string $linkedTaskId = null,
            ?string $linkedTicketId = null,
            ?string $linkedTicketNumber = null,
            ?array $supportRecordMeta = null
        ) use (
            $isSimulation,
            $callId,
            $displayLabel,
            $callerPhone,
            $summary,
            $transcriptLines,
            $durationSeconds,
            $effectiveHandledBy,
            $effectiveCallMode,
            $isCustomerService,
            $rahmenbedingungen,
            $actionRequired,
            $urgency,
            $callbackRequested,
            $verification,
            $category,
            $callerEmail,
            $orderNumber,
            $suggestedReply,
            $isSpam
        ): ?int {
            if ($isSimulation) {
                return null;
            }
            if (!Schema::hasTable('support_telephony_calls')) {
                return null;
            }

            $record = null;
            if (str_starts_with($callId, 'twilio_')) {
                $twilioDbId = (int) substr($callId, 7);
                if ($twilioDbId > 0) {
                    $record = SupportTelephonyCall::find($twilioDbId);
                }
                if (!$record) {
                    return null;
                }
                $existingMeta = is_string($record->next_steps)
                    ? (json_decode($record->next_steps, true) ?: [])
                    : (is_array($record->next_steps) ? $record->next_steps : []);
                $existingMeta['mode'] = $effectiveCallMode;
                $existingMeta['call_mode'] = $effectiveCallMode;
                $existingMeta['handled_by'] = $effectiveHandledBy;
                $existingMeta['is_fallback_stub'] = $fallbackFlag;
                $existingMeta['is_spam'] = $isSpam;
                $existingMeta['client_call_id'] = $callId;
                if ($linkedTaskId !== null) {
                    $existingMeta['task_id'] = $linkedTaskId;
                }
                if ($linkedTicketId !== null) {
                    $existingMeta['ticket_id'] = $linkedTicketId;
                }
                if ($linkedTicketNumber !== null) {
                    $existingMeta['ticket_number'] = $linkedTicketNumber;
                }
                if (is_array($supportRecordMeta)) {
                    $existingMeta['support_ticket_type'] = $supportRecordMeta['type'] ?? ($existingMeta['support_ticket_type'] ?? null);
                    $existingMeta['support_ticket_id'] = $supportRecordMeta['id'] ?? ($existingMeta['support_ticket_id'] ?? null);
                    $existingMeta['support_ticket_number'] = $supportRecordMeta['ticket_number'] ?? ($existingMeta['support_ticket_number'] ?? null);
                }
                if ($isCustomerService) {
                    $existingMeta['category'] = $category;
                    if ($callerEmail !== '') {
                        $existingMeta['caller_email'] = $callerEmail;
                    }
                    if ($orderNumber !== '') {
                        $existingMeta['order_number'] = $orderNumber;
                    }
                    if ($suggestedReply !== '') {
                        $existingMeta['suggested_reply'] = $suggestedReply;
                    }
                }
                if ($isSpam) {
                    $record->status = 'spam_filtered';
                }
                $record->next_steps = json_encode($existingMeta, JSON_UNESCAPED_UNICODE);
                $record->save();

                return (int) $record->id;
            }

            $sidKey = 'app_' . Str::limit($callId, 100, '');
            $record = SupportTelephonyCall::where('twilio_sid', $sidKey)->first();
            $existingMeta = [];
            if (!$record) {
                $record = new SupportTelephonyCall();
                $record->twilio_sid = $sidKey;
            } else {
                $existingMeta = is_string($record->next_steps)
                    ? (json_decode($record->next_steps, true) ?: [])
                    : (is_array($record->next_steps) ? $record->next_steps : []);
            }
            $record->contact_name = Str::limit($displayLabel, 250, '');
            $record->phone = $callerPhone !== '' ? Str::limit($callerPhone, 100, '') : ($record->phone ?? '');
            $record->objective = $isCustomerService
                ? "Funkira Kundenservice: Eingehender Kundenanruf ({$displayLabel})"
                : "Funkira Telefon-Sekretärin: Eingehender Anruf von {$displayLabel}";
            $record->status = $isSpam ? 'spam_filtered' : 'completed';
            $record->duration_seconds = $durationSeconds > 0 ? $durationSeconds : ($record->duration_seconds ?? 0);
            $record->summary = $summary;
            if (!empty($transcriptLines)) {
                $record->transcript = json_encode($transcriptLines, JSON_UNESCAPED_UNICODE);
            } elseif (empty($record->transcript)) {
                $record->transcript = json_encode([], JSON_UNESCAPED_UNICODE);
            }
            $record->next_steps = json_encode([
                'mode' => $effectiveCallMode,
                'call_mode' => $effectiveCallMode,
                'handled_by' => $effectiveHandledBy,
                'client_call_id' => $callId,
                'is_fallback_stub' => $fallbackFlag,
                'is_spam' => $isSpam,
                'rahmenbedingungen' => $rahmenbedingungen,
                'action_required' => $actionRequired,
                'urgency' => $urgency,
                'callback_requested' => $callbackRequested,
                'verification' => $verification,
                'steps' => [$actionRequired],
                'task_id' => $linkedTaskId ?? ($existingMeta['task_id'] ?? null),
                'ticket_id' => $linkedTicketId ?? ($existingMeta['ticket_id'] ?? null),
                'ticket_number' => $linkedTicketNumber ?? ($existingMeta['ticket_number'] ?? null),
                'support_ticket_type' => $supportRecordMeta['type'] ?? ($existingMeta['support_ticket_type'] ?? null),
                'support_ticket_id' => $supportRecordMeta['id'] ?? ($existingMeta['support_ticket_id'] ?? null),
                'support_ticket_number' => $supportRecordMeta['ticket_number'] ?? ($existingMeta['support_ticket_number'] ?? null),
                'category' => $isCustomerService ? $category : ($existingMeta['category'] ?? null),
                'caller_email' => $callerEmail !== '' ? $callerEmail : ($existingMeta['caller_email'] ?? null),
                'order_number' => $orderNumber !== '' ? $orderNumber : ($existingMeta['order_number'] ?? null),
                'suggested_reply' => $suggestedReply !== '' ? $suggestedReply : ($existingMeta['suggested_reply'] ?? null),
                'resolved' => (bool) ($existingMeta['resolved'] ?? false),
            ], JSON_UNESCAPED_UNICODE);
            $record->save();

            return (int) $record->id;
        };

        $buildStructuredAiPlan = function (
            string $label,
            string $srcLabel,
            string $ts,
            ?string $supportNumber = null
        ) use ($summary, $rahmenbedingungen, $actionRequired, $urgency, $callbackRequested, $isCustomerService, $callerPhone, $callerEmail, $orderNumber, $category): string {
            $lines = [
                'Besprochen:',
                $summary,
            ];
            if ($isCustomerService) {
                $metaBits = [];
                if ($supportNumber) {
                    $metaBits[] = "Ticket: {$supportNumber}";
                }
                $metaBits[] = 'Kategorie: ' . ucfirst($category);
                if ($callerPhone !== '') {
                    $metaBits[] = "Tel: {$callerPhone}";
                }
                if ($callerEmail !== '') {
                    $metaBits[] = "E-Mail: {$callerEmail}";
                }
                if ($orderNumber !== '') {
                    $metaBits[] = "Bestellung: {$orderNumber}";
                }
                $lines[] = '';
                $lines[] = 'Kundendaten & Einordnung:';
                $lines[] = implode(' · ', $metaBits);
            }
            if (!empty($rahmenbedingungen)) {
                $lines[] = '';
                $lines[] = 'Rahmenbedingungen:';
                foreach ($rahmenbedingungen as $item) {
                    $lines[] = '✓ ' . $item;
                }
            }
            $lines[] = '';
            $lines[] = 'Für dich zu tun:';
            $lines[] = $actionRequired;
            $lines[] = '';
            $lines[] = "Aufgenommen von Funkira ({$srcLabel}) am {$ts} Uhr · Anrufer: {$label} · Dringlichkeit: " . ucfirst($urgency) . ' · Rückruf: ' . ($callbackRequested ? 'Ja' : 'Nein');

            return implode("\n", $lines);
        };

        $isFallbackStub = (bool) ($data['is_fallback_stub'] ?? false);
        $dedupKey = 'funkira_concierge_completed_' . md5($callId);
        $existingCompleted = RedisResilienceService::safeCacheGet($dedupKey);
        if (!is_array($existingCompleted) && !$isSimulation) {
            $existingCompleted = self::findPersistedCompletedCallPayload($callId);
        }

        if (is_array($existingCompleted)) {
            // Falls zuerst ein kurzer Status-Fallback ankam und kurz darauf das echte KI-Transkript eintrifft,
            // werden Aufgabe, Call-Log, Support-Ticket UND Universum-Ticket nahtlos in-place aktualisiert!
            if (!empty($existingCompleted['is_fallback_stub']) && !$isFallbackStub) {
                $tsUpd = now()->format('d.m.Y H:i');
                $existingTaskId = !empty($existingCompleted['task_id']) ? (string) $existingCompleted['task_id'] : null;

                $supportMetaUpd = (!$isSimulation && $isCustomerService)
                    ? self::syncCustomerSupportRecord([
                        'existing_type' => $existingCompleted['support_ticket_type'] ?? null,
                        'existing_id' => $existingCompleted['support_ticket_id'] ?? null,
                        'customer_id' => $customerId,
                        'caller_name' => $displayLabel,
                        'caller_phone' => $callerPhone,
                        'caller_email' => $callerEmail,
                        'order_number' => $orderNumber,
                        'category' => $category,
                        'ticket_subject' => $ticketSubject,
                        'summary' => $summary,
                        'rahmenbedingungen' => $rahmenbedingungen,
                        'action_required' => $actionRequired,
                        'urgency' => $urgency,
                        'callback_requested' => $callbackRequested,
                        'duration_seconds' => $durationSeconds,
                        'transcript_lines' => $transcriptLines,
                        'call_id' => $callId,
                    ])
                    : null;

                $effectiveSupportNumber = $supportMetaUpd['ticket_number'] ?? ($existingCompleted['support_ticket_number'] ?? null);
                $prefixUpd = $isSimulation
                    ? '📞 [Test-Anruf] '
                    : ($isCustomerService
                        ? ($effectiveSupportNumber ? "📞 [{$effectiveSupportNumber}] " : '📞 Kundenanruf von ')
                        : '📞 Anruf von ');
                $headlineUpd = $actionRequired !== '' && $actionRequired !== 'Zur Kenntnis nehmen.' && !str_contains($summary, $actionRequired)
                    ? "{$summary} ({$actionRequired})"
                    : $summary;

                if ($existingTaskId !== null) {
                    $existingTask = ManagementTask::find($existingTaskId);
                    if ($existingTask && $summary !== '') {
                        $existingTask->title = Str::limit($prefixUpd . $displayLabel . ': ' . $headlineUpd, 240, '...');
                        $existingTask->priority = ($urgency === 'hoch' || $callbackRequested) ? 'hoch' : $urgency;
                        $existingTask->ai_plan = $buildStructuredAiPlan($displayLabel, $sourceLabel, $tsUpd, $effectiveSupportNumber);
                        $existingTask->save();
                    }
                }

                $syncedTicket = self::syncStructuredCallTicket([
                    'call_id' => $callId,
                    'existing_ticket_id' => $existingCompleted['ticket_id'] ?? null,
                    'task_id' => $existingTaskId,
                    'db_call_id' => $existingCompleted['db_call_id'] ?? null,
                    'display_label' => $displayLabel,
                    'caller_phone' => $callerPhone,
                    'caller_email' => $callerEmail,
                    'order_number' => $orderNumber,
                    'category' => $category,
                    'support_ticket_type' => $supportMetaUpd['type'] ?? ($existingCompleted['support_ticket_type'] ?? null),
                    'support_ticket_id' => $supportMetaUpd['id'] ?? ($existingCompleted['support_ticket_id'] ?? null),
                    'support_ticket_number' => $effectiveSupportNumber,
                    'summary' => $summary,
                    'rahmenbedingungen' => $rahmenbedingungen,
                    'action_required' => $actionRequired,
                    'urgency' => $urgency,
                    'callback_requested' => $callbackRequested,
                    'duration_seconds' => $durationSeconds,
                    'handled_by' => $effectiveHandledBy,
                    'source_label' => $sourceLabel,
                    'transcript_lines' => $transcriptLines,
                    'verification' => $verification,
                    'is_business' => $isBusinessCall,
                    'is_fallback_stub' => false,
                ]);

                $dbIdUpd = $persistAppCallRecord(
                    false,
                    $existingTaskId,
                    $syncedTicket?->id ?? ($existingCompleted['ticket_id'] ?? null),
                    $syncedTicket?->ticket_number ?? ($existingCompleted['ticket_number'] ?? null),
                    $supportMetaUpd
                );
                if ($dbIdUpd !== null) {
                    $existingCompleted['db_call_id'] = $dbIdUpd;
                }
                if ($syncedTicket !== null) {
                    $existingCompleted['ticket_id'] = $syncedTicket->id;
                    $existingCompleted['ticket_number'] = $syncedTicket->ticket_number;
                }
                if (is_array($supportMetaUpd)) {
                    $existingCompleted['support_ticket_type'] = $supportMetaUpd['type'];
                    $existingCompleted['support_ticket_id'] = $supportMetaUpd['id'];
                    $existingCompleted['support_ticket_number'] = $supportMetaUpd['ticket_number'];
                }
                $existingCompleted['summary'] = $summary;
                $existingCompleted['rahmenbedingungen'] = $rahmenbedingungen;
                $existingCompleted['action_required'] = $actionRequired;
                $existingCompleted['urgency'] = $urgency;
                $existingCompleted['callback_requested'] = $callbackRequested;
                if ($suggestedReply !== '') {
                    $existingCompleted['suggested_reply'] = $suggestedReply;
                }
                $existingCompleted['is_fallback_stub'] = false;
                RedisResilienceService::safeCachePut($dedupKey, $existingCompleted, now()->addMinutes(30));
                RedisResilienceService::safeCachePut(self::CACHE_LAST_SUMMARY, $existingCompleted, now()->addMinutes(30));
            }

            return $existingCompleted;
        }

        // 1. Aktives Gespräch auf completed + hangup_requested setzen (damit Android sofort auflegt)
        if (is_array($active)) {
            $active['state'] = 'completed';
            $active['hangup_requested'] = true;
            $active['summary'] = $summary;
            $active['updated_at'] = now()->toIso8601String();
            RedisResilienceService::safeCachePut(self::CACHE_ACTIVE_CALL, $active, now()->addSeconds(30));
        }

        // 1b. Stiller Spam-/Werbeanruf-Filter: Archiviert nur im Telefon-Log ohne Ticket, To-Do oder Push!
        if ($isSpam) {
            $spamDbCallId = $persistAppCallRecord($isFallbackStub, null, null, null, null);
            $spamPayload = [
                'call_id' => $callId,
                'db_call_id' => $spamDbCallId,
                'is_simulation' => $isSimulation,
                'is_fallback_stub' => $isFallbackStub,
                'is_spam' => true,
                'call_mode' => $effectiveCallMode,
                'caller_name' => $displayLabel,
                'summary' => $summary,
                'rahmenbedingungen' => $rahmenbedingungen,
                'action_required' => $actionRequired,
                'urgency' => 'niedrig',
                'callback_requested' => false,
                'handled_by' => $effectiveHandledBy,
                'task_id' => null,
                'ticket_id' => null,
                'ticket_number' => null,
                'support_ticket_type' => null,
                'support_ticket_id' => null,
                'support_ticket_number' => null,
                'category' => 'spam',
                'caller_email' => null,
                'order_number' => null,
                'suggested_reply' => null,
                'completed_at' => now()->toIso8601String(),
            ];
            RedisResilienceService::safeCachePut($dedupKey, $spamPayload, now()->addMinutes(30));
            if (!$isSimulation) {
                RealtimeBroadcastService::dispatch(new SupportTelephonyUpdated(), 'FunkiraPhoneConciergeService::completeCallSpam');
            }

            return $spamPayload;
        }

        // 2. Bei Kundenservice-Anruf zuerst strukturiertes Shop-Support-Ticket / Kontaktanfrage anlegen
        $supportMeta = (!$isSimulation && $isCustomerService)
            ? self::syncCustomerSupportRecord([
                'existing_type' => null,
                'existing_id' => null,
                'customer_id' => $customerId,
                'caller_name' => $displayLabel,
                'caller_phone' => $callerPhone,
                'caller_email' => $callerEmail,
                'order_number' => $orderNumber,
                'category' => $category,
                'ticket_subject' => $ticketSubject,
                'summary' => $summary,
                'rahmenbedingungen' => $rahmenbedingungen,
                'action_required' => $actionRequired,
                'urgency' => $urgency,
                'callback_requested' => $callbackRequested,
                'duration_seconds' => $durationSeconds,
                'transcript_lines' => $transcriptLines,
                'call_id' => $callId,
            ])
            : null;
        $supportTicketNumber = $supportMeta['ticket_number'] ?? null;

        // 3. To-Do in Alinas primärer Aufgabenliste auf /admin/dashboard anlegen
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

        $prefix = $isSimulation
            ? '📞 [Test-Anruf] '
            : ($isCustomerService
                ? ($supportTicketNumber ? "📞 [{$supportTicketNumber}] " : '📞 Kundenanruf von ')
                : '📞 Anruf von ');
        $headline = $actionRequired !== '' && $actionRequired !== 'Zur Kenntnis nehmen.' && !str_contains($summary, $actionRequired)
            ? "{$summary} ({$actionRequired})"
            : $summary;
        $taskTitle = Str::limit($prefix . $displayLabel . ': ' . $headline, 240, '...');
        $priority = ($urgency === 'hoch' || $callbackRequested) ? 'hoch' : $urgency;

        $timestamp = now()->format('d.m.Y H:i');

        $task = ManagementTask::create([
            'task_list_id' => $defaultList->id,
            'title' => $taskTitle,
            'is_completed' => false,
            'is_archived' => false,
            'position' => 0,
            'priority' => $priority,
            'ai_plan' => $buildStructuredAiPlan($displayLabel, $sourceLabel, $timestamp, $supportTicketNumber),
        ]);

        if (!$isFallbackStub && $actionRequired !== '' && $actionRequired !== 'Zur Kenntnis nehmen.') {
            ManagementTask::create([
                'task_list_id' => $defaultList->id,
                'parent_id' => $task->id,
                'title' => Str::limit($actionRequired, 220, '...'),
                'is_completed' => false,
                'is_archived' => false,
                'position' => 0,
                'priority' => $priority,
            ]);
        }

        // 4. Strukturiertes Universum-Ticket (/admin/tickets & Funkenwerk App) für echte Anrufe anlegen
        $callTicket = !$isSimulation ? self::syncStructuredCallTicket([
            'call_id' => $callId,
            'existing_ticket_id' => null,
            'task_id' => (string) $task->id,
            'db_call_id' => null,
            'display_label' => $displayLabel,
            'caller_phone' => $callerPhone,
            'caller_email' => $callerEmail,
            'order_number' => $orderNumber,
            'category' => $category,
            'support_ticket_type' => $supportMeta['type'] ?? null,
            'support_ticket_id' => $supportMeta['id'] ?? null,
            'support_ticket_number' => $supportTicketNumber,
            'summary' => $summary,
            'rahmenbedingungen' => $rahmenbedingungen,
            'action_required' => $actionRequired,
            'urgency' => $urgency,
            'callback_requested' => $callbackRequested,
            'duration_seconds' => $durationSeconds,
            'handled_by' => $effectiveHandledBy,
            'source_label' => $sourceLabel,
            'transcript_lines' => $transcriptLines,
            'verification' => $verification,
            'is_business' => $isBusinessCall,
            'is_fallback_stub' => $isFallbackStub,
        ]) : null;

        // 5. Call-Protokoll in support_telephony_calls inkl. Task- & Ticket-Verknüpfung persistieren
        $dbCallId = $persistAppCallRecord(
            $isFallbackStub,
            (string) $task->id,
            $callTicket?->id,
            $callTicket?->ticket_number,
            $supportMeta
        );

        if ($callTicket !== null && $dbCallId !== null) {
            $ap = is_array($callTicket->action_payload) ? $callTicket->action_payload : [];
            $ap['db_call_id'] = $dbCallId;
            $callTicket->action_payload = $ap;
            $callTicket->save();
        }

        // 6. Optionaler Eintrag im Kontakt-Gedächtnis (nur bei echten Whitelist-Anrufen, kein Test-Spam)
        if (!$isSimulation && $matchedContactForScope) {
            $existingFacts = trim((string) ($matchedContactForScope->ai_learned_facts ?? ''));
            $rahmenInline = !empty($rahmenbedingungen) ? (' | Rahmenbedingungen: ' . implode(', ', $rahmenbedingungen)) : '';
            $entry = "[{$timestamp} Anrufnotiz] {$summary}{$rahmenInline} | Zu tun: {$actionRequired}";
            $matchedContactForScope->ai_learned_facts = trim($existingFacts . "\n" . $entry);
            $matchedContactForScope->save();
        }

        $completedPayload = [
            'call_id' => $callId,
            'db_call_id' => $dbCallId,
            'is_simulation' => $isSimulation,
            'is_fallback_stub' => $isFallbackStub,
            'call_mode' => $effectiveCallMode,
            'caller_name' => $displayLabel,
            'summary' => $summary,
            'rahmenbedingungen' => $rahmenbedingungen,
            'action_required' => $actionRequired,
            'urgency' => $urgency,
            'callback_requested' => $callbackRequested,
            'handled_by' => $effectiveHandledBy,
            'task_id' => $task->id,
            'ticket_id' => $callTicket?->id,
            'ticket_number' => $callTicket?->ticket_number,
            'support_ticket_type' => $supportMeta['type'] ?? null,
            'support_ticket_id' => $supportMeta['id'] ?? null,
            'support_ticket_number' => $supportTicketNumber,
            'category' => $isCustomerService ? $category : null,
            'caller_email' => $callerEmail !== '' ? $callerEmail : null,
            'order_number' => $orderNumber !== '' ? $orderNumber : null,
            'suggested_reply' => $suggestedReply !== '' ? $suggestedReply : null,
            'is_spam' => false,
            'completed_at' => now()->toIso8601String(),
        ];

        RedisResilienceService::safeCachePut($dedupKey, $completedPayload, now()->addMinutes(30));
        RedisResilienceService::safeCachePut(self::CACHE_LAST_SUMMARY, $completedPayload, now()->addMinutes(30));

        if (!$isSimulation) {
            RealtimeBroadcastService::dispatch(new SupportTelephonyUpdated(), 'FunkiraPhoneConciergeService::completeCall');

            try {
                $pushTitle = $isCustomerService
                    ? ("📞 Kundenservice · {$displayLabel}" . ($supportTicketNumber ? " [{$supportTicketNumber}]" : ''))
                    : "📞 Funkira · {$displayLabel}";
                $pushBody = $actionRequired !== '' && $actionRequired !== 'Zur Kenntnis nehmen.'
                    ? "{$summary} · Zu tun: {$actionRequired}"
                    : $summary;
                resolve(\App\Services\FirebaseService::class)->sendToAdmins(
                    $pushTitle,
                    Str::limit($pushBody, 220, '...'),
                    [
                        'type' => 'phone_secretary',
                        'call_mode' => $effectiveCallMode,
                        'navigate_to' => 'organizer',
                        'open_subtab' => '7',
                        'task_id' => (string) $task->id,
                        'ticket_id' => (string) ($callTicket?->id ?? ''),
                        'ticket_number' => (string) ($callTicket?->ticket_number ?? ''),
                        'support_ticket_id' => (string) ($supportMeta['id'] ?? ''),
                        'support_ticket_number' => (string) ($supportTicketNumber ?? ''),
                        'call_id' => $callId,
                    ]
                );
            } catch (\Throwable $pushEx) {
                // Push ist Best-Effort und blockiert niemals die Dashboard-Aufgabe
            }
        }

        return $completedPayload;
    }

    /**
     * Bestimmt für die strikte Rollen-Isolation, ob ein Telefonat gewerblich (true) oder familiär/privat (false) ist.
     */
    public static function classifyBusinessCall(?ManagementContact $contact, ?string $rawRelation = null): bool
    {
        $relation = mb_strtolower(trim((string) ($contact?->relation_type ?: $rawRelation ?: '')), 'UTF-8');
        $nickname = mb_strtolower(trim((string) ($contact?->nickname ?? '')), 'UTF-8');
        $combined = trim($relation . ' ' . $nickname);

        if ($combined === '') {
            return true;
        }

        return !(bool) preg_match(
            '/\b(vater|papa|mutter|mama|mum|eltern|familie|partner\w*|freund\w*|schwester|bruder|oma|opa|privat|liebster)\b/iu',
            $combined
        );
    }

    /**
     * Dauerhafte DB-Idempotenz für Offline-Queue-Replays (> 30 Min. nach Cache-Ablauf):
     * verhindert doppelte Aufgaben, Tickets und Push-Nachrichten für dieselbe call_id.
     *
     * @return array<string, mixed>|null
     */
    private static function findPersistedCompletedCallPayload(string $callId): ?array
    {
        try {
            $fingerprint = 'phone_call:' . md5($callId);
            $existingTicket = Schema::hasTable('ai_universe_tickets')
                ? AiUniverseTicket::where('error_fingerprint', $fingerprint)->first()
                : null;

            $existingCall = null;
            if (Schema::hasTable('support_telephony_calls')) {
                if (str_starts_with($callId, 'twilio_')) {
                    $twilioDbId = (int) substr($callId, 7);
                    if ($twilioDbId > 0) {
                        $candidate = SupportTelephonyCall::find($twilioDbId);
                        if ($candidate && $candidate->status === 'completed') {
                            $existingCall = $candidate;
                        }
                    }
                } else {
                    $sidKey = 'app_' . Str::limit($callId, 100, '');
                    $existingCall = SupportTelephonyCall::where('twilio_sid', $sidKey)->first();
                }
            }

            if (!$existingCall && !$existingTicket) {
                return null;
            }

            $meta = [];
            if ($existingCall) {
                $meta = is_string($existingCall->next_steps)
                    ? (json_decode($existingCall->next_steps, true) ?: [])
                    : (is_array($existingCall->next_steps) ? $existingCall->next_steps : []);
            }
            $tp = ($existingTicket && is_array($existingTicket->action_payload)) ? $existingTicket->action_payload : [];

            // Bei Twilio-Call-Records, die gerade erst durch inboundStatus/callLog gespeichert wurden,
            // aber noch gar keine Task/Ticket-Verknüpfung besitzen, darf der reguläre Erst-Abschluss noch laufen.
            if ($existingCall && !$existingTicket && empty($meta['task_id']) && empty($meta['ticket_id']) && str_starts_with($callId, 'twilio_')) {
                return null;
            }

            return [
                'call_id' => $callId,
                'db_call_id' => $existingCall ? (int) $existingCall->id : ($tp['db_call_id'] ?? null),
                'is_simulation' => false,
                'is_fallback_stub' => (bool) ($meta['is_fallback_stub'] ?? ($tp['is_fallback_stub'] ?? false)),
                'call_mode' => (string) ($meta['call_mode'] ?? ($tp['call_mode'] ?? 'inbound_secretary')),
                'caller_name' => (string) ($existingCall?->contact_name ?? ($tp['caller_name'] ?? 'Kontakt')),
                'summary' => (string) ($existingCall?->summary ?? ($tp['summary'] ?? 'Telefonat mit Funkira geführt.')),
                'rahmenbedingungen' => is_array($meta['rahmenbedingungen'] ?? null)
                    ? $meta['rahmenbedingungen']
                    : (is_array($tp['rahmenbedingungen'] ?? null) ? $tp['rahmenbedingungen'] : []),
                'action_required' => (string) ($meta['action_required'] ?? ($tp['action_required'] ?? 'Zur Kenntnis nehmen.')),
                'urgency' => (string) ($meta['urgency'] ?? ($tp['urgency'] ?? 'mittel')),
                'callback_requested' => (bool) ($meta['callback_requested'] ?? ($tp['callback_requested'] ?? true)),
                'handled_by' => (string) ($meta['handled_by'] ?? ($tp['handled_by'] ?? 'android_secretary')),
                'task_id' => $meta['task_id'] ?? ($tp['task_id'] ?? null),
                'ticket_id' => $existingTicket?->id ?? ($meta['ticket_id'] ?? null),
                'ticket_number' => $existingTicket?->ticket_number ?? ($meta['ticket_number'] ?? null),
                'support_ticket_type' => $meta['support_ticket_type'] ?? ($tp['support_ticket_type'] ?? null),
                'support_ticket_id' => $meta['support_ticket_id'] ?? ($tp['support_ticket_id'] ?? null),
                'support_ticket_number' => $meta['support_ticket_number'] ?? ($tp['support_ticket_number'] ?? null),
                'category' => $meta['category'] ?? ($tp['category'] ?? null),
                'caller_email' => $meta['caller_email'] ?? ($tp['caller_email'] ?? null),
                'order_number' => $meta['order_number'] ?? ($tp['order_number'] ?? null),
                'completed_at' => ($existingCall?->updated_at ?? $existingTicket?->updated_at ?? now())->toIso8601String(),
            ];
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Erstellt oder aktualisiert idempotent ein strukturiertes Universum-Ticket (/admin/tickets)
     * samt chronologischer Protokoll-Nachricht für Alina.
     *
     * @param  array<string, mixed>  $ctx
     */
    private static function syncStructuredCallTicket(array $ctx): ?AiUniverseTicket
    {
        if (!Schema::hasTable('ai_universe_tickets') || !Schema::hasTable('ai_universe_ticket_messages')) {
            return null;
        }

        try {
            $callId = (string) ($ctx['call_id'] ?? '');
            $fingerprint = 'phone_call:' . md5($callId);
            $displayLabel = (string) ($ctx['display_label'] ?? 'Kontakt');
            $summary = (string) ($ctx['summary'] ?? '');
            $actionRequired = (string) ($ctx['action_required'] ?? 'Zur Kenntnis nehmen.');
            $rahmen = is_array($ctx['rahmenbedingungen'] ?? null) ? $ctx['rahmenbedingungen'] : [];
            $transcript = is_array($ctx['transcript_lines'] ?? null) ? $ctx['transcript_lines'] : [];
            $urgency = (string) ($ctx['urgency'] ?? 'mittel');
            $callbackRequested = (bool) ($ctx['callback_requested'] ?? true);
            $durationSeconds = max(0, (int) ($ctx['duration_seconds'] ?? 0));
            $handledBy = (string) ($ctx['handled_by'] ?? 'android_secretary');
            $sourceLabel = (string) ($ctx['source_label'] ?? 'Telefon-Assistent');
            $isBusiness = (bool) ($ctx['is_business'] ?? true);
            $isFallbackStub = (bool) ($ctx['is_fallback_stub'] ?? false);
            $verification = is_array($ctx['verification'] ?? null) ? $ctx['verification'] : null;

            $headline = $actionRequired !== '' && $actionRequired !== 'Zur Kenntnis nehmen.' && !str_contains($summary, $actionRequired)
                ? "{$summary} ({$actionRequired})"
                : $summary;
            $title = Str::limit("📞 Anruf von {$displayLabel}: {$headline}", 240, '...');
            $priority = ($urgency === 'hoch' || $callbackRequested)
                ? 'high'
                : ($urgency === 'niedrig' ? 'low' : 'normal');
            $needsAlina = $callbackRequested || ($actionRequired !== '' && $actionRequired !== 'Zur Kenntnis nehmen.');
            $status = $needsAlina ? AiUniverseTicket::STATUS_WAITING_FOR_ALINA : AiUniverseTicket::STATUS_NEW;
            $sourceChannel = match ($handledBy) {
                'twilio_customer_service' => 'twilio_phone_customer_service',
                'android_customer_service' => 'android_phone_customer_service',
                'twilio_secretary' => 'twilio_phone_secretary',
                default => 'android_phone_secretary',
            };
            $supportTicketNumber = !empty($ctx['support_ticket_number']) ? (string) $ctx['support_ticket_number'] : null;
            if ($supportTicketNumber !== null) {
                $title = Str::limit("📞 [{$supportTicketNumber}] {$displayLabel}: {$headline}", 240, '...');
            }

            $dept = AiDepartment::where('name', $isBusiness ? 'Support' : 'Leitung')->first()
                ?? AiDepartment::where('name', 'Leitung')->first()
                ?? AiDepartment::first();
            $agent = AiAgent::where('name', 'Funkira')->first()
                ?? AiAgent::where('name', 'Dr. Funki')->first();

            $payload = [
                'type' => 'phone_call_protocol',
                'call_id' => $callId,
                'db_call_id' => $ctx['db_call_id'] ?? null,
                'task_id' => $ctx['task_id'] ?? null,
                'caller_name' => $displayLabel,
                'caller_phone' => (string) ($ctx['caller_phone'] ?? ''),
                'caller_email' => (string) ($ctx['caller_email'] ?? ''),
                'order_number' => (string) ($ctx['order_number'] ?? ''),
                'category' => (string) ($ctx['category'] ?? ''),
                'support_ticket_type' => $ctx['support_ticket_type'] ?? null,
                'support_ticket_id' => $ctx['support_ticket_id'] ?? null,
                'support_ticket_number' => $supportTicketNumber,
                'summary' => $summary,
                'rahmenbedingungen' => array_values($rahmen),
                'action_required' => $actionRequired,
                'urgency' => $urgency,
                'callback_requested' => $callbackRequested,
                'duration_seconds' => $durationSeconds,
                'handled_by' => $handledBy,
                'is_fallback_stub' => $isFallbackStub,
                'verification' => $verification,
            ];

            $ticket = null;
            if (!empty($ctx['existing_ticket_id']) && Str::isUuid((string) $ctx['existing_ticket_id'])) {
                $ticket = AiUniverseTicket::find((string) $ctx['existing_ticket_id']);
            }
            if (!$ticket) {
                $ticket = AiUniverseTicket::where('error_fingerprint', $fingerprint)->first();
            }

            $protocolLines = [
                "**KI-Anrufprotokoll ({$sourceLabel})**",
                "**Anrufer:** {$displayLabel}" . (!empty($ctx['caller_phone']) ? " ({$ctx['caller_phone']})" : ''),
                "**Dauer:** {$durationSeconds} Sek. · **Dringlichkeit:** " . ucfirst($urgency) . ' · **Rückruf gewünscht:** ' . ($callbackRequested ? 'Ja' : 'Nein'),
            ];
            if ($supportTicketNumber !== null) {
                $protocolLines[] = "**Support-Vorgang:** {$supportTicketNumber}"
                    . (!empty($ctx['caller_email']) ? " · **E-Mail:** {$ctx['caller_email']}" : '')
                    . (!empty($ctx['order_number']) ? " · **Bestellung:** {$ctx['order_number']}" : '');
            }
            $protocolLines[] = '';
            $protocolLines[] = '**Besprochen:**';
            $protocolLines[] = $summary;
            if (!empty($rahmen)) {
                $protocolLines[] = '';
                $protocolLines[] = '**Rahmenbedingungen:**';
                foreach ($rahmen as $rb) {
                    $protocolLines[] = '- ' . $rb;
                }
            }
            $protocolLines[] = '';
            $protocolLines[] = '**Für dich zu tun:**';
            $protocolLines[] = $actionRequired;

            if (!empty($transcript)) {
                $protocolLines[] = '';
                $protocolLines[] = '**Gesprächsprotokoll (Auszug):**';
                foreach (array_slice($transcript, 0, 20) as $line) {
                    $protocolLines[] = '> ' . $line;
                }
            }
            $protocolMarkdown = str_replace(' – ', ', ', implode("\n", $protocolLines));

            if (!$ticket) {
                $ticketAttrs = [
                    'ticket_number' => AiUniverseTicket::generateNextTicketNumber(),
                    'title' => $title,
                    'status' => $status,
                    'priority' => $priority,
                    'ticket_type' => 'phone_call',
                    'ai_department_id' => $dept?->id,
                    'assigned_agent_id' => $agent?->id,
                    'assigned_to_antigravity' => false,
                    'source_channel' => $sourceChannel,
                    'environment_tier' => FunkiraPresenceContext::resolveEnvironmentTier(),
                    'error_fingerprint' => $fingerprint,
                    'occurrence_count' => 1,
                    'messages_count' => 1,
                    'compact_context' => Str::limit($summary, 1000, ''),
                    'action_payload' => $payload,
                    'last_activity_at' => now(),
                ];
                if (Schema::hasColumn('ai_universe_tickets', 'is_business')) {
                    $ticketAttrs['is_business'] = $isBusiness;
                }

                $ticket = AiUniverseTicket::create($ticketAttrs);

                AiUniverseTicketMessage::create([
                    'ticket_id' => $ticket->id,
                    'sender_role' => 'agent',
                    'sender_name' => $agent?->name ?? 'Funkira',
                    'ai_agent_id' => $agent?->id,
                    'channel' => $sourceChannel,
                    'content' => $protocolMarkdown,
                    'action_card' => [
                        'type' => 'phone_call_protocol',
                        'call_id' => $callId,
                        'task_id' => $ctx['task_id'] ?? null,
                        'urgency' => $urgency,
                        'callback_requested' => $callbackRequested,
                    ],
                ]);

                return $ticket;
            }

            $ticket->title = $title;
            $ticket->status = $status;
            $ticket->priority = $priority;
            $ticket->compact_context = Str::limit($summary, 1000, '');
            $ticket->action_payload = $payload;
            $ticket->last_activity_at = now();
            $ticket->save();

            $firstMsg = AiUniverseTicketMessage::where('ticket_id', $ticket->id)
                ->orderBy('created_at', 'asc')
                ->first();
            if ($firstMsg) {
                $firstMsg->content = $protocolMarkdown;
                $firstMsg->save();
            } else {
                AiUniverseTicketMessage::create([
                    'ticket_id' => $ticket->id,
                    'sender_role' => 'agent',
                    'sender_name' => $agent?->name ?? 'Funkira',
                    'ai_agent_id' => $agent?->id,
                    'channel' => $sourceChannel,
                    'content' => $protocolMarkdown,
                    'action_card' => [
                        'type' => 'phone_call_protocol',
                        'call_id' => $callId,
                        'task_id' => $ctx['task_id'] ?? null,
                        'urgency' => $urgency,
                        'callback_requested' => $callbackRequested,
                    ],
                ]);
                $ticket->messages_count = max(1, (int) $ticket->messages_count);
                $ticket->save();
            }

            return $ticket;
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Erstellt oder aktualisiert idempotent ein Shop-Support-Ticket (`SupportTicket` bei erkanntem Bestandskunden)
     * bzw. eine strukturierte Kontaktanfrage (`SupportContactRequest` bei unbekannten Anrufern / Gästen).
     *
     * @param  array<string, mixed>  $ctx
     * @return array{type: string, id: string, ticket_number: string}|null
     */
    private static function syncCustomerSupportRecord(array $ctx): ?array
    {
        try {
            $callerName = self::sanitizeCleanText((string) ($ctx['caller_name'] ?? 'Telefon-Kunde'), 140);
            $callerPhone = self::sanitizeCleanText((string) ($ctx['caller_phone'] ?? ''), 60);
            $callerEmail = strtolower(trim((string) ($ctx['caller_email'] ?? '')));
            $orderNumber = trim((string) ($ctx['order_number'] ?? ''));
            $category = (string) ($ctx['category'] ?? 'support');
            $summary = self::sanitizeCleanText((string) ($ctx['summary'] ?? ''), 1900);
            $actionRequired = self::sanitizeCleanText((string) ($ctx['action_required'] ?? 'Kundenanliegen prüfen.'), 480);
            $rahmen = is_array($ctx['rahmenbedingungen'] ?? null) ? $ctx['rahmenbedingungen'] : [];
            $transcript = is_array($ctx['transcript_lines'] ?? null) ? $ctx['transcript_lines'] : [];
            $urgency = (string) ($ctx['urgency'] ?? 'mittel');
            $callbackRequested = (bool) ($ctx['callback_requested'] ?? true);
            $durationSeconds = max(0, (int) ($ctx['duration_seconds'] ?? 0));
            $subjectBase = self::sanitizeCleanText((string) ($ctx['ticket_subject'] ?? ''), 150);
            if ($subjectBase === '') {
                $subjectBase = Str::limit($summary, 110, '...');
            }
            $subject = Str::limit('📞 Telefon-Anliegen: ' . $subjectBase, 190, '...');

            $bodyLines = [
                'KI-Telefonaufnahme (Funkira Kundenservice)',
                "Anrufer: {$callerName}" . ($callerPhone !== '' ? " | Tel: {$callerPhone}" : '') . ($callerEmail !== '' ? " | E-Mail: {$callerEmail}" : ''),
            ];
            if ($orderNumber !== '') {
                $bodyLines[] = "Genannte Bestellnummer: {$orderNumber}";
            }
            $bodyLines[] = 'Dringlichkeit: ' . ucfirst($urgency) . ' | Rückruf gewünscht: ' . ($callbackRequested ? 'Ja' : 'Nein') . " | Dauer: {$durationSeconds} Sek.";
            $bodyLines[] = '';
            $bodyLines[] = 'Zusammenfassung:';
            $bodyLines[] = $summary;
            if (!empty($rahmen)) {
                $bodyLines[] = '';
                $bodyLines[] = 'Rahmenbedingungen:';
                foreach ($rahmen as $item) {
                    $bodyLines[] = '✓ ' . $item;
                }
            }
            $bodyLines[] = '';
            $bodyLines[] = 'Empfohlene Aktion:';
            $bodyLines[] = $actionRequired;
            $formattedMessage = implode("\n", $bodyLines);

            $transcriptNotes = !empty($transcript)
                ? "Gesprächsprotokoll:\n" . implode("\n", array_slice($transcript, 0, 60))
                : 'Kein Live-Transkript vorhanden.';

            $existingType = (string) ($ctx['existing_type'] ?? '');
            $existingId = (string) ($ctx['existing_id'] ?? '');

            // Fall 1: Bestehendes SupportTicket oder SupportContactRequest bei Nachlieferung nach Stub aktualisieren
            if ($existingType === 'support_ticket' && Str::isUuid($existingId) && Schema::hasTable('support_tickets')) {
                $ticket = SupportTicket::find($existingId);
                if ($ticket) {
                    $ticket->subject = $subject;
                    $ticket->category = $category;
                    $ticket->priority = $urgency === 'hoch' ? 'high' : 'normal';
                    $ticket->save();
                    $firstMsg = SupportTicketMessage::where('support_ticket_id', $ticket->id)->orderBy('created_at', 'asc')->first();
                    if ($firstMsg) {
                        $firstMsg->message = $formattedMessage . "\n\n" . $transcriptNotes;
                        $firstMsg->save();
                    }

                    return [
                        'type' => 'support_ticket',
                        'id' => (string) $ticket->id,
                        'ticket_number' => (string) $ticket->ticket_number,
                    ];
                }
            }
            if ($existingType === 'contact_request' && Str::isUuid($existingId) && Schema::hasTable('support_contact_requests')) {
                $req = SupportContactRequest::find($existingId);
                if ($req) {
                    $nameParts = preg_split('/\s+/u', $callerName, 2) ?: [$callerName];
                    if ($callerName !== '' && !str_starts_with($callerName, 'Kundenanruf')) {
                        $req->first_name = Str::limit((string) ($nameParts[0] ?? 'Telefon'), 80, '');
                        $req->last_name = Str::limit((string) ($nameParts[1] ?? 'Kunde'), 80, '');
                    }
                    if ($callerEmail !== '' && filter_var($callerEmail, FILTER_VALIDATE_EMAIL)) {
                        $req->email = $callerEmail;
                    }
                    $req->subject = $subject;
                    $req->category = $category;
                    $req->message = $formattedMessage;
                    $req->admin_notes = $transcriptNotes;
                    $req->save();
                    $firstMsg = SupportContactRequestMessage::where('support_contact_request_id', $req->id)->orderBy('created_at', 'asc')->first();
                    if ($firstMsg) {
                        $firstMsg->message = $formattedMessage;
                        $firstMsg->save();
                    }

                    return [
                        'type' => 'contact_request',
                        'id' => (string) $req->id,
                        'ticket_number' => (string) $req->ticket_number,
                    ];
                }
            }

            // Fall 2: Verifizierter Shop-Kunde -> SupportTicket
            $customerId = !empty($ctx['customer_id']) && Str::isUuid((string) $ctx['customer_id']) ? (string) $ctx['customer_id'] : null;
            if ($customerId !== null && Schema::hasTable('support_tickets') && Schema::hasTable('support_ticket_messages')) {
                $customer = Customer::find($customerId);
                if ($customer) {
                    $validOrderId = null;
                    if ($orderNumber !== '' && Schema::hasTable('order_orders')) {
                        $validOrderId = OrderOrder::where('customer_id', $customer->id)
                            ->where('order_number', $orderNumber)
                            ->value('id');
                    }

                    $ticketNumber = 'MSF-TEL-' . date('y') . '-' . strtoupper(Str::random(5));
                    $ticket = SupportTicket::create([
                        'ticket_number' => $ticketNumber,
                        'customer_id' => $customer->id,
                        'order_id' => $validOrderId,
                        'subject' => $subject,
                        'category' => $category,
                        'status' => 'open',
                        'priority' => $urgency === 'hoch' ? 'high' : 'normal',
                    ]);

                    $msg = SupportTicketMessage::create([
                        'support_ticket_id' => $ticket->id,
                        'sender_type' => 'customer',
                        'message' => $formattedMessage . "\n\n" . $transcriptNotes,
                        'is_read_by_customer' => true,
                        'is_read_by_admin' => false,
                    ]);

                    RealtimeBroadcastService::dispatch(
                        new TicketMessageSent($msg, $ticket->id, $ticket->customer_id),
                        'FunkiraPhoneConciergeService::syncCustomerSupportRecord'
                    );

                    return [
                        'type' => 'support_ticket',
                        'id' => (string) $ticket->id,
                        'ticket_number' => (string) $ticket->ticket_number,
                    ];
                }
            }

            // Fall 3: Neuer Interessent / Gast / nicht zugeordnete Nummer -> SupportContactRequest
            if (Schema::hasTable('support_contact_requests') && Schema::hasTable('support_contact_request_messages')) {
                $cleanName = str_starts_with($callerName, 'Kundenanruf') ? 'Telefon Anrufer' : $callerName;
                $nameParts = preg_split('/\s+/u', trim($cleanName), 2) ?: ['Telefon', 'Anrufer'];
                $firstName = Str::limit((string) ($nameParts[0] ?? 'Telefon'), 80, '') ?: 'Telefon';
                $lastName = Str::limit((string) ($nameParts[1] ?? 'Anrufer'), 80, '') ?: 'Anrufer';
                $safeEmail = ($callerEmail !== '' && filter_var($callerEmail, FILTER_VALIDATE_EMAIL))
                    ? $callerEmail
                    : 'telefon-eingang@mein-seelenfunke.de';

                $req = SupportContactRequest::create([
                    'ticket_number' => 'REQ-TEL-' . date('y') . '-' . strtoupper(Str::random(5)),
                    'first_name' => $firstName,
                    'last_name' => $lastName,
                    'email' => $safeEmail,
                    'phone' => $callerPhone !== '' ? Str::limit($callerPhone, 60, '') : 'Unterdrückt',
                    'subject' => $subject,
                    'category' => $category,
                    'status' => 'new',
                    'message' => $formattedMessage,
                    'admin_notes' => $transcriptNotes,
                ]);

                SupportContactRequestMessage::create([
                    'support_contact_request_id' => $req->id,
                    'sender_type' => 'customer',
                    'message' => $formattedMessage,
                    'is_read_by_customer' => true,
                    'is_read_by_admin' => false,
                ]);

                return [
                    'type' => 'contact_request',
                    'id' => (string) $req->id,
                    'ticket_number' => (string) $req->ticket_number,
                ];
            }
        } catch (\Throwable $e) {
            // Niemals Hauptprotokoll oder Dashboard-Task wegen eines Support-Record-Fehlers abbrechen
        }

        return null;
    }

    /**
     * Findet ein SupportTelephonyCall-Protokoll anhand der DB-ID, `twilio_<id>`, `app_<uuid>` oder `client_call_id`.
     */
    public static function findCallRecordByIdentifier(string $identifier): ?SupportTelephonyCall
    {
        $clean = trim($identifier);
        if ($clean === '' || !Schema::hasTable('support_telephony_calls')) {
            return null;
        }

        if (ctype_digit($clean)) {
            $byId = SupportTelephonyCall::find((int) $clean);
            if ($byId) {
                return $byId;
            }
        }

        if (str_starts_with($clean, 'twilio_') && ctype_digit(substr($clean, 7))) {
            $byTwilioId = SupportTelephonyCall::find((int) substr($clean, 7));
            if ($byTwilioId) {
                return $byTwilioId;
            }
        }

        $appSid = str_starts_with($clean, 'app_') ? $clean : ('app_' . Str::limit($clean, 100, ''));
        $bySid = SupportTelephonyCall::where('twilio_sid', $appSid)
            ->orWhere('twilio_sid', $clean)
            ->first();
        if ($bySid) {
            return $bySid;
        }

        $recent = SupportTelephonyCall::orderByDesc('id')->limit(100)->get();
        foreach ($recent as $row) {
            $meta = is_string($row->next_steps)
                ? (json_decode($row->next_steps, true) ?: [])
                : (is_array($row->next_steps) ? $row->next_steps : []);
            if (($meta['client_call_id'] ?? null) === $clean) {
                return $row;
            }
        }

        return null;
    }

    /**
     * Sendet eine Antwort von Alina auf ein telefonisches Kundenanliegen direkt in das verknüpfte
     * Support-Ticket bzw. die Kontaktanfrage (optional inkl. E-Mail-Versand) und protokolliert im Universum-Ticket.
     *
     * @return array<string, mixed>
     */
    public static function replyToCall(string $identifier, string $replyText, bool $sendEmail = false): array
    {
        $cleanReply = self::sanitizeCleanText($replyText, 3000);
        if ($cleanReply === '') {
            return ['success' => false, 'message' => 'Antworttext darf nicht leer sein.'];
        }

        $call = self::findCallRecordByIdentifier($identifier);
        if (!$call) {
            return ['success' => false, 'message' => 'Anrufprotokoll nicht gefunden.'];
        }

        $meta = is_string($call->next_steps)
            ? (json_decode($call->next_steps, true) ?: [])
            : (is_array($call->next_steps) ? $call->next_steps : []);

        $supportType = (string) ($meta['support_ticket_type'] ?? '');
        $supportId = (string) ($meta['support_ticket_id'] ?? '');
        $universeTicketId = (string) ($meta['ticket_id'] ?? '');
        $deliveredChannels = [];
        $emailDispatched = false;

        if ($supportType === 'support_ticket' && Str::isUuid($supportId) && Schema::hasTable('support_tickets')) {
            $ticket = SupportTicket::find($supportId);
            if ($ticket) {
                $msg = SupportTicketMessage::create([
                    'support_ticket_id' => $ticket->id,
                    'sender_type' => 'admin',
                    'message' => $cleanReply,
                    'is_read_by_customer' => false,
                    'is_read_by_admin' => true,
                ]);
                RealtimeBroadcastService::dispatch(
                    new TicketMessageSent($msg, $ticket->id, $ticket->customer_id),
                    'FunkiraPhoneConciergeService::replyToCall'
                );
                $deliveredChannels[] = 'support_ticket';

                if ($sendEmail) {
                    $recipient = trim((string) ($ticket->customer?->email ?? ($meta['caller_email'] ?? '')));
                    if ($recipient !== '' && filter_var($recipient, FILTER_VALIDATE_EMAIL) && !str_ends_with(strtolower($recipient), 'telefon-eingang@mein-seelenfunke.de')) {
                        try {
                            Mail::raw($cleanReply, function ($m) use ($recipient, $ticket) {
                                $m->to($recipient)->subject('Re: ' . ($ticket->subject ?: ('Ihr Anliegen ' . $ticket->ticket_number)));
                            });
                            $emailDispatched = true;
                            $deliveredChannels[] = 'email';
                        } catch (\Throwable) {
                            // E-Mail ist Best-Effort und blockiert niemals die Ticket-Antwort
                        }
                    }
                }
            }
        } elseif ($supportType === 'contact_request' && Str::isUuid($supportId) && Schema::hasTable('support_contact_requests')) {
            $req = SupportContactRequest::find($supportId);
            if ($req) {
                SupportContactRequestMessage::create([
                    'support_contact_request_id' => $req->id,
                    'sender_type' => 'admin',
                    'message' => $cleanReply,
                    'is_read_by_customer' => false,
                    'is_read_by_admin' => true,
                ]);
                if ($req->status === 'new') {
                    $req->status = 'in_progress';
                    $req->save();
                }
                $deliveredChannels[] = 'contact_request';

                if ($sendEmail) {
                    $recipient = trim((string) ($req->email ?: ($meta['caller_email'] ?? '')));
                    if ($recipient !== '' && filter_var($recipient, FILTER_VALIDATE_EMAIL) && !str_ends_with(strtolower($recipient), 'telefon-eingang@mein-seelenfunke.de')) {
                        try {
                            Mail::raw($cleanReply, function ($m) use ($recipient, $req) {
                                $m->to($recipient)->subject('Re: ' . ($req->subject ?: ('Ihr Anliegen ' . $req->ticket_number)));
                            });
                            $emailDispatched = true;
                            $deliveredChannels[] = 'email';
                        } catch (\Throwable) {
                            // Best-Effort
                        }
                    }
                }
            }
        }

        if ($universeTicketId !== '' && Str::isUuid($universeTicketId) && Schema::hasTable('ai_universe_ticket_messages')) {
            $uTicket = AiUniverseTicket::find($universeTicketId);
            if ($uTicket) {
                AiUniverseTicketMessage::create([
                    'ticket_id' => $uTicket->id,
                    'sender_role' => 'admin',
                    'sender_name' => 'Alina Steinhauer',
                    'channel' => 'phone_reply',
                    'content' => "**Antwort auf Telefonanliegen:**\n" . $cleanReply,
                ]);
                $uTicket->increment('messages_count');
                $uTicket->update(['last_activity_at' => now()]);
                $deliveredChannels[] = 'universe_ticket';
            }
        }

        $meta['last_reply'] = $cleanReply;
        $meta['last_reply_at'] = now()->toIso8601String();
        $call->next_steps = json_encode($meta, JSON_UNESCAPED_UNICODE);
        $call->save();

        RealtimeBroadcastService::dispatch(new SupportTelephonyUpdated(), 'FunkiraPhoneConciergeService::replyToCall');

        return [
            'success' => true,
            'call_id' => (string) $call->id,
            'delivered_channels' => $deliveredChannels,
            'email_dispatched' => $emailDispatched,
            'last_reply' => $cleanReply,
            'last_reply_at' => $meta['last_reply_at'],
        ];
    }

    /**
     * Markiert ein Telefonprotokoll samt verknüpfter Dashboard-Aufgabe, Universum-Ticket
     * und (optional) Support-Ticket / Kontaktanfrage synchron als erledigt.
     *
     * @return array<string, mixed>
     */
    public static function resolveCall(string $identifier, bool $closeSupportRecord = true): array
    {
        $call = self::findCallRecordByIdentifier($identifier);
        if (!$call) {
            return ['success' => false, 'message' => 'Anrufprotokoll nicht gefunden.'];
        }

        $meta = is_string($call->next_steps)
            ? (json_decode($call->next_steps, true) ?: [])
            : (is_array($call->next_steps) ? $call->next_steps : []);

        $meta['resolved'] = true;
        $meta['resolved_at'] = now()->toIso8601String();
        $call->next_steps = json_encode($meta, JSON_UNESCAPED_UNICODE);
        $call->save();

        $taskId = (string) ($meta['task_id'] ?? '');
        if ($taskId !== '' && Schema::hasTable('management_tasks')) {
            $task = ManagementTask::find($taskId);
            if ($task) {
                $task->is_completed = true;
                $task->save();
                ManagementTask::where('parent_id', $task->id)->update(['is_completed' => true]);
            }
        }

        $universeTicketId = (string) ($meta['ticket_id'] ?? '');
        if ($universeTicketId !== '' && Str::isUuid($universeTicketId) && Schema::hasTable('ai_universe_tickets')) {
            $uTicket = AiUniverseTicket::find($universeTicketId);
            if ($uTicket && $uTicket->status !== AiUniverseTicket::STATUS_COMPLETED) {
                $uTicket->status = AiUniverseTicket::STATUS_COMPLETED;
                $uTicket->completed_at = now();
                $uTicket->last_activity_at = now();
                $uTicket->save();
            }
        }

        if ($closeSupportRecord) {
            $supportType = (string) ($meta['support_ticket_type'] ?? '');
            $supportId = (string) ($meta['support_ticket_id'] ?? '');
            if ($supportType === 'support_ticket' && Str::isUuid($supportId) && Schema::hasTable('support_tickets')) {
                $ticket = SupportTicket::find($supportId);
                if ($ticket && $ticket->status !== 'closed') {
                    $ticket->status = 'closed';
                    $ticket->close_reason = 'Telefonisch / per App durch Alina erledigt';
                    $ticket->save();
                }
            } elseif ($supportType === 'contact_request' && Str::isUuid($supportId) && Schema::hasTable('support_contact_requests')) {
                $req = SupportContactRequest::find($supportId);
                if ($req && $req->status !== 'resolved') {
                    $req->status = 'resolved';
                    $req->save();
                }
            }
        }

        RealtimeBroadcastService::dispatch(new SupportTelephonyUpdated(), 'FunkiraPhoneConciergeService::resolveCall');

        return [
            'success' => true,
            'call_id' => (string) $call->id,
            'resolved' => true,
            'resolved_at' => $meta['resolved_at'],
        ];
    }
}
