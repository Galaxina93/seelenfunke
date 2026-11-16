package de.meinseelenfunke.app.util

import org.junit.Assert.assertEquals
import org.junit.Assert.assertFalse
import org.junit.Assert.assertTrue
import org.junit.Test

class ShopWatchtowerManagerTest {

    @Test
    fun `only admin role with valid token is eligible for watchtower checks`() {
        assertTrue(ShopWatchtowerManager.isEligibleUserRole("admin", "12|validSanctumToken"))
        assertTrue(ShopWatchtowerManager.isEligibleUserRole("ADMIN", "12|validSanctumToken"))
        assertFalse(ShopWatchtowerManager.isEligibleUserRole("admin", ""))
        assertFalse(ShopWatchtowerManager.isEligibleUserRole("admin", null))
        assertFalse(ShopWatchtowerManager.isEligibleUserRole("customer", "12|validSanctumToken"))
        assertFalse(ShopWatchtowerManager.isEligibleUserRole("employee", "12|validSanctumToken"))
        assertFalse(ShopWatchtowerManager.isEligibleUserRole("influencer", "12|validSanctumToken"))
        assertFalse(ShopWatchtowerManager.isEligibleUserRole("tax_advisor", "12|validSanctumToken"))
    }

    @Test
    fun `suppresses false alarm when smartphone itself is offline`() {
        val decision = ShopWatchtowerManager.evaluateOutcome(
            shopReachable = false,
            httpCode = 0,
            pulseStatus = null,
            pulseHeadline = null,
            internetOnline = false,
            previousStrikes = 0,
            wasAlertOpen = false,
            lastAlertMs = 0L,
            nowMs = 100_000L
        )
        assertEquals("suppressed_offline", decision.action)
        assertEquals(0, decision.newStrikes)
        assertFalse(decision.newAlertOpen)
    }

    @Test
    fun `requires two consecutive outages before alerting and notifies recovery when healthy again`() {
        val strike1 = ShopWatchtowerManager.evaluateOutcome(
            shopReachable = true,
            httpCode = 503,
            pulseStatus = null,
            pulseHeadline = null,
            internetOnline = true,
            previousStrikes = 0,
            wasAlertOpen = false,
            lastAlertMs = 0L,
            nowMs = 100_000L
        )
        assertEquals("pending_strike", strike1.action)
        assertEquals(1, strike1.newStrikes)
        assertFalse(strike1.newAlertOpen)

        val strike2 = ShopWatchtowerManager.evaluateOutcome(
            shopReachable = true,
            httpCode = 503,
            pulseStatus = null,
            pulseHeadline = null,
            internetOnline = true,
            previousStrikes = strike1.newStrikes,
            wasAlertOpen = strike1.newAlertOpen,
            lastAlertMs = 0L,
            nowMs = 200_000L
        )
        assertEquals("notify_alert", strike2.action)
        assertTrue(strike2.newAlertOpen)

        val recovered = ShopWatchtowerManager.evaluateOutcome(
            shopReachable = true,
            httpCode = 200,
            pulseStatus = "ok",
            pulseHeadline = "Alle 4 Säulen grün",
            internetOnline = true,
            previousStrikes = strike2.newStrikes,
            wasAlertOpen = strike2.newAlertOpen,
            lastAlertMs = 200_000L,
            nowMs = 300_000L
        )
        assertEquals("notify_recovered", recovered.action)
        assertEquals(0, recovered.newStrikes)
        assertFalse(recovered.newAlertOpen)
    }

    @Test
    fun `alerts immediately when health pulse reports critical pillar and respects 60m cooldown`() {
        val crit1 = ShopWatchtowerManager.evaluateOutcome(
            shopReachable = true,
            httpCode = 200,
            pulseStatus = "critical",
            pulseHeadline = "Kritische Störung (Cronjobs, Backups)",
            internetOnline = true,
            previousStrikes = 0,
            wasAlertOpen = false,
            lastAlertMs = 0L,
            nowMs = 1_000_000L
        )
        assertEquals("notify_alert", crit1.action)
        assertTrue(crit1.newAlertOpen)

        val critWithinCooldown = ShopWatchtowerManager.evaluateOutcome(
            shopReachable = true,
            httpCode = 200,
            pulseStatus = "critical",
            pulseHeadline = "Kritische Störung (Cronjobs, Backups)",
            internetOnline = true,
            previousStrikes = 0,
            wasAlertOpen = true,
            lastAlertMs = 1_000_000L,
            nowMs = 1_900_000L // +15m (< 60m cooldown)
        )
        assertEquals("already_alerted", critWithinCooldown.action)
        assertTrue(critWithinCooldown.newAlertOpen)
    }
}
