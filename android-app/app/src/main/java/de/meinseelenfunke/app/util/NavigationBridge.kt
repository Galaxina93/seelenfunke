package de.meinseelenfunke.app.util

import de.meinseelenfunke.app.ui.navigation.Screen
import kotlinx.coroutines.flow.MutableSharedFlow
import kotlinx.coroutines.flow.SharedFlow
import kotlinx.coroutines.flow.asSharedFlow
import java.util.Locale

object NavigationBridge {
    // Canonical deterministic tab codes:
    // 0 = Zentrum, 1 = Finanzen, 2 = Organizer, 3 = Agenten/Chat, 4 = Einstellungen, 5 = Bestellungen (Admin)
    const val TAB_ZENTRUM = 0
    const val TAB_FINANCES = 1
    const val TAB_ORGANIZER = 2
    const val TAB_CHAT = 3
    const val TAB_SETTINGS = 4
    const val TAB_ORDERS = 5

    enum class NavTarget(val code: Int, val route: String) {
        ZENTRUM(TAB_ZENTRUM, "zentrum"),
        FINANCES(TAB_FINANCES, "finances"),
        ORGANIZER(TAB_ORGANIZER, "organizer"),
        CHAT(TAB_CHAT, "chat"),
        SETTINGS(TAB_SETTINGS, "settings"),
        ORDERS(TAB_ORDERS, "orders")
    }

    @Volatile
    var pendingTarget: NavTarget? = null

    @Volatile
    var pendingTab: Int? = null

    @Volatile
    var organizerSubTab: Int = 0

    @Volatile
    var pendingSelectedDate: String? = null

    @Volatile
    var pendingCreateEvent: Boolean = false

    @Volatile
    var pendingEmailId: String? = null

    @Volatile
    var pendingOpenQuickEntry: Boolean = false

    @Volatile
    var isLiveCallActive: Boolean = false

    @Volatile
    var pendingWakeWordTrigger: Boolean = false

    private const val DEFAULT_FAST_UNLOCK_WINDOW_MS = 15_000L

    @Volatile
    private var trustedFastUnlockGrantedAtMs: Long = 0L

    internal var elapsedRealtimeProvider: () -> Long = { android.os.SystemClock.elapsedRealtime() }

    private val _navigationTrigger = MutableSharedFlow<Unit>(extraBufferCapacity = 1)
    val navigationTrigger: SharedFlow<Unit> = _navigationTrigger.asSharedFlow()

    private val _falconExitFocusTrigger = MutableSharedFlow<Unit>(extraBufferCapacity = 1)
    val falconExitFocusTrigger: SharedFlow<Unit> = _falconExitFocusTrigger.asSharedFlow()

    private val _wakeWordTrigger = MutableSharedFlow<Unit>(extraBufferCapacity = 1)
    val wakeWordTrigger: SharedFlow<Unit> = _wakeWordTrigger.asSharedFlow()

    private val _trustedFastUnlockTrigger = MutableSharedFlow<Unit>(extraBufferCapacity = 1)
    val trustedFastUnlockTrigger: SharedFlow<Unit> = _trustedFastUnlockTrigger.asSharedFlow()

    private val _logoutTrigger = MutableSharedFlow<Unit>(extraBufferCapacity = 1)
    val logoutTrigger: SharedFlow<Unit> = _logoutTrigger.asSharedFlow()

    fun resolveTarget(tabIndex: Int): NavTarget {
        return when (tabIndex) {
            TAB_ZENTRUM -> NavTarget.ZENTRUM
            TAB_FINANCES -> NavTarget.FINANCES
            TAB_ORGANIZER -> NavTarget.ORGANIZER
            TAB_CHAT -> NavTarget.CHAT
            TAB_SETTINGS -> NavTarget.SETTINGS
            TAB_ORDERS -> NavTarget.ORDERS
            else -> NavTarget.ZENTRUM
        }
    }

    fun parseTarget(raw: String?): NavTarget? {
        if (raw.isNullOrBlank()) return null
        return when (raw.trim().lowercase(Locale.ROOT)) {
            "zentrum", "dashboard", "home" -> NavTarget.ZENTRUM
            "finances", "finance", "finanzen", "buchhaltung" -> NavTarget.FINANCES
            "orders", "order", "bestellungen", "bestellung" -> NavTarget.ORDERS
            "organizer", "tasks", "aufgaben", "calendar", "kalender", "routine", "shopping", "einkauf", "email", "mail", "music", "musik", "alarm", "wecker", "telefon", "anrufbeantworter", "phone_secretary" -> NavTarget.ORGANIZER
            "chat", "agents", "agenten", "ai" -> NavTarget.CHAT
            "settings", "einstellungen" -> NavTarget.SETTINGS
            else -> raw.trim().toIntOrNull()?.let { resolveTarget(it) }
        }
    }

    fun bottomNavScreensForRole(isAdmin: Boolean): List<Screen> {
        return if (isAdmin) {
            listOf(Screen.Zentrum, Screen.Orders, Screen.Finances, Screen.Organizer, Screen.Chat, Screen.Settings)
        } else {
            listOf(Screen.Zentrum, Screen.Finances, Screen.Organizer, Screen.Chat, Screen.Settings)
        }
    }

