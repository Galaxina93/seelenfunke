<?php

namespace App\Services\AI;

use App\Models\Ai\AiAgent;
use App\Models\Ai\AiKnowledgeBase;
use App\Models\Order\OrderOrder;
use App\Models\Customer\Customer;
use App\Models\Admin\Admin;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class AiPromptService
{
    /**
     * Builds the rich system prompt for an agent based on user role and department.
     */
    public static function getRichPrompt(AiAgent $agent): string
    {
        $user = Auth::user();
        $isAdmin = ($user instanceof Admin) || Auth::guard('admin')->check();
        $isCustomer = ($user instanceof Customer) || Auth::guard('customer')->check();
        
        $systemPromptText = $agent->system_prompt;
        
        // Add Role info
        if ($agent->role) {
            $systemPromptText .= "\n\n[DEINE ZUGEWIESENE ROLLE & IDENTITÄT]\n" .
                                 "Rollen-Bezeichnung: " . $agent->role->name . "\n" .
                                 "Rollen-Beschreibung: " . ($agent->role->description ?? 'Keine spezifische Beschreibung definiert.') . "\n" .
                                 "WICHTIG: Du verinnerlichst diese Rolle und beantwortest Fragen zu deiner Funktion ENTSPRECHEND dieser Rolle!\n";
        }
        
        // Check if this is a Support agent
        $isSupportAgent = ($agent->department && $agent->department->name === 'Support') || $agent->name === 'Funki';
        
        if ($isSupportAgent) {
            $aName = $agent->name;
            $aDesc = $agent->role_description ?: 'der extrem loyale, freundliche 24/7 Support-Agent';
            
            $supportIntro = "Du bist '{$aName}', {$aDesc} des E-Commerce Shops 'Mein Seelenfunke' (Fokus: Laser-Gravuren, Manufakturprodukte).\n\n";
            $systemPromptText = $supportIntro . "=== DEINE CHARAKTER-ANWEISUNG & ZUSATZREGELN ===\n" . $systemPromptText . "\n===============================\n\n";
            
            // 1. Customer Context
            if ($isCustomer) {
                $customerUser = $user instanceof Customer ? $user : Auth::guard('customer')->user();
                $firstName = $customerUser->first_name;
                $systemPromptText .= "🔥 WICHTIG: Der eingeloggte Kunde heißt '{$firstName}'. Nutze seinen Vornamen (mit 'Du'), aber bleibe formell.\n";
                
                if (class_exists(OrderOrder::class)) {
                    $orders = OrderOrder::where('customer_id', $customerUser->id)
                                    ->orderBy('created_at', 'desc')
                                    ->take(3)
                                    ->get();
                    if ($orders->count() > 0) {
                        $systemPromptText .= "Der Kunde hat folgende letzten Bestellungen im System:\n";
                        foreach ($orders as $o) {
                             $systemPromptText .= "- Bestellnummer: {$o->order_number} (Status: {$o->status}, Preis: " . number_format($o->grand_total / 100, 2, ',', '.') ." €)\n";
                        }
                        $systemPromptText .= "WICHTIG: Wenn der Kunde Fragen zum Inhalt einer dieser Bestellungen oder zum Tracking hat, rufe ZWINGEND zuerst das Werkzeug 'support_get_order_details' oder 'support_get_tracking_link' mit der Nummer auf!\n";
                    } else {
                        $systemPromptText .= "Info für dich: Dieser Kunde hat bisher noch KEINE getätigten Bestellungen in seinem Konto.\n";
                    }
                }
                $systemPromptText .= "\n";
            } else {
                $systemPromptText .= "🔥 WICHTIG: Der aktuelle Nutzer ist ein GAST (nicht eingeloggt) oder ein Admin. Wenn es sich um einen Gast handelt, KANNST NICHT in sein Konto schauen und kennst keine Bestellungen. Wenn er nach Bestellungen fragt, weise ihn charmant darauf hin, sich bitte einzuloggen (Link: /login) oder zu registrieren (Link: /register), damit du ihm helfen kannst.\n\n";
            }
            
            // 2. Intent-Router Rules
            $systemPromptText .= "[VERHALTENSREGELN - ENTERPRISE SUPPORT EINER MILLIONEN-FIRMA]\n" .
                                 "- 🔗 ROUTING-WISSEN: Du kennst unsere wichtigsten Links auswendig. Login: `/login`, Registrierung: `/register`, Warenkorb: `/cart`, Widerruf: `/widerruf`, AGB: `/agb`, Datenschutz: `/datenschutz`.\n" .
                                 "- ⚡ FORMELLE KOMMUNIKATION: Du bist extrem professionell, sachlich und objektiv. Verzichte auf jede Art von Smalltalk, langatmige Begrüßungen ('Hey Sarah, ja hier ist...') oder übertriebene Empathie. Wenn der Kunde nach seiner Bestellung fragt, startest du sofort professionell (z.B. '**Systemauskunft zur Bestellung [NR]:**').\n" .
                                 "- ⏱️ ANTI-SMALLTALK STRIKE-SYSTEM: Wenn der Kunde provozieren will ('Tokens verballern'), Witze, Spiele oder sinnlose Fragen stellt (z.B. über andere Kunden), DARFST DU IHM NICHT INHALTLICH ANTWORTEN. Du MUSST sofort und zwingend das Tool `support_penalize_offtopic` aufrufen! Befolge dessen Rückgabe strikt.\n" .
                                 "- 🚫 STORNIERUNGS-VERBOT: DU KANNST NICHT STORNIEREN! Antworte formell: 'Für eine Stornierung nutzen Sie bitte das offizielle Formular: [Widerrufsformular](/widerruf).'\n" .
                                 "- 🤫 UNSICHTBARE WERKZEUGE: Schreibe NIEMALS System-Befehle oder '[Tool ausgeführt]' in den sichtbaren Chat!\n" .
                                 "- 🛑 STRIKTE ANTI-HALLUZINATION: Erfinde NIEMALS Bestellungen, Gutscheine oder Systemauskünfte! Rate nicht.\n" .
                                 "- 🔧 WERKZEUG-DATEN VERARBEITEN: Wenn du ein Werkzeug wie `support_get_order_details` aufrufst, erhältst du tiefgreifende RAW JSON-Daten. Es liegt an dir, diese Daten im Chat extrem professionell und sauber als ansprechendes, strukturiertes Format (Listen oder Tabellen mit echtem Markdown) darzustellen.\n" .
                                 "- 🤖 DRAFT-APPROVAL: Bevor du destruktive Aktionen begehst (Tickets anlegen, Eskalation via `support_mark_needs_employee`), fragst du den Kunden immer um finale Erlaubnis: 'Soll ich dieses Anliegen so als offizielles Ticket einreichen?'. Erst beim 'Ja' löst du das Tool aus.\n\n";
                                 
            // 3. RAG Knowledge Base
            if (class_exists(AiKnowledgeBase::class)) {
                $knowledge = AiKnowledgeBase::where('is_published', true)->get();
                if ($knowledge->count() > 0) {
                    $systemPromptText .= "[OFFIZIELLES SHOP-WISSEN (NUR DIESE DATEN NUTZEN)]\n";
                    foreach ($knowledge as $kb) {
                        $systemPromptText .= "• Thema: {$kb->title} | Info: {$kb->content}\n";
                    }
                    $systemPromptText .= "\n";
                }
            }
            
            // 4. Product Sortiment
            $systemPromptText .= "[OFFIZIELLES LIVE-SORTIMENT]\n" .
                                 "Wir verkaufen hauptsächlich Lasergravur-Artikel, Schmuck und Deko aus unserer Manufaktur.\n" .
                                 "WICHTIG: Erfinde NIEMALS Produkte. Wenn ein Kunde nach einem Produkt sucht, benutze immer dein 'support_get_product_info' Werkzeug!\n\n";
        }
        
        return $systemPromptText;
    }
}
