<?php

namespace App\Services\AI;

class TTSHelper
{
    /**
     * Sanitizes and phonetically formats text specifically for ElevenLabs German TTS.
     * ElevenLabs struggles with symbols like "@", German date formats, and strict abbreviations.
     */
    public static function sanitizeForGermanTTS(string $text): string
    {
        // 1. Remove markdown formatting elements
        $cleanText = preg_replace('/\[.*?\]/s', '', $text);
        $cleanText = preg_replace('/[*_#`~>]/', '', $cleanText);

        // 1b. Normalisiere ALLE Arten von exotischen Unicode-Leerzeichen (wie "Narrow No-Break Space" U+202F) zu normalen Leerzeichen.
        // Die LLMs generieren diese oft zwischen Ziffern (z.B. "25 Oktober" oder "0176 57793016").
        // Diese unsichtbaren Sonderzeichen lassen Regex-Pattern crashen und bringen ElevenLabs zum Absturz.
        $cleanText = preg_replace('/[\s\x{202F}\x{00A0}\x{200B}]+/u', ' ', $cleanText);

        // 2. Pronounce @ correctly in German ("ätt")
        $cleanText = str_replace('@', ' ätt ', $cleanText);
        
        // 3. Pronounce "&" correctly in German ("und")
        $cleanText = str_replace('&', ' und ', $cleanText);
        
        // 4. Telefonnummern pausiert sprechen, da ElevenLabs keine langen Ziffernfolgen versteht.
        // Matcht Formate wie +49 176 1234567, 0049176, 0176-123456, 0531 692760
        $cleanText = preg_replace_callback('/((?:\+|00)49\s?[1-9][\d\s\/\-]{5,}\d|0[1-9][\d\s\/\-]{6,}\d)/', function($matches) {
            $raw = $matches[1];
            // Entferne alles außer Ziffern und dem Pluszeichen
            $digitsOnly = preg_replace('/[^\d\+]/', '', $raw);

            // Füge ein Komma nach jeder Ziffer ein, um eine natürliche Pause bei ElevenLabs zu erzwingen
            $spaced = implode(', ', str_split($digitsOnly));
            
            return ' ' . $spaced . ' ';
        }, $cleanText);

        // 4b. Web-Domains und TLDs (Top-Level-Domains) wie ".de" phonetisch zwingen
        // ".de" am Wortende oder vor Leerzeichen zu "Punkt d e"
        $cleanText = preg_replace('/\.de\b/i', ' Punkt d e', $cleanText);
        $cleanText = preg_replace('/\.com\b/i', ' Punkt com', $cleanText);
        $cleanText = preg_replace('/\.net\b/i', ' Punkt nett', $cleanText);
        $cleanText = preg_replace('/\.org\b/i', ' Punkt ork', $cleanText);

        // 5. Fix typical date pronunciation (e.g. 13.03. or 13.03.2026)
        $months = [
            '01' => 'Januar', '02' => 'Februar', '03' => 'März', '04' => 'April',
            '05' => 'Mai', '06' => 'Juni', '07' => 'Juli', '08' => 'August',
            '09' => 'September', '10' => 'Oktober', '11' => 'November', '12' => 'Dezember',
            '1' => 'Januar', '2' => 'Februar', '3' => 'März', '4' => 'April',
            '5' => 'Mai', '6' => 'Juni', '7' => 'Juli', '8' => 'August', '9' => 'September'
        ];
        
        $dayWords = [
            1 => 'erster', 2 => 'zweiter', 3 => 'dritter', 4 => 'vierter', 5 => 'fünfter',
            6 => 'sechster', 7 => 'siebter', 8 => 'achter', 9 => 'neunter', 10 => 'zehnter',
            11 => 'elfter', 12 => 'zwölfter', 13 => 'dreizehnter', 14 => 'vierzehnter', 15 => 'fünfzehnter',
            16 => 'sechzehnter', 17 => 'siebzehnter', 18 => 'achtzehnter', 19 => 'neunzehnter', 20 => 'zwanzigster',
            21 => 'einundzwanzigster', 22 => 'zweiundzwanzigster', 23 => 'dreiundzwanzigster', 24 => 'vierundzwanzigster',
            25 => 'fünfundzwanzigster', 26 => 'sechsundzwanzigster', 27 => 'siebenundzwanzigster', 28 => 'achtundzwanzigster',
            29 => 'neunundzwanzigster', 30 => 'dreißigster', 31 => 'einunddreißigster'
        ];

        // Hilfsfunktion: Jahr aussprechen (z.B. "1963" -> "neunzehnhundert dreiundsechzig", "2026" -> "zweitausend sechsundzwanzig")
        $pronounceYear = function($year) {
            $year = (int)$year;
            $ones = ['', 'eins', 'zwei', 'drei', 'vier', 'fünf', 'sechs', 'sieben', 'acht', 'neun'];
            $tens2 = ['', '', 'zwanzig', 'dreißig', 'vierzig', 'fünfzig', 'sechzig', 'siebzig', 'achtzig', 'neunzig'];
            $teens = ['zehn', 'elf', 'zwölf', 'dreizehn', 'vierzehn', 'fünfzehn', 'sechzehn', 'siebzehn', 'achtzehn', 'neunzehn'];

            $twoDigitWord = function($n) use ($ones, $tens2, $teens) {
                if ($n < 10) return $ones[$n];
                if ($n < 20) return $teens[$n - 10];
                $t = intdiv($n, 10);
                $o = $n % 10;
                if ($o === 0) return $tens2[$t];
                if ($o === 1) return 'einund' . $tens2[$t];
                return $ones[$o] . 'und' . $tens2[$t];
            };

            if ($year >= 2000 && $year < 2100) {
                $rem = $year % 100;
                return 'zweitausend ' . ($rem > 0 ? $twoDigitWord($rem) : '');
            } elseif ($year >= 1000 && $year < 2000) {
                $c = intdiv($year, 100);
                $rem = $year % 100;
                $remStr = $rem > 0 ? $twoDigitWord($rem) : '';
                
                $cWord = '';
                if ($c == 10) $cWord = 'zehn';
                elseif ($c == 11) $cWord = 'elf';
                elseif ($c == 12) $cWord = 'zwölf';
                elseif ($c < 20) $cWord = $teens[$c - 10];

                return trim($cWord . 'hundert ' . $remStr);
            }
            return (string)$year; // Fallback
        };

        // Matches formats like 13.03.2026 or 13.3.2026 -> "dreizehnter März zweitausend sechsundzwanzig"
        $cleanText = preg_replace_callback('/(\d{1,2})\.(\d{1,2})\.(\d{4})/', function($matches) use ($months, $dayWords, $pronounceYear) {
            $day = (int)$matches[1];
            $month = str_pad($matches[2], 2, '0', STR_PAD_LEFT);
            $year = $matches[3];
            
            $dayName = $dayWords[$day] ?? $day . '.';
            $monthName = $months[$month] ?? $month;
            $yearName = $pronounceYear($year);
            
            return "{$dayName} {$monthName} {$yearName}";
        }, $cleanText);

        // NEU: Matches formats like 25. Oktober 1963 (Textueller Monat vom LLM generiert) -> "fünfundzwanzigster Oktober neunzehnhundertdreiundsechzig"
        $cleanText = preg_replace_callback('/(\d{1,2})\.\s+([A-Za-zÄÖÜäöüß]+)\s+(\d{4})/', function($matches) use ($dayWords, $pronounceYear) {
            $day = (int)$matches[1];
            $monthName = $matches[2]; // e.g. "Oktober"
            $year = $matches[3];
            
            $dayName = $dayWords[$day] ?? $day . '.';
            $yearName = $pronounceYear($year);
            
            return "{$dayName} {$monthName} {$yearName}";
        }, $cleanText);

        // Matches formats like 1990-07-13 (ISO aus der DB) -> "dreizehnter Juli neunzehnhundert neunzig"
        $cleanText = preg_replace_callback('/(\d{4})-(\d{1,2})-(\d{1,2})/', function($matches) use ($months, $dayWords, $pronounceYear) {
            $year = $matches[1];
            $month = str_pad($matches[2], 2, '0', STR_PAD_LEFT);
            $day = (int)$matches[3];

            $dayName = $dayWords[$day] ?? $day . '.';
            $monthName = $months[$month] ?? $month;
            $yearName = $pronounceYear($year);
            
            return "{$dayName} {$monthName} {$yearName}";
        }, $cleanText);

        // Matches formats like 13.03. (without year) -> "dreizehnter März"
        $cleanText = preg_replace_callback('/(\d{1,2})\.(\d{1,2})\./', function($matches) use ($months, $dayWords) {
            $day = (int)$matches[1];
            $month = str_pad($matches[2], 2, '0', STR_PAD_LEFT);
            
            $dayName = $dayWords[$day] ?? $day . '.';
            $monthName = $months[$month] ?? $month;
            
            return "{$dayName} {$monthName}";
        }, $cleanText);

        // 5b. Kurze Monatsnamen ausschreiben (weil "Feb." oft als "Fepp" gelesen wird)
        $cleanText = preg_replace('/\bJan\.\b/i', 'Januar', $cleanText);
        $cleanText = preg_replace('/\bFeb\.\b/i', 'Februar', $cleanText);
        $cleanText = preg_replace('/\bApr\.\b/i', 'April', $cleanText);
        $cleanText = preg_replace('/\bAug\.\b/i', 'August', $cleanText);
        $cleanText = preg_replace('/\bSep\.\b/i', 'September', $cleanText);
        $cleanText = preg_replace('/\bOkt\.\b/i', 'Oktober', $cleanText);
        $cleanText = preg_replace('/\bNov\.\b/i', 'November', $cleanText);
        $cleanText = preg_replace('/\bDez\.\b/i', 'Dezember', $cleanText);

        // 6. Common Abbreviations that TTS struggles with
        $cleanText = str_replace(['z.B.', 'z. B.'], 'zum Beispiel', $cleanText);
        $cleanText = str_replace(['bzw.', 'bzw'], 'beziehungsweise', $cleanText);
        $cleanText = str_replace(['evtl.', 'evtl'], 'eventuell', $cleanText);
        $cleanText = str_replace(['bzgl.', 'bzgl'], 'bezüglich', $cleanText);
        $cleanText = preg_replace('/\b(ca\.)\b/', 'zirka', $cleanText);
        $cleanText = preg_replace('/\b(max\.)\b/', 'maximal', $cleanText);
        $cleanText = preg_replace('/\b(min\.)\b/', 'minimal', $cleanText);

        // 7. Fix missing spaces after commas or periods (ElevenLabs skips breaths if no space)
        // WICHTIG: Ersetze das NUR, wenn danach ein Buchstabe kommt (sonst zerreißt es Daten wie "13. März" oder Kommazahlen).
        $cleanText = preg_replace('/([,.?!])([A-Za-zÄÖÜäöüß])/', '$1 $2', $cleanText);
        $cleanText = preg_replace('/\s+/', ' ', $cleanText);

        // 8. Aggressiv alle Halluzinationen entfernen, in denen die KI behauptet, sie könne nicht sprechen
        // Die LLMs entschuldigen sich oft ungefragt dafür, dass sie "nur Text" generieren können. Wir löschen diesen Kram.
        $cleanText = preg_replace('/\([^\)]*(Text ausgeben|Audionachricht|kann das nur als Text|Sprachnachricht|Sprachausgabe|nicht sprechen|als Text wiedergeben)[^\)]*\)/i', '', $cleanText);
        $cleanText = preg_replace('/Ich kann das (leider )?nur als Text ausgeben.*?/i', '', $cleanText);
        $cleanText = preg_replace('/Ich (kann|darf) (?:\w+ )?(?:keine |nicht als )?(?:Sprachnachricht|Audionachricht)en? (?:senden|ausgeben).*?/i', '', $cleanText);
        
        return trim($cleanText);
    }
}
