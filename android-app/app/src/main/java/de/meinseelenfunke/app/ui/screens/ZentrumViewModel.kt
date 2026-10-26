package de.meinseelenfunke.app.ui.screens

import android.content.Context
import androidx.lifecycle.ViewModel
import androidx.lifecycle.viewModelScope
import de.meinseelenfunke.app.data.api.CalendarEvent
import de.meinseelenfunke.app.data.api.FinanceKpis
import de.meinseelenfunke.app.data.api.ManagementDayRoutine
import de.meinseelenfunke.app.data.api.ManagementTask
import de.meinseelenfunke.app.data.api.OrderSummary
import de.meinseelenfunke.app.data.api.UltimateCommandResponse
import de.meinseelenfunke.app.di.ServiceLocator
import kotlinx.coroutines.async
import kotlinx.coroutines.coroutineScope
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.asStateFlow
import kotlinx.coroutines.launch
import java.text.SimpleDateFormat
import java.util.Date
import java.util.Locale

sealed class ZentrumUiState {
    object Loading : ZentrumUiState()
    object Success : ZentrumUiState()
    data class Error(val message: String) : ZentrumUiState()
}

class ZentrumViewModel : ViewModel() {

    private val financeRepository = ServiceLocator.financeRepository
    private val organizerRepository = ServiceLocator.organizerRepository
    private val orderRepository = ServiceLocator.orderRepository
    private val aiRepository = ServiceLocator.aiRepository

    private val _uiState = MutableStateFlow<ZentrumUiState>(ZentrumUiState.Loading)
    val uiState: StateFlow<ZentrumUiState> = _uiState.asStateFlow()

    private val _ultimateCommand = MutableStateFlow<UltimateCommandResponse?>(null)
    val ultimateCommand: StateFlow<UltimateCommandResponse?> = _ultimateCommand.asStateFlow()

    private val _kpis = MutableStateFlow<FinanceKpis?>(null)
    val kpis: StateFlow<FinanceKpis?> = _kpis.asStateFlow()

    private val _routines = MutableStateFlow<List<ManagementDayRoutine>>(emptyList())
    val routines: StateFlow<List<ManagementDayRoutine>> = _routines.asStateFlow()

    private val _tasks = MutableStateFlow<List<ManagementTask>>(emptyList())
    val tasks: StateFlow<List<ManagementTask>> = _tasks.asStateFlow()

    private val _events = MutableStateFlow<List<CalendarEvent>>(emptyList())
    val events: StateFlow<List<CalendarEvent>> = _events.asStateFlow()

    private val _orders = MutableStateFlow<List<OrderSummary>>(emptyList())
    val orders: StateFlow<List<OrderSummary>> = _orders.asStateFlow()

    private val _totalOrdersCount = MutableStateFlow(0)
    val totalOrdersCount: StateFlow<Int> = _totalOrdersCount.asStateFlow()

    private val _isUploading = MutableStateFlow(false)
    val isUploading: StateFlow<Boolean> = _isUploading.asStateFlow()

    private val _isAiScanning = MutableStateFlow(false)
    val isAiScanning: StateFlow<Boolean> = _isAiScanning.asStateFlow()

    private val _actionResult = MutableStateFlow<Result<String>?>(null)
    val actionResult: StateFlow<Result<String>?> = _actionResult.asStateFlow()

    init {
        loadDashboardData()
    }

    fun clearActionResult() {
        _actionResult.value = null
    }

