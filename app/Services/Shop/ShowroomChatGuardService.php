<?php

namespace App\Services\Shop;

use Illuminate\Contracts\Cache\LockProvider;
use Illuminate\Support\Facades\Cache;

class ShowroomChatGuardService
{
    public const MAX_MESSAGE_LENGTH = 100;
    public const COOLDOWN_SECONDS = 4;
    public const REPORT_THRESHOLD = 2;
    public const SHADOW_MUTE_SECONDS = 86400; // 24 Stunden

    /**
     * Exakte gesperrte Einzelwörter nach Normalisierung (DE & EN Beleidigungen, Fäkalsprache, Hassrede, NSFW).
     *
     * @var array<int, string>
     */
    private const BLOCKED_TOKENS = [
        // Deutsch
        'arsch', 'arschloch', 'arschgeige', 'arschkriecher', 'hure', 'hurensohn', 'hundesohn',
        'huso', 'hs', 'fotze', 'votze', 'schlampe', 'nutte', 'wichser', 'wixxer', 'wixer', 'wichsen',
        'fick', 'ficken', 'ficker', 'gefickt', 'scheisse', 'scheiss', 'scheis', 'kacke', 'pisser',
        'pisse', 'bastard', 'missgeburt', 'mistgeburt', 'fehlgeburt', 'drecksau', 'spast', 'spasti',
        'mongo', 'vollidiot', 'idiot', 'huren', 'schwuchtel', 'transe', 'kanake', 'kanacke',
        'neger', 'zigeuner', 'bimbo', 'assi', 'kackbratze', 'fresse', 'verpiss', 'kotze',
        'schwanzlutscher', 'mord', 'moerder', 'suizid', 'amok', 'inzest', 'nazi', 'hitler',
        'nsdap', 'gestapo', 'ss', 'sa', 'kz', 'heil',
        // Englisch
        'fuck', 'fck', 'fuk', 'fucker', 'fucking', 'mofo', 'motherfucker', 'shit', 'bullshit',
        'bitch', 'btch', 'whore', 'slut', 'cunt', 'dick', 'dickhead', 'cock', 'pussy', 'asshole',
        'arsehole', 'retard', 'retarded', 'faggot', 'fag', 'dyke', 'nigger', 'nigga', 'negro',
        'chink', 'kike', 'twat', 'wanker', 'prick', 'blowjob', 'handjob', 'cum', 'cumshot',
        'porn', 'porno', 'hentai', 'sex', 'penis', 'vagina', 'anal', 'dildo', 'boobs', 'tits',
        'rape', 'rapist', 'pedo', 'paedo', 'pedophile', 'incest', 'suicide', 'kys', 'isis',
        'daesh', 'jihad', 'kkk', 'swastika',
    ];

    /**
     * Eindeutige Hochrisiko-Teilstrings, die auch ohne Wortgrenzen (z. B. zusammengeschrieben) hart blockiert werden.
     *
     * @var array<int, string>
     */
    private const BLOCKED_SUBSTRINGS = [
        'hurensohn', 'arschloch', 'missgeburt', 'mistgeburt', 'fehlgeburt', 'schwanzlutscher',
        'kackbratze', 'haltdiefresse', 'motherfuck', 'dickhead', 'faggot', 'nigger', 'nigga',
        'heilhitler', 'siegheil', 'hakenkreuz', 'swastika', 'auschwitz', 'holocaust', 'treblinka',
        'waffenss', 'wehrmacht', 'reichsburger', 'reichsbuerger', 'whitepower', 'kuklux',
        'vergewaltig', 'kinderfick', 'childabuse', 'schoolshoot', 'killall', 'bringdichum',
        'killyourself', 'terrorist', 'blowjob', 'cumshot', 'onlyfans', 'pornhub', 'xvideos',
    ];

    /**
     * Maskierte Sternchen-/Sonderzeichen-Muster vor der Bereinigung (z. B. f*ck, sh*t, b*tch, h*re, a*sch).
     *
     * @var array<int, string>
     */
    private const MASKED_EVASION_PATTERNS = [
        '/\bf+[\W_]*[*+#_\-.]+[\W_]*c*k+\b/iu',
        '/\bs+h+[\W_]*[*+#_\-.]+[\W_]*t+\b/iu',
        '/\bb+[\W_]*[*+#_\-.]+[\W_]*t+c*h+\b/iu',
        '/\bh+[\W_]*[*+#_\-.]+[\W_]*r+e+\b/iu',
        '/\ba+r*[\W_]*[*+#_\-.]+[\W_]*s*c*h+\b/iu',
        '/\bc+[\W_]*[*+#_\-.]+[\W_]*n+t+\b/iu',
        '/\bn+[\W_]*[*+#_\-.]+[\W_]*z+i+\b/iu',
        '/\bp+[\W_]*[*+#_\-.]+[\W_]*s+y+\b/iu',
        '/\bw+[\W_]*[*+#_\-.]+[\W_]*(?:ch|x)+s*/iu',
    ];

