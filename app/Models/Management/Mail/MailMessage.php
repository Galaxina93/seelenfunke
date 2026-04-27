<?php

namespace App\Models\Management\Mail;

use Illuminate\Database\Eloquent\Model;

class MailMessage extends Model
{
    protected $guarded = [];

    protected $casts = [
        'is_read' => 'boolean',
        'is_archived' => 'boolean',
        'has_attachments' => 'boolean',
        'received_at' => 'datetime',
        'tags' => 'array',
    ];

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
            foreach (['iframe', 'object', 'embed', 'applet', 'svg'] as $tag) {
                foreach ($xpath->query('//' . $tag) as $node) {
                    $node->parentNode->removeChild($node);
                }
            }

            // 3. Jegliche Inline Event-Handler Attribute löschen (ohne Regex)
            foreach ($xpath->query('//@*[starts-with(name(), "on")]') as $attr) {
                $attr->ownerElement->removeAttributeNode($attr);
            }

            // 4. "javascript:" hrefs löschen
            foreach ($xpath->query('//@href[starts-with(translate(., "ABCDEFGHIJKLMNOPQRSTUVWXYZ", "abcdefghijklmnopqrstuvwxyz"), "javascript:")]') as $attr) {
                $attr->ownerElement->setAttribute('href', '#');
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

                foreach ($xpath->query('//img') as $img) {
                    $src = trim($img->getAttribute('src'));
                    if (str_starts_with(strtolower($src), 'cid:')) {
                        $cidSource = substr($src, 4);
                        
                        $match = $inlineAttachments->first(function($att) use ($cidSource) {
                            return trim($att->content_id, '<>') === trim($cidSource, '<>');
                        });

                        if ($match && \Illuminate\Support\Facades\Storage::exists($match->path)) {
                            $content = \Illuminate\Support\Facades\Storage::get($match->path);
                            $base64 = base64_encode($content);
                            $mime = $match->content_type ?: 'image/png';
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
