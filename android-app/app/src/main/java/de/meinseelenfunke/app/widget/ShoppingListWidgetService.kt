package de.meinseelenfunke.app.widget

import android.content.Context
import android.content.Intent
import android.text.SpannableString
import android.text.Spanned
import android.text.style.StrikethroughSpan
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

    override fun onCreate() {}

    override fun onDataSetChanged() {
        val sharedPrefs = context.getSharedPreferences("shopping_widget_prefs", Context.MODE_PRIVATE)
        val json = sharedPrefs.getString("shopping_items_cache", null)
        if (json != null) {
            try {
                val type = object : TypeToken<List<ManagementShoppingItem>>() {}.type
                val allItems: List<ManagementShoppingItem> = Gson().fromJson(json, type)
                
                // Sort: "needed" items first, then "stocked" items
                itemsList = allItems.sortedWith(
                    compareBy<ManagementShoppingItem> { if (it.status == "needed") 0 else 1 }
                        .thenBy { it.name.lowercase(Locale.ROOT) }
                )
            } catch (e: Exception) {
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

    override fun getViewAt(position: Int): RemoteViews {
        val views = RemoteViews(context.packageName, R.layout.widget_shopping_item_layout)
        val item = itemsList[position]

        val isStocked = item.status == "stocked"

        // Set Checkbox icon
        if (isStocked) {
            views.setImageViewResource(R.id.item_checkbox, R.drawable.ic_checkbox_checked)
        } else {
            views.setImageViewResource(R.id.item_checkbox, R.drawable.ic_checkbox_unchecked)
        }

        // Set Item Name (with strikethrough if stocked)
        if (isStocked) {
            val spannable = SpannableString(item.name)
            spannable.setSpan(StrikethroughSpan(), 0, item.name.length, Spanned.SPAN_EXCLUSIVE_EXCLUSIVE)
            views.setTextViewText(R.id.item_name, spannable)
            views.setTextColor(R.id.item_name, 0xFF94A3B8.toInt()) // Dimmed Slate400
        } else {
            views.setTextViewText(R.id.item_name, item.name)
            views.setTextColor(R.id.item_name, 0xFFF8FAFC.toInt()) // Bright White
        }

        // Set Category Color Bar
        val categoryColor = when (item.category?.name?.lowercase(Locale.ROOT)) {
            "gemüse", "obst", "frisch" -> 0xFF34D399.toInt() // emerald-400
            "haushalt" -> 0xFFEC4899.toInt() // pink-500
            "drogerie" -> 0xFFA855F7.toInt() // purple-500
            "getränke" -> 0xFF3B82F6.toInt() // blue-500
            "backen", "süßwaren" -> 0xFFF59E0B.toInt() // amber-500
            null -> 0x00000000.toInt() // transparent if uncategorized
            else -> 0xFFC5A059.toInt() // gold default
        }
        
        if (categoryColor == 0) {
            views.setViewVisibility(R.id.item_category_bar, View.INVISIBLE)
        } else {
            views.setViewVisibility(R.id.item_category_bar, View.VISIBLE)
            views.setInt(R.id.item_category_bar, "setBackgroundColor", categoryColor)
        }

        // Attach item ID for the toggle action template
        val fillInIntent = Intent().apply {
            putExtra("item_id", item.id)
        }
        views.setOnClickFillInIntent(R.id.widget_shopping_item_root, fillInIntent)

        return views
    }

    override fun getLoadingView(): RemoteViews? = null

    override fun getViewTypeCount(): Int = 1

    override fun getItemId(position: Int): Long = position.toLong()

    override fun hasStableIds(): Boolean = true
}
