<?php

namespace App\Services\AI\Functions;

use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use App\Services\AI\Mails\AiAgentMessageMail;

trait AiLaserFuncs
{
    /**
     * Define the Laser specific tools for Lasi
     */
    public static function getAiLaserFuncsSchema(): array
    {
        return [
            [
                'name' => 'laser_generate_pdf_and_mail',
                'description' => 'Generiert ein PDF-Dokument mit Informationen aus der Laserschutzschulung oder generellen Sicherheitsanweisungen. Das PDF kann als Bericht oder Zertifikat formatiert sein und dem Nutzer zum Download bereitgestellt oder direkt per E-Mail gesendet werden.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'title' => [
                            'type' => 'string',
                            'description' => 'Der Titel des Dokuments, z.B. "Zertifikat: Laserschutzschulung" oder "Sicherheitsunterweisung Laser".'
                        ],
                        'content' => [
                            'type' => 'string',
                            'description' => 'Der vollständige Inhalt des Dokuments im Markdown-Format (wird in ein strukturiertes PDF umgewandelt). Nutze Überschriften, Listen und Tabellen zur besseren Lesbarkeit.'
                        ],
                        'target_action' => [
                            'type' => 'string',
                            'description' => 'Was soll mit dem PDF passieren? "download" (öffnet Download-Dialog) oder "email" (versendet die PDF per Mail an recipient_email).',
                            'enum' => ['download', 'email']
                        ],
                        'recipient_email' => [
                            'type' => 'string',
                            'description' => 'Die E-Mail-Adresse des Empfängers. Wenn der Nutzer keine E-Mail nennt und "email" gewählt ist, lasse dieses Feld zwingend leer (null).'
                        ],
                        'design' => [
                            'type' => 'string',
                            'description' => 'Das visuelle Design der E-Mail und des PDFs. "seelenfunke" (inkl. Briefkopf, CI-Farben, Logo) oder "generic" (neutrales Design ohne Firmenbezug). Standardmäßig "seelenfunke".',
                            'enum' => ['seelenfunke', 'generic']
                        ],
                        'email_message' => [
                            'type' => 'string',
                            'description' => 'Optional: Der Begleittext für die E-Mail. Wird ignoriert, wenn target_action "download" ist.'
                        ]
                    ],
                    'required' => ['title', 'content', 'target_action']
                ],
                'callable' => [self::class, 'executeLaserGeneratePdfAndMail']
            ]
        ];
    }

    public static function executeLaserGeneratePdfAndMail(array $args, $agent = null)
    {
        $title = $args['title'] ?? 'Laserschutz-Information';
        $content = $args['content'] ?? '';
        $action = $args['target_action'] ?? 'download';
        $recipient = $args['recipient_email'] ?? null;
        if (is_string($recipient) && (strtolower($recipient) === 'null' || trim($recipient) === '')) {
            $recipient = null;
        }
        $design = $args['design'] ?? 'seelenfunke';
        $emailMessage = $args['email_message'] ?? "Anbei erhalten Sie die angeforderten Unterlagen zur Laserschutzschulung.";
        $agentName = $agent ? $agent->name : 'Lasi';

        try {
            // Convert Markdown to HTML for the PDF
            $htmlContent = Str::markdown($content);
            
            // Choose the correct view template
            $viewName = $design === 'generic' ? 'global.pdf.ai-report-generic' : 'global.pdf.ai-report-seelenfunke';
            
            // Generate PDF
            $pdf = Pdf::loadView($viewName, [
                'title' => $title,
                'htmlContent' => $htmlContent,
                'agentName' => $agentName
            ]);

            // Create target folder in workspace
            $folderPath = 'agenten/workspace/Laserschutz';
            if (!Storage::disk('public')->exists($folderPath)) {
                Storage::disk('public')->makeDirectory($folderPath);
            }

            // Generate File Name
            $safeTitle = preg_replace('/[^A-Za-z0-9\-]/', '_', $title);
            $fileName = 'LaserReport_' . $safeTitle . '_' . now()->format('Ymd_Hi') . '.pdf';
            $filePath = $folderPath . '/' . $fileName;
            $absolutePath = Storage::disk('public')->path($filePath);

            // Save PDF
            $pdf->save($absolutePath);
            
            $downloadUrl = url('storage/' . $filePath);

            // Handle action
            if ($action === 'email') {
                if (empty($recipient)) {
                    $recipient = config('mail.from.address') ?: 'kontakt@mein-seelenfunke.de';
                }

                Mail::to($recipient)->send(new AiAgentMessageMail(
                    $title,
                    $emailMessage,
                    $agentName,
                    [$absolutePath],
                    $design
                ));

                return [
                    'status' => 'success',
                    'message' => "Das PDF-Dokument wurde erfolgreich generiert und per E-Mail an {$recipient} versendet.",
                    'file_name' => $fileName,
                ];
            } else {
                return [
                    'status' => 'success',
                    'message' => 'Das PDF-Dokument wurde erfolgreich generiert!',
                    'pdf_url' => $downloadUrl,
                    'file_name' => $fileName,
                    'note' => 'Das PDF wurde im Workspace gespeichert und der Link kann dem Nutzer gegeben werden.'
                ];
            }
        } catch (\Exception $e) {
            Log::error("Laser PDF Generation Error: " . $e->getMessage());
            return ['status' => 'error', 'message' => 'Fehler beim Erstellen des PDFs: ' . $e->getMessage()];
        }
    }
}
