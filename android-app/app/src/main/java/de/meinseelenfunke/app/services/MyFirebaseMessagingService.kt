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
        ServiceLocator.ensureInitialized(applicationContext)

        // Save the token locally in AndroidKeyStore-backed storage
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
        ServiceLocator.ensureInitialized(context)
        if (!ServiceLocator.authRepository.isLoggedIn()) {
            return
        }

        // Check if it's a silent sync command
        if (remoteMessage.data["action"] == "sync_calendar") {
            CoroutineScope(Dispatchers.IO).launch {
                try {
                    ServiceLocator.organizerRepository.getCalendarEvents()
                } catch (e: Exception) {
                    de.meinseelenfunke.app.util.AppLogger.error(context, "FCM", "Silent calendar sync failed", e)
                }
            }
            return
        }

        // Check if message contains data payload
        if (remoteMessage.data.isNotEmpty()) {
            val title = remoteMessage.data["title"] ?: "Neue Meldung"
            val body = remoteMessage.data["body"] ?: ""
            sendNotification(title, body, remoteMessage.data)
        } else {
            remoteMessage.notification?.let {
                sendNotification(it.title ?: "Neue Meldung", it.body ?: "", emptyMap())
            }
        }
    }

    private fun sendNotification(title: String, messageBody: String, data: Map<String, String>) {
        val isNewOrder = data.containsKey("order_id")
        val intent = Intent(this, MainActivity::class.java).apply {
            flags = Intent.FLAG_ACTIVITY_CLEAR_TOP or Intent.FLAG_ACTIVITY_SINGLE_TOP
            if (!data["navigate_to"].isNullOrBlank()) {
                putExtra("navigate_to", data["navigate_to"])
            } else if (isNewOrder && !data.containsKey("open_tab")) {
                putExtra("navigate_to", "orders")
            }
            if (!data["email_id"].isNullOrBlank()) {
                putExtra("email_id", data["email_id"])
            }
            if (data.containsKey("open_tab")) {
                putExtra("open_tab", (data["open_tab"]?.toIntOrNull() ?: 0).coerceIn(0, 5))
            }
            if (data.containsKey("open_subtab")) {
                putExtra("open_subtab", (data["open_subtab"]?.toIntOrNull() ?: 0).coerceIn(0, 6))
            }
            de.meinseelenfunke.app.util.SecurityIntentHelper.signIntent(this@MyFirebaseMessagingService, this)
        }

        val pendingIntent = PendingIntent.getActivity(
            this, (System.currentTimeMillis() and 0x7FFFFFFF).toInt(), intent,
            PendingIntent.FLAG_ONE_SHOT or PendingIntent.FLAG_IMMUTABLE
        )

        val channelId = if (isNewOrder) "orders_notification_channel_v4" else "default_notification_channel"
        val publicNotification = NotificationCompat.Builder(this, channelId)
            .setSmallIcon(R.mipmap.ic_launcher)
            .setContentTitle("Funkenwerk")
            .setContentText(if (isNewOrder) "Neue Bestellung eingegangen" else "Neue Benachrichtigung")
            .setPriority(NotificationCompat.PRIORITY_HIGH)
            .build()

        val notificationBuilder = NotificationCompat.Builder(this, channelId)
            .setSmallIcon(R.mipmap.ic_launcher)
            .setContentTitle(title)
            .setContentText(messageBody)
            .setAutoCancel(true)
            .setContentIntent(pendingIntent)
            .setVisibility(NotificationCompat.VISIBILITY_PRIVATE)
            .setPublicVersion(publicNotification)
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
