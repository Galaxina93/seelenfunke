<?php

namespace App\Services\AI\Functions;

use App\Models\Marketing\MarketingNewsletter;
use App\Models\Marketing\MarketingNewsletterSubscriber;
use Carbon\Carbon;

trait AiMarketingNewsletterFuncs
{
    public static function getAiMarketingNewsletterFuncsSchema(): array
    {
        return [
            [
                'name' => 'marketing_newsletter_get_subscribers',
                'description' => 'Gibt eine Liste aller Abonnenten des Newsletters zurück. Optional filterbar nach einer E-Mail Adresse.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'email_search' => [
                            'type' => 'string',
                            'description' => 'Optional: Suchbegriff für die E-Mail Adresse'
                        ]
                    ]
                ],
                'callable' => [self::class, 'marketing_newsletter_get_subscribers']
            ],
            [
                'name' => 'marketing_newsletter_add_subscriber',
                'description' => 'Fügt einen neuen Abonnenten zur in-house Newsletter Liste hinzu.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'email' => [
                            'type' => 'string',
                            'description' => 'Die E-Mail Adresse des neuen Abonnenten'
                        ]
                    ],
                    'required' => ['email']
                ],
                'callable' => [self::class, 'marketing_newsletter_add_subscriber']
            ],
            [
                'name' => 'marketing_newsletter_get_campaigns',
                'description' => 'Gibt alle Newsletter Kampagnen (sowohl aktive als auch archivierte) zurück.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'is_active' => [
                            'type' => 'boolean',
                            'description' => 'Wenn true, werden nur aktive Kampagnen zurückgegeben. Wenn false, nur archivierte. Optional.'
                        ]
                    ]
                ],
                'callable' => [self::class, 'marketing_newsletter_get_campaigns']
            ],
            [
                'name' => 'marketing_newsletter_create_automated_campaign',
                'description' => 'Erstellt eine neue E-Mail Kampagne für ein wiederkehrendes Jahres-Event.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'target_event_key' => [
                            'type' => 'string',
                            'description' => 'Schlüssel des Feiertags: valentines, womens_day, easter, mothers_day, fathers_day, halloween, advent_1, christmas, new_year, sale_summer, sale_winter.'
                        ],
                        'subject' => [
                            'type' => 'string',
                            'description' => 'Der Betreff der E-Mail.'
                        ],
                        'content' => [
                            'type' => 'string',
                            'description' => 'Der HTML-Inhalt der E-Mail. Verwende Platzhalter wie {first_name}.'
                        ],
                        'days_offset' => [
                            'type' => 'integer',
                            'description' => 'Wie viele Tage VOR dem eigentlichen Ereignis soll versendet werden? (z.B. 14).'
                        ]
                    ],
                    'required' => ['target_event_key', 'subject', 'content', 'days_offset']
                ],
                'callable' => [self::class, 'marketing_newsletter_create_automated_campaign']
            ],
            [
                'name' => 'marketing_newsletter_create_manual_campaign',
                'description' => 'Erstellt eine neue, einmalige manuelle Sonder-Kampagne zu einem fixen Versanddatum.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'title' => [
                            'type' => 'string',
                            'description' => 'Der interne Name der Kampagne.'
                        ],
                        'subject' => [
                            'type' => 'string',
                            'description' => 'Der Betreff der E-Mail.'
                        ],
                        'content' => [
                            'type' => 'string',
                            'description' => 'Der HTML-Inhalt der E-Mail.'
                        ],
                        'send_at' => [
                            'type' => 'string',
                            'description' => 'Zeitpunkt des Versands im YYYY-MM-DD HH:MM Format.'
                        ]
                    ],
                    'required' => ['title', 'subject', 'content', 'send_at']
                ],
                'callable' => [self::class, 'marketing_newsletter_create_manual_campaign']
            ],
            [
                'name' => 'marketing_newsletter_toggle_archive',
                'description' => 'Archiviert (deaktiviert) oder reaktiviert eine Kampagne basierend auf ihrer ID.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'id' => [
                            'type' => 'integer',
                            'description' => 'ID der Kampagne (Newsletter Model).'
                        ],
                        'is_active' => [
                            'type' => 'boolean',
                            'description' => 'Setze false zum Archivieren, true zum Reaktivieren.'
                        ]
                    ],
                    'required' => ['id', 'is_active']
                ],
                'callable' => [self::class, 'marketing_newsletter_toggle_archive']
            ]
        ];
    }

    public static function marketing_newsletter_get_subscribers(array $args)
    {
        $query = MarketingNewsletterSubscriber::query();
        if (isset($args['email_search']) && !empty($args['email_search'])) {
            $query->where('email', 'like', '%' . $args['email_search'] . '%');
        }

        $subs = $query->limit(50)->get(['id', 'email', 'is_verified', 'created_at']);
        return [
            'status' => 'success',
            'count' => $subs->count(),
            'subscribers' => $subs->toArray()
        ];
    }

    public static function marketing_newsletter_add_subscriber(array $args)
    {
        if (empty($args['email']) || !filter_var($args['email'], FILTER_VALIDATE_EMAIL)) {
            return ['status' => 'error', 'message' => 'Ungültige Email Adresse.'];
        }

        if (MarketingNewsletterSubscriber::where('email', $args['email'])->exists()) {
            return ['status' => 'error', 'message' => 'Diese E-Mail-Adresse ist bereits eingetragen.'];
        }

        $sub = MarketingNewsletterSubscriber::create([
            'email' => $args['email'],
            'is_verified' => true,
        ]);

        return ['status' => 'success', 'message' => 'Abonnent hinzugefügt.', 'id' => $sub->id];
    }

    public static function marketing_newsletter_get_campaigns(array $args)
    {
        $query = MarketingNewsletter::query();
        if (isset($args['is_active'])) {
            $query->where('is_active', $args['is_active']);
        }

        $campaigns = $query->get(['id', 'type', 'title', 'target_event_key', 'subject', 'days_offset', 'send_at', 'is_active']);
        return [
            'status' => 'success',
            'count' => $campaigns->count(),
            'campaigns' => $campaigns->toArray()
        ];
    }

    public static function marketing_newsletter_create_automated_campaign(array $args)
    {
        if (MarketingNewsletter::where('target_event_key', $args['target_event_key'])->where('is_active', true)->exists()) {
            return ['status' => 'error', 'message' => 'Es existiert bereits eine aktive Kampagne für dieses Ereignis.'];
        }

        $template = MarketingNewsletter::create([
            'type' => 'automated',
            'title' => ucfirst(str_replace('_', ' ', $args['target_event_key'])) . ' Kampagne',
            'target_event_key' => $args['target_event_key'],
            'subject' => $args['subject'],
            'content' => $args['content'],
            'days_offset' => $args['days_offset'],
            'is_active' => true
        ]);

        return ['status' => 'success', 'message' => 'Automatisierte Kampagne erstellt.', 'id' => $template->id];
    }

    public static function marketing_newsletter_create_manual_campaign(array $args)
    {
        try {
            $sendAt = Carbon::parse($args['send_at']);
        } catch (\Exception $e) {
            return ['status' => 'error', 'message' => 'Ungültiges Datumsformat für send_at. Nutze YYYY-MM-DD HH:MM'];
        }

        $template = MarketingNewsletter::create([
            'type' => 'manual',
            'title' => $args['title'],
            'target_event_key' => null,
            'subject' => $args['subject'],
            'content' => $args['content'],
            'send_at' => $sendAt,
            'days_offset' => 0,
            'is_active' => true
        ]);

        return ['status' => 'success', 'message' => 'Manuelle Kampagne erstellt.', 'id' => $template->id];
    }

    public static function marketing_newsletter_toggle_archive(array $args)
    {
        $campaign = MarketingNewsletter::find($args['id']);
        if (!$campaign) {
            return ['status' => 'error', 'message' => 'Kampagne nicht gefunden.'];
        }

        $campaign->is_active = $args['is_active'];
        $campaign->save();

        return [
            'status' => 'success',
            'message' => 'Kampagnen-Status aktualisiert auf: ' . ($args['is_active'] ? 'Aktiv' : 'Archiviert')
        ];
    }
}
