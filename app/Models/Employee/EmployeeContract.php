<?php

namespace App\Models\Employee;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class EmployeeContract extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    protected $table = 'employee_contracts';

    protected $fillable = [
        'employee_id',
        'invitation_label',
        'job_title',
        'department',
        'employment_type',
        'compensation_type',
        'hourly_rate_cents',
        'monthly_gross_cents',
        'weekly_hours',
        'vacation_days_per_year',
        'vacation_days_taken',
        'start_date',
        'end_date',
        'is_fixed_term',
        'probation_months',
        'notice_period_text',
        'work_location',
        'working_hours_schedule',
        'birth_date',
        'birth_place',
        'nationality',
        'tax_id',
        'tax_class',
        'church_tax',
        'children_allowances',
        'social_security_number',
        'health_insurance_name',
        'health_insurance_type',
        'rv_exemption_requested',
        'has_other_employment',
        'account_holder',
        'iban',
        'bic',
        'bank_name',
        'signature_name',
        'signature_data',
        'signed_at',
        'signed_ip_hash',
        'contract_number',
        'contract_pdf_path',
        'questionnaire_pdf_path',
        'invitation_token_hash',
        'invitation_token_encrypted',
        'invitation_code_hash',
        'invitation_code_encrypted',
        'invitation_expires_at',
        'invitation_accepted_at',
        'is_active',
        'is_archived',
        'internal_notes',
    ];

    protected $hidden = [
        'tax_id',
        'social_security_number',
        'iban',
        'signature_data',
        'invitation_token_hash',
        'invitation_token_encrypted',
        'invitation_code_hash',
        'invitation_code_encrypted',
        'internal_notes',
    ];

    protected $casts = [
        'hourly_rate_cents' => 'integer',
        'monthly_gross_cents' => 'integer',
        'weekly_hours' => 'float',
        'vacation_days_per_year' => 'integer',
        'vacation_days_taken' => 'integer',
        'start_date' => 'date',
        'end_date' => 'date',
        'is_fixed_term' => 'boolean',
        'probation_months' => 'integer',
        'birth_date' => 'date',
        'tax_id' => 'encrypted',
        'church_tax' => 'boolean',
        'children_allowances' => 'float',
        'social_security_number' => 'encrypted',
        'rv_exemption_requested' => 'boolean',
        'has_other_employment' => 'boolean',
        'iban' => 'encrypted',
        'signed_at' => 'datetime',
        'invitation_token_encrypted' => 'encrypted',
        'invitation_code_encrypted' => 'encrypted',
        'invitation_expires_at' => 'datetime',
        'invitation_accepted_at' => 'datetime',
        'is_active' => 'boolean',
        'is_archived' => 'boolean',
    ];

    public function employee()
    {
        return $this->belongsTo(Employee::class, 'employee_id')->withTrashed();
    }

    public function timeEntries()
    {
        return $this->hasMany(EmployeeTimeEntry::class, 'contract_id');
    }

    public function payslips()
    {
        return $this->hasMany(EmployeePayslip::class, 'contract_id');
    }

    public function getInvitationUrlAttribute(): ?string
    {
        if ($this->invitation_accepted_at !== null || empty($this->invitation_token_encrypted)) {
            return null;
        }

        return route('employee.invitation', ['token' => $this->invitation_token_encrypted]);
    }

    public function getVerificationCodeAttribute(): ?string
    {
        if ($this->invitation_accepted_at !== null || empty($this->invitation_code_encrypted)) {
            return null;
        }

        return (string) $this->invitation_code_encrypted;
    }

    public function getIsPendingInvitationAttribute(): bool
    {
        return $this->invitation_accepted_at === null && empty($this->employee_id);
    }

    public function getEmploymentTypeLabelAttribute(): string
    {
        return match ($this->employment_type) {
            'minijob' => 'Minijob (Geringfügig bis 556 €)',
            'part_time' => 'Teilzeit (Sozialversicherungspflichtig)',
            'full_time' => 'Vollzeit (Sozialversicherungspflichtig)',
            'working_student' => 'Werkstudent/in',
            'short_term' => 'Kurzfristige Beschäftigung',
            default => 'Mitarbeiter/in',
        };
    }

    public function getRemainingVacationDaysAttribute(): int
    {
        return max(0, (int) $this->vacation_days_per_year - (int) $this->vacation_days_taken);
    }

    public function getMaskedIbanAttribute(): ?string
    {
        $raw = preg_replace('/\s+/', '', (string) ($this->iban ?? ''));
        if ($raw === '') {
            return null;
        }
        if (strlen($raw) <= 8) {
            return str_repeat('*', max(0, strlen($raw) - 2)) . substr($raw, -2);
        }

        return substr($raw, 0, 4) . ' •••• •••• •••• ' . substr($raw, -4);
    }

    public function getMaskedTaxIdAttribute(): ?string
    {
        $raw = preg_replace('/\s+/', '', (string) ($this->tax_id ?? ''));
        if ($raw === '') {
            return null;
        }
        if (strlen($raw) <= 4) {
            return $raw;
        }

        return substr($raw, 0, 2) . ' ••••••• ' . substr($raw, -2);
    }

    public function isReadyForPayroll(): bool
    {
        if (!$this->signed_at || empty(trim((string) $this->tax_id)) || empty(trim((string) $this->iban))) {
            return false;
        }

        return true;
    }

    /**
     * Erzeugt den offiziellen EPC-QR-Code-Datensatz (GiroCode Standard) für SEPA-Gehaltsüberweisungen.
     */
    public function buildEpcGiroCodePayload(int $amountCents, string $reference): ?string
    {
        $cleanIban = strtoupper(preg_replace('/\s+/', '', (string) ($this->iban ?? '')));
        $holder = trim((string) ($this->account_holder ?: ($this->employee ? $this->employee->first_name . ' ' . $this->employee->last_name : '')));

        if ($cleanIban === '' || $holder === '' || $amountCents <= 0) {
            return null;
        }

        $cleanBic = strtoupper(preg_replace('/\s+/', '', (string) ($this->bic ?? '')));
        $formattedEur = 'EUR' . number_format($amountCents / 100, 2, '.', '');
        $cleanHolder = mb_substr(preg_replace('/[\r\n]+/', ' ', $holder), 0, 70);
        $cleanRef = mb_substr(preg_replace('/[\r\n]+/', ' ', $reference), 0, 140);

        return implode("\n", [
            'BCD',
            '002',
            '1',
            'SCT',
            $cleanBic,
            $cleanHolder,
            $cleanIban,
            $formattedEur,
            '',
            '',
            $cleanRef,
        ]);
    }
}
