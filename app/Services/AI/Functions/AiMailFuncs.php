<?php

namespace App\Services\AI\Functions;

trait AiMailFuncs
{
    public static function getAiMailFuncsSchema(): array
    {
        return [
            [
                'name' => 'email_get_unread',
                'description' => 'Liest den Posteingang aus und gibt eine Liste aller ungelesenen E-Mails zurück (Betreff, Absender, Datum). Stichworte: Zeig mir meine neuen Mails, Was ist im Postfach, Ungelesene Nachrichten, Check meine E-Mails.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'folder' => [
                            'type' => 'string',
                            'description' => 'Optionaler Ordner (z.B. Inbox, Spam, Archiv). Standard: Inbox',
                            'default' => 'Inbox'
                        ]
                    ]
                ],
                'callable' => [self::class, 'executeGetUnreadEmails']
            ],
            [
                'name' => 'email_read_thread',
                'description' => 'Gibt den kompletten Inhalt/Thread einer spezifischen E-Mail zurück. Führe erst email_get_unread aus, um die ID zu bekommen! Stichworte: Lies die erste Mail vor, Öffne Nachricht von, Zeig mir den Inhalt der Mail.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'email_id' => [
                            'type' => 'string',
                            'description' => 'Die eindeutige ID der E-Mail (erhalten aus email_get_unread).'
                        ]
                    ],
                    'required' => ['email_id']
                ],
                'callable' => [self::class, 'executeReadEmailThread']
            ],
            [
                'name' => 'email_send_message',
                'description' => 'Verfasst eine E-Mail und sendet sie an einen Kontakt oder eine spezifische E-Mail Adresse. Stichworte: Schreib eine Mail an, Sende Nachricht, Antworte auf die Mail, Mail verfassen.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'to_address' => [
                            'type' => 'string',
                            'description' => 'Die E-Mail Adresse des Empfängers.'
                        ],
                        'subject' => [
                            'type' => 'string',
                            'description' => 'Der Betreff der E-Mail.'
                        ],
                        'body' => [
                            'type' => 'string',
                            'description' => 'Der vollständige Text/Text-Inhalt der E-Mail (Plain Text oder einfaches HTML).'
                        ]
                    ],
                    'required' => ['to_address', 'subject', 'body']
                ],
                'callable' => [self::class, 'executeSendEmail']
            ],
            [
                'name' => 'email_move_to_folder',
                'description' => 'Verschiebt eine E-Mail in einen bestimmten Ordner (z.B. Archivieren, Löschen/Papierkorb, Wichtig, Spam). Stichworte: Mail archivieren, Nachricht löschen, ab in den Spam, verschieben nach.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'email_id' => [
                            'type' => 'string',
                            'description' => 'Die ID der zu verschiebenden E-Mail.'
                        ],
                        'target_folder' => [
                            'type' => 'string',
                            'description' => 'Der Zielordner (Archive, Trash, Spam, Important, Inbox).',
                            'enum' => ['Archive', 'Trash', 'Spam', 'Important', 'Inbox']
                        ]
                    ],
                    'required' => ['email_id', 'target_folder']
                ],
                'callable' => [self::class, 'executeMoveEmail']
            ]
        ];
    }

    public static function executeGetUnreadEmails(array $args)
    {
        try {
            $folder = $args['folder'] ?? 'Inbox';
            
            // Dummy Umsetzung für Posteingang der Firmenleitung
            $emails = [
                [
                    'id' => 'mail-883a-9x',
                    'from' => 'julia.schmidt@beispiel-vertrieb.de',
                    'subject' => 'Rückfrage zum Quartalsbericht Q3',
                    'date' => now()->subHours(2)->format('d.m.Y H:i'),
                    'preview' => 'Hallo, ich habe mir die Zahlen angesehen und...'
                ],
                [
                    'id' => 'mail-772b-4y',
                    'from' => 'info@steuerberater-meier.de',
                    'subject' => 'WICHTIG: Fristablauf Umsatzsteuer',
                    'date' => now()->subDay()->format('d.m.Y H:i'),
                    'preview' => 'Sehr geehrte Damen und Herren, bitte...'
                ]
            ];

            return [
                'status' => 'success',
                'folder' => $folder,
                'unread_count' => count($emails),
                'emails' => $emails,
                'message' => 'Du hast ' . count($emails) . ' ungelesene Nachrichten im Ordner ' . $folder . '.'
            ];
        } catch (\Exception $e) {
            return ['status' => 'error', 'message' => 'Fehler beim Laden der Mails: ' . $e->getMessage()];
        }
    }

    public static function executeReadEmailThread(array $args)
    {
        try {
            if (empty($args['email_id'])) {
                return ['status' => 'error', 'message' => 'email_id wird benötigt.'];
            }

            $id = $args['email_id'];

            // Dummy Responses based on ID
            if ($id === 'mail-883a-9x') {
                 $body = "Hallo,\n\nich habe mir die Zahlen angesehen und mir ist aufgefallen, dass im Bereich Marketing die Ausgaben für Ads nicht ganz mit unserem Budget übereinstimmen. Können wir das bitte nächste Woche kurz im Call besprechen?\n\nViele Grüße,\nJulia Schmidt";
            } elseif ($id === 'mail-772b-4y') {
                 $body = "Sehr geehrte Damen und Herren,\n\nbitte denken Sie daran, die erforderlichen Belege für den vergangenen Monat zeitnah hochzuladen. Die Frist für die Umsatzsteuer-Voranmeldung läuft in 3 Tagen ab.\n\nMit freundlichen Grüßen,\nKanzlei Meier & Partner";
            } else {
                 $body = "Die E-Mail konnte nicht geladen werden oder existiert nicht mehr im Eingang.";
            }

            return [
                'status' => 'success',
                'email_id' => $id,
                'full_content' => $body
            ];

        } catch (\Exception $e) {
            return ['status' => 'error', 'message' => 'Fehler beim Laden der Nachricht: ' . $e->getMessage()];
        }
    }

    public static function executeSendEmail(array $args)
    {
        try {
            if (empty($args['to_address']) || empty($args['subject']) || empty($args['body'])) {
                return ['status' => 'error', 'message' => 'Empfänger, Betreff und Text (body) sind erforderlich.'];
            }

            $to = $args['to_address'];
            $subject = $args['subject'];
            $body = $args['body'];

            // Dummy Logik: Würde hier via Laravel Mail::to() oder API versendet werden.
            // Log::info('Email gesendet', ['to' => $to, 'subject' => $subject]);

            return [
                'status' => 'success',
                'message' => "Die E-Mail mit dem Betreff '{$subject}' wurde erfolgreich an {$to} versendet."
            ];
        } catch (\Exception $e) {
            return ['status' => 'error', 'message' => 'Macht konnte nicht gesendet werden: ' . $e->getMessage()];
        }
    }

    public static function executeMoveEmail(array $args)
    {
        try {
             if (empty($args['email_id']) || empty($args['target_folder'])) {
                return ['status' => 'error', 'message' => 'Es fehlen email_id oder target_folder.'];
            }

            $id = $args['email_id'];
            $folder = $args['target_folder'];

            return [
                'status' => 'success',
                'message' => "Die E-Mail {$id} wurde erfolgreich in den Ordner '{$folder}' verschoben."
            ];

        } catch (\Exception $e) {
            return ['status' => 'error', 'message' => 'Mails konnte nicht verschoben werden: ' . $e->getMessage()];
        }
    }
}
