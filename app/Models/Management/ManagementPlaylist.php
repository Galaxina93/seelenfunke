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
use Illuminate\Database\Eloquent\Relations\HasMany;

class ManagementPlaylist extends Model implements HasSystemBlueprint
{
    use InteractsWithSystemBlueprint;
    use HasUuids;

    public static function blueprint(): ModelBlueprint
    {
        return ModelBlueprint::make(
            label: 'Management-Musik-Playlist',
            description: 'Zentrale Musik-Playlist für Funkenwerk Desktop, Android App und Leitung.',
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
            assetColumns: [],
        );
    }

    protected $table = 'management_playlists';

    protected $fillable = [
        'id',
        'name',
        'description',
        'cover_url',
        'source_url',
        'icon',
        'color',
        'position',
        'is_favorite',
    ];

    protected $casts = [
        'is_favorite' => 'boolean',
        'position' => 'integer',
    ];

    public function tracks(): HasMany
    {
        return $this->hasMany(ManagementTrack::class, 'playlist_id')->orderBy('position', 'asc');
    }
}
