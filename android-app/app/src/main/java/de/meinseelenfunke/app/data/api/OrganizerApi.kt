package de.meinseelenfunke.app.data.api

import com.google.gson.annotations.SerializedName
import de.meinseelenfunke.app.util.SmartAlarmItem
import de.meinseelenfunke.app.util.SmartHomeGroupDto
import de.meinseelenfunke.app.util.SmartPlugDevice
import de.meinseelenfunke.app.util.SmartShutterDevice
import de.meinseelenfunke.app.util.TimerPresetItem
import de.meinseelenfunke.app.util.WakeUpLogEntry
import okhttp3.MultipartBody
import okhttp3.RequestBody
import retrofit2.http.Body
import retrofit2.http.DELETE
import retrofit2.http.Field
import retrofit2.http.FormUrlEncoded
import retrofit2.http.GET
import retrofit2.http.HTTP
import retrofit2.http.Multipart
import retrofit2.http.PATCH
import retrofit2.http.POST
import retrofit2.http.PUT
import retrofit2.http.Path
import retrofit2.http.Part

data class ManagementTaskList(
    val id: String,
    val name: String,
    val icon: String?,
    val color: String?,
    val open_count: Int? = null,
    val completed_count: Int? = null
)

data class ManagementTask(
    val id: String,
    val task_list_id: String,
    val parent_id: String?,
    val title: String,
    val status: String? = "open",
    val is_mine: Boolean = false,
    val assignee_name: String? = null,
    val assignee_key: String? = null,
    val position: Int = 0,
    val my_position: Int = 0,
    val priority: String?,
    val is_completed: Boolean,
    val is_escalated: Boolean = false,
    val escalation_reason: String? = null,
    val parent_title: String?,
    val relevant_from: String?,
    val due_time: String? = null,
    val duration_minutes: Int? = 60,
    val messages_count: Int = 0,
    val ai_plan: String? = null,
    val created_at: String? = null,
    val file_paths: List<String>? = null
) {
    val normalizedStatus: String
        get() = when {
            is_completed -> "completed"
            status.equals("waiting", ignoreCase = true) -> "waiting"
            status.equals("in_progress", ignoreCase = true) -> "in_progress"
            status.equals("completed", ignoreCase = true) -> "completed"
            else -> "open"
        }

    val statusLabel: String
        get() = when (normalizedStatus) {
            "waiting" -> "Warten"
            "in_progress" -> "In Bearbeitung"
            "completed" -> "Erledigt"
            else -> "Offen"
        }

    val isEscalatedEffective: Boolean
        get() = !is_completed && (is_escalated || status.equals("escalated", ignoreCase = true))

    val isInMyTickets: Boolean
        get() = !is_completed && is_mine
}

data class ManagementTaskChatAttachment(
    val path: String? = null,
    val name: String? = null,
    val mime: String? = null,
    val size: Long = 0
)

data class ManagementTaskMessageDto(
    val id: String,
    val management_task_id: String? = null,
    val sender_type: String? = null,
    val sender_name: String? = null,
    val sender_avatar_url: String? = null,
    val message_type: String? = null,
    val content: String? = null,
    val attachments: List<ManagementTaskChatAttachment>? = null,
    val created_at: String? = null,
    val created_human: String? = null,
    val edited_at: String? = null,
    val edited_human: String? = null,
    val can_edit: Boolean = false,
    val remaining_edit_seconds: Int = 0
)

data class TaskReorderBody(
    val ordered_ids: List<String>,
    val is_mine: Boolean = false,
    val list_scope: String = "all"
)

data class ManagementDayRoutineStep(
    val id: String,
    @SerializedName("day_routine_id") val routine_id: String,
    val title: String,
    val duration_minutes: Int,
    @SerializedName("position") val sort_order: Int
)

data class ManagementDayRoutine(
    val id: String,
    val title: String,
    val message: String?,
    val duration_minutes: Int,
    val start_time: String,
    val icon: String?,
    val is_active: Boolean,
    val steps: List<ManagementDayRoutineStep>
)

data class ManagementShoppingCategory(
    val id: String,
    val name: String,
    val icon: String?,
    val sort_order: Int
)

data class ManagementShoppingItem(
    val id: String,
    val name: String,
    val quantity: Int = 1,
    val category_id: String?,
    val status: String, // "needed" or "stocked"
    val last_purchased_at: String?,
    val purchase_count: Int,
    val category: ManagementShoppingCategory?
)