    /**
     * Prüft, bereinigt und taktet eine eingehende Showroom-Chatnachricht in < 2 ms (4-Stufen-Schranke).
     *
     * @return array{allowed: bool, sanitized: string, code: ?string, reason: ?string}
     */
    public function inspectAndSanitize(string $customerId, string $rawMessage, bool $applyCooldown = true): array
    {
        // Zero-Width- & Steuerzeichen sofort entfernen, danach HTML strippen
        $withoutInvisible = preg_replace('/[\x{0000}-\x{001F}\x{007F}\x{00AD}\x{200B}-\x{200F}\x{202A}-\x{202E}\x{2060}-\x{206F}\x{FEFF}]/u', '', $rawMessage) ?? $rawMessage;
        $stripped = trim(preg_replace('/\s+/u', ' ', strip_tags($withoutInvisible)) ?? '');

        // Stufe 3a: Leer- & Längenprüfung (max. 100 Zeichen)
        if ($stripped === '') {
            return [
                'allowed' => false,
                'sanitized' => '',
                'code' => 'empty',
                'reason' => 'Bitte gib eine Nachricht ein.',
            ];
        }

        if (mb_strlen($stripped, 'UTF-8') > self::MAX_MESSAGE_LENGTH) {
            return [
                'allowed' => false,
                'sanitized' => '',
                'code' => 'too_long',
                'reason' => 'Maximal 100 Zeichen pro Nachricht erlaubt.',
            ];
        }

        // Stufe 4 Check: Aktiver 24h Shadow-Mute durch 2-Strike-Reports
        if ($this->isPlayerShadowMuted($customerId)) {
            return [
                'allowed' => false,
                'sanitized' => '',
                'code' => 'muted',
                'reason' => 'Deine Chat-Funktion ist vorübergehend für 24 Stunden pausiert.',
            ];
        }

        // Stufe 3b: Slow-Mode (4 Sekunden Cooldown pro Kunde)
        if ($applyCooldown && $this->isOnCooldown($customerId)) {
            return [
                'allowed' => false,
                'sanitized' => '',
                'code' => 'cooldown',
                'reason' => 'Bitte warte einen kurzen Moment vor der nächsten Nachricht.',
            ];
        }

        // Stufe 2a: Strikte Link-, Social-Handle-, E-Mail- & Telefonnummern-Sperre (DSGVO & Phishing-Schutz)
        if ($this->containsLinkOrPii($stripped)) {
            return [
                'allowed' => false,
                'sanitized' => '',
                'code' => 'blocked_link_or_pii',
                'reason' => 'Links, Kontaktdaten, Telefonnummern und Social-Handles sind im Showroom nicht erlaubt.',
            ];
        }

        // Stufe 1 & 2b: Anti-Trick-Normalisierung + DE/EN TikTok-Basis-Wortfilter
        if ($this->containsBlockedLanguage($stripped)) {
            return [
                'allowed' => false,
                'sanitized' => '',
                'code' => 'blocked_language',
                'reason' => 'Diese Nachricht entspricht nicht unseren Richtlinien für einen respektvollen Umgang.',
            ];
        }

        // KI-Gedankenstriche bereinigen
        $cleanOutput = str_replace(
            [' ' . "\u{2013}" . ' ', ' ' . "\u{2014}" . ' ', "\u{2013}", "\u{2014}"],
            [' · ', ' · ', '-', '-'],
            $stripped
        );

        if ($applyCooldown) {
            $this->touchCooldown($customerId);
        }

        return [
            'allowed' => true,
            'sanitized' => $cleanOutput,
            'code' => null,
            'reason' => null,
        ];
    }

