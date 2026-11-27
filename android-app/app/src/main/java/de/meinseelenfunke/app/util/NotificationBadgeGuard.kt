package de.meinseelenfunke.app.util

import android.app.Notification
import android.app.NotificationChannel
import android.app.NotificationManager
import android.content.Context
import android.os.Build

/**
 * Central Android Notification-Channel & App-Icon-Badge Governance.
 *
 * Background/foreground status services and transient triggers must never leave a permanent
 * notification dot (badge) on the launcher icon. Because Android 8.0+ treats channel `showBadge`
 * as immutable once created, legacy badge-enabled status channels are migrated to `_v2` IDs
 * with `setShowBadge(false)` and the legacy channels are deleted at startup.
 */
object NotificationBadgeGuard {

    const val WAKEWORD_STATUS_CHANNEL_ID = "seelenfunke_wakeword_channel_v2"
    const val WAKEWORD_TRIGGER_CHANNEL_ID = "seelenfunke_trigger_channel_v2"
    const val PHONE_CONCIERGE_STATUS_CHANNEL_ID = "funkira_phone_concierge_status_v2"

    const val LEGACY_WAKEWORD_CHANNEL_ID = "seelenfunke_wakeword_channel"
    const val LEGACY_TRIGGER_CHANNEL_ID = "seelenfunke_trigger_channel"

    val LEGACY_BADGE_POLLUTING_CHANNELS = listOf(
        LEGACY_WAKEWORD_CHANNEL_ID,
        LEGACY_TRIGGER_CHANNEL_ID
    )

    val SILENT_STATUS_CHANNEL_IDS = setOf(
        WAKEWORD_STATUS_CHANNEL_ID,
        WAKEWORD_TRIGGER_CHANNEL_ID,
        PHONE_CONCIERGE_STATUS_CHANNEL_ID,
        "funkenwerk_media_session_channel_v2",
        "funkenwerk_alarm_status_channel"
    )

    fun stableDedupeNotificationId(dedupeKey: String): Int =
        (dedupeKey.hashCode() and 0x7FFFFFFF).coerceAtLeast(2000)

    fun ensureSilentBackgroundChannels(context: Context) {
        if (Build.VERSION.SDK_INT < Build.VERSION_CODES.O) return
        val nm = context.applicationContext.getSystemService(Context.NOTIFICATION_SERVICE) as? NotificationManager
            ?: return

        // Delete legacy channels whose immutable showBadge=true polluted the launcher dot
        for (legacyId in LEGACY_BADGE_POLLUTING_CHANNELS) {
            try {
                nm.deleteNotificationChannel(legacyId)
            } catch (_: Throwable) {
            }
        }

        val wakewordChannel = NotificationChannel(
            WAKEWORD_STATUS_CHANNEL_ID,
            "Hey Funkira Sprachaktivierung",
            NotificationManager.IMPORTANCE_LOW
        ).apply {
            description = "Hintergrunddienst für Hey Funkira"
            setShowBadge(false)
            enableLights(false)
            enableVibration(false)
            setSound(null, null)
        }
        nm.createNotificationChannel(wakewordChannel)

        val triggerChannel = NotificationChannel(
            WAKEWORD_TRIGGER_CHANNEL_ID,
            "Funkira Aktivierung",
            NotificationManager.IMPORTANCE_HIGH
        ).apply {
            description = "Direktstart nach Hey Funkira Aktivierung"
            setShowBadge(false)
            lockscreenVisibility = Notification.VISIBILITY_PRIVATE
            enableVibration(true)
        }
        nm.createNotificationChannel(triggerChannel)

        val conciergeStatusChannel = NotificationChannel(
            PHONE_CONCIERGE_STATUS_CHANNEL_ID,
            "Funkira Anruf-Status",
            NotificationManager.IMPORTANCE_LOW
        ).apply {
            description = "Aktiver Telefon-Assistent Status während eines Gesprächs"
            setShowBadge(false)
            enableLights(false)
            enableVibration(false)
            setSound(null, null)
        }
        nm.createNotificationChannel(conciergeStatusChannel)
    }

    fun clearObsoleteStatusNotifications(context: Context, triggerNotificationId: Int) {
        val appCtx = context.applicationContext
        val nm = appCtx.getSystemService(Context.NOTIFICATION_SERVICE) as? NotificationManager ?: return

        try {
            nm.cancel(triggerNotificationId)
        } catch (_: Throwable) {
        }

        try {
            ShopWatchtowerManager.clearResolvedNotification(appCtx)
        } catch (_: Throwable) {
        }

        try {
            if (PhoneConciergeManager.refreshUnseenCount(appCtx) == 0) {
                nm.cancel(de.meinseelenfunke.app.services.FunkiraPhoneConciergeService.SUMMARY_NOTIF_ID)
            }
        } catch (_: Throwable) {
        }

        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.M) {
            try {
                val active = nm.activeNotifications ?: return
                for (sbn in active) {
                    val notif = sbn.notification ?: continue
                    val isOngoing = (notif.flags and Notification.FLAG_ONGOING_EVENT) != 0
                    if (isOngoing) continue

                    val channelId = if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.O) {
                        notif.channelId.orEmpty()
                    } else {
                        ""
                    }
                    if (channelId in LEGACY_BADGE_POLLUTING_CHANNELS || channelId == WAKEWORD_TRIGGER_CHANNEL_ID) {
                        nm.cancel(sbn.tag, sbn.id)
                    }
                }
            } catch (_: Throwable) {
            }
        }
    }
}
