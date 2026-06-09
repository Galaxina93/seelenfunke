package de.meinseelenfunke.app.data.repository

import de.meinseelenfunke.app.data.api.OrderApi
import de.meinseelenfunke.app.data.api.OrderListResponse
import de.meinseelenfunke.app.data.api.OrderDetail
import de.meinseelenfunke.app.data.api.OrderStatusRequest
import de.meinseelenfunke.app.data.api.OrderStatusResponse
import de.meinseelenfunke.app.di.ServiceLocator
import de.meinseelenfunke.app.util.AppLogger
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.withContext

class OrderRepository(private val serviceLocator: ServiceLocator) {
    suspend fun getOrders(status: String?, search: String? = null, page: Int? = null): Result<OrderListResponse> = withContext(Dispatchers.IO) {
        try {
            Result.success(serviceLocator.getOrderApi().getOrders(status, search, page))
        } catch (e: Exception) {
            AppLogger.error(serviceLocator.context, "OrderRepo", "getOrders failed: status=$status, search=$search, page=$page", e)
            Result.failure(e)
        }
    }

    suspend fun getOrderDetails(id: String): Result<OrderDetail> = withContext(Dispatchers.IO) {
        try {
            Result.success(serviceLocator.getOrderApi().getOrderDetails(id))
        } catch (e: Exception) {
            AppLogger.error(serviceLocator.context, "OrderRepo", "getOrderDetails failed: id=$id", e)
            Result.failure(e)
        }
    }

    suspend fun updateOrderStatus(id: String, status: String): Result<OrderStatusResponse> = withContext(Dispatchers.IO) {
        try {
            Result.success(serviceLocator.getOrderApi().updateOrderStatus(id, OrderStatusRequest(status)))
        } catch (e: Exception) {
            AppLogger.error(serviceLocator.context, "OrderRepo", "updateOrderStatus failed: id=$id, status=$status", e)
            Result.failure(e)
        }
    }

    suspend fun createDhlLabel(id: String, packageCount: Int, weightPerPackage: Double): Result<de.meinseelenfunke.app.data.api.DhlLabelResponse> = withContext(Dispatchers.IO) {
        try {
            Result.success(serviceLocator.getOrderApi().createDhlLabel(id, de.meinseelenfunke.app.data.api.DhlLabelRequest(packageCount, weightPerPackage)))
        } catch (e: Exception) {
            AppLogger.error(serviceLocator.context, "OrderRepo", "createDhlLabel failed: id=$id, packageCount=$packageCount, weight=$weightPerPackage", e)
            Result.failure(e)
        }
    }
}

