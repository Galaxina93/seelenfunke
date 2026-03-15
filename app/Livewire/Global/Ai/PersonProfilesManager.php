<?php

namespace App\Livewire\Global\Ai;

use App\Models\PersonProfile;
use Livewire\Attributes\Validate;
use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\WithPagination;

class PersonProfilesManager extends Component
{
    use WithPagination, WithFileUploads;

    public $search = '';
    public $activeProfileId = null;
    public $isEditing = false;

    #[Validate('nullable|image|max:10240')]
    public $avatar_upload;

    public $editForm = [
        'id' => null,
        'first_name' => '',
        'last_name' => '',
        'nickname' => '',
        'relation_type' => '',
        'avatar_path' => '',
        'links' => [],
        'birthday' => '',
        'email' => '',
        'phone' => '',
        'system_instructions' => '',
        'ai_learned_facts' => '',
        'street' => '',
        'postal_code' => '',
        'city' => '',
        'country' => ''
    ];

    protected $queryString = ['search'];

    public function updatingSearch()
    {
        $this->resetPage();
    }

    protected function rules()
    {
        return [
            'editForm.first_name' => 'required|string|max:255',
            'editForm.last_name' => 'nullable|string|max:255',
            'editForm.nickname' => 'nullable|string|max:255',
            'editForm.relation_type' => 'nullable|string|max:255',
            'editForm.birthday' => 'nullable|date',
            'editForm.email' => 'nullable|email|max:255',
            'editForm.phone' => 'nullable|string|max:255',
            'editForm.system_instructions' => 'nullable|string',
            'editForm.ai_learned_facts' => 'nullable|string',
            'editForm.street' => 'nullable|string|max:255',
            'editForm.postal_code' => 'nullable|string|max:50',
            'editForm.city' => 'nullable|string|max:255',
            'editForm.country' => 'nullable|string|max:255',
            'editForm.links' => 'nullable|array',
            'editForm.links.*.name' => 'required|string|max:255',
            'editForm.links.*.url' => 'required|url|max:255',
        ];
    }

    public function selectProfile($id)
    {
        $this->activeProfileId = $id;
        $this->isEditing = false;
        $this->avatar_upload = null;
    }

    public function createProfile()
    {
        $this->resetValidation();
        $this->activeProfileId = null;
        $this->isEditing = true;
        $this->avatar_upload = null;
        $this->editForm = [
            'id' => null,
            'first_name' => '',
            'last_name' => '',
            'nickname' => '',
            'relation_type' => '',
            'avatar_path' => '',
            'links' => [],
            'birthday' => '',
            'email' => '',
            'phone' => '',
            'system_instructions' => '',
            'ai_learned_facts' => '',
            'street' => '',
            'postal_code' => '',
            'city' => '',
            'country' => ''
        ];
    }

    public function editProfile($id)
    {
        $this->resetValidation();
        $profile = PersonProfile::findOrFail($id);

        $this->activeProfileId = $profile->id;
        $this->isEditing = true;
        $this->avatar_upload = null;

        $this->editForm = [
            'id' => $profile->id,
            'first_name' => $profile->first_name,
            'last_name' => $profile->last_name,
            'nickname' => $profile->nickname,
            'relation_type' => $profile->relation_type,
            'avatar_path' => $profile->avatar_path,
            'links' => is_array($profile->links) ? $profile->links : [],
            'birthday' => $profile->birthday ? $profile->birthday->format('Y-m-d') : '',
            'email' => $profile->email,
            'phone' => $profile->phone,
            'system_instructions' => $profile->system_instructions,
            'ai_learned_facts' => $profile->ai_learned_facts,
            'street' => $profile->street,
            'postal_code' => $profile->postal_code,
            'city' => $profile->city,
            'country' => $profile->country
        ];
    }

    public function addLink()
    {
        $this->editForm['links'][] = ['name' => '', 'url' => ''];
    }

    public function removeLink($index)
    {
        unset($this->editForm['links'][$index]);
        $this->editForm['links'] = array_values($this->editForm['links']);
    }

    public function cancelEditing()
    {
        $this->isEditing = false;
        $this->avatar_upload = null;
        $this->resetValidation();
    }

    public function saveProfile()
    {
        $this->validate();

        $data = [
            'first_name' => $this->editForm['first_name'],
            'last_name' => $this->editForm['last_name'],
            'nickname' => $this->editForm['nickname'],
            'relation_type' => $this->editForm['relation_type'],
            'links' => $this->editForm['links'],
            'birthday' => $this->editForm['birthday'] ?: null,
            'email' => $this->editForm['email'],
            'phone' => $this->editForm['phone'],
            'system_instructions' => $this->editForm['system_instructions'],
            'ai_learned_facts' => $this->editForm['ai_learned_facts'],
            'street' => $this->editForm['street'],
            'postal_code' => $this->editForm['postal_code'],
            'city' => $this->editForm['city'],
            'country' => $this->editForm['country']
        ];

        if ($this->avatar_upload) {
            $data['avatar_path'] = $this->avatar_upload->store('person_profiles', 'public');
        }

        if ($this->editForm['id']) {
            $profile = PersonProfile::findOrFail($this->editForm['id']);
            $profile->update($data);
            $message = 'Profil erfolgreich aktualisiert.';
        } else {
            $profile = PersonProfile::create($data);
            $this->activeProfileId = $profile->id;
            $message = 'Neues Profil angelegt.';
        }

        $this->isEditing = false;
        $this->avatar_upload = null;
        session()->flash('success', $message);
    }

    public function deleteProfile($id)
    {
        PersonProfile::findOrFail($id)->delete();

        if ($this->activeProfileId == $id) {
            $this->activeProfileId = null;
            $this->isEditing = false;
        }

        session()->flash('success', 'Profil wurde gelöscht.');
    }

    public function toggleFavorite($id)
    {
        $profile = PersonProfile::findOrFail($id);
        $profile->is_favorite = !$profile->is_favorite;
        $profile->save();

        // No flash message needed for seamless inline experience
    }

    public function render()
    {
        $profiles = PersonProfile::where('first_name', 'like', '%' . $this->search . '%')
            ->orWhere('last_name', 'like', '%' . $this->search . '%')
            ->orWhere('nickname', 'like', '%' . $this->search . '%')
            ->orderBy('is_favorite', 'desc')
            ->orderBy('first_name')
            ->get();

        if (!$this->activeProfileId && $profiles->isNotEmpty() && !$this->isEditing) {
            $this->activeProfileId = $profiles->first()->id;
        }

        $activeProfile = $this->activeProfileId ? PersonProfile::find($this->activeProfileId) : null;
        $totalCount = PersonProfile::count();

        return view('livewire.global.ai.person-profiles-manager', [
            'profiles' => $profiles,
            'activeProfile' => $activeProfile,
            'totalCount' => $totalCount
        ]);
    }
}