data class CalendarEvent(
    val id: String,
    val title: String,
    val start: String,
    val end: String?,
    val is_all_day: Boolean,
    val category: String,
    val description: String?,
    val recurrence: String?,
    val reminder_minutes: Int?,
    val priority: String?
)

data class TaskAssigneeDto(
    val key: String = "",
    val name: String = "",
    val role_label: String? = null,
    val initials: String? = null
)

// Response wrappers
data class TaskAssigneesResponse(val success: Boolean, val data: List<TaskAssigneeDto>)
data class TaskListsResponse(val success: Boolean, val data: List<ManagementTaskList>)
data class TasksResponse(val success: Boolean, val data: List<ManagementTask>)
data class TaskDetailResponse(val success: Boolean, val data: ManagementTask)
data class TaskListDetailResponse(val success: Boolean, val data: ManagementTaskList)
data class TaskMessagesResponse(val success: Boolean, val data: List<ManagementTaskMessageDto>)
data class TaskMessagePostResponse(val success: Boolean, val data: ManagementTaskMessageDto?, val task: ManagementTask? = null)
data class RoutinesResponse(val success: Boolean, val data: List<ManagementDayRoutine>)
data class RoutineDetailResponse(val success: Boolean, val data: ManagementDayRoutine)
data class RoutineStepResponse(val success: Boolean, val data: ManagementDayRoutineStep)
data class ShoppingItemsResponse(val success: Boolean, val data: List<ManagementShoppingItem>)
data class ShoppingItemDetailResponse(val success: Boolean, val data: ManagementShoppingItem)
data class CalendarEventsResponse(val success: Boolean, val data: List<CalendarEvent>)

data class AlarmSyncPackagePayload(
    @SerializedName("last_synced_at") val lastSyncedAt: String? = null,
    @SerializedName("device_name") val deviceName: String? = null,
    @SerializedName("active_alarm_time") val activeAlarmTime: String? = null,
    @SerializedName("active_alarm_label") val activeAlarmLabel: String? = null,
    val alarms: List<SmartAlarmItem>? = null,
    val timers: List<TimerPresetItem>? = null,
    @SerializedName("smart_plugs") val smartPlugs: List<SmartPlugDevice>? = null,
    @SerializedName("smart_shutters") val smartShutters: List<SmartShutterDevice>? = null,
    @SerializedName("smart_groups") val smartGroups: List<SmartHomeGroupDto>? = null,
    @SerializedName("wake_up_logs") val wakeUpLogs: Map<String, WakeUpLogEntry>? = null,
    @SerializedName("funkira_voice") val funkiraVoice: String? = "Aoede",
    @SerializedName("tapo_email") val tapoEmail: String? = null,
    @SerializedName("tapo_password") val tapoPassword: String? = null
)

data class AlarmSyncPackageResponse(
    val success: Boolean,
    val data: AlarmSyncPackagePayload?
)

data class ManagementContactDto(
    val id: String,
    val first_name: String? = null,
    val last_name: String? = null,
    val full_name: String? = null,
    val nickname: String? = null,
    val relation_type: String? = null,
    val phone: String? = null,
    val normalized_phone: String? = null,
    val match_suffix: String? = null,
    val system_instructions: String? = null,
    val is_favorite: Boolean? = false,
    val funkira_call_whitelist: Boolean? = false
)

data class ContactsListResponse(
    val success: Boolean,
    val data: List<ManagementContactDto>?
)

data class ContactSingleResponse(
    val success: Boolean,
    val data: ManagementContactDto?
)

data class PhoneWhitelistToggleBody(
    val funkira_call_whitelist: Boolean
)

data class PhoneSecretaryCallSummaryDto(
    val id: String = "",
    val caller_name: String = "",
    val created_at_label: String = "",
    val duration_seconds: Int = 0,
    val summary: String = "",
    val rahmenbedingungen: List<String>? = emptyList(),
    val action_required: String = "",
    val urgency: String = "mittel",
    val callback_requested: Boolean = true,
    val transcript: List<String>? = emptyList()
)

data class PhoneConciergeConfigPayload(
    val enabled: Boolean = true,
    val ring_delay_seconds: Int = 12,
    val gateway_phone: String? = "+4953717269023",
    val whitelist_count: Int = 0,
    val whitelist_contacts: List<ManagementContactDto>? = emptyList(),
    val recent_calls: List<PhoneSecretaryCallSummaryDto>? = emptyList()
)

data class PhoneConciergeConfigResponse(
    val success: Boolean,
    val data: PhoneConciergeConfigPayload?
)

