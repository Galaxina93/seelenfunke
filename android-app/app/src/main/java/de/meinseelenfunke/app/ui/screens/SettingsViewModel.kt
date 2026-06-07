package de.meinseelenfunke.app.ui.screens

import androidx.lifecycle.ViewModel
import de.meinseelenfunke.app.di.ServiceLocator
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.asStateFlow

class SettingsViewModel : ViewModel() {

    private val _apiKey = MutableStateFlow(ServiceLocator.getGeminiApiKey())
    val apiKey: StateFlow<String> = _apiKey.asStateFlow()

    private val _wakeWordEnabled = MutableStateFlow(ServiceLocator.isWakeWordEnabled())
    val wakeWordEnabled: StateFlow<Boolean> = _wakeWordEnabled.asStateFlow()

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
