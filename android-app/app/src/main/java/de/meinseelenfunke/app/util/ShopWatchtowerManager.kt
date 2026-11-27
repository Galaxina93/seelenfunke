package de.meinseelenfunke.app.util

import android.Manifest
import android.app.NotificationChannel
import android.app.NotificationManager
import android.app.PendingIntent
import android.app.job.JobInfo
import android.app.job.JobScheduler
import android.content.ComponentName
import android.content.Context
import android.content.Intent
import android.content.pm.PackageManager
import android.os.Build
import androidx.core.app.NotificationCompat
import androidx.core.content.ContextCompat
import de.meinseelenfunke.app.MainActivity
import de.meinseelenfunke.app.R
import de.meinseelenfunke.app.di.ServiceLocator
import de.meinseelenfunke.app.services.ShopWatchtowerJobService
import okhttp3.OkHttpClient
import okhttp3.Request
import org.json.JSONObject
import java.util.Locale
import java.util.concurrent.TimeUnit

data class WatchtowerDecision(
    val action: String,
    val title: String,
    val message: String,
    val newStrikes: Int,
    val newAlertOpen: Boolean
)

object ShopWatchtowerManager {
    const val JOB_ID = 179401
    const val NOTIFICATION_ID = 179402
    const val CHANNEL_ID = "watchtower_alert_channel"
    private const val PREFS_WATCHTOWER = "seelenfunke_watchtower_state"
    private const val KEY_STRIKES = "consecutive_strikes"
    private const val KEY_ALERT_OPEN = "alert_open"
    private const val KEY_LAST_ALERT_MS = "last_alert_ms"
    private const val KEY_LAST_FINGERPRINT = "last_alert_fp"
    private const val ALERT_COOLDOWN_MS = 60L * 60L * 1000L // 60 Minuten

    private val httpClient: OkHttpClient by lazy {
        OkHttpClient.Builder()
            .connectTimeout(8, TimeUnit.SECONDS)
            .readTimeout(8, TimeUnit.SECONDS)
            .followRedirects(false)
            .followSslRedirects(false)
            .build()
    }

