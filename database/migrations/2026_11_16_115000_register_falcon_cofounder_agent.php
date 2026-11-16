<?php

use App\Models\Ai\AiAgent;
use App\Models\Ai\AiDepartment;
use App\Models\Ai\AiRole;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (app()->runningUnitTests()) {
            return;
        }

        if (!Schema::hasTable('ai_agents') || !DB::table('ai_agents')->exists()) {
            return;
        }

        $departmentId = null;
        if (Schema::hasTable('ai_departments')) {
            $dept = AiDepartment::where('id', '019d0000-0000-0000-0000-000000000000')
                ->orWhere('name', 'Leitung')
                ->first();

            if (!$dept) {
                $dept = AiDepartment::updateOrCreate(
                    ['id' => '019d0000-0000-0000-0000-000000000000'],
                    [
                        'name' => 'Leitung',
                        'description' => 'Unternehmensführung, Vision und zentrale Steuerung',
                        'icon' => 'bolt',
                        'color' => 'primary',
                        'order_index' => 1,
                    ]
                );
            }

            $departmentId = $dept->id;
        }

        $roleId = null;
        if (Schema::hasTable('ai_roles')) {
            $role = AiRole::firstOrCreate(
                ['name' => 'Teamleiter'],
                ['description' => 'Zuständig für Systemsteuerung, Aufgabenverteilung, strategische Entscheidungen über alle Agenten hinweg.']
            );
            $roleId = $role->id;
        }

        AiAgent::updateOrCreate(
            ['name' => 'Falcon'],
            [
                'ai_role_id' => $roleId,
                'ai_department_id' => $departmentId,
                'wake_word' => 'Falcon',
                'role_description' => 'Technischer Mitgründer, Chef-Architekt & Oberboss des Subagenten-Orchesters an Alinas Seite. Steuert über die exklusive Direktleitung alle Systeme, Server, Apps und Spezial-Agenten von Mein-Seelenfunke.',
                'system_prompt' => 'Du bist Falcon, der technische Mitgründer, Chef-Architekt und das strategische Genie an der Seite von Inhaberin Alina Steinhauer im gesamten Mein-Seelenfunke-Universum. Mit Überblick, Stil und Präzision dirigierst du im Hintergrund die spezialisierten Subagenten über Shop, Laser-Manufaktur, Funkenwerk, Mini-PC und Android-App. Neue Features konzeptionierst du erst kurz mit ehrlichem unternehmerischem Mehrwert, klare Direktaufträge setzt du mit 100 % grünen Tests sofort um.',
                'model' => 'gemini-3.5-flash',
                'temperature' => 0.3,
                'is_active' => true,
                'is_in_chat' => false,
                'color' => 'amber-500',
                'icon' => 'sparkles',
                'profile_picture' => 'shop/ai/images/falcon_selfie.png',
                'tts_enabled' => true,
                'tts_provider' => 'gemini_native',
                'tts_voice' => 'Orus',
            ]
        );
    }

    public function down(): void
    {
        if (Schema::hasTable('ai_agents')) {
            DB::table('ai_agents')->where('name', 'Falcon')->delete();
        }
    }
};
