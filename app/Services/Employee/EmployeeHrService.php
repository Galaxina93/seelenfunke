<?php

namespace App\Services\Employee;

use App\Models\Accounting\AccountingCategory;
use App\Models\Accounting\AccountingSpecialIssue;
use App\Models\Admin\Admin;
use App\Models\Employee\Employee;
use App\Models\Employee\EmployeeContract;
use App\Models\Employee\EmployeePayslip;
use App\Models\Employee\EmployeeTimeEntry;
use App\Models\System\SystemLog;
use App\Models\System\SystemUser;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class EmployeeHrService
{
    /**
     * Gesetzliche Geringfügigkeitsgrenze für Minijobs (§ 8 Abs. 1 Nr. 1 SGB IV): 556,00 € / Monat (55600 Cents).
     */
    public const MINIJOB_LIMIT_CENTS = 55600;

    /**
     * Stellt sicher, dass die HR-Tabellen in bereits bestehenden Entwicklungs-Datenbanken existieren
     * (konsolidiert mit database/migrations/2026_03_28_000000_create_management_tables.php).
     */
    public function ensureTablesExist(): void
    {
        if (\Illuminate\Support\Facades\Schema::hasTable('employee_contracts') && \Illuminate\Support\Facades\Schema::hasTable('employee_payslips')) {
            return;
        }

        if (!\Illuminate\Support\Facades\Schema::hasTable('employee_contracts')) {
            \Illuminate\Support\Facades\Schema::create('employee_contracts', function (\Illuminate\Database\Schema\Blueprint $table) {
                $table->uuid('id')->primary();
                $table->uuid('employee_id')->nullable()->index();
                $table->string('invitation_label', 191)->nullable();
                $table->string('job_title', 150)->default('Manufaktur- & Versand-Assistent/in');
                $table->string('department', 100)->default('Produktion & Versand');
                $table->string('employment_type', 40)->default('minijob')->index();
                $table->string('compensation_type', 30)->default('hourly');
                $table->unsignedInteger('hourly_rate_cents')->default(1400);
                $table->unsignedInteger('monthly_gross_cents')->default(54000);
                $table->decimal('weekly_hours', 5, 2)->default(9.00);
                $table->unsignedInteger('vacation_days_per_year')->default(20);
                $table->unsignedInteger('vacation_days_taken')->default(0);
                $table->date('start_date')->nullable();
                $table->date('end_date')->nullable();
                $table->boolean('is_fixed_term')->default(false);
                $table->unsignedInteger('probation_months')->default(3);
                $table->string('notice_period_text', 191)->default('4 Wochen zum 15. oder zum Ende eines Kalendermonats (§ 622 BGB)');
                $table->string('work_location', 191)->default('Manufaktur Gifhorn / Mobiles Arbeiten nach Absprache');
                $table->string('working_hours_schedule', 255)->nullable();
                $table->date('birth_date')->nullable();
                $table->string('birth_place', 120)->nullable();
                $table->string('nationality', 80)->default('Deutsch');
                $table->text('tax_id')->nullable();
                $table->string('tax_class', 20)->default('I');
                $table->boolean('church_tax')->default(false);
                $table->decimal('children_allowances', 3, 1)->default(0.0);
                $table->text('social_security_number')->nullable();
                $table->string('health_insurance_name', 150)->nullable();
                $table->string('health_insurance_type', 30)->default('statutory');
                $table->boolean('rv_exemption_requested')->default(false);
                $table->boolean('has_other_employment')->default(false);
                $table->string('account_holder', 191)->nullable();
                $table->text('iban')->nullable();
                $table->string('bic', 30)->nullable();
                $table->string('bank_name', 150)->nullable();
                $table->string('signature_name', 191)->nullable();
                $table->longText('signature_data')->nullable();
                $table->timestamp('signed_at')->nullable();
                $table->string('signed_ip_hash', 64)->nullable();
                $table->string('contract_number', 60)->nullable()->unique();
                $table->string('contract_pdf_path', 500)->nullable();
                $table->string('questionnaire_pdf_path', 500)->nullable();
                $table->string('invitation_token_hash', 64)->nullable()->unique();
                $table->text('invitation_token_encrypted')->nullable();
                $table->string('invitation_code_hash', 64)->nullable();
                $table->text('invitation_code_encrypted')->nullable();
                $table->timestamp('invitation_expires_at')->nullable();
                $table->timestamp('invitation_accepted_at')->nullable();
                $table->boolean('is_active')->default(true)->index();
                $table->boolean('is_archived')->default(false)->index();
                $table->text('internal_notes')->nullable();
                $table->timestamps();
                $table->softDeletes();
            });
        }

        if (!\Illuminate\Support\Facades\Schema::hasTable('employee_time_entries')) {
            \Illuminate\Support\Facades\Schema::create('employee_time_entries', function (\Illuminate\Database\Schema\Blueprint $table) {
                $table->uuid('id')->primary();
                $table->uuid('contract_id')->index();
                $table->uuid('employee_id')->nullable()->index();
                $table->date('work_date')->index();
                $table->timestamp('started_at')->nullable();
                $table->timestamp('ended_at')->nullable();
                $table->unsignedInteger('break_minutes')->default(0);
                $table->unsignedInteger('net_minutes')->default(0);
                $table->string('entry_type', 30)->default('work');
                $table->string('activity_note', 255)->nullable();
                $table->string('status', 30)->default('recorded')->index();
                $table->boolean('arbzg_compliant')->default(true);
                $table->timestamps();
            });
        }

        if (!\Illuminate\Support\Facades\Schema::hasTable('employee_payslips')) {
            \Illuminate\Support\Facades\Schema::create('employee_payslips', function (\Illuminate\Database\Schema\Blueprint $table) {
                $table->uuid('id')->primary();
                $table->uuid('contract_id')->index();
                $table->uuid('employee_id')->nullable()->index();
                $table->string('payslip_number', 60)->unique();
                $table->unsignedSmallInteger('period_year')->index();
                $table->unsignedTinyInteger('period_month')->index();
                $table->unsignedInteger('worked_minutes')->default(0);
                $table->unsignedInteger('hourly_rate_cents_snapshot')->default(0);
                $table->unsignedInteger('gross_amount_cents')->default(0);
                $table->unsignedInteger('net_payout_cents')->default(0);
                $table->unsignedInteger('employer_charges_cents')->default(0);
                $table->unsignedInteger('total_employer_cost_cents')->default(0);
                $table->string('tax_class_snapshot', 20)->nullable();
                $table->string('pdf_path', 500)->nullable();
                $table->string('timesheet_pdf_path', 500)->nullable();
                $table->string('uploaded_external_pdf_path', 500)->nullable();
                $table->uuid('accounting_special_issue_id')->nullable()->index();
                $table->string('status', 30)->default('open')->index();
                $table->timestamp('approved_at')->nullable();
                $table->timestamp('paid_at')->nullable();
                $table->uuid('paid_by_admin_id')->nullable();
                $table->string('notes', 255)->nullable();
                $table->json('recipient_snapshot')->nullable();
                $table->timestamps();
            });
        }
    }

    /**
     * Erzeugt einen neuen Mitarbeiter-Einladungs-Slot inklusive Einladungslink (/mitarbeiter/einladung/{token})
     * und 6-stelligem Sicherheits-Verifizierungscode für das digitale Onboarding.
     */
    public function createInvitationSlot(array $options = []): EmployeeContract
    {
        $rawToken = Str::random(48);
        $rawCode = str_pad((string) random_int(100000, 999999), 6, '0', STR_PAD_LEFT);

        $rawEmpType = (string) ($options['employment_type'] ?? 'minijob');
        $employmentType = in_array($rawEmpType, ['minijob', 'part_time', 'full_time', 'working_student', 'short_term'], true)
            ? $rawEmpType
            : 'minijob';

        $rawCompType = (string) ($options['compensation_type'] ?? 'hourly');
        $compensationType = in_array($rawCompType, ['hourly', 'fixed_salary'], true)
            ? $rawCompType
            : 'hourly';

        $hourlyRateCents = max(1282, (int) ($options['hourly_rate_cents'] ?? 1400)); // Mind. gesetzlicher Mindestlohn
        $monthlyGrossCents = max(0, (int) ($options['monthly_gross_cents'] ?? 54000));
        $weeklyHours = max(1.0, min(48.0, (float) ($options['weekly_hours'] ?? 9.0)));
        $vacationDays = max(0, min(60, (int) ($options['vacation_days_per_year'] ?? 20)));
        $isFixedTerm = (bool) ($options['is_fixed_term'] ?? false);

        $label = !empty($options['invitation_label'])
            ? trim((string) $options['invitation_label'])
            : ('Mitarbeiter-Einladung #' . strtoupper(Str::random(4)));

        return EmployeeContract::create([
            'employee_id' => null,
            'invitation_label' => $label,
            'job_title' => !empty($options['job_title']) ? trim((string) $options['job_title']) : 'Manufaktur- & Versand-Assistent/in',
            'department' => !empty($options['department']) ? trim((string) $options['department']) : 'Produktion & Versand',
            'employment_type' => $employmentType,
            'compensation_type' => $compensationType,
            'hourly_rate_cents' => $hourlyRateCents,
            'monthly_gross_cents' => $monthlyGrossCents,
            'weekly_hours' => $weeklyHours,
            'vacation_days_per_year' => $vacationDays,
            'vacation_days_taken' => 0,
            'start_date' => !empty($options['start_date']) ? $options['start_date'] : now()->startOfMonth()->toDateString(),
            'end_date' => ($isFixedTerm && !empty($options['end_date'])) ? $options['end_date'] : null,
            'is_fixed_term' => $isFixedTerm,
            'probation_months' => max(0, min(6, (int) ($options['probation_months'] ?? 3))),
            'notice_period_text' => !empty($options['notice_period_text'])
                ? trim((string) $options['notice_period_text'])
                : '4 Wochen zum 15. oder zum Ende eines Kalendermonats (§ 622 BGB)',
            'work_location' => !empty($options['work_location'])
                ? trim((string) $options['work_location'])
                : 'Manufaktur Gifhorn / Mobiles Arbeiten nach Absprache',
            'working_hours_schedule' => !empty($options['working_hours_schedule'])
                ? trim((string) $options['working_hours_schedule'])
                : 'Mo–Fr flexibel nach Schichtplan (Kernzeit 09:00–16:00 Uhr)',
            'contract_number' => $this->generateNextContractNumber(),
            'invitation_token_hash' => hash('sha256', $rawToken),
            'invitation_token_encrypted' => $rawToken,
            'invitation_code_hash' => hash('sha256', $rawCode),
            'invitation_code_encrypted' => $rawCode,
            'invitation_expires_at' => now()->addDays(14),
            'is_active' => true,
            'is_archived' => false,
            'internal_notes' => $options['internal_notes'] ?? null,
        ]);
    }

    /**
     * Erneuert den Einladungs-Token und den 6-stelligen Verifizierungscode für 14 Tage.
     */
    public function regenerateInvitationToken(EmployeeContract $contract): string
    {
        $rawToken = Str::random(48);
        $rawCode = str_pad((string) random_int(100000, 999999), 6, '0', STR_PAD_LEFT);

        $contract->update([
            'invitation_token_hash' => hash('sha256', $rawToken),
            'invitation_token_encrypted' => $rawToken,
            'invitation_code_hash' => hash('sha256', $rawCode),
            'invitation_code_encrypted' => $rawCode,
            'invitation_expires_at' => now()->addDays(14),
            'invitation_accepted_at' => null,
        ]);

        return $rawToken;
    }

    /**
     * Normalisiert den 6-stelligen Verifizierungscode (entfernt Leerzeichen, Bindestriche und optionales SF-Präfix).
     */
    public function normalizeVerificationCode(string $inputCode): string
    {
        $clean = strtoupper(preg_replace('/[^0-9A-Za-z]/', '', trim($inputCode)));
        if (str_starts_with($clean, 'SF') && strlen($clean) > 6) {
            $clean = substr($clean, 2);
        }

        return $clean;
    }

    /**
     * Prüft Timing-safe, ob Einladungs-Token und 6-stelliger Verifizierungscode gültig sind.
     */
    public function verifyInvitationCode(string $rawToken, string $inputCode): ?EmployeeContract
    {
        $cleanToken = trim($rawToken);
        $cleanCode = $this->normalizeVerificationCode($inputCode);

        if (strlen($cleanToken) < 20 || strlen($cleanCode) < 4) {
            return null;
        }

        $tokenHash = hash('sha256', $cleanToken);
        $contract = EmployeeContract::with('employee.profile')
            ->where('invitation_token_hash', $tokenHash)
            ->first();

        if (!$contract || !$contract->is_active || $contract->is_archived || $contract->invitation_accepted_at !== null) {
            return null;
        }

        if ($contract->invitation_expires_at && $contract->invitation_expires_at->isPast()) {
            return null;
        }

        if (empty($contract->invitation_code_hash)) {
            return null;
        }

        $inputHash = hash('sha256', $cleanCode);
        if (!hash_equals((string) $contract->invitation_code_hash, $inputHash)) {
            return null;
        }

        return $contract;
    }

    /**
     * Schließt das digitale Mitarbeiter-Onboarding ab:
     * - Verifiziert Token & 6-stelligen Sicherheitscode
     * - Erstellt den Employee-Account (role = 'internal') + EmployeeProfile
     * - Speichert alle ELStAM- & Sozialversicherungs-Pflichtdaten (verschlüsselt)
     * - Speichert die digitale Unterschrift inkl. Zeitstempel & IP-Hash (§ 2 NachwG / BEG IV)
     * - Generiert automatisch Arbeitsvertrag.pdf und Personalfragebogen.pdf im Tresor (`buchhaltung/contracts/`)
     */
    public function completeSelfRegistration(string $rawToken, string $inputCode, array $data): ?EmployeeContract
    {
        $cleanToken = trim($rawToken);
        $cleanCode = $this->normalizeVerificationCode($inputCode);

        if (strlen($cleanToken) < 20 || strlen($cleanCode) < 4) {
            return null;
        }

        $tokenHash = hash('sha256', $cleanToken);
        $codeHash = hash('sha256', $cleanCode);

        $contract = DB::transaction(function () use ($tokenHash, $codeHash, $data) {
            $contract = EmployeeContract::with('employee.profile')
                ->where('invitation_token_hash', $tokenHash)
                ->lockForUpdate()
                ->first();

            if (!$contract || !$contract->is_active || $contract->is_archived || $contract->invitation_accepted_at !== null) {
                return null;
            }

            if ($contract->invitation_expires_at && $contract->invitation_expires_at->isPast()) {
                return null;
            }

            if (!empty($contract->invitation_code_hash) && !hash_equals((string) $contract->invitation_code_hash, $codeHash)) {
                return null;
            }

            $firstName = trim((string) ($data['first_name'] ?? ''));
            $lastName = trim((string) ($data['last_name'] ?? ''));
            $email = strtolower(trim((string) ($data['email'] ?? '')));
            $password = (string) ($data['password'] ?? '');

            $employee = $contract->employee;
            if ($email !== '' && SystemUser::isEmailTakenAcrossGuards($email, 'employee', $employee?->id)) {
                throw ValidationException::withMessages([
                    'email' => 'Diese E-Mail-Adresse kann für die Mitarbeiter-Registrierung nicht verwendet werden. Bitte nutze eine andere E-Mail-Adresse.',
                ]);
            }

            if ($employee) {
                $employee->update([
                    'first_name' => $firstName ?: $employee->first_name,
                    'last_name' => $lastName ?: $employee->last_name,
                    'email' => $email ?: $employee->email,
                    'role' => 'internal',
                    'password' => Hash::make($password),
                    'email_verified_at' => now(),
                ]);
            } else {
                $employee = Employee::create([
                    'first_name' => $firstName,
                    'last_name' => $lastName,
                    'email' => $email,
                    'role' => 'internal',
                    'password' => Hash::make($password),
                    'email_verified_at' => now(),
                ]);
            }

            $employee->load('profile');
            if ($employee->profile) {
                $employee->profile->update([
                    'street' => trim((string) ($data['street'] ?? '')),
                    'house_number' => trim((string) ($data['house_number'] ?? '')),
                    'postal' => trim((string) ($data['postal'] ?? '')),
                    'city' => trim((string) ($data['city'] ?? '')),
                    'country' => strtoupper(trim((string) ($data['country'] ?? 'DE'))),
                    'phone_number' => trim((string) ($data['phone_number'] ?? '')),
                    'birthday' => !empty($data['birth_date']) ? $data['birth_date'] : null,
                    'email_verified_at' => now(),
                ]);
            }

            $contractNumber = $contract->contract_number ?: $this->generateNextContractNumber();
            $signatureName = !empty($data['signature_name'])
                ? trim((string) $data['signature_name'])
                : trim($firstName . ' ' . $lastName);

            $contract->update([
                'employee_id' => $employee->id,
                'contract_number' => $contractNumber,
                'birth_date' => !empty($data['birth_date']) ? $data['birth_date'] : null,
                'birth_place' => trim((string) ($data['birth_place'] ?? '')),
                'nationality' => trim((string) ($data['nationality'] ?? 'Deutsch')),
                'tax_id' => preg_replace('/\s+/', '', (string) ($data['tax_id'] ?? '')),
                'tax_class' => trim((string) ($data['tax_class'] ?? 'I')),
                'church_tax' => (bool) ($data['church_tax'] ?? false),
                'children_allowances' => (float) ($data['children_allowances'] ?? 0.0),
                'social_security_number' => strtoupper(preg_replace('/\s+/', '', (string) ($data['social_security_number'] ?? ''))) ?: null,
                'health_insurance_name' => trim((string) ($data['health_insurance_name'] ?? '')),
                'health_insurance_type' => in_array((string) ($data['health_insurance_type'] ?? 'statutory'), ['statutory', 'private', 'family'], true)
                    ? (string) ($data['health_insurance_type'] ?? 'statutory')
                    : 'statutory',
                'rv_exemption_requested' => (bool) ($data['rv_exemption_requested'] ?? false),
                'has_other_employment' => (bool) ($data['has_other_employment'] ?? false),
                'account_holder' => !empty($data['account_holder']) ? trim((string) $data['account_holder']) : trim($firstName . ' ' . $lastName),
                'iban' => !empty($data['iban']) ? strtoupper(preg_replace('/\s+/', '', (string) $data['iban'])) : null,
                'bic' => !empty($data['bic']) ? strtoupper(preg_replace('/\s+/', '', (string) $data['bic'])) : null,
                'bank_name' => trim((string) ($data['bank_name'] ?? '')),
                'signature_name' => $signatureName,
                'signature_data' => $data['signature_data'] ?? ('DIGITAL-SIGNATURE:' . $signatureName),
                'signed_at' => now(),
                'signed_ip_hash' => hash('sha256', (string) request()->ip() . config('app.key')),
                'invitation_token_hash' => null,
                'invitation_token_encrypted' => null,
                'invitation_code_hash' => null,
                'invitation_code_encrypted' => null,
                'invitation_expires_at' => null,
                'invitation_accepted_at' => now(),
            ]);

            if (class_exists(SystemLog::class)) {
                SystemLog::create([
                    'type' => 'system',
                    'action_id' => 'hr:onboarding_completed',
                    'title' => 'Mitarbeiter-Onboarding & Vertrag unterzeichnet',
                    'message' => "Mitarbeiter '{$firstName} {$lastName}' ({$email}) hat den Arbeitsvertrag {$contractNumber} digital unterzeichnet.",
                    'status' => 'success',
                    'payload' => [
                        'contract_id' => $contract->id,
                        'employee_id' => $employee->id,
                        'contract_number' => $contractNumber,
                        'employment_type' => $contract->employment_type,
                    ],
                    'started_at' => now(),
                    'finished_at' => now(),
                ]);
            }

            return $contract->fresh(['employee.profile']);
        });

        if ($contract) {
            $this->generateAndStoreContractPdf($contract);
            $this->generateAndStoreQuestionnairePdf($contract);
            $contract->refresh();
        }

        return $contract;
    }

    /**
     * Gesetzliche Mindestpausen nach § 4 ArbZG (Arbeitszeitgesetz):
     * - Bis 6 Stunden (360 Min.): 0 Min. Pflichtpause
     * - Über 6 bis 9 Stunden (361–540 Min.): mind. 30 Min. Pause
     * - Über 9 Stunden (> 540 Min.): mind. 45 Min. Pause
     */
    public function calculateRequiredBreakMinutes(int $grossMinutes): int
    {
        if ($grossMinutes > 540) {
            return 45;
        }
        if ($grossMinutes > 360) {
            return 30;
        }

        return 0;
    }

    /**
     * Erfasst oder aktualisiert einen Arbeitszeiteintrag nach § 17 MiLoG / § 4 ArbZG.
     */
    public function recordTimeEntry(EmployeeContract $contract, array $data): EmployeeTimeEntry
    {
        $workDate = !empty($data['work_date']) ? Carbon::parse($data['work_date'])->toDateString() : now()->toDateString();
        $rawEntryType = (string) ($data['entry_type'] ?? 'work');
        $entryType = in_array($rawEntryType, ['work', 'vacation', 'sick'], true) ? $rawEntryType : 'work';

        $startTimeStr = trim((string) ($data['start_time'] ?? '09:00'));
        $endTimeStr = trim((string) ($data['end_time'] ?? '13:00'));

        $startedAt = Carbon::parse($workDate . ' ' . $startTimeStr);
        $endedAt = Carbon::parse($workDate . ' ' . $endTimeStr);
        if ($endedAt->lessThanOrEqualTo($startedAt)) {
            $endedAt->addDay();
        }

        $grossMinutes = max(0, (int) $startedAt->diffInMinutes($endedAt));
        $requiredBreak = $entryType === 'work' ? $this->calculateRequiredBreakMinutes($grossMinutes) : 0;

        $inputBreak = isset($data['break_minutes']) && $data['break_minutes'] !== ''
            ? max(0, (int) $data['break_minutes'])
            : $requiredBreak;

        $autoEnforceBreak = (bool) ($data['auto_enforce_arbzg'] ?? true);
        $breakMinutes = $autoEnforceBreak ? max($inputBreak, $requiredBreak) : $inputBreak;
        $netMinutes = max(0, $grossMinutes - $breakMinutes);
        $arbzgCompliant = ($breakMinutes >= $requiredBreak) && ($netMinutes <= 600); // § 3 ArbZG max. 10h werktäglich

        return EmployeeTimeEntry::create([
            'contract_id' => $contract->id,
            'employee_id' => $contract->employee_id,
            'work_date' => $workDate,
            'started_at' => $startedAt,
            'ended_at' => $endedAt,
            'break_minutes' => $breakMinutes,
            'net_minutes' => $netMinutes,
            'entry_type' => $entryType,
            'activity_note' => !empty($data['activity_note']) ? trim((string) $data['activity_note']) : 'Manufaktur & Versand',
            'status' => 'recorded',
            'arbzg_compliant' => $arbzgCompliant,
        ]);
    }

    /**
     * Startet die Live-Stempeluhr (Einstempeln).
     */
    public function clockIn(EmployeeContract $contract, ?string $activityNote = null): EmployeeTimeEntry
    {
        $running = EmployeeTimeEntry::where('contract_id', $contract->id)
            ->where('status', 'running')
            ->first();

        if ($running) {
            return $running;
        }

        return EmployeeTimeEntry::create([
            'contract_id' => $contract->id,
            'employee_id' => $contract->employee_id,
            'work_date' => now()->toDateString(),
            'started_at' => now(),
            'ended_at' => null,
            'break_minutes' => 0,
            'net_minutes' => 0,
            'entry_type' => 'work',
            'activity_note' => $activityNote ?: 'Aktive Schicht (Stempeluhr)',
            'status' => 'running',
            'arbzg_compliant' => true,
        ]);
    }

    /**
     * Beendet die Live-Stempeluhr (Ausstempeln) und wendet automatisch § 4 ArbZG Pausenregeln an.
     */
    public function clockOut(EmployeeTimeEntry $entry, ?int $customBreakMinutes = null, ?string $activityNote = null): EmployeeTimeEntry
    {
        $endedAt = now();
        $startedAt = $entry->started_at ?: $endedAt->copy()->subMinutes(60);
        $grossMinutes = max(1, (int) $startedAt->diffInMinutes($endedAt));
        $requiredBreak = $this->calculateRequiredBreakMinutes($grossMinutes);

        $breakMinutes = $customBreakMinutes !== null
            ? max($customBreakMinutes, $requiredBreak)
            : $requiredBreak;

        $netMinutes = max(1, $grossMinutes - $breakMinutes);
        $arbzgCompliant = ($breakMinutes >= $requiredBreak) && ($netMinutes <= 600);

        $entry->update([
            'ended_at' => $endedAt,
            'break_minutes' => $breakMinutes,
            'net_minutes' => $netMinutes,
            'activity_note' => $activityNote ?: ($entry->activity_note ?: 'Manufaktur & Versand'),
            'status' => 'recorded',
            'arbzg_compliant' => $arbzgCompliant,
        ]);

        return $entry->fresh();
    }

    /**
     * Zentralisierte Monatsberechnung für Stunden, Soll-Ist, Brutto-/Netto-Vorschau und Minijob-Grenze.
     */
    public function getMonthlyStats(EmployeeContract $contract, int $year, int $month): array
    {
        $entries = EmployeeTimeEntry::where('contract_id', $contract->id)
            ->whereYear('work_date', $year)
            ->whereMonth('work_date', $month)
            ->where('status', '!=', 'running')
            ->orderByDesc('work_date')
            ->orderByDesc('started_at')
            ->get();

        $workedMinutes = (int) $entries->sum('net_minutes');
        $breakMinutes = (int) $entries->sum('break_minutes');
        $workedHoursDecimal = round($workedMinutes / 60, 2);
        $hoursInt = intdiv($workedMinutes, 60);
        $minsInt = $workedMinutes % 60;
        $workedHoursFormatted = sprintf('%d:%02d Std.', $hoursInt, $minsInt);

        $targetHoursDecimal = round(((float) $contract->weekly_hours) * 4.33, 1);

        if ($contract->compensation_type === 'fixed_salary') {
            $estimatedGrossCents = (int) $contract->monthly_gross_cents;
        } else {
            $estimatedGrossCents = (int) round(($workedMinutes / 60) * ((int) $contract->hourly_rate_cents));
        }

        // Vorbereitende Netto- & Arbeitgeberkosten-Vorschau nach Beschäftigungsart
        $calc = $this->calculatePreliminaryPayrollAmounts($contract, $estimatedGrossCents);

        $isMinijob = $contract->employment_type === 'minijob';
        $minijobUsagePercent = $isMinijob
            ? min(100, (int) round(($estimatedGrossCents / self::MINIJOB_LIMIT_CENTS) * 100))
            : 0;
        $minijobWarning = $isMinijob && $estimatedGrossCents > self::MINIJOB_LIMIT_CENTS;

        $arbzgAllCompliant = $entries->every(fn ($e) => (bool) $e->arbzg_compliant);

        return [
            'year' => $year,
            'month' => $month,
            'entries' => $entries,
            'entries_count' => $entries->count(),
            'worked_minutes' => $workedMinutes,
            'break_minutes' => $breakMinutes,
            'worked_hours_decimal' => $workedHoursDecimal,
            'worked_hours_formatted' => $workedHoursFormatted,
            'target_hours_decimal' => $targetHoursDecimal,
            'estimated_gross_cents' => $estimatedGrossCents,
            'estimated_net_cents' => $calc['net_payout_cents'],
            'employer_charges_cents' => $calc['employer_charges_cents'],
            'total_employer_cost_cents' => $calc['total_employer_cost_cents'],
            'is_minijob' => $isMinijob,
            'minijob_limit_cents' => self::MINIJOB_LIMIT_CENTS,
            'minijob_usage_percent' => $minijobUsagePercent,
            'minijob_warning' => $minijobWarning,
            'arbzg_all_compliant' => $arbzgAllCompliant,
        ];
    }

    /**
     * Berechnet vorbereitende Brutto-, Netto- und Arbeitgeber-Gesamtkosten (als Vorschau bzw. Basis für den Lohnbeleg).
     */
    public function calculatePreliminaryPayrollAmounts(EmployeeContract $contract, int $grossCents): array
    {
        $grossCents = max(0, $grossCents);

        if ($contract->employment_type === 'minijob') {
            // Minijob: Arbeitnehmer zahlt 0 % (bei RV-Befreiung nach § 6 Abs. 1b SGB VI) oder 3,6 % RV-Eigenanteil.
            // Arbeitgeber zahlt ca. 28 % Pauschalabgaben (13 % KV + 15 % RV) + ca. 3,45 % Umlagen/Pauschsteuer (~31,45 %).
            $employeeRvDeduction = $contract->rv_exemption_requested ? 0 : (int) round($grossCents * 0.036);
            $netPayoutCents = max(0, $grossCents - $employeeRvDeduction);
            $employerChargesCents = (int) round($grossCents * 0.3145);
        } elseif ($contract->employment_type === 'working_student' || $contract->employment_type === 'short_term') {
            $employeeDeduction = (int) round($grossCents * 0.093);
            $netPayoutCents = max(0, $grossCents - $employeeDeduction);
            $employerChargesCents = (int) round($grossCents * 0.11);
        } else {
            // Teilzeit / Vollzeit (Richtwert-Vorschau bis offizieller DATEV-Lohnzettel hinterlegt wird)
            $netPayoutCents = (int) round($grossCents * 0.72);
            $employerChargesCents = (int) round($grossCents * 0.21);
        }

        return [
            'gross_amount_cents' => $grossCents,
            'net_payout_cents' => $netPayoutCents,
            'employer_charges_cents' => $employerChargesCents,
            'total_employer_cost_cents' => $grossCents + $employerChargesCents,
        ];
    }

    /**
     * Erstellt oder aktualisiert eine monatliche Lohnabrechnung + Monats-Stundenzettel (PDF).
     */
    public function createOrUpdatePayslip(EmployeeContract $contract, int $year, int $month, array $overrides = []): EmployeePayslip
    {
        $stats = $this->getMonthlyStats($contract, $year, $month);

        $grossCents = isset($overrides['gross_amount_cents']) && $overrides['gross_amount_cents'] !== null
            ? max(0, (int) $overrides['gross_amount_cents'])
            : $stats['estimated_gross_cents'];

        $defaultCalc = $this->calculatePreliminaryPayrollAmounts($contract, $grossCents);

        $netCents = isset($overrides['net_payout_cents']) && $overrides['net_payout_cents'] !== null
            ? max(0, (int) $overrides['net_payout_cents'])
            : $defaultCalc['net_payout_cents'];

        $employerChargesCents = isset($overrides['employer_charges_cents']) && $overrides['employer_charges_cents'] !== null
            ? max(0, (int) $overrides['employer_charges_cents'])
            : $defaultCalc['employer_charges_cents'];

        $totalEmployerCostCents = $grossCents + $employerChargesCents;

        $payslip = DB::transaction(function () use ($contract, $year, $month, $stats, $grossCents, $netCents, $employerChargesCents, $totalEmployerCostCents, $overrides) {
            $existing = EmployeePayslip::where('contract_id', $contract->id)
                ->where('period_year', $year)
                ->where('period_month', $month)
                ->lockForUpdate()
                ->first();

            $employee = $contract->employee;
            $profile = $employee?->profile;

            $snapshot = [
                'name' => trim(($employee->first_name ?? '') . ' ' . ($employee->last_name ?? '')) ?: $contract->invitation_label,
                'email' => $employee->email ?? '',
                'job_title' => $contract->job_title,
                'employment_type' => $contract->employment_type,
                'employment_type_label' => $contract->employment_type_label,
                'street' => trim(($profile->street ?? '') . ' ' . ($profile->house_number ?? '')),
                'postal' => $profile->postal ?? '',
                'city' => $profile->city ?? '',
                'tax_class' => $contract->tax_class,
                'health_insurance_name' => $contract->health_insurance_name,
                'account_holder' => $contract->account_holder,
                'masked_iban' => $contract->masked_iban,
            ];

            if ($existing) {
                $existing->update([
                    'employee_id' => $contract->employee_id,
                    'worked_minutes' => $stats['worked_minutes'],
                    'hourly_rate_cents_snapshot' => (int) $contract->hourly_rate_cents,
                    'gross_amount_cents' => $grossCents,
                    'net_payout_cents' => $netCents,
                    'employer_charges_cents' => $employerChargesCents,
                    'total_employer_cost_cents' => $totalEmployerCostCents,
                    'tax_class_snapshot' => $contract->tax_class,
                    'uploaded_external_pdf_path' => $overrides['uploaded_external_pdf_path'] ?? $existing->uploaded_external_pdf_path,
                    'notes' => $overrides['notes'] ?? $existing->notes,
                    'recipient_snapshot' => $snapshot,
                    'approved_at' => $existing->approved_at ?: now(),
                ]);
                $payslip = $existing;
            } else {
                $payslip = EmployeePayslip::create([
                    'contract_id' => $contract->id,
                    'employee_id' => $contract->employee_id,
                    'payslip_number' => $this->generateNextPayslipNumber($year, $month),
                    'period_year' => $year,
                    'period_month' => $month,
                    'worked_minutes' => $stats['worked_minutes'],
                    'hourly_rate_cents_snapshot' => (int) $contract->hourly_rate_cents,
                    'gross_amount_cents' => $grossCents,
                    'net_payout_cents' => $netCents,
                    'employer_charges_cents' => $employerChargesCents,
                    'total_employer_cost_cents' => $totalEmployerCostCents,
                    'tax_class_snapshot' => $contract->tax_class,
                    'uploaded_external_pdf_path' => $overrides['uploaded_external_pdf_path'] ?? null,
                    'status' => 'open',
                    'approved_at' => now(),
                    'notes' => $overrides['notes'] ?? null,
                    'recipient_snapshot' => $snapshot,
                ]);
            }

            // Alle Zeiteinträge des Monats als geprüft markieren
            EmployeeTimeEntry::where('contract_id', $contract->id)
                ->whereYear('work_date', $year)
                ->whereMonth('work_date', $month)
                ->where('status', 'recorded')
                ->update(['status' => 'approved']);

            return $payslip;
        });

        $this->generateAndStoreTimesheetPdf($contract, $year, $month, $payslip);
        $this->generateAndStorePayslipPdf($payslip);

        return $payslip->fresh(['contract.employee.profile']);
    }

    /**
     * Markiert eine Lohnabrechnung als überwiesen (`paid`) und bucht sie vollautomatisch
     * inklusive Lohnbeleg & Stundenzettel in die Buchhaltung (`AccountingSpecialIssue`) für den Steuerberater.
     */
    public function markPayslipAsPaid(EmployeePayslip $payslip, ?string $adminId = null): EmployeePayslip
    {
        return DB::transaction(function () use ($payslip, $adminId) {
            $payslip = EmployeePayslip::with('contract.employee.profile')
                ->lockForUpdate()
                ->findOrFail($payslip->id);

            if ($payslip->status === 'paid') {
                return $payslip;
            }

            if (empty($payslip->pdf_path) || !Storage::disk('local')->exists((string) $payslip->pdf_path)) {
                $this->generateAndStorePayslipPdf($payslip);
            }
            if (empty($payslip->timesheet_pdf_path) || !Storage::disk('local')->exists((string) $payslip->timesheet_pdf_path)) {
                $this->generateAndStoreTimesheetPdf($payslip->contract, (int) $payslip->period_year, (int) $payslip->period_month, $payslip);
            }
            $payslip->refresh();

            $effectiveAdminId = $adminId ?: Admin::orderBy('created_at', 'asc')->value('id');
            $issueId = $payslip->accounting_special_issue_id;

            if ($effectiveAdminId && !$issueId) {
                AccountingCategory::firstOrCreate(
                    ['admin_id' => $effectiveAdminId, 'name' => 'Personal & Löhne'],
                    ['is_business' => true, 'usage_count' => 1]
                );

                $employeeName = $payslip->recipient_snapshot['name'] ?? ($payslip->contract?->invitation_label ?: 'Mitarbeiter');
                $totalCostEur = round(((int) $payslip->total_employer_cost_cents) / 100, 2);
                $netEur = number_format(((int) $payslip->net_payout_cents) / 100, 2, ',', '.');
                $grossEur = number_format(((int) $payslip->gross_amount_cents) / 100, 2, ',', '.');

                $filePaths = array_values(array_filter([
                    $payslip->effective_pdf_path,
                    $payslip->timesheet_pdf_path,
                ]));

                $issue = AccountingSpecialIssue::create([
                    'admin_id' => $effectiveAdminId,
                    'title' => "Lohnabrechnung {$payslip->payslip_number} ({$employeeName} - {$payslip->period_label})",
                    'category' => 'Personal & Löhne',
                    'amount' => -abs($totalCostEur),
                    'tax_rate' => 0,
                    'is_business' => true,
                    'location' => $employeeName,
                    'execution_date' => now()->toDateString(),
                    'note' => "Brutto: {$grossEur} € | Netto-Überweisung: {$netEur} € | SKR03 Konto 4120/4190 (Umsatzsteuerfrei)",
                    'file_paths' => $filePaths,
                ]);

                $issueId = $issue->id;
            }

            $payslip->update([
                'status' => 'paid',
                'paid_at' => now(),
                'paid_by_admin_id' => $effectiveAdminId,
                'accounting_special_issue_id' => $issueId,
            ]);

            return $payslip->fresh(['contract.employee.profile', 'accountingSpecialIssue']);
        });
    }

    /**
     * Erzeugt das Arbeitsvertrags-PDF (§ 2 NachwG / BEG IV) und speichert es unter `buchhaltung/contracts/`.
     */
    public function generateAndStoreContractPdf(EmployeeContract $contract): ?string
    {
        try {
            $contract->loadMissing('employee.profile');
            if (empty($contract->contract_number)) {
                $contract->update(['contract_number' => $this->generateNextContractNumber()]);
            }

            $pdf = Pdf::loadView('global.pdf.employee_contract', [
                'contract' => $contract,
                'employee' => $contract->employee,
                'profile' => $contract->employee?->profile,
            ])->setPaper('a4', 'portrait');

            $safeNo = preg_replace('/[^A-Za-z0-9_\-]/', '_', (string) $contract->contract_number);
            $relativePath = "buchhaltung/contracts/Arbeitsvertrag_{$safeNo}.pdf";

            Storage::disk('local')->put($relativePath, $pdf->output());

            if ($contract->contract_pdf_path !== $relativePath) {
                $contract->update(['contract_pdf_path' => $relativePath]);
            }

            return $relativePath;
        } catch (\Throwable $e) {
            Log::error("Fehler beim Erstellen des Arbeitsvertrags {$contract->id}: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Erzeugt das Personalfragebogen-PDF (ELStAM & SV-Stammblatt für den Steuerberater) unter `buchhaltung/contracts/`.
     */
    public function generateAndStoreQuestionnairePdf(EmployeeContract $contract): ?string
    {
        try {
            $contract->loadMissing('employee.profile');
            if (empty($contract->contract_number)) {
                $contract->update(['contract_number' => $this->generateNextContractNumber()]);
            }

            $pdf = Pdf::loadView('global.pdf.employee_questionnaire', [
                'contract' => $contract,
                'employee' => $contract->employee,
                'profile' => $contract->employee?->profile,
            ])->setPaper('a4', 'portrait');

            $safeNo = preg_replace('/[^A-Za-z0-9_\-]/', '_', (string) $contract->contract_number);
            $relativePath = "buchhaltung/contracts/Personalbogen_{$safeNo}.pdf";

            Storage::disk('local')->put($relativePath, $pdf->output());

            if ($contract->questionnaire_pdf_path !== $relativePath) {
                $contract->update(['questionnaire_pdf_path' => $relativePath]);
            }

            return $relativePath;
        } catch (\Throwable $e) {
            Log::error("Fehler beim Erstellen des Personalbogens {$contract->id}: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Erzeugt den monatlichen Stundenzettel nach § 17 MiLoG / § 4 ArbZG unter `buchhaltung/receipts/`.
     */
    public function generateAndStoreTimesheetPdf(EmployeeContract $contract, int $year, int $month, ?EmployeePayslip $payslip = null): ?string
    {
        try {
            $contract->loadMissing('employee.profile');
            $stats = $this->getMonthlyStats($contract, $year, $month);

            $pdf = Pdf::loadView('global.pdf.employee_timesheet', [
                'contract' => $contract,
                'employee' => $contract->employee,
                'profile' => $contract->employee?->profile,
                'stats' => $stats,
                'entries' => $stats['entries'],
                'year' => $year,
                'month' => $month,
            ])->setPaper('a4', 'portrait');

            $safeNo = preg_replace('/[^A-Za-z0-9_\-]/', '_', (string) ($contract->contract_number ?: $contract->id));
            $monthPadded = str_pad((string) $month, 2, '0', STR_PAD_LEFT);
            $relativePath = "buchhaltung/receipts/Stundenzettel_{$safeNo}_{$year}_{$monthPadded}.pdf";

            Storage::disk('local')->put($relativePath, $pdf->output());

            if ($payslip && $payslip->timesheet_pdf_path !== $relativePath) {
                $payslip->update(['timesheet_pdf_path' => $relativePath]);
            }

            return $relativePath;
        } catch (\Throwable $e) {
            Log::error("Fehler beim Erstellen des Stundenzettels {$contract->id}: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Erzeugt den vorbereitenden Lohnbeleg / Entgeltabrechnungs-PDF unter `buchhaltung/receipts/`.
     */
    public function generateAndStorePayslipPdf(EmployeePayslip $payslip): ?string
    {
        try {
            $payslip->loadMissing('contract.employee.profile');
            $contract = $payslip->contract;

            $pdf = Pdf::loadView('global.pdf.employee_payslip', [
                'payslip' => $payslip,
                'contract' => $contract,
                'employee' => $contract?->employee,
                'profile' => $contract?->employee?->profile,
            ])->setPaper('a4', 'portrait');

            $safeNo = preg_replace('/[^A-Za-z0-9_\-]/', '_', (string) $payslip->payslip_number);
            $relativePath = "buchhaltung/receipts/Lohnabrechnung_{$safeNo}.pdf";

            Storage::disk('local')->put($relativePath, $pdf->output());

            if ($payslip->pdf_path !== $relativePath) {
                $payslip->update(['pdf_path' => $relativePath]);
            }

            return $relativePath;
        } catch (\Throwable $e) {
            Log::error("Fehler beim Erstellen der Lohnabrechnung {$payslip->payslip_number}: " . $e->getMessage());
            return null;
        }
    }

    public function generateNextContractNumber(): string
    {
        $year = date('Y');
        $prefix = "AV-{$year}-";

        $lastNumber = EmployeeContract::withTrashed()
            ->where('contract_number', 'like', $prefix . '%')
            ->orderByDesc('contract_number')
            ->value('contract_number');

        $nextSeq = 1;
        if ($lastNumber && preg_match('/^AV-\d{4}-(\d+)$/', $lastNumber, $matches)) {
            $nextSeq = ((int) $matches[1]) + 1;
        }

        return $prefix . str_pad((string) $nextSeq, 4, '0', STR_PAD_LEFT);
    }

    public function generateNextPayslipNumber(int $year, int $month): string
    {
        $monthPadded = str_pad((string) $month, 2, '0', STR_PAD_LEFT);
        $prefix = "LOHN-{$year}{$monthPadded}-";

        $lastNumber = EmployeePayslip::where('payslip_number', 'like', $prefix . '%')
            ->orderByDesc('payslip_number')
            ->value('payslip_number');

        $nextSeq = 1;
        if ($lastNumber && preg_match('/^LOHN-\d{6}-(\d+)$/', $lastNumber, $matches)) {
            $nextSeq = ((int) $matches[1]) + 1;
        }

        return $prefix . str_pad((string) $nextSeq, 4, '0', STR_PAD_LEFT);
    }

    /**
     * Erzeugt ein sauberes SVG für den SEPA-GiroCode (EPC-QR) zur 1-Klick-Gehaltsüberweisung.
     */
    public function generateQrCodeSvg(string $payload, int $size = 180): string
    {
        $safeSize = max(100, min(400, $size));
        $renderer = new ImageRenderer(
            new RendererStyle($safeSize, 1),
            new SvgImageBackEnd()
        );

        $writer = new Writer($renderer);
        $svg = $writer->writeString($payload);

        return preg_replace('/<\?xml.*?\?>/i', '', $svg) ?: $svg;
    }
}
