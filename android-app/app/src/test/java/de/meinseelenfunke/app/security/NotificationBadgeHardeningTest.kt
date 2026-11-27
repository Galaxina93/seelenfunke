package de.meinseelenfunke.app.security

import android.app.NotificationChannel
import android.app.NotificationManager
import android.content.Context
import androidx.core.app.NotificationCompat
import de.meinseelenfunke.app.R
import de.meinseelenfunke.app.data.EmailWebSocketClient
import de.meinseelenfunke.app.services.MyFirebaseMessagingService
import de.meinseelenfunke.app.services.WakeWordService
import de.meinseelenfunke.app.util.NotificationBadgeGuard
import de.meinseelenfunke.app.util.ShopWatchtowerManager
import org.json.JSONObject
import org.junit.Assert.assertEquals
import org.junit.Assert.assertFalse
import org.junit.Assert.assertNotNull
import org.junit.Assert.assertNull
import org.junit.Assert.assertTrue
import org.junit.Test
import org.junit.runner.RunWith
import org.robolectric.RobolectricTestRunner
import org.robolectric.RuntimeEnvironment
import org.robolectric.annotation.Config
import java.io.File

@RunWith(RobolectricTestRunner::class)
@Config(sdk = [34])
class NotificationBadgeHardeningTest {

    @Test
    fun silentBackgroundChannels_disableBadgesAndDeleteLegacyChannels() {
        val context: Context = RuntimeEnvironment.getApplication()
        val nm = context.getSystemService(Context.NOTIFICATION_SERVICE) as NotificationManager

        // Simulate a previously installed app that registered the legacy badge-polluting channels
        for (legacyId in NotificationBadgeGuard.LEGACY_BADGE_POLLUTING_CHANNELS) {
            nm.createNotificationChannel(
                NotificationChannel(legacyId, "Legacy $legacyId", NotificationManager.IMPORTANCE_LOW)
            )
            assertNotNull("Precondition: legacy channel $legacyId exists", nm.getNotificationChannel(legacyId))
        }

        NotificationBadgeGuard.ensureSilentBackgroundChannels(context)

        for (legacyId in NotificationBadgeGuard.LEGACY_BADGE_POLLUTING_CHANNELS) {
            assertNull(
                "Legacy channel $legacyId with immutable showBadge=true must be deleted on startup",
                nm.getNotificationChannel(legacyId)
            )
        }

        val wakewordChannel = nm.getNotificationChannel(NotificationBadgeGuard.WAKEWORD_STATUS_CHANNEL_ID)
        assertNotNull("WakeWord v2 status channel must exist", wakewordChannel)
        assertFalse("WakeWord status channel must never show an app icon badge", wakewordChannel!!.canShowBadge())

        val triggerChannel = nm.getNotificationChannel(NotificationBadgeGuard.WAKEWORD_TRIGGER_CHANNEL_ID)
        assertNotNull("WakeWord trigger v2 channel must exist", triggerChannel)
        assertFalse("WakeWord trigger channel must never show an app icon badge", triggerChannel!!.canShowBadge())

        val conciergeStatus = nm.getNotificationChannel(NotificationBadgeGuard.PHONE_CONCIERGE_STATUS_CHANNEL_ID)
        assertNotNull("Phone concierge status v2 channel must exist", conciergeStatus)
        assertFalse("Phone concierge ongoing status channel must never show an app icon badge", conciergeStatus!!.canShowBadge())
    }

