<?php

namespace App\Support\Blueprint\Enums;

enum AiAccessPolicy: string
{
    case None = 'none';
    case ReadOnly = 'read_only';
    case ReadAndPropose = 'read_and_propose';
    case AutonomousReadWrite = 'autonomous_read_write';

    public function label(): string
    {
        return match ($this) {
            self::None => 'Strikte KI-Sperre (Kein Zugriff durch Funki)',
            self::ReadOnly => 'Nur Lesen & Analysieren (Schreibgeschützt für KI)',
            self::ReadAndPropose => 'Lesen & Entwurf vorschlagen (Freigabe durch Admin)',
            self::AutonomousReadWrite => 'Autonomes Lesen & Schreiben durch KI-Agenten erlaubt',
        };
    }

    public function allowsAiRead(): bool
    {
        return $this !== self::None;
    }

    public function allowsAiWrite(): bool
    {
        return $this === self::AutonomousReadWrite;
    }

    public function allowsAiProposal(): bool
    {
        return in_array($this, [self::ReadAndPropose, self::AutonomousReadWrite], true);
    }
}