    /**
     * Strict 1:1 Web-Dashboard parity for internal Employee accounts:
     * Employees ONLY see [Screen.Zentrum], optional [Screen.Orders] (if contract grants production),
     * optional [Screen.Organizer] -> Tasks (if contract grants tasks), and [Screen.Settings] —
     * NEVER Admin-only [Screen.Finances] or [Screen.Chat].
     */
    fun bottomNavScreensForUserRole(
        userType: String?,
        canTasks: Boolean = true,
        canProduction: Boolean = false
    ): List<Screen> {
        if (userType == "admin") {
            return listOf(Screen.Zentrum, Screen.Orders, Screen.Finances, Screen.Organizer, Screen.Chat, Screen.Settings)
        }
        return buildList {
            add(Screen.Zentrum)
            if (canProduction) add(Screen.Orders)
            if (canTasks) add(Screen.Organizer)
            add(Screen.Settings)
        }
    }

    /**
     * Resolves a [NavTarget] deterministically to the target [Screen] for Admin vs. Employee.
     * Employees do not have access to [Screen.Orders] and safely land on [Screen.Zentrum]
     * without ever colliding with [Screen.Finances].
     */
    fun resolveScreen(target: NavTarget, isAdmin: Boolean): Screen {
        return when (target) {
            NavTarget.ZENTRUM -> Screen.Zentrum
            NavTarget.ORDERS -> if (isAdmin) Screen.Orders else Screen.Zentrum
            NavTarget.FINANCES -> Screen.Finances
            NavTarget.ORGANIZER -> Screen.Organizer
            NavTarget.CHAT -> Screen.Chat
            NavTarget.SETTINGS -> Screen.Settings
        }
    }

    fun resolveScreenForUserRole(
        target: NavTarget,
        userType: String?,
        canTasks: Boolean = true,
        canProduction: Boolean = false
    ): Screen {
        if (userType == "admin") {
            return resolveScreen(target, isAdmin = true)
        }
        return when (target) {
            NavTarget.ZENTRUM -> Screen.Zentrum
            NavTarget.ORDERS -> if (canProduction) Screen.Orders else Screen.Zentrum
            NavTarget.ORGANIZER -> if (canTasks) Screen.Organizer else Screen.Zentrum
            NavTarget.SETTINGS -> Screen.Settings
            NavTarget.FINANCES, NavTarget.CHAT -> Screen.Zentrum
        }
    }

    fun resolveScreen(tabIndex: Int, isAdmin: Boolean): Screen {
        return resolveScreen(resolveTarget(tabIndex), isAdmin)
    }

    fun resolvePageIndex(target: NavTarget, isAdmin: Boolean): Int {
        val items = bottomNavScreensForRole(isAdmin)
        val screen = resolveScreen(target, isAdmin)
        return items.indexOfFirst { it.route == screen.route }.coerceAtLeast(0)
    }

    fun resolvePageIndexForUserRole(
        target: NavTarget,
        userType: String?,
        canTasks: Boolean = true,
        canProduction: Boolean = false
    ): Int {
        val items = bottomNavScreensForUserRole(userType, canTasks, canProduction)
        val screen = resolveScreenForUserRole(target, userType, canTasks, canProduction)
        return items.indexOfFirst { it.route == screen.route }.coerceAtLeast(0)
    }

    fun resolvePageIndex(tabIndex: Int, isAdmin: Boolean): Int {
        return resolvePageIndex(resolveTarget(tabIndex), isAdmin)
    }

    fun consumePendingTarget(): NavTarget? {
        val explicit = pendingTarget
        val fromTab = pendingTab?.let { resolveTarget(it) }
        pendingTarget = null
        pendingTab = null
        return explicit ?: fromTab
    }

    fun triggerNavigation(target: NavTarget, subTab: Int = 0) {
        pendingTarget = target
        pendingTab = target.code
        organizerSubTab = subTab
        _navigationTrigger.tryEmit(Unit)
        _falconExitFocusTrigger.tryEmit(Unit)
    }

    fun triggerNavigation(tabIndex: Int, subTab: Int = 0) {
        triggerNavigation(resolveTarget(tabIndex), subTab)
    }

    fun triggerWakeWord() {
        pendingWakeWordTrigger = true
        _wakeWordTrigger.tryEmit(Unit)
    }

    fun grantTrustedFastUnlock() {
        val now = elapsedRealtimeProvider().coerceAtLeast(1L)
        trustedFastUnlockGrantedAtMs = now
        _trustedFastUnlockTrigger.tryEmit(Unit)
    }

    fun hasRecentTrustedFastUnlock(windowMs: Long = DEFAULT_FAST_UNLOCK_WINDOW_MS): Boolean {
        val grantedAt = trustedFastUnlockGrantedAtMs
        if (grantedAt <= 0L) return false
        val elapsed = elapsedRealtimeProvider() - grantedAt
        return elapsed in 0L..windowMs
    }

    fun clearTrustedFastUnlock() {
        trustedFastUnlockGrantedAtMs = 0L
    }

    fun triggerLogout() {
        clearTrustedFastUnlock()
        _logoutTrigger.tryEmit(Unit)
    }
}
