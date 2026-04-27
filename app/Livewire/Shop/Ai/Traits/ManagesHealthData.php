<?php

namespace App\Livewire\Shop\Ai\Traits;

use App\Models\Ai\AiHealthMedication;
use App\Models\Management\ManagementContact;
use Livewire\Attributes\Computed;

trait ManagesHealthData
{
    // Medication Form state
    public $showMedicationModal = false;
    public $medicationForm = [
        'id' => null,
        'name' => '',
        'description' => '',
        'active_ingredients' => '',
        'dosage' => '',
        'frequency' => '',
        'is_long_term' => false,
    ];

    #[Computed]
    public function activeMedications()
    {
        return AiHealthMedication::orderBy('name', 'asc')->get();
    }

    #[Computed]
    public function doctors()
    {
        return ManagementContact::where('relation_type', 'like', '%arzt%')
            ->orWhere('relation_type', 'like', '%Praxis%')
            ->orderBy('is_favorite', 'desc')
            ->orderBy('last_name', 'asc')
            ->get();
    }

    public function loadHealthData()
    {
        // No-op now, using Computed properties instead.
    }

    public function editMedication($id = null)
    {
        if ($id) {
            $med = AiHealthMedication::find($id);
            if ($med) {
                $this->medicationForm = $med->toArray();
            }
        } else {
            $this->medicationForm = [
                'id' => null,
                'name' => '',
                'description' => '',
                'active_ingredients' => '',
                'dosage' => '',
                'frequency' => '',
                'is_long_term' => false,
            ];
        }
        $this->showMedicationModal = true;
    }

    public function saveMedication()
    {
        $this->validate([
            'medicationForm.name' => 'required|string|max:255',
            'medicationForm.dosage' => 'nullable|string|max:255',
            'medicationForm.frequency' => 'nullable|string|max:255',
        ]);

        $data = $this->medicationForm;
        $data['user_id'] = auth()->id() ?? \App\Models\System\SystemUser::first()->id;

        if ($data['id']) {
            AiHealthMedication::find($data['id'])->update($data);
        } else {
            AiHealthMedication::create($data);
        }

        $this->showMedicationModal = false;
        unset($this->activeMedications); // clear cache
    }

    public function deleteMedication($id)
    {
        AiHealthMedication::destroy($id);
        unset($this->activeMedications); // clear cache
    }
}
