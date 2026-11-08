package de.meinseelenfunke.app.widget

import android.app.PendingIntent
import android.appwidget.AppWidgetManager
import android.appwidget.AppWidgetProvider
import android.content.ComponentName
import android.content.Context
import android.content.Intent
import android.net.Uri
import android.util.Log
import android.widget.RemoteViews
import com.google.gson.Gson
import com.google.gson.reflect.TypeToken
import de.meinseelenfunke.app.MainActivity
import de.meinseelenfunke.app.R
import de.meinseelenfunke.app.data.api.ManagementShoppingItem
import de.meinseelenfunke.app.di.ServiceLocator
import kotlinx.coroutines.CoroutineScope
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.launch

class ShoppingListWidgetProvider : AppWidgetProvider() {

    override fun onUpdate(context: Context, appWidgetManager: AppWidgetManager, appWidgetIds: IntArray) {
        for (appWidgetId in appWidgetIds) {
            updateAppWidget(context, appWidgetManager, appWidgetId)
        }
        triggerBackgroundSync(context)
        super.onUpdate(context, appWidgetManager, appWidgetIds)
    }

    override fun onReceive(context: Context, intent: Intent) {
        super.onReceive(context, intent)
        val appContext = context.applicationContext
        val appWidgetManager = AppWidgetManager.getInstance(appContext)
        val componentName = ComponentName(appContext, ShoppingListWidgetProvider::class.java)
        val appWidgetIds = appWidgetManager.getAppWidgetIds(componentName)

        val resolvedAction = intent.getStringExtra(EXTRA_CLICK_ACTION) ?: intent.action

        when (resolvedAction) {
            ACTION_REFRESH -> {
                triggerBackgroundSync(appContext)
            }

            ACTION_TOGGLE_ITEM -> {
                val itemId = intent.getStringExtra("item_id")
                if (!itemId.isNullOrBlank()) {
                    // 1. Optimistic toggle locally for instant UI feedback
                    ServiceLocator.organizerRepository.optimisticToggleShoppingItem(itemId)

                    // 2. Perform network update in the background with goAsync()
                    val pendingResult = goAsync()
                    CoroutineScope(Dispatchers.IO).launch {
                        try {
                            ServiceLocator.organizerRepository.toggleShoppingItem(itemId)
                                .onFailure {
                                    ServiceLocator.organizerRepository.getShoppingItems()
                                }
                        } finally {
                            pendingResult.finish()
                        }
                    }
                }
            }

            ACTION_DELETE_ITEM -> {
                val itemId = intent.getStringExtra("item_id")
                if (!itemId.isNullOrBlank()) {
                    val pendingResult = goAsync()
                    CoroutineScope(Dispatchers.IO).launch {
                        try {
                            ServiceLocator.organizerRepository.deleteShoppingItem(itemId)
                                .onFailure {
                                    ServiceLocator.organizerRepository.getShoppingItems()
                                }
                        } finally {
                            pendingResult.finish()
                        }
                    }
                }
            }

            AppWidgetManager.ACTION_APPWIDGET_UPDATE -> {
                appWidgetManager.notifyAppWidgetViewDataChanged(appWidgetIds, R.id.shopping_widget_list)
                for (appWidgetId in appWidgetIds) {
                    updateAppWidget(appContext, appWidgetManager, appWidgetId)
                }
            }
        }
    }

    private fun triggerBackgroundSync(context: Context) {
        val appContext = context.applicationContext
        val pendingResult = goAsync()
        CoroutineScope(Dispatchers.IO).launch {
            try {
                ServiceLocator.organizerRepository.getShoppingItems()
                ServiceLocator.organizerRepository.triggerShoppingWidgetUpdate(appContext)
            } catch (e: Exception) {
                Log.e("ShoppingWidget", "Sync failed", e)
            } finally {
                pendingResult.finish()
            }
        }
    }

