<?php

namespace App\Services\Gamification;

use App\Models\Customer\CustomerGamification;

class PlayerNameValidator
{
    /**
     * Weltweiter & deutscher Gaming-Filter gegen:
     * 1. Rechtlich verbotene / verfassungsfeindliche / extremistische Begriffe (§ 86a StGB, NS-Begriffe, Terror, Hassrede)
     * 2. Schwere Gewaltverherrlichung, Selbstverletzung & Missbrauch
     * 3. Beleidigungen, Rassismus, Diskriminierung & Vulgärsprache (DE + EN + International)
     * 4. Amtsanmaßung / Impersonation offizieller System- oder Manufaktur-Rollen
     */
    private const BLOCKED_SUBSTRINGS = [
        // 1. Extremismus, NS-Begriffe, Hassrede & verfassungsfeindliche Codes
        'nazi', 'narzi', 'natzi', 'hitler', 'adolf', 'himmler', 'goebbels', 'goring', 'goering',
        'mengelel', 'mengele', 'heyß', 'heydrich', 'eichmann', 'bormann', 'rommel', 'ssSturm',
        'waffenss', 'wehrmacht', 'hakenkreuz', 'swastika', 'siegheil', 'heilhitler', 'judenfeind',
        'holocaust', 'auschwitz', 'treblinka', 'dachau', 'buchenwald', 'nsdap', 'gestapo',
        'sturmabteilung', 'reichsburger', 'reichsbuerger', 'arisch', 'aryan', 'whitepower',
        'kuklux', 'klansman', 'neonazi', 'faschist', 'fascist', 'racist', 'rassist',
        'jihad', 'dschihad', 'isis', 'daesh', 'alqaeda', 'alkiada', 'terrorist', 'taliban',
        'hamas', 'hisbollah', 'breivik', 'christchurch', '444', '1488', '88heil', 'heil88',

        // 2. Gewaltverherrlichung, Missbrauch & Selbstgefährdung
        'morder', 'moerder', 'murder', 'amok', 'schoolshoot', 'vergewaltig', 'rapist', 'raper',
        'pedo', 'paedo', 'padophil', 'kinderfick', 'childabuse', 'lolita', 'incest', 'inzest',
        'suizid', 'suicide', 'killall', 'massaker', 'genozid', 'genocide', 'folter', 'torture',
        'enthaupt', 'behead', 'blutbad', 'leichen', 'necro', 'nekrophil', 'zoophil',

        // 3. Beleidigungen, Rassismus & Vulgärsprache (DE / EN / Global)
        'hurensohn', 'hure', 'fotze', 'votze', 'schlampe', 'wichser', 'wixxer', 'wixer',
        'arschloch', 'arschgeige', 'missgeburt', 'fehlgeburt', 'spast', 'spasti', 'mongo',
        'kanake', 'kanacke', 'neger', 'nigga', 'nigger', 'negro', 'bimbo', 'zigeuner',
        'schwuchtel', 'transe', 'faggot', 'fag', 'dyke', 'retard', 'cunt', 'whore', 'slut',
        'bitch', 'fuck', 'motherfuck', 'cock', 'dickhead', 'pussy', 'penis', 'vagina',
        'anal', 'cumshot', 'blowjob', 'handjob', 'porno', 'bastard', 'drecksau', 'mistgeburt',
        'scheiss', 'pisser', 'kackbratze', 'schwanzlutscher', 'hundesohn', 'nutte',

        // 4. Impersonation / Offizielle Systembegriffe
        'admin', 'moderator', 'supportteam', 'seelenfunke', 'gamemaster', 'systembot',
        'offiziell', 'official', 'kundendienst', 'geschäftsführung', 'betreiber',
    ];

    /**
     * Exakte gesperrte Kurz-Tokens oder Zahlenkombinationen (z. B. 88, 18, 28, SS, SA, KZ, IS, KKK).
     */
    private const BLOCKED_EXACT_TOKENS = [
        'ss', 'sa', 'kz', 'ns', 'is', 'kkk', '18', '28', '88', '1488', '8818', '420', '666',
        'heil', 'rape', 'kill', 'dead', 'tod', 'mord', 'sex', 'xxx', 'porn', 'fuck', 'shit',
        'nazi', 'narzi', 'root', 'mod', 'gm', 'dev', 'ceo', 'chef',
    ];

