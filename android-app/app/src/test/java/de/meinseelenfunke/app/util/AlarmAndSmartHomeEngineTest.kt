package de.meinseelenfunke.app.util

import de.meinseelenfunke.app.services.WakeWordService
import org.junit.Assert.assertEquals
import org.junit.Assert.assertFalse
import org.junit.Assert.assertNull
import org.junit.Assert.assertTrue
import org.junit.Test
import kotlin.math.PI
import kotlin.math.sin

class AlarmAndSmartHomeEngineTest {

    @Test
    fun formatsGermanRemainingDurationAccurately() {
        assertEquals(
            "Wecker klingelt in weniger als 1 Minute",
            FunkenwerkAlarmManager.formatRemainingDurationText(15_000L)
        )
        assertEquals(
            "Wecker klingelt in 1 Minute",
            FunkenwerkAlarmManager.formatRemainingDurationText(60_000L)
        )
        assertEquals(
            "Wecker klingelt in 25 Minuten",
            FunkenwerkAlarmManager.formatRemainingDurationText(25 * 60_000L)
        )
        assertEquals(
            "Wecker klingelt in 2 Stunden",
            FunkenwerkAlarmManager.formatRemainingDurationText(2 * 3_600_000L)
        )
        assertEquals(
            "Wecker klingelt in 2 Stunden und 15 Minuten",
            FunkenwerkAlarmManager.formatRemainingDurationText(2 * 3_600_000L + 15 * 60_000L)
        )
        assertEquals(
            "Wecker klingelt in 1 Tag, 3 Stunden und 5 Minuten",
            FunkenwerkAlarmManager.formatRemainingDurationText(86_400_000L + 3 * 3_600_000L + 5 * 60_000L)
        )
    }

    @Test
    fun computesRoutineShiftAndCompressesLateEveningSteps() {
        val today = "2026-11-14"
        val syncedAlarms = listOf(
            SmartAlarmItem(
                id = "alarm_1",
                hour = 7,
                minute = 45,
                label = "Morgenfokus",
                isEnabled = true,
                syncWithRoutine = true
            )
        )
        val (shiftMins, wakeStr) = FunkenwerkAlarmManager.computeTodayRoutineShift(
            firstRoutineBaseStartTime = "07:00",
            overrideWakeLogs = emptyMap(),
            overrideAlarms = syncedAlarms,
            overrideTodayIso = today
        )
        assertEquals(45, shiftMins)
        assertEquals("07:45", wakeStr)

        assertEquals("09:15", FunkenwerkAlarmManager.shiftRoutineTimeString("08:30", 45))
        assertEquals("21:51", FunkenwerkAlarmManager.shiftRoutineTimeString("21:30", 60))

        val unsyncedAlarms = listOf(
            SmartAlarmItem(
                id = "alarm_2",
                hour = 9,
                minute = 0,
                label = "Wochenende",
                isEnabled = true,
                syncWithRoutine = false
            )
        )
        val (noShift, noWake) = FunkenwerkAlarmManager.computeTodayRoutineShift(
            firstRoutineBaseStartTime = "07:00",
            overrideWakeLogs = emptyMap(),
            overrideAlarms = unsyncedAlarms,
            overrideTodayIso = today
        )
        assertEquals(0, noShift)
        assertNull(noWake)
    }

    @Test
    fun blocksNonPrivateAndLoopbackAndLinkLocalSmartPlugIpsAgainstSsrf() {
        val blockedIps = listOf(
            "169.254.169.254",
            "127.0.0.1",
            "8.8.8.8",
            "1.1.1.1",
            "0.0.0.0",
            "255.255.255.255",
            "172.32.0.1",
            "172.15.255.1",
            "192.169.1.1",
            "192.168.188.999",
            "localhost",
            "http://192.168.188.109"
        )
        for (ip in blockedIps) {
            assertFalse("SSRF guard must reject non-private IPv4: $ip", FunkenwerkAlarmManager.isValidPrivateLanIpv4(ip))
        }

        val allowedPrivateLanIps = listOf(
            "192.168.188.109",
            "192.168.188.110",
            "10.0.0.15",
            "172.16.0.10",
            "172.31.255.250"
        )
        for (ip in allowedPrivateLanIps) {
            assertTrue("Valid RFC1918 LAN IPv4 should be allowed: $ip", FunkenwerkAlarmManager.isValidPrivateLanIpv4(ip))
        }
    }

    @Test
    fun alarmSyncResolvesSingleEnvironmentWithoutCrossSprayOrReleaseCleartext() {
        assertEquals(
            "https://stage.mein-seelenfunke.de/api/funki/routine/alarm-sync",
            FunkenwerkAlarmManager.resolveAlarmSyncEndpointUrl(
                configuredBaseUrl = "https://stage.mein-seelenfunke.de/api/",
                isDebugBuild = false
            )
        )

        assertEquals(
            "https://mein-seelenfunke.de/api/funki/routine/alarm-sync",
            FunkenwerkAlarmManager.resolveAlarmSyncEndpointUrl(
                configuredBaseUrl = "https://mein-seelenfunke.de/api/",
                isDebugBuild = false
            )
        )

        assertNull(
            "Cleartext LAN URL must never resolve as an authenticated alarm-sync endpoint in release",
            FunkenwerkAlarmManager.resolveAlarmSyncEndpointUrl(
                configuredBaseUrl = "http://192.168.188.119/api/",
                isDebugBuild = false
            )
        )

        assertNull(
            FunkenwerkAlarmManager.resolveAlarmSyncEndpointUrl(
                configuredBaseUrl = "https://evil.com/api/",
                isDebugBuild = true
            )
        )
    }

    @Test
    fun wakeWordCadenceGateRejectsSingleNoiseSpikeAndHighFrequencyHiss() {
        val sampleRate = 16_000
        val frameSize = 800

        // Voiced speech-band frame (~220 Hz fundamental -> ZCR ~ 0.0275, amplitude 7000 -> RMS ~ 4950)
        val voicedFrame = ShortArray(frameSize) { i ->
            (7000.0 * sin(2.0 * PI * 220.0 * i / sampleRate)).toInt().toShort()
        }
        assertTrue(WakeWordService.isVoicedSpeechFrame(voicedFrame))

        // Unvoiced high-frequency hiss/transient (alternating signs every sample -> ZCR ~ 1.0)
        val hissFrame = ShortArray(frameSize) { i ->
            if (i % 2 == 0) 8000 else -8000
        }
        assertFalse(WakeWordService.isVoicedSpeechFrame(hissFrame))

        // Silence frame
        val silenceFrame = ShortArray(frameSize) { 0 }
        assertFalse(WakeWordService.isVoicedSpeechFrame(silenceFrame))

        val gate = WakeWordService.Companion.SpeechCadenceGate(requiredVoicedFrames = 3, windowSize = 8)

        // Single loud spike followed by silence must NOT trigger wake-word activation
        assertFalse(gate.onFrame(voicedFrame))
        for (i in 0 until 8) {
            assertFalse(gate.onFrame(silenceFrame))
        }

        // Loud high-frequency hiss across 6 frames must NOT trigger
        for (i in 0 until 6) {
            assertFalse(gate.onFrame(hissFrame))
        }

        // Sustained 3-frame voiced speech cadence DOES trigger
        assertFalse(gate.onFrame(voicedFrame))
        assertFalse(gate.onFrame(voicedFrame))
        assertTrue(gate.onFrame(voicedFrame))
    }
}
