package de.meinseelenfunke.app.ui.screens

import android.app.Application
import android.content.Context
import androidx.test.core.app.ApplicationProvider
import de.meinseelenfunke.app.di.ServiceLocator
import de.meinseelenfunke.app.util.FunkenwerkAlarmManager
import org.junit.Assert.assertEquals
import org.junit.Before
import org.junit.Test
import org.junit.runner.RunWith
import org.robolectric.RobolectricTestRunner
import org.robolectric.annotation.Config

@RunWith(RobolectricTestRunner::class)
@Config(sdk = [34], application = Application::class)
class SettingsViewModelTest {

    private lateinit var context: Context

    @Before
    fun setUp() {
        context = ApplicationProvider.getApplicationContext()
        ServiceLocator.init(context)
        ServiceLocator.saveTapoEmail("")
        ServiceLocator.saveTapoPassword("")
    }

    @Test
    fun loadsInitialTapoCredentialsFromServiceLocator() {
        ServiceLocator.saveTapoEmail("initial@mein-seelenfunke.de")
        ServiceLocator.saveTapoPassword("initialSecret123")

        val viewModel = SettingsViewModel()

        assertEquals("initial@mein-seelenfunke.de", viewModel.tapoEmail.value)
        assertEquals("initialSecret123", viewModel.tapoPassword.value)
    }

    @Test
    fun saveTapoCredentialsPersistsAndUpdatesStateFlows() {
        val viewModel = SettingsViewModel()

        viewModel.saveTapoCredentials("  test-user@mein-seelenfunke.de  ", "ultraSecretPass456")

        assertEquals("test-user@mein-seelenfunke.de", viewModel.tapoEmail.value)
        assertEquals("ultraSecretPass456", viewModel.tapoPassword.value)

        assertEquals("test-user@mein-seelenfunke.de", ServiceLocator.getTapoEmail())
        assertEquals("ultraSecretPass456", ServiceLocator.getTapoPassword())

        val creds = FunkenwerkAlarmManager.getTapoCredentials(context)
        assertEquals("test-user@mein-seelenfunke.de", creds.first)
        assertEquals("ultraSecretPass456", creds.second)
    }
}
