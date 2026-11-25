package de.meinseelenfunke.app.data.api

import retrofit2.http.Body
import retrofit2.http.DELETE
import retrofit2.http.GET
import retrofit2.http.POST
import retrofit2.http.Path
import retrofit2.http.Query

data class EmployeeCockpitEnvelope(
    val success: Boolean = false,
    val message: String? = null,
    val data: EmployeeCockpitData? = null
)

data class EmployeeCockpitData(
    val has_contract: Boolean = false,
    val selected_year: Int = 2026,
    val selected_month: Int = 11,
    val employee: EmployeeIdentityDto? = null,
    val contract: EmployeeContractSummaryDto? = null,
    val operator_profile: EmployeeOperatorProfileDto? = null,
    val running_entry: EmployeeRunningEntryDto? = null,
    val kpis: EmployeeCockpitKpisDto? = null,
    val time_entries: List<EmployeeTimeEntryDto> = emptyList(),
    val payslips: List<EmployeePayslipDto> = emptyList(),
    val support_tickets: List<EmployeeSupportTicketDto> = emptyList(),
    val physical_products: List<EmployeeProductItemDto> = emptyList(),
    val recent_losses: List<EmployeeLossItemDto> = emptyList(),
    val permissions: RolePermissionsDto? = null
)

data class EmployeeIdentityDto(
    val id: String = "",
    val first_name: String = "",
    val last_name: String = "",
    val full_name: String = "",
    val email: String = ""
)

data class EmployeeContractSummaryDto(
    val id: String = "",
    val contract_number: String = "",
    val job_title: String = "",
    val department: String = "",
    val weekly_hours: Double = 0.0,
    val vacation_days_per_year: Int = 0,
    val vacation_days_taken: Int = 0,
    val remaining_vacation_days: Int = 0,
    val can_access_tasks: Boolean = true,
    val can_access_production: Boolean = false,
    val can_access_support: Boolean = false,
    val can_access_inventory: Boolean = false
)

data class EmployeeOperatorProfileDto(
    val initials: String = "SF",
    val avatar_url: String? = null,
    val rank_class: String = "KLASSE C",
    val title_label: String = "Team",
    val ep_total: Int = 0,
    val efficiency_score: Int = 100
)

data class EmployeeRunningEntryDto(
    val id: String = "",
    val started_at_iso: String? = null,
    val started_at_time: String = "",
    val activity_note: String = ""
)

data class EmployeeCockpitKpisDto(
    val worked_hours_formatted: String = "0:00 Std.",
    val worked_minutes: Int = 0,
    val remaining_vacation_days: Int = 0,
    val vacation_days_per_year: Int = 0,
    val assigned_open_tasks_count: Int = 0,
    val efficiency_score: Int = 100,
    val open_orders_count: Int = 0,
    val express_orders_count: Int = 0
)

data class EmployeeTimeEntryDto(
    val id: String = "",
    val work_date: String = "",
    val work_date_formatted: String = "",
    val entry_type: String = "work",
    val started_at_time: String? = null,
    val ended_at_time: String? = null,
    val break_minutes: Int = 0,
    val net_minutes: Int = 0,
    val formatted_hours: String = "0:00 Std.",
    val activity_note: String = "",
    val status: String = "pending_approval",
    val can_delete: Boolean = true
)

data class EmployeePayslipDto(
    val id: String = "",
    val payslip_number: String = "",
    val period_year: Int = 2026,
    val period_month: Int = 11,
    val period_label: String = "",
    val net_payout_cents: Int = 0,
    val net_payout_formatted: String = "0,00 €",
    val status: String = "draft"
)

data class EmployeeSupportTicketDto(
    val id: String = "",
    val ticket_number: String = "",
    val subject: String = "",
    val status: String = "open",
    val customer_name: String = "Kunde",
    val updated_at_formatted: String = ""
)

data class EmployeeProductItemDto(
    val id: String = "",
    val name: String = "",
    val quantity: Int = 0
)

data class EmployeeLossItemDto(
    val id: String = "",
    val product_name: String = "",
    val quantity: Int = 1,
    val reason: String = "",
    val created_at_formatted: String = ""
)

data class EmployeeClockNoteRequest(
    val activity_note: String = "Manufaktur & Versand"
)

data class EmployeeManualTimeRequest(
    val work_date: String,
    val start_time: String,
    val end_time: String,
    val activity_note: String = "Manufaktur & Versand"
)

data class EmployeeLeaveRequest(
    val entry_type: String,
    val start_date: String,
    val end_date: String,
    val activity_note: String = ""
)

data class EmployeeInventoryLossRequest(
    val product_id: String,
    val quantity: Int,
    val reason: String
)

interface EmployeeApi {
    @GET("employee/cockpit")
    suspend fun getCockpit(
        @Query("year") year: Int? = null,
        @Query("month") month: Int? = null
    ): EmployeeCockpitEnvelope

    @POST("employee/clock-in")
    suspend fun clockIn(@Body body: EmployeeClockNoteRequest = EmployeeClockNoteRequest()): EmployeeCockpitEnvelope

    @POST("employee/clock-out")
    suspend fun clockOut(@Body body: EmployeeClockNoteRequest = EmployeeClockNoteRequest()): EmployeeCockpitEnvelope

    @POST("employee/time-entries")
    suspend fun saveTimeEntry(@Body body: EmployeeManualTimeRequest): EmployeeCockpitEnvelope

    @POST("employee/leave-entries")
    suspend fun saveLeaveEntry(@Body body: EmployeeLeaveRequest): EmployeeCockpitEnvelope

    @DELETE("employee/time-entries/{id}")
    suspend fun deleteTimeEntry(@Path("id") id: String): EmployeeCockpitEnvelope

    @POST("employee/inventory/loss")
    suspend fun reportInventoryLoss(@Body body: EmployeeInventoryLossRequest): EmployeeCockpitEnvelope
}
