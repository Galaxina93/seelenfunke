<?php

namespace App\Models\Marketing;

use App\Models\Product\Product;
use App\Models\Product\ProductTemplate;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class MarketingLandingPage extends Model
{
    use HasUuids;

    protected $fillable = [
        'product_id',
        'slug',
        'title',
        'headline',
        'sales_copy',
        'cta_text',
        'status',
    ];

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * Erzeugt intelligente, produktindividuelle Standard-Marketingtexte anhand von Produkttyp,
     * Material, Konfigurator-Einstellungen und Beschreibung.
     */
    public static function generateDefaultCopy(Product $product): array
    {
        $name = trim((string) $product->name);
        $slug = trim((string) $product->slug);
        $type = (string) ($product->type ?: 'physical');
        $isPersonalizable = $product->isPersonalizable();
        $settings = is_array($product->configurator_settings) ? $product->configurator_settings : [];
        $attributes = is_array($product->attributes) ? $product->attributes : [];
        $material = mb_strtolower((string) ($attributes['Material'] ?? ($settings['material_type'] ?? '')));
        $overlayType = (string) ($settings['overlay_type'] ?? 'plane');
        $hasBackSide = !empty($settings['has_back_side']);

        // 1. Geschenkgutschein
        if ($slug === 'geschenkgutschein') {
            return [
                'title'      => $name . ' | Mein-Seelenfunke Manufaktur',
                'headline'   => 'Freude schenken mit Stil: Der Seelenfunken Wertgutschein',
                'sales_copy' => $product->description ?: 'Verschenke unvergessliche Momente mit unserem edlen Seelenfunken Geschenkgutschein. Wähle flexibel deinen Wunschbetrag, füge eine persönliche Widmung hinzu und erhalte ihn sofort digital per E-Mail oder als edle Klappkarte mit Wachssiegel.',
                'cta_text'   => 'Jetzt Wunschgutschein gestalten',
            ];
        }

        // 2. Bekannte Manufaktur-Hauptprodukte mit fein abgestimmtem Copywriting
        if ($slug === 'seelen-kristall' || str_contains($name, 'Kristall')) {
            return [
                'title'      => $name . ' | Personalisiertes K9-Kristallglas aus der Manufaktur',
                'headline'   => 'Magische Lichtbrechung für die Ewigkeit: ' . $name,
                'sales_copy' => $product->description ?: 'Halte besondere Momente für die Ewigkeit fest – mit einem meisterhaft geschliffenen K9-Kristall. Dank unserer präzisen Lasergravur und 3D-Live-Vorschau entsteht dein unverwechselbares Unikat in edler Geschenkbox.',
                'cta_text'   => 'Jetzt persönliches Unikat gestalten',
            ];
        }

        if ($slug === 'seelen-anhaenger' || str_contains($name, 'Anhänger')) {
            return [
                'title'      => $name . ' | Massives Aluminium-Herz mit beidseitiger Wunschgravur',
                'headline'   => 'Wahre Zuneigung, die man jeden Tag fühlen kann: ' . $name,
                'sales_copy' => $product->description ?: 'Der Seelenanhänger verkörpert pure Emotion in greifbarer Form. Massives, handschmeichelndes Aluminium mit hochpräziser beidseitiger Lasergravur für Namen, Daten, Symbole oder Koordinaten.',
                'cta_text'   => 'Jetzt Wunschgravur konfigurieren',
            ];
        }

        if ($slug === 'weizenglas-personalisiert' || str_contains($name, 'Feierabend')) {
            return [
                'title'      => $name . ' | Weizenglas mit 360° Rundum-Lasergravur',
                'headline'   => 'Kröne den wohlverdienten Feierabend mit deinem Unikat: ' . $name,
                'sales_copy' => $product->description ?: 'Ein klassisch geformtes Kristall-Weizenbierglas, das durch eine beeindruckende, 100% spülmaschinenfeste 360° Rundum-Lasergravur dein persönliches Highlight oder das perfekte Vereins- & Männergeschenk wird.',
                'cta_text'   => 'Jetzt Glas im 3D-Konfigurator gestalten',
            ];
        }

        // 3. Dynamische Adaption für alle weiteren & neu angelegten Produkte
        if ($type === 'digital') {
            $headline = 'Sofort verfügbar & praxiserprobt: ' . $name;
            $cta = 'Jetzt Sofort-Download sichern';
            $fallbackCopy = "Sichere dir {$name} als digitales Produkt mit direktem Sofort-Download nach deiner Bestellung – komplett versandkostenfrei und auf allen Endgeräten nutzbar.";
        } elseif ($type === 'service') {
            $headline = 'Persönliche Manufaktur-Kompetenz: ' . $name;
            $cta = 'Jetzt Service & Beratung anfragen';
            $fallbackCopy = "Profitiere bei {$name} von direkter fachlicher Betreuung aus unserer Manufaktur in Gifhorn – individuell abgestimmt auf dein Projekt.";
        } elseif ($isPersonalizable) {
            if ($overlayType === 'cylinder') {
                $headline = 'Dein persönliches Unikat mit 360°-Lasergravur: ' . $name;
                $cta = $product->three_d_active ? 'Jetzt im 3D-Konfigurator gestalten' : 'Jetzt Rundum-Gravur gestalten';
            } elseif ($hasBackSide) {
                $headline = 'Beidseitig individuell für dich graviert: ' . $name;
                $cta = 'Jetzt Vorder- & Rückseite gestalten';
            } elseif (str_contains($material, 'glas') || str_contains($material, 'kristall') || str_contains($material, 'glass')) {
                $headline = 'Edle Glas- & Kristallveredelung nach Wunsch: ' . $name;
                $cta = 'Jetzt persönliches Unikat gestalten';
            } elseif (str_contains($material, 'metall') || str_contains($material, 'alu') || str_contains($material, 'edelstahl') || str_contains($material, 'metal')) {
                $headline = 'Spürbare Wertigkeit mit deiner Wunschgravur: ' . $name;
                $cta = 'Jetzt Metall-Unikat personalisieren';
            } elseif (str_contains($material, 'schiefer') || str_contains($material, 'holz') || str_contains($material, 'natur')) {
                $headline = 'Charakterstarkes Natur-Unikat mit Lasergravur: ' . $name;
                $cta = 'Jetzt Natur-Unikat personalisieren';
            } else {
                $headline = 'Handveredeltes Manufaktur-Unikat: ' . $name;
                $cta = $product->three_d_active ? 'Jetzt im 3D-Konfigurator gestalten' : 'Jetzt online personalisieren';
            }

            $fallbackCopy = "Gestalte {$name} direkt online mit deinem Wunschtext oder Motiv. Jedes Einzelstück wird in unserer Manufaktur in Gifhorn mit höchster Laser-Präzision veredelt und sorgfältig verpackt.";
        } else {
            $headline = 'Exklusiv aus unserer Manufaktur: ' . $name;
            $cta = 'Jetzt im Shop entdecken & bestellen';
            $fallbackCopy = "Entdecke {$name} in geprüfter Manufaktur-Qualität aus Gifhorn – liebevoll gefertigt, sorgfältig kontrolliert und schnell zu dir nach Hause geliefert.";
        }

        $salesCopy = trim((string) ($product->description ?: $product->short_description ?: $fallbackCopy));

        return [
            'title'      => ($product->seo_title ?: $name) . ' | Mein-Seelenfunke',
            'headline'   => $headline,
            'sales_copy' => $salesCopy,
            'cta_text'   => $cta,
        ];
    }

    /**
     * Stellt sicher, dass für ein Produkt eine passende MarketingLandingPage existiert
     * und hält Entwurfs-Slugs/Texte bei neu angelegten Produkten automatisch synchron.
     */
    public static function syncForProduct(Product $product, bool $forceRegenerateCopy = false): ?self
    {
        $rawSlug = trim((string) $product->slug);
        if ($rawSlug === '' || str_starts_with($rawSlug, 'draft-')) {
            return null;
        }

        $defaults = self::generateDefaultCopy($product);
        $existing = self::where('product_id', $product->id)->first();

        if (!$existing) {
            $targetSlug = $rawSlug;
            if (self::where('slug', $targetSlug)->exists()) {
                $targetSlug = $rawSlug . '-' . substr((string) $product->id, 0, 6);
            }

            return self::create([
                'product_id' => $product->id,
                'slug'       => $targetSlug,
                'title'      => $defaults['title'],
                'headline'   => $defaults['headline'],
                'sales_copy' => $defaults['sales_copy'],
                'cta_text'   => $defaults['cta_text'],
                'status'     => $product->status === 'active' ? 'active' : 'draft',
            ]);
        }

        $needsCopyUpdate = $forceRegenerateCopy
            || empty(trim((string) $existing->headline))
            || empty(trim((string) $existing->sales_copy))
            || str_contains((string) $existing->title, 'Neues Produkt ')
            || str_contains((string) $existing->headline, 'Neues Produkt ')
            || str_starts_with((string) $existing->headline, 'Entdecke jetzt:');

        $updates = [];

        if ($needsCopyUpdate) {
            $updates['title'] = $defaults['title'];
            $updates['headline'] = $defaults['headline'];
            $updates['sales_copy'] = $defaults['sales_copy'];
            $updates['cta_text'] = $defaults['cta_text'];
        }

        if (str_starts_with((string) $existing->slug, 'draft-') && !self::where('slug', $rawSlug)->where('id', '!=', $existing->id)->exists()) {
            $updates['slug'] = $rawSlug;
        }

        if (!empty($updates)) {
            $existing->update($updates);
        }

        return $existing;
    }

    /**
     * Erzeugt das dynamische Darstellungs-Profil (Badges, 3 Highlight-Kacheln, Story-Punkte, CTA-Hinweise)
     * passend zu Produkttyp, Material, Eigenschaften, Jahreszeit und aktiven Vorlagen.
     */
    public static function buildDynamicProfile(
        Product $product,
        ?array $heroImageMeta = null,
        ?ProductTemplate $activeSeasonalTemplate = null
    ): array {
        $slug = (string) $product->slug;
        $type = (string) ($product->type ?: 'physical');
        $isVoucher = $slug === 'geschenkgutschein';
        $isDigital = $type === 'digital' && !$isVoucher;
        $isService = $type === 'service';
        $isPhysical = $type === 'physical' && !$isVoucher;
        $isPersonalizable = $product->isPersonalizable();
        $has3d = (bool) ($product->three_d_active ?? false);

        $settings = is_array($product->configurator_settings) ? $product->configurator_settings : [];
        $attributes = array_filter(is_array($product->attributes) ? $product->attributes : [], fn($v) => !empty($v));
        $materialAttr = (string) ($attributes['Material'] ?? '');
        $materialLower = mb_strtolower($materialAttr . ' ' . ($settings['material_type'] ?? ''));
        $overlayType = (string) ($settings['overlay_type'] ?? 'plane');
        $hasBackSide = !empty($settings['has_back_side']);
        $templatesCount = $product->relationLoaded('templates')
            ? $product->templates->count()
            : $product->templates()->where('is_active', true)->count();

        $seasonalHolidayName = $activeSeasonalTemplate?->holiday ?: null;

        // 1. Hero Badge & Mini-Badges
        if ($isVoucher) {
            $heroBadge = $heroImageMeta['label'] && !str_contains((string) $heroImageMeta['label'], 'Geschenkgutschein im schwarz')
                ? '✨ ' . $heroImageMeta['label']
                : 'Flexibler Wertgutschein • Sofort-PDF oder Wachssiegel-Post';
            $heroMiniBadges = [
                ['icon' => 'gift', 'title' => '10 € – 250 € frei wählbar', 'subtitle' => 'Flexibler Wunschbetrag'],
                ['icon' => 'bolt', 'title' => 'Sofort-PDF & Postkarte', 'subtitle' => 'Für jede Gelegenheit'],
                ['icon' => 'heart', 'title' => 'Persönliche Widmung', 'subtitle' => 'Individueller Grußtext'],
            ];
            $highlightsSubtitle = 'Ob als spontanes Last-Minute-Geschenk per E-Mail oder als edle Klappkarte mit Wachssiegel – der Seelenfunken Gutschein passt immer.';
            $highlights = [
                [
                    'icon'  => 'sparkles',
                    'title' => 'Wunschbetrag & Grußtext',
                    'text'  => 'Wähle deinen Wert flexibel zwischen 10 € und 250 € und hinterlege deine ganz persönliche Botschaft direkt auf dem Gutschein.',
                ],
                [
                    'icon'  => 'mail',
                    'title' => 'Sofort-Download oder Postversand',
                    'text'  => 'Erhalte den Gutschein unmittelbar nach der Zahlung als druckfertiges PDF oder bestelle ihn als hochwertige Klappkarte mit Wachssiegel.',
                ],
                [
                    'icon'  => 'shield',
                    'title' => 'Für alle Unikate einlösbar',
                    'text'  => 'Volle 3 Jahre gültig (§ 195 BGB) und flexibel für unser gesamtes Sortiment aus Kristallglas, Metall und Geschenksets einlösbar.',
                ],
            ];
            $storyKicker = 'Schenken ohne Risiko';
            $storyHeadline = 'Wenn der Beschenkte sein Unikat selbst gestalten darf';
            $storyPoints = [
                'Frei wählbarer Gutscheinwert zwischen 10,00 € und 250,00 €',
                'Auf Wunsch sofort als PDF zum Ausdrucken (perfekt für Last-Minute)',
                'Restguthaben bleibt flexibel für spätere Bestellungen erhalten',
                'Passende saisonale Gutschein-Motive für Feste & besondere Anlässe',
            ];
            $ctaSubtext = 'Sofort per E-Mail verfügbar • Optional als edle Postkarte • 3 Jahre gültig';
        } elseif ($isDigital) {
            $heroBadge = 'Digitales Produkt • Sofort-Download nach Bestellung';
            $heroMiniBadges = [
                ['icon' => 'download', 'title' => 'Sofort-Download', 'subtitle' => 'Direkt nach Zahlung'],
                ['icon' => 'device', 'title' => 'Alle Endgeräte', 'subtitle' => 'Desktop, Tablet & Mobil'],
                ['icon' => 'check', 'title' => 'Versandkostenfrei', 'subtitle' => 'Keine Lieferzeit'],
            ];
            $highlightsSubtitle = 'Direkter Zugriff ohne Wartezeit – entwickelt für maximale Praxisnähe und sofortige Anwendbarkeit.';
            $highlights = [
                [
                    'icon'  => 'download',
                    'title' => 'Sofortiger Direkt-Download',
                    'text'  => 'Unmittelbar nach Abschluss deiner Bestellung stehen dir alle digitalen Dateien und Inhalte ohne Wartezeit bereit.',
                ],
                [
                    'icon'  => 'device',
                    'title' => 'Optimal aufbereitet',
                    'text'  => !empty($attributes['Format'])
                        ? "Geliefert im Format {$attributes['Format']} – übersichtlich strukturiert und auf allen gängigen Endgeräten nutzbar."
                        : 'Übersichtlich strukturiert und flexibel auf PC, Mac, Tablet oder Smartphone einsetzbar.',
                ],
                [
                    'icon'  => 'shield',
                    'title' => '100% Versandkostenfrei',
                    'text'  => 'Rein digitale Bereitstellung über unsere geschützte Download-Infrastruktur – ohne Versandkosten und ohne Umwege.',
                ],
            ];
            $storyKicker = 'Digitales Know-how & Praxis';
            $storyHeadline = 'Durchdachte Inhalte für deinen direkten Vorsprung';
            $storyPoints = [
                !empty($attributes['Format']) ? "Format: {$attributes['Format']}" : 'Sofortiger digitaler Zugriff nach Bestellabschluss',
                !empty($attributes['Seiten']) ? "Umfang: {$attributes['Seiten']} Seiten geballtes Praxiswissen" : 'Jederzeit im Kundenkonto abrufbar',
                !empty($attributes['Assets']) ? "Inklusive: {$attributes['Assets']}" : 'Für alle modernen Endgeräte optimiert',
                'Komplett versandkostenfrei & sofort einsatzbereit',
            ];
            $ctaSubtext = 'Sofort-Download nach Zahlung • 100% Versandkostenfrei • Sichere Kaufabwicklung';
        } elseif ($isService) {
            $heroBadge = 'Exklusive Manufaktur-Dienstleistung • Persönliche Betreuung';
            $heroMiniBadges = [
                ['icon' => 'sparkles', 'title' => 'Experten-Betreuung', 'subtitle' => 'Direkt aus der Manufaktur'],
                ['icon' => 'check', 'title' => 'Individuell geplant', 'subtitle' => 'Auf dein Projekt abgestimmt'],
                ['icon' => 'shield', 'title' => 'Verlässlicher Ablauf', 'subtitle' => 'Transparente Umsetzung'],
            ];
            $highlightsSubtitle = 'Persönliche Beratung und präzise Umsetzung direkt durch unser erfahrenes Manufaktur-Team in Gifhorn.';
            $highlights = [
                [
                    'icon'  => 'sparkles',
                    'title' => 'Direkte Manufaktur-Expertise',
                    'text'  => 'Du sprichst direkt mit unseren Spezialisten in Gifhorn – für maßgeschneiderte Lösungen ohne Umwege.',
                ],
                [
                    'icon'  => 'check',
                    'title' => 'Individuelle Machbarkeitsprüfung',
                    'text'  => 'Wir prüfen deine Anforderungen im Detail und stimmen alle Schritte transparent mit dir ab.',
                ],
                [
                    'icon'  => 'shield',
                    'title' => 'Höchste Qualitätsstandards',
                    'text'  => 'Von Einzelprojekten bis hin zu Vereins-Awards und B2B-Serien garantieren wir ein erstklassiges Ergebnis.',
                ],
            ];
            $storyKicker = 'Service & Sonderanfertigung';
            $storyHeadline = 'Persönliche Begleitung für dein Vorhaben';
            $storyPoints = [
                !empty($attributes['Dauer']) ? "Dauer / Umfang: {$attributes['Dauer']}" : 'Individuelle Abstimmung auf deine Wünsche',
                !empty($attributes['Ort']) ? "Durchführung: {$attributes['Ort']}" : 'Persönliche Betreuung aus unserer Manufaktur',
                'Transparente Konditionen ohne versteckte Kosten',
                'Zuverlässige und termingerechte Realisierung',
            ];
            $ctaSubtext = 'Persönliche Betreuung • Kein Versand nötig • Manufaktur Gifhorn';
        } elseif ($isPersonalizable) {
            if ($seasonalHolidayName) {
                $heroBadge = "✨ Passend zu {$seasonalHolidayName} • Live im Konfigurator personalisierbar";
            } elseif ($has3d) {
                $heroBadge = 'Ab 1 Stück personalisierbar • Mit interaktivem 3D-Konfigurator';
            } else {
                $heroBadge = 'Ab 1 Stück personalisierbar • Handveredelt in Gifhorn';
            }

            // Spezifisches Gravur-/Material-Highlight ermitteln
            if ($overlayType === 'cylinder') {
                $craftTitle = '360° Rundum-Lasergravur';
                $craftText = 'Mittels präziser Laser-Rotationstechnik brennen wir dein Wunschmotiv abriebfest und 100% spülmaschinenfest rund um das Glas ein.';
                $mini1 = ['icon' => 'sparkles', 'title' => '360° Rundum-Gravur', 'subtitle' => '100% spülmaschinenfest'];
            } elseif ($hasBackSide) {
                $craftTitle = 'Beidseitige Präzisionsgravur';
                $craftText = 'Vorder- und Rückseite lassen sich individuell mit Texten, Symbolen, Daten oder Koordinaten dauerhaft im Material verewigen.';
                $mini1 = ['icon' => 'sparkles', 'title' => 'Beidseitig gravierbar', 'subtitle' => 'Vorder- & Rückseite'];
            } elseif (str_contains($materialLower, 'kristall') || str_contains($materialLower, 'glass') || str_contains($materialLower, 'glas')) {
                $craftTitle = $materialAttr ?: 'Brillantes K9-Kristallglas';
                $craftText = 'Hochreines optisches Kristallglas bricht das Licht in edlen Facetten und lässt deine feine Lasergravur eindrucksvoll erstrahlen.';
                $mini1 = ['icon' => 'sparkles', 'title' => 'Feinste Lasergravur', 'subtitle' => $materialAttr ?: 'Optisches Kristallglas'];
            } elseif (str_contains($materialLower, 'alu') || str_contains($materialLower, 'metall') || str_contains($materialLower, 'metal')) {
                $craftTitle = $materialAttr ?: 'Massives Edelmetall-Unikat';
                $craftText = 'Handschmeichelnde Haptik trifft auf kontrastreiche, dauerhaft abriebfeste Lasergravur für einen Begleiter fürs Leben.';
                $mini1 = ['icon' => 'sparkles', 'title' => 'Abriebfeste Lasergravur', 'subtitle' => $materialAttr ?: 'Massives Metall'];
            } else {
                $craftTitle = $materialAttr ? "Edles {$materialAttr}" : 'Hochpräzise Lasergravur';
                $craftText = 'Dein Wunschmotiv wird in unserer Manufaktur dauerhaft, gestochen scharf und abriebfest in das Material eingearbeitet.';
                $mini1 = ['icon' => 'sparkles', 'title' => 'Präzise Lasergravur', 'subtitle' => $materialAttr ?: 'Handveredelt'];
            }

            $heroMiniBadges = [
                $mini1,
                [
                    'icon'     => 'cube',
                    'title'    => $has3d ? 'Live 3D-Konfigurator' : 'Live-Vorschau',
                    'subtitle' => $templatesCount > 0 ? "Inkl. {$templatesCount} Design-Vorlagen" : 'Frei gestaltbar',
                ],
                [
                    'icon'     => 'truck',
                    'title'    => 'Manufaktur Gifhorn',
                    'subtitle' => $attributes['Lieferzeit'] ?? 'Schneller DHL-Versand',
                ],
            ];

            $highlightsSubtitle = "Jedes Exemplar von „{$product->name}“ wird individuell nach deinem Entwurf in unserer deutschen Manufaktur gefertigt.";
            $highlights = [
                [
                    'icon'  => 'sparkles',
                    'title' => $craftTitle,
                    'text'  => $craftText,
                ],
                [
                    'icon'  => 'cube',
                    'title' => $templatesCount > 0 ? 'Anlass-Vorlagen & Live-Vorschau' : 'Intuitiver Live-Konfigurator',
                    'text'  => $templatesCount > 0
                        ? "Wähle aus {$templatesCount} liebevoll gestalteten Anlass-Vorlagen (z. B. für Weihnachten, Ostern, Hochzeit & Geburtstag) oder gestalte dein Unikat komplett frei."
                        : 'Sieh schon während der Eingabe live im Konfigurator, wie deine persönliche Gravur auf dem Produkt wirkt.',
                ],
                [
                    'icon'  => 'shield',
                    'title' => 'Manufaktur-Qualitätskontrolle',
                    'text'  => 'Vor dem Versand wird jedes Unikat in Gifhorn von Hand auf Gravurschärfe geprüft und bruchsicher für dich verpackt.',
                ],
            ];

            $storyKicker = 'Handwerk & Emotion aus Gifhorn';
            $storyHeadline = 'Ein Unikat, das Geschichten für die Ewigkeit erzählt';
            $storyPoints = array_values(array_filter([
                $materialAttr ? "Gefertigt aus: {$materialAttr}" : 'Ausgewählte Premium-Materialien mit brillanter Oberfläche',
                !empty($attributes['Größe']) ? "Abmessungen: {$attributes['Größe']}" : ($hasBackSide ? 'Beidseitig individuell mit Wunschtext & Motiv gravierbar' : 'Individuell mit Wunschtext, Schriftart & Symbolen gestaltbar'),
                !empty($attributes['Pflege']) ? "Pflegehinweis: {$attributes['Pflege']}" : ($has3d ? 'Interaktive 3D-Ansicht aus jedem Blickwinkel vor dem Kauf' : 'Gestochen scharfe, dauerhaft haltbare Laser-Veredelung'),
                'Sorgfältige Endkontrolle & sicherer Versand direkt aus Deutschland',
            ]));
            $ctaSubtext = 'In 2 Minuten online gestaltet • Keine Vorkenntnisse nötig • Versand aus Deutschland';
        } else {
            // Physisches Produkt ohne Personalisierung
            $heroBadge = 'Sofort bestellbar • Geprüfte Manufaktur-Qualität aus Gifhorn';
            $heroMiniBadges = [
                ['icon' => 'sparkles', 'title' => 'Premium Qualität', 'subtitle' => $materialAttr ?: 'Geprüfte Verarbeitung'],
                ['icon' => 'check', 'title' => 'Sofort bestellbar', 'subtitle' => 'Ohne Konfigurationsaufwand'],
                ['icon' => 'truck', 'title' => 'Sicherer Versand', 'subtitle' => $attributes['Lieferzeit'] ?? 'Schnelle Lieferung'],
            ];
            $highlightsSubtitle = "Entdecke „{$product->name}“ – ausgewählt und geprüft in unserer Manufaktur in Gifhorn.";
            $highlights = [
                [
                    'icon'  => 'sparkles',
                    'title' => $materialAttr ? "Hochwertiges {$materialAttr}" : 'Ausgewählte Materialqualität',
                    'text'  => 'Sorgfältig verarbeitet und in unserer Manufaktur auf höchste optische und haptische Güte geprüft.',
                ],
                [
                    'icon'  => 'check',
                    'title' => 'Direkt versandfertig',
                    'text'  => 'Ohne Umwege direkt bestellbar – ideal, wenn du ein fertiges Set oder Zubehör ohne Wartezeit suchst.',
                ],
                [
                    'icon'  => 'shield',
                    'title' => 'Liebevoll & sicher verpackt',
                    'text'  => 'Jede Bestellung wird sorgfältig gepolstert und schnell per DHL mit Sendungsverfolgung verschickt.',
                ],
            ];
            $storyKicker = 'Qualität aus unserer Manufaktur';
            $storyHeadline = 'Mit Liebe zum Detail für dich zusammengestellt';
            $storyPoints = array_values(array_filter([
                $materialAttr ? "Material: {$materialAttr}" : 'Hochwertige Verarbeitung bis ins kleinste Detail',
                !empty($attributes['Größe']) ? "Maße: {$attributes['Größe']}" : 'Manuelle Qualitätsprüfung vor dem Versand',
                'Sichere, stoßgeschützte Verpackung',
                'Schneller Versand direkt aus Gifhorn (Deutschland)',
            ]));
            $ctaSubtext = 'Geprüfte Qualität • Schneller DHL-Versand • Sichere Zahlung';
        }

        return [
            'hero_badge'          => $heroBadge,
            'hero_mini_badges'    => $heroMiniBadges,
            'highlights_subtitle' => $highlightsSubtitle,
            'highlights'          => $highlights,
            'story_kicker'        => $storyKicker,
            'story_headline'      => $storyHeadline,
            'story_points'        => array_slice($storyPoints, 0, 4),
            'cta_subtext'         => $ctaSubtext,
            'valid_attributes'    => $attributes,
        ];
    }
}