data class PhoneConciergeConfigUpdateBody(
    val enabled: Boolean? = null,
    val ring_delay_seconds: Int? = null
)

data class PhoneConciergeSignalBody(
    val call_id: String? = null,
    val state: String,
    val is_simulation: Boolean = false,
    val contact_id: String? = null,
    val caller_name: String? = null,
    val caller_nickname: String? = null,
    val relation_type: String? = null,
    val caller_phone: String? = null,
    val system_instructions: String? = null,
    val audio_mode: String = "bluetooth_hfp"
)

data class PhoneConciergeActiveCallDto(
    val call_id: String? = null,
    val state: String? = null,
    val rejected: Boolean? = false,
    val caller_name: String? = null,
    val greeting_name: String? = null,
    val hangup_requested: Boolean? = false,
    val summary: String? = null
)

data class PhoneConciergeSignalResponse(
    val success: Boolean,
    val data: PhoneConciergeActiveCallDto?
)

data class PhoneConciergeCompletedDto(
    val call_id: String? = null,
    val caller_name: String? = null,
    val summary: String? = null,
    val urgency: String? = null,
    val callback_requested: Boolean? = true
)

data class PhoneConciergePollPayload(
    val enabled: Boolean = true,
    val ring_delay_seconds: Int = 12,
    val active_call: PhoneConciergeActiveCallDto? = null,
    val hangup_requested: Boolean = false,
    val last_completed_call: PhoneConciergeCompletedDto? = null
)

data class PhoneConciergePollResponse(
    val success: Boolean,
    val data: PhoneConciergePollPayload?
)

data class PhoneConciergeCompleteBody(
    val call_id: String? = null,
    val is_simulation: Boolean = false,
    val contact_id: String? = null,
    val caller_name: String,
    val greeting_name: String? = null,
    val caller_phone: String? = null,
    val handled_by: String = "android_secretary",
    val summary: String,
    val urgency: String = "mittel",
    val callback_requested: Boolean = true
)

data class PhoneConciergeCompleteResponse(
    val success: Boolean,
    val data: PhoneConciergeCompletedDto?
)

interface OrganizerApi {
    // --- Tasks ---
    @GET("funki/tasks/lists")
    suspend fun getTaskLists(): TaskListsResponse

    @DELETE("funki/tasks/lists/{id}")
    suspend fun deleteTaskList(@Path("id") id: String): SimpleSuccessResponse

    @FormUrlEncoded
    @POST("funki/tasks/lists")
    suspend fun addTaskList(
        @Field("name") name: String,
        @Field("icon") icon: String? = "bookmark",
        @Field("color") color: String? = "#818CF8"
    ): TaskListDetailResponse

    @FormUrlEncoded
    @PUT("funki/tasks/lists/{id}")
    suspend fun updateTaskList(
        @Path("id") id: String,
        @Field("name") name: String,
        @Field("icon") icon: String? = null,
        @Field("color") color: String? = null
    ): TaskListDetailResponse

    @GET("funki/tasks")
    suspend fun getTasks(): TasksResponse

    @PUT("funki/tasks/{id}/toggle")
    suspend fun toggleTask(@Path("id") id: String): TaskDetailResponse

    @FormUrlEncoded
    @PUT("funki/tasks/{id}")
    suspend fun updateTask(
        @Path("id") id: String,
        @Field("title") title: String?,
        @Field("priority") priority: String?,
        @Field("is_completed") isCompleted: Boolean?,
        @Field("relevant_from") relevantFrom: String?,
        @Field("ai_plan") aiPlan: String? = null,
        @Field("task_list_id") taskListId: String? = null,
        @Field("due_time") dueTime: String? = null,
        @Field("duration_minutes") durationMinutes: Int? = null,
        @Field("status") status: String? = null
    ): TaskDetailResponse

    @GET("funki/tasks/assignees")
    suspend fun getTaskAssignees(): TaskAssigneesResponse

    @FormUrlEncoded
    @PUT("funki/tasks/{id}/assign")
    suspend fun assignTask(
        @Path("id") id: String,
        @Field("assignee_key") assigneeKey: String
    ): TaskDetailResponse

    @FormUrlEncoded
    @PUT("funki/tasks/{id}/status")
    suspend fun updateTaskStatus(
        @Path("id") id: String,
        @Field("status") status: String
    ): TaskDetailResponse

