package de.meinseelenfunke.app.data.api

import com.google.gson.annotations.SerializedName
import okhttp3.MultipartBody
import retrofit2.http.Body
import retrofit2.http.DELETE
import retrofit2.http.Field
import retrofit2.http.FormUrlEncoded
import retrofit2.http.GET
import retrofit2.http.Multipart
import retrofit2.http.POST
import retrofit2.http.PUT
import retrofit2.http.Part
import retrofit2.http.Path
import retrofit2.http.Query

data class EmailAccount(
    val id: String,
    val name: String? = null,
    val email: String,
    val host: String? = null,
    @SerializedName("unread_count") val unreadCount: Int = 0,
    @SerializedName("imap_username") val imapUsername: String? = null,
    @SerializedName("smtp_username") val smtpUsername: String? = null,
    @SerializedName("imap_host") val imapHost: String? = null,
    @SerializedName("imap_port") val imapPort: Int? = null,
    @SerializedName("imap_encryption") val imapEncryption: String? = null,
    val signature: String? = null,
    @SerializedName("smtp_host") val smtpHost: String? = null,
    @SerializedName("smtp_port") val smtpPort: Int? = null,
    @SerializedName("smtp_encryption") val smtpEncryption: String? = null,
    @SerializedName("is_default") val isDefault: Boolean = false,
    @SerializedName("is_commercial") val isCommercial: Boolean = true,
    val status: String? = null,
    @SerializedName("total_unread") val totalUnread: Int = 0,
    val folders: Map<String, String>? = null
)

data class EmailMessage(
    val id: String,
    @SerializedName("mail_account_id") val accountId: String?,
    val folder: String?,
    @SerializedName("from_name") val fromName: String?,
    @SerializedName("from_email") val fromEmail: String?,
    @SerializedName("to_email") val toEmail: String?,
    val subject: String?,
    @SerializedName("body_plain") val body: String?,
    @SerializedName("body_html") val htmlBody: String?,
    @SerializedName("is_read") val isRead: Boolean = false,
    @SerializedName("received_at") val receivedAt: String?,
    @SerializedName("has_attachments") val hasAttachments: Boolean = false
) {
    val from: String
        get() = fromName?.takeIf { it.isNotBlank() } ?: fromEmail ?: ""

    val to: String
        get() = toEmail ?: ""
}


data class EmailAttachment(
    val path: String,
    val filename: String,
    val mime: String,
    val size: Long
)

data class SendEmailRequest(
    @SerializedName("account_id") val accountId: String? = null,
    val from: String,
    val to: String,
    val subject: String,
    val body: String,
    val attachments: List<EmailAttachment>? = null
)

data class EmailAccountResponse(val success: Boolean, val data: List<EmailAccount>)
data class SingleEmailAccountResponse(val success: Boolean, val data: EmailAccount)
data class EmailMessagesData(val data: List<EmailMessage>)
data class EmailMessagesResponse(val success: Boolean, val data: EmailMessagesData)
data class EmailMessageDetailResponse(val success: Boolean, val data: EmailMessage)
data class AttachmentUploadResponse(val success: Boolean, val data: EmailAttachment)

data class ManagementContact(
    val id: String,
    @SerializedName("first_name") val firstName: String,
    @SerializedName("last_name") val lastName: String?,
    val nickname: String?,
    val email: String?,
    val phone: String?
)

data class ContactsResponse(
    val success: Boolean,
    val data: List<ManagementContact>
)

interface EmailApi {
    @GET("funki/emails/accounts")
    suspend fun getAccounts(): EmailAccountResponse

    @FormUrlEncoded
    @POST("funki/emails/accounts")
    suspend fun saveAccount(
        @Field("id") id: String?,
        @Field("name") name: String,
        @Field("email") email: String,
        @Field("password") password: String?,
        @Field("imap_host") imapHost: String,
        @Field("imap_port") imapPort: Int,
        @Field("imap_encryption") imapEncryption: String?,
        @Field("imap_username") imapUsername: String?,
        @Field("smtp_host") smtpHost: String,
        @Field("smtp_port") smtpPort: Int,
        @Field("smtp_encryption") smtpEncryption: String?,
        @Field("smtp_username") smtpUsername: String?,
        @Field("signature") signature: String?,
        @Field("is_default") isDefault: Int,
        @Field("is_commercial") isCommercial: Int
    ): SingleEmailAccountResponse

    @DELETE("funki/emails/accounts/{id}")
    suspend fun deleteAccount(@Path("id") id: String): SimpleSuccessResponse

    @GET("funki/contacts")
    suspend fun getContacts(): ContactsResponse

    @GET("funki/emails/messages")
    suspend fun getMessages(
        @Query("folder") folder: String?,
        @Query("account_id") accountId: String?,
        @Query("search") search: String?,
        @Query("unread_only") unreadOnly: Boolean?
    ): EmailMessagesResponse

    @GET("funki/emails/messages/{id}")
    suspend fun getMessageDetails(@Path("id") id: String): EmailMessageDetailResponse

    @POST("funki/emails/send")
    suspend fun sendEmail(
        @Body request: SendEmailRequest
    ): SimpleSuccessResponse

    @Multipart
    @POST("funki/emails/attachments")
    suspend fun uploadAttachment(
        @Part file: MultipartBody.Part
    ): AttachmentUploadResponse

    @FormUrlEncoded
    @PUT("funki/emails/messages/{id}/move")
    suspend fun moveMessage(
        @Path("id") id: String,
        @Field("folder") folder: String
    ): SimpleSuccessResponse

    @FormUrlEncoded
    @PUT("funki/emails/messages/{id}/read")
    suspend fun markMessageRead(
        @Path("id") id: String,
        @Field("is_read") isRead: Boolean
    ): SimpleSuccessResponse

    @DELETE("funki/emails/messages/{id}")
    suspend fun deleteMessage(@Path("id") id: String): SimpleSuccessResponse

    @FormUrlEncoded
    @POST("funki/emails/folders")
    suspend fun createFolder(
        @Field("account_id") accountId: String,
        @Field("name") name: String
    ): SimpleSuccessResponse

    @DELETE("funki/emails/folders")
    suspend fun deleteFolder(
        @Query("account_id") accountId: String,
        @Query("name") name: String
    ): SimpleSuccessResponse
}


