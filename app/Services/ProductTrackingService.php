<?php

namespace App\Services;

use App\Models\Product\ProductAnalyticsEvent;
use Illuminate\Support\Facades\Log;

class ProductTrackingService
{
    /**
     * Core tracking method to write an event into the product_analytics_events table.
     */
    public static function track(
        string $source,
        string $eventName,
        array $params = []
    ): ?ProductAnalyticsEvent {
        try {
            $sessionHash = self::getSessionHash();
            $deviceType = self::detectDeviceType();

            $sanitizedSource = substr(trim($source), 0, 30);
            $sanitizedEventName = substr(trim($eventName), 0, 60);
            $productId = isset($params['product_id']) && is_string($params['product_id']) ? substr(trim($params['product_id']), 0, 36) : null;
            $templateId = isset($params['template_id']) && is_string($params['template_id']) ? substr(trim($params['template_id']), 0, 36) : null;
            $step = isset($params['step']) && is_numeric($params['step']) ? max(0, min(255, (int)$params['step'])) : null;

            $valueCents = null;
            if (isset($params['value_cents']) && is_numeric($params['value_cents'])) {
                $valueCents = max(0, min(2147483647, (int)$params['value_cents']));
            }

            $metaData = isset($params['meta_data']) && is_array($params['meta_data']) ? $params['meta_data'] : null;

            return ProductAnalyticsEvent::create([
                'session_hash'    => $sessionHash,
                'source'          => $sanitizedSource,
                'event_name'      => $sanitizedEventName,
                'step'            => $step,
                'product_id'      => $productId,
                'template_id'     => $templateId,
                'item_identifier' => isset($params['item_identifier']) ? substr((string)$params['item_identifier'], 0, 190) : null,
                'value_cents'     => $valueCents,
                'device_type'     => $deviceType,
                'meta_data'       => $metaData,
                'created_at'      => now(),
            ]);
        } catch (\Throwable $e) {
            // Fail silently in production so user interaction is never interrupted
            Log::warning('ProductTrackingService error: ' . $e->getMessage());
            return null;
        }
    }

    /* --------------------------------------------------------------------------
     * 1. PRODUKTKONFIGURATOR EVENTS
     * -------------------------------------------------------------------------- */

    /**
     * Aufruf der Produktseite mit Konfigurator
     */
    public static function trackConfiguratorView(?string $productId): void
    {
        self::track('configurator', 'configurator_view', [
            'product_id' => $productId,
        ]);
    }

    /**
     * Modus ausgewählt: 'template' (Vorlage nutzen) oder 'custom' (Selbst konfigurieren)
     */
    public static function trackModeChosen(?string $productId, string $mode): void
    {
        self::track('configurator', 'mode_chosen', [
            'product_id'      => $productId,
            'item_identifier' => $mode, // 'template' oder 'custom'
        ]);
    }

    /**
     * Bestimmte Designvorlage ausgewählt
     */
    public static function trackTemplateSelected(?string $productId, ?string $templateId, ?string $templateTitle = null): void
    {
        self::track('configurator', 'template_selected', [
            'product_id'      => $productId,
            'template_id'     => $templateId,
            'item_identifier' => $templateTitle ?? $templateId,
        ]);
    }

    /**
     * Symbol aus der Symbol-Bibliothek hinzugefügt
     */
    public static function trackSymbolAdded(?string $productId, string $symbolFile, ?string $category = null, string $side = 'front'): void
    {
        self::track('configurator', 'symbol_added', [
            'product_id'      => $productId,
            'item_identifier' => $symbolFile,
            'meta_data'       => [
                'category' => $category,
                'side'     => $side,
            ],
        ]);
    }

    /**
     * Eigene Datei hochgeladen (Logo / Foto / SVG / PDF)
     */
    public static function trackFileUploaded(?string $productId, string $extension, ?int $sizeKb = null): void
    {
        self::track('configurator', 'file_uploaded', [
            'product_id'      => $productId,
            'item_identifier' => strtolower($extension),
            'meta_data'       => [
                'size_kb' => $sizeKb,
            ],
        ]);
    }

    /**
     * Upload fehlgeschlagen oder abgebrochen
     */
    public static function trackFileUploadError(?string $productId, string $reason): void
    {
        self::track('configurator', 'file_upload_error', [
            'product_id'      => $productId,
            'item_identifier' => substr($reason, 0, 190),
        ]);
    }

    /**
     * Express-Zuschlag "Eiliges Geschenk" aktiviert oder deaktiviert
     */
    public static function trackExpressToggled(?string $productId, bool $isExpress, int $feeCents = 0, string $source = 'configurator'): void
    {
        self::track($source, 'express_toggled', [
            'product_id'      => $productId,
            'item_identifier' => $isExpress ? 'enabled' : 'disabled',
            'value_cents'     => $isExpress ? $feeCents : 0,
        ]);
    }

    /**
     * Konfiguration erfolgreich abgeschlossen und in den Warenkorb gelegt
     */
    public static function trackConfiguratorSaved(?string $productId, int $totalPriceCents, array $meta = []): void
    {
        self::track('configurator', 'configurator_saved', [
            'product_id'      => $productId,
            'value_cents'     => $totalPriceCents,
            'meta_data'       => $meta,
        ]);
    }