    @FormUrlEncoded
    @PUT("funki/tasks/{id}/mine")
    suspend fun updateTaskMine(
        @Path("id") id: String,
        @Field("is_mine") isMine: Boolean,
        @Field("position") position: Int? = null
    ): TaskDetailResponse

    @POST("funki/tasks/reorder")
    suspend fun reorderTasks(
        @Body body: TaskReorderBody
    ): SimpleSuccessResponse

    @GET("funki/tasks/{id}/messages")
    suspend fun getTaskMessages(
        @Path("id") id: String
    ): TaskMessagesResponse

    @Multipart
    @POST("funki/tasks/{id}/messages")
    suspend fun postTaskMessage(
        @Path("id") id: String,
        @Part("content") content: RequestBody,
        @Part files: List<MultipartBody.Part>
    ): TaskMessagePostResponse

    @Multipart
    @POST("funki/tasks/{id}/messages/{messageId}")
    suspend fun editTaskMessage(
        @Path("id") id: String,
        @Path("messageId") messageId: String,
        @Part("_method") method: RequestBody,
        @Part("content") content: RequestBody,
        @Part("keep_attachments_present") keepPresent: RequestBody,
        @Part keepAttachments: List<MultipartBody.Part>,
        @Part files: List<MultipartBody.Part>
    ): TaskMessagePostResponse

    @FormUrlEncoded
    @POST("funki/tasks/{id}/resolve-escalation")
    suspend fun resolveTaskEscalation(
        @Path("id") id: String,
        @Field("relevant_from") relevantFrom: String,
        @Field("due_time") dueTime: String? = null,
        @Field("duration_minutes") durationMinutes: Int? = null,
        @Field("status") status: String = "in_progress",
        @Field("content") content: String
    ): TaskDetailResponse

    @FormUrlEncoded
    @POST("funki/tasks/lists/{listId}/tasks")
    suspend fun addTask(
        @Path("listId") listId: String,
        @Field("title") title: String,
        @Field("priority") priority: String = "niedrig",
        @Field("relevant_from") relevantFrom: String? = null,
        @Field("due_time") dueTime: String? = null,
        @Field("duration_minutes") durationMinutes: Int? = null,
        @Field("status") status: String? = null,
        @Field("assignee_key") assigneeKey: String? = null
    ): TaskDetailResponse

    @DELETE("funki/tasks/{id}")
    suspend fun deleteTask(@Path("id") id: String): SimpleSuccessResponse

    // --- Routine ---
    @GET("funki/routine")
    suspend fun getRoutines(): RoutinesResponse

    @FormUrlEncoded
    @POST("funki/routine")
    suspend fun createRoutine(
        @Field("title") title: String,
        @Field("message") message: String?,
        @Field("duration_minutes") durationMinutes: Int,
        @Field("start_time") startTime: String,
        @Field("icon") icon: String? = null
    ): RoutineDetailResponse

    @FormUrlEncoded
    @PUT("funki/routine/{id}")
    suspend fun updateRoutine(
        @Path("id") id: String,
        @Field("title") title: String?,
        @Field("message") message: String?,
        @Field("duration_minutes") durationMinutes: Int?,
        @Field("start_time") startTime: String?,
        @Field("icon") icon: String?,
        @Field("is_active") isActive: Boolean?
    ): RoutineDetailResponse

    @DELETE("funki/routine/{id}")
    suspend fun deleteRoutine(@Path("id") id: String): SimpleSuccessResponse

    @FormUrlEncoded
    @POST("funki/routine/{routineId}/steps")
    suspend fun addRoutineStep(
        @Path("routineId") routineId: String,
        @Field("title") title: String,
        @Field("duration_minutes") durationMinutes: Int
    ): RoutineStepResponse

    @DELETE("funki/routine/steps/{stepId}")
    suspend fun deleteRoutineStep(@Path("stepId") stepId: String): SimpleSuccessResponse

    @GET("funki/routine/alarm-sync")
    suspend fun getAlarmSyncPackage(): AlarmSyncPackageResponse

    @FormUrlEncoded
    @POST("funki/routine/alarm-sync")
    suspend fun syncAlarmPackage(
        @Field("alarms_json") alarmsJson: String,
        @Field("timers_json") timersJson: String,
        @Field("smart_plugs_json") smartPlugsJson: String,
        @Field("wake_up_logs_json") wakeUpLogsJson: String,
        @Field("active_alarm_time") activeAlarmTime: String,
        @Field("device_name") deviceName: String,
        @Field("tapo_email") tapoEmail: String? = null,
        @Field("tapo_password") tapoPassword: String? = null
    ): AlarmSyncPackageResponse

