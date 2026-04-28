<?php

namespace Database\Seeders;

use App\Models\Customer\Customer;
use App\Models\Product\Product;
use App\Models\Product\ProductReview;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class ProductReviewSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $products = Product::all();
        $customers = Customer::all();

        // Sicherheits-Check: Wir brauchen Produkte und Kunden, um Bewertungen zuzuordnen
        if ($products->isEmpty() || $customers->isEmpty()) {
            $this->command->warn('Achtung: Es müssen zuerst Produkte und Kunden in der Datenbank existieren, bevor Bewertungen generiert werden können!');
            return;
        }

        // Realistische deutsche Bewertungs-Bausteine
        $titles = [
            'Wunderschönes Geschenk!',
            'Einfach magisch ✨',
            'Tolle Qualität, schneller Versand',
            'Immer wieder gerne',
            'Perfekt für den Hochzeitstag',
            'Richtig massiv und schwer',
            'Absolutes Highlight',
            'Bin total begeistert',
            'Sehr edel und hochwertig',
            'Gute Arbeit, aber kleine Verzögerung',
            'Ein echter Hingucker auf dem Kamin',
            'Meine Frau hat geweint vor Freude',
            'Top Service von der Manufaktur',
            'Bin zufrieden',
            'Sehr liebevoll verpackt',
        ];

        $contents = [
            'Ich habe den Seelen-Kristall bestellt. Die Gravur ist unglaublich präzise und das Glas wirkt sehr massiv und hochwertig. Absolut empfehlenswert!',
            'Das Ergebnis hat meine Erwartungen übertroffen. Das Licht bricht sich toll in den Kanten. Der Support war auch super freundlich, als ich Fragen hatte.',
            'Das Produkt ist wirklich 1A und genau wie beschrieben. Leider hat der Paketdienst das Paket einen Tag zu spät geliefert, dafür kann die Manufaktur aber nichts.',
            'Vielen Dank für dieses tolle Unikat! Es war in einer sehr edlen Box verpackt und direkt bereit zum Verschenken. Macht richtig was her.',
            'Ich war erst skeptisch, ob das Foto im Glas wirklich gut aussieht. Aber die Details sind gestochen scharf. Wahnsinn!',
            'Sehr schwere Qualität. Fühlt sich nach echtem Premium-Produkt an. Werde hier definitiv noch einmal für Weihnachten bestellen.',
            'Die Kommunikation war einwandfrei. Man hat mir quasi jeden Sonderwunsch erfüllt. Das Ergebnis ist perfekt.',
            'Alles bestens. Schnelle Abwicklung, tolles Produkt. Kann ich nur weiterempfehlen.',
            'Ein Stern Abzug, weil ich die Geschenkbox beim Auspacken leicht zerkratzt habe. Das Produkt selbst ist aber wunderschön.',
            'Wirklich eine tolle Erinnerung. Haben unser Haustier gravieren lassen und es sieht aus, als würde es einen direkt ansehen. Gänsehaut pur.',
        ];

        // 20 Bewertungen generieren
        for ($i = 0; $i < 20; $i++) {
            $product = $products->random();
            $customer = $customers->random();

            // Zufällige Sterne (meistens gut)
            $rating = rand(1, 100) > 80 ? rand(3, 4) : 5;

            // Zufälliger Status (80% approved, 10% pending, 10% rejected)
            $statusChance = rand(1, 100);
            if ($statusChance <= 80) {
                $status = 'approved';
            } elseif ($statusChance <= 90) {
                $status = 'pending';
            } else {
                $status = 'rejected';
            }

            // Zufälliges Erstellungsdatum in den letzten 30 Tagen
            $createdAt = Carbon::now()->subDays(rand(0, 30))->subHours(rand(0, 23))->subMinutes(rand(0, 59));

            ProductReview::create([
                'id' => Str::uuid(),
                'product_id' => $product->id,
                'customer_id' => $customer->id,
                'rating' => $rating,
                'title' => $titles[array_rand($titles)],
                'content' => $contents[array_rand($contents)],
                'media' => [], // Leeres Array, da echte Dateien physisch auf dem Server liegen müssten
                'status' => $status,
                'created_at' => $createdAt,
                'updated_at' => $createdAt,
            ]);
        }

        $this->command->info('20 realistische Bewertungen wurden erfolgreich generiert! 🥂');
    }
}