    fun loadDashboardData(showLoading: Boolean = _uiState.value !is ZentrumUiState.Success) {
        viewModelScope.launch {
            if (showLoading) {
                _uiState.value = ZentrumUiState.Loading
            }
            try {
                val isAdmin = ServiceLocator.userTypeState.value == "admin" || ServiceLocator.getUserType() == "admin"

                coroutineScope {
                    val commandDeferred = async { aiRepository.getUltimateCommand() }
                    val kpisDeferred = async { financeRepository.getKpis() }
                    val routinesDeferred = async { organizerRepository.getRoutines() }
                    val tasksDeferred = async { organizerRepository.getTasks() }
                    val eventsDeferred = async { organizerRepository.getCalendarEvents() }
                    val ordersDeferred = if (isAdmin) {
                        async { orderRepository.getOrders(status = null, search = null, page = 1) }
                    } else null

                    commandDeferred.await().onSuccess { _ultimateCommand.value = it }
                    kpisDeferred.await().onSuccess { _kpis.value = it }
                    routinesDeferred.await().onSuccess { _routines.value = it }
                    tasksDeferred.await().onSuccess { _tasks.value = it }
                    eventsDeferred.await().onSuccess { rawEvents ->
                        val now = Date()
                        _events.value = rawEvents.filter { event ->
                            val startD = parseEventDate(event.start)
                            startD != null && startD.time >= now.time
                        }
                    }
                    ordersDeferred?.await()?.onSuccess { response ->
                        _orders.value = response.data
                        _totalOrdersCount.value = response.total
                    }
                }

                _uiState.value = ZentrumUiState.Success
            } catch (e: Exception) {
                _uiState.value = ZentrumUiState.Error(
                    e.localizedMessage ?: "Ein unerwarteter Fehler ist aufgetreten."
                )
            }
        }
    }

    fun toggleTask(taskId: String) {
        val currentList = _tasks.value
        val target = currentList.find { it.id == taskId }
        if (target != null) {
            val willComplete = !target.is_completed
            _tasks.value = currentList.map {
                if (it.id == taskId) it.copy(is_completed = willComplete) else it
            }
        }
        viewModelScope.launch {
            organizerRepository.toggleTask(taskId)
                .onSuccess { updated ->
                    _tasks.value = _tasks.value.map { if (it.id == taskId) updated else it }
                    aiRepository.getUltimateCommand().onSuccess { _ultimateCommand.value = it }
                }
                .onFailure {
                    loadDashboardData(showLoading = false)
                }
        }
    }

    fun scanReceipt(
        bytes: ByteArray,
        fileName: String,
        mimeType: String,
        autoSave: Boolean,
        onScanComplete: (de.meinseelenfunke.app.data.api.ReceiptAiScanData?) -> Unit
    ) {
        viewModelScope.launch {
            _isAiScanning.value = true
            financeRepository.scanReceipt(bytes, fileName, mimeType, autoSave)
                .onSuccess { response ->
                    _isAiScanning.value = false
                    if (response.auto_saved) {
                        _actionResult.value = Result.success(response.message ?: "Beleg erfolgreich erfasst & gebucht!")
                        loadDashboardData(showLoading = false)
                        onScanComplete(null)
                    } else {
                        onScanComplete(response.data)
                    }
                }
                .onFailure { error ->
                    _isAiScanning.value = false
                    _actionResult.value = Result.failure(error)
                    onScanComplete(null)
                }
        }
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
                loadDashboardData(showLoading = false)
            }.onFailure { error ->
                _actionResult.value = Result.failure(error)
                _isUploading.value = false
            }
        }
    }

    private fun parseEventDate(dateStr: String): Date? {
        if (dateStr.isBlank()) return null
        return try {
            if (dateStr.contains("T")) {
                try {
                    val odt = java.time.OffsetDateTime.parse(dateStr)
                    Date(odt.toInstant().toEpochMilli())
                } catch (e: Exception) {
                    try {
                        val ldt = java.time.LocalDateTime.parse(dateStr)
                        val zdt = ldt.atZone(java.time.ZoneId.systemDefault())
                        Date(zdt.toInstant().toEpochMilli())
                    } catch (e2: Exception) {
                        val sdf = SimpleDateFormat("yyyy-MM-dd'T'HH:mm:ss", Locale.US).apply {
                            timeZone = java.util.TimeZone.getTimeZone("UTC")
                        }
                        sdf.parse(dateStr)
                    }
                }
            } else {
                SimpleDateFormat("yyyy-MM-dd", Locale.US).parse(dateStr)
            }
        } catch (e: Exception) {
            null
        }
    }
}
