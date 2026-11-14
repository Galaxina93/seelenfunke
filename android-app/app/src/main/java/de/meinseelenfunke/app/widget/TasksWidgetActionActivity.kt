package de.meinseelenfunke.app.widget

import android.app.Activity
import android.content.Intent
import android.os.Bundle
import de.meinseelenfunke.app.MainActivity

/**
 * Lightweight zero-UI router Activity for TasksWidget and MasterWidget ListView item clicks.
 * Ensures 100% Background Activity Launch (BAL) compliance on Android 14-17 (API 34-37)
 * when opening Dialog Activities from inside a RemoteViews ListView template, while
 * routing non-UI actions (toggle, select list, cycle priority, play track) with zero animation.
 */
class TasksWidgetActionActivity : Activity() {

    @Suppress("DEPRECATION")
    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)
        overridePendingTransition(0, 0)

        val appContext = applicationContext
        de.meinseelenfunke.app.di.ServiceLocator.ensureInitialized(appContext)
        if (de.meinseelenfunke.app.util.SecurityIntentHelper.isDeviceLocked(this) ||
            de.meinseelenfunke.app.di.ServiceLocator.getAuthToken().isNullOrBlank()
        ) {
            finish()
            overridePendingTransition(0, 0)
            return
        }

        if (!de.meinseelenfunke.app.util.SecurityIntentHelper.isTrustedInternalIntent(this, intent)) {
            finish()
            overridePendingTransition(0, 0)
            return
        }

        val resolvedAction = try { intent.getStringExtra(TasksWidgetProvider.EXTRA_CLICK_ACTION) } catch (_: Exception) { null } ?: intent.action

        when (resolvedAction) {
            TasksWidgetProvider.ACTION_EDIT_TASK -> {
                val taskId = intent.getStringExtra(TasksWidgetProvider.EXTRA_TASK_ID)
                if (!taskId.isNullOrBlank()) {
                    startActivity(
                        Intent(this, EditTaskWidgetActivity::class.java).apply {
                            putExtra("task_id", taskId)
                        }
                    )
                }
            }

            TasksWidgetProvider.ACTION_ADD_TASK_CLICK -> {
                val listId = intent.getStringExtra(TasksWidgetProvider.EXTRA_LIST_ID)
                startActivity(
                    Intent(this, AddTaskWidgetActivity::class.java).apply {
                        if (!listId.isNullOrBlank()) {
                            putExtra("list_id", listId)
                        }
                    }
                )
            }

            TasksWidgetProvider.ACTION_ADD_LIST_CLICK -> {
                startActivity(Intent(this, AddTaskListWidgetActivity::class.java))
            }

            TasksWidgetProvider.ACTION_RENAME_LIST -> {
                val listId = intent.getStringExtra(TasksWidgetProvider.EXTRA_LIST_ID)
                if (!listId.isNullOrBlank()) {
                    startActivity(
                        Intent(this, RenameTaskListWidgetActivity::class.java).apply {
                            putExtra("list_id", listId)
                        }
                    )
                }
            }

            TasksWidgetProvider.ACTION_DELETE_LIST -> {
                val listId = intent.getStringExtra(TasksWidgetProvider.EXTRA_LIST_ID)
                if (!listId.isNullOrBlank()) {
                    startActivity(
                        Intent(this, ConfirmDeleteListActivity::class.java).apply {
                            putExtra("list_id", listId)
                        }
                    )
                }
            }

            MasterWidgetProvider.ACTION_OPEN_CALENDAR_EVENT -> {
                val selectedDate = intent.getStringExtra(MasterWidgetProvider.EXTRA_EVENT_DATE)
                startActivity(
                    Intent(this, MainActivity::class.java).apply {
                        putExtra("open_tab", 2)
                        putExtra("open_subtab", 1)
                        if (!selectedDate.isNullOrBlank()) {
                            putExtra("selected_date", selectedDate)
                        }
                        flags = Intent.FLAG_ACTIVITY_NEW_TASK or Intent.FLAG_ACTIVITY_CLEAR_TOP
                        de.meinseelenfunke.app.util.SecurityIntentHelper.signIntent(appContext, this)
                    }
                )
            }

            MasterWidgetProvider.ACTION_MASTER_SELECT_LIST,
            MasterWidgetProvider.ACTION_PLAY_TRACK -> {
                val broadcastIntent = Intent(appContext, MasterWidgetProvider::class.java).apply {
                    action = resolvedAction
                    intent.extras?.let { putExtras(it) }
                    de.meinseelenfunke.app.util.SecurityIntentHelper.signIntent(appContext, this)
                }
                appContext.sendBroadcast(broadcastIntent)
            }

            ShoppingListWidgetProvider.ACTION_TOGGLE_ITEM,
            ShoppingListWidgetProvider.ACTION_DELETE_ITEM -> {
                val broadcastIntent = Intent(appContext, ShoppingListWidgetProvider::class.java).apply {
                    action = resolvedAction
                    intent.extras?.let { putExtras(it) }
                    de.meinseelenfunke.app.util.SecurityIntentHelper.signIntent(appContext, this)
                }
                appContext.sendBroadcast(broadcastIntent)
            }

            else -> {
                val broadcastIntent = Intent(appContext, TasksWidgetProvider::class.java).apply {
                    action = resolvedAction ?: TasksWidgetProvider.ACTION_ITEM_CLICK
                    intent.extras?.let { putExtras(it) }
                    de.meinseelenfunke.app.util.SecurityIntentHelper.signIntent(appContext, this)
                }
                appContext.sendBroadcast(broadcastIntent)
            }
        }

        finish()
        overridePendingTransition(0, 0)
    }
}
