<?php

namespace App\Models\Product;


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
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class ProductTemplate extends Model implements HasSystemBlueprint
{
    
    use InteractsWithSystemBlueprint;

    /**
     * Digitales Architektur- & Compliance-Typenschild (Living Documentation).
     */
    public static function blueprint(): ModelBlueprint
    {
        return ModelBlueprint::make(
            label: '2D/3D-Gravurvorlage (Feiertags- & Anlass-Preset)',
            description: 'Vorkonfigurierte Text-, Schrift- und Vektor-Layouts für den 1-Klick-Start im Produktkonfigurator.',
            department: SystemDepartment::Product,
            navigationNodes: ['admin.products.templates', 'shop.product.configurator'],
            lifecycle: LifecycleBehavior::SeasonalPerpetual,
            sensitivity: DataSensitivity::PublicCatalog,
            retention: RetentionPolicy::PermanentMasterData,
            aiAccess: AiAccessPolicy::ReadAndPropose,
            actorsRead: [ActorScope::PublicVisitor, ActorScope::Customer, ActorScope::Admin],
            actorsWrite: [ActorScope::Admin],
            regulations: [ComplianceRegulation::EU_AI_Act_Art50],
            externalChannels: [ExternalChannel::LaserMachine_SVG_PDF],
            dependsOnSettings: [],
            isGdprArt15Exportable: false,
            customerForeignKey: null,
            containsProductionAssets: false,
            assetColumns: [],
        );
    }

use HasFactory, HasUuids, SoftDeletes;

    /**
     * Die Tabelle, die mit dem Model verknüpft ist.
     *
     * @var string
     */
    protected $table = 'product_templates';

    /**
     * Die Attribute, die massenweise zugewiesen werden können.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'product_id',
        'name',
        'configuration',
        'is_active',
        'holiday',
        'preview_image',
        'is_ai_generated',
    ];

    /**
     * Die Attribute, die in native Typen gecastet werden sollen.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'configuration' => 'array',
        'is_active' => 'boolean',
        'is_ai_generated' => 'boolean',
    ];

    /**
     * Kalender-gebundene Feiertage und Jahreszeiten inkl. case-insensitiver Synonyme und Umlaute.
     *
     * @var array<string, array<int, string>>
     */
    public const SEASONAL_HOLIDAY_SLOTS = [
        'Weihnachten'   => ['weihnachten', 'advent', 'advent_1', 'christmas', 'xmas', 'nikolaus'],
        'Ostern'        => ['ostern', 'easter'],
        'Valentinstag'  => ['valentinstag', 'valentines', 'valentine'],
        'Muttertag'     => ['muttertag', 'mothers_day'],
        'Vatertag'      => ['vatertag', 'fathers_day', 'himmelfahrt', 'christi himmelfahrt', 'christi_himmelfahrt'],
        'Halloween'     => ['halloween'],
        'Neujahr'       => ['silvester', 'neujahr', 'new_year'],
        'Weltfrauentag' => ['weltfrauentag', 'womens_day', 'frauentag'],
        'Einschulung'   => ['einschulung', 'schulanfang'],
        'Frühling'      => ['frühling', 'fruehling', 'spring'],
        'Sommer'        => ['sommer', 'summer', 'sale_summer'],
        'Herbst'        => ['herbst', 'autumn', 'fall'],
        'Winter'        => ['winter', 'sale_winter'],
    ];

    /**
     * Kanonische Jahreszeiten-Schlüssel (Teilmenge von SEASONAL_HOLIDAY_SLOTS).
     *
     * @var array<int, string>
     */
    public const SEASONAL_SEASON_KEYS = ['Frühling', 'Sommer', 'Herbst', 'Winter'];

    /**
     * Zeitlose Ganzjahres-Klassiker und neutrale Werte (365 Tage im Jahr relevant).
     *
     * @var array<int, string>
     */
    public const EVERGREEN_OCCASION_SLOTS = [
        'geburtstag',
        'birthday',
        'hochzeit',
        'wedding',
        'verlobung',
        'jubilaeum',
        'jubiläum',
        'jahrestag',
        'taufe',
        'geburt',
        'trauer',
        'gedenken',
        'freundschaft',
        'liebe',
        'dankeschön',
        'dankeschoen',
        '',
        'kein feiertag',
        'kein_feiertag',
        'none',
        'null',
        'allgemein',
        'standard',
        'neutral',
    ];

    /**
     * Klassifiziert einen Anlass-/Feiertagswert in 'holiday', 'season' oder 'evergreen'.
     * Frei vergebene individuelle Anlassnamen, die kein gesperrter Kalender-Feiertag und keine
     * Jahreszeit sind, gelten automatisch als 'evergreen' (ganzjährig sichtbar).
     *
     * @return array{type: string, canonical: ?string}
     */
    public static function classifyHolidaySlot(?string $rawHoliday): array
    {
        if ($rawHoliday === null) {
            return ['type' => 'evergreen', 'canonical' => null];
        }

        $trimmed = trim($rawHoliday);
        $norm = preg_replace('/\s+/', ' ', mb_strtolower($trimmed)) ?? '';

        if ($norm === '' || in_array($norm, ['kein feiertag', 'kein_feiertag', 'none', 'null', 'allgemein', 'standard', 'neutral'], true)) {
            return ['type' => 'evergreen', 'canonical' => null];
        }

        foreach (self::SEASONAL_HOLIDAY_SLOTS as $canonical => $aliases) {
            if (in_array($norm, $aliases, true)) {
                $type = in_array($canonical, self::SEASONAL_SEASON_KEYS, true) ? 'season' : 'holiday';

                return [
                    'type' => $type,
                    'canonical' => $canonical,
                ];
            }
        }

        return [
            'type' => 'evergreen',
            'canonical' => $trimmed,
        ];
    }

    /**
     * Prüft, ob ein Anlasswert ein kalender-gebundener Feiertag oder eine Jahreszeit ist.
     */
    public static function isSeasonalHolidaySlot(?string $rawHoliday): bool
    {
        return in_array(self::classifyHolidaySlot($rawHoliday)['type'], ['holiday', 'season'], true);
    }

    /**
     * Prüft, ob diese Vorlage einem saisonalen Feiertag oder einer Jahreszeit zugeordnet ist.
     */
    public function isSeasonalSlot(): bool
    {
        return self::isSeasonalHolidaySlot($this->holiday);
    }

    /**
     * Berechnet die Storefront-Priorität eines Anlasses zum Referenzzeitpunkt:
     * - 10: Aktiver Haupt-Feiertag (bzw. nächster bevorstehender Feiertag im <= 35-Tage-Vorlauf)
     * - 15: Sekundärer aktiver/bevorstehender Feiertag im Vorlauf
     * - 20: Aktuelle Jahreszeit (season_slot)
     * - 30: Zeitloser Ganzjahres-Klassiker (Geburtstag, Hochzeit, Taufe, neutral usw.)
     * - 40: Außerhalb der Saison liegender Feiertag / fremde Jahreszeit
     */
    public static function resolveHolidaySlotPriority(?string $rawHoliday, ?\Carbon\CarbonInterface $referenceDate = null): int
    {
        $classified = self::classifyHolidaySlot($rawHoliday);

        if ($classified['type'] === 'evergreen') {
            return 30;
        }

        $now = $referenceDate
            ? \Carbon\Carbon::instance($referenceDate)->copy()
            : \Carbon\Carbon::now()->copy();

        $ctx = Product::resolveActivePrimarySeasonalSlot($now);
        $canonical = (string) $classified['canonical'];

        if ($classified['type'] === 'season') {
            return ($canonical === ($ctx['season_slot'] ?? '')) ? 20 : 40;
        }

        $primaryHolidaySlot = ($ctx['group'] ?? '') === 'holiday' ? ($ctx['slot'] ?? null) : null;
        $upcomingHolidaySlot = $ctx['upcoming_holiday_slot'] ?? null;

        // 1. Weihnachten: nahtlos ab 01.11. bis einschließlich 26.12. 23:59:59
        if ($canonical === 'Weihnachten') {
            $inNovDecWindow = ($now->month === 11) || ($now->month === 12 && $now->day <= 26);
            if ($inNovDecWindow || $primaryHolidaySlot === 'Weihnachten' || $upcomingHolidaySlot === 'Weihnachten') {
                return ($primaryHolidaySlot === null || $primaryHolidaySlot === 'Weihnachten') ? 10 : 15;
            }

            return 40;
        }

        // 2. Silvester / Neujahr: 27.12. 00:00:00 bis 08.01. 23:59:59
        if ($canonical === 'Neujahr') {
            $inNewYearWindow = ($now->month === 12 && $now->day >= 27) || ($now->month === 1 && $now->day <= 8);
            if ($inNewYearWindow) {
                return ($primaryHolidaySlot === null || $primaryHolidaySlot === 'Neujahr') ? 10 : 15;
            }

            return 40;
        }

        // 3. Einschulung: Spätsommer 15.07. 00:00:00 bis 30.09. 23:59:59
        if ($canonical === 'Einschulung') {
            $inSchoolStartWindow = ($now->month === 7 && $now->day >= 15) || $now->month === 8 || $now->month === 9;
            if ($inSchoolStartWindow) {
                return ($primaryHolidaySlot === null && $upcomingHolidaySlot === null) ? 10 : 15;
            }

            return 40;
        }

        // 4. Primärer aktiver oder nächster bevorstehender Feiertag laut Product::resolveActivePrimarySeasonalSlot()
        if ($primaryHolidaySlot === $canonical) {
            return 10;
        }
        if ($upcomingHolidaySlot === $canonical) {
            return $primaryHolidaySlot === null ? 10 : 15;
        }

        // 5. 35-Tage-Vorlauf für alle Kalender-Feiertage aus dem HolidayOfferAutopilotService
        $eventKeyMap = [
            'Valentinstag'  => 'valentines',
            'Weltfrauentag' => 'womens_day',
            'Ostern'        => 'easter',
            'Muttertag'     => 'mothers_day',
            'Vatertag'      => 'fathers_day',
            'Halloween'     => 'halloween',
        ];

        $eventKey = $eventKeyMap[$canonical] ?? null;
        if ($eventKey !== null) {
            try {
                $autopilot = app(\App\Services\Marketing\HolidayOfferAutopilotService::class);
                foreach ([$now->year, $now->year - 1, $now->year + 1] as $yr) {
                    $eventDate = $autopilot->getHolidayDate($eventKey, $yr);
                    if (!$eventDate) {
                        continue;
                    }
                    $winStart = $eventDate->copy()->subDays(35)->startOfDay();
                    $winEnd = ($eventKey === 'easter')
                        ? $eventDate->copy()->addDays(1)->endOfDay()
                        : $eventDate->copy()->endOfDay();

                    if ($now->between($winStart, $winEnd)) {
                        return ($primaryHolidaySlot === null && $upcomingHolidaySlot === null) ? 10 : 15;
                    }
                }
            } catch (\Throwable $e) {
                // Sicherheits-Fallback bei fehlendem Service-Kontext
            }
        }

        return 40;
    }

    /**
     * Prüft, ob ein gegebener Anlass-/Feiertags-Slot aktuell im Shop relevant ist.
     */
    public static function isHolidaySlotCurrentlyRelevant(?string $rawHoliday, ?\Carbon\CarbonInterface $referenceDate = null): bool
    {
        return self::resolveHolidaySlotPriority($rawHoliday, $referenceDate) < 40;
    }

    /**
     * Prüft, ob diese Design-Vorlage zum Referenzzeitpunkt saisonal bzw. ganzjährig relevant ist.
     */
    public function isCurrentlyRelevant(?\Carbon\CarbonInterface $referenceDate = null): bool
    {
        return self::isHolidaySlotCurrentlyRelevant($this->holiday, $referenceDate);
    }

    /**
     * Filtert und sortiert Produktvorlagen (ProductTemplate-Instanzen oder Arrays) verkaufspsychologisch für den Shop:
     * 1) Aktiver/kommender Haupt-Feiertag (Platz 1)
     * 2) Aktuelle Jahreszeit (Platz 2)
     * 3) Zeitlose Ganzjahres-Klassiker wie Geburtstag & Hochzeit (Platz 3)
     * 4) Außerhalb der Saison liegende Feiertage (Platz 4, nur wenn $includeAllSeasonal === true)
     *
     * @param  iterable<int, self|array<string, mixed>>  $templates
     * @return array<int, mixed>
     */
    public static function filterAndSortForStorefront(
        iterable $templates,
        bool $includeAllSeasonal = false,
        ?\Carbon\CarbonInterface $referenceDate = null
    ): array {
        $decorated = [];
        $index = 0;

        foreach ($templates as $tpl) {
            $holiday = $tpl instanceof self
                ? $tpl->holiday
                : (is_array($tpl) ? ($tpl['holiday'] ?? null) : null);

            $priority = self::resolveHolidaySlotPriority(is_string($holiday) ? $holiday : null, $referenceDate);

            if (!$includeAllSeasonal && $priority >= 40) {
                $index++;
                continue;
            }

            $decorated[] = [
                'priority' => $priority,
                'index'    => $index,
                'item'     => $tpl,
            ];
            $index++;
        }

        usort($decorated, static fn (array $a, array $b): int => [$a['priority'], $a['index']] <=> [$b['priority'], $b['index']]);

        return array_column($decorated, 'item');
    }

    /**
     * Zählt, wie viele aktive Saison-Vorlagen einer Liste aktuell außerhalb der Saison liegen.
     *
     * @param  iterable<int, self|array<string, mixed>>  $templates
     */
    public static function countHiddenSeasonalTemplates(iterable $templates, ?\Carbon\CarbonInterface $referenceDate = null): int
    {
        $hidden = 0;

        foreach ($templates as $tpl) {
            $holiday = $tpl instanceof self
                ? $tpl->holiday
                : (is_array($tpl) ? ($tpl['holiday'] ?? null) : null);

            if (self::resolveHolidaySlotPriority(is_string($holiday) ? $holiday : null, $referenceDate) >= 40) {
                $hidden++;
            }
        }

        return $hidden;
    }

    /**
     * Löst einen relativen Storage-Pfad eines Vorlagenbildes in eine Cache-sichere URL (mit ?v=filemtime) auf.
     */
    public static function resolveImageUrl(?string $path): ?string
    {
        if (empty($path)) {
            return null;
        }

        if (\Illuminate\Support\Str::startsWith($path, ['http://', 'https://'])) {
            return $path;
        }

        if (\Illuminate\Support\Str::startsWith($path, ['shop/', 'shopverwaltung/images/'])) {
            $abs = public_path($path);
            $ver = is_file($abs) ? (int) @filemtime($abs) : 1;
            return asset($path) . '?v=' . $ver;
        }

        $clean = ltrim(preg_replace('/\?.*$/', '', $path), '/');
        if (\Illuminate\Support\Str::startsWith($clean, 'storage/')) {
            $clean = substr($clean, strlen('storage/'));
        }

        $abs = storage_path('app/public/' . $clean);
        $ver = is_file($abs) ? (int) @filemtime($abs) : 1;

        return \Illuminate\Support\Facades\Storage::disk('public')->url($clean) . '?v=' . $ver;
    }

    /**
     * Holt die korrekte öffentliche URL für das Vorschaubild.
     */
    public function getPreviewImageUrlAttribute(): ?string
    {
        return self::resolveImageUrl($this->preview_image);
    }

    /**
     * Holt das Produkt, zu dem diese Vorlage gehört.
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id');
    }
}
