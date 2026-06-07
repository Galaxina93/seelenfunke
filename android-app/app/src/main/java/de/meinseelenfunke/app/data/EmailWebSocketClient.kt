package de.meinseelenfunke.app.data

import android.app.NotificationChannel
import android.app.NotificationManager
import android.app.PendingIntent
import android.content.Context
import android.content.Intent
import android.os.Build
import android.util.Log
import androidx.core.app.NotificationCompat
import de.meinseelenfunke.app.MainActivity
import de.meinseelenfunke.app.di.ServiceLocator
import de.meinseelenfunke.app.util.SoundManager
import okhttp3.*
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
    private var isConnected = false

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
            }
            notificationManager.createNotificationChannel(channel)
        }
    }

    fun startWebSocket() {
        val ctx = context ?: return
        if (client == null) {
            client = OkHttpClient.Builder()
                .readTimeout(0, TimeUnit.MILLISECONDS)
                .build()
        }

        val url = ServiceLocator.getWebSocketUrl()
        Log.d(TAG, "Connecting to WebSocket at $url")

        val request = Request.Builder()
            .url(url)
            .build()

        // Close existing web socket if any
        try {
            webSocket?.close(1000, "Reconnecting")
        } catch (e: Exception) {
            // ignore
        }

        webSocket = client?.newWebSocket(request, object : WebSocketListener() {
            override fun onOpen(webSocket: WebSocket, response: Response) {
                Log.d(TAG, "WebSocket connection opened")
                isConnected = true
            }

            override fun onMessage(webSocket: WebSocket, text: String) {
                Log.d(TAG, "WebSocket message received: $text")
                handleMessage(text)
            }

            override fun onClosed(webSocket: WebSocket, code: Int, reason: String) {
                Log.d(TAG, "WebSocket connection closed: $reason")
                isConnected = false
                reconnect()
            }

            override fun onFailure(webSocket: WebSocket, t: Throwable, response: Response?) {
                Log.e(TAG, "WebSocket connection failure", t)
                isConnected = false
                reconnect()
            }
        })
    }

    private fun reconnect() {
        // Reconnect after 5 seconds
        val ctx = context ?: return
        android.os.Handler(android.os.Looper.getMainLooper()).postDelayed({
            Log.d(TAG, "Attempting to reconnect WebSocket...")
            startWebSocket()
        }, 5000)
    }

    private fun handleMessage(text: String) {
        try {
            val json = JSONObject(text)
            val event = json.optString("event")
            val dataObj = json.opt("data")?.toString() ?: ""

            // Notify listeners
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

                    // Show notification
                    showNotification(id, from, subject, snippet)

                    // Play notification sound
                    SoundManager.playNotificationSound()
                }
            }
        } catch (e: Exception) {
            Log.e(TAG, "Error handling WebSocket message", e)
        }
    }

    private fun showNotification(id: String, from: String, subject: String, snippet: String) {
        val ctx = context ?: return
        val notificationManager = ctx.getSystemService(Context.NOTIFICATION_SERVICE) as NotificationManager

        val clickIntent = Intent(ctx, MainActivity::class.java).apply {
            flags = Intent.FLAG_ACTIVITY_NEW_TASK or Intent.FLAG_ACTIVITY_CLEAR_TASK
            putExtra("navigate_to", "email")
            putExtra("email_id", id)
        }

        val pendingIntent = PendingIntent.getActivity(
            ctx,
            id.hashCode(),
            clickIntent,
            PendingIntent.FLAG_UPDATE_CURRENT or PendingIntent.FLAG_IMMUTABLE
        )

        val notification = NotificationCompat.Builder(ctx, CHANNEL_ID)
            .setSmallIcon(android.R.drawable.ic_dialog_email)
            .setContentTitle("Neue E-Mail von $from")
            .setContentText(if (subject.isNotBlank()) "$subject: $snippet" else snippet)
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
