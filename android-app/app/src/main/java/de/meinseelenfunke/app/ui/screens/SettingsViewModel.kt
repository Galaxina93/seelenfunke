package de.meinseelenfunke.app.ui.screens

import androidx.lifecycle.ViewModel
import androidx.lifecycle.viewModelScope
import de.meinseelenfunke.app.data.api.KdriveWebDavConfigDto
import de.meinseelenfunke.app.data.api.PhoneSecretaryCallSummaryDto
import de.meinseelenfunke.app.di.ServiceLocator
import de.meinseelenfunke.app.util.CachedWhitelistContact
import de.meinseelenfunke.app.util.FunkenwerkAlarmManager
import de.meinseelenfunke.app.util.PhoneConciergeManager
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.asStateFlow
import kotlinx.coroutines.launch

class SettingsViewModel : ViewModel() {

    private val _apiKey = MutableStateFlow(ServiceLocator.getGeminiApiKey())
    val apiKey: StateFlow<String> = _apiKey.asStateFlow()

    private val _tapoEmail = MutableStateFlow(ServiceLocator.getTapoEmail())
    val tapoEmail: StateFlow<String> = _tapoEmail.asStateFlow()

    private val _tapoPassword = MutableStateFlow(ServiceLocator.getTapoPassword())
    val tapoPassword: StateFlow<String> = _tapoPassword.asStateFlow()

    private val _kdriveConfig = MutableStateFlow(KdriveWebDavConfigDto())
    val kdriveConfig: StateFlow<KdriveWebDavConfigDto> = _kdriveConfig.asStateFlow()

    private val _kdriveSaving = MutableStateFlow(false)
    val kdriveSaving: StateFlow<Boolean> = _kdriveSaving.asStateFlow()

    private val _kdriveStatusFeedback = MutableStateFlow<TrichterFeedback?>(null)
    val kdriveStatusFeedback: StateFlow<TrichterFeedback?> = _kdriveStatusFeedback.asStateFlow()

    private val _wakeWordEnabled = MutableStateFlow(ServiceLocator.isWakeWordEnabled())
    val wakeWordEnabled: StateFlow<Boolean> = _wakeWordEnabled.asStateFlow()

    private val _phoneConciergeEnabled = MutableStateFlow(PhoneConciergeManager.isEnabled(ServiceLocator.context))
    val phoneConciergeEnabled: StateFlow<Boolean> = _phoneConciergeEnabled.asStateFlow()

    private val _phoneRingDelaySec = MutableStateFlow(PhoneConciergeManager.getRingDelaySeconds(ServiceLocator.context))
    val phoneRingDelaySec: StateFlow<Int> = _phoneRingDelaySec.asStateFlow()

    private val _phoneAnswerMode = MutableStateFlow(PhoneConciergeManager.getAnswerMode(ServiceLocator.context))
    val phoneAnswerMode: StateFlow<String> = _phoneAnswerMode.asStateFlow()

    private val _carrierForwardingConfirmed = MutableStateFlow(PhoneConciergeManager.isCarrierForwardingConfirmed(ServiceLocator.context))
    val carrierForwardingConfirmed: StateFlow<Boolean> = _carrierForwardingConfirmed.asStateFlow()

    private val _autoVerifyNotes = MutableStateFlow(PhoneConciergeManager.isAutoVerifyEnabled(ServiceLocator.context))
    val autoVerifyNotes: StateFlow<Boolean> = _autoVerifyNotes.asStateFlow()

    private val _whitelistContacts = MutableStateFlow(PhoneConciergeManager.loadCachedContacts(ServiceLocator.context))
    val whitelistContacts: StateFlow<List<CachedWhitelistContact>> = _whitelistContacts.asStateFlow()

    private val _recentSecretaryCalls = MutableStateFlow(PhoneConciergeManager.loadCachedRecentCalls(ServiceLocator.context))
    val recentSecretaryCalls: StateFlow<List<PhoneSecretaryCallSummaryDto>> = _recentSecretaryCalls.asStateFlow()

    init {
        if (ServiceLocator.getUserType() == "admin") {
            refreshPhoneConcierge()
            refreshKdriveConfig()
        }
    }

    fun refreshKdriveConfig() {
        if (ServiceLocator.getUserType() != "admin") return
        viewModelScope.launch(Dispatchers.IO) {
            ServiceLocator.financeRepository.getKdriveTrichterConfig()
                .onSuccess { dto ->
                    _kdriveConfig.value = dto
                }
        }
    }

