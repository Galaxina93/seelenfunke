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
import de.meinseelenfunke.app.R
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
        val appWidgetManager = AppWidgetManager.getInstance(context)
        val componentName = ComponentName(context, ShoppingListWidgetProvider::class.java)
        val appWidgetIds = appWidgetManager.getAppWidgetIds(componentName)

        when (intent.action) {
            ACTION_REFRESH -> {
                appWidgetManager.notifyAppWidgetViewDataChanged(appWidgetIds, R.id.shopping_widget_list)
                for (appWidgetId in appWidgetIds) {
                    updateAppWidget(context, appWidgetManager, appWidgetId)
                }
                triggerBackgroundSync(context)
            }
            ACTION_TOGGLE_ITEM -> {
                val itemId = intent.getStringExtra("item_id")
                if (itemId != null) {
                    // 1. Optimistic toggle locally
                    ServiceLocator.organizerRepository.optimisticToggleShoppingItem(itemId)

                    // 2. Perform network update in the background
                    CoroutineScope(Dispatchers.Main).launch {
                        ServiceLocator.organizerRepository.toggleShoppingItem(itemId)
                            .onFailure {
                                // On failure, fetch latest state to sync back
                                ServiceLocator.organizerRepository.getShoppingItems()
                            }
                    }
                }
            }
            AppWidgetManager.ACTION_APPWIDGET_UPDATE -> {
                appWidgetManager.notifyAppWidgetViewDataChanged(appWidgetIds, R.id.shopping_widget_list)
                for (appWidgetId in appWidgetIds) {
                    updateAppWidget(context, appWidgetManager, appWidgetId)
                }
                triggerBackgroundSync(context)
            }
        }
    }

    private fun triggerBackgroundSync(context: Context) {
        if (!ServiceLocator.authRepository.isLoggedIn()) return
        CoroutineScope(Dispatchers.IO).launch {
            try {
                ServiceLocator.organizerRepository.getShoppingItems()
            } catch (e: Exception) {
                Log.e("ShoppingWidget", "Sync failed", e)
            }
        }
    }

    companion object {
        const val ACTION_REFRESH = "de.meinseelenfunke.app.widget.ACTION_SHOPPING_REFRESH"
        const val ACTION_TOGGLE_ITEM = "de.meinseelenfunke.app.widget.ACTION_SHOPPING_TOGGLE"

        fun updateAppWidget(context: Context, appWidgetManager: AppWidgetManager, appWidgetId: Int) {
            val views = RemoteViews(context.packageName, R.layout.widget_shopping_layout)

            // Setup ListView Adapter
            val serviceIntent = Intent(context, ShoppingListWidgetService::class.java).apply {
                putExtra(AppWidgetManager.EXTRA_APPWIDGET_ID, appWidgetId)
                data = Uri.parse(toUri(Intent.URI_INTENT_SCHEME))
            }
            views.setRemoteAdapter(R.id.shopping_widget_list, serviceIntent)
            views.setEmptyView(R.id.shopping_widget_list, R.id.shopping_widget_empty_view)

            // Setup PendingIntent template for ListView item toggles (Broadcast)
            val toggleIntent = Intent(context, ShoppingListWidgetProvider::class.java).apply {
                action = ACTION_TOGGLE_ITEM
            }
            val togglePendingIntent = PendingIntent.getBroadcast(
                context, appWidgetId * 10 + 201, toggleIntent,
                PendingIntent.FLAG_UPDATE_CURRENT or PendingIntent.FLAG_MUTABLE
            )
            views.setPendingIntentTemplate(R.id.shopping_widget_list, togglePendingIntent)

            // Refresh button click handler (Broadcast)
            val refreshIntent = Intent(context, ShoppingListWidgetProvider::class.java).apply {
                action = ACTION_REFRESH
            }
            val pendingRefresh = PendingIntent.getBroadcast(
                context, appWidgetId * 10 + 202, refreshIntent,
                PendingIntent.FLAG_UPDATE_CURRENT or PendingIntent.FLAG_IMMUTABLE
            )
            views.setOnClickPendingIntent(R.id.btn_shopping_refresh, pendingRefresh)

            // Add button click handler (Opens translucent Compose dialog activity)
            val addIntent = Intent(context, AddShoppingItemActivity::class.java).apply {
                putExtra(AppWidgetManager.EXTRA_APPWIDGET_ID, appWidgetId)
            }
            val pendingAdd = PendingIntent.getActivity(
                context, appWidgetId * 10 + 203, addIntent,
                PendingIntent.FLAG_UPDATE_CURRENT or PendingIntent.FLAG_IMMUTABLE
            )
            views.setOnClickPendingIntent(R.id.btn_shopping_add, pendingAdd)

            appWidgetManager.updateAppWidget(appWidgetId, views)
        }
    }
}
