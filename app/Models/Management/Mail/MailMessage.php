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
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $mail_account_id
 * @property string|null $message_id
 * @property string $folder
 * @property bool $is_archived
 * @property bool $is_read
 * @property bool $has_attachments
 * @property-read MailAccount|null $account
 * @property-read MailFolder|null $mailFolder
 * @property-read EloquentCollection<int, MailAttachment> $attachments
 */
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

    public const STANDARD_FOLDERS = [
        'INBOX',
        'Sent',
        'Drafts',
        'Junk',
        'Trash',
        'Archive',
        'Processed',
    ];

    protected $guarded = [];

    protected $casts = [
        'folder' => 'string',
        'is_read' => 'boolean',
        'is_archived' => 'boolean',
        'has_attachments' => 'boolean',
        'received_at' => 'datetime',
        'tags' => 'array',
    ];

    /**
     * Stellt sicher, dass jeder E-Mail-Zeitstempel (z. B. -0800 PST oder +0000 UTC)
     * vor dem Speichern in die MySQL-DATETIME-Spalte exakt in die App-Zeitzone
     * (Europe/Berlin) konvertiert wird, damit "Alle Posteingänge" immer exakt sortiert.
     */
    public function setReceivedAtAttribute(mixed $value): void
    {
        if ($value === null || $value === '') {
            $this->attributes['received_at'] = null;
            return;
        }

        $appTz = (string) config('app.timezone', 'Europe/Berlin');
        try {
            if ($value instanceof \DateTimeInterface) {
                $dt = \Carbon\Carbon::instance($value)->copy()->setTimezone($appTz);
            } else {
                $dt = \Carbon\Carbon::parse((string) $value)->setTimezone($appTz);
            }
            $this->attributes['received_at'] = $dt->format($this->getDateFormat());
        } catch (\Throwable) {
            $this->attributes['received_at'] = $this->fromDateTime($value);
        }
    }

    protected $appends = [
        'safe_body_html',
    ];

    public function toArray(): array
    {
        $hadFolderRelation = $this->relationLoaded('folder');
        $loadedFolderModel = $hadFolderRelation ? $this->getRelation('folder') : null;

        $array = parent::toArray();

        if ($hadFolderRelation) {
            $array['folder'] = $this->getAttributeValue('folder');
            if (!array_key_exists('mail_folder', $array)) {
                $array['mail_folder'] = $loadedFolderModel instanceof MailFolder
                    ? $loadedFolderModel->toArray()
                    : null;
            }
        }

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

    public function account(): BelongsTo
    {
        return $this->belongsTo(MailAccount::class, 'mail_account_id');
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(MailAttachment::class, 'mail_message_id');
    }

    /**
     * Optionale Verknüpfung auf benutzerdefinierte IMAP-Ordner (kombiniert über mail_account_id + folder = name).
     * Standard-Systemordner wie INBOX, Processed oder Archive besitzen keinen eigenen MailFolder-Datensatz und liefern null.
     */
    public function mailFolder(): BelongsTo
    {
        return $this->newCompositeMailFolderRelation('mailFolder');
    }

    /**
     * Eloquent-kompatibler Relations-Alias auf mailFolder() für with('folder'), load('folder') und whereHas('folder').
     * Der direkte Attributzugriff $message->folder sowie toArray()['folder'] bleiben durch $casts['folder'] = 'string'
     * und den Serialisierungs-Schutz in toArray() jederzeit strikt der Ordner-String (z. B. 'INBOX', 'Processed').
     */
    public function folder(): BelongsTo
    {
        return $this->newCompositeMailFolderRelation('folder');
    }

    protected function newCompositeMailFolderRelation(string $relationName): BelongsTo
    {
        $instance = $this->newRelatedInstance(MailFolder::class);

        return new class($instance->newQuery(), $this, 'folder', 'name', $relationName) extends BelongsTo {
            public function getResults()
            {
                if (
                    is_null($this->child->getAttributeValue($this->foreignKey))
                    || is_null($this->child->getAttributeValue('mail_account_id'))
                ) {
                    return $this->getDefaultFor($this->parent);
                }

                return $this->query->first() ?: $this->getDefaultFor($this->parent);
            }

            public function addConstraints(): void
            {
                if (static::$constraints) {
                    $this->query->where(
                        $this->getQualifiedOwnerKeyName(),
                        '=',
                        $this->child->getAttributeValue($this->foreignKey)
                    );
                    $this->query->where(
                        $this->related->qualifyColumn('mail_account_id'),
                        '=',
                        $this->child->getAttributeValue('mail_account_id')
                    );
                }
            }

            public function addEagerConstraints(array $models): void
            {
                parent::addEagerConstraints($models);

                $accountIds = [];
                foreach ($models as $model) {
                    $accId = $model instanceof MailMessage
                        ? $model->getAttributeValue('mail_account_id')
                        : $model->getAttribute('mail_account_id');
                    if ($accId !== null) {
                        $accountIds[] = (int) $accId;
                    }
                }

                if ($accountIds !== []) {
                    $this->query->whereIn(
                        $this->related->qualifyColumn('mail_account_id'),
                        array_values(array_unique($accountIds))
                    );
                }
            }

            public function match(array $models, EloquentCollection $results, $relation): array
            {
                $dictionary = [];

                foreach ($results as $result) {
                    $accId = $result->getAttribute('mail_account_id');
                    $name = $result->getAttribute($this->ownerKey);
                    if ($accId !== null && $name !== null && $name !== '') {
                        $dictionary[(int) $accId . '|' . (string) $name] = $result;
                    }
                }

                foreach ($models as $model) {
                    $accId = $model instanceof MailMessage
                        ? $model->getAttributeValue('mail_account_id')
                        : $model->getAttribute('mail_account_id');
                    $folderName = $model instanceof MailMessage
                        ? $model->getAttributeValue($this->foreignKey)
                        : $model->getAttribute($this->foreignKey);

                    $key = ($accId !== null && $folderName !== null && $folderName !== '')
                        ? ((int) $accId . '|' . (string) $folderName)
                        : null;

                    $matched = ($key !== null && isset($dictionary[$key]))
                        ? $dictionary[$key]
                        : $this->getDefaultFor($model);

                    $model->setRelation($relation, $matched);
                    if ($relation === 'folder') {
                        $model->setRelation('mailFolder', $matched);
                    }
                }

                return $models;
            }

            public function getRelationExistenceQuery(Builder $query, Builder $parentQuery, $columns = ['*']): Builder
            {
                $query = parent::getRelationExistenceQuery($query, $parentQuery, $columns);

                return $query->whereColumn(
                    $this->child->qualifyColumn('mail_account_id'),
                    '=',
                    $query->qualifyColumn('mail_account_id')
                );
            }

            protected function getForeignKeyFrom(Model $model): mixed
            {
                return $model instanceof MailMessage
                    ? $model->getAttributeValue($this->foreignKey)
                    : parent::getForeignKeyFrom($model);
            }
        };
    }

    public function getSafeBodyHtmlAttribute()
    {
        $html = $this->body_html;
        if (!$html) {
            return $html;
        }

        $dom = new \DOMDocument();
        libxml_use_internal_errors(true); // Verhindert PHP Warnings bei unsauberem E-Mail HTML
        
        // Ensure valid UTF-8 and encode non-ASCII characters as numeric entities for DOMDocument
        if (!mb_check_encoding($html, 'UTF-8')) {
            $html = mb_convert_encoding($html, 'UTF-8', 'Windows-1252,ISO-8859-1,UTF-8');
        }
        $htmlWrapped = mb_encode_numericentity($html, [0x80, 0x10FFFF, 0, ~0], 'UTF-8');
        
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
