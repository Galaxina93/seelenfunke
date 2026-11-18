<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('products')) {
            return;
        }

        // 1. Echtsilber-Armband: sauberer Hauptname ohne Klammerzusatz, einheitlich zusammengeschrieben
        DB::table('products')
            ->where('slug', 'id-armband-silber-herz')
            ->update([
                'name' => 'Das Echtsilber Seelenarmband',
                'description' => 'Ein zeitloses Schmuckstück aus echtem Edelmetall, auf Bestellung für Sie handveredelt in unserer Manufaktur in Gifhorn: Das Echtsilber Seelenarmband stammt aus der deutschen Traditionsmanufaktur Friedrich Binder (FBM, Mönsheim, gefertigt in Deutschland seit 1910) und besteht aus massivem 925er Sterlingsilber (gestempelt 925, ca. 2,50 g). Das 2,1 mm breite, fein diamantierte Figaro-Kettengeflecht trägt eine hochglanzpolierte Gravurplatte (24 x 5,5 mm) mit einem filigran ausgestanzten Herz-Durchbruch auf der linken Seite. Mit unserem 5W UV-Präzisionslaser veredeln wir die polierte Gravurfläche berührungslos und materialschonend mit Ihrem Wunschnamen, einem besonderen Datum oder einer liebevollen Botschaft, auf Wunsch kostenfrei beidseitig auf Vorder- und Rückseite. Dank des sicheren Federringverschlusses und der offenen Figaro-Glieder lässt sich die Damen-Standardlänge von 18,5 cm flexibel am Handgelenk tragen.',
                'short_description' => 'Massives 925er Sterlingsilber Seelenarmband (Figaro diamantiert 2,1 mm, 18,5 cm, ca. 2,50 g) mit ausgestanztem Herz-Durchbruch und beidseitiger 5W UV-Lasergravur.',
                'seo_title' => 'Das Echtsilber Seelenarmband (925er Sterlingsilber) | Mein-Seelenfunke',
                'seo_description' => 'Massives 925er Sterlingsilber Seelenarmband (Figaro diamantiert 2,1 mm, 18,5 cm) mit Herz-Durchbruch und individueller, beidseitiger Lasergravur aus unserer Manufaktur.',
                'updated_at' => now(),
            ]);

        // 2. Goldenes Armband (Option A): klares, abmahnsicheres Deutsch ohne „Gold-Vermeil“ oder „Echtgold“
        $goldBracelet = DB::table('products')->where('slug', 'id-armband-gold-herz')->first();
        if ($goldBracelet) {
            $updatePayload = [
                'name' => 'Das Goldene Seelenarmband',
                'description' => 'Edler Goldglanz auf echtem Edelmetall, auf Bestellung für Sie handveredelt in unserer Manufaktur in Gifhorn: Das Goldene Seelenarmband wird in der deutschen Traditionsmanufaktur Friedrich Binder (FBM, Mönsheim, gefertigt in Deutschland seit 1910) aus massivem 925er Sterlingsilber mit echter Gelbgold-Vergoldung (gestempelt 925, ca. 2,50 g) gefertigt. Das 2,1 mm breite, fein diamantierte Figaro-Kettengeflecht trägt eine spiegelpolierte Gravurplatte (24 x 5,5 mm) mit einem ausgestanzten Herz-Durchbruch auf der linken Seite. Mit unserem 5W UV-Präzisionslaser gravieren wir Ihren Wunschnamen, Initialen oder ein besonderes Datum gestochen scharf rechts neben das Herz, auf Wunsch kostenfrei beidseitig auf Vorder- und Rückseite. Die Damen-Standardlänge von 18,5 cm mit Federringverschluss schmiegt sich elegant an jedes Handgelenk.',
                'short_description' => 'Das Goldene Seelenarmband aus massivem 925er Sterlingsilber mit echter Gelbgold-Vergoldung (gestempelt 925, Figaro diamantiert 2,1 mm, 18,5 cm, ca. 2,50 g), ausgestanztem Herz-Durchbruch und beidseitiger 5W UV-Lasergravur.',
                'seo_title' => 'Das Goldene Seelenarmband (925er Silber vergoldet) | Mein-Seelenfunke',
                'seo_description' => 'Das Goldene Seelenarmband aus massivem 925er Sterlingsilber mit echter Gelbgold-Vergoldung (gestempelt 925, Figaro 2,1 mm, 18,5 cm) mit Herz-Durchbruch und beidseitiger Lasergravur.',
                'updated_at' => now(),
            ];

            $attrs = is_string($goldBracelet->attributes ?? null)
                ? json_decode($goldBracelet->attributes, true)
                : null;
            if (is_array($attrs) && isset($attrs['Edelmetall & Legierung'])) {
                $attrs['Edelmetall & Legierung'] = 'Massives 925er Sterlingsilber mit echter Gelbgold-Vergoldung (925 Gelb vergoldet, gestempelt 925)';
                $updatePayload['attributes'] = json_encode($attrs, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            }

            DB::table('products')
                ->where('slug', 'id-armband-gold-herz')
                ->update($updatePayload);
        }
    }

    public function down(): void
    {
        // Nicht-destruktives Text-Update; kein Rollback erforderlich.
    }
};
