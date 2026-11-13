package de.meinseelenfunke.app.data

import android.app.NotificationChannel
import android.app.NotificationManager
import android.app.PendingIntent
import android.content.Context
import android.content.Intent
import android.os.Build
import android.os.Handler
import android.os.Looper
import android.util.Log
import androidx.core.app.NotificationCompat
import de.meinseelenfunke.app.MainActivity
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

    private fun handleMessage(text: String) {
        try {
            val json = JSONObject(text)
            val event = json.optString("event")
            val dataObj = json.opt("data")?.toString() ?: ""

            synchronized(listeners) {
                listeners.forEach { it(event, dataObj) }
            }

            if (event == "new_email") {
                val messageObj = json.optJSONObject("data")
                if (messageObj != null) {
                    val from = messageObj.optString("from", "Unbekannt")
                    val subject = messageObj.optString("subject", "(Kein Betreff)")
                    val snippet = messageObj.optString("snippet", "")
                    val id = messageObj.optString("id", "")

                    showNotification(id, from, subject, snippet)
                    SoundManager.playNotificationSound()
                }
            }
        } catch (e: Exception) {
            Log.e(TAG, "Error handling WebSocket event")
        }
    }

    private fun showNotification(id: String, from: String, subject: String, snippet: String) {
        val ctx = context ?: return
        val notificationManager = ctx.getSystemService(Context.NOTIFICATION_SERVICE) as NotificationManager

        val clickIntent = SecurityIntentHelper.signIntent(ctx, Intent(ctx, MainActivity::class.java).apply {
            flags = Intent.FLAG_ACTIVITY_NEW_TASK or Intent.FLAG_ACTIVITY_CLEAR_TASK
            putExtra("navigate_to", "email")
            putExtra("email_id", id)
        })

        val pendingIntent = PendingIntent.getActivity(
            ctx,
            id.hashCode(),
            clickIntent,
            PendingIntent.FLAG_UPDATE_CURRENT or PendingIntent.FLAG_IMMUTABLE
        )

        val publicNotification = NotificationCompat.Builder(ctx, CHANNEL_ID)
            .setSmallIcon(android.R.drawable.ic_dialog_email)
            .setContentTitle("Funkenwerk Postfach")
            .setContentText("Neue E-Mail eingegangen")
            .setPriority(NotificationCompat.PRIORITY_DEFAULT)
            .build()

        val notification = NotificationCompat.Builder(ctx, CHANNEL_ID)
            .setSmallIcon(android.R.drawable.ic_dialog_email)
            .setContentTitle("Neue E-Mail von $from")
            .setContentText(if (subject.isNotBlank()) "$subject: $snippet" else snippet)
            .setVisibility(NotificationCompat.VISIBILITY_PRIVATE)
            .setPublicVersion(publicNotification)
            .setPriority(NotificationCompat.PRIORITY_DEFAULT)
            .setContentIntent(pendingIntent)
            .setAutoCancel(true)
            .build()

        notificationManager.notify(id.hashCode(), notification)
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
