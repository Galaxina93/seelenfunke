<?php

namespace App\Services\AI\Functions;

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
                            'description' => 'Die E-Mail Adresse des Empfängers. Leer lassen, um sie an die Standard-Shop-Adresse zu senden.'
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
            if (empty($to)) {
                $to = shop_setting('owner_email');
            }
            if (empty($to)) {
                return ['status' => 'error', 'message' => 'Keine Empfänger-E-Mail angegeben und keine Standard-E-Mail im System (owner_email) hinterlegt.'];
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
