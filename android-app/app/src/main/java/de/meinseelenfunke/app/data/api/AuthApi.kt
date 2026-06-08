package de.meinseelenfunke.app.data.api

import retrofit2.http.Body
import retrofit2.http.GET
import retrofit2.http.POST

data class LoginRequest(
    val email: String,
    val password: String
)

data class User(
    val id: String,
    val first_name: String?,
    val last_name: String?,
    val email: String,
    val user_type: String? = null
)

data class LoginResponse(
    val status: String,
    val token: String?,
    val user_type: String?,
    val user: User?
)

data class ForgotPasswordRequest(
    val email: String
)

data class ForgotPasswordResponse(
    val status: String,
    val message: String
)

data class FcmTokenRequest(
    val fcm_token: String,
    val device_name: String? = "Android App"
)

data class FcmTokenResponse(
    val success: Boolean,
    val message: String?
)

interface AuthApi {
    @POST("login")
    suspend fun login(@Body request: LoginRequest): LoginResponse

    @GET("user")
    suspend fun getUser(): User

    @POST("password/email")
    suspend fun forgotPassword(@Body request: ForgotPasswordRequest): ForgotPasswordResponse

    @POST("device/register")
    suspend fun updateFcmToken(@Body request: FcmTokenRequest): FcmTokenResponse
}


