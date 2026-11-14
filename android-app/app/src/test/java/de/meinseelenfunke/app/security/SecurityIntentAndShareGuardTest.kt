package de.meinseelenfunke.app.security

import android.app.Application
import android.app.KeyguardManager
import android.content.Context
import android.content.Intent
import android.net.Uri
import androidx.test.core.app.ApplicationProvider
import com.google.gson.Gson
import de.meinseelenfunke.app.data.api.ManagementShoppingItem
import de.meinseelenfunke.app.data.api.ManagementTask
import de.meinseelenfunke.app.di.ServiceLocator
import de.meinseelenfunke.app.util.MusicPlayerManager
import de.meinseelenfunke.app.util.SecurityIntentHelper
import de.meinseelenfunke.app.widget.MusicWidgetProvider
import de.meinseelenfunke.app.widget.ShoppingListWidgetProvider
import de.meinseelenfunke.app.widget.TasksWidgetActionActivity
import de.meinseelenfunke.app.widget.TasksWidgetProvider
import de.meinseelenfunke.app.widget.TrichterShareActivity
import org.junit.Assert.assertEquals
import org.junit.Assert.assertFalse
import org.junit.Assert.assertTrue
import org.junit.Before
import org.junit.Test
import org.junit.runner.RunWith
import org.robolectric.Robolectric
import org.robolectric.RobolectricTestRunner
import org.robolectric.Shadows
import org.robolectric.annotation.Config

@RunWith(RobolectricTestRunner::class)
@Config(sdk = [34], application = Application::class)
class SecurityIntentAndShareGuardTest {

    private lateinit var context: Context
    private lateinit var keyguardManager: KeyguardManager

    @Before
    fun setUp() {
        context = ApplicationProvider.getApplicationContext()
        ServiceLocator.init(context)
        keyguardManager = context.getSystemService(Context.KEYGUARD_SERVICE) as KeyguardManager
        Shadows.shadowOf(keyguardManager).setIsDeviceLocked(false)
    }

    @Test
    fun signsAndVerifies256BitInternalIntentToken() {
        val intent = Intent(context, TasksWidgetProvider::class.java).apply {
            action = TasksWidgetProvider.ACTION_REFRESH
        }
        SecurityIntentHelper.signIntent(context, intent)

        val token = intent.getStringExtra(SecurityIntentHelper.EXTRA_SECURITY_TOKEN)
        assertTrue("256-bit Base64URL token must be at least 43 chars", !token.isNullOrBlank() && token.length >= 43)
        assertTrue(SecurityIntentHelper.isTrustedInternalIntent(context, intent))
    }

    @Test
    fun rejectsUnsignedTamperedAndLengthMismatchedIntents() {
        val unsignedIntent = Intent(context, TasksWidgetProvider::class.java)
        assertFalse(SecurityIntentHelper.isTrustedInternalIntent(context, unsignedIntent))
        assertFalse(SecurityIntentHelper.isTrustedInternalIntent(context, null))

        val signed = SecurityIntentHelper.signIntent(context, Intent(context, TasksWidgetProvider::class.java))
        val originalToken = signed.getStringExtra(SecurityIntentHelper.EXTRA_SECURITY_TOKEN)!!

        val flippedChar = if (originalToken.last() == 'A') 'B' else 'A'
        val sameLenTampered = Intent(signed).apply {
            putExtra(SecurityIntentHelper.EXTRA_SECURITY_TOKEN, originalToken.dropLast(1) + flippedChar)
        }
        assertFalse(SecurityIntentHelper.isTrustedInternalIntent(context, sameLenTampered))

        val truncated = Intent(signed).apply {
            putExtra(SecurityIntentHelper.EXTRA_SECURITY_TOKEN, originalToken.take(12))
        }
        assertFalse(SecurityIntentHelper.isTrustedInternalIntent(context, truncated))
    }

    @Test
    fun trichterShareBlocksFileSchemeOwnFileProviderAndTraversal() {
        val pkg = "de.meinseelenfunke.app"
        val blockedUris = listOf(
            Uri.parse("file:///data/data/de.meinseelenfunke.app/shared_prefs/seelenfunke_prefs.xml"),
            Uri.parse("file:///sdcard/Download/rechnung.pdf"),
            Uri.parse("content://de.meinseelenfunke.app.fileprovider/mail_attachments/rechnung.pdf"),
            Uri.parse("content://de.meinseelenfunke.app/internal/secret"),
            Uri.parse("content://com.external.provider/files/../../secret.xml"),
            Uri.parse("content://com.external.provider/data/data/de.meinseelenfunke.app/files/secret.json"),
            Uri.parse("content://com.external.provider/data/user/0/de.meinseelenfunke.app/cache/x.pdf")
        )

        for (uri in blockedUris) {
            assertFalse("URI should be rejected: $uri", TrichterShareActivity.isSafeExternalShareUri(uri, pkg))
        }
        assertTrue(TrichterShareActivity.filterSafeExternalUris(blockedUris, pkg).isEmpty())
    }

