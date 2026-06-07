package de.meinseelenfunke.app.util

import kotlinx.coroutines.flow.MutableSharedFlow
import kotlinx.coroutines.flow.SharedFlow
import kotlinx.coroutines.flow.asSharedFlow

object NavigationBridge {
    // Target tabs: 0 = Zentrum, 1 = Finanzen, 2 = Organizer, 3 = Agenten, 4 = Einstellungen
    var pendingTab: Int? = null
    var organizerSubTab: Int = 0
    var pendingSelectedDate: String? = null
    var pendingCreateEvent: Boolean = false
    var pendingEmailId: String? = null

    @Volatile
    var isLiveCallActive: Boolean = false

    @Volatile
    var pendingWakeWordTrigger: Boolean = false

    private val _navigationTrigger = MutableSharedFlow<Unit>(extraBufferCapacity = 1)
    val navigationTrigger: SharedFlow<Unit> = _navigationTrigger.asSharedFlow()

    private val _wakeWordTrigger = MutableSharedFlow<Unit>(extraBufferCapacity = 1)
    val wakeWordTrigger: SharedFlow<Unit> = _wakeWordTrigger.asSharedFlow()

    private val _logoutTrigger = MutableSharedFlow<Unit>(extraBufferCapacity = 1)
    val logoutTrigger: SharedFlow<Unit> = _logoutTrigger.asSharedFlow()

    fun triggerNavigation(tabIndex: Int, subTab: Int = 0) {
        pendingTab = tabIndex
        organizerSubTab = subTab
        _navigationTrigger.tryEmit(Unit)
    }

    fun triggerWakeWord() {
        pendingWakeWordTrigger = true
        _wakeWordTrigger.tryEmit(Unit)
    }

    fun triggerLogout() {
        _logoutTrigger.tryEmit(Unit)
    }
}
