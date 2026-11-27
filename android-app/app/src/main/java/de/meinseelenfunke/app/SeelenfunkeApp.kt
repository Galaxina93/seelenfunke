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
        de.meinseelenfunke.app.util.ShopWatchtowerManager.ensureNotificationChannel(this)
        de.meinseelenfunke.app.util.ShopWatchtowerManager.schedulePeriodicWatchtower(this)

        // Auto-start background "Hey Falcone" wake-word listening service if enabled and permitted
        de.meinseelenfunke.app.services.WakeWordService.syncServiceState(this)
    }

    private fun createNotificationChannels() {
        de.meinseelenfunke.app.util.NotificationBadgeGuard.ensureSilentBackgroundChannels(this)
        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.O) {
            val notificationManager = getSystemService(Context.NOTIFICATION_SERVICE) as? NotificationManager ?: return
            
            // 1. Create orders channel with high importance and custom sound
            val soundUri = Uri.parse("android.resource://" + packageName + "/raw/order_ching")
            val audioAttributes = AudioAttributes.Builder()
                .setUsage(AudioAttributes.USAGE_NOTIFICATION)
                .setContentType(AudioAttributes.CONTENT_TYPE_SONIFICATION)
                .build()
            
            val orderChannelIds = listOf(
                "orders_notification_channel",
                "orders_notification_channel_v2",
                "orders_notification_channel_v3",
                "orders_notification_channel_v4"
            )
            for (channelId in orderChannelIds) {
                val orderChannel = NotificationChannel(
                    channelId,
                    "Bestellungen",
                    NotificationManager.IMPORTANCE_HIGH
                ).apply {
                    setSound(soundUri, audioAttributes)
                    enableLights(true)
                    enableVibration(true)
                    description = "Kanal für neue Bestellungen"
                }
                notificationManager.createNotificationChannel(orderChannel)
            }

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
