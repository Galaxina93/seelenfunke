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
        if (!\App\Services\AI\AiAuthHelper::isAdmin()) {
            return collect();
        }
        $adminId = \App\Services\AI\AiAuthHelper::getAdminId();
        return AiHealthMedication::where('user_id', $adminId)->orderBy('name', 'asc')->get();
    }

    #[Computed]
    public function doctors()
    {
        if (!\App\Services\AI\AiAuthHelper::isAdmin()) {
            return collect();
        }
        return ManagementContact::where(function ($q) {
                $q->where('relation_type', 'like', '%arzt%')
                  ->orWhere('relation_type', 'like', '%Praxis%');
            })
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
        if (!\App\Services\AI\AiAuthHelper::isAdmin()) {
            abort(403, 'Nur Administratoren dürfen Gesundheitsdaten bearbeiten.');
        }
        if ($id) {
            $adminId = \App\Services\AI\AiAuthHelper::getAdminId();
            $med = AiHealthMedication::where('user_id', $adminId)->find($id);
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
        if (!\App\Services\AI\AiAuthHelper::isAdmin()) {
            abort(403, 'Nur Administratoren dürfen Gesundheitsdaten speichern.');
        }

        $this->validate([
            'medicationForm.name' => 'required|string|max:255',
            'medicationForm.description' => 'nullable|string|max:2000',
            'medicationForm.active_ingredients' => 'nullable|string|max:500',
            'medicationForm.dosage' => 'nullable|string|max:255',
            'medicationForm.frequency' => 'nullable|string|max:255',
            'medicationForm.is_long_term' => 'nullable|boolean',
        ]);

        $adminId = \App\Services\AI\AiAuthHelper::getAdminId();
        $payload = [
            'name' => (string) ($this->medicationForm['name'] ?? ''),
            'description' => $this->medicationForm['description'] ?? null,
            'active_ingredients' => $this->medicationForm['active_ingredients'] ?? null,
            'dosage' => $this->medicationForm['dosage'] ?? null,
            'frequency' => $this->medicationForm['frequency'] ?? null,
            'is_long_term' => (bool) ($this->medicationForm['is_long_term'] ?? false),
            'user_id' => $adminId,
        ];

        if (!empty($this->medicationForm['id'])) {
            $med = AiHealthMedication::where('user_id', $adminId)->find($this->medicationForm['id']);
            if ($med) {
                $med->update($payload);
            }
        } else {
            AiHealthMedication::create($payload);
        }

        $this->showMedicationModal = false;
        unset($this->activeMedications); // clear cache
    }

    public function deleteMedication($id)
    {
        if (!\App\Services\AI\AiAuthHelper::isAdmin()) {
            abort(403, 'Nur Administratoren dürfen Gesundheitsdaten löschen.');
        }
        $adminId = \App\Services\AI\AiAuthHelper::getAdminId();
        AiHealthMedication::where('user_id', $adminId)->where('id', $id)->delete();
        unset($this->activeMedications); // clear cache
    }
}
