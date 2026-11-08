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
    val folders: Map<String, String>? = null,
    val counts: Map<String, Int>? = null,
    @SerializedName("total_counts") val totalCounts: Map<String, Int>? = null
)

data class EmailProcessedSummary(
    @SerializedName("total_count") val totalCount: Int = 0,
    @SerializedName("important_count") val importantCount: Int = 0,
    @SerializedName("normal_count") val normalCount: Int = 0,
    @SerializedName("unread_count") val unreadCount: Int = 0,
    @SerializedName("archived_count") val archivedCount: Int = 0
)

data class EmailMessageTags(
    @SerializedName("action_hint") val actionHint: String? = null,
    @SerializedName("account_email") val accountEmail: String? = null,
    val resolved: Boolean? = null
)

data class EmailMessageAttachment(
    val id: Int = 0,
    val filename: String = "Anhang",
    val size: Long = 0L,
    val path: String? = null,
    @SerializedName("content_type") val contentType: String? = "application/octet-stream"
)

data class EmailMessage(
    val id: String,
    @SerializedName("mail_account_id") val accountId: String?,
    val folder: String?,
    @SerializedName("from_name") val fromName: String?,
    @SerializedName("from_email") val fromEmail: String?,
    @SerializedName("to_email") val toEmail: String?,
    @SerializedName("cc_email") val ccEmail: String? = null,
    @SerializedName("bcc_email") val bccEmail: String? = null,
    val subject: String?,
    @SerializedName("body_plain") val body: String?,
    @SerializedName("body_html") val htmlBody: String?,
    val category: String? = null,
    val priority: String? = null,
    @SerializedName("ai_status") val aiStatus: String? = null,
    @SerializedName("is_read") val isRead: Boolean = false,
    @SerializedName("is_archived") val isArchived: Boolean = false,
    @SerializedName("received_at") val receivedAt: String?,
    @SerializedName("has_attachments") val hasAttachments: Boolean = false,
    val tags: EmailMessageTags? = null,
    val attachments: List<EmailMessageAttachment>? = null
) {
    val from: String
        get() = fromName?.takeIf { it.isNotBlank() } ?: fromEmail ?: ""

    val to: String
        get() = toEmail ?: ""

    val actionHint: String
        get() = tags?.actionHint ?: ""

    val accountEmail: String
        get() = tags?.accountEmail ?: ""

    val isImportant: Boolean
        get() = priority.equals("high", ignoreCase = true)
}


data class EmailAttachment(
    val id: Int? = null,
    val path: String,
    val filename: String,
    val mime: String,
    val size: Long
)

data class SendEmailRequest(
    @SerializedName("draft_id") val draftId: String? = null,
    @SerializedName("account_id") val accountId: String? = null,
    val from: String,
    val to: String,
    val cc: String? = null,
    val bcc: String? = null,
    val subject: String,
    val body: String,
    val attachments: List<EmailAttachment>? = null
)

data class SaveDraftRequest(
    @SerializedName("draft_id") val draftId: String? = null,
    @SerializedName("account_id") val accountId: String,
    val to: String = "",
    val cc: String = "",
    val bcc: String = "",
    val subject: String = "",
    val body: String = ""
)

data class ResolveMessageRequest(
    val important: Boolean = false
)

data class SenderRuleRequest(
    @SerializedName("target_folder") val targetFolder: String
)

data class EmailRecipientDto(
    val email: String = "",
    val name: String = "",
    val count: Int = 1
)

data class RecipientsResponse(
    val success: Boolean = false,
    val data: List<EmailRecipientDto>? = null
)

data class AiSortResponse(
    val success: Boolean = false,
    val message: String? = null,
    val processed: Int? = null,
    val important: Int? = null
)

data class EmailAccountResponse(
    val success: Boolean,
    val data: List<EmailAccount>,
    @SerializedName("processed_summary") val processedSummary: EmailProcessedSummary? = null
)
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

    @GET("funki/emails/recipients")
    suspend fun getRecipients(): RecipientsResponse

    @GET("funki/emails/messages")
    suspend fun getMessages(
        @Query("folder") folder: String?,
        @Query("account_id") accountId: String?,
        @Query("search") search: String?,
        @Query("unread") unread: Int?,
        @Query("global") global: Int? = 1,
        @Query("priority_filter") priorityFilter: String? = null,
        @Query("category_filter") categoryFilter: String? = null
    ): EmailMessagesResponse

    @GET("funki/emails/messages/{id}")
    suspend fun getMessageDetails(@Path("id") id: String): EmailMessageDetailResponse

    @POST("funki/emails/send")
    suspend fun sendEmail(
        @Body request: SendEmailRequest
    ): SimpleSuccessResponse

    @POST("funki/emails/drafts")
    suspend fun saveDraft(
        @Body request: SaveDraftRequest
    ): EmailMessageDetailResponse

    @POST("funki/emails/sync")
    suspend fun syncEmails(): SimpleSuccessResponse

    @POST("funki/emails/ai-sort")
    suspend fun aiSortEmails(): AiSortResponse

    @PUT("funki/emails/messages/{id}/resolve")
    suspend fun resolveMessage(
        @Path("id") id: String,
        @Body request: ResolveMessageRequest
    ): SimpleSuccessResponse

    @POST("funki/emails/messages/{id}/create-task")
    suspend fun createTaskFromMail(@Path("id") id: String): SimpleSuccessResponse

    @POST("funki/emails/messages/{id}/create-contact")
    suspend fun createContactFromMail(@Path("id") id: String): SimpleSuccessResponse

    @POST("funki/emails/messages/{id}/rule")
    suspend fun createSenderRule(
        @Path("id") id: String,
        @Body request: SenderRuleRequest
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


