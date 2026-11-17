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

class MailFolder extends Model implements HasSystemBlueprint
{
    
    use InteractsWithSystemBlueprint;

    /**
     * Digitales Architektur- & Compliance-Typenschild (Living Documentation).
     */
    public static function blueprint(): ModelBlueprint
    {
        return ModelBlueprint::make(
            label: 'E-Mail-Postfachordner',
            description: 'Synchronisierte IMAP-Ordnerstruktur (Posteingang, Gesendet, Rechnungen, Archiv).',
            department: SystemDepartment::Management,
            navigationNodes: ['admin.management.mail'],
            lifecycle: LifecycleBehavior::MasterData,
            sensitivity: DataSensitivity::InternalErp,
            retention: RetentionPolicy::PermanentMasterData,
            aiAccess: AiAccessPolicy::ReadOnly,
            actorsRead: [ActorScope::Admin],
            actorsWrite: [ActorScope::Admin, ActorScope::SystemCron],
            regulations: [],
            externalChannels: [ExternalChannel::IMAP_SMTP_Mail],
            dependsOnSettings: [],
            isGdprArt15Exportable: false,
            customerForeignKey: null,
            containsProductionAssets: false,
            assetColumns: [],
        );
    }

    protected $guarded = [];

    public function account(): BelongsTo
    {
        return $this->belongsTo(MailAccount::class, 'mail_account_id');
    }

    /**
     * Alle Nachrichten dieses Kontos, die diesem benutzerdefinierten Ordner (mail_account_id + name = folder) zugeordnet sind.
     */
    public function messages(): HasMany
    {
        $instance = $this->newRelatedInstance(MailMessage::class);

        return new class($instance->newQuery(), $this, $instance->getTable() . '.folder', 'name') extends HasMany {
            public function addConstraints(): void
            {
                parent::addConstraints();

                if (static::$constraints) {
                    $this->getRelationQuery()->where(
                        $this->related->qualifyColumn('mail_account_id'),
                        '=',
                        $this->parent->getAttribute('mail_account_id')
                    );
                }
            }

            public function addEagerConstraints(array $models): void
            {
                parent::addEagerConstraints($models);

                $accountIds = [];
                foreach ($models as $model) {
                    $accId = $model->getAttribute('mail_account_id');
                    if ($accId !== null) {
                        $accountIds[] = (int) $accId;
                    }
                }

                if ($accountIds !== []) {
                    $this->getRelationQuery()->whereIn(
                        $this->related->qualifyColumn('mail_account_id'),
                        array_values(array_unique($accountIds))
                    );
                }
            }

            public function match(array $models, EloquentCollection $results, $relation): array
            {
                $dictionary = [];
                $foreign = $this->getForeignKeyName();

                foreach ($results as $item) {
                    $accId = $item instanceof MailMessage
                        ? $item->getAttributeValue('mail_account_id')
                        : $item->getAttribute('mail_account_id');
                    $folderName = $item instanceof MailMessage
                        ? $item->getAttributeValue($foreign)
                        : $item->getAttribute($foreign);

                    if ($accId !== null && $folderName !== null && $folderName !== '') {
                        $dictionary[(int) $accId . '|' . (string) $folderName][] = $item;
                    }
                }

                foreach ($models as $model) {
                    $accId = $model->getAttribute('mail_account_id');
                    $name = $model->getAttribute($this->localKey);
                    $key = ($accId !== null && $name !== null && $name !== '')
                        ? ((int) $accId . '|' . (string) $name)
                        : null;

                    if ($key !== null && isset($dictionary[$key])) {
                        $model->setRelation($relation, $this->related->newCollection($dictionary[$key]));
                    }
                }

                return $models;
            }

            public function getRelationExistenceQuery(Builder $query, Builder $parentQuery, $columns = ['*']): Builder
            {
                $query = parent::getRelationExistenceQuery($query, $parentQuery, $columns);

                return $query->whereColumn(
                    $this->parent->qualifyColumn('mail_account_id'),
                    '=',
                    $query->qualifyColumn('mail_account_id')
                );
            }
        };
    }
}
