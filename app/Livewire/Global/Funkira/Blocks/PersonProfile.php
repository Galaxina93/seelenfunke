<?php

namespace App\Livewire\Global\Funkira\Blocks;

use Livewire\Component;
use App\Models\Funki\PersonProfile as PersonProfileModel;

class PersonProfile extends Component
{
    public $profileId;
    public $profile;

    public function mount($profileId)
    {
        $this->profileId = $profileId;
        $this->profile = PersonProfileModel::find($profileId);
    }

    public function render()
    {
        return view('livewire.global.funkira.blocks.person-profile');
    }
}
