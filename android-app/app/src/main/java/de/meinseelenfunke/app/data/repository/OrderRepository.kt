package de.meinseelenfunke.app.data.repository

import de.meinseelenfunke.app.data.api.OrderApi
import de.meinseelenfunke.app.data.api.OrderListResponse
import de.meinseelenfunke.app.data.api.OrderDetail
import de.meinseelenfunke.app.data.api.OrderStatusRequest
import de.meinseelenfunke.app.data.api.OrderStatusResponse
import de.meinseelenfunke.app.di.ServiceLocator
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.withContext

class OrderRepository(private val serviceLocator: ServiceLocator) {
    suspend fun getOrders(status: String?, page: Int? = null): Result<OrderListResponse> = withContext(Dispatchers.IO) {
        try {
            Result.success(serviceLocator.getOrderApi().getOrders(status, page))
        } catch (e: Exception) {
            Result.failure(e)
        }
    }

    suspend fun getOrderDetails(id: Long): Result<OrderDetail> = withContext(Dispatchers.IO) {
        try {
            Result.success(serviceLocator.getOrderApi().getOrderDetails(id))
        } catch (e: Exception) {
            Result.failure(e)
        }
    }

    suspend fun updateOrderStatus(id: Long, status: String): Result<OrderStatusResponse> = withContext(Dispatchers.IO) {
        try {
            Result.success(serviceLocator.getOrderApi().updateOrderStatus(id, OrderStatusRequest(status)))
        } catch (e: Exception) {
            Result.failure(e)
        }
    }
}