    fun saveKdriveConfig(
        driveId: String,
        email: String,
        appPassword: String,
        onComplete: (Boolean, String) -> Unit = { _, _ -> }
    ) {
        viewModelScope.launch {
            _kdriveSaving.value = true
            _kdriveStatusFeedback.value = null
            val res = ServiceLocator.financeRepository.saveKdriveTrichterConfig(
                driveId = driveId,
                email = email,
                appPassword = appPassword,
                directSort = false
            )
            _kdriveSaving.value = false
            res.onSuccess { payload ->
                payload.data?.let { _kdriveConfig.value = it }
                val msg = payload.message ?: "kDrive verbunden."
                _kdriveStatusFeedback.value = TrichterFeedback(isSuccess = true, message = msg)
                onComplete(true, msg)
            }.onFailure { err ->
                val msg = err.localizedMessage ?: "kDrive-Verbindung fehlgeschlagen."
                _kdriveStatusFeedback.value = TrichterFeedback(isSuccess = false, message = msg)
                onComplete(false, msg)
            }
        }
    }

    fun flushPendingKdriveRelay() {
        viewModelScope.launch {
            _kdriveSaving.value = true
            _kdriveStatusFeedback.value = null
            val res = ServiceLocator.financeRepository.flushKdriveTrichterRelay()
            _kdriveSaving.value = false
            res.onSuccess { msg ->
                _kdriveStatusFeedback.value = TrichterFeedback(isSuccess = true, message = msg)
                refreshKdriveConfig()
            }.onFailure { err ->
                _kdriveStatusFeedback.value = TrichterFeedback(
                    isSuccess = false,
                    message = err.localizedMessage ?: "Verarbeitung fehlgeschlagen."
                )
            }
        }
    }

    fun refreshPhoneConcierge() {
        if (ServiceLocator.getUserType() != "admin") return
        viewModelScope.launch(Dispatchers.IO) {
            val ctx = ServiceLocator.context
            val synced = PhoneConciergeManager.syncFromBackend(ctx)
            _whitelistContacts.value = synced
            _recentSecretaryCalls.value = PhoneConciergeManager.loadCachedRecentCalls(ctx)
            _phoneConciergeEnabled.value = PhoneConciergeManager.isEnabled(ctx)
            _phoneRingDelaySec.value = PhoneConciergeManager.getRingDelaySeconds(ctx)
            _phoneAnswerMode.value = PhoneConciergeManager.getAnswerMode(ctx)
            _carrierForwardingConfirmed.value = PhoneConciergeManager.isCarrierForwardingConfirmed(ctx)
            _autoVerifyNotes.value = PhoneConciergeManager.isAutoVerifyEnabled(ctx)
        }
    }

    fun setPhoneAnswerMode(mode: String) {
        val ctx = ServiceLocator.context
        PhoneConciergeManager.setAnswerMode(ctx, mode)
        _phoneAnswerMode.value = PhoneConciergeManager.getAnswerMode(ctx)
    }

    fun setAutoVerifyNotes(enabled: Boolean) {
        val ctx = ServiceLocator.context
        PhoneConciergeManager.setAutoVerifyEnabled(ctx, enabled)
        _autoVerifyNotes.value = enabled
    }

    fun refreshCarrierForwardingState() {
        _carrierForwardingConfirmed.value = PhoneConciergeManager.isCarrierForwardingConfirmed(ServiceLocator.context)
    }

    fun confirmCarrierForwarding(confirmed: Boolean) {
        val ctx = ServiceLocator.context
        PhoneConciergeManager.setCarrierForwardingConfirmed(ctx, confirmed)
        _carrierForwardingConfirmed.value = confirmed
    }

    fun clearPhoneDiagnosticTrace() {
        PhoneConciergeManager.clearDiagnosticTrace(ServiceLocator.context)
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

    fun saveTapoCredentials(email: String, pass: String) {
        val cleanEmail = email.trim()
        _tapoEmail.value = cleanEmail
        _tapoPassword.value = pass
        FunkenwerkAlarmManager.saveTapoCredentials(ServiceLocator.context, cleanEmail, pass)
        viewModelScope.launch(Dispatchers.IO) {
            try {
                ServiceLocator.organizerRepository.syncAlarmPackageToPortal(ServiceLocator.context)
            } catch (_: Exception) {
            }
        }
    }

    fun setWakeWordEnabled(enabled: Boolean) {
        _wakeWordEnabled.value = enabled
        ServiceLocator.setWakeWordEnabled(enabled)
        de.meinseelenfunke.app.services.WakeWordService.syncServiceState(ServiceLocator.context)
    }
}
