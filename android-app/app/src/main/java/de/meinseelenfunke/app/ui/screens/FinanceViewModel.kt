package de.meinseelenfunke.app.ui.screens

import androidx.lifecycle.ViewModel
import androidx.lifecycle.viewModelScope
import de.meinseelenfunke.app.data.api.FinanceGroup
import de.meinseelenfunke.app.data.api.FinanceKpis
import de.meinseelenfunke.app.data.api.FinanceSpecialIssue
import de.meinseelenfunke.app.data.repository.FinanceRepository
import de.meinseelenfunke.app.di.ServiceLocator
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.asStateFlow
import kotlinx.coroutines.launch

sealed class FinanceUiState {
    object Loading : FinanceUiState()
    object Success : FinanceUiState()
    data class Error(val message: String) : FinanceUiState()
}

class FinanceViewModel : ViewModel() {

    private val financeRepository = ServiceLocator.financeRepository

    private val _uiState = MutableStateFlow<FinanceUiState>(FinanceUiState.Loading)
    val uiState: StateFlow<FinanceUiState> = _uiState.asStateFlow()

    private val _kpis = MutableStateFlow<FinanceKpis?>(null)
    val kpis: StateFlow<FinanceKpis?> = _kpis.asStateFlow()

    private val _variableItems = MutableStateFlow<List<FinanceSpecialIssue>>(emptyList())
    val variableItems: StateFlow<List<FinanceSpecialIssue>> = _variableItems.asStateFlow()

    private val _fixedGroups = MutableStateFlow<List<FinanceGroup>>(emptyList())
    val fixedGroups: StateFlow<List<FinanceGroup>> = _fixedGroups.asStateFlow()

    companion object {
        val DEFAULT_CATEGORIES = listOf(
            "Fitness", "Werbung & Marketing", "Software & Lizenzen", "Verpackungen",
            "Rohmaterial", "Wareneinkauf", "Arbeitsmaterial", "Sprit", "Haushalt",
            "Kind", "Steuer", "Schmutzwasser", "Nahrungsmittel", "Feiertage",
            "Sonstiges", "Auto", "Technik", "Friseur", "Geschenk", "Kleidung",
            "Kosmetik", "Gesundheit", "Freizeit", "Glücksspiel"
        )
    }

    private val _categories = MutableStateFlow<List<String>>(DEFAULT_CATEGORIES)
    val categories: StateFlow<List<String>> = _categories.asStateFlow()

    private val _isUploading = MutableStateFlow(false)
    val isUploading: StateFlow<Boolean> = _isUploading.asStateFlow()

    private val _actionResult = MutableStateFlow<Result<String>?>(null)
    val actionResult: StateFlow<Result<String>?> = _actionResult.asStateFlow()

    init {
        loadAllFinanceData()
    }

    fun clearActionResult() {
        _actionResult.value = null
    }

    fun loadAllFinanceData(showLoading: Boolean = _uiState.value !is FinanceUiState.Success) {
        viewModelScope.launch {
            if (showLoading) {
                _uiState.value = FinanceUiState.Loading
            }
            try {
                var errorMsg: String? = null

                // Fetch KPIs
                financeRepository.getKpis()
                    .onSuccess { _kpis.value = it }
                    .onFailure { 
                        android.util.Log.e("FinanceViewModel", "KPIs load failed", it)
                        errorMsg = "KPIs konnten nicht geladen werden: " + it.localizedMessage
                    }

                // Fetch categories
                financeRepository.getCategories()
                    .onSuccess { list -> _categories.value = list.ifEmpty { DEFAULT_CATEGORIES } }
                    .onFailure {
                        android.util.Log.e("FinanceViewModel", "Categories load failed", it)
                        _categories.value = DEFAULT_CATEGORIES
                    }

                // Fetch variable cost entries
                financeRepository.getVariableItems()
                    .onSuccess { list ->
                        _variableItems.value = list.sortedWith(
                            compareByDescending { parseDate(it.execution_date) }
                        )
                    }
                    .onFailure { 
                        android.util.Log.e("FinanceViewModel", "Variable items load failed", it)
                        if (errorMsg == null) errorMsg = "Variable Kosten konnten nicht geladen werden: " + it.localizedMessage
                    }

                // Fetch fixed cost groups
                financeRepository.getFixedGroups()
                    .onSuccess { _fixedGroups.value = it }
                    .onFailure { 
                        android.util.Log.e("FinanceViewModel", "Fixed groups load failed", it)
                        if (errorMsg == null) errorMsg = "Fixkosten konnten nicht geladen werden: " + it.localizedMessage
                    }

                if (errorMsg != null) {
                    _uiState.value = FinanceUiState.Error(errorMsg!!)
                } else {
                    _uiState.value = FinanceUiState.Success
                }
            } catch (e: Exception) {
                _uiState.value = FinanceUiState.Error(
                    e.localizedMessage ?: "Fehler beim Laden der Finanzdaten."
                )
            }
        }
    }