    fun ensureNotificationChannel(context: Context) {
        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.O) {
            val nm = context.getSystemService(Context.NOTIFICATION_SERVICE) as? NotificationManager ?: return
            val channel = NotificationChannel(
                CHANNEL_ID,
                "Seelenfunke 24/7 Watchtower",
                NotificationManager.IMPORTANCE_HIGH
            ).apply {
                description = "Warnungen bei Ausfall von Live-Shop, Cronjobs, Queue-Worker oder Backups"
                enableLights(true)
                enableVibration(true)
            }
            nm.createNotificationChannel(channel)
        }
    }

    fun schedulePeriodicWatchtower(context: Context) {
        try {
            val appCtx = context.applicationContext
            val scheduler = appCtx.getSystemService(Context.JOB_SCHEDULER_SERVICE) as? JobScheduler ?: return
            val alreadyScheduled = scheduler.allPendingJobs.any { it.id == JOB_ID }
            if (alreadyScheduled) return

            val component = ComponentName(appCtx, ShopWatchtowerJobService::class.java)
            val jobInfo = JobInfo.Builder(JOB_ID, component)
                .setPeriodic(15L * 60L * 1000L)
                .setRequiredNetworkType(JobInfo.NETWORK_TYPE_ANY)
                .setPersisted(true)
                .build()

            scheduler.schedule(jobInfo)
        } catch (_: Throwable) {
        }
    }

    fun isEligibleUserRole(userType: String?, authToken: String?): Boolean {
        if (authToken.isNullOrBlank()) return false
        return userType?.trim()?.lowercase(Locale.ROOT) == "admin"
    }

    fun evaluateOutcome(
        shopReachable: Boolean,
        httpCode: Int,
        pulseStatus: String?,
        pulseHeadline: String?,
        internetOnline: Boolean,
        previousStrikes: Int,
        wasAlertOpen: Boolean,
        lastAlertMs: Long,
        nowMs: Long
    ): WatchtowerDecision {
        if (!shopReachable || httpCode >= 500) {
            if (!internetOnline) {
                return WatchtowerDecision(
                    action = "suppressed_offline",
                    title = "",
                    message = "Kein Mobilfunk/WLAN aktiv",
                    newStrikes = previousStrikes,
                    newAlertOpen = wasAlertOpen
                )
            }

            val strikes = previousStrikes + 1
            val cooldownExpired = (nowMs - lastAlertMs) >= ALERT_COOLDOWN_MS
            if (strikes >= 2 && (!wasAlertOpen || cooldownExpired)) {
                val detail = if (!shopReachable) {
                    "Der Shop antwortet nicht trotz aktiver Internetverbindung."
                } else {
                    "Der Shop antwortet mit Serverfehler HTTP $httpCode."
                }
                return WatchtowerDecision(
                    action = "notify_alert",
                    title = "Seelenfunke Live-Shop Alarm",
                    message = detail,
                    newStrikes = strikes,
                    newAlertOpen = true
                )
            }

            return WatchtowerDecision(
                action = "pending_strike",
                title = "",
                message = "Erster Fehler registriert",
                newStrikes = strikes,
                newAlertOpen = wasAlertOpen
            )
        }

        if (httpCode == 401 || httpCode == 403) {
            return WatchtowerDecision(
                action = "skip_auth",
                title = "",
                message = "Token nicht berechtigt",
                newStrikes = 0,
                newAlertOpen = wasAlertOpen
            )
        }

        val normStatus = (pulseStatus ?: "ok").trim().lowercase(Locale.ROOT)
        if (normStatus == "critical") {
            val cooldownExpired = (nowMs - lastAlertMs) >= ALERT_COOLDOWN_MS
            val msg = pulseHeadline?.takeIf { it.isNotBlank() } ?: "Kritische Abweichung in Hintergrunddiensten."
            if (!wasAlertOpen || cooldownExpired) {
                return WatchtowerDecision(
                    action = "notify_alert",
                    title = "Seelenfunke System-Wächter",
                    message = msg,
                    newStrikes = 0,
                    newAlertOpen = true
                )
            }
            return WatchtowerDecision(
                action = "already_alerted",
                title = "",
                message = msg,
                newStrikes = 0,
                newAlertOpen = true
            )
        }

        if (wasAlertOpen) {
            return WatchtowerDecision(
                action = "notify_recovered",
                title = "Entwarnung: Shop wieder grün",
                message = "Alle 4 Säulen (Shop, Cronjobs, Queue, Backups) laufen wieder regulär.",
                newStrikes = 0,
                newAlertOpen = false
            )
        }

        return WatchtowerDecision(
            action = "healthy",
            title = "",
            message = "OK",
            newStrikes = 0,
            newAlertOpen = false
        )
    }

    fun runCheckNow(
        context: Context,
        customEndpointUrl: String? = null,
        internetProbeOverride: (() -> Boolean)? = null
    ): WatchtowerDecision? {
        val appCtx = context.applicationContext
        ServiceLocator.ensureInitialized(appCtx)

        val token = try { ServiceLocator.getAuthToken() } catch (_: Throwable) { null }
        val userType = try { ServiceLocator.getUserType() } catch (_: Throwable) { null }
        if (!isEligibleUserRole(userType, token)) {
            return null
        }

        val endpoint = customEndpointUrl ?: run {
            val base = try { ServiceLocator.getBaseUrl() } catch (_: Throwable) { return null }
            val candidate = "${base.trimEnd('/')}/system/health-pulse?source=android_watchtower"
            if (!ServiceLocator.isAllowedApiRequestUrl(candidate) || !ServiceLocator.shouldAttachBearerToken(candidate)) {
                return null
            }
            candidate
        }

        var reachable = false
        var statusCode = 0
        var pulseStatus: String? = null
        var pulseHeadline: String? = null

        try {
            val req = Request.Builder()
                .url(endpoint)
                .header("Authorization", "Bearer $token")
                .header("Accept", "application/json")
                .get()
                .build()

            httpClient.newCall(req).execute().use { resp ->
                reachable = true
                statusCode = resp.code
                if (resp.isSuccessful) {
                    val bodyStr = resp.body?.string().orEmpty()
                    if (bodyStr.isNotBlank()) {
                        val json = JSONObject(bodyStr)
                        val data = json.optJSONObject("data")
                        pulseStatus = data?.optString("status", "ok")
                        pulseHeadline = data?.optString("headline", "")
                    }
                }
            }
        } catch (_: Throwable) {
            reachable = false
        }

        val internetUp = if (!reachable || statusCode >= 500) {
            internetProbeOverride?.invoke() ?: verifySmartphoneInternetOnline()
        } else {
            true
        }

        val prefs = appCtx.getSharedPreferences(PREFS_WATCHTOWER, Context.MODE_PRIVATE)
        val prevStrikes = prefs.getInt(KEY_STRIKES, 0)
        val wasOpen = prefs.getBoolean(KEY_ALERT_OPEN, false)
        val lastAlertMs = prefs.getLong(KEY_LAST_ALERT_MS, 0L)
        val nowMs = System.currentTimeMillis()

        val decision = evaluateOutcome(
            shopReachable = reachable,
            httpCode = statusCode,
            pulseStatus = pulseStatus,
            pulseHeadline = pulseHeadline,
            internetOnline = internetUp,
            previousStrikes = prevStrikes,
            wasAlertOpen = wasOpen,
            lastAlertMs = lastAlertMs,
            nowMs = nowMs
        )

        val editor = prefs.edit()
            .putInt(KEY_STRIKES, decision.newStrikes)
            .putBoolean(KEY_ALERT_OPEN, decision.newAlertOpen)

        if (decision.action == "notify_alert") {
            editor.putLong(KEY_LAST_ALERT_MS, nowMs)
            editor.putString(KEY_LAST_FINGERPRINT, decision.message.take(120))
            postWatchtowerNotification(appCtx, decision.title, decision.message)
        } else if (decision.action == "notify_recovered") {
            editor.putLong(KEY_LAST_ALERT_MS, 0L)
            editor.remove(KEY_LAST_FINGERPRINT)
            postWatchtowerNotification(appCtx, decision.title, decision.message)
        } else if (decision.action == "healthy" && !decision.newAlertOpen) {
            (appCtx.getSystemService(Context.NOTIFICATION_SERVICE) as? NotificationManager)?.cancel(NOTIFICATION_ID)
        }
        editor.apply()

        return decision
    }

    fun isAlertOpen(context: Context): Boolean {
        val prefs = context.applicationContext.getSharedPreferences(PREFS_WATCHTOWER, Context.MODE_PRIVATE)
        return prefs.getBoolean(KEY_ALERT_OPEN, false)
    }

    fun clearResolvedNotification(context: Context): Boolean {
        val appCtx = context.applicationContext
        if (isAlertOpen(appCtx)) return false
        val nm = appCtx.getSystemService(Context.NOTIFICATION_SERVICE) as? NotificationManager ?: return false
        nm.cancel(NOTIFICATION_ID)
        return true
    }

    private fun verifySmartphoneInternetOnline(): Boolean {
        val checkUrls = listOf(
            "https://connectivitycheck.gstatic.com/generate_204",
            "https://1.1.1.1"
        )
        for (url in checkUrls) {
            try {
                val req = Request.Builder().url(url).head().build()
                httpClient.newCall(req).execute().use { resp ->
                    if (resp.code in 200..399) return true
                }
            } catch (_: Throwable) {
            }
        }
        return false
    }

    private fun postWatchtowerNotification(context: Context, title: String, message: String) {
        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.TIRAMISU) {
            val granted = ContextCompat.checkSelfPermission(
                context,
                Manifest.permission.POST_NOTIFICATIONS
            ) == PackageManager.PERMISSION_GRANTED
            if (!granted) return
        }

        ensureNotificationChannel(context)
        val nm = context.getSystemService(Context.NOTIFICATION_SERVICE) as? NotificationManager ?: return

        val openIntent = Intent(context, MainActivity::class.java).apply {
            flags = Intent.FLAG_ACTIVITY_NEW_TASK or Intent.FLAG_ACTIVITY_CLEAR_TOP
        }
        val piFlags = PendingIntent.FLAG_UPDATE_CURRENT or PendingIntent.FLAG_IMMUTABLE
        val pendingIntent = PendingIntent.getActivity(context, NOTIFICATION_ID, openIntent, piFlags)

        val notification = NotificationCompat.Builder(context, CHANNEL_ID)
            .setSmallIcon(R.drawable.ic_stat_funkenwerk)
            .setColor(0xFFC5A059.toInt())
            .setContentTitle(title)
            .setContentText(message)
            .setStyle(NotificationCompat.BigTextStyle().bigText(message))
            .setPriority(NotificationCompat.PRIORITY_HIGH)
            .setAutoCancel(true)
            .setContentIntent(pendingIntent)
            .build()

        nm.notify(NOTIFICATION_ID, notification)
    }
}
