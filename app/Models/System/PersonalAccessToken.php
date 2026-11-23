<?php

namespace App\Models\System;

use App\Support\Blueprint\Concerns\InteractsWithSystemBlueprint;
use App\Support\Blueprint\Contracts\HasSystemBlueprint;
use App\Support\Blueprint\Enums\ActorScope;
use App\Support\Blueprint\Enums\AiAccessPolicy;
use App\Support\Blueprint\Enums\ComplianceRegulation;
use App\Support\Blueprint\Enums\DataSensitivity;
use App\Support\Blueprint\Enums\LifecycleBehavior;
use App\Support\Blueprint\Enums\RetentionPolicy;
use App\Support\Blueprint\Enums\SystemDepartment;
use App\Support\Blueprint\ModelBlueprint;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Cache;
use Laravel\Sanctum\PersonalAccessToken as SanctumPersonalAccessToken;

class PersonalAccessToken extends SanctumPersonalAccessToken implements HasSystemBlueprint
{
    use InteractsWithSystemBlueprint;

    public static function blueprint(): ModelBlueprint
    {
        return ModelBlueprint::make(
            label: 'Sanctum API-Zugriffstoken',
            description: 'Kryptographischer API-Bearer-Token für Funkenwerk Android, Desktop-Suite und Mini-PC mit 5-Minuten-Schreibdrosselung.',
            department: SystemDepartment::System,
            navigationNodes: ['admin.settings'],
            lifecycle: LifecycleBehavior::TransactionalWorkflow,
            sensitivity: DataSensitivity::InternalErp,
            retention: RetentionPolicy::ShortLived_30Days,
            aiAccess: AiAccessPolicy::None,
            actorsRead: [ActorScope::Admin],
            actorsWrite: [ActorScope::Admin, ActorScope::SystemCron],
            regulations: [ComplianceRegulation::DSGVO_Art15_Art30],
            externalChannels: [],
            dependsOnSettings: [],
            isGdprArt15Exportable: false,
            customerForeignKey: null,
            containsProductionAssets: false,
            assetColumns: [],
        );
    }
    /**
     * Minimum interval (in seconds) between DB writes when only `last_used_at` changes.
     * Prevents InnoDB row-lock contention (`SQLSTATE 1205 Lock wait timeout`) on parallel
     * mobile/desktop/Live-Call API requests sharing the same bearer token.
     */
    public const LAST_USED_TOUCH_INTERVAL_SECONDS = 300;

    public function save(array $options = []): bool
    {
        if ($this->exists && $this->isOnlyLastUsedTouch()) {
            $originalLastUsed = $this->getOriginal('last_used_at');
            $originalTimestamp = null;

            if ($originalLastUsed instanceof \DateTimeInterface) {
                $originalTimestamp = $originalLastUsed->getTimestamp();
            } elseif (is_string($originalLastUsed) && $originalLastUsed !== '') {
                $parsed = strtotime($originalLastUsed);
                $originalTimestamp = $parsed !== false ? $parsed : null;
            }

            $nowTs = now()->getTimestamp();
            if ($originalTimestamp !== null && ($nowTs - $originalTimestamp) < self::LAST_USED_TOUCH_INTERVAL_SECONDS) {
                $this->syncOriginalAttribute('last_used_at');
                return true;
            }

            $throttleKey = 'sanctum_pat_touch_' . $this->getKey();
            try {
                if (!Cache::store('file')->add($throttleKey, $nowTs, self::LAST_USED_TOUCH_INTERVAL_SECONDS)) {
                    $this->syncOriginalAttribute('last_used_at');
                    return true;
                }
            } catch (\Throwable) {
                // Continue gracefully if file cache is unavailable
            }

            try {
                return parent::save($options);
            } catch (QueryException $e) {
                // Never fail an authenticated API/Live-Call request if personal_access_tokens is row-locked
                $this->syncOriginalAttribute('last_used_at');
                return true;
            }
        }

        return parent::save($options);
    }

    protected function isOnlyLastUsedTouch(): bool
    {
        $dirty = $this->getDirty();
        if (empty($dirty) || !array_key_exists('last_used_at', $dirty)) {
            return false;
        }

        unset($dirty['last_used_at'], $dirty['updated_at']);

        return empty($dirty);
    }
}