    /**
     * Meldet einen störenden Spieler (Stufe 4: Ab 2 verschiedenen Meldungen greift sofort 24h Shadow-Mute).
     *
     * @return array{accepted: bool, reports_count: int, shadow_muted: bool}
     */
    public function reportPlayer(string $reporterCustomerId, string $targetCustomerId): array
    {
        $reporter = trim($reporterCustomerId);
        $target = trim($targetCustomerId);

        if ($reporter === '' || $target === '' || $reporter === $target) {
            return [
                'accepted' => false,
                'reports_count' => 0,
                'shadow_muted' => $this->isPlayerShadowMuted($target),
            ];
        }

        $applyReport = function () use ($reporter, $target): array {
            $reportKey = $this->reportCacheKey($target);
            $reporters = Cache::get($reportKey, []);
            if (!is_array($reporters)) {
                $reporters = [];
            }

            $reporters[$reporter] = time();
            Cache::put($reportKey, $reporters, self::SHADOW_MUTE_SECONDS);

            $count = count($reporters);
            $muted = $this->isPlayerShadowMuted($target);

            if ($count >= self::REPORT_THRESHOLD && !$muted) {
                Cache::put(
                    $this->muteCacheKey($target),
                    time() + self::SHADOW_MUTE_SECONDS,
                    self::SHADOW_MUTE_SECONDS
                );
                $muted = true;
            }

            return [
                'accepted' => true,
                'reports_count' => $count,
                'shadow_muted' => $muted,
            ];
        };

        $store = Cache::getStore();
        if ($store instanceof LockProvider) {
            return Cache::lock('sf_showroom_rep_lock_' . md5($target), 5)->block(2, $applyReport);
        }

        return $applyReport();
    }

    public function isPlayerShadowMuted(string $customerId): bool
    {
        if (trim($customerId) === '') {
            return false;
        }

        $until = (int) Cache::get($this->muteCacheKey($customerId), 0);

        return $until > time();
    }

    public function isOnCooldown(string $customerId): bool
    {
        if (trim($customerId) === '') {
            return false;
        }

        $until = (float) Cache::get($this->cooldownCacheKey($customerId), 0);

        return $until > microtime(true);
    }

    public function touchCooldown(string $customerId): void
    {
        if (trim($customerId) === '') {
            return;
        }

        Cache::put(
            $this->cooldownCacheKey($customerId),
            microtime(true) + self::COOLDOWN_SECONDS,
            self::COOLDOWN_SECONDS + 2
        );
    }

    private function containsLinkOrPii(string $text): bool
    {
        $lower = mb_strtolower($text, 'UTF-8');

        // 1. Protokolle, www & bekannte Messenger-/Shortener-Domains
        if (preg_match('#(?:https?://|ftp://|www\.|t\.me/|wa\.me/|bit\.ly/|tinyurl\.com|discord\.gg|discord\.com|tiktok\.com|instagram\.com|youtu\.be)#iu', $lower)) {
            return true;
        }

        // 2. E-Mail-Adressen inkl. "(at)" / "[at]" Verschleierung (vor @-Leetspeak!)
        if (preg_match('/[a-z0-9._%+\-]+\s*(?:@|\(at\)|\[at\])\s*[a-z0-9.\-]+\s*\.\s*[a-z]{2,}/iu', $lower)) {
            return true;
        }

        // 3. @-Social-Handles (z. B. "@meinprofil" am Wortanfang)
        if (preg_match('/(?:^|\s)@[a-z0-9_.\-]{2,}/iu', $lower)) {
            return true;
        }

        // 4. Domain-Endungen (.de, .com, .net, .org, .io, .gg, .shop, .ru, .cn, .xyz, .me, .eu, .at, .ch)
        if (preg_match('/\b[a-z0-9\-]{2,}\s*\.\s*(?:de|com|net|org|io|gg|me|ru|cn|xyz|shop|store|online|app|link|info|eu|at|ch|tv|co|uk)\b/iu', $lower)) {
            return true;
        }

        // 5. Telefonnummern (>= 6 Ziffern am Stück oder getrennt durch Leerzeichen, Bindestrich, Schrägstrich, Punkt, Klammern)
        $digitsCollapsed = preg_replace('~(?<=\d)[\s\-./()+]+(?=\d)~u', '', $lower) ?? $lower;
        if (preg_match('/\d{6,}/', $digitsCollapsed)) {
            return true;
        }

        return false;
    }