    fun deleteVariableEntry(id: String) {
        viewModelScope.launch {
            financeRepository.deleteVariableItem(id)
                .onSuccess {
                    _actionResult.value = Result.success("Eintrag erfolgreich gelöscht.")
                    loadAllFinanceData()
                }
                .onFailure { error ->
                    _actionResult.value = Result.failure(error)
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
                loadAllFinanceData()
            }.onFailure { error ->
                _actionResult.value = Result.failure(error)
                _isUploading.value = false
            }
        }
    }

    fun updateVariableEntry(
        id: String,
        title: String,
        amount: Double,
        category: String,
        executionDate: String,
        isBusiness: Boolean,
        location: String?
    ) {
        if (title.isBlank() || amount == 0.0) {
            _actionResult.value = Result.failure(Exception("Titel und valider Betrag sind erforderlich."))
            return
        }
        viewModelScope.launch {
            financeRepository.updateVariableItem(id, title, amount, category, executionDate, isBusiness, location)
                .onSuccess {
                    _actionResult.value = Result.success("Eintrag erfolgreich aktualisiert.")
                    loadAllFinanceData()
                }
                .onFailure { error ->
                    _actionResult.value = Result.failure(error)
                }
        }
    }

    fun createFixedEntry(
        financeGroupId: String,
        name: String,
        amount: Double,
        intervalMonths: Int,
        firstPaymentDate: String,
        description: String?,
        isBusiness: Boolean
    ) {
        if (name.isBlank() || amount == 0.0) {
            _actionResult.value = Result.failure(Exception("Name und valider Betrag sind erforderlich."))
            return
        }
        viewModelScope.launch {
            financeRepository.createFixedItem(financeGroupId, name, amount, intervalMonths, firstPaymentDate, description, isBusiness)
                .onSuccess {
                    _actionResult.value = Result.success("Fixkostenstelle erfolgreich erstellt.")
                    loadAllFinanceData()
                }
                .onFailure { error ->
                    _actionResult.value = Result.failure(error)
                }
        }
    }

    fun updateFixedEntry(
        id: String,
        financeGroupId: String,
        name: String,
        amount: Double,
        intervalMonths: Int,
        firstPaymentDate: String,
        description: String?,
        isBusiness: Boolean
    ) {
        if (name.isBlank() || amount == 0.0) {
            _actionResult.value = Result.failure(Exception("Name und valider Betrag sind erforderlich."))
            return
        }
        viewModelScope.launch {
            financeRepository.updateFixedItem(id, financeGroupId, name, amount, intervalMonths, firstPaymentDate, description, isBusiness)
                .onSuccess {
                    _actionResult.value = Result.success("Fixkostenstelle erfolgreich aktualisiert.")
                    loadAllFinanceData()
                }
                .onFailure { error ->
                    _actionResult.value = Result.failure(error)
                }
        }
    }

    fun deleteFixedEntry(id: String) {
        viewModelScope.launch {
            financeRepository.deleteFixedItem(id)
                .onSuccess {
                    _actionResult.value = Result.success("Fixkostenstelle erfolgreich gelöscht.")
                    loadAllFinanceData()
                }
                .onFailure { error ->
                    _actionResult.value = Result.failure(error)
                }
        }
    }

    private fun parseDate(dateStr: String?): java.util.Date {
        if (dateStr.isNullOrBlank()) return java.util.Date(0)
        try {
            if (dateStr.contains("T")) {
                val isoParser = java.text.SimpleDateFormat("yyyy-MM-dd'T'HH:mm:ss", java.util.Locale.US).apply {
                    timeZone = java.util.TimeZone.getTimeZone("UTC")
                }
                val d = isoParser.parse(dateStr)
                if (d != null) return d
            }
            val simpleParser = java.text.SimpleDateFormat("yyyy-MM-dd", java.util.Locale.US)
            val d = simpleParser.parse(dateStr)
            if (d != null) return d
        } catch (e: Exception) {
            e.printStackTrace()
        }
        return java.util.Date(0)
    }
}
