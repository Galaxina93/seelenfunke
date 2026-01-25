

## Universal Crud

1. Definiere extra Funktionen (closure_functions) die anstelle des eigentlichen Feldes treten.
    Diese extra Funktionen kannst du einfach bei deinem neuen CRUD in der Config Datei definieren.

'closure_function'  => 'crud\AdminProfileConfig@getNameByAdminId'

Hier ein Beispiel:

    'admin_id' => [
        'hide_on_index'     => false,
        'hide_on_update'    => false,
        'hide_on_create'    => false,
        'required'          => false,
        'type'              => 'text',
        'translation'       => 'Admin (id)',
        'placeholder'       => 'Admin ID',
        'rules'             => '',
        'class'             => 'col-span-full md:col-span-3',
        'closure_function'  => 'crud\AdminProfileConfig@getNameByAdminId' <-------
    ],

public static function getNameByAdminId($row)
{
    return $row->admin->first_name;
}

2. Bei dem Feldtypen "range", müssen (min_range und max_range) mit im Feld definiert werden
3. Falls im with array eine Beziehung angegeben wurde (z.B. profile), so muss man im jeweiligen Beziehungsfeld ('relation' => 'BEZIEHUNGSFUNKTION') hinzufügen

4. Einfügen einer Trennlinie oder jedem erdenklichen Code. Im Pfad (resources/views/components/forms/crud) findet man den Divider.

Beispiel:
    
         'divider_1' => [
            'hide_on_index'     => true,
            'hide_on_create'    => true,
            'translation'       => 'Adresse',
            'type'              => 'divider_1',
            'class'             => 'col-span-full font-bold py-4'
        ],


5. Einfügen eines Dropdowns (Selection mit Options)

Beispiel:

in den neu erstellten Crud (z.B. AdminCrud) fügt man eine Methode hinzu, die das passende Array für die Selection erstellt.
Dieses Array wird dann über die Mount Methode geladen.

In Crud (Nicht in der CrudConfig!):
        
    class AdminCrud extends UniversalCrud
        {
            public function mount(string $configClass = null): void
                {
                    parent::mount('crud\\AdminConfig');
                    $this->config['fields']['profile']['options'] = $this->getProfileOptions();
            
                }
            
        protected function getProfileOptions(): array
        {
            $profiles = AdminProfile::all();
            $profileOptions = [];
    
            foreach ($profiles as $profile) {
                $profileOptions[$profile->id] = 'Admin ID: ' . $profile->admin_id;
            }
    
            return $profileOptions;
        }

    }
