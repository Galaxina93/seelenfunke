package de.meinseelenfunke.app.data

import android.Manifest
import android.app.NotificationChannel
import android.app.NotificationManager
import android.app.PendingIntent
import android.content.Context
import android.content.Intent
import android.content.pm.PackageManager
import android.os.Build
import android.os.Handler
import android.os.Looper
import android.util.Log
import androidx.core.app.NotificationCompat
import androidx.core.content.ContextCompat
import de.meinseelenfunke.app.MainActivity
import de.meinseelenfunke.app.R
import de.meinseelenfunke.app.di.ServiceLocator
import de.meinseelenfunke.app.util.SecurityIntentHelper
import de.meinseelenfunke.app.util.SoundManager
import okhttp3.OkHttpClient
import okhttp3.Request
import okhttp3.Response
import okhttp3.WebSocket
import okhttp3.WebSocketListener
import org.json.JSONObject
import java.util.concurrent.TimeUnit

object EmailWebSocketClient {
    private const val TAG = "EmailWebSocketClient"
    private const val CHANNEL_ID = "email_channel"
    private const val CHANNEL_NAME = "Seelenfunke E-Mails"

    private var context: Context? = null
    private var client: OkHttpClient? = null
    private var webSocket: WebSocket? = null
    private val listeners = mutableListOf<(event: String, data: String) -> Unit>()
    private val mainHandler = Handler(Looper.getMainLooper())
    private val reconnectRunnable = Runnable { startWebSocket() }

    @Volatile
    private var isConnected = false

    @Volatile
    private var shouldReconnect = true

    fun init(appContext: Context) {
        context = appContext.applicationContext
        createNotificationChannel()
        startWebSocket()
    }

