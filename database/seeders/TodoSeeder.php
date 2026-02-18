<?php

namespace Database\Seeders;

use App\Models\Todo;
use App\Models\TodoList;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class TodoSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Liste erstellen (Gewerblich/Projekt)
        $mainList = TodoList::create([
            'id' => (string) Str::uuid(),
            'name' => 'Mein-Seelenfunke',
            'icon' => 'rocket-launch',
            'color' => '#C5A059'
        ]);

        // 2. Aufgaben und deren Schritte (gemäß Screenshot)

        // --- PRODUCTKONFIGURATOR ---
        $configurator = Todo::create([
            'id' => (string) Str::uuid(),
            'todo_list_id' => $mainList->id,
            'title' => 'PRODUCTKONFIGURATOR',
            'is_completed' => false,
        ]);

        Todo::create(['id' => (string) Str::uuid(), 'todo_list_id' => $mainList->id, 'parent_id' => $configurator->id, 'title' => 'Vorgefertigte Icons zur Auswahl hinzufügen', 'is_completed' => false]);
        Todo::create(['id' => (string) Str::uuid(), 'todo_list_id' => $mainList->id, 'parent_id' => $configurator->id, 'title' => 'Three js 3D Ansicht bauen', 'is_completed' => false]);
        Todo::create(['id' => (string) Str::uuid(), 'todo_list_id' => $mainList->id, 'parent_id' => $configurator->id, 'title' => 'Hochgeladene Bilder in laser Kontrast umwandeln', 'is_completed' => false]);
        Todo::create(['id' => (string) Str::uuid(), 'todo_list_id' => $mainList->id, 'parent_id' => $configurator->id, 'title' => 'Mobil gehen die Buttons nicht die an den Ecken', 'is_completed' => false]);

        // --- STEUER - ABSCHLUSS ---
        Todo::create([
            'id' => (string) Str::uuid(),
            'todo_list_id' => $mainList->id,
            'title' => 'STEUER - ABSCHLUSS',
            'is_completed' => false,
        ]);

        // --- HOSTING EINRICHTEN ---
        Todo::create([
            'id' => (string) Str::uuid(),
            'todo_list_id' => $mainList->id,
            'title' => 'HOSTING EINRICHTEN',
            'is_completed' => false,
        ]);

        // --- LOYALITÄT UND PUNKTESYSTEM ---
        Todo::create([
            'id' => (string) Str::uuid(),
            'todo_list_id' => $mainList->id,
            'title' => 'Loyalität und Punktesystem integrieren',
            'is_completed' => false,
        ]);

        // --- DASHBOARD SHOP ---
        Todo::create([
            'id' => (string) Str::uuid(),
            'todo_list_id' => $mainList->id,
            'title' => 'DASHBOARD SHOP',
            'is_completed' => false, // Laut Screenshot 10 von 11 erledigt, hier als offen markiert
        ]);

        // --- WEBSEITE & SHOP ---
        Todo::create([
            'id' => (string) Str::uuid(),
            'todo_list_id' => $mainList->id,
            'title' => 'WEBSEITE & SHOP',
            'is_completed' => false,
        ]);

        // --- ABLAUF BIS MITTE MÄRZ ---
        Todo::create([
            'id' => (string) Str::uuid(),
            'todo_list_id' => $mainList->id,
            'title' => 'ABLAUF BIS MITTE MÄRZ',
            'is_completed' => false,
        ]);

        // --- AB GEWERBESTART 01.04.2026 ---
        Todo::create([
            'id' => (string) Str::uuid(),
            'todo_list_id' => $mainList->id,
            'title' => 'AB GEWERBESTART 01.04.2026',
            'is_completed' => false,
        ]);

        // --- VERPACKUNG ---
        Todo::create([
            'id' => (string) Str::uuid(),
            'todo_list_id' => $mainList->id,
            'title' => 'VERPACKUNG',
            'is_completed' => false,
        ]);

        // --- VERSAND ---
        Todo::create([
            'id' => (string) Str::uuid(),
            'todo_list_id' => $mainList->id,
            'title' => 'VERSAND',
            'is_completed' => false,
        ]);

        // --- MARKETING ---
        Todo::create([
            'id' => (string) Str::uuid(),
            'todo_list_id' => $mainList->id,
            'title' => 'MARKETING',
            'is_completed' => false,
        ]);

        // --- EINZELAUFGABE (World Pay) ---
        Todo::create([
            'id' => (string) Str::uuid(),
            'todo_list_id' => $mainList->id,
            'title' => 'World Pay HP ink Abzug auf Gewerbe Konto FINOM übertragen',
            'is_completed' => false,
        ]);
    }
}
