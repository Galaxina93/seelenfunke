package de.meinseelenfunke.app.data.api

import com.google.gson.annotations.SerializedName
import okhttp3.ResponseBody
import retrofit2.http.Body
import retrofit2.http.DELETE
import retrofit2.http.GET
import retrofit2.http.POST
import retrofit2.http.Path
import retrofit2.http.Query

data class AgDirectCountsDto(
    @SerializedName("pending") val pending: Int = 0,
    @SerializedName("delivered") val delivered: Int = 0,
    @SerializedName("awaiting_go") val awaiting_go: Int = 0,
    @SerializedName("open") val open: Int = 0,
    @SerializedName("completed") val completed: Int = 0
)

data class AgDirectInboxItemDto(
    @SerializedName("id") val id: String = "",
    @SerializedName("client_uuid") val client_uuid: String? = null,
    @SerializedName("title") val title: String = "",
    @SerializedName("body") val body: String? = "",
    @SerializedName("checklist") val checklist: List<String>? = emptyList(),
    @SerializedName("execution_mode") val execution_mode: String? = "concept",
    @SerializedName("channel") val channel: String? = "leitung",
    @SerializedName("status") val status: String? = "pending",
    @SerializedName("has_attachment") val has_attachment: Boolean = false,
    @SerializedName("attachment_name") val attachment_name: String? = null,
    @SerializedName("attachment_mime") val attachment_mime: String? = null,
    @SerializedName("ag_response_summary") val ag_response_summary: String? = null,
    @SerializedName("pending_question") val pending_question: String? = null,
    @SerializedName("awaiting_go_at") val awaiting_go_at: String? = null,
    @SerializedName("created_at") val created_at: String? = null,
    @SerializedName("delivered_at") val delivered_at: String? = null,
    @SerializedName("completed_at") val completed_at: String? = null
)

data class AgDirectInboxListResponse(
    @SerializedName("success") val success: Boolean = false,
    @SerializedName("counts") val counts: AgDirectCountsDto? = null,
    @SerializedName("items") val items: List<AgDirectInboxItemDto>? = emptyList()
)

data class AgDirectCreateRequest(
    @SerializedName("client_uuid") val client_uuid: String,
    @SerializedName("title") val title: String,
    @SerializedName("body") val body: String = "",
    @SerializedName("checklist") val checklist: List<String> = emptyList(),
    @SerializedName("execution_mode") val execution_mode: String = "concept",
    @SerializedName("channel") val channel: String = "leitung",
    @SerializedName("attachment_base64") val attachment_base64: String? = null,
    @SerializedName("attachment_mime") val attachment_mime: String? = null,
    @SerializedName("attachment_name") val attachment_name: String? = null
)

data class AgDirectCreateResponse(
    @SerializedName("success") val success: Boolean = false,
    @SerializedName("data") val data: AgDirectInboxItemDto? = null
)

data class AgDirectDeleteResponse(
    @SerializedName("success") val success: Boolean = false
)

data class AgDirectResolveRequest(
    @SerializedName("decision") val decision: String = "approved",
    @SerializedName("answer_text") val answer_text: String = ""
)

data class AgDirectResolveResponse(
    @SerializedName("success") val success: Boolean = false,
    @SerializedName("decision") val decision: String? = null,
    @SerializedName("resolved_item") val resolved_item: AgDirectInboxItemDto? = null,
    @SerializedName("followup_item") val followup_item: AgDirectInboxItemDto? = null,
    @SerializedName("counts") val counts: AgDirectCountsDto? = null
)

interface AgDirectApi {
    @GET("system/ag-direct-inbox")
    suspend fun listInbox(@Query("limit") limit: Int = 30): AgDirectInboxListResponse

    @POST("system/ag-direct-inbox")
    suspend fun createItem(@Body request: AgDirectCreateRequest): AgDirectCreateResponse

    @POST("funkenwerk/ag-inbox/{id}/resolve")
    suspend fun resolveBlockade(
        @Path("id") id: String,
        @Body request: AgDirectResolveRequest
    ): AgDirectResolveResponse

    @DELETE("system/ag-direct-inbox/{id}")
    suspend fun deleteItem(@Path("id") id: String): AgDirectDeleteResponse

    @GET("system/ag-direct-inbox/{id}/attachment")
    suspend fun getAttachment(@Path("id") id: String): ResponseBody
}