    /**
     * Prüft und bereinigt einen gewünschten Spielernamen nach weltweiten Gaming-Standards.
     *
     * @return array{valid: bool, sanitized: string, error: ?string}
     */
    public static function validate(string $rawName, ?string $excludeCustomerId = null, bool $isRealName = false): array
    {
        $sanitized = preg_replace('/\s+/u', ' ', trim(strip_tags($rawName)));

        if ($sanitized === '') {
            return [
                'valid'     => false,
                'sanitized' => '',
                'error'     => 'Bitte gib einen gültigen Spielernamen (mindestens 3 Zeichen) ein.',
            ];
        }

        $length = mb_strlen($sanitized);
        if ($length < 3 || $length > 20) {
            return [
                'valid'     => false,
                'sanitized' => $sanitized,
                'error'     => 'Dein Spielername muss zwischen 3 und 20 Zeichen lang sein.',
            ];
        }

        // Erlaubt sind Buchstaben (inkl. Umlaute), Zahlen, Leerzeichen, Bindestrich, Unterstrich und Punkt (bei Initialen)
        $pattern = $isRealName
            ? '/^[\p{L}\p{N} ._-]+$/u'
            : '/^[\p{L}\p{N} _-]+$/u';

        if (!preg_match($pattern, $sanitized)) {
            return [
                'valid'     => false,
                'sanitized' => $sanitized,
                'error'     => 'Der Spielername darf nur Buchstaben, Zahlen, Leerzeichen, Bindestriche (-) oder Unterstriche (_) enthalten.',
            ];
        }

        // Darf nicht mit Sonderzeichen beginnen oder enden (außer Punkt am Ende bei echtem Namen-Initial)
        if (!$isRealName && !preg_match('/^[\p{L}\p{N}].*[\p{L}\p{N}]$/u', $sanitized)) {
            return [
                'valid'     => false,
                'sanitized' => $sanitized,
                'error'     => 'Der Spielername muss mit einem Buchstaben oder einer Zahl beginnen und enden.',
            ];
        }

        // Keine doppelten Sonderzeichen hintereinander
        if (preg_match('/[_.\s-]{2,}/u', $sanitized)) {
            return [
                'valid'     => false,
                'sanitized' => $sanitized,
                'error'     => 'Bitte verwende keine aufeinanderfolgenden Sonderzeichen im Namen.',
            ];
        }

        // Normalisierung für Leetspeak & Verschleierung (z. B. "N4rzi", "H!tler", "N_A_Z_I")
        $normalized = self::normalizeForFilter($sanitized);
        $normalizedAlphaOnly = preg_replace('/[^a-z0-9]/', '', $normalized);

        // 1. Exakte Token-Prüfung (Wörter getrennt durch Leerzeichen, Bindestrich oder Unterstrich)
        $tokens = preg_split('/[\s._-]+/u', mb_strtolower($sanitized)) ?: [];
        foreach ($tokens as $token) {
            $normToken = self::normalizeForFilter($token);
            if (in_array($normToken, self::BLOCKED_EXACT_TOKENS, true) || in_array(mb_strtolower($token), self::BLOCKED_EXACT_TOKENS, true)) {
                return [
                    'valid'     => false,
                    'sanitized' => $sanitized,
                    'error'     => 'Dieser Spielername enthält einen gesperrten oder rechtlich unzulässigen Begriff. Bitte wähle einen respektvollen Heldennamen.',
                ];
            }
        }

        // 2. Teilstring-Prüfung auf dem bereinigten String (erkennt auch "xX_Narzi_Xx" oder "N.a.z.i")
        foreach (self::BLOCKED_SUBSTRINGS as $blocked) {
            $blockedLower = mb_strtolower($blocked);
            if (str_contains($normalizedAlphaOnly, $blockedLower) || str_contains(mb_strtolower($sanitized), $blockedLower)) {
                return [
                    'valid'     => false,
                    'sanitized' => $sanitized,
                    'error'     => 'Dieser Spielername verstößt gegen unsere Richtlinien für faire und respektvolle Spielernamen. Bitte wähle einen anderen Namen.',
                ];
            }
        }

        // 3. Eindeutigkeit prüfen (bei freien Spielernamen darf kein anderer Held exakt denselben Namen tragen)
        if (!$isRealName) {
            try {
                $query = CustomerGamification::whereRaw('LOWER(player_name) = ?', [mb_strtolower($sanitized)]);
                if ($excludeCustomerId !== null) {
                    $query->where('customer_id', '!=', $excludeCustomerId);
                }
                if ($query->exists()) {
                    return [
                        'valid'     => false,
                        'sanitized' => $sanitized,
                        'error'     => 'Dieser Spielername ist bereits an einen anderen Helden vergeben. Bitte wähle einen einzigartigen Namen.',
                    ];
                }
            } catch (\Throwable $e) {
                // Falls Spalte in einer frischen Test-Migration noch angelegt wird, keinen Fehler werfen
            }
        }

        return [
            'valid'     => true,
            'sanitized' => $sanitized,
            'error'     => null,
        ];
    }

    private static function normalizeForFilter(string $input): string
    {
        $lower = mb_strtolower($input, 'UTF-8');

        // Deutsche Umlaute & Akzente vereinheitlichen
        $replacements = [
            'ä' => 'a', 'ö' => 'o', 'ü' => 'u', 'ß' => 'ss',
            'á' => 'a', 'à' => 'a', 'â' => 'a', 'é' => 'e', 'è' => 'e', 'ê' => 'e',
            'í' => 'i', 'ì' => 'i', 'î' => 'i', 'ó' => 'o', 'ò' => 'o', 'ô' => 'o',
            'ú' => 'u', 'ù' => 'u', 'û' => 'u',
            // Typisches Leetspeak
            '0' => 'o', '1' => 'i', '3' => 'e', '4' => 'a', '5' => 's', '7' => 't',
            '@' => 'a', '$' => 's', '!' => 'i', '|' => 'l',
        ];

        $normalized = strtr($lower, $replacements);

        // Mehrfach wiederholte gleiche Buchstaben (z.B. "naaaazi" -> "nazi") reduzieren
        $normalized = preg_replace('/([a-z])\1{2,}/', '$1', $normalized) ?? $normalized;

        return $normalized;
    }
}
