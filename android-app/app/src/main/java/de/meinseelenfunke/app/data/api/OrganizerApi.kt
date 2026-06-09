package de.meinseelenfunke.app.data.api

import com.google.gson.annotations.SerializedName
import okhttp3.MultipartBody
import retrofit2.http.DELETE
import retrofit2.http.Field
import retrofit2.http.FormUrlEncoded
import retrofit2.http.GET
import retrofit2.http.HTTP
import retrofit2.http.Multipart
import retrofit2.http.POST
import retrofit2.http.PUT
import retrofit2.http.Path
import retrofit2.http.Part

data class ManagementTaskList(
    val id: String,
    val name: String,
    val icon: String?,
    val color: String?
)

data class ManagementTask(
    val id: String,
    val task_list_id: String,
    val parent_id: String?,
    val title: String,
    val priority: String?,
    val is_completed: Boolean,
    val parent_title: String?,
    val relevant_from: String?,
    val file_paths: List<String>? = null
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

// Response wrappers
data class TaskListsResponse(val success: Boolean, val data: List<ManagementTaskList>)
data class TasksResponse(val success: Boolean, val data: List<ManagementTask>)
data class TaskDetailResponse(val success: Boolean, val data: ManagementTask)
data class TaskListDetailResponse(val success: Boolean, val data: ManagementTaskList)
data class RoutinesResponse(val success: Boolean, val data: List<ManagementDayRoutine>)
data class RoutineDetailResponse(val success: Boolean, val data: ManagementDayRoutine)
data class RoutineStepResponse(val success: Boolean, val data: ManagementDayRoutineStep)
data class ShoppingItemsResponse(val success: Boolean, val data: List<ManagementShoppingItem>)
data class ShoppingItemDetailResponse(val success: Boolean, val data: ManagementShoppingItem)
data class CalendarEventsResponse(val success: Boolean, val data: List<CalendarEvent>)

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
        @Field("icon") icon: String? = "bookmark"
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
        @Field("relevant_from") relevantFrom: String?
    ): TaskDetailResponse

    @FormUrlEncoded
    @POST("funki/tasks/lists/{listId}/tasks")
    suspend fun addTask(
        @Path("listId") listId: String,
        @Field("title") title: String,
        @Field("priority") priority: String = "niedrig"
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

    // --- Shopping ---
    @GET("funki/shopping/items")
    suspend fun getShoppingItems(): ShoppingItemsResponse

    @PUT("funki/shopping/items/{id}/toggle")
    suspend fun toggleShoppingItem(@Path("id") id: String): ShoppingItemDetailResponse

    @FormUrlEncoded
    @POST("funki/shopping/items")
    suspend fun addShoppingItem(
        @Field("name") name: String,
        @Field("category_id") categoryId: String? = null
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
}
