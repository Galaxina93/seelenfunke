package de.meinseelenfunke.app.ui.screens

import androidx.lifecycle.ViewModel
import androidx.lifecycle.viewModelScope
import de.meinseelenfunke.app.data.api.OrderSummary
import de.meinseelenfunke.app.data.api.OrderDetail
import de.meinseelenfunke.app.di.ServiceLocator
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.asStateFlow
import kotlinx.coroutines.launch

sealed class OrderUiState {
    object Loading : OrderUiState()
    object Success : OrderUiState()
    data class Error(val message: String) : OrderUiState()
}

class OrderViewModel : ViewModel() {

    private val repository = ServiceLocator.orderRepository

    private val _uiState = MutableStateFlow<OrderUiState>(OrderUiState.Loading)
    val uiState: StateFlow<OrderUiState> = _uiState.asStateFlow()

    private val _orders = MutableStateFlow<List<OrderSummary>>(emptyList())
    val orders: StateFlow<List<OrderSummary>> = _orders.asStateFlow()

    private val _selectedOrder = MutableStateFlow<OrderDetail?>(null)
    val selectedOrder: StateFlow<OrderDetail?> = _selectedOrder.asStateFlow()

    private val _statusFilter = MutableStateFlow<String>("all")
    val statusFilter: StateFlow<String> = _statusFilter.asStateFlow()

    private val _searchQuery = MutableStateFlow<String>("")
    val searchQuery: StateFlow<String> = _searchQuery.asStateFlow()

    private val _priorityOrder = MutableStateFlow<OrderSummary?>(null)
    val priorityOrder: StateFlow<OrderSummary?> = _priorityOrder.asStateFlow()

    private val _isUpdatingStatus = MutableStateFlow(false)
    val isUpdatingStatus: StateFlow<Boolean> = _isUpdatingStatus.asStateFlow()

    private val _isGeneratingDhlLabel = MutableStateFlow(false)
    val isGeneratingDhlLabel: StateFlow<Boolean> = _isGeneratingDhlLabel.asStateFlow()

    private val _dhlLabelError = MutableStateFlow<String?>(null)
    val dhlLabelError: StateFlow<String?> = _dhlLabelError.asStateFlow()

    private val _dhlLabelSuccessMessage = MutableStateFlow<String?>(null)
    val dhlLabelSuccessMessage: StateFlow<String?> = _dhlLabelSuccessMessage.asStateFlow()

    init {
        loadOrders(status = null, search = null)
    }

    fun setStatusFilter(filter: String) {
        _statusFilter.value = filter
        val apiFilter = if (filter == "all") null else filter
        val currentSearch = if (_searchQuery.value.isEmpty()) null else _searchQuery.value
        loadOrders(status = apiFilter, search = currentSearch)
    }

    fun setSearchQuery(query: String) {
        _searchQuery.value = query
        val apiFilter = if (_statusFilter.value == "all") null else _statusFilter.value
        val apiSearch = if (query.isEmpty()) null else query
        loadOrders(status = apiFilter, search = apiSearch, showLoading = false)
    }

    fun loadOrders(status: String?, search: String? = null, showLoading: Boolean = true) {
        if (showLoading) {
            _uiState.value = OrderUiState.Loading
        }
        viewModelScope.launch {
            repository.getOrders(status, search)
                .onSuccess { response ->
                    _orders.value = response.data
                    _priorityOrder.value = response.priority_order
                    _uiState.value = OrderUiState.Success
                }
                .onFailure { error ->
                    _uiState.value = OrderUiState.Error(error.localizedMessage ?: "Fehler beim Laden der Bestellungen")
                }
        }
    }

    fun loadOrderDetails(id: String) {
        viewModelScope.launch {
            repository.getOrderDetails(id)
                .onSuccess { details ->
                    _selectedOrder.value = details
                }
                .onFailure { error ->
                    // Handle detail loading error
                }
        }
    }

    fun clearSelectedOrder() {
        _selectedOrder.value = null
    }

    fun updateOrderStatus(id: String, newStatus: String) {
        _isUpdatingStatus.value = true
        viewModelScope.launch {
            repository.updateOrderStatus(id, newStatus)
                .onSuccess { response ->
                    _isUpdatingStatus.value = false
                    // Reload order details to refresh the detail view
                    loadOrderDetails(id)
                    // Reload order list to refresh list badges without showing full-screen loading
                    val currentFilter = if (_statusFilter.value == "all") null else _statusFilter.value
                    val currentSearch = if (_searchQuery.value.isEmpty()) null else _searchQuery.value
                    loadOrders(status = currentFilter, search = currentSearch, showLoading = false)
                }
                .onFailure { error ->
                    _isUpdatingStatus.value = false
                }
        }
    }

    fun createDhlLabel(id: String, packageCount: Int, weightPerPackage: Double) {
        _isGeneratingDhlLabel.value = true
        _dhlLabelError.value = null
        _dhlLabelSuccessMessage.value = null
        viewModelScope.launch {
            repository.createDhlLabel(id, packageCount, weightPerPackage)
                .onSuccess { response ->
                    _isGeneratingDhlLabel.value = false
                    if (response.success) {
                        _dhlLabelSuccessMessage.value = response.message
                        // Refresh details
                        loadOrderDetails(id)
                        // Refresh orders list
                        val currentFilter = if (_statusFilter.value == "all") null else _statusFilter.value
                        val currentSearch = if (_searchQuery.value.isEmpty()) null else _searchQuery.value
                        loadOrders(status = currentFilter, search = currentSearch, showLoading = false)
                    } else {
                        _dhlLabelError.value = response.message
                    }
                }
                .onFailure { error ->
                    _isGeneratingDhlLabel.value = false
                    _dhlLabelError.value = error.localizedMessage ?: "Fehler beim Erstellen des DHL-Labels"
                }
        }
    }

    fun clearDhlLabelState() {
        _dhlLabelError.value = null
        _dhlLabelSuccessMessage.value = null
    }
}