    @Test
    fun trichterShareAllowsTrustedExternalContentProviders() {
        val pkg = "de.meinseelenfunke.app"
        val allowedUris = listOf(
            Uri.parse("content://media/external/images/media/1024"),
            Uri.parse("content://com.android.providers.downloads.documents/document/42"),
            Uri.parse("content://com.whatsapp.provider.media/item/abc123")
        )

        for (uri in allowedUris) {
            assertTrue("External content URI should be allowed: $uri", TrichterShareActivity.isSafeExternalShareUri(uri, pkg))
        }
        assertEquals(3, TrichterShareActivity.filterSafeExternalUris(allowedUris, pkg).size)
    }

    @Test
    fun lockscreenBlocksWidgetMutationGuardAndActionActivity() {
        ServiceLocator.saveAuthToken("1|ValidSanctumSessionTokenForTest123456")
        assertTrue(SecurityIntentHelper.canExecuteWidgetMutation(context))

        Shadows.shadowOf(keyguardManager).setIsDeviceLocked(true)
        assertFalse(SecurityIntentHelper.canExecuteWidgetMutation(context))

        val actionIntent = Intent(context, TasksWidgetActionActivity::class.java).apply {
            action = TasksWidgetProvider.ACTION_ITEM_CLICK
            putExtra(TasksWidgetProvider.EXTRA_CLICK_ACTION, TasksWidgetProvider.ACTION_TOGGLE_TASK)
            putExtra("task_id", "task_locked_1")
            SecurityIntentHelper.signIntent(context, this)
        }
        val controller = Robolectric.buildActivity(TasksWidgetActionActivity::class.java, actionIntent).create()
        assertTrue("TasksWidgetActionActivity must finish immediately when keyguard is locked", controller.get().isFinishing)
    }

    @Test
    fun lockscreenAndLoggedOutGuardBlocksWidgetProviders() {
        val gson = Gson()
        val tasksPrefs = context.getSharedPreferences("tasks_widget_prefs", Context.MODE_PRIVATE)
        val initialTasks = listOf(
            ManagementTask(
                id = "t1",
                task_list_id = "l1",
                parent_id = null,
                title = "Vertrauliche Aufgabe",
                priority = "hoch",
                is_completed = false,
                parent_title = null,
                relevant_from = null
            )
        )
        ServiceLocator.putEncryptedPref(tasksPrefs, "tasks_cache", gson.toJson(initialTasks))

        val shoppingPrefs = context.getSharedPreferences("shopping_widget_prefs", Context.MODE_PRIVATE)
        val initialShopping = listOf(
            ManagementShoppingItem(
                id = "s1",
                name = "Verpackungskartons",
                quantity = 2,
                category_id = null,
                status = "needed",
                last_purchased_at = null,
                purchase_count = 0,
                category = null
            )
        )
        ServiceLocator.putEncryptedPref(shoppingPrefs, "shopping_items_cache", gson.toJson(initialShopping))

        // 1. Device locked with active token -> blocked
        ServiceLocator.saveAuthToken("1|ActiveToken1234567890")
        Shadows.shadowOf(keyguardManager).setIsDeviceLocked(true)

        val toggleTaskIntent = SecurityIntentHelper.signIntent(
            context,
            Intent(context, TasksWidgetProvider::class.java).apply {
                action = TasksWidgetProvider.ACTION_TOGGLE_TASK
                putExtra("task_id", "t1")
            }
        )
        TasksWidgetProvider().onReceive(context, toggleTaskIntent)

        val toggleShoppingIntent = SecurityIntentHelper.signIntent(
            context,
            Intent(context, ShoppingListWidgetProvider::class.java).apply {
                action = "de.meinseelenfunke.app.widget.ACTION_SHOPPING_TOGGLE"
                putExtra("item_id", "s1")
            }
        )
        ShoppingListWidgetProvider().onReceive(context, toggleShoppingIntent)

        val musicIntent = SecurityIntentHelper.signIntent(
            context,
            Intent(context, MusicWidgetProvider::class.java).apply {
                action = "de.meinseelenfunke.app.widget.MUSIC_PLAY_PAUSE"
            }
        )
        MusicWidgetProvider().onReceive(context, musicIntent)
        assertFalse(MusicPlayerManager.isPlaying.value)

        // 2. Device unlocked, but logged out -> blocked
        Shadows.shadowOf(keyguardManager).setIsDeviceLocked(false)
        ServiceLocator.clearAuthToken()
        TasksWidgetProvider().onReceive(context, toggleTaskIntent)

        val tasksAfter = gson.fromJson(
            ServiceLocator.getDecryptedPref(tasksPrefs, "tasks_cache", "[]"),
            Array<ManagementTask>::class.java
        ).toList()
        assertEquals(1, tasksAfter.size)
        assertFalse("Task must remain uncompleted after blocked lockscreen/logged-out broadcasts", tasksAfter.first().is_completed)
    }
}
