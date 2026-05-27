<?php

namespace App\Services\AI\Functions;

use App\Models\Marketing\MarketingVideo;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

trait AiVideoGenerateFuncs
{
    /**
     * Define the AI tool schema for video generation functions.
     */
    public static function getAiVideoGenerateFuncsSchema(): array
    {
        return [
            [
                'name' => 'video_generate_brand_animation',
                'description' => 'Erstellt ein neues Video-Projekt (Logo-Animation) mit anpassbarer Dauer, Design-Stil, Text, Format und Partikeleffekten.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'title' => [
                            'type' => 'string',
                            'description' => 'Der Haupttitel / Logo-Text für die Animation, z.B. "mein-seelenfunke".'
                        ],
                        'subtitle' => [
                            'type' => 'string',
                            'description' => 'Der Slogan / Untertitel, z.B. "EIN FUNKE, DER BLEIBT".'
                        ],
                        'design_style' => [
                            'type' => 'string',
                            'enum' => ['seelenfunke', 'standard'],
                            'description' => "Der Design-Stil. 'seelenfunke' nutzt das goldene Markenbranding mit Partikeleffekten; 'standard' ist ein neutraler cleaner Look."
                        ],
                        'duration' => [
                            'type' => 'number',
                            'description' => 'Die Gesamtdauer des Videos in Sekunden (z.B. 6.0, 10.0, 30.0, 60.0 für 1 Minute, 120.0 für 2 Minuten).'
                        ],
                        'aspect_ratio' => [
                            'type' => 'string',
                            'enum' => ['16:9', '9:16', '1:1', '4:5', '2:3'],
                            'description' => 'Seitenverhältnis für das Video (z.B. "9:16" für TikTok/Reels/Shorts oder "16:9" für YouTube/Web).'
                        ],
                        'layers' => [
                            'type' => 'array',
                            'description' => 'Optional: Eine benutzerdefinierte Ebenen-Konfiguration (Array von Objekten mit Eigenschaften wie id, type, name, x, y, width, height, color, text, startTime, endTime, animation). Falls angegeben, wird das Standard-Layout überschrieben.',
                            'items' => [
                                'type' => 'object'
                            ]
                        ]
                    ],
                    'required' => ['title']
                ],
                'callable' => [self::class, 'executeVideoGenerateBrandAnimation']
            ],
            [
                'name' => 'video_generate_explainer_video',
                'description' => 'Erstellt ein hochgradig professionelles Erklärvideo (Loft Film-Stil) mit Hook, Problem, Lösung und Call-to-Action (CTA).',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'title' => [
                            'type' => 'string',
                            'description' => 'Der Name des beworbenen Produkts oder der Kampagne.'
                        ],
                        'hook' => [
                            'type' => 'string',
                            'description' => 'Aufmerksamkeitsstarker Hook / Frage für den Anfang des Videos (z.B. "Kennst du das Problem?").'
                        ],
                        'problem' => [
                            'type' => 'string',
                            'description' => 'Beschreibung des Kunden-Problems.'
                        ],
                        'solution' => [
                            'type' => 'string',
                            'description' => 'Vorstellung des Produkts als rettende Lösung.'
                        ],
                        'cta' => [
                            'type' => 'string',
                            'description' => 'Handlungsaufforderung (CTA) für das Ende des Videos (z.B. Voucher oder Webadresse).'
                        ],
                        'design_style' => [
                            'type' => 'string',
                            'enum' => ['seelenfunke', 'standard'],
                            'description' => "Der Design-Stil. 'seelenfunke' nutzt das goldene Markenbranding; 'standard' ist ein neutraler cleaner Look."
                        ],
                        'duration' => [
                            'type' => 'number',
                            'description' => 'Die Gesamtdauer des Videos in Sekunden (z.B. 6.0, 15.0, 30.0, 60.0 für 1 Minute).'
                        ],
                        'aspect_ratio' => [
                            'type' => 'string',
                            'enum' => ['16:9', '9:16', '1:1', '4:5', '2:3'],
                            'description' => 'Seitenverhältnis für die Zielplattform.'
                        ],
                        'product_id' => [
                            'type' => 'string',
                            'description' => 'Optionale UUID eines Systemprodukts zur dynamischen Integration von Preis oder Details.'
                        ],
                        'layers' => [
                            'type' => 'array',
                            'description' => 'Optional: Eine benutzerdefinierte Ebenen-Konfiguration (Array von Objekten mit Eigenschaften wie id, type, name, x, y, width, height, color, text, startTime, endTime, animation). Falls angegeben, wird das Standard-Layout überschrieben.',
                            'items' => [
                                'type' => 'object'
                            ]
                        ]
                    ],
                    'required' => ['title', 'hook', 'problem', 'solution', 'cta']
                ],
                'callable' => [self::class, 'executeVideoGenerateExplainerVideo']
            ],
            [
                'name' => 'video_get_list',
                'description' => 'Gibt eine Liste aller existierenden Marketing-Videos (ausgenommen archivierte Videos) zurück.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => []
                ],
                'callable' => [self::class, 'executeVideoGetList']
            ],
            [
                'name' => 'video_get_details',
                'description' => 'Gibt alle Detail-Informationen und die gesamte Ebenenkonfiguration (config) eines Videos zurück.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'id' => [
                            'type' => 'string',
                            'description' => 'Die eindeutige UUID des Video-Projekts.'
                        ]
                    ],
                    'required' => ['id']
                ],
                'callable' => [self::class, 'executeVideoGetDetails']
            ],
            [
                'name' => 'video_delete',
                'description' => 'Archiviert ein Video-Projekt (verschiebt es in das Sicherheits-Archiv), um ein versehentliches Löschen zu verhindern.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'id' => [
                            'type' => 'string',
                            'description' => 'Die UUID des Video-Projekts.'
                        ]
                    ],
                    'required' => ['id']
                ],
                'callable' => [self::class, 'executeVideoDelete']
            ],
            [
                'name' => 'video_permanent_delete',
                'description' => 'Löscht ein bereits archiviertes Video-Projekt dauerhaft und unwiderruflich aus der Datenbank und dem lokalen Speicher.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'id' => [
                            'type' => 'string',
                            'description' => 'Die UUID des archivierten Video-Projekts.'
                        ]
                    ],
                    'required' => ['id']
                ],
                'callable' => [self::class, 'executeVideoPermanentDelete']
            ],
            [
                'name' => 'video_apply_preset',
                'description' => 'Wendet ein fertiges Design-Preset (gold, neon, minimal) auf ein Video-Projekt an.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'id' => [
                            'type' => 'string',
                            'description' => 'Die UUID des Video-Projekts.'
                        ],
                        'preset' => [
                            'type' => 'string',
                            'enum' => ['gold', 'neon', 'minimal'],
                            'description' => 'Der Name des anzuwendenden Presets.'
                        ]
                    ],
                    'required' => ['id', 'preset']
                ],
                'callable' => [self::class, 'executeVideoApplyPreset']
            ],
            [
                'name' => 'video_change_format',
                'description' => 'Ändert das Seitenverhältnis (z.B. TikTok 9:16, YouTube 16:9) eines Video-Projekts.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'id' => [
                            'type' => 'string',
                            'description' => 'Die UUID des Video-Projekts.'
                        ],
                        'aspect_ratio' => [
                            'type' => 'string',
                            'enum' => ['16:9', '9:16', '1:1', '4:5', '2:3'],
                            'description' => 'Das neue Seitenverhältnis.'
                        ]
                    ],
                    'required' => ['id', 'aspect_ratio']
                ],
                'callable' => [self::class, 'executeVideoChangeFormat']
            ],
            [
                'name' => 'video_add_layer',
                'description' => 'Fügt eine neue Ebene (Text, Bild, Partikel, Form) zu einem Video-Projekt hinzu.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'id' => [
                            'type' => 'string',
                            'description' => 'Die UUID des Video-Projekts.'
                        ],
                        'type' => [
                            'type' => 'string',
                            'enum' => ['text', 'image', 'shape', 'particles', 'avatar', 'subtitles', 'audio'],
                            'description' => 'Der Typ der neuen Ebene.'
                        ],
                        'name' => [
                            'type' => 'string',
                            'description' => 'Ein lesbarer Name für die Ebene, z.B. "Titel-Einblendung".'
                        ],
                        'properties' => [
                            'type' => 'object',
                            'description' => 'Die Parameter für die Ebene (z.B. x, y, width, height, startTime, endTime, color, opacity, text, fontSize, fontFamily, imageUrl, shapeType).'
                        ]
                    ],
                    'required' => ['id', 'type', 'name', 'properties']
                ],
                'callable' => [self::class, 'executeVideoAddLayer']
            ],
            [
                'name' => 'video_update_layer',
                'description' => 'Modifiziert die Eigenschaften einer bestehenden Ebene eines Videos (z.B. Text, Position, Zeiten, Farben).',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'id' => [
                            'type' => 'string',
                            'description' => 'Die UUID des Video-Projekts.'
                        ],
                        'layer_id' => [
                            'type' => 'string',
                            'description' => 'Die ID der zu modifizierenden Ebene (z.B. "layer-title").'
                        ],
                        'properties' => [
                            'type' => 'object',
                            'description' => 'Ein Key-Value-Objekt mit den zu ändernden Attributen.'
                        ]
                    ],
                    'required' => ['id', 'layer_id', 'properties']
                ],
                'callable' => [self::class, 'executeVideoUpdateLayer']
            ],
            [
                'name' => 'video_delete_layer',
                'description' => 'Löscht eine Ebene aus der Ebenenkonfiguration eines Videos.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'id' => [
                            'type' => 'string',
                            'description' => 'Die UUID des Video-Projekts.'
                        ],
                        'layer_id' => [
                            'type' => 'string',
                            'description' => 'Die ID der zu löschenden Ebene (Hintergründe können nicht gelöscht werden).'
                        ]
                    ],
                    'required' => ['id', 'layer_id']
                ],
                'callable' => [self::class, 'executeVideoDeleteLayer']
            ],
            [
                'name' => 'video_set_avatar',
                'description' => 'Fügt einen sprechenden HeyGen KI-Avatar hinzu oder passt diesen an.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'id' => [
                            'type' => 'string',
                            'description' => 'Die UUID des Video-Projekts.'
                        ],
                        'avatar_url' => [
                            'type' => 'string',
                            'description' => 'Bildpfad des Avatars, z.B. "/shop/ai/images/funkira_selfie.png".'
                        ],
                        'script_text' => [
                            'type' => 'string',
                            'description' => 'Der Sprechtext für den Avatar.'
                        ],
                        'voice_id' => [
                            'type' => 'string',
                            'description' => 'Die ID der Stimme (z.B. "de-DE-Wavenet-D").'
                        ],
                        'x' => ['type' => 'integer', 'description' => 'Horizontale Position.'],
                        'y' => ['type' => 'integer', 'description' => 'Vertikale Position.'],
                        'width' => ['type' => 'integer'],
                        'height' => ['type' => 'integer'],
                        'style' => ['type' => 'string', 'enum' => ['circle', 'rect']]
                    ],
                    'required' => ['id', 'script_text']
                ],
                'callable' => [self::class, 'executeVideoSetAvatar']
            ],
            [
                'name' => 'video_set_audio',
                'description' => 'Richtet Hintergrundmusik oder ein Voiceover (Text-to-Speech) ein.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'id' => [
                            'type' => 'string',
                            'description' => 'Die UUID des Video-Projekts.'
                        ],
                        'audio_url' => [
                            'type' => 'string',
                            'description' => 'Musik-Preset (z.B. "calm", "energetic", "branding").'
                        ],
                        'script_text' => [
                            'type' => 'string',
                            'description' => 'Der Sprechtext für die Sprachausgabe.'
                        ],
                        'voice_id' => [
                            'type' => 'string',
                            'description' => 'ID der Stimme.'
                        ],
                        'volume' => [
                            'type' => 'number',
                            'description' => 'Lautstärke (0.0 bis 1.0).'
                        ]
                    ],
                    'required' => ['id', 'script_text']
                ],
                'callable' => [self::class, 'executeVideoSetAudio']
            ],
            [
                'name' => 'video_set_subtitles',
                'description' => 'Aktiviert und gestaltet automatische Wort-für-Wort-Untertitel (Captions).',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'id' => [
                            'type' => 'string',
                            'description' => 'Die UUID des Video-Projekts.'
                        ],
                        'text' => [
                            'type' => 'string',
                            'description' => 'Der vollständige Untertitel-Text.'
                        ],
                        'color' => [
                            'type' => 'string',
                            'description' => 'Schriftfarbe als Hex.'
                        ],
                        'font_size' => [
                            'type' => 'integer',
                            'description' => 'Schriftgröße in Pixeln.'
                        ],
                        'y' => [
                            'type' => 'integer',
                            'description' => 'Vertikale Position.'
                        ]
                    ],
                    'required' => ['id', 'text']
                ],
                'callable' => [self::class, 'executeVideoSetSubtitles']
            ],
            [
                'name' => 'video_generate_ai_background',
                'description' => 'Setzt einen KI-generierten Hintergrund oder ein Bildpfad für das Video.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'id' => [
                            'type' => 'string',
                            'description' => 'Die UUID des Video-Projekts.'
                        ],
                        'prompt' => [
                            'type' => 'string',
                            'description' => 'Beschreibung des Hintergrunds.'
                        ],
                        'image_url' => [
                            'type' => 'string',
                            'description' => 'Bild-URL.'
                        ],
                        'use_gradient' => [
                            'type' => 'boolean',
                            'description' => 'Farbverlauf aktivieren.'
                        ],
                        'color' => [
                            'type' => 'string',
                            'description' => 'Hintergrundfarbe.'
                        ]
                    ],
                    'required' => ['id']
                ],
                'callable' => [self::class, 'executeVideoGenerateAiBackground']
            ]
        ];
    }

    /**
     * Executes the video generation template creation.
     */
    public static function executeVideoGenerateBrandAnimation(array $args, $agent = null)
    {
        $title = $args['title'] ?? 'mein-seelenfunke';
        $subtitle = $args['subtitle'] ?? '';
        $designStyle = $args['design_style'] ?? 'seelenfunke';
        $duration = isset($args['duration']) ? floatval($args['duration']) : 6.0;
        $aspectRatio = $args['aspect_ratio'] ?? '16:9';
        
        $isSeelenfunke = ($designStyle === 'seelenfunke');
        $themeColor = $isSeelenfunke ? '#C5A059' : '#3B82F6';
        $hasParticles = $isSeelenfunke;
        
        if (empty($subtitle) && $isSeelenfunke) {
            $subtitle = 'EIN FUNKE, DER BLEIBT';
        }

        $agentId = $agent ? $agent->id : null;

        try {
            $defaultConfig = [
                [
                    'id' => 'metadata',
                    'aspectRatio' => $aspectRatio,
                    'duration' => $duration
                ],
                [
                    'id' => 'layer-bg',
                    'name' => 'Hintergrund',
                    'type' => 'background',
                    'color' => $isSeelenfunke ? '#FAF9F6' : '#111827',
                    'gradientColor' => $themeColor,
                    'useGradient' => $isSeelenfunke,
                    'opacity' => 1.0,
                    'startTime' => 0.0,
                    'endTime' => $duration
                ],
                [
                    'id' => 'layer-particles',
                    'name' => 'Seelenfunken',
                    'type' => 'particles',
                    'particleType' => 'sparks',
                    'color' => $themeColor,
                    'x' => 480,
                    'y' => 160,
                    'width' => 120,
                    'height' => 120,
                    'opacity' => $hasParticles ? 0.8 : 0.0,
                    'startTime' => min(1.8, $duration),
                    'endTime' => $duration
                ],
                [
                    'id' => 'layer-logo',
                    'name' => 'Marken-Logo',
                    'type' => 'image',
                    'imageUrl' => $isSeelenfunke ? 'shop/projekt/logo/mein-seelenfunke-logo.png' : 'shop/ai/images/funkira_selfie.png',
                    'x' => 480,
                    'y' => 160,
                    'width' => 120,
                    'height' => 120,
                    'opacity' => 1.0,
                    'startTime' => min(0.2, $duration),
                    'endTime' => $duration,
                    'animation' => 'fade'
                ],
                [
                    'id' => 'layer-title',
                    'name' => 'Haupttitel',
                    'type' => 'text',
                    'text' => $title,
                    'x' => 480,
                    'y' => 370,
                    'fontSize' => 32,
                    'color' => $themeColor,
                    'fontFamily' => $isSeelenfunke ? 'Playfair Display' : 'Outfit',
                    'opacity' => 1.0,
                    'startTime' => min(2.3, $duration),
                    'endTime' => $duration,
                    'animation' => 'fade'
                ],
                [
                    'id' => 'layer-subtitle',
                    'name' => 'Slogan',
                    'type' => 'text',
                    'text' => $subtitle,
                    'x' => 480,
                    'y' => 405,
                    'fontSize' => 12,
                    'color' => $isSeelenfunke ? '#5C5549' : '#9CA3AF',
                    'fontFamily' => 'Outfit',
                    'opacity' => 1.0,
                    'startTime' => min(2.6, $duration),
                    'endTime' => $duration,
                    'animation' => 'fade'
                ]
            ];

            $customLayers = $args['layers'] ?? null;
            if ($customLayers && is_array($customLayers)) {
                $hasMetadata = false;
                foreach ($customLayers as $layer) {
                    if (isset($layer['id']) && $layer['id'] === 'metadata') {
                        $hasMetadata = true;
                        break;
                    }
                }
                if (!$hasMetadata) {
                    array_unshift($customLayers, [
                        'id' => 'metadata',
                        'aspectRatio' => $aspectRatio,
                        'duration' => $duration
                    ]);
                }
                $finalConfig = $customLayers;
            } else {
                $finalConfig = $defaultConfig;
            }

            $video = MarketingVideo::create([
                'ai_agent_id' => $agentId,
                'title' => $title,
                'subtitle' => $subtitle,
                'theme_color' => $themeColor,
                'has_particles' => $hasParticles,
                'config' => $finalConfig,
                'status' => 'draft',
                'video_path' => null
            ]);

            return [
                'status' => 'success',
                'message' => "Der Video-Entwurf '{$title}' wurde erfolgreich mit dem Seitenverhältnis {$aspectRatio} erstellt (ID: {$video->id}, Dauer: {$duration}s).",
                'video_id' => $video->id,
            ];
        } catch (\Exception $e) {
            Log::error("Video Template Generation Error: " . $e->getMessage());
            return [
                'status' => 'error',
                'message' => 'Fehler beim Erstellen des Video-Entwurfs: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Generates a high-quality storyboard Loft-Film style Explainer video.
     */
    public static function executeVideoGenerateExplainerVideo(array $args, $agent = null)
    {
        $title = $args['title'] ?? 'Erklärvideo';
        $hook = $args['hook'] ?? 'Kennst du das Problem?';
        $problem = $args['problem'] ?? 'Viele scheitern an komplexen Abläufen.';
        $solution = $args['solution'] ?? 'Unser Service löst das einfach per Klick!';
        $cta = $args['cta'] ?? 'Jetzt starten auf seelenfunke.de';
        $designStyle = $args['design_style'] ?? 'seelenfunke';
        $duration = isset($args['duration']) ? floatval($args['duration']) : 6.0;
        $aspectRatio = $args['aspect_ratio'] ?? '16:9';
        $productId = $args['product_id'] ?? null;
        
        $agentId = $agent ? $agent->id : null;
        $isSeelenfunke = ($designStyle === 'seelenfunke');
        $themeColor = $isSeelenfunke ? '#C5A059' : '#3B82F6';

        // Try searching product details in DB if product_id is provided
        if ($productId) {
            try {
                $product = \App\Models\Shop\Product::find($productId);
                if ($product) {
                    $title = $product->title ?? $title;
                    $solution = "Entdecke {$product->title}: " . ($product->slogan ?? $solution);
                }
            } catch (\Exception $ex) {
                // Ignore model mismatch
            }
        }

        try {
            $scriptText = "{$hook} {$problem} {$solution} {$cta}";

            // Calculate scaled timestamps for phases
            $t1 = $duration * 0.25; // end hook
            $t2 = $duration * 0.50; // end problem
            $t3 = $duration * 0.80; // end solution
            $t4 = $duration;        // end CTA

            $storyboardConfig = [
                [
                    'id' => 'metadata',
                    'aspectRatio' => $aspectRatio,
                    'duration' => $duration
                ],
                [
                    'id' => 'layer-bg',
                    'name' => 'Explainer BG',
                    'type' => 'background',
                    'color' => $isSeelenfunke ? '#FAF9F6' : '#0A0A0F',
                    'gradientColor' => $themeColor,
                    'useGradient' => $isSeelenfunke,
                    'opacity' => 1.0,
                    'startTime' => 0.0,
                    'endTime' => $duration
                ],
                [
                    'id' => 'layer-audio-explainer',
                    'name' => 'TTS Voiceover track',
                    'type' => 'audio',
                    'audioUrl' => 'calm',
                    'scriptText' => $scriptText,
                    'voiceId' => 'de-DE-Wavenet-D',
                    'volume' => 0.7,
                    'startTime' => 0.0,
                    'endTime' => $duration
                ],
                [
                    'id' => 'layer-subtitles-explainer',
                    'name' => 'Dynamic Captions',
                    'type' => 'subtitles',
                    'text' => $scriptText,
                    'color' => $isSeelenfunke ? '#111827' : '#ffffff',
                    'fontSize' => 26,
                    'fontFamily' => 'Outfit',
                    'x' => 480,
                    'y' => 460,
                    'opacity' => 1.0,
                    'startTime' => 0.0,
                    'endTime' => $duration
                ],
                
                // HOOK PHASE (0.0s to $t1)
                [
                    'id' => 'l-hook-txt',
                    'name' => 'Hook Headline',
                    'type' => 'text',
                    'text' => $hook,
                    'x' => 480,
                    'y' => 200,
                    'fontSize' => 30,
                    'color' => $isSeelenfunke ? '#111827' : '#ffffff',
                    'fontFamily' => 'Outfit',
                    'opacity' => 1.0,
                    'startTime' => 0.0,
                    'endTime' => $t1,
                    'animation' => 'slide-up'
                ],
                [
                    'id' => 'l-hook-shape',
                    'name' => 'Hook Alert Circle',
                    'type' => 'shape',
                    'shapeType' => 'circle',
                    'color' => 'rgba(239, 68, 68, 0.15)',
                    'x' => 480,
                    'y' => 320,
                    'width' => 180,
                    'height' => 180,
                    'opacity' => 1.0,
                    'startTime' => 0.0,
                    'endTime' => $t1
                ],
                
                // PROBLEM PHASE ($t1 to $t2)
                [
                    'id' => 'l-prob-txt',
                    'name' => 'Problem statement',
                    'type' => 'text',
                    'text' => $problem,
                    'x' => 480,
                    'y' => 240,
                    'fontSize' => 24,
                    'color' => '#EF4444',
                    'fontFamily' => 'Outfit',
                    'opacity' => 1.0,
                    'startTime' => $t1,
                    'endTime' => $t2,
                    'animation' => 'fade'
                ],
                
                // SOLUTION PRESENTATION ($t2 to $t3)
                [
                    'id' => 'l-sol-avatar',
                    'name' => 'Explainer AI Avatar',
                    'type' => 'avatar',
                    'avatarUrl' => '/shop/ai/images/funkira_selfie.png',
                    'scriptText' => $solution,
                    'voiceId' => 'de-DE-Wavenet-D',
                    'style' => 'circle',
                    'x' => 250,
                    'y' => 250,
                    'width' => 150,
                    'height' => 150,
                    'opacity' => 1.0,
                    'startTime' => $t2,
                    'endTime' => $duration
                ],
                [
                    'id' => 'l-sol-txt',
                    'name' => 'Solution name',
                    'type' => 'text',
                    'text' => $title,
                    'x' => 580,
                    'y' => 230,
                    'fontSize' => 36,
                    'color' => $themeColor,
                    'fontFamily' => $isSeelenfunke ? 'Playfair Display' : 'Outfit',
                    'opacity' => 1.0,
                    'startTime' => min($t2 + ($duration * 0.03), $duration),
                    'endTime' => $t3,
                    'animation' => 'scale'
                ],
                [
                    'id' => 'l-sol-sub',
                    'name' => 'Solution tagline',
                    'type' => 'text',
                    'text' => $solution,
                    'x' => 580,
                    'y' => 290,
                    'fontSize' => 16,
                    'color' => $isSeelenfunke ? '#5C5549' : '#E5E7EB',
                    'fontFamily' => 'Outfit',
                    'opacity' => 1.0,
                    'startTime' => min($t2 + ($duration * 0.06), $duration),
                    'endTime' => $t3,
                    'animation' => 'fade'
                ],
                
                // CALL TO ACTION ($t3 to $t4)
                [
                    'id' => 'l-cta-button',
                    'name' => 'CTA Glowing Button',
                    'type' => 'shape',
                    'shapeType' => 'rect',
                    'color' => $themeColor,
                    'x' => 580,
                    'y' => 260,
                    'width' => 280,
                    'height' => 60,
                    'opacity' => 1.0,
                    'startTime' => $t3,
                    'endTime' => $t4
                ],
                [
                    'id' => 'l-cta-txt',
                    'name' => 'CTA Link Text',
                    'type' => 'text',
                    'text' => $cta,
                    'x' => 580,
                    'y' => 260,
                    'fontSize' => 14,
                    'color' => $isSeelenfunke ? '#FAF9F6' : '#0F0F12',
                    'fontFamily' => 'Outfit',
                    'opacity' => 1.0,
                    'startTime' => min($t3 + ($duration * 0.02), $duration),
                    'endTime' => $t4,
                    'animation' => 'scale'
                ]
            ];

            $customLayers = $args['layers'] ?? null;
            if ($customLayers && is_array($customLayers)) {
                $hasMetadata = false;
                foreach ($customLayers as $layer) {
                    if (isset($layer['id']) && $layer['id'] === 'metadata') {
                        $hasMetadata = true;
                        break;
                    }
                }
                if (!$hasMetadata) {
                    array_unshift($customLayers, [
                        'id' => 'metadata',
                        'aspectRatio' => $aspectRatio,
                        'duration' => $duration
                    ]);
                }
                $finalConfig = $customLayers;
            } else {
                $finalConfig = $storyboardConfig;
            }

            $video = MarketingVideo::create([
                'ai_agent_id' => $agentId,
                'title' => $title . " - Erklärvideo",
                'subtitle' => $solution,
                'theme_color' => $themeColor,
                'has_particles' => false,
                'config' => $finalConfig,
                'status' => 'draft',
                'video_path' => null
            ]);

            return [
                'status' => 'success',
                'message' => "Das Loft Film Erklärvideo '{$title}' wurde erfolgreich mit Storyboard, Hook, Problem, Avatar und CTA generiert (ID: {$video->id}, Dauer: {$duration}s).",
                'video_id' => $video->id
            ];
        } catch (\Exception $e) {
            Log::error("Explainer Video Generation Error: " . $e->getMessage());
            return [
                'status' => 'error',
                'message' => 'Fehler beim Erstellen des Erklärvideos: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Fetch list of all marketing videos.
     */
    public static function executeVideoGetList(array $args, $agent = null)
    {
        try {
            $videos = MarketingVideo::where('status', '!=', 'archived')->orderBy('created_at', 'desc')->get()->map(function($v) {
                $aspectRatio = '16:9';
                if (is_array($v->config)) {
                    foreach ($v->config as $layer) {
                        if (isset($layer['id']) && $layer['id'] === 'metadata') {
                            $aspectRatio = $layer['aspectRatio'] ?? '16:9';
                            break;
                        }
                    }
                }
                return [
                    'id' => $v->id,
                    'title' => $v->title,
                    'subtitle' => $v->subtitle,
                    'theme_color' => $v->theme_color,
                    'aspect_ratio' => $aspectRatio,
                    'status' => $v->status,
                    'created_at' => $v->created_at->toDateTimeString()
                ];
            });

            return [
                'status' => 'success',
                'videos' => $videos->toArray()
            ];
        } catch (\Exception $e) {
            return [
                'status' => 'error',
                'message' => 'Fehler beim Abrufen der Video-Liste: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Fetch full video configuration.
     */
    public static function executeVideoGetDetails(array $args, $agent = null)
    {
        $id = $args['id'] ?? null;
        if (!$id) {
            return ['status' => 'error', 'message' => 'Video-ID ist erforderlich.'];
        }

        try {
            $video = MarketingVideo::find($id);
            if (!$video) {
                return ['status' => 'error', 'message' => "Video mit ID {$id} wurde nicht gefunden."];
            }

            return [
                'status' => 'success',
                'video' => [
                    'id' => $video->id,
                    'title' => $video->title,
                    'subtitle' => $video->subtitle,
                    'theme_color' => $video->theme_color,
                    'has_particles' => $video->has_particles,
                    'status' => $video->status,
                    'config' => $video->config
                ]
            ];
        } catch (\Exception $e) {
            return [
                'status' => 'error',
                'message' => 'Fehler beim Abrufen der Video-Details: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Archive video project.
     */
    public static function executeVideoDelete(array $args, $agent = null)
    {
        $id = $args['id'] ?? null;
        if (!$id) {
            return ['status' => 'error', 'message' => 'Video-ID ist erforderlich.'];
        }

        try {
            $video = MarketingVideo::find($id);
            if (!$video) {
                return ['status' => 'error', 'message' => "Video mit ID {$id} wurde nicht gefunden."];
            }

            $video->update(['status' => 'archived']);

            return [
                'status' => 'success',
                'message' => "Video-Projekt {$id} wurde erfolgreich archiviert (in das Sicherheits-Archiv verschoben)."
            ];
        } catch (\Exception $e) {
            return [
                'status' => 'error',
                'message' => 'Fehler beim Archivieren des Videos: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Permanent deletion of video project.
     */
    public static function executeVideoPermanentDelete(array $args, $agent = null)
    {
        $id = $args['id'] ?? null;
        if (!$id) {
            return ['status' => 'error', 'message' => 'Video-ID ist erforderlich.'];
        }

        try {
            $video = MarketingVideo::where('id', $id)->where('status', 'archived')->first();
            if (!$video) {
                return ['status' => 'error', 'message' => "Archiviertes Video mit ID {$id} wurde nicht gefunden."];
            }

            \Illuminate\Support\Facades\Storage::disk('local')->deleteDirectory("marketing/marketing/videos/{$video->id}");
            $video->delete();

            return [
                'status' => 'success',
                'message' => "Video-Projekt {$id} wurde endgültig und dauerhaft gelöscht."
            ];
        } catch (\Exception $e) {
            return [
                'status' => 'error',
                'message' => 'Fehler beim endgültigen Löschen des Videos: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Apply standard style presets.
     */
    public static function executeVideoApplyPreset(array $args, $agent = null)
    {
        $id = $args['id'] ?? null;
        $presetName = $args['preset'] ?? null;

        if (!$id || !$presetName) {
            return ['status' => 'error', 'message' => 'Video-ID und Preset-Name sind erforderlich.'];
        }

        try {
            $video = MarketingVideo::find($id);
            if (!$video) {
                return ['status' => 'error', 'message' => "Video mit ID {$id} wurde nicht gefunden."];
            }

            $aspectRatio = '16:9';
            if (is_array($video->config)) {
                foreach ($video->config as $layer) {
                    if (isset($layer['id']) && $layer['id'] === 'metadata') {
                        $aspectRatio = $layer['aspectRatio'] ?? '16:9';
                        break;
                    }
                }
            }

            $layers = [];
            $layers[] = ['id' => 'metadata', 'aspectRatio' => $aspectRatio];

            if ($presetName === 'gold') {
                $layers = array_merge($layers, [
                    ['id' => 'l-bg', 'name' => 'Hintergrund', 'type' => 'background', 'color' => '#FAF9F6', 'gradientColor' => '#C5A059', 'useGradient' => true, 'opacity' => 1.0, 'startTime' => 0.0, 'endTime' => 6.0],
                    ['id' => 'l-part', 'name' => 'Goldschweif-Funken', 'type' => 'particles', 'particleType' => 'sparks', 'color' => '#C5A059', 'x' => 480, 'y' => 160, 'width' => 120, 'height' => 120, 'opacity' => 0.8, 'startTime' => 1.8, 'endTime' => 6.0],
                    ['id' => 'l-logo', 'name' => 'Seelenfunke Flame', 'type' => 'image', 'imageUrl' => 'shop/projekt/logo/mein-seelenfunke-logo.png', 'x' => 480, 'y' => 160, 'width' => 120, 'height' => 120, 'opacity' => 1.0, 'startTime' => 0.2, 'endTime' => 6.0, 'animation' => 'fade', 'shine' => true],
                    ['id' => 'l-t1', 'name' => 'Gold-Schriftzug', 'type' => 'text', 'text' => $video->title, 'x' => 480, 'y' => 370, 'fontSize' => 32, 'color' => '#C5A059', 'fontFamily' => 'Playfair Display', 'opacity' => 1.0, 'startTime' => 2.3, 'endTime' => 6.0, 'animation' => 'fade'],
                    ['id' => 'l-t2', 'name' => 'Gold-Slogan', 'type' => 'text', 'text' => $video->subtitle ?: 'EIN FUNKE, DER BLEIBT', 'x' => 480, 'y' => 405, 'fontSize' => 12, 'color' => '#5C5549', 'fontFamily' => 'Outfit', 'opacity' => 1.0, 'startTime' => 2.6, 'endTime' => 6.0, 'animation' => 'fade']
                ]);
            } else if ($presetName === 'neon') {
                $layers = array_merge($layers, [
                    ['id' => 'l-bg', 'name' => 'Dunkler Space', 'type' => 'background', 'color' => '#09090E', 'useGradient' => false, 'opacity' => 1.0, 'startTime' => 0.0, 'endTime' => 6.0],
                    ['id' => 'l-part', 'name' => 'Cyan Funkenregen', 'type' => 'particles', 'particleType' => 'sparks', 'color' => '#06B6D4', 'x' => 480, 'y' => 270, 'width' => 960, 'height' => 540, 'opacity' => 0.9, 'startTime' => 0.0, 'endTime' => 6.0],
                    ['id' => 'l-shape', 'name' => 'Neon Glüh-Ring', 'type' => 'shape', 'shapeType' => 'circle', 'color' => 'rgba(6, 182, 212, 0.1)', 'width' => 280, 'x' => 480, 'y' => 270, 'opacity' => 1.0, 'startTime' => 0.5, 'endTime' => 6.0],
                    ['id' => 'l-t1', 'name' => 'Cyan Neon Text', 'type' => 'text', 'text' => strtoupper($video->title), 'x' => 480, 'y' => 260, 'fontSize' => 36, 'color' => '#06B6D4', 'fontFamily' => 'Outfit', 'opacity' => 1.0, 'startTime' => 1.0, 'endTime' => 6.0, 'animation' => 'typewriter'],
                    ['id' => 'l-t2', 'name' => 'Sub-Neon', 'type' => 'text', 'text' => strtoupper($video->subtitle ?: 'ENTERPRISE PRODUCTION'), 'x' => 480, 'y' => 310, 'fontSize' => 12, 'color' => '#38BDF8', 'fontFamily' => 'Outfit', 'opacity' => 1.0, 'startTime' => 2.0, 'endTime' => 6.0, 'animation' => 'fade']
                ]);
            } else if ($presetName === 'minimal') {
                $layers = array_merge($layers, [
                    ['id' => 'l-bg', 'name' => 'Lichtgrau', 'type' => 'background', 'color' => '#F3F4F6', 'useGradient' => false, 'opacity' => 1.0, 'startTime' => 0.0, 'endTime' => 6.0],
                    ['id' => 'l-shape', 'name' => 'Zentrierter Kreis', 'type' => 'shape', 'shapeType' => 'circle', 'color' => '#E5E7EB', 'width' => 320, 'x' => 480, 'y' => 270, 'opacity' => 1.0, 'startTime' => 0.2, 'endTime' => 6.0],
                    ['id' => 'l-t1', 'name' => 'Minimal Text', 'type' => 'text', 'text' => strtoupper($video->title), 'x' => 480, 'y' => 270, 'fontSize' => 40, 'color' => '#1F2937', 'fontFamily' => 'Outfit', 'opacity' => 1.0, 'startTime' => 1.2, 'endTime' => 6.0, 'animation' => 'slide-up']
                ]);
            }

            $video->update(['config' => $layers]);

            return [
                'status' => 'success',
                'message' => "Design-Preset '{$presetName}' erfolgreich auf Video {$id} angewendet."
            ];
        } catch (\Exception $e) {
            return [
                'status' => 'error',
                'message' => 'Fehler beim Anwenden des Presets: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Change format / aspect ratio of a video.
     */
    public static function executeVideoChangeFormat(array $args, $agent = null)
    {
        $id = $args['id'] ?? null;
        $aspectRatio = $args['aspect_ratio'] ?? null;

        if (!$id || !$aspectRatio) {
            return ['status' => 'error', 'message' => 'Video-ID und Seitenverhältnis sind erforderlich.'];
        }

        try {
            $video = MarketingVideo::find($id);
            if (!$video) {
                return ['status' => 'error', 'message' => "Video mit ID {$id} wurde nicht gefunden."];
            }

            $config = $video->config;
            if (!is_array($config)) {
                $config = [];
            }

            $found = false;
            foreach ($config as &$layer) {
                if (isset($layer['id']) && $layer['id'] === 'metadata') {
                    $layer['aspectRatio'] = $aspectRatio;
                    $found = true;
                    break;
                }
            }

            if (!$found) {
                array_unshift($config, ['id' => 'metadata', 'aspectRatio' => $aspectRatio]);
            }

            $video->update(['config' => $config]);

            return [
                'status' => 'success',
                'message' => "Seitenverhältnis für Video {$id} erfolgreich auf {$aspectRatio} geändert."
            ];
        } catch (\Exception $e) {
            return [
                'status' => 'error',
                'message' => 'Fehler beim Ändern des Seitenverhältnisses: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Add a layer to video config.
     */
    public static function executeVideoAddLayer(array $args, $agent = null)
    {
        $id = $args['id'] ?? null;
        $type = $args['type'] ?? null;
        $name = $args['name'] ?? null;
        $properties = $args['properties'] ?? [];

        if (!$id || !$type || !$name) {
            return ['status' => 'error', 'message' => 'Video-ID, Typ und Name sind erforderlich.'];
        }

        try {
            $video = MarketingVideo::find($id);
            if (!$video) {
                return ['status' => 'error', 'message' => "Video mit ID {$id} wurde nicht gefunden."];
            }

            $config = $video->config;
            if (!is_array($config)) {
                $config = [];
            }

            $newLayer = array_merge([
                'id' => 'layer-' . Str::random(8),
                'name' => $name,
                'type' => $type,
                'opacity' => 1.0,
                'startTime' => 0.0,
                'endTime' => 6.0,
                'animation' => 'none'
            ], $properties);

            $config[] = $newLayer;
            $video->update(['config' => $config]);

            return [
                'status' => 'success',
                'message' => "Ebene '{$name}' (Typ: {$type}) erfolgreich hinzugefügt (Ebenen-ID: {$newLayer['id']}).",
                'layer_id' => $newLayer['id']
            ];
        } catch (\Exception $e) {
            return [
                'status' => 'error',
                'message' => 'Fehler beim Hinzufügen der Ebene: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Update layer properties.
     */
    public static function executeVideoUpdateLayer(array $args, $agent = null)
    {
        $id = $args['id'] ?? null;
        $layerId = $args['layer_id'] ?? null;
        $properties = $args['properties'] ?? [];

        if (!$id || !$layerId || empty($properties)) {
            return ['status' => 'error', 'message' => 'Video-ID, Ebenen-ID und Eigenschaften sind erforderlich.'];
        }

        try {
            $video = MarketingVideo::find($id);
            if (!$video) {
                return ['status' => 'error', 'message' => "Video mit ID {$id} wurde nicht gefunden."];
            }

            $config = $video->config;
            if (!is_array($config)) {
                return ['status' => 'error', 'message' => 'Ungültige Ebenenkonfiguration.'];
            }

            $updated = false;
            foreach ($config as &$layer) {
                if (isset($layer['id']) && $layer['id'] === $layerId) {
                    $layer = array_merge($layer, $properties);
                    $updated = true;
                    break;
                }
            }

            if (!$updated) {
                return ['status' => 'error', 'message' => "Ebene mit ID {$layerId} wurde nicht gefunden."];
            }

            $video->update(['config' => $config]);

            return [
                'status' => 'success',
                'message' => "Ebene {$layerId} erfolgreich aktualisiert."
            ];
        } catch (\Exception $e) {
            return [
                'status' => 'error',
                'message' => 'Fehler beim Aktualisieren der Ebene: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Delete layer from config.
     */
    public static function executeVideoDeleteLayer(array $args, $agent = null)
    {
        $id = $args['id'] ?? null;
        $layerId = $args['layer_id'] ?? null;

        if (!$id || !$layerId) {
            return ['status' => 'error', 'message' => 'Video-ID und Ebenen-ID sind erforderlich.'];
        }

        try {
            $video = MarketingVideo::find($id);
            if (!$video) {
                return ['status' => 'error', 'message' => "Video mit ID {$id} wurde nicht gefunden."];
            }

            $config = $video->config;
            if (!is_array($config)) {
                return ['status' => 'error', 'message' => 'Ungültige Ebenenkonfiguration.'];
            }

            $initialCount = count($config);
            $config = array_values(array_filter($config, function($layer) use ($layerId) {
                if (isset($layer['id']) && $layer['id'] === 'layer-bg') {
                    return true; // Hintergrund schützen
                }
                return !isset($layer['id']) || $layer['id'] !== $layerId;
            }));

            if (count($config) === $initialCount) {
                return ['status' => 'error', 'message' => "Ebene mit ID {$layerId} wurde nicht gefunden oder konnte nicht gelöscht werden (z.B. Hintergrund)."];
            }

            $video->update(['config' => $config]);

            return [
                'status' => 'success',
                'message' => "Ebene {$layerId} erfolgreich entfernt."
            ];
        } catch (\Exception $e) {
            return [
                'status' => 'error',
                'message' => 'Fehler beim Löschen der Ebene: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Set talking AI Avatar on video.
     */
    public static function executeVideoSetAvatar(array $args, $agent = null)
    {
        $id = $args['id'] ?? null;
        $scriptText = $args['script_text'] ?? null;
        if (!$id || !$scriptText) {
            return ['status' => 'error', 'message' => 'Video-ID und Sprechtext sind erforderlich.'];
        }

        try {
            $video = MarketingVideo::find($id);
            if (!$video) {
                return ['status' => 'error', 'message' => "Video mit ID {$id} wurde nicht gefunden."];
            }

            $config = $video->config;
            if (!is_array($config)) {
                $config = [];
            }

            $avatarUrl = $args['avatar_url'] ?? '/shop/ai/images/funkira_selfie.png';
            $voiceId = $args['voice_id'] ?? 'de-DE-Wavenet-D';
            $x = $args['x'] ?? 800;
            $y = $args['y'] ?? 400;
            $w = $args['width'] ?? 120;
            $h = $args['height'] ?? 120;
            $style = $args['style'] ?? 'circle';

            $found = false;
            foreach ($config as &$layer) {
                if (isset($layer['type']) && $layer['type'] === 'avatar') {
                    $layer['scriptText'] = $scriptText;
                    $layer['avatarUrl'] = $avatarUrl;
                    $layer['voiceId'] = $voiceId;
                    $layer['x'] = $x;
                    $layer['y'] = $y;
                    $layer['width'] = $w;
                    $layer['height'] = $h;
                    $layer['style'] = $style;
                    $found = true;
                    break;
                }
            }

            if (!$found) {
                $config[] = [
                    'id' => 'l-avatar-' . Str::random(6),
                    'name' => 'AI Talking Avatar',
                    'type' => 'avatar',
                    'avatarUrl' => $avatarUrl,
                    'scriptText' => $scriptText,
                    'voiceId' => $voiceId,
                    'style' => $style,
                    'x' => $x,
                    'y' => $y,
                    'width' => $w,
                    'height' => $h,
                    'opacity' => 1.0,
                    'startTime' => 0.0,
                    'endTime' => 6.0
                ];
            }

            $video->update(['config' => $config]);

            return [
                'status' => 'success',
                'message' => "AI Talking Avatar erfolgreich für Video {$id} konfiguriert."
            ];
        } catch (\Exception $e) {
            return [
                'status' => 'error',
                'message' => 'Fehler beim Konfigurieren des Avatars: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Set background audio or TTS script.
     */
    public static function executeVideoSetAudio(array $args, $agent = null)
    {
        $id = $args['id'] ?? null;
        $scriptText = $args['script_text'] ?? null;
        if (!$id || !$scriptText) {
            return ['status' => 'error', 'message' => 'Video-ID und Sprechtext sind erforderlich.'];
        }

        try {
            $video = MarketingVideo::find($id);
            if (!$video) {
                return ['status' => 'error', 'message' => "Video mit ID {$id} wurde nicht gefunden."];
            }

            $config = $video->config;
            if (!is_array($config)) {
                $config = [];
            }

            $audioUrl = $args['audio_url'] ?? 'calm';
            $voiceId = $args['voice_id'] ?? 'de-DE-Wavenet-D';
            $volume = $args['volume'] ?? 0.5;

            $found = false;
            foreach ($config as &$layer) {
                if (isset($layer['type']) && $layer['type'] === 'audio') {
                    $layer['scriptText'] = $scriptText;
                    $layer['audioUrl'] = $audioUrl;
                    $layer['voiceId'] = $voiceId;
                    $layer['volume'] = $volume;
                    $found = true;
                    break;
                }
            }

            if (!$found) {
                $config[] = [
                    'id' => 'l-audio-' . Str::random(6),
                    'name' => 'Audio Voiceover',
                    'type' => 'audio',
                    'audioUrl' => $audioUrl,
                    'scriptText' => $scriptText,
                    'voiceId' => $voiceId,
                    'volume' => $volume,
                    'startTime' => 0.0,
                    'endTime' => 6.0
                ];
            }

            $video->update(['config' => $config]);

            return [
                'status' => 'success',
                'message' => "Audio Voiceover erfolgreich für Video {$id} eingerichtet."
            ];
        } catch (\Exception $e) {
            return [
                'status' => 'error',
                'message' => 'Fehler beim Konfigurieren des Audios: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Set dynamic subtitles.
     */
    public static function executeVideoSetSubtitles(array $args, $agent = null)
    {
        $id = $args['id'] ?? null;
        $text = $args['text'] ?? null;
        if (!$id || !$text) {
            return ['status' => 'error', 'message' => 'Video-ID und Untertitel-Text sind erforderlich.'];
        }

        try {
            $video = MarketingVideo::find($id);
            if (!$video) {
                return ['status' => 'error', 'message' => "Video mit ID {$id} wurde nicht gefunden."];
            }

            $config = $video->config;
            if (!is_array($config)) {
                $config = [];
            }

            $color = $args['color'] ?? '#ffffff';
            $fontSize = $args['font_size'] ?? 24;
            $y = $args['y'] ?? 470;

            $found = false;
            foreach ($config as &$layer) {
                if (isset($layer['type']) && $layer['type'] === 'subtitles') {
                    $layer['text'] = $text;
                    $layer['color'] = $color;
                    $layer['fontSize'] = $fontSize;
                    $layer['y'] = $y;
                    $found = true;
                    break;
                }
            }

            if (!$found) {
                $config[] = [
                    'id' => 'l-subtitles-' . Str::random(6),
                    'name' => 'Auto Captions',
                    'type' => 'subtitles',
                    'text' => $text,
                    'color' => $color,
                    'fontSize' => $fontSize,
                    'fontFamily' => 'Outfit',
                    'x' => 480,
                    'y' => $y,
                    'opacity' => 1.0,
                    'startTime' => 0.0,
                    'endTime' => 6.0
                ];
            }

            $video->update(['config' => $config]);

            return [
                'status' => 'success',
                'message' => "Automatische Untertitel erfolgreich für Video {$id} eingerichtet."
            ];
        } catch (\Exception $e) {
            return [
                'status' => 'error',
                'message' => 'Fehler beim Konfigurieren der Untertitel: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Generate or set background details.
     */
    public static function executeVideoGenerateAiBackground(array $args, $agent = null)
    {
        $id = $args['id'] ?? null;
        if (!$id) {
            return ['status' => 'error', 'message' => 'Video-ID ist erforderlich.'];
        }

        try {
            $video = MarketingVideo::find($id);
            if (!$video) {
                return ['status' => 'error', 'message' => "Video mit ID {$id} wurde nicht gefunden."];
            }

            $config = $video->config;
            if (!is_array($config)) {
                $config = [];
            }

            $color = $args['color'] ?? '#FAF9F6';
            $imageUrl = $args['image_url'] ?? null;
            $useGradient = $args['use_gradient'] ?? false;
            $prompt = $args['prompt'] ?? null;

            if ($prompt) {
                $color = '#0B0B0F';
                $useGradient = true;
                $imageUrl = 'shop/ai/images/generated_bg.png';
            }

            $found = false;
            foreach ($config as &$layer) {
                if (isset($layer['id']) && $layer['id'] === 'layer-bg') {
                    $layer['color'] = $color;
                    $layer['useGradient'] = $useGradient;
                    if ($imageUrl) {
                        $layer['type'] = 'background';
                        $layer['imageUrl'] = $imageUrl;
                        $layer['gradientColor'] = '#C5A059';
                    }
                    if ($prompt) {
                        $layer['prompt'] = $prompt;
                    }
                    $found = true;
                    break;
                }
            }

            $video->update(['config' => $config]);

            return [
                'status' => 'success',
                'message' => "Hintergrund erfolgreich für Video {$id} angepasst." . ($prompt ? " (KI-Generierungs-Prompt erfasst: '{$prompt}')" : "")
            ];
        } catch (\Exception $e) {
            return [
                'status' => 'error',
                'message' => 'Fehler beim Generieren des Hintergrunds: ' . $e->getMessage()
            ];
        }
    }
}
