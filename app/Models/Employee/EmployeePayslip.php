<?php

namespace App\Models\Employee;

use App\Models\Accounting\AccountingSpecialIssue;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EmployeePayslip extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'employee_payslips';

    protected $fillable = [
        'contract_id',
        'employee_id',
        'payslip_number',
        'period_year',
        'period_month',
        'worked_minutes',
        'hourly_rate_cents_snapshot',
        'gross_amount_cents',
        'net_payout_cents',
        'employer_charges_cents',
        'total_employer_cost_cents',
        'tax_class_snapshot',
        'pdf_path',
        'timesheet_pdf_path',
        'uploaded_external_pdf_path',
        'accounting_special_issue_id',
        'status',
        'approved_at',
        'paid_at',
        'paid_by_admin_id',
        'notes',
        'recipient_snapshot',
    ];

    protected $casts = [
        'period_year' => 'integer',
        'period_month' => 'integer',
        'worked_minutes' => 'integer',
        'hourly_rate_cents_snapshot' => 'integer',
        'gross_amount_cents' => 'integer',
        'net_payout_cents' => 'integer',
        'employer_charges_cents' => 'integer',
        'total_employer_cost_cents' => 'integer',
        'approved_at' => 'datetime',
        'paid_at' => 'datetime',
        'recipient_snapshot' => 'array',
    ];

    public function contract()
    {
        return $this->belongsTo(EmployeeContract::class, 'contract_id')->withTrashed();
    }

    public function employee()
    {
        return $this->belongsTo(Employee::class, 'employee_id')->withTrashed();
    }

    public function accountingSpecialIssue()
    {
        return $this->belongsTo(AccountingSpecialIssue::class, 'accounting_special_issue_id');
    }

    public function getPeriodLabelAttribute(): string
    {
        $months = [
            1 => 'Januar', 2 => 'Februar', 3 => 'März', 4 => 'April',
            5 => 'Mai', 6 => 'Juni', 7 => 'Juli', 8 => 'August',
            9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Dezember',
        ];

        return ($months[$this->period_month] ?? $this->period_month) . ' ' . $this->period_year;
    }

    public function getEffectivePdfPathAttribute(): ?string
    {
        if (!empty($this->uploaded_external_pdf_path)) {
            return $this->uploaded_external_pdf_path;
        }

        return $this->pdf_path;
    }
}
