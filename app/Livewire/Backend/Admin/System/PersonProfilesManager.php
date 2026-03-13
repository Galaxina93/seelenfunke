<?php

namespace App\Livewire\Backend\Admin\System;

use App\Models\Funki\PersonProfile;
use Livewire\Component;
use Livewire\WithPagination;

class PersonProfilesManager extends Component
{
    use WithPagination;

    public $search = '';
    public $showModal = false;
    public $isEditing = false;
    
    // Form fields
    public $profileId;
    public $first_name = '';
    public $last_name = '';
    public $nickname = '';
    public $relation_type = '';
    public $birthday = '';
    public $email = '';
    public $phone = '';
    public $system_instructions = '';
    public $ai_learned_facts = '';

    protected $queryString = ['search'];

    public function updatingSearch()
    {
        $this->resetPage();
    }

    protected function rules()
    {
        return [
            'first_name' => 'required|string|max:255',
            'last_name' => 'nullable|string|max:255',
            'nickname' => 'nullable|string|max:255',
            'relation_type' => 'nullable|string|max:255',
            'birthday' => 'nullable|date',
            'email' => 'nullable|email|max:255',
            'phone' => 'nullable|string|max:255',
            'system_instructions' => 'nullable|string',
            'ai_learned_facts' => 'nullable|string',
        ];
    }

    public function createProfile()
    {
        $this->resetValidation();
        $this->resetForm();
        $this->isEditing = false;
        $this->showModal = true;
    }

    public function editProfile($id)
    {
        $this->resetValidation();
        $profile = PersonProfile::findOrFail($id);
        
        $this->profileId = $profile->id;
        $this->first_name = $profile->first_name;
        $this->last_name = $profile->last_name;
        $this->nickname = $profile->nickname;
        $this->relation_type = $profile->relation_type;
        $this->birthday = $profile->birthday ? $profile->birthday->format('Y-m-d') : '';
        $this->email = $profile->email;
        $this->phone = $profile->phone;
        $this->system_instructions = $profile->system_instructions;
        $this->ai_learned_facts = $profile->ai_learned_facts;

        $this->isEditing = true;
        $this->showModal = true;
    }

    public function saveProfile()
    {
        $this->validate();

        $data = [
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
            'nickname' => $this->nickname,
            'relation_type' => $this->relation_type,
            'birthday' => $this->birthday ?: null,
            'email' => $this->email,
            'phone' => $this->phone,
            'system_instructions' => $this->system_instructions,
            'ai_learned_facts' => $this->ai_learned_facts,
        ];

        if ($this->isEditing) {
            $profile = PersonProfile::findOrFail($this->profileId);
            $profile->update($data);
            $message = 'Profil erfolgreich aktualisiert.';
        } else {
            PersonProfile::create($data);
            $message = 'Neues Profil angelegt.';
        }

        $this->showModal = false;
        session()->flash('success', $message);
    }

    public function deleteProfile($id)
    {
        PersonProfile::findOrFail($id)->delete();
        session()->flash('success', 'Profil wurde gelöscht.');
    }

    public function resetForm()
    {
        $this->profileId = null;
        $this->first_name = '';
        $this->last_name = '';
        $this->nickname = '';
        $this->relation_type = '';
        $this->birthday = '';
        $this->email = '';
        $this->phone = '';
        $this->system_instructions = '';
        $this->ai_learned_facts = '';
    }

    public function render()
    {
        $profiles = PersonProfile::where('first_name', 'like', '%' . $this->search . '%')
            ->orWhere('last_name', 'like', '%' . $this->search . '%')
            ->orWhere('nickname', 'like', '%' . $this->search . '%')
            ->orderBy('first_name')
            ->paginate(12);

        return view('livewire.backend.admin.system.person-profiles-manager', [
            'profiles' => $profiles
        ]);
    }
}
