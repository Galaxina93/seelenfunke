package de.meinseelenfunke.app.util

import android.content.Context
import androidx.biometric.BiometricManager
import androidx.biometric.BiometricPrompt
import androidx.core.content.ContextCompat
import androidx.fragment.app.FragmentActivity

object BiometricHelper {

    const val ALLOWED_AUTHENTICATORS: Int =
        BiometricManager.Authenticators.BIOMETRIC_STRONG or
            BiometricManager.Authenticators.DEVICE_CREDENTIAL

    fun resolveAllowedAuthenticators(@Suppress("UNUSED_PARAMETER") context: Context? = null): Int {
        return ALLOWED_AUTHENTICATORS
    }

    fun isBiometricAvailable(context: Context): Boolean {
        val biometricManager = BiometricManager.from(context)
        return biometricManager.canAuthenticate(ALLOWED_AUTHENTICATORS) == BiometricManager.BIOMETRIC_SUCCESS
    }

    fun showBiometricPrompt(
        activity: FragmentActivity,
        title: String = "Biometrische Anmeldung",
        subtitle: String = "Bestätige deine Identität",
        @Suppress("UNUSED_PARAMETER") negativeButtonText: String = "Passwort nutzen",
        onSuccess: (BiometricPrompt.AuthenticationResult) -> Unit,
        onError: (errorCode: Int, errString: CharSequence) -> Unit = { _, _ -> },
        onFailed: () -> Unit = {}
    ) {
        val executor = ContextCompat.getMainExecutor(activity)
        val authenticators = resolveAllowedAuthenticators(activity)
        val promptBuilder = BiometricPrompt.PromptInfo.Builder()
            .setTitle(title)
            .setSubtitle(subtitle)
            .setAllowedAuthenticators(authenticators)

        // AndroidX BiometricPrompt forbids setting negativeButtonText when DEVICE_CREDENTIAL is enabled.
        if ((authenticators and BiometricManager.Authenticators.DEVICE_CREDENTIAL) == 0) {
            promptBuilder.setNegativeButtonText(negativeButtonText)
        }

        val promptInfo = promptBuilder.build()

        val biometricPrompt = BiometricPrompt(
            activity,
            executor,
            object : BiometricPrompt.AuthenticationCallback() {
                override fun onAuthenticationSucceeded(result: BiometricPrompt.AuthenticationResult) {
                    super.onAuthenticationSucceeded(result)
                    onSuccess(result)
                }

                override fun onAuthenticationError(errorCode: Int, errString: CharSequence) {
                    super.onAuthenticationError(errorCode, errString)
                    onError(errorCode, errString)
                }

                override fun onAuthenticationFailed() {
                    super.onAuthenticationFailed()
                    onFailed()
                }
            }
        )

        biometricPrompt.authenticate(promptInfo)
    }
}
