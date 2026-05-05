<?php

namespace App\Services\AI\Functions;

use App\Models\Management\Mail\MailMessage;

trait AiMailFuncs
{
    public static function getAiMailFuncsSchema(): array
    {
        return [
            [
                'name' => 'email_send_message',
                'description' => 'Verfasst eine E-Mail und sendet sie an einen Kontakt oder eine spezifische E-Mail Adresse. Stichworte: Schreib eine Mail an, Sende Nachricht, Antworte auf die Mail, Mail verfassen.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'to_address' => [
                            'type' => 'string',
                            'description' => 'OPTIONAL. Die E-Mail Adresse des Empfängers. WICHTIGE REGEL: Erfinde NIEMALS eine E-Mail-Adresse! Wenn der Nutzer keine explizite E-Mail nennt und du sie nicht aus der Datenbank (z.B. Kontakt- oder Lieferanten-Info) sicher ausgelesen hast, MUSS dieses Feld zwingend LEER (null) bleiben. Das System nutzt dann automatisch die Firmen-E-Mail (kontakt@mein-seelenfunke.de).'
                        ],
                        'subject' => [
                            'type' => 'string',
                            'description' => 'Der Betreff der E-Mail.'
                        ],
                        'body' => [
                            'type' => 'string',
                            'description' => 'Der vollständige Text/Text-Inhalt der E-Mail (Plain Text oder einfaches HTML).'
                        ],
                        'agent_name' => [
                            'type' => 'string',
                            'description' => 'Dein Name (der Name des Agenten, der die E-Mail versendet).'
                        ]
                    ],
                    'required' => ['subject', 'body', 'agent_name']
                ],
                'callable' => [self::class, 'executeSendEmail']
            ],
            [
                'name' => 'mail_list_pending',
                'description' => 'Holt eine Liste aller neuen E-Mails, die noch nicht von der KI verarbeitet wurden (ai_status = pending).',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'limit' => [
                            'type' => 'integer',
                            'description' => 'Maximale Anzahl an Mails, die geholt werden sollen (z.B. 10).'
                        ]
                    ]
                ],
                'callable' => [self::class, 'executeMailListPending']
            ],
            [
                'name' => 'mail_read',
                'description' => 'Liest den vollständigen Inhalt einer E-Mail anhand ihrer ID.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'email_id' => [
                            'type' => 'integer',
                            'description' => 'Die ID der E-Mail.'
                        ]
                    ],
                    'required' => ['email_id']
                ],
                'callable' => [self::class, 'executeMailRead']
            ],
            [
                'name' => 'mail_update_metadata',
                'description' => 'Aktualisiert die Metadaten einer E-Mail (Priorität, Kategorie, Tags und AI-Status). Setze den AI-Status auf "processed", wenn du fertig bist.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'email_id' => [
                            'type' => 'integer',
                            'description' => 'Die ID der E-Mail.'
                        ],
                        'priority' => [
                            'type' => 'string',
                            'description' => 'Die Priorität der E-Mail (low, normal, high).'
                        ],
                        'category' => [
                            'type' => 'string',
                            'description' => 'Die Kategorie der E-Mail (z.B. Rechnung, Support, Anfrage, Spam, Intern).'
                        ],
                        'tags' => [
                            'type' => 'array',
                            'items' => [
                                'type' => 'string'
                            ],
                            'description' => 'Ein Array von Tags (z.B. ["Dringend", "Kunde"]).'
                        ],
                        'ai_status' => [
                            'type' => 'string',
                            'description' => 'Der Verarbeitungsstatus (pending, processed, needs_human_review, replied).'
                        ]
                    ],
                    'required' => ['email_id']
                ],
                'callable' => [self::class, 'executeMailUpdateMetadata']
            ],
            [
                'name' => 'mail_move',
                'description' => 'Verschiebt eine E-Mail in einen bestimmten Ordner (z.B. INBOX, Archive, Junk, Trash, Sent).',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'email_id' => [
                            'type' => 'integer',
                            'description' => 'Die ID der E-Mail.'
                        ],
                        'target_folder' => [
                            'type' => 'string',
                            'description' => 'Der Zielordner (z.B. Junk, Archive, Trash).'
                        ]
                    ],
                    'required' => ['email_id', 'target_folder']
                ],
                'callable' => [self::class, 'executeMoveEmail']
            ],
            [
                'name' => 'mail_bulk_update',
                'description' => 'Aktualisiert die Metadaten (Kategorie, Priorität, Tags, Ordner, Status) für mehrere E-Mails auf einen Schlag.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'updates' => [
                            'type' => 'array',
                            'items' => [
                                'type' => 'object',
                                'properties' => [
                                    'email_id' => ['type' => 'integer', 'description' => 'Die ID der E-Mail.'],
                                    'priority' => ['type' => 'string', 'description' => 'low, normal, high'],
                                    'category' => ['type' => 'string'],
                                    'tags' => ['type' => 'array', 'items' => ['type' => 'string']],
                                    'target_folder' => ['type' => 'string', 'description' => 'z.B. INBOX, Junk'],
                                    'ai_status' => ['type' => 'string', 'description' => 'z.B. processed']
                                ],
                                'required' => ['email_id', 'ai_status']
                            ]
                        ]
                    ],
                    'required' => ['updates']
                ],
                'callable' => [self::class, 'executeMailBulkUpdate']
            ],
            [
                'name' => 'mail_mark_as_read',
                'description' => 'Markiert eine spezifische E-Mail oder alle betreffenden E-Mails als gelesen ODER ungelesen.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'email_id' => [
                            'type' => 'integer',
                            'description' => 'Die ID der E-Mail, die als gelesen/ungelesen markiert werden soll. Lass dies leer, wenn du nach Betreff suchst.'
                        ],
                        'subject' => [
                            'type' => 'string',
                            'description' => 'Der Betreff (Titel) oder ein Teil des Betreffs der E-Mail. Die Suche ist unscharf (fuzzy), findet also auch ähnliche Mails.'
                        ],
                        'all' => [
                            'type' => 'boolean',
                            'description' => 'Setze dies auf true, um alle aktuell zutreffenden E-Mails global zu ändern.'
                        ],
                        'is_read' => [
                            'type' => 'boolean',
                            'description' => 'Setze dies auf true (gelesen) oder false (ungelesen). Standard ist true.'
                        ]
                    ]
                ],
                'callable' => [self::class, 'executeMarkAsRead']
            ]
        ];
    }

    public static function executeSendEmail(array $args)
    {
        try {
            if (empty($args['subject']) || empty($args['body'])) {
                return ['status' => 'error', 'message' => 'Betreff und Text (body) sind erforderlich.'];
            }

            $to = $args['to_address'] ?? null;
            
            // Validate dummy emails
            if ($to && (str_contains(strtolower($to), 'example') || str_contains(strtolower($to), 'test.com') || str_contains(strtolower($to), 'dummy') || str_contains(strtolower($to), 'domain.de') || str_contains(strtolower($to), 'platzhalter'))) {
                return ['status' => 'error', 'message' => 'Ungültige E-Mail Adresse. Bitte erfinde keine Adressen. Lass das Feld to_address zwingend leer (null), wenn du die exakte Adresse nicht kennst.'];
            }

            if (empty($to)) {
                $to = shop_setting('company_email') ?: shop_setting('owner_email') ?: config('mail.from.address') ?: 'kontakt@mein-seelenfunke.de';
            }
            if (empty($to)) {
                return ['status' => 'error', 'message' => 'Keine Empfänger-E-Mail angegeben und keine Standard-E-Mail im System (company_email, owner_email oder mail.from.address) hinterlegt.'];
            }

            $subject = $args['subject'];
            $body = $args['body'];
            $agentName = $args['agent_name'] ?? 'System-Agent';

            \Illuminate\Support\Facades\Mail::to($to)->send(new \App\Mail\AiAgentMessageMail($subject, $body, $agentName));

            return [
                'status' => 'success',
                'message' => "Die E-Mail mit dem Betreff '{$subject}' wurde erfolgreich an {$to} versendet."
            ];
        } catch (\Exception $e) {
            return ['status' => 'error', 'message' => 'E-Mail konnte nicht gesendet werden: ' . $e->getMessage()];
        }
    }

    public static function executeMailListPending(array $args)
    {
        try {
            $limit = $args['limit'] ?? 10;
            $mails = MailMessage::where('ai_status', 'pending')
                ->where('folder', 'INBOX')
                ->orderBy('received_at', 'desc')
                ->limit($limit)
                ->get(['id', 'subject', 'from_name', 'from_email', 'received_at', 'folder']);

            return [
                'status' => 'success',
                'mails' => $mails->toArray(),
                'message' => count($mails) . ' noch nicht verarbeitete Mails gefunden.'
            ];
        } catch (\Exception $e) {
            return ['status' => 'error', 'message' => 'Fehler beim Abrufen der ausstehenden Mails: ' . $e->getMessage()];
        }
    }

    public static function executeMailRead(array $args)
    {
        try {
            if (empty($args['email_id'])) {
                return ['status' => 'error', 'message' => 'email_id fehlt.'];
            }
            $mail = MailMessage::find($args['email_id']);
            if (!$mail) {
                return ['status' => 'error', 'message' => 'Mail nicht gefunden.'];
            }

            return [
                'status' => 'success',
                'id' => $mail->id,
                'subject' => $mail->subject,
                'from_name' => $mail->from_name,
                'from_email' => $mail->from_email,
                'received_at' => $mail->received_at,
                'body_plain' => $mail->body_plain ?? strip_tags($mail->body_html)
            ];
        } catch (\Exception $e) {
            return ['status' => 'error', 'message' => 'Fehler beim Lesen der Mail: ' . $e->getMessage()];
        }
    }

    public static function executeMailUpdateMetadata(array $args)
    {
        try {
            if (empty($args['email_id'])) {
                return ['status' => 'error', 'message' => 'email_id fehlt.'];
            }
            $mail = MailMessage::find($args['email_id']);
            if (!$mail) {
                return ['status' => 'error', 'message' => 'Mail nicht gefunden.'];
            }

            if (isset($args['priority'])) $mail->priority = $args['priority'];
            if (isset($args['category'])) $mail->category = $args['category'];
            if (isset($args['tags'])) $mail->tags = is_array($args['tags']) ? $args['tags'] : [];
            if (isset($args['ai_status'])) $mail->ai_status = $args['ai_status'];

            $mail->save();

            return [
                'status' => 'success',
                'message' => "Metadaten für E-Mail {$mail->id} erfolgreich aktualisiert (AI-Status: {$mail->ai_status}, Prio: {$mail->priority})."
            ];
        } catch (\Exception $e) {
            return ['status' => 'error', 'message' => 'Fehler beim Aktualisieren der Mail: ' . $e->getMessage()];
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

            $mail = MailMessage::find($id);
            if (!$mail) {
                return ['status' => 'error', 'message' => 'Mail nicht gefunden.'];
            }

            $mail->folder = $folder;
            $mail->save();

            return [
                'status' => 'success',
                'message' => "Die E-Mail {$id} wurde erfolgreich in den Ordner '{$folder}' verschoben."
            ];

        } catch (\Exception $e) {
            return ['status' => 'error', 'message' => 'Mails konnte nicht verschoben werden: ' . $e->getMessage()];
        }
    }

    public static function executeMailBulkUpdate(array $args)
    {
        try {
            $updates = $args['updates'] ?? [];
            if (empty($updates) || !is_array($updates)) {
                return ['status' => 'error', 'message' => 'Keine validen Updates übergeben.'];
            }

            $successCount = 0;
            foreach ($updates as $data) {
                if (empty($data['email_id'])) continue;

                $mail = MailMessage::find($data['email_id']);
                if (!$mail) continue;

                if (isset($data['priority'])) $mail->priority = $data['priority'];
                if (isset($data['category'])) $mail->category = $data['category'];
                if (isset($data['tags'])) $mail->tags = is_array($data['tags']) ? $data['tags'] : [];
                if (isset($data['ai_status'])) $mail->ai_status = $data['ai_status'];
                
                if (!empty($data['target_folder']) && $data['target_folder'] !== 'INBOX') {
                    $folderName = $data['target_folder'];
                    $baseFolders = ['Sent', 'Drafts', 'Junk', 'Trash', 'Archive'];
                    
                    if (!in_array($folderName, $baseFolders)) {
                        \App\Models\Management\Mail\MailFolder::firstOrCreate([
                            'mail_account_id' => $mail->mail_account_id,
                            'name' => $folderName
                        ]);
                    }
                    $mail->folder = $folderName;
                }

                $mail->save();
                $successCount++;
            }

            return [
                'status' => 'success',
                'message' => "Erfolgreich {$successCount} E-Mails gebündelt aktualisiert."
            ];

        } catch (\Exception $e) {
            return ['status' => 'error', 'message' => 'Fehler beim Bulk-Update: ' . $e->getMessage()];
        }
    }

    public static function executeMarkAsRead(array $args)
    {
        try {
            $all = $args['all'] ?? false;
            $emailId = $args['email_id'] ?? null;
            $subject = $args['subject'] ?? null;
            $isRead = isset($args['is_read']) ? (bool) $args['is_read'] : true;

            $statusText = $isRead ? 'gelesen' : 'ungelesen';

            if ($all && empty($subject)) {
                $count = MailMessage::where('is_read', !$isRead)->update(['is_read' => $isRead]);
                return [
                    'status' => 'success',
                    'message' => "Erfolgreich {$count} E-Mails als {$statusText} markiert."
                ];
            }

            if ($emailId) {
                $mail = MailMessage::find($emailId);
                if (!$mail) {
                    return ['status' => 'error', 'message' => 'E-Mail nicht gefunden.'];
                }
                $mail->is_read = $isRead;
                $mail->save();
                return [
                    'status' => 'success',
                    'message' => "Die E-Mail {$emailId} wurde als {$statusText} markiert."
                ];
            }

            if (!empty($subject)) {
                $mails = MailMessage::where('is_read', !$isRead)
                    ->where('subject', 'like', '%' . $subject . '%')
                    ->get();

                if ($mails->isEmpty()) {
                    return ['status' => 'error', 'message' => "Keine E-Mails gefunden, die den Suchbegriff '{$subject}' im Betreff enthalten und aktuell den umgekehrten Status haben."];
                }

                $count = 0;
                foreach ($mails as $mail) {
                    $mail->is_read = $isRead;
                    $mail->save();
                    $count++;
                }

                return [
                    'status' => 'success',
                    'message' => "Erfolgreich {$count} E-Mails zum Thema '{$subject}' als {$statusText} markiert."
                ];
            }

            return ['status' => 'error', 'message' => 'Bitte gib entweder eine email_id, einen subject an oder setze all auf true.'];

        } catch (\Exception $e) {
            return ['status' => 'error', 'message' => 'Fehler beim Markieren als gelesen: ' . $e->getMessage()];
        }
    }
}
