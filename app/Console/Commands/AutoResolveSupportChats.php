<?php

namespace App\Console\Commands;

use App\Models\Support\SupportCustomerChat;
use App\Services\System\SystemAlertTicketService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('support:auto-resolve-chats')]
#[Description('Schließt inaktive reine KI-Kundenchats nach 12 Stunden automatisch (eskalierte Mitarbeiter-Chats bleiben geschützt)')]
class AutoResolveSupportChats extends Command
{
    public function handle(): int
    {
        $cutoff = now()->subHours(12);

        // WICHTIG: Niemals 'needs_employee' automatisch schließen, da der Kunde explizit auf menschliche Rückmeldung wartet!
        $count = SupportCustomerChat::query()
            ->where('status', 'open')
            ->where('updated_at', '<', $cutoff)
            ->update(['status' => 'resolved_auto']);

        // Eskalierte Chats ('needs_employee'), die > 12h unbeantwortet warten, dublettenfrei als internes Ticket vormerken
        $staleEscalatedCount = SupportCustomerChat::query()
            ->where('status', 'needs_employee')
            ->where('updated_at', '<', $cutoff)
            ->count();

        if ($staleEscalatedCount > 0) {
            SystemAlertTicketService::upsertSystemTicket(
                alertKey: 'support:stale_escalated_chats',
                title: "Support-Hinweis: {$staleEscalatedCount} eskalierte Kunden-Chat(s) warten seit über 12 Std.",
                markdownBody: "Unter `/admin/support/chats` befinden sich aktuell **{$staleEscalatedCount} Kunden-Chat(s)** im Status `Mitarbeiter angefordert` (`needs_employee`), die seit mehr als 12 Stunden offen sind. Sie wurden bewusst **nicht** automatisch geschlossen.",
                departmentName: 'Support',
                agentName: 'Funkira',
                ticketType: 'support_escalation_reminder',
                priority: 'normal',
                assignToAntigravity: false
            );
        } else {
            SystemAlertTicketService::autoResolveByAlertPrefix(
                'support:stale_escalated_chats',
                'Alle eskalierten Kunden-Chats unter `/admin/support/chats` wurden bearbeitet.',
                'Funkira'
            );
        }

        $this->info("Auto-resolved {$count} inactive AI chats (escalated chats protected: {$staleEscalatedCount}).");

        return self::SUCCESS;
    }
}