    private fun createNotificationChannel() {
        val ctx = context ?: return
        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.O) {
            val notificationManager = ctx.getSystemService(Context.NOTIFICATION_SERVICE) as NotificationManager
            val channel = NotificationChannel(
                CHANNEL_ID,
                CHANNEL_NAME,
                NotificationManager.IMPORTANCE_DEFAULT
            ).apply {
                description = "Benachrichtigungen über neue E-Mails"
                enableLights(true)
                enableVibration(true)
                lockscreenVisibility = NotificationCompat.VISIBILITY_PRIVATE
            }
            notificationManager.createNotificationChannel(channel)
        }
    }

    fun startWebSocket() {
        val ctx = context ?: return
        ServiceLocator.ensureInitialized(ctx)
        val authToken = ServiceLocator.getAuthToken()
        if (authToken.isNullOrBlank()) {
            shouldReconnect = false
            return
        }
        shouldReconnect = true
        mainHandler.removeCallbacks(reconnectRunnable)

        if (client == null) {
            client = OkHttpClient.Builder()
                .readTimeout(0, TimeUnit.MILLISECONDS)
                .build()
        }

        val url = ServiceLocator.getWebSocketUrl()
        val request = Request.Builder()
            .url(url)
            .addHeader("Authorization", "Bearer $authToken")
            .build()

        try {
            webSocket?.close(1000, "Reconnecting")
        } catch (_: Exception) {
        }

        webSocket = client?.newWebSocket(request, object : WebSocketListener() {
            override fun onOpen(webSocket: WebSocket, response: Response) {
                isConnected = true
            }

            override fun onMessage(webSocket: WebSocket, text: String) {
                handleMessage(text)
            }

            override fun onClosed(webSocket: WebSocket, code: Int, reason: String) {
                isConnected = false
                if (code != 1000 && shouldReconnect) {
                    reconnect()
                }
            }

            override fun onFailure(webSocket: WebSocket, t: Throwable, response: Response?) {
                isConnected = false
                if (shouldReconnect) {
                    reconnect()
                }
            }
        })
    }

    fun disconnect() {
        shouldReconnect = false
        isConnected = false
        mainHandler.removeCallbacks(reconnectRunnable)
        try {
            webSocket?.close(1000, "Logout")
        } catch (_: Exception) {
        }
        webSocket = null
    }

    private fun reconnect() {
        if (!shouldReconnect || ServiceLocator.getAuthToken().isNullOrBlank()) return
        mainHandler.removeCallbacks(reconnectRunnable)
        mainHandler.postDelayed(reconnectRunnable, 5000L)
    }

    internal data class VisibleEmailPayload(
        val id: String,
        val from: String,
        val subject: String,
        val snippet: String,
        val notificationId: Int
    )

    internal fun extractVisibleEmailPayload(messageObj: JSONObject?): VisibleEmailPayload? {
        if (messageObj == null) return null
        fun cleanField(key: String): String {
            if (!messageObj.has(key) || messageObj.isNull(key)) return ""
            val value = messageObj.optString(key, "").trim()
            return if (value.equals("null", ignoreCase = true)) "" else value
        }

        val from = cleanField("from")
        val subject = cleanField("subject")
        val snippet = cleanField("snippet")
        if (from.isEmpty() && subject.isEmpty() && snippet.isEmpty()) {
            return null
        }

        val rawId = cleanField("id")
        val stableKey = rawId.ifEmpty { "$from|$subject|$snippet" }
        val notifId = (stableKey.hashCode() and 0x7FFFFFFF).coerceAtLeast(3000)

        return VisibleEmailPayload(
            id = rawId,
            from = from.ifEmpty { "Unbekannt" },
            subject = subject.ifEmpty { "(Kein Betreff)" },
            snippet = snippet,
            notificationId = notifId
        )
    }

    private fun handleMessage(text: String) {
        try {
            val json = JSONObject(text)
            val event = json.optString("event")
            val dataObj = json.opt("data")?.toString() ?: ""

            synchronized(listeners) {
                listeners.forEach { it(event, dataObj) }
            }

            if (event == "new_email") {
                val payload = extractVisibleEmailPayload(json.optJSONObject("data"))
                if (payload != null) {
                    showNotification(payload)
                    SoundManager.playNotificationSound()
                }
            }
        } catch (e: Exception) {
            Log.e(TAG, "Error handling WebSocket event")
        }
    }

    private fun showNotification(payload: VisibleEmailPayload) {
        val ctx = context ?: return
        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.TIRAMISU &&
            ContextCompat.checkSelfPermission(ctx, Manifest.permission.POST_NOTIFICATIONS) != PackageManager.PERMISSION_GRANTED
        ) {
            return
        }
        val notificationManager = ctx.getSystemService(Context.NOTIFICATION_SERVICE) as? NotificationManager ?: return

        val clickIntent = SecurityIntentHelper.signIntent(ctx, Intent(ctx, MainActivity::class.java).apply {
            flags = Intent.FLAG_ACTIVITY_NEW_TASK or Intent.FLAG_ACTIVITY_CLEAR_TASK
            putExtra("navigate_to", "email")
            putExtra("email_id", payload.id)
        })

        val pendingIntent = PendingIntent.getActivity(
            ctx,
            payload.notificationId,
            clickIntent,
            PendingIntent.FLAG_UPDATE_CURRENT or PendingIntent.FLAG_IMMUTABLE
        )

        val publicNotification = NotificationCompat.Builder(ctx, CHANNEL_ID)
            .setSmallIcon(R.drawable.ic_stat_funkenwerk)
            .setColor(0xFFC5A059.toInt())
            .setContentTitle("Funkenwerk Postfach")
            .setContentText("Neue E-Mail eingegangen")
            .setPriority(NotificationCompat.PRIORITY_DEFAULT)
            .build()

        val contentText = if (payload.snippet.isNotBlank()) "${payload.subject}: ${payload.snippet}" else payload.subject
        val notification = NotificationCompat.Builder(ctx, CHANNEL_ID)
            .setSmallIcon(R.drawable.ic_stat_funkenwerk)
            .setColor(0xFFC5A059.toInt())
            .setContentTitle("Neue E-Mail von ${payload.from}")
            .setContentText(contentText)
            .setVisibility(NotificationCompat.VISIBILITY_PRIVATE)
            .setPublicVersion(publicNotification)
            .setPriority(NotificationCompat.PRIORITY_DEFAULT)
            .setContentIntent(pendingIntent)
            .setAutoCancel(true)
            .build()

        notificationManager.notify(payload.notificationId, notification)
    }

    fun addListener(listener: (event: String, data: String) -> Unit) {
        synchronized(listeners) {
            listeners.add(listener)
        }
    }

    fun removeListener(listener: (event: String, data: String) -> Unit) {
        synchronized(listeners) {
            listeners.remove(listener)
        }
    }
}