    @Test
    fun clearObsoleteStatusNotifications_cancelsTriggerAndResolvedWatchtowerButPreservesOpenAlert() {
        val context: Context = RuntimeEnvironment.getApplication()
        val nm = context.getSystemService(Context.NOTIFICATION_SERVICE) as NotificationManager
        NotificationBadgeGuard.ensureSilentBackgroundChannels(context)
        ShopWatchtowerManager.ensureNotificationChannel(context)

        val triggerNotif = NotificationCompat.Builder(context, NotificationBadgeGuard.WAKEWORD_TRIGGER_CHANNEL_ID)
            .setSmallIcon(R.drawable.ic_stat_funkenwerk)
            .setContentTitle("Funkira gerufen")
            .setContentText("Live-Gespräch startet")
            .build()
        nm.notify(WakeWordService.TRIGGER_NOTIFICATION_ID, triggerNotif)

        val watchtowerNotif = NotificationCompat.Builder(context, ShopWatchtowerManager.CHANNEL_ID)
            .setSmallIcon(R.drawable.ic_stat_funkenwerk)
            .setContentTitle("Entwarnung")
            .setContentText("Shop wieder grün")
            .build()
        nm.notify(ShopWatchtowerManager.NOTIFICATION_ID, watchtowerNotif)

        // Ensure watchtower alert flag is closed -> both should be cancelled on resume
        val prefs = context.getSharedPreferences("seelenfunke_watchtower_state", Context.MODE_PRIVATE)
        prefs.edit().putBoolean("alert_open", false).commit()

        NotificationBadgeGuard.clearObsoleteStatusNotifications(context, WakeWordService.TRIGGER_NOTIFICATION_ID)

        val activeIdsAfterClear = nm.activeNotifications.map { it.id }.toSet()
        assertFalse(activeIdsAfterClear.contains(WakeWordService.TRIGGER_NOTIFICATION_ID))
        assertFalse(activeIdsAfterClear.contains(ShopWatchtowerManager.NOTIFICATION_ID))

        // If a genuine outage alert IS open, clearResolvedNotification must keep it visible
        prefs.edit().putBoolean("alert_open", true).commit()
        nm.notify(ShopWatchtowerManager.NOTIFICATION_ID, watchtowerNotif)
        assertFalse(ShopWatchtowerManager.clearResolvedNotification(context))
        assertTrue(nm.activeNotifications.any { it.id == ShopWatchtowerManager.NOTIFICATION_ID })
    }

    @Test
    fun fcmPayloadGuard_suppressesSilentAndEmptyGhostNotifications() {
        assertNull(
            MyFirebaseMessagingService.resolveVisibleNotificationPayload(
                data = mapOf("action" to "sync_calendar"),
                notificationTitle = null,
                notificationBody = null
            )
        )
        assertNull(
            MyFirebaseMessagingService.resolveVisibleNotificationPayload(
                data = mapOf("action" to "sync_orders", "title" to "Ignored", "body" to "Silent"),
                notificationTitle = "Ignored",
                notificationBody = "Silent"
            )
        )
        assertNull(
            MyFirebaseMessagingService.resolveVisibleNotificationPayload(
                data = mapOf("silent" to "true", "title" to "Keep hidden", "body" to "Hidden"),
                notificationTitle = null,
                notificationBody = null
            )
        )
        assertNull(
            MyFirebaseMessagingService.resolveVisibleNotificationPayload(
                data = mapOf("type" to "heartbeat_meta"),
                notificationTitle = "   ",
                notificationBody = ""
            )
        )

        val orderPayload = MyFirebaseMessagingService.resolveVisibleNotificationPayload(
            data = mapOf(
                "order_id" to "ord-42",
                "title" to "Neue Bestellung",
                "body" to "Bestellung #SF-42"
            ),
            notificationTitle = null,
            notificationBody = null
        )
        assertNotNull(orderPayload)
        assertTrue(orderPayload!!.isNewOrder)
        assertEquals("orders_notification_channel_v4", orderPayload.channelId)
        assertEquals("Neue Bestellung", orderPayload.title)
        assertEquals("Bestellung #SF-42", orderPayload.body)

        val duplicateOrderPayload = MyFirebaseMessagingService.resolveVisibleNotificationPayload(
            data = mapOf(
                "order_id" to "ord-42",
                "title" to "Neue Bestellung",
                "body" to "Bestellung #SF-42"
            ),
            notificationTitle = null,
            notificationBody = null
        )
        assertEquals(orderPayload.notificationId, duplicateOrderPayload!!.notificationId)
    }

