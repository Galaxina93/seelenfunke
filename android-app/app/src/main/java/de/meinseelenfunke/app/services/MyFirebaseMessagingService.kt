package de.meinseelenfunke.app.services

import android.app.NotificationChannel
import android.app.NotificationManager
import android.app.PendingIntent
import android.content.Context
import android.content.Intent
import android.os.Build
import android.util.Log
import androidx.core.app.NotificationCompat
import com.google.firebase.messaging.FirebaseMessagingService
import com.google.firebase.messaging.RemoteMessage
import de.meinseelenfunke.app.MainActivity
import de.meinseelenfunke.app.R
import de.meinseelenfunke.app.di.ServiceLocator
import kotlinx.coroutines.CoroutineScope
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.launch

class MyFirebaseMessagingService : FirebaseMessagingService() {

    private val tag = "FirebaseMessaging"

    override fun onNewToken(token: String) {
        super.onNewToken(token)
        Log.d(tag, "Refreshed token: $token")
        
        // Save the token locally in SharedPreferences
        ServiceLocator.saveFcmToken(token)
        
        // If logged in, upload it to the backend immediately
        if (ServiceLocator.authRepository.isLoggedIn()) {
            CoroutineScope(Dispatchers.IO).launch {
                ServiceLocator.authRepository.updateFcmToken(token)
            }
        }
    }

    override fun onMessageReceived(remoteMessage: RemoteMessage) {
        super.onMessageReceived(remoteMessage)
        val context = applicationContext
        de.meinseelenfunke.app.util.AppLogger.info(context, "FCM", "onMessageReceived from: ${remoteMessage.from}")

        // Check if it's a silent sync command
        if (remoteMessage.data["action"] == "sync_calendar") {
            de.meinseelenfunke.app.util.AppLogger.info(context, "FCM", "Received silent calendar sync push command")
            CoroutineScope(Dispatchers.IO).launch {
                try {
                    ServiceLocator.organizerRepository.getCalendarEvents()
                    de.meinseelenfunke.app.util.AppLogger.info(context, "FCM", "Silent calendar sync completed successfully and alarms rescheduled")
                } catch (e: Exception) {
                    de.meinseelenfunke.app.util.AppLogger.error(context, "FCM", "Silent calendar sync failed: ${e.message}", e)
                }
            }
            return
        }

        // Check if message contains data payload
        if (remoteMessage.data.isNotEmpty()) {
            Log.d(tag, "Message data payload: " + remoteMessage.data)
            val title = remoteMessage.data["title"] ?: "Neue Meldung"
            val body = remoteMessage.data["body"] ?: ""
            de.meinseelenfunke.app.util.AppLogger.info(context, "FCM", "Received data push payload. Title: $title, Body: $body")
            sendNotification(title, body, remoteMessage.data)
        } else {
            // Check if message contains notification payload
            remoteMessage.notification?.let {
                Log.d(tag, "Message Notification Body: ${it.body}")
                de.meinseelenfunke.app.util.AppLogger.info(context, "FCM", "Received visual push notification. Title: ${it.title}, Body: ${it.body}")
                sendNotification(it.title ?: "Neue Meldung", it.body ?: "", emptyMap())
            }
        }
    }

    private fun sendNotification(title: String, messageBody: String, data: Map<String, String>) {
        val intent = Intent(this, MainActivity::class.java).apply {
            flags = Intent.FLAG_ACTIVITY_CLEAR_TOP or Intent.FLAG_ACTIVITY_SINGLE_TOP
            if (data.containsKey("open_tab")) {
                putExtra("open_tab", data["open_tab"]?.toIntOrNull() ?: 0)
            }
            if (data.containsKey("open_subtab")) {
                putExtra("open_subtab", data["open_subtab"]?.toIntOrNull() ?: 0)
            }
        }
        
        val pendingIntent = PendingIntent.getActivity(
            this, 0, intent,
            PendingIntent.FLAG_ONE_SHOT or PendingIntent.FLAG_IMMUTABLE
        )

        val isNewOrder = data.containsKey("order_id")
        val channelId = if (isNewOrder) "orders_notification_channel_v4" else "default_notification_channel"
        val notificationBuilder = NotificationCompat.Builder(this, channelId)
            .setSmallIcon(R.mipmap.ic_launcher)
            .setContentTitle(title)
            .setContentText(messageBody)
            .setAutoCancel(true)
            .setContentIntent(pendingIntent)
            .setPriority(NotificationCompat.PRIORITY_HIGH)

        if (isNewOrder) {
            val soundUri = android.net.Uri.parse("android.resource://" + packageName + "/raw/order_ching")
            notificationBuilder.setSound(soundUri)
            
            // Also play in-app if running and preloaded
            try {
                de.meinseelenfunke.app.util.SoundManager.playOrderChingSound()
            } catch (e: Exception) {
                Log.e(tag, "Failed to play order ching via SoundManager", e)
            }
        }

        val notificationManager = getSystemService(Context.NOTIFICATION_SERVICE) as NotificationManager

        // Notification channel is needed on Android Oreo (API 26) and above
        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.O) {
            if (isNewOrder) {
                val soundUri = android.net.Uri.parse("android.resource://" + packageName + "/raw/order_ching")
                val audioAttributes = android.media.AudioAttributes.Builder()
                    .setUsage(android.media.AudioAttributes.USAGE_NOTIFICATION)
                    .setContentType(android.media.AudioAttributes.CONTENT_TYPE_SONIFICATION)
                    .build()
                
                val channel = NotificationChannel(
                    "orders_notification_channel_v4",
                    "Bestellungen",
                    NotificationManager.IMPORTANCE_HIGH
                ).apply {
                    setSound(soundUri, audioAttributes)
                    enableLights(true)
                    enableVibration(true)
                }
                notificationManager.createNotificationChannel(channel)
            } else {
                val channel = NotificationChannel(
                    "default_notification_channel",
                    "Standard Benachrichtigungen",
                    NotificationManager.IMPORTANCE_DEFAULT
                )
                notificationManager.createNotificationChannel(channel)
            }
        }

        notificationManager.notify(System.currentTimeMillis().toInt(), notificationBuilder.build())
    }
}
