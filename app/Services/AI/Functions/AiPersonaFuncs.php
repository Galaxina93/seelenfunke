<?php

namespace App\Services\AI\Functions;

use Illuminate\Support\Facades\Log;

trait AiPersonaFuncs
{
    public static function getAiPersonaFuncsSchema(): array
    {
        return [
            [
                'name' => 'system_visualize_persona_profile',
                'description' => 'Visualisiert ein detailliertes Personenprofil / einen Steckbrief einer gesuchten Person (z.B. nach einer Websuche) in der UI.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'name' => ['type' => 'string', 'description' => 'Voller Name der Person'],
                        'aliases' => ['type' => 'string', 'description' => 'Spitznamen, Titel oder Rollen (z.B. Parteivorsitzender, Schauspieler)'],
                        'status' => ['type' => 'string', 'description' => 'Aktueller Status (z.B. "Aktiv", "Verstorben", "Im Amt")'],
                        'origin' => ['type' => 'string', 'description' => 'Herkunft / Geburtsort'],
                        'birth_date' => ['type' => 'string', 'description' => 'Geburtsdatum'],
                        'image_url' => ['type' => 'string', 'description' => 'Eine URL zu einem passenden Porträt-/Profilbild der Person.'],
                        'summary' => ['type' => 'string', 'description' => 'Eine kurze Geheimdienst-Zusammenfassung (Executive Summary) der Person.'],
                        'career_timeline' => [
                            'type' => 'array',
                            'items' => [
                                'type' => 'object',
                                'properties' => [
                                    'year' => ['type' => 'string'],
                                    'event' => ['type' => 'string']
                                ]
                            ],
                            'description' => 'Wichtige Meilensteine in der Karriere oder im Leben.'
                        ],
                        'known_associates' => [
                            'type' => 'array',
                            'items' => ['type' => 'string'],
                            'description' => 'Bekannte Partner, Verbündete oder politische Weggefährten.'
                        ]
                    ],
                    'required' => ['name', 'summary']
                ],
                'callable' => [self::class, 'executeSystemVisualizePersonaProfile']
            ],
            [
                'name' => 'system_generate_persona_pdf_and_mail',
                'description' => 'Generiert eine druckfertige Personenakte / ein Profil (PDF) der Person und sendet es optional per E-Mail.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'name' => ['type' => 'string'],
                        'aliases' => ['type' => 'string'],
                        'status' => ['type' => 'string'],
                        'origin' => ['type' => 'string'],
                        'birth_date' => ['type' => 'string'],
                        'image_url' => ['type' => 'string'],
                        'summary' => ['type' => 'string'],
                        'career_timeline' => [
                            'type' => 'array',
                            'items' => [
                                'type' => 'object',
                                'properties' => [
                                    'year' => ['type' => 'string'],
                                    'event' => ['type' => 'string']
                                ]
                            ]
                        ],
                        'known_associates' => [
                            'type' => 'array',
                            'items' => ['type' => 'string']
                        ],
                        'target_action' => [
                            'type' => 'string',
                            'description' => 'Was soll mit dem PDF passieren? "download" oder "email".',
                            'enum' => ['download', 'email']
                        ],
                        'recipient_email' => [
                            'type' => 'string',
                            'description' => 'E-Mail-Adresse, falls "email" gewählt.'
                        ]
                    ],
                    'required' => ['name', 'summary', 'target_action']
                ],
                'callable' => [self::class, 'executeSystemGeneratePersonaPdf']
            ]
        ];
    }

    public static function executeSystemVisualizePersonaProfile(array $args)
    {
        return [
            'status' => 'success',
            'message' => "Das Personenprofil für '{$args['name']}' wurde visualisiert. Frage den Nutzer, ob er das Profil/die Akte auch als PDF exportiert haben möchte (nutze dafür system_generate_persona_pdf_and_mail).",
            '_frontend_event' => [
                'name' => 'ai-show-persona',
                'detail' => [
                    'payload' => $args
                ]
            ]
        ];
    }

    public static function executeSystemGeneratePersonaPdf(array $args)
    {
        $name = $args['name'] ?? 'Unbekannt';
        $action = $args['target_action'] ?? 'download';
        $recipient = $args['recipient_email'] ?? null;
        $agentName = session('current_ai_agent_name', 'System');

        try {
            $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('pdf.persona_dossier', [
                'persona' => $args
            ]);

            $dir = storage_path('app/public/dossiers');
            if (!file_exists($dir)) {
                mkdir($dir, 0755, true);
            }

            $filename = 'profil_' . \Illuminate\Support\Str::slug($name) . '_' . time() . '.pdf';
            $filePath = $dir . '/' . $filename;
            $pdf->save($filePath);

            $downloadUrl = url('storage/dossiers/' . $filename);

            if ($action === 'email') {
                if (empty($recipient)) {
                    $recipient = shop_setting('company_email') ?: shop_setting('owner_email') ?: config('mail.from.address') ?: 'kontakt@mein-seelenfunke.de';
                }

                \Illuminate\Support\Facades\Mail::to($recipient)->send(new \App\Mail\AiHolidayPlanMail(
                    "Personenprofil: $name",
                    "Anbei finden Sie die angeforderte Personenakte.",
                    $agentName,
                    [$filePath]
                ));

                return [
                    'status' => 'success',
                    'message' => "Die Akte (PDF) wurde erfolgreich generiert und per E-Mail an $recipient versendet!",
                    'file_name' => $filename,
                ];
            } else {
                return [
                    'status' => 'success',
                    'message' => 'Die Akte (PDF) wurde erfolgreich generiert!',
                    'pdf_url' => $downloadUrl,
                    'file_name' => $filename,
                    'note' => 'Das Dokument wurde generiert und kann vom Nutzer heruntergeladen werden.'
                ];
            }
        } catch (\Exception $e) {
            Log::error("PDF Generation Error (Persona): " . $e->getMessage());
            return ['status' => 'error', 'message' => 'Fehler beim Erstellen der Akte: ' . $e->getMessage()];
        }
    }
}
