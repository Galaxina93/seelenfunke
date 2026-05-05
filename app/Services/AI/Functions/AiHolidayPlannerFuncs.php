<?php

namespace App\Services\AI\Functions;

use Illuminate\Support\Facades\Log;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Str;

trait AiHolidayPlannerFuncs
{
    public static function getAiHolidayPlannerFuncsSchema(): array
    {
        return [
            [
                'name' => 'holiday_generate_pdf_plan',
                'description' => 'Generiert aus den recherchierten Daten ein professionelles Urlaubs-PDF (inkl. Packliste, Sehenswürdigkeiten, Route) und liefert den Download-Link für den User. Nutze diese Funktion am ENDE eines Urlaubsplanungs-Workflows.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'title' => [
                            'type' => 'string',
                            'description' => 'Der Titel der Reise, z.B. "Rom entdecken: Ein Wochenende in der ewigen Stadt".'
                        ],
                        'description' => [
                            'type' => 'string',
                            'description' => 'Ein motivierender Einleitungstext für die Reise.'
                        ],
                        'packing_list' => [
                            'type' => 'array',
                            'items' => ['type' => 'string'],
                            'description' => 'Eine Liste an Dingen, die eingepackt werden müssen.'
                        ],
                        'itinerary' => [
                            'type' => 'array',
                            'items' => [
                                'type' => 'object',
                                'properties' => [
                                    'day' => ['type' => 'string', 'description' => 'Tag (z.B. "Tag 1")'],
                                    'activities' => [
                                        'type' => 'array',
                                        'items' => [
                                            'type' => 'object',
                                            'properties' => [
                                                'time' => ['type' => 'string', 'description' => 'Uhrzeit, z.B. "09:00"'],
                                                'title' => ['type' => 'string', 'description' => 'Name der Aktivität, z.B. "Kolosseum"'],
                                                'description' => ['type' => 'string', 'description' => 'Kurzbeschreibung oder Adresse']
                                            ]
                                        ]
                                    ]
                                ]
                            ],
                            'description' => 'Der detaillierte Reiseverlauf.'
                        ],
                        'target_action' => [
                            'type' => 'string',
                            'description' => 'Was soll mit dem generierten Urlaubsplan passieren? "download" (öffnet Download-Dialog beim Nutzer im Browser) oder "email" (versendet die PDF per Mail an recipient_email).',
                            'enum' => ['download', 'email']
                        ],
                        'recipient_email' => [
                            'type' => 'string',
                            'description' => 'Die E-Mail-Adresse des Empfängers. Wenn der Nutzer keine E-Mail nennt, lasse dieses Feld zwingend leer (null). Das System nutzt dann automatisch die Standard-E-Mail.'
                        ]
                    ],
                    'required' => ['title', 'description', 'packing_list', 'itinerary', 'target_action']
                ],
                'callable' => [self::class, 'executeHolidayGeneratePdfPlan']
            ]
        ];
    }

    public static function executeHolidayGeneratePdfPlan(array $args)
    {
        $title = $args['title'] ?? 'Urlaubsplan';
        $description = $args['description'] ?? '';
        $packingList = $args['packing_list'] ?? [];
        $itinerary = $args['itinerary'] ?? [];
        $action = $args['target_action'] ?? 'download';
        $recipient = $args['recipient_email'] ?? null;
        $agentName = session('current_ai_agent_name', 'Mapi - Leiter Globale Planung');

        try {
            // Generate PDF using dompdf
            $pdf = Pdf::loadView('pdf.holiday_plan', [
                'title' => $title,
                'description' => $description,
                'packingList' => $packingList,
                'itinerary' => $itinerary
            ]);

            // Ensure the directory exists
            $dir = storage_path('app/public/holidays');
            if (!file_exists($dir)) {
                mkdir($dir, 0755, true);
            }

            // Save PDF
            $filename = 'urlaubsplan_' . Str::slug($title) . '_' . time() . '.pdf';
            $filePath = $dir . '/' . $filename;
            $pdf->save($filePath);

            $downloadUrl = url('storage/holidays/' . $filename);

            if ($action === 'email') {
                if (empty($recipient)) {
                    $recipient = shop_setting('company_email') ?: shop_setting('owner_email') ?: config('mail.from.address') ?: 'kontakt@mein-seelenfunke.de';
                }
                
                \Illuminate\Support\Facades\Mail::to($recipient)->send(new \App\Mail\AiHolidayPlanMail(
                    "Dein exklusiver Urlaubsplan: $title",
                    $description,
                    $agentName,
                    [$filePath]
                ));

                return [
                    'status' => 'success',
                    'message' => "Urlaubsplan PDF erfolgreich generiert und per E-Mail an $recipient versendet!",
                    'file_name' => $filename,
                ];
            } else {
                return [
                    'status' => 'success',
                    'message' => 'Urlaubsplan PDF erfolgreich generiert!',
                    'pdf_url' => $downloadUrl,
                    'file_name' => $filename,
                    'note' => 'Der Plan wurde erfolgreich generiert und kann vom Nutzer heruntergeladen werden.'
                ];
            }
        } catch (\Exception $e) {
            Log::error("PDF Generation Error: " . $e->getMessage());
            return ['status' => 'error', 'message' => 'Fehler beim Erstellen des PDFs: ' . $e->getMessage()];
        }
    }
}
