package de.meinseelenfunke.app.ui.screens

import androidx.lifecycle.ViewModel
import androidx.lifecycle.viewModelScope
import de.meinseelenfunke.app.di.ServiceLocator
import de.meinseelenfunke.app.util.CachedWhitelistContact
import de.meinseelenfunke.app.util.PhoneConciergeManager
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.asStateFlow
import kotlinx.coroutines.launch

class SettingsViewModel : ViewModel() {

    private val _apiKey = MutableStateFlow(ServiceLocator.getGeminiApiKey())
    val apiKey: StateFlow<String> = _apiKey.asStateFlow()

    private val _wakeWordEnabled = MutableStateFlow(ServiceLocator.isWakeWordEnabled())
    val wakeWordEnabled: StateFlow<Boolean> = _wakeWordEnabled.asStateFlow()

    private val _phoneConciergeEnabled = MutableStateFlow(PhoneConciergeManager.isEnabled(ServiceLocator.context))
    val phoneConciergeEnabled: StateFlow<Boolean> = _phoneConciergeEnabled.asStateFlow()

    private val _phoneRingDelaySec = MutableStateFlow(PhoneConciergeManager.getRingDelaySeconds(ServiceLocator.context))
    val phoneRingDelaySec: StateFlow<Int> = _phoneRingDelaySec.asStateFlow()

    private val _whitelistContacts = MutableStateFlow(PhoneConciergeManager.loadCachedContacts(ServiceLocator.context))
    val whitelistContacts: StateFlow<List<CachedWhitelistContact>> = _whitelistContacts.asStateFlow()

    init {
        refreshPhoneConcierge()
    }

    fun refreshPhoneConcierge() {
        viewModelScope.launch(Dispatchers.IO) {
            val ctx = ServiceLocator.context
            val synced = PhoneConciergeManager.syncFromBackend(ctx)
            _whitelistContacts.value = synced
            _phoneConciergeEnabled.value = PhoneConciergeManager.isEnabled(ctx)
            _phoneRingDelaySec.value = PhoneConciergeManager.getRingDelaySeconds(ctx)
        }
    }

    fun setPhoneConciergeEnabled(enabled: Boolean) {
        val ctx = ServiceLocator.context
        _phoneConciergeEnabled.value = enabled
        PhoneConciergeManager.setEnabledLocal(ctx, enabled)
        viewModelScope.launch(Dispatchers.IO) {
            PhoneConciergeManager.updateGlobalConfig(ctx, enabled = enabled)
        }
    }

    fun setPhoneRingDelaySec(seconds: Int) {
        val ctx = ServiceLocator.context
        val clamped = seconds.coerceIn(0, 30)
        _phoneRingDelaySec.value = clamped
        PhoneConciergeManager.setRingDelayLocal(ctx, clamped)
        viewModelScope.launch(Dispatchers.IO) {
            PhoneConciergeManager.updateGlobalConfig(ctx, ringDelaySeconds = clamped)
        }
    }

    fun toggleContactWhitelist(contactId: String, whitelisted: Boolean) {
        val ctx = ServiceLocator.context
        _whitelistContacts.value = _whitelistContacts.value.map {
            if (it.id == contactId) it.copy(whitelisted = whitelisted) else it
        }
        viewModelScope.launch(Dispatchers.IO) {
            val updated = PhoneConciergeManager.toggleContactWhitelist(ctx, contactId, whitelisted)
            _whitelistContacts.value = updated
        }
    }

    fun saveApiKey(key: String) {
        _apiKey.value = key
        ServiceLocator.saveGeminiApiKey(key)
    }

    fun setWakeWordEnabled(enabled: Boolean) {
        _wakeWordEnabled.value = enabled
        ServiceLocator.setWakeWordEnabled(enabled)
        val context = ServiceLocator.context
        val intent = android.content.Intent(context, de.meinseelenfunke.app.services.WakeWordService::class.java)
        try {
            if (enabled) {
                if (android.os.Build.VERSION.SDK_INT >= android.os.Build.VERSION_CODES.O) {
                    context.startForegroundService(intent)
                } else {
                    context.startService(intent)
                }
            } else {
                context.stopService(intent)
            }
        } catch (e: Exception) {
            e.printStackTrace()
        }
    }
}
