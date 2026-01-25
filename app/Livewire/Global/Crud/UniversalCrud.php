<?php

namespace App\Livewire\Global\Crud;

use Illuminate\Support\Facades\Hash;
use Livewire\Component;

class UniversalCrud extends Component
{
    public $model;
    protected $data;
    public array $record = [];

    // Der aktuelle Context (Bereich index, create, edit)
    public string $context;

    public string $modelName;

    public string $search = '';
    public string $primaryKey = 'id';
    public string $sortField = 'id';

    public bool $showEdit = false;
    public bool $showCreate = false;
    public bool $showArchive = false;
    public bool $confirmingDelete = false;
    public bool $confirmingForceDelete = false;

    public string $itemToDelete;
    public string $itemToForceDelete;

    protected $listeners = ['refreshData' => '$refresh'];

    public $config;

    /* Rendering */
    public function mount(string $configClass): void
    {
        $this->config = $configClass::getConfig();
        $this->model = "App\\Models\\" . $this->config['model'];
        $this->modelName = $this->config['model'];
    }
    public function render()
    {
        $modelInstance = new $this->model;

        $relationFields = $this->getRelationFields();

        if ($this->showArchive) {
            $query = $modelInstance::query()->with(array_merge($this->config['with'] ?? [], $relationFields))->onlyTrashed();
        } else {
            $query = $modelInstance::query()->with(array_merge($this->config['with'] ?? [], $relationFields));
        }

        $query = $this->applySortingAndFiltering($query);

        $this->data = $query->paginate($this->config['per_page']);

        return view('livewire.crud.main.universal-crud');
    }

    /* CRUD */
    public function saveOrUpdate(): void
    {
        try {
            $validationRules = $this->getRules();

            if (!empty($validationRules)) {
                $validatedData = $this->validate($validationRules);
                $recordData = $validatedData['record'];
            } else {
                $recordData = $this->record;
            }

            $relatedData = $this->prepareRelatedData($recordData);
            $this->handlePasswordHashing($recordData);

            if (isset($this->record[$this->primaryKey])) {
                $record = $this->model::findOrFail($this->record[$this->primaryKey]);
                $record->update($recordData);
                session()->flash('successUpdate', 'Datensatz wurde erfolgreich aktualisiert.');
            } else {
                $record = $this->model::create($recordData);
                session()->flash('successCreate', 'Datensatz wurde erfolgreich erstellt.');
            }

            $this->saveRelations($record, $relatedData);
            $this->refreshData();

        } catch (\Illuminate\Validation\ValidationException $e) {
            $errors = $e->validator->errors()->messages();
            $errorMessages = '';

            foreach ($errors as $field => $error) {
                $translatedError = __($error[0], ['attribute' => trans("validation.attributes.$field")]);
                $this->addError($field, $translatedError);
                $errorMessages .= $translatedError . ' ';
            }

            $this->addError('saveError', 'Die Validierung ist fehlgeschlagen: ' . $errorMessages);
        }
    }
    public function edit($id): void
    {
        $relationFields = $this->getRelationFields();

        // Laden der Relationen und des Datensatzes
        $this->record = $this->model::with($relationFields)->findOrFail($id)->toArray();

        $this->showEdit = true;
    }
    public function delete(): void
    {
        $item = $this->model::findOrFail($this->itemToDelete);
        $item->delete();

        $this->confirmingDelete = false;
        $this->itemToDelete = '';
        $this->refreshData();
    }
    public function forceDelete(): void
    {
        $item = $this->model::findOrFail($this->itemToForceDelete);
        $item->forceDelete();

        $this->confirmingForceDelete = false;
        $this->itemToForceDelete = '';
        $this->refreshData();
    }
    public function restoreItem($id): void
    {
        $modelInstance = new $this->model;

        // Modell mit SoftDeletes laden
        $item = $modelInstance->withTrashed()->findOrFail($id);

        // Restore Hauptmodell
        $item->restore();

        // Falls das Modell ein Profil hat, auch das wiederherstellen
        if (method_exists($item, 'profile')) {
            $item->load(['profile' => function ($query) {
                $query->withTrashed();
            }]);

            if ($item->profile) {
                $item->profile->restore();
            }
        }
    }

    /* Felder laden */
    public function getModelFields(string $context = 'index'): array
    {
        $this->context = $context;

        if ($this->config && isset($this->config['fields'])) {
            $fields = [];

            foreach ($this->config['fields'] as $key => $field) {
                if (
                    ($context === 'index' && (!isset($field['hide_on_index']) || !$field['hide_on_index'])) ||
                    ($context === 'edit' && (!isset($field['hide_on_update']) || !$field['hide_on_update'])) ||
                    ($context === 'create' && (!isset($field['hide_on_create']) || !$field['hide_on_create']))
                ) {
                    $fields[] = $key;
                }
            }

            return $fields;
        }

        $modelInstance = new $this->model;
        return $modelInstance->getFillable();
    }

