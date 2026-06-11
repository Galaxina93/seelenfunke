package de.meinseelenfunke.app

import android.app.Application
import android.app.NotificationChannel
import android.app.NotificationManager
import android.content.Context
import android.media.AudioAttributes
import android.net.Uri
import android.os.Build
import de.meinseelenfunke.app.di.ServiceLocator
import de.meinseelenfunke.app.util.SoundManager

class SeelenfunkeApp : Application() {
    override fun onCreate() {
        super.onCreate()
        // Initialize the dependency injection service locator
        ServiceLocator.init(this)
        // Initialize the sound manager helper
        SoundManager.init(this)
        // Initialize the real-time Email WebSocket Client
        de.meinseelenfunke.app.data.EmailWebSocketClient.init(this)

        // Initialize Notification Channels on Android Oreo (API 26) and above
        createNotificationChannels()

        // Auto-start background wake-word listening service if enabled
        if (ServiceLocator.isWakeWordEnabled()) {
            val intent = android.content.Intent(this, de.meinseelenfunke.app.services.WakeWordService::class.java)
            try {
                if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.O) {
                    startForegroundService(intent)
                } else {
                    startService(intent)
                }
            } catch (e: Exception) {
                e.printStackTrace()
            }
        }
    }

    private fun createNotificationChannels() {
        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.O) {
            val notificationManager = getSystemService(Context.NOTIFICATION_SERVICE) as NotificationManager
            
            // 1. Create orders channel with high importance and custom sound
            val soundUri = Uri.parse("android.resource://" + packageName + "/" + R.raw.order_ching)
            val audioAttributes = AudioAttributes.Builder()
                .setUsage(AudioAttributes.USAGE_NOTIFICATION)
                .setContentType(AudioAttributes.CONTENT_TYPE_SONIFICATION)
                .build()
            
            val orderChannel = NotificationChannel(
                "orders_notification_channel",
                "Bestellungen",
                NotificationManager.IMPORTANCE_HIGH
            ).apply {
                setSound(soundUri, audioAttributes)
                enableLights(true)
                enableVibration(true)
                description = "Kanal für neue Bestellungen"
            }
            notificationManager.createNotificationChannel(orderChannel)

            // 2. Create default channel
            val defaultChannel = NotificationChannel(
                "default_notification_channel",
                "Standard Benachrichtigungen",
                NotificationManager.IMPORTANCE_DEFAULT
            ).apply {
                description = "Kanal für Standard Systemmeldungen"
            }
            notificationManager.createNotificationChannel(defaultChannel)
        }
    }
}