    private function containsBlockedLanguage(string $text): bool
    {
        // 0. Maskierte Zensur-Tricks wie "f*ck", "sh*t", "b*tch", "h*re", "a*sch"
        foreach (self::MASKED_EVASION_PATTERNS as $pattern) {
            if (preg_match($pattern, $text)) {
                return true;
            }
        }

        $homoglyphAndLeetMapped = $this->mapHomoglyphsAndLeetspeak($text);

        // 1. Trennzeichen innerhalb von Wörtern auflösen ("A.r.s.c.h" -> "arsch", "f-u-c-k" -> "fuck")
        $unpunctuated = preg_replace('/(?<=[a-z])[\._\-*+#~\/\\\\|]+(?=[a-z])/u', '', $homoglyphAndLeetMapped) ?? $homoglyphAndLeetMapped;

        // 2. Einzeln gesperrt getippte Buchstaben zusammenziehen ("h u r e" -> "hure", "a r s c h" -> "arsch")
        $collapsedSpaced = preg_replace_callback(
            '/(?:^|\s)([a-z](?:\s+[a-z]){2,})(?=\s|$)/u',
            static fn (array $m): string => ' ' . str_replace(' ', '', $m[1]) . ' ',
            $unpunctuated
        ) ?? $unpunctuated;

        $tokens = preg_split('/[^a-z0-9]+/u', $collapsedSpaced, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $blockedSet = array_flip(self::BLOCKED_TOKENS);

        foreach ($tokens as $token) {
            // Exakt, auf Doppelbuchstaben reduziert ("puuuussy" -> "pussy") und auf Einzelbuchstaben reduziert ("huuure" -> "hure")
            $dedupDouble = preg_replace('/([a-z])\1{2,}/u', '$1$1', $token) ?? $token;
            $dedupSingle = preg_replace('/([a-z])\1+/u', '$1', $token) ?? $token;

            foreach ([$token, $dedupDouble, $dedupSingle] as $candidate) {
                if (isset($blockedSet[$candidate])) {
                    return true;
                }
            }
        }

        // 3. Vollständig zusammengezogener Buchstabenstrom gegen eindeutige Hochrisiko-Begriffe
        $alphaStream = preg_replace('/[^a-z0-9]/u', '', $homoglyphAndLeetMapped) ?? '';
        $alphaStreamDedup2 = preg_replace('/([a-z])\1{2,}/u', '$1$1', $alphaStream) ?? $alphaStream;
        $alphaStreamDedup1 = preg_replace('/([a-z])\1+/u', '$1', $alphaStream) ?? $alphaStream;

        foreach (self::BLOCKED_SUBSTRINGS as $sub) {
            $subDedup1 = preg_replace('/([a-z])\1+/u', '$1', $sub) ?? $sub;
            if (
                str_contains($alphaStream, $sub)
                || str_contains($alphaStreamDedup2, $sub)
                || ($subDedup1 !== '' && strlen($subDedup1) >= 5 && str_contains($alphaStreamDedup1, $subDedup1))
            ) {
                return true;
            }
        }

        return false;
    }

    private function mapHomoglyphsAndLeetspeak(string $input): string
    {
        $lower = mb_strtolower($input, 'UTF-8');

        $map = [
            // Deutsche Umlaute & Akzente
            'ä' => 'a', 'ö' => 'o', 'ü' => 'u', 'ß' => 'ss',
            'á' => 'a', 'à' => 'a', 'â' => 'a', 'ã' => 'a', 'å' => 'a',
            'é' => 'e', 'è' => 'e', 'ê' => 'e', 'ë' => 'e',
            'í' => 'i', 'ì' => 'i', 'î' => 'i', 'ï' => 'i',
            'ó' => 'o', 'ò' => 'o', 'ô' => 'o', 'õ' => 'o', 'ø' => 'o',
            'ú' => 'u', 'ù' => 'u', 'û' => 'u',
            'ç' => 'c', 'ñ' => 'n', 'ý' => 'y', 'ÿ' => 'y',
            // Kyrillische & griechische Homoglyphen
            'а' => 'a', 'в' => 'b', 'е' => 'e', 'і' => 'i', 'ї' => 'i',
            'к' => 'k', 'м' => 'm', 'н' => 'h', 'о' => 'o', 'р' => 'p',
            'с' => 'c', 'т' => 't', 'у' => 'y', 'х' => 'x', 'ѕ' => 's',
            'α' => 'a', 'β' => 'b', 'ε' => 'e', 'ι' => 'i', 'κ' => 'k',
            'ο' => 'o', 'ρ' => 'p', 'τ' => 't', 'υ' => 'u', 'χ' => 'x',
            // Leetspeak
            '0' => 'o', '1' => 'i', '3' => 'e', '4' => 'a', '5' => 's',
            '7' => 't', '8' => 'b', '$' => 's', '@' => 'a', '!' => 'i',
            '|' => 'l', '€' => 'e', '+' => 't',
        ];

        return strtr($lower, $map);
    }

    private function cooldownCacheKey(string $customerId): string
    {
        return 'sf_showroom_chat_cd_' . md5($customerId);
    }

    private function reportCacheKey(string $targetCustomerId): string
    {
        return 'sf_showroom_reports_' . md5($targetCustomerId);
    }

    private function muteCacheKey(string $targetCustomerId): string
    {
        return 'sf_showroom_mute_' . md5($targetCustomerId);
    }
}