    /* Validierung laden - Gibt die Felder mit ihrer jeweiligen Validierungsregel zurück */
    public function getRules(): array
    {
        if (!isset($this->config['fields']) || empty($this->config['fields'])) {
            return [];
        }

        $rules = [];
        $modelInstance = new $this->model;

        foreach ($this->config['fields'] as $column => $fieldConfig) {

            // Überspringen, wenn validierung explizit deaktiviert ist
            if (!empty($fieldConfig['ignore_validate']) || !empty($fieldConfig['ignore'])) {
                continue;
            }

            // Kein rules-Eintrag -> nichts machen (kein "required" erzwingen)
            if (!array_key_exists('rules', $fieldConfig)) {
                continue;
            }

            $fieldName = "record.{$column}";

            // Relationen behandeln
            if (isset($fieldConfig['relation'])) {
                $fieldName = "record.{$fieldConfig['relation']}.{$column}";
            }

            $ruleString = trim($fieldConfig['rules'] ?? '');

            // E-Mail Unique-Anpassung (falls im Basismodell)
            if ($column === 'email') {
                $relatedTable = isset($fieldConfig['relation'])
                    ? $modelInstance->{$fieldConfig['relation']}()->getRelated()->getTable()
                    : $modelInstance->getTable();

                // Nur anhängen, wenn "unique" nicht schon vollständig spezifiziert wurde
                if (str_starts_with($ruleString, 'unique:') === false && str_contains($ruleString, 'unique') === false) {
                    $ruleString .= '|unique:' . $relatedTable . ',email,' . ($this->record['id'] ?? 'NULL') . ',id';
                } elseif (str_contains($ruleString, 'unique') && !str_contains($ruleString, $relatedTable)) {
                    // Falls nur "unique" drinstand, ersetzen wir es mit vollständiger Angabe
                    $ruleString = preg_replace('/unique\b/', 'unique:' . $relatedTable . ',email,' . ($this->record['id'] ?? 'NULL') . ',id', $ruleString);
                }
            }

            $rules[$fieldName] = $ruleString;
        }

        return $rules;
    }

    /* Sichtbarkeit */
    public function toggleVisibility(string $element, bool $visibility): void
    {
        switch ($element) {
            case 'edit':
                $this->showEdit = $visibility;
                break;
            case 'archive':
                $this->showArchive = $visibility;
                if ($visibility) {
                    $this->render();
                }
                break;
            case 'create':
                if ($visibility) {
                    $this->record = [];
                }
                $this->showCreate = $visibility;
                break;
        }
    }

    /* Sortierung/Filterung */
    public function sortBy($field): void
    {
        if (in_array($field, $this->config['sortable'])) {
            if ($this->sortField === $field) {
                $this->config['sort_direction'] = $this->config['sort_direction'] === 'asc' ? 'desc' : 'asc';
            } else {
                $this->config['sort_direction'] = 'asc';
            }
            $this->sortField = $field;
        }
    }
    protected function applySortingAndFiltering($query)
    {
        if ($this->config && isset($this->config['searchable'])) {
            $searchFields = $this->config['searchable'];
        } else {
            $searchFields = (new $this->model)->getFillable();
        }

        if ($this->search) {
            $query->where(function ($query) use ($searchFields) {
                foreach ($searchFields as $field) {
                    $query->orWhere($field, 'LIKE', '%' . $this->search . '%');
                }
            });
        }

        if ($this->sortField) {
            $query->orderBy($this->sortField, $this->config['sort_direction']);
        }

        return $query;
    }

    /* Relations */
    public function getRelationFields(): array
    {
        $relations = [];
        foreach ($this->config['fields'] as $field => $fieldConfig) {
            if (isset($fieldConfig['relation'])) {
                $relations[] = $fieldConfig['relation'];
            }
        }

        // Entfernen von doppelten Relationen
        return array_unique($relations);
    }
    private function prepareRelatedData(&$recordData): array
    {
        $relatedData = [];
        foreach ($this->config['fields'] as $attribute => $fieldConfig) {
            if (isset($fieldConfig['relation'])) {
                $relatedData[$fieldConfig['relation']][$attribute] = $recordData[$fieldConfig['relation']][$attribute] ?? null;
                unset($recordData[$attribute]);
            }
        }

        return $relatedData;
    }
    private function saveRelations($record, $relatedData): void
    {
        foreach ($relatedData as $relation => $data) {

            if (method_exists($record, $relation)) {
                $relationInstance = $record->$relation;

                if ($relationInstance) {
                    $relationInstance->update($data);
                } else {
                    $relatedModel = $record->$relation()->getRelated();
                    $record->$relation()->save($relatedModel->create($data));
                }
            }
        }
    }

    /* Helfer */
    public function refreshData(): void
    {
        $this->data = $this->applySortingAndFiltering($this->model::query())->paginate($this->config['per_page']);
    }
    public function hasPermission(string $action): bool
    {
        $permissionKey = $this->config['permissions'][$action] ?? null;

        if (allowed($permissionKey)) {
            return true;
        }
        return false;
    }
    public function confirmDelete($id): void
    {
        $this->confirmingDelete = true;
        $this->itemToDelete = $id;
    }
    public function confirmForceDelete($id): void
    {
        $this->confirmingForceDelete = true;
        $this->itemToForceDelete = $id;
    }
    protected function handlePasswordHashing(array &$recordData): void
    {
        foreach ($this->getModelFields('create') as $attribute) {
            if ($attribute == 'password') {
                if (isset($this->record['id'])) {
                    // Hole das aktuelle Passwort des Models aus der Datenbank
                    $currentModel = $this->model::findOrFail($this->record['id']);

                    if ($currentModel->password != $recordData[$attribute]) {
                        $recordData[$attribute] = Hash::make($recordData[$attribute]);
                    }
                } else {
                    $recordData[$attribute] = Hash::make($recordData[$attribute]);
                }
            }
        }
    }

}