    /**
     * Konfiguration abgebrochen
     */
    public static function trackConfiguratorAbandoned(?string $productId, array $meta = []): void
    {
        self::track('configurator', 'configurator_abandoned', [
            'product_id' => $productId,
            'meta_data'  => $meta,
        ]);
    }

    /* --------------------------------------------------------------------------
     * 2. MENGENRECHNER (/calculator) EVENTS
     * -------------------------------------------------------------------------- */

    /**
     * Aufruf der Seite /calculator (Stufe 0)
     */
    public static function trackCalculatorView(): void
    {
        self::track('calculator', 'calc_view', [
            'step' => 0,
        ]);
    }

    /**
     * Start geklickt & AGB/Hinweis akzeptiert (Stufe 0 -> 1)
     */
    public static function trackCalculatorStarted(): void
    {
        self::track('calculator', 'calc_started', [
            'step' => 1,
        ]);
    }

    /**
     * Produkt im Mengenrechner zur Konfiguration geöffnet (Stufe 1 -> 2)
     */
    public static function trackCalculatorProductOpened(?string $productId): void
    {
        self::track('calculator', 'calc_product_opened', [
            'step'       => 2,
            'product_id' => $productId,
        ]);
    }

    /**
     * Artikel im Rechner konfiguriert und in den Rechner-Warenkorb gelegt (Stufe 2 -> 1)
     */
    public static function trackCalculatorItemSaved(?string $productId, int $qty, int $valueCents): void
    {
        self::track('calculator', 'calc_item_saved', [
            'step'        => 1,
            'product_id'  => $productId,
            'value_cents' => $valueCents,
            'meta_data'   => ['qty' => $qty],
        ]);
    }

    /**
     * Konfiguration im Rechner abgebrochen (Stufe 2 -> 1)
     */
    public static function trackCalculatorItemCanceled(?string $productId): void
    {
        self::track('calculator', 'calc_item_canceled', [
            'step'       => 1,
            'product_id' => $productId,
        ]);
    }

    /**
     * Übergang zum Kontaktformular (Stufe 1 -> 3)
     */
    public static function trackCalculatorFormReached(int $itemsCount, int $totalGrossCents): void
    {
        self::track('calculator', 'calc_form_reached', [
            'step'        => 3,
            'value_cents' => $totalGrossCents,
            'meta_data'   => ['items_count' => $itemsCount],
        ]);
    }

    /**
     * Validierungsfehler im Rechner (z.B. fehlende Pflichtfelder)
     */
    public static function trackCalculatorValidationFailed(int $step, array $errors = []): void
    {
        self::track('calculator', 'calc_validation_failed', [
            'step'      => $step,
            'meta_data' => ['errors' => array_keys($errors)],
        ]);
    }

    /**
     * Preisanfrage erfolgreich abgesendet (Stufe 3 -> 4)
     */
    public static function trackCalculatorQuoteSubmitted(
        string $quoteNumber,
        int $itemsCount,
        int $totalNetCents,
        int $totalGrossCents,
        bool $isExpress
    ): void {
        self::track('calculator', 'calc_quote_submitted', [
            'step'            => 4,
            'item_identifier' => $quoteNumber,
            'value_cents'     => $totalGrossCents,
            'meta_data'       => [
                'net_cents'   => $totalNetCents,
                'items_count' => $itemsCount,
                'is_express'  => $isExpress,
            ],
        ]);
    }

    /**
     * Rechner verlassen vor Abschluss
     */
    public static function trackCalculatorAbandoned(int $step, array $meta = []): void
    {
        self::track('calculator', 'calc_abandoned', [
            'step'      => $step,
            'meta_data' => $meta,
        ]);
    }

    /* --------------------------------------------------------------------------
     * 3. ANONYMISIERUNG & GERÄTE-ERKENNUNG (DSGVO-KONFORM)
     * -------------------------------------------------------------------------- */

    /**
     * Erzeugt einen vollkommen anonymen Session-Hash.
     * Nutzt die PHP/Laravel-Session-ID zusammen mit dem App-Key.
     * Keine IP-Adresse wird gespeichert!
     */
    public static function getSessionHash(): string
    {
        $rawSession = session()->getId();
        if (empty($rawSession)) {
            // Fallback für Headless / API-Aufrufe
            $rawSession = request()->cookie(config('session.cookie')) ?? 'guest_' . request()->ip();
        }

        return hash('sha256', $rawSession . config('app.key'));
    }

    /**
     * Erkennt grob den Gerätetyp (Desktop, Tablet, Mobile) anhand des User-Agents,
     * ohne Personenbezug herzustellen.
     */
    public static function detectDeviceType(): string
    {
        $userAgent = strtolower(request()->userAgent() ?? '');

        if (empty($userAgent)) {
            return 'desktop';
        }

        if (preg_match('/(ipad|tablet|(android(?!.*mobile))|(windows(?!.*phone)(.*touch))|kindle|playbook|silk)/i', $userAgent)) {
            return 'tablet';
        }

        if (preg_match('/(mobile|iphone|ipod|blackberry|opera mini|iemobile|wpdesktop|android)/i', $userAgent)) {
            return 'mobile';
        }

        return 'desktop';
    }
}
