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

    private val _isUpdatingStatus = MutableStateFlow(false)
    val isUpdatingStatus: StateFlow<Boolean> = _isUpdatingStatus.asStateFlow()

    init {
        loadOrders(status = null)
    }

    fun setStatusFilter(filter: String) {
        _statusFilter.value = filter
        val apiFilter = if (filter == "all") null else filter
        loadOrders(status = apiFilter)
    }

    fun loadOrders(status: String?, showLoading: Boolean = true) {
        if (showLoading) {
            _uiState.value = OrderUiState.Loading
        }
        viewModelScope.launch {
            repository.getOrders(status)
                .onSuccess { response ->
                    _orders.value = response.data
                    _uiState.value = OrderUiState.Success
                }
                .onFailure { error ->
                    _uiState.value = OrderUiState.Error(error.localizedMessage ?: "Fehler beim Laden der Bestellungen")
                }
        }
    }

    fun loadOrderDetails(id: Long) {
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

    fun updateOrderStatus(id: Long, newStatus: String) {
        _isUpdatingStatus.value = true
        viewModelScope.launch {
            repository.updateOrderStatus(id, newStatus)
                .onSuccess { response ->
                    _isUpdatingStatus.value = false
                    // Reload order details to refresh the detail view
                    loadOrderDetails(id)
                    // Reload order list to refresh list badges without showing full-screen loading
                    val currentFilter = if (_statusFilter.value == "all") null else _statusFilter.value
                    loadOrders(status = currentFilter, showLoading = false)
                }
                .onFailure { error ->
                    _isUpdatingStatus.value = false
                }
        }
    }
}
