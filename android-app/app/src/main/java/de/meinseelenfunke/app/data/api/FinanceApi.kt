package de.meinseelenfunke.app.data.api

import com.google.gson.annotations.SerializedName
import okhttp3.MultipartBody
import okhttp3.RequestBody
import retrofit2.http.Body
import retrofit2.http.DELETE
import retrofit2.http.GET
import retrofit2.http.Multipart
import retrofit2.http.POST
import retrofit2.http.PUT
import retrofit2.http.Part
import retrofit2.http.Path
import retrofit2.http.Query

data class FinanceKpis(
    val available: Double,
    val shop_revenue: Double,
    val fixed_expenses: Double,
    val special_expenses: Double,
    val month_label: String
)

data class FinanceSpecialIssue(
    val id: String,
    val title: String,
    val amount: Double,
    val category: String?,
    val execution_date: String?,
    val is_business: Boolean,
    val location: String?,
    val file_paths: List<String>?
)

data class FinanceCostItem(
    val id: String,
    @SerializedName("accounting_group_id") val finance_group_id: String?,
    val name: String,
    val amount: Double,
    val interval_months: Int,
    val first_payment_date: String?,
    val description: String?,
    val is_business: Boolean,
    val contract_file_path: String?
)

data class FinanceGroup(
    val id: String,
    val name: String,
    val type: String,
    val position: Int,
    val items: List<FinanceCostItem>
)

data class SimpleSuccessResponse(
    val success: Boolean,
    val message: String? = null
)

data class UpdateVariableRequest(
    val title: String,
    val amount: Double,
    val category: String,
    val execution_date: String,
    val is_business: Boolean,
    val location: String? = null
)

data class FixedItemRequest(
    @SerializedName("finance_group_id") val finance_group_id: String,
    val name: String,
    val amount: Double,
    @SerializedName("interval_months") val interval_months: Int,
    @SerializedName("first_payment_date") val first_payment_date: String,
    val description: String?,
    @SerializedName("is_business") val is_business: Boolean
)

interface FinanceApi {
    @GET("funki/financials/kpis")
    suspend fun getKpis(): FinanceKpis

    @GET("funki/financials/categories")
    suspend fun getCategories(): List<String>

    @GET("funki/financials/variable")
    suspend fun getVariableItems(@Query("limit") limit: Int = 50): List<FinanceSpecialIssue>

    @Multipart
    @POST("funki/financials/quick-entry")
    suspend fun quickEntry(
        @Part("title") title: RequestBody,
        @Part("amount") amount: RequestBody,
        @Part("category") category: RequestBody?,
        @Part("is_business") isBusiness: RequestBody,
        @Part("date") date: RequestBody?,
        @Part("location") location: RequestBody?,
        @Part file: MultipartBody.Part?
    ): SimpleSuccessResponse

    @PUT("funki/financials/variable/{id}")
    suspend fun updateVariableItem(
        @Path("id") id: String,
        @Body body: UpdateVariableRequest
    ): SimpleSuccessResponse

    @DELETE("funki/financials/variable/{id}")
    suspend fun deleteVariableItem(@Path("id") id: String): SimpleSuccessResponse

    @GET("funki/financials/fixed")
    suspend fun getFixedGroups(): List<FinanceGroup>

    @POST("funki/financials/fixed-item")
    suspend fun createFixedItem(
        @Body body: FixedItemRequest
    ): SimpleSuccessResponse

    @PUT("funki/financials/fixed-item/{id}")
    suspend fun updateFixedItem(
        @Path("id") id: String,
        @Body body: FixedItemRequest
    ): SimpleSuccessResponse

    @DELETE("funki/financials/fixed-item/{id}")
    suspend fun deleteFixedItem(
        @Path("id") id: String
    ): SimpleSuccessResponse
}
