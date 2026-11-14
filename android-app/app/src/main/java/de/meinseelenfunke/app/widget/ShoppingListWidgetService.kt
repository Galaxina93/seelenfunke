package de.meinseelenfunke.app.widget

import android.content.Context
import android.content.Intent
import android.graphics.Color
import android.graphics.Paint
import android.view.View
import android.widget.RemoteViews
import android.widget.RemoteViewsService
import com.google.gson.Gson
import com.google.gson.reflect.TypeToken
import de.meinseelenfunke.app.R
import de.meinseelenfunke.app.data.api.ManagementShoppingItem
import java.util.Locale

class ShoppingListWidgetService : RemoteViewsService() {
    override fun onGetViewFactory(intent: Intent): RemoteViewsFactory {
        return ShoppingListWidgetViewsFactory(this.applicationContext)
    }
}

class ShoppingListWidgetViewsFactory(private val context: Context) : RemoteViewsService.RemoteViewsFactory {
    private var itemsList: List<ManagementShoppingItem> = emptyList()

    override fun onCreate() {
        loadFromPrefs()
    }

    override fun onDataSetChanged() {
        loadFromPrefs()
    }

    private fun loadFromPrefs() {
        de.meinseelenfunke.app.di.ServiceLocator.ensureInitialized(context.applicationContext)
        val sharedPrefs = context.getSharedPreferences("shopping_widget_prefs", Context.MODE_PRIVATE)
        val json = de.meinseelenfunke.app.di.ServiceLocator.getDecryptedPref(sharedPrefs, "shopping_items_cache")
        if (json != null) {
            try {
                val type = object : TypeToken<List<ManagementShoppingItem>>() {}.type
                val allItems: List<ManagementShoppingItem> = Gson().fromJson(json, type)

                // Sort: "needed" items first, then "stocked" items
                itemsList = allItems.sortedWith(
                    compareBy<ManagementShoppingItem> { if (it.status == "needed") 0 else 1 }
                        .thenBy { it.name.lowercase(Locale.ROOT) }
                )
            } catch (_: Exception) {
                itemsList = emptyList()
            }
        } else {
            itemsList = emptyList()
        }
    }

    override fun onDestroy() {
        itemsList = emptyList()
    }

    override fun getCount(): Int = itemsList.size

    override fun getViewAt(position: Int): RemoteViews? {
        if (position < 0 || position >= itemsList.size) return null
        val views = RemoteViews(context.packageName, R.layout.widget_shopping_item_layout)
        val item = itemsList[position]

        val isStocked = item.status == "stocked"

        // Checkbox & Text styling
        views.setTextViewText(R.id.item_name, item.name)
        if (isStocked) {
            views.setImageViewResource(R.id.item_checkbox, R.drawable.ic_checkbox_checked)
            views.setTextColor(R.id.item_name, Color.parseColor("#64748B"))
            views.setInt(
                R.id.item_name,
                "setPaintFlags",
                Paint.STRIKE_THRU_TEXT_FLAG or Paint.ANTI_ALIAS_FLAG
            )
        } else {
            views.setImageViewResource(R.id.item_checkbox, R.drawable.ic_checkbox_unchecked)
            views.setTextColor(R.id.item_name, Color.parseColor("#F8FAFC"))
            views.setInt(R.id.item_name, "setPaintFlags", Paint.ANTI_ALIAS_FLAG)
        }

        // Quantity badge
        if (item.quantity > 1) {
            views.setViewVisibility(R.id.item_quantity_badge, View.VISIBLE)
            views.setTextViewText(R.id.item_quantity_badge, "${item.quantity}x")
        } else {
            views.setViewVisibility(R.id.item_quantity_badge, View.GONE)
        }

        // Category Color Bar
        val categoryColor = when (item.category?.name?.lowercase(Locale.ROOT)) {
            "gemüse", "obst", "frisch" -> 0xFF34D399.toInt()
            "haushalt" -> 0xFFEC4899.toInt()
            "drogerie" -> 0xFFA855F7.toInt()
            "getränke" -> 0xFF3B82F6.toInt()
            "backen", "süßwaren" -> 0xFFF59E0B.toInt()
            null -> if (isStocked) 0xFF334155.toInt() else 0xFFC5A059.toInt()
            else -> 0xFFC5A059.toInt()
        }
        views.setViewVisibility(R.id.item_category_bar, View.VISIBLE)
        views.setInt(R.id.item_category_bar, "setBackgroundColor", categoryColor)

        // Toggle fill-in intent on row / toggle area
        val toggleFillIn = Intent().apply {
            putExtra(ShoppingListWidgetProvider.EXTRA_CLICK_ACTION, ShoppingListWidgetProvider.ACTION_TOGGLE_ITEM)
            putExtra("item_id", item.id)
        }
        views.setOnClickFillInIntent(R.id.item_toggle_area, toggleFillIn)
        views.setOnClickFillInIntent(R.id.widget_shopping_item_root, toggleFillIn)

        // Delete fill-in intent on trash icon
        val deleteFillIn = Intent().apply {
            putExtra(ShoppingListWidgetProvider.EXTRA_CLICK_ACTION, ShoppingListWidgetProvider.ACTION_DELETE_ITEM)
            putExtra("item_id", item.id)
        }
        views.setOnClickFillInIntent(R.id.item_delete_button, deleteFillIn)

        return views
    }

    override fun getLoadingView(): RemoteViews? = null

    override fun getViewTypeCount(): Int = 1

    override fun getItemId(position: Int): Long {
        if (position < 0 || position >= itemsList.size) return position.toLong()
        return itemsList[position].id.hashCode().toLong()
    }

    override fun hasStableIds(): Boolean = true
}
