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

interface AuthApi {
    @POST("login")
    suspend fun login(@Body request: LoginRequest): LoginResponse

    @GET("user")
    suspend fun getUser(): User
}
