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
     * Explizite Whitelist harmloser deutscher & Shop-Begriffe (Scunthorpe-Schutz fuer Wortstamm-Pruefung).
     *
     * @var array<int, string>
     */
    private const SAFE_GERMAN_WORDS = [
        'barsch', 'barsche', 'barschen', 'marsch', 'maersche', 'marsche', 'marschieren',
        'abmarsch', 'einmarsch', 'durchmarsch', 'aufmarsch', 'rueckmarsch', 'ruckmarsch', 'vormarsch',
        'herrschaft', 'herrschaften', 'herrschaftlich', 'nachbarschaft', 'nachbarschaften',
        'wirtschaft', 'wirtschaftlich', 'wissenschaft', 'wissenschaftlich', 'freundschaft', 'freundschaftlich',
        'gesellschaft', 'gesellschaftlich', 'landschaft', 'landschaften', 'mannschaft', 'mannschaften',
        'leidenschaft', 'leidenschaftlich', 'meisterschaft', 'meisterschaften', 'kundschaft', 'bereitschaft',
        'botschaft', 'eigenschaft', 'eigenschaften', 'gemeinschaft', 'erbschaft', 'verwandtschaft', 'partnerschaft',
        'kirsche', 'kirschen', 'hirsch', 'hirsche', 'bursche', 'burschen', 'frosch', 'froesche', 'frosche',
        'wunsch', 'wuensche', 'wunsche', 'wuenschen', 'wunschen', 'mensch', 'menschen', 'menschlich',
        'tisch', 'tische', 'fisch', 'fische', 'frisch', 'frische', 'frischer', 'frisches', 'fleisch',
        'busch', 'buesche', 'busche', 'dusche', 'duschen', 'tasche', 'taschen', 'flasche', 'flaschen',
        'asche', 'masche', 'maschen', 'waschen', 'waesche', 'wasche', 'falsch', 'falsche', 'falscher',
        'schmuck', 'schmuckstueck', 'schmuckstuck', 'schmuckstuecke', 'schmuckstucke', 'schmuckbox',
        'klassiker', 'klasse', 'klassen', 'klassisch', 'klassische', 'klassischer', 'klassisches',
        'dick', 'dicke', 'dicker', 'dickes', 'dicken', 'dickem', 'dickicht',
        'geschickt', 'geschickte', 'geschickter', 'schicken', 'schick', 'schicke', 'schicker', 'schickes',
        'schickt', 'schickte', 'geschick', 'abgeschickt', 'zugeschickt', 'verschicken', 'verschickt',
        'nicken', 'nickt', 'blicken', 'blick', 'blickt', 'augenblick', 'klicken', 'klick', 'klickt', 'anklicken',
        'ticken', 'tickt', 'sticken', 'bestickt', 'stricken', 'gestrickt', 'flicken', 'rucksack',
        'druecken', 'drucken', 'drueckt', 'druckt', 'ausdruck', 'eindruck', 'beeindruckend',
        'ruecken', 'rucken', 'bruecke', 'brucke', 'bruecken', 'brucken',
        'stueck', 'stuck', 'stuecke', 'stucke', 'meisterstueck', 'meisterstuck', 'einzelstueck', 'einzelstuck',
        'glueck', 'gluck', 'gluecklich', 'glucklich', 'glueckwunsch', 'gluckwunsch',
        'zurueck', 'zuruck', 'verrueckt', 'verruckt', 'entzueckend', 'entzuckend', 'schmuecken', 'schmucken',
        'bitte', 'bitten', 'mitte', 'mitten', 'mutter', 'muetter', 'butter', 'futter',
        'ritter', 'gewitter', 'gitter', 'bitter', 'splitter', 'glitzer', 'glitzert', 'zittern', 'dritte', 'dritter',
        'analyse', 'analysen', 'analysieren', 'analysiert', 'analog', 'analoge',
        'kanal', 'kanaele', 'kanale', 'signal', 'signale', 'original', 'originale',
        'pokal', 'pokale', 'lokal', 'lokale', 'egal', 'schal', 'wahl', 'wahlen', 'auswahl',
        'zahl', 'zahlen', 'anzahl', 'strahl', 'strahlen', 'saal', 'saele', 'sale',
        'ideal', 'ideale', 'material', 'kristall', 'kristalle', 'kristallglas', 'metall', 'edelstahl',
        'portal', 'portale', 'regal', 'regale', 'denkmal', 'merkmal', 'normal', 'optimal', 'genial',
        'weniger', 'wenig', 'wenige', 'wenigstens', 'koenig', 'konig', 'koenigin', 'konigin', 'honig', 'ewig',
        'schein', 'scheine', 'scheinen', 'scheint', 'wahrscheinlich', 'sonnenschein', 'mondschein',
        'scheibe', 'scheiben', 'glasscheibe', 'bescheid', 'bescheiden',
        'entscheiden', 'entscheidung', 'entscheidungen', 'entschieden',
        'unterschied', 'unterschiede', 'unterscheiden', 'unterschiedlich',
        'abschied', 'verschieden', 'verschiedene', 'verschiedenen',
        'geschenk', 'geschenke', 'geschenkbox', 'geschenkpapier', 'geschenkgutschein', 'geschenkgutscheine',
        'gutschein', 'gutscheine', 'schenken', 'geschenkt',
        'atelier', 'helden', 'held', 'heldin', 'heldenhalle', 'ehrenplatz', 'podest', 'podeste',
        'seelenfunke', 'seelenfunken', 'funken', 'funkenwerk', 'funki', 'funkis', 'louvre',
        'gravur', 'gravuren', 'lasergravur', 'manufaktur', 'gifhorn', 'kollektion', 'unikat', 'unikate',
        'heiltrank', 'heiltraenke', 'heiltranke', 'heilung', 'heilen',
        'schoen', 'schon', 'schoene', 'schone', 'schoenes', 'schones', 'schoener', 'schoner',
        'wunderschoen', 'wunderschon', 'danke', 'dankeschoen', 'dankeschon', 'willkommen', 'hallo', 'super',
    ];

    /**
     * Exakte gesperrte Einzelwoerter nach Normalisierung (DE & EN Beleidigungen, Alltagsabwertungen, Plurale, Hassrede, NSFW).
     *
     * @var array<int, string>
     */
    private const BLOCKED_TOKENS = [
        // Deutsch: Harte Beleidigungen, Plurale & Beugungen
        'arsch', 'arsche', 'aersche', 'arschloch', 'arschlocher', 'arschloecher', 'arschgeige', 'arschgeigen',
        'arschkriecher', 'arschgesicht', 'arschgesichter', 'arschkopf', 'arschkrampe',
        'hure', 'huren', 'hurre', 'hurren', 'hurensohn', 'hurensohne', 'hurensoehne', 'huensohn', 'huan',
        'hundesohn', 'hundesohne', 'hundesoehne', 'huso', 'husos', 'hs',
        'fotze', 'fotzen', 'votze', 'votzen', 'schlampe', 'schlampen', 'nutte', 'nutten',
        'wichser', 'wichsers', 'wixxer', 'wixer', 'wichsen', 'wichse', 'wixe', 'wixen',
        'fick', 'ficken', 'ficker', 'fickst', 'fickt', 'gefickt', 'verfickt', 'verfickte', 'verfickter',
        'scheisse', 'scheiss', 'scheis', 'scheisser', 'beschissen', 'beschissene', 'beschissener',
        'kacke', 'kacken', 'kackvogel', 'kackvoegel', 'kackbratze', 'kackhaufen', 'verkackt',
        'pisser', 'pissers', 'pisse', 'pissen', 'verpiss', 'verpisst', 'verpissen',
        'bastard', 'bastarde', 'missgeburt', 'missgeburten', 'mistgeburt', 'mistgeburten', 'fehlgeburt', 'fehlgeburten',
        'drecksau', 'drecksaeue', 'drecksaue', 'dreckskerl', 'dreckskerle', 'drecksack', 'dreckssack',
        'dreckig', 'dreckige', 'dreckiger', 'mistkerl', 'mistkerle', 'miststueck', 'miststuck', 'mistvieh',
        'spast', 'spasti', 'spastis', 'spasten', 'spastiker', 'spacko', 'spackos', 'spacken', 'spack',
        'mongo', 'mongos', 'vollidiot', 'vollidioten', 'idiot', 'idioten', 'idiotisch',
        // Deutsch: Alltagsbeleidigungen & Abwertungen
        'dumm', 'dumme', 'dummer', 'dummes', 'dummen', 'duemmer', 'dummkopf', 'dummkoepfe', 'dummkopfe', 'dummbatz',
        'bloed', 'blod', 'bloede', 'blode', 'bloeder', 'bloder', 'bloedes', 'blodes', 'bloedmann', 'blodmann', 'bloedian', 'blodian',
        'depp', 'deppen', 'trottel', 'volltrottel', 'vollpfosten', 'pfosten', 'vollhorst',
        'penner', 'opfer', 'lappen', 'lauch', 'assi', 'assis', 'asozial', 'asozialer', 'asoziale',
        'behindert', 'behinderte', 'behinderter', 'behindertes', 'behinderten',
        'haesslich', 'hasslich', 'haessliche', 'hassliche', 'haesslicher', 'hasslicher',
        'stinkst', 'stinkt', 'stinker', 'schnauze', 'maul', 'fresse', 'hdf',
        'lutscher', 'schwanzlutscher', 'kotze', 'kotzen',
        'abschaum', 'gesindel', 'untermensch', 'untermenschen',
        'schwachkopf', 'schwachkoepfe', 'schwachkopfe', 'schwachmat', 'schwachmaten',
        'hohlkopf', 'hohlkoepfe', 'hohlkopfe', 'holzkopf', 'hirnlos', 'hirnlose', 'hirnloser',
        'geisteskrank', 'geisteskranker', 'geisteskranke', 'gestoert', 'gestort', 'gestoerte', 'gestorte', 'gestoerter', 'gestorter',
        'ekelhaft', 'ekelhafte', 'ekelhafter', 'widerlich', 'widerliche', 'widerlicher', 'widerling',
        'versager', 'loser', 'losers', 'looser', 'loosers', 'nichtsnutz', 'taugenichts',
        'affenkopf', 'affenarsch', 'koeter', 'koter',
        'schwuchtel', 'schwuchteln', 'transe', 'transen', 'kanake', 'kanaken', 'kanacke', 'kanacken',
        'neger', 'zigeuner', 'bimbo', 'mord', 'moerder', 'morder', 'suizid', 'amok', 'inzest',
        'nazi', 'nazis', 'hitler', 'nsdap', 'gestapo', 'ss', 'sa', 'kz', 'heil',
        // Englisch
        'fuck', 'fck', 'fuk', 'fucker', 'fuckers', 'fucking', 'fucked', 'mofo', 'motherfucker', 'motherfuckers',
        'shit', 'shitty', 'bullshit', 'dipshit', 'horseshit',
        'bitch', 'bitches', 'btch', 'bitchy', 'whore', 'whores', 'slut', 'sluts', 'cunt', 'cunts',
        'dickhead', 'dickheads', 'cock', 'cocks', 'pussy', 'pussies',
        'asshole', 'assholes', 'arsehole', 'arseholes', 'jackass', 'dumbass', 'fatass',
        'retard', 'retards', 'retarded', 'moron', 'morons', 'stupid', 'scumbag', 'scumbags',
        'douche', 'douchebag', 'douchebags', 'trash', 'bastards', 'idiots',
        'faggot', 'faggots', 'fag', 'fags', 'dyke', 'nigger', 'niggers', 'nigga', 'niggas', 'negro',
        'chink', 'kike', 'twat', 'twats', 'wanker', 'wankers', 'prick', 'pricks',
        'stfu', 'gtfo', 'kys',
        'blowjob', 'handjob', 'cum', 'cumshot', 'porn', 'porno', 'hentai', 'sex', 'penis', 'vagina', 'anal', 'dildo', 'boobs', 'tits',
        'rape', 'rapist', 'pedo', 'paedo', 'pedophile', 'incest', 'suicide', 'isis', 'daesh', 'jihad', 'kkk', 'swastika',
    ];

    /**
     * Eindeutige Schimpfwort-Staemme am Wortanfang (greifen auf normalisierte Tokens, sofern nicht in SAFE_GERMAN_WORDS).
     *
     * @var array<int, string>
     */
    private const BLOCKED_TOKEN_PREFIXES = [
        'arsch',
        'huren',
        'fotz',
        'votz',
        'wichs',
        'wix',
        'fick',
        'scheiss',
        'scheis',
        'kack',
        'drecks',
        'mistkerl',
        'miststueck',
        'miststuck',
        'missgeburt',
        'mistgeburt',
        'fehlgeburt',
        'vollidiot',
        'dummkopf',
        'vollpfosten',
        'schwachkopf',
        'hohlkopf',
        'spast',
        'spack',
        'schlamp',
        'schwuchtel',
        'huso',
        'motherfuck',
        'fuck',
        'bullshit',
        'asshole',
        'dickhead',
        'bitch',
    ];

    /**
     * Eindeutige Schimpfwort-Staemme am Wortende fuer deutsche Komposita (sofern nicht in SAFE_GERMAN_WORDS).
     *
     * @var array<int, string>
     */
    private const BLOCKED_TOKEN_SUFFIXES = [
        'arschloch',
        'arschlocher',
        'arschloecher',
        'hurensohn',
        'hurensohne',
        'hurensoehne',
        'fotze',
        'fotzen',
        'votze',
        'votzen',
        'schlampe',
        'schlampen',
        'nutte',
        'nutten',
        'wichser',
        'wixxer',
        'pisser',
        'spasti',
        'fresse',
        'idiot',
        'idioten',
        'bastard',
        'bastarde',
        'missgeburt',
        'vollpfosten',
        'dummkopf',
        'schwachkopf',
        'hohlkopf',
    ];

    /**
     * Zusammengesetzte Beleidigungs-Phrasen auf Satzebene (mit Wortgrenzen geprueft).
     *
     * @var array<int, string>
     */
    private const BLOCKED_PHRASES = [
        'halt die klappe',
        'halt deine klappe',
        'klappe halten',
        'halts maul',
        'halt s maul',
        'halt das maul',
        'halt dein maul',
        'maul halten',
        'fresse halten',
        'halt die fresse',
        'halt deine fresse',
        'fick dich',
        'fickt euch',
        'verpiss dich',
        'verpisst euch',
        'geh sterben',
        'geht sterben',
        'bring dich um',
        'du hund',
        'ihr hunde',
        'du affe',
        'du opfer',
        'du lappen',
        'du lauch',
        'bist du dumm',
        'seid ihr dumm',
        'wie dumm',
        'so dumm',
        'fuck you',
        'fuck off',
        'shut the fuck up',
        'kill yourself',
        'suck my dick',
        'eat a dick',
        'big dick',
    ];

    /**
     * Eindeutige Hochrisiko-Teilstrings, die auch ohne Wortgrenzen (z. B. zusammengeschrieben) hart blockiert werden.
     *
     * @var array<int, string>
     */
    private const BLOCKED_SUBSTRINGS = [
        'hurensohn', 'hurensohne', 'arschloch', 'arschlocher', 'arschgesicht',
        'missgeburt', 'mistgeburt', 'fehlgeburt', 'schwanzlutscher',
        'kackbratze', 'vollpfosten', 'dreckskerl', 'dummkopf', 'schwachkopf', 'hohlkopf',
        'haltdiefresse', 'haltdieklappe', 'haltsmaul', 'fickdich', 'verpissdich', 'gehsterben',
        'motherfuck', 'dickhead', 'faggot', 'nigger', 'nigga',
        'heilhitler', 'siegheil', 'hakenkreuz', 'swastika', 'auschwitz', 'holocaust', 'treblinka',
        'waffenss', 'wehrmacht', 'reichsburger', 'reichsbuerger', 'whitepower', 'kuklux',
        'vergewaltig', 'kinderfick', 'childabuse', 'schoolshoot', 'killall', 'bringdichum',
        'killyourself', 'terrorist', 'blowjob', 'cumshot', 'onlyfans', 'pornhub', 'xvideos',
    ];

    /** @var array<string, int>|null */
    private static ?array $safeWordsLookup = null;

    /** @var array<string, int>|null */
    private static ?array $blockedTokensLookup = null;

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
        $safeSet = self::$safeWordsLookup ??= array_flip(self::SAFE_GERMAN_WORDS);
        $blockedSet = self::$blockedTokensLookup ??= array_flip(self::BLOCKED_TOKENS);

        if (!empty($tokens)) {
            $dedupTokens = [];
            $foldedTokens = [];
            foreach ($tokens as $tok) {
                $d2 = preg_replace('/([a-z])\1{2,}/u', '$1$1', $tok) ?? $tok;
                $dedupTokens[] = $d2;
                $foldedTokens[] = self::foldGermanUmlautDigraphs($d2);
            }

            $sentenceRaw = ' ' . implode(' ', $tokens) . ' ';
            $sentenceDedup = ' ' . implode(' ', $dedupTokens) . ' ';
            $sentenceFolded = ' ' . implode(' ', $foldedTokens) . ' ';

            foreach (self::BLOCKED_PHRASES as $phrase) {
                $needle = ' ' . $phrase . ' ';
                if (
                    str_contains($sentenceRaw, $needle)
                    || str_contains($sentenceDedup, $needle)
                    || str_contains($sentenceFolded, $needle)
                ) {
                    return true;
                }
            }
        }

        foreach ($tokens as $token) {
            if (isset($safeSet[$token])) {
                continue;
            }

            $foldedToken = self::foldGermanUmlautDigraphs($token);
            if (isset($safeSet[$foldedToken])) {
                continue;
            }

            // Exakt, auf Doppelbuchstaben reduziert ("puuuussy" -> "pussy") und auf Einzelbuchstaben reduziert ("huuure" -> "hure")
            $dedupDouble = preg_replace('/([a-z])\1{2,}/u', '$1$1', $token) ?? $token;
            $dedupSingle = preg_replace('/([a-z])\1+/u', '$1', $token) ?? $token;

            if (isset($safeSet[$dedupDouble])) {
                continue;
            }

            $foldedDouble = self::foldGermanUmlautDigraphs($dedupDouble);
            if (isset($safeSet[$foldedDouble])) {
                continue;
            }

            $foldedSingle = self::foldGermanUmlautDigraphs($dedupSingle);

            foreach ([$token, $foldedToken, $dedupDouble, $foldedDouble, $dedupSingle, $foldedSingle] as $candidate) {
                if (!isset($safeSet[$candidate]) && isset($blockedSet[$candidate])) {
                    return true;
                }
            }

            foreach ([$token, $foldedToken, $dedupDouble, $foldedDouble] as $stemCandidate) {
                if (isset($safeSet[$stemCandidate])) {
                    continue;
                }

                $len = strlen($stemCandidate);
                if ($len >= 4) {
                    foreach (self::BLOCKED_TOKEN_PREFIXES as $prefix) {
                        if (str_starts_with($stemCandidate, $prefix)) {
                            return true;
                        }
                    }
                }

                if ($len >= 5) {
                    if (
                        str_ends_with($stemCandidate, 'arsch')
                        && !str_ends_with($stemCandidate, 'marsch')
                        && !str_ends_with($stemCandidate, 'barsch')
                    ) {
                        return true;
                    }

                    foreach (self::BLOCKED_TOKEN_SUFFIXES as $suffix) {
                        if (str_ends_with($stemCandidate, $suffix)) {
                            return true;
                        }
                    }
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

    private static function foldGermanUmlautDigraphs(string $word): string
    {
        if ($word === '') {
            return '';
        }

        return str_replace(['ae', 'oe', 'ue'], ['a', 'o', 'u'], $word);
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
