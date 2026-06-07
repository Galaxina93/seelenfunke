package de.meinseelenfunke.app.receiver

import android.app.NotificationChannel
import android.app.NotificationManager
import android.app.PendingIntent
import android.content.BroadcastReceiver
import android.content.Context
import android.content.Intent
import android.os.Build
import androidx.core.app.NotificationCompat
import de.meinseelenfunke.app.MainActivity

class CalendarAlarmReceiver : BroadcastReceiver() {
    companion object {
        const val CHANNEL_ID = "seelenfunke_calendar_notifications"
        const val CHANNEL_NAME = "Seelenfunke Kalender-Termine"
    }

    override fun onReceive(context: Context, intent: Intent) {
        val eventId = intent.getStringExtra("event_id") ?: ""
        val eventTitle = intent.getStringExtra("event_title") ?: "Termin"
        val eventDesc = intent.getStringExtra("event_desc") ?: ""
        val eventTime = intent.getStringExtra("event_time") ?: ""

        val notificationManager = context.getSystemService(Context.NOTIFICATION_SERVICE) as NotificationManager

        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.O) {
            val channel = NotificationChannel(
                CHANNEL_ID,
                CHANNEL_NAME,
                NotificationManager.IMPORTANCE_HIGH
            ).apply {
                description = "Meldungen für anstehende Termine und Routinen"
                enableLights(true)
                enableVibration(true)
            }
            notificationManager.createNotificationChannel(channel)
        }

        val clickIntent = Intent(context, MainActivity::class.java).apply {
            flags = Intent.FLAG_ACTIVITY_NEW_TASK or Intent.FLAG_ACTIVITY_CLEAR_TASK
        }
        
        val pendingIntent = PendingIntent.getActivity(
            context,
            eventId.hashCode(),
            clickIntent,
            PendingIntent.FLAG_UPDATE_CURRENT or PendingIntent.FLAG_IMMUTABLE
        )

        val contentText = if (eventTime.isNotBlank()) "Beginnt um $eventTime. $eventDesc" else eventDesc
        val notification = NotificationCompat.Builder(context, CHANNEL_ID)
            .setSmallIcon(android.R.drawable.ic_lock_idle_alarm)
            .setContentTitle(eventTitle)
            .setContentText(contentText)
            .setPriority(NotificationCompat.PRIORITY_HIGH)
            .setContentIntent(pendingIntent)
            .setAutoCancel(true)
            .build()

        notificationManager.notify(eventId.hashCode(), notification)
    }
}
