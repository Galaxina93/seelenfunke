<?php

namespace App\Models\Management\Mail;


use App\Support\Blueprint\Concerns\InteractsWithSystemBlueprint;
use App\Support\Blueprint\Contracts\HasSystemBlueprint;
use App\Support\Blueprint\Enums\ActorScope;
use App\Support\Blueprint\Enums\AiAccessPolicy;
use App\Support\Blueprint\Enums\ComplianceRegulation;
use App\Support\Blueprint\Enums\DataSensitivity;
use App\Support\Blueprint\Enums\ExternalChannel;
use App\Support\Blueprint\Enums\LifecycleBehavior;
use App\Support\Blueprint\Enums\RetentionPolicy;
use App\Support\Blueprint\Enums\SystemDepartment;
use App\Support\Blueprint\ModelBlueprint;
use Illuminate\Database\Eloquent\Model;

class MailMessage extends Model implements HasSystemBlueprint
{
    
    use InteractsWithSystemBlueprint;

    /**
     * Digitales Architektur- & Compliance-Typenschild (Living Documentation).
     */
    public static function blueprint(): ModelBlueprint
    {
        return ModelBlueprint::make(
            label: 'Geschäftliche E-Mail-Nachricht',
            description: 'Archivierte ein- und ausgehende Geschäftsbriefe und Kunden-E-Mails nach § 257 HGB.',
            department: SystemDepartment::Management,
            navigationNodes: ['admin.management.mail'],
            lifecycle: LifecycleBehavior::ImmutableAfterFinalized,
            sensitivity: DataSensitivity::PersonalPII,
            retention: RetentionPolicy::TaxBusinessLetters_6Years,
            aiAccess: AiAccessPolicy::ReadAndPropose,
            actorsRead: [ActorScope::Admin],
            actorsWrite: [ActorScope::Admin, ActorScope::SystemCron],
            regulations: [ComplianceRegulation::GoBD, ComplianceRegulation::DSGVO_Art15_Art30],
            externalChannels: [ExternalChannel::IMAP_SMTP_Mail],
            dependsOnSettings: [],
            isGdprArt15Exportable: false,
            customerForeignKey: null,
            containsProductionAssets: false,
            assetColumns: [],
        );
    }

protected $guarded = [];

    protected $casts = [
        'is_read' => 'boolean',
        'is_archived' => 'boolean',
        'has_attachments' => 'boolean',
        'received_at' => 'datetime',
        'tags' => 'array',
    ];

    protected $appends = [
        'safe_body_html',
    ];

    public function toArray(): array
    {
        $array = parent::toArray();
        if (!empty($array['body_html'])) {
            $array['body_html'] = $this->safe_body_html;
        }
        return $array;
    }

    protected static function booted(): void
    {
        static::deleting(function (MailMessage $message) {
            foreach ($message->attachments()->get() as $attachment) {
                $attachment->delete();
            }
            if ($message->id) {
                \Illuminate\Support\Facades\Storage::disk('local')->deleteDirectory('leitung/crm/mail-attachments/' . $message->id);
            }
        });
    }

    public function account()
    {
        return $this->belongsTo(MailAccount::class, 'mail_account_id');
    }

    public function attachments()
    {
        return $this->hasMany(MailAttachment::class, 'mail_message_id');
    }

    public function getSafeBodyHtmlAttribute()
    {
        $html = $this->body_html;
        if (!$html) {
            return $html;
        }

        $dom = new \DOMDocument();
        libxml_use_internal_errors(true); // Verhindert PHP Warnings bei unsauberem E-Mail HTML
        
        // UTF-8 Hack to ensure proper encoding parsing
        $htmlWrapped = mb_convert_encoding($html, 'HTML-ENTITIES', 'UTF-8');
        
        if ($dom->loadHTML($htmlWrapped, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD)) {
            $xpath = new \DOMXPath($dom);
            
            // 1. Alle <script> Tags restlos entfernen
            foreach ($xpath->query('//script') as $node) {
                $node->parentNode->removeChild($node);
            }
            
            // 2. Böse Container entfernen
            foreach (['iframe', 'object', 'embed', 'applet', 'svg', 'base', 'form', 'meta', 'math'] as $tag) {
                foreach ($xpath->query('//' . $tag) as $node) {
                    $node->parentNode->removeChild($node);
                }
            }

            // 3. Jegliche Inline Event-Handler Attribute und srcdoc löschen (ohne Regex)
            foreach ($xpath->query('//@*[starts-with(name(), "on") or name()="srcdoc"]') as $attr) {
                $attr->ownerElement->removeAttributeNode($attr);
            }

            // 4. "javascript:" und "data:text/html" URLs in href/src/action/formaction löschen
            $urlAttrs = $xpath->query('//@*[name()="href" or name()="src" or name()="action" or name()="formaction" or name()="xlink:href" or local-name()="href"]');
            if ($urlAttrs) {
                foreach ($urlAttrs as $attr) {
                    $val = strtolower(trim(preg_replace('/[\x00-\x20]+/', '', $attr->value)));
                    if (str_starts_with($val, 'javascript:') || str_starts_with($val, 'vbscript:') || str_starts_with($val, 'data:text/html') || str_starts_with($val, 'data:image/svg') || str_starts_with($val, 'data:application/xhtml')) {
                        $attr->ownerElement->setAttribute($attr->nodeName, '#');
                    }
                }
            }

            // 5. Inline-Attachments (cid:...) auslesen und direkt als Base64 einbetten, sonst 1x1 Pixel
            $hasCid = false;
            foreach ($xpath->query('//img') as $img) {
                if (str_starts_with(strtolower(trim($img->getAttribute('src'))), 'cid:')) {
                    $hasCid = true;
                    break;
                }
            }

            if ($hasCid) {
                $inlineAttachments = \App\Models\Management\Mail\MailAttachment::where('mail_message_id', $this->id)
                    ->whereNotNull('content_id')
                    ->get();

                $allowedInlineMimes = ['image/png', 'image/jpeg', 'image/gif', 'image/webp'];

                foreach ($xpath->query('//img') as $img) {
                    $src = trim($img->getAttribute('src'));
                    if (str_starts_with(strtolower($src), 'cid:')) {
                        $cidSource = substr($src, 4);
                        
                        $match = $inlineAttachments->first(function($att) use ($cidSource) {
                            return trim($att->content_id, '<>') === trim($cidSource, '<>');
                        });

                        if ($match && !empty($match->path) && !str_contains((string) $match->path, '..') && \Illuminate\Support\Facades\Storage::exists($match->path)) {
                            $content = \Illuminate\Support\Facades\Storage::get($match->path);
                            $base64 = base64_encode($content);
                            $rawMime = strtolower(trim((string) ($match->content_type ?: 'image/png')));
                            $mime = in_array($rawMime, $allowedInlineMimes, true) ? $rawMime : 'image/png';
                            $img->setAttribute('src', 'data:' . $mime . ';base64,' . $base64);
                        } else {
                            // Transparentes Pixel einkleben um Browser-Fehler (ERR_UNKNOWN_URL_SCHEME) zu vermeiden
                            $img->setAttribute('src', 'data:image/gif;base64,R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7');
                        }
                    }
                }
            }

            $html = $dom->saveHTML();
        }
        
        libxml_clear_errors();

        return $html;
    }
}