    // --- Shopping ---
    @GET("funki/shopping/items")
    suspend fun getShoppingItems(): ShoppingItemsResponse

    @PUT("funki/shopping/items/{id}/toggle")
    suspend fun toggleShoppingItem(@Path("id") id: String): ShoppingItemDetailResponse

    @FormUrlEncoded
    @POST("funki/shopping/items")
    suspend fun addShoppingItem(
        @Field("name") name: String,
        @Field("category_id") categoryId: String? = null,
        @Field("quantity") quantity: Int = 1
    ): ShoppingItemDetailResponse

    @FormUrlEncoded
    @PATCH("funki/shopping/items/{id}/quantity")
    suspend fun updateShoppingItemQuantity(
        @Path("id") id: String,
        @Field("quantity") quantity: Int
    ): ShoppingItemDetailResponse

    @DELETE("funki/shopping/items/{id}")
    suspend fun deleteShoppingItem(@Path("id") id: String): SimpleSuccessResponse

    // --- Calendar ---
    @GET("funki/calendar/events")
    suspend fun getCalendarEvents(): CalendarEventsResponse

    @FormUrlEncoded
    @POST("funki/calendar/events")
    suspend fun addCalendarEvent(
        @Field("title") title: String,
        @Field("start") start: String,
        @Field("end") end: String?,
        @Field("is_all_day") isAllDay: Int,
        @Field("category") category: String,
        @Field("description") description: String?,
        @Field("recurrence") recurrence: String? = null,
        @Field("reminder_minutes") reminderMinutes: Int? = null,
        @Field("priority") priority: String? = null,
        @Field("send_email") sendEmail: Int = 0
    ): SimpleSuccessResponse

    @DELETE("funki/calendar/events/{id}")
    suspend fun deleteCalendarEvent(@Path("id") id: String): SimpleSuccessResponse

    @FormUrlEncoded
    @PUT("funki/calendar/events/{id}")
    suspend fun updateCalendarEvent(
        @Path("id") id: String,
        @Field("title") title: String,
        @Field("start") start: String,
        @Field("end") end: String?,
        @Field("is_all_day") isAllDay: Int,
        @Field("category") category: String,
        @Field("description") description: String?,
        @Field("recurrence") recurrence: String? = null,
        @Field("reminder_minutes") reminderMinutes: Int? = null,
        @Field("priority") priority: String? = null
    ): SimpleSuccessResponse

    // --- Subtasks ---
    @FormUrlEncoded
    @POST("funki/tasks/{id}/subtask")
    suspend fun addSubtask(
        @Path("id") id: String,
        @Field("title") title: String
    ): TaskDetailResponse

    @Multipart
    @POST("funki/tasks/{id}/files")
    suspend fun uploadTaskFile(
        @Path("id") id: String,
        @Part file: MultipartBody.Part
    ): TaskDetailResponse

    @HTTP(method = "DELETE", path = "funki/tasks/{id}/files", hasBody = true)
    @FormUrlEncoded
    suspend fun deleteTaskFile(
        @Path("id") id: String,
        @Field("path") path: String
    ): TaskDetailResponse

    // --- Contacts & Funkira Phone-Concierge (Whitelist Answering Machine) ---
    @GET("funki/contacts")
    suspend fun getContacts(): ContactsListResponse

    @PUT("funki/contacts/{id}/call-whitelist")
    suspend fun updateContactCallWhitelist(
        @Path("id") id: String,
        @Body body: PhoneWhitelistToggleBody
    ): ContactSingleResponse

    @GET("system/phone-concierge/config")
    suspend fun getPhoneConciergeConfig(): PhoneConciergeConfigResponse

    @POST("system/phone-concierge/config")
    suspend fun updatePhoneConciergeConfig(
        @Body body: PhoneConciergeConfigUpdateBody
    ): PhoneConciergeConfigResponse

    @POST("system/phone-concierge/signal")
    suspend fun signalPhoneConciergeCall(
        @Body body: PhoneConciergeSignalBody
    ): PhoneConciergeSignalResponse

    @GET("system/phone-concierge/poll")
    suspend fun pollPhoneConciergeState(): PhoneConciergePollResponse

    @POST("system/phone-concierge/complete")
    suspend fun completePhoneConciergeCall(
        @Body body: PhoneConciergeCompleteBody
    ): PhoneConciergeCompleteResponse
}