    companion object {
        const val ACTION_ITEM_CLICK = "de.meinseelenfunke.app.widget.ACTION_SHOPPING_ITEM_CLICK"
        const val EXTRA_CLICK_ACTION = "shopping_click_action"

        const val ACTION_REFRESH = "de.meinseelenfunke.app.widget.ACTION_SHOPPING_REFRESH"
        const val ACTION_TOGGLE_ITEM = "de.meinseelenfunke.app.widget.ACTION_SHOPPING_TOGGLE"
        const val ACTION_DELETE_ITEM = "de.meinseelenfunke.app.widget.ACTION_SHOPPING_DELETE"

        fun updateAppWidget(context: Context, appWidgetManager: AppWidgetManager, appWidgetId: Int) {
            val appContext = context.applicationContext
            val views = RemoteViews(appContext.packageName, R.layout.widget_shopping_layout)

            // Compute live counts for header subtitle
            val sharedPrefs = appContext.getSharedPreferences("shopping_widget_prefs", Context.MODE_PRIVATE)
            val allItems: List<ManagementShoppingItem> = try {
                val json = sharedPrefs.getString("shopping_items_cache", null)
                if (json != null) {
                    Gson().fromJson(json, object : TypeToken<List<ManagementShoppingItem>>() {}.type)
                } else emptyList()
            } catch (_: Exception) {
                emptyList()
            }

            val neededCount = allItems.count { it.status == "needed" }
            val stockedCount = allItems.count { it.status == "stocked" }
            val subtitleText = when {
                allItems.isEmpty() -> "Keine Artikel"
                neededCount > 0 -> "$neededCount benötigt · $stockedCount vorrätig"
                else -> "Alles eingekauft ($stockedCount vorrätig)"
            }
            views.setTextViewText(R.id.shopping_widget_subtitle, subtitleText)

            // Header click -> Open App in Organizer -> Einkauf (SubTab 3)
            val openAppIntent = Intent(appContext, MainActivity::class.java).apply {
                putExtra("open_tab", 2)
                putExtra("open_subtab", 3)
                data = Uri.parse("funkenwerk://shopping/open_app/$appWidgetId")
                flags = Intent.FLAG_ACTIVITY_NEW_TASK or Intent.FLAG_ACTIVITY_CLEAR_TOP
            }
            val openAppPending = PendingIntent.getActivity(
                appContext,
                appWidgetId * 100 + 200,
                openAppIntent,
                PendingIntent.FLAG_UPDATE_CURRENT or PendingIntent.FLAG_IMMUTABLE
            )
            views.setOnClickPendingIntent(R.id.shopping_widget_header_click, openAppPending)

            // Setup ListView Adapter
            val serviceIntent = Intent(appContext, ShoppingListWidgetService::class.java).apply {
                putExtra(AppWidgetManager.EXTRA_APPWIDGET_ID, appWidgetId)
                data = Uri.parse("funkenwerk://shopping/service/$appWidgetId")
            }
            views.setRemoteAdapter(R.id.shopping_widget_list, serviceIntent)
            views.setEmptyView(R.id.shopping_widget_list, R.id.shopping_widget_empty_view)

            // Setup Mutable PendingIntent template for ListView item clicks (Toggle & Delete)
            val itemClickIntent = Intent(appContext, ShoppingListWidgetProvider::class.java).apply {
                action = ACTION_ITEM_CLICK
                putExtra(AppWidgetManager.EXTRA_APPWIDGET_ID, appWidgetId)
                data = Uri.parse("funkenwerk://shopping/item_click/$appWidgetId")
            }
            val itemClickPendingIntent = PendingIntent.getBroadcast(
                appContext,
                appWidgetId * 100 + 201,
                itemClickIntent,
                PendingIntent.FLAG_UPDATE_CURRENT or PendingIntent.FLAG_MUTABLE
            )
            views.setPendingIntentTemplate(R.id.shopping_widget_list, itemClickPendingIntent)

            // Refresh button click handler
            val refreshIntent = Intent(appContext, ShoppingListWidgetProvider::class.java).apply {
                action = ACTION_REFRESH
                data = Uri.parse("funkenwerk://shopping/refresh/$appWidgetId")
            }
            val pendingRefresh = PendingIntent.getBroadcast(
                appContext,
                appWidgetId * 100 + 202,
                refreshIntent,
                PendingIntent.FLAG_UPDATE_CURRENT or PendingIntent.FLAG_IMMUTABLE
            )
            views.setOnClickPendingIntent(R.id.btn_shopping_refresh, pendingRefresh)

            // Add button click handler (Opens AddShoppingItemActivity)
            val addIntent = Intent(appContext, AddShoppingItemActivity::class.java).apply {
                putExtra(AppWidgetManager.EXTRA_APPWIDGET_ID, appWidgetId)
                data = Uri.parse("funkenwerk://shopping/add/$appWidgetId")
                flags = Intent.FLAG_ACTIVITY_NEW_TASK or Intent.FLAG_ACTIVITY_CLEAR_TOP
            }
            val pendingAdd = PendingIntent.getActivity(
                appContext,
                appWidgetId * 100 + 203,
                addIntent,
                PendingIntent.FLAG_UPDATE_CURRENT or PendingIntent.FLAG_IMMUTABLE
            )
            views.setOnClickPendingIntent(R.id.btn_shopping_add, pendingAdd)

            appWidgetManager.updateAppWidget(appWidgetId, views)
        }
    }
}
