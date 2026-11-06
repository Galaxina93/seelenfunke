<?php

namespace App\Models\Management;

use App\Support\Blueprint\Concerns\InteractsWithSystemBlueprint;
use App\Support\Blueprint\Contracts\HasSystemBlueprint;
use App\Support\Blueprint\Enums\ActorScope;
use App\Support\Blueprint\Enums\AiAccessPolicy;
use App\Support\Blueprint\Enums\DataSensitivity;
use App\Support\Blueprint\Enums\LifecycleBehavior;
use App\Support\Blueprint\Enums\RetentionPolicy;
use App\Support\Blueprint\Enums\SystemDepartment;
use App\Support\Blueprint\ModelBlueprint;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ManagementTrack extends Model implements HasSystemBlueprint
{
    use InteractsWithSystemBlueprint;
    use HasUuids;

    public static function blueprint(): ModelBlueprint
    {
        return ModelBlueprint::make(
            label: 'Management-Musik-Track',
            description: 'Einzelner Musik-Titel innerhalb einer Funkenwerk-Playlist im geschützten Privat-Speicher.',
            department: SystemDepartment::Management,
            navigationNodes: ['admin.management.music'],
            lifecycle: LifecycleBehavior::MasterData,
            sensitivity: DataSensitivity::InternalErp,
            retention: RetentionPolicy::PermanentMasterData,
            aiAccess: AiAccessPolicy::AutonomousReadWrite,
            actorsRead: [ActorScope::Admin],
            actorsWrite: [ActorScope::Admin],
            regulations: [],
            externalChannels: [],
            dependsOnSettings: [],
            isGdprArt15Exportable: false,
            customerForeignKey: null,
            containsProductionAssets: false,
            assetColumns: ['file_path'],
        );
    }

    protected $table = 'management_tracks';

    protected $fillable = [
        'id',
        'playlist_id',
        'title',
        'artist',
        'duration_seconds',
        'file_path',
        'cover_url',
        'external_id',
        'source_url',
        'status',
        'file_size',
        'position',
        'is_favorite',
        'play_count',
    ];

    protected $casts = [
        'duration_seconds' => 'integer',
        'file_size' => 'integer',
        'position' => 'integer',
        'is_favorite' => 'boolean',
        'play_count' => 'integer',
    ];

    public function playlist(): BelongsTo
    {
        return $this->belongsTo(ManagementPlaylist::class, 'playlist_id');
    }
}
