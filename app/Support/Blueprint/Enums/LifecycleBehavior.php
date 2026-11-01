<?php

namespace App\Support\Blueprint\Enums;

enum LifecycleBehavior: string
{
    case MasterData = 'master_data';
    case SeasonalPerpetual = 'seasonal_perpetual';
    case TransactionalWorkflow = 'transactional_workflow';
    case ImmutableAfterFinalized = 'immutable_after_finalized';
    case AppendOnlyLog = 'append_only_log';

    public function label(): string
    {
        return match ($this) {
            self::MasterData => 'Zeitlose Stammdaten (Master Data)',
            self::SeasonalPerpetual => 'Saisonale / Feiertags-Autopilot-Steuerung',
            self::TransactionalWorkflow => 'Transaktionaler Status-Workflow',
            self::ImmutableAfterFinalized => 'Nach Festschreibung unveränderlich (GoBD-Festschreibung)',
            self::AppendOnlyLog => 'Reines Ereignis- & Audit-Protokoll (Append-Only)',
        };
    }
}
