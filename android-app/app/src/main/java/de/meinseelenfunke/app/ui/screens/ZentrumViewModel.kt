package de.meinseelenfunke.app.ui.screens

import androidx.lifecycle.ViewModel
import androidx.lifecycle.viewModelScope
import de.meinseelenfunke.app.data.api.CalendarEvent
import de.meinseelenfunke.app.data.api.FinanceKpis
import de.meinseelenfunke.app.data.api.ManagementDayRoutine
import de.meinseelenfunke.app.data.api.ManagementTask
import de.meinseelenfunke.app.di.ServiceLocator
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.asStateFlow
import kotlinx.coroutines.launch

sealed class DashboardState {
    object Loading : DashboardState()
    data class Success(
        val kpis: FinanceKpis?,
        val routines: List<ManagementDayRoutine>,
        val tasks: List<ManagementTask>,
        val events: List<CalendarEvent>
    ) : DashboardState()
    data class Error(val message: String) : DashboardState()
}

class ZentrumViewModel : ViewModel() {

    private val financeRepository = ServiceLocator.financeRepository
    private val organizerRepository = ServiceLocator.organizerRepository

    private val _dashboardState = MutableStateFlow<DashboardState>(DashboardState.Loading)
    val dashboardState: StateFlow<DashboardState> = _dashboardState.asStateFlow()

    private val _isUploading = MutableStateFlow(false)
    val isUploading: StateFlow<Boolean> = _isUploading.asStateFlow()

    private val _actionResult = MutableStateFlow<Result<String>?>(null)
    val actionResult: StateFlow<Result<String>?> = _actionResult.asStateFlow()

    fun clearActionResult() {
        _actionResult.value = null
    }

    init {
        loadDashboardData()
    }

    fun submitQuickEntry(
        title: String,
        amount: Double,
        category: String?,
        isBusiness: Boolean,
        date: String?,
        location: String?,
        fileBytes: ByteArray?,
        fileName: String?,
        mimeType: String?
    ) {
        if (title.isBlank() || amount == 0.0) {
            _actionResult.value = Result.failure(Exception("Titel und valider Betrag sind erforderlich."))
            return
        }

        viewModelScope.launch {
            _isUploading.value = true
            _actionResult.value = null
            financeRepository.quickEntry(
                title = title,
                amount = amount,
                category = category,
                isBusiness = isBusiness,
                date = date,
                location = location,
                fileBytes = fileBytes,
                fileName = fileName,
                mimeType = mimeType
            ).onSuccess {
                _actionResult.value = Result.success("Eintrag erfolgreich hinzugefügt.")
                _isUploading.value = false
                loadDashboardData()
            }.onFailure { error ->
                _actionResult.value = Result.failure(error)
                _isUploading.value = false
            }
        }
    }

    fun loadDashboardData() {
        viewModelScope.launch {
            _dashboardState.value = DashboardState.Loading
            try {
                var kpis: FinanceKpis? = null
                var routines = emptyList<ManagementDayRoutine>()
                var tasks = emptyList<ManagementTask>()
                var events = emptyList<CalendarEvent>()

                // Fetch Finance KPIs
                financeRepository.getKpis().onSuccess { kpis = it }
                
                // Fetch Routines
                organizerRepository.getRoutines()
                    .onSuccess { 
                        routines = it 
                        android.util.Log.d("ZentrumViewModel", "Routines loaded successfully: ${it.size} items")
                    }
                    .onFailure {
                        android.util.Log.e("ZentrumViewModel", "Routines load failed", it)
                    }

                // Fetch Tasks
                organizerRepository.getTasks().onSuccess { tasks = it }

                // Fetch Calendar Events
                organizerRepository.getCalendarEvents().onSuccess { rawEvents ->
                    val now = java.util.Date()
                    events = rawEvents.filter { event ->
                        val startD = parseEventDate(event.start)
                        startD != null && startD.time >= now.time
                    }
                }

                _dashboardState.value = DashboardState.Success(
                    kpis = kpis,
                    routines = routines,
                    tasks = tasks,
                    events = events
                )
            } catch (e: Exception) {
                android.util.Log.e("ZentrumViewModel", "Exception in loadDashboardData", e)
                _dashboardState.value = DashboardState.Error(
                    e.localizedMessage ?: "Fehler beim Laden des Zentrums."
                )
            }
        }
    }

    fun toggleTask(taskId: String) {
        viewModelScope.launch {
            organizerRepository.toggleTask(taskId).onSuccess {
                // Reload dashboard data to keep it fully synced
                loadDashboardData()
            }
        }
    }

    private fun parseEventDate(dateStr: String): java.util.Date? {
        if (dateStr.isBlank()) return null
        return try {
            if (dateStr.contains("T")) {
                try {
                    val odt = java.time.OffsetDateTime.parse(dateStr)
                    java.util.Date(odt.toInstant().toEpochMilli())
                } catch (e: Exception) {
                    try {
                        val ldt = java.time.LocalDateTime.parse(dateStr)
                        val zdt = ldt.atZone(java.time.ZoneId.systemDefault())
                        java.util.Date(zdt.toInstant().toEpochMilli())
                    } catch (e2: Exception) {
                        val sdf = java.text.SimpleDateFormat("yyyy-MM-dd'T'HH:mm:ss", java.util.Locale.US).apply {
                            timeZone = java.util.TimeZone.getTimeZone("UTC")
                        }
                        sdf.parse(dateStr)
                    }
                }
            } else {
                java.text.SimpleDateFormat("yyyy-MM-dd", java.util.Locale.US).parse(dateStr)
            }
        } catch (e: Exception) {
            null
        }
    }
}
