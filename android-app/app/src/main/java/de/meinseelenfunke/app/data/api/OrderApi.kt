package de.meinseelenfunke.app.data.api

import retrofit2.http.Body
import retrofit2.http.GET
import retrofit2.http.POST
import retrofit2.http.Path
import retrofit2.http.Query

data class OrderSummary(
    val id: String,
    val order_number: String,
    val email: String,
    val customer_name: String,
    val status: String,
    val status_color: String?,
    val payment_status: String,
    val payment_status_color: String?,
    val payment_method: String,
    val total_price: Long, // in cents
    val created_at: String,
    val item_count: Int
)

data class OrderListResponse(
    val data: List<OrderSummary>,
    val current_page: Int,
    val last_page: Int,
    val total: Int
)

data class OrderItem(
    val id: String,
    val product_name: String,
    val quantity: Int,
    val unit_price: Long, // in cents
    val total_price: Long, // in cents
    val configuration: Map<String, Any>?
)

data class OrderDetail(
    val id: String,
    val order_number: String,
    val email: String,
    val customer_name: String,
    val status: String,
    val status_color: String?,
    val payment_status: String,
    val payment_status_color: String?,
    val payment_method: String,
    val billing_address: Map<String, Any>?,
    val shipping_address: Map<String, Any>?,
    val volume_discount: Long?,
    val coupon_code: String?,
    val discount_amount: Long?,
    val subtotal_price: Long?,
    val tax_amount: Long?,
    val shipping_price: Long?,
    val total_price: Long,
    val notes: String?,
    val is_express: Boolean?,
    val express_price: Long?,
    val created_at: String,
    val items: List<OrderItem>,
    val tracking_number: String?
)

data class OrderStatusRequest(
    val status: String
)

data class OrderStatusResponse(
    val success: Boolean,
    val status: String,
    val status_color: String?
)

interface OrderApi {
    @GET("shop/orders")
    suspend fun getOrders(
        @Query("status") status: String?,
        @Query("page") page: Int? = null
    ): OrderListResponse

    @GET("shop/orders/{id}")
    suspend fun getOrderDetails(@Path("id") id: String): OrderDetail

    @POST("shop/orders/{id}/status")
    suspend fun updateOrderStatus(
        @Path("id") id: String,
        @Body request: OrderStatusRequest
    ): OrderStatusResponse
}