    @Test
    fun emailWebSocketPayloadGuard_rejectsEmptyAndLiteralNullPayloads() {
        assertNull(EmailWebSocketClient.extractVisibleEmailPayload(null))
        assertNull(EmailWebSocketClient.extractVisibleEmailPayload(JSONObject()))
        assertNull(
            EmailWebSocketClient.extractVisibleEmailPayload(
                JSONObject().apply {
                    put("id", "")
                    put("from", JSONObject.NULL)
                    put("subject", "  ")
                    put("snippet", "null")
                }
            )
        )

        val valid = EmailWebSocketClient.extractVisibleEmailPayload(
            JSONObject().apply {
                put("id", "mail-99")
                put("from", "kunde@example.de")
                put("subject", "Anfrage Gravur")
                put("snippet", "Hallo Team")
            }
        )
        assertNotNull(valid)
        assertEquals("kunde@example.de", valid!!.from)
        assertEquals("Anfrage Gravur", valid.subject)
        assertEquals("Hallo Team", valid.snippet)
    }

    @Test
    fun backgroundServiceSources_doNotRegressToMissingSetShowBadgeFalse() {
        val srcRoot = listOf(File("src/main/java"), File("app/src/main/java"))
            .firstOrNull { it.exists() && it.isDirectory }
        assertNotNull("Expected src/main/java directory", srcRoot)

        val wakeWordText = File(srcRoot, "de/meinseelenfunke/app/services/WakeWordService.kt").readText()
        assertFalse(
            "WakeWordService must not reference legacy badge-polluting channel string directly",
            wakeWordText.contains("\"seelenfunke_wakeword_channel\"")
        )
        assertFalse(
            "WakeWordService must not reference legacy trigger channel string directly",
            wakeWordText.contains("\"seelenfunke_trigger_channel\"")
        )
        assertTrue(
            "WakeWordService must enforce setShowBadge(false)",
            wakeWordText.contains("setShowBadge(false)")
        )

        val musicText = File(srcRoot, "de/meinseelenfunke/app/services/MusicPlaybackService.kt").readText()
        assertTrue("MusicPlaybackService must keep setShowBadge(false)", musicText.contains("setShowBadge(false)"))
        assertTrue(
            "SILENT_STATUS_CHANNEL_IDS must track MusicPlaybackService channel",
            NotificationBadgeGuard.SILENT_STATUS_CHANNEL_IDS.contains("funkenwerk_media_session_channel_v2")
        )
    }

    @Test
    fun markAllCallsSeen_cancelsSecretarySummaryAndMatchingCallPushNotification() {
        val context: Context = RuntimeEnvironment.getApplication()
        val nm = context.getSystemService(Context.NOTIFICATION_SERVICE) as NotificationManager
        NotificationBadgeGuard.ensureSilentBackgroundChannels(context)

        val summaryNotif = NotificationCompat.Builder(context, "funkira_phone_concierge_channel")
            .setSmallIcon(R.drawable.ic_stat_funkenwerk)
            .setContentTitle("📞 Funkira Sekretärin · Test")
            .setContentText("Rückruf gewünscht")
            .build()
        nm.notify(de.meinseelenfunke.app.services.FunkiraPhoneConciergeService.SUMMARY_NOTIF_ID, summaryNotif)

        val callPushId = NotificationBadgeGuard.stableDedupeNotificationId("call:call-777")
        val callPushNotif = NotificationCompat.Builder(context, "default_notification_channel")
            .setSmallIcon(R.drawable.ic_stat_funkenwerk)
            .setContentTitle("📞 Funkira · Test")
            .setContentText("Nachricht")
            .build()
        nm.notify(callPushId, callPushNotif)

        val callDto = de.meinseelenfunke.app.data.api.PhoneSecretaryCallSummaryDto(
            id = "call-777",
            caller_name = "Test",
            summary = "Nachricht",
            urgency = "normal",
            callback_requested = false
        )
        de.meinseelenfunke.app.util.PhoneConciergeManager.markAllCallsSeen(context, listOf(callDto))

        val activeIds = nm.activeNotifications.map { it.id }.toSet()
        assertFalse(activeIds.contains(de.meinseelenfunke.app.services.FunkiraPhoneConciergeService.SUMMARY_NOTIF_ID))
        assertFalse(activeIds.contains(callPushId))
    }
}
