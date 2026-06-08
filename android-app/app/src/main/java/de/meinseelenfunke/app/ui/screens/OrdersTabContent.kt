package de.meinseelenfunke.app.ui.screens

import androidx.compose.foundation.background
import androidx.compose.foundation.border
import androidx.compose.foundation.clickable
import androidx.compose.foundation.layout.*
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.lazy.items
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.Close
import androidx.compose.material.icons.filled.FilterList
import androidx.compose.material.icons.filled.Refresh
import androidx.compose.material3.*
import androidx.compose.runtime.*
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import androidx.compose.ui.window.Dialog
import androidx.compose.ui.window.DialogProperties
import androidx.lifecycle.viewmodel.compose.viewModel
import de.meinseelenfunke.app.data.api.OrderDetail
import de.meinseelenfunke.app.data.api.OrderSummary
import de.meinseelenfunke.app.ui.theme.*
import java.text.SimpleDateFormat
import java.util.*

@OptIn(ExperimentalMaterial3Api::class)
@Composable
fun OrdersTabContent(
    viewModel: OrderViewModel = viewModel()
) {
    val uiState by viewModel.uiState.collectAsState()
    val orders by viewModel.orders.collectAsState()
    val selectedOrder by viewModel.selectedOrder.collectAsState()
    val statusFilter by viewModel.statusFilter.collectAsState()
    val isUpdatingStatus by viewModel.isUpdatingStatus.collectAsState()

    var showFilterMenu by remember { mutableStateOf(false) }

    val statusList = listOf(
        "all" to "Alle",
        "pending" to "Ausstehend",
        "processing" to "In Bearbeitung",
        "shipped" to "Versendet",
        "completed" to "Abgeschlossen",
        "cancelled" to "Storniert",
        "refunded" to "Erstattet"
    )

    Column(
        modifier = Modifier
            .fillMaxSize()
            .background(SpaceBlack)
    ) {
        // Top Filter Bar
        Row(
            modifier = Modifier
                .fillMaxWidth()
                .padding(horizontal = 8.dp, vertical = 8.dp),
            horizontalArrangement = Arrangement.SpaceBetween,
            verticalAlignment = Alignment.CenterVertically
        ) {
            Row(verticalAlignment = Alignment.CenterVertically) {
                Icon(
                    imageVector = Icons.Default.FilterList,
                    contentDescription = "Filter",
                    tint = Gold,
                    modifier = Modifier.size(20.dp)
                )
                Spacer(modifier = Modifier.width(8.dp))
                Text(
                    text = "Filter: " + (statusList.find { it.first == statusFilter }?.second ?: "Alle"),
                    color = Slate50,
                    fontSize = 14.sp,
                    fontWeight = FontWeight.Medium
                )
            }

            Row {
                IconButton(onClick = { viewModel.loadOrders(if (statusFilter == "all") null else statusFilter) }) {
                    Icon(
                        imageVector = Icons.Default.Refresh,
                        contentDescription = "Aktualisieren",
                        tint = Gold
                    )
                }
                Box {
                    Button(
                        onClick = { showFilterMenu = true },
                        colors = ButtonDefaults.buttonColors(containerColor = GlassWhite10),
                        shape = RoundedCornerShape(8.dp),
                        contentPadding = PaddingValues(horizontal = 12.dp, vertical = 6.dp)
                    ) {
                        Text("Status ändern", color = Slate50, fontSize = 12.sp)
                    }
                    DropdownMenu(
                        expanded = showFilterMenu,
                        onDismissRequest = { showFilterMenu = false },
                        modifier = Modifier.background(Slate900)
                    ) {
                        statusList.forEach { (statusVal, statusLabel) ->
                            DropdownMenuItem(
                                text = { Text(statusLabel, color = Slate50) },
                                onClick = {
                                    viewModel.setStatusFilter(statusVal)
                                    showFilterMenu = false
                                }
                            )
                        }
                    }
                }
            }
        }

        // Main List Content
        when (uiState) {
            is OrderUiState.Loading -> {
                Box(
                    modifier = Modifier
                        .fillMaxSize()
                        .weight(1f),
                    contentAlignment = Alignment.Center
                ) {
                    CircularProgressIndicator(color = Gold)
                }
            }
            is OrderUiState.Error -> {
                Box(
                    modifier = Modifier
                        .fillMaxSize()
                        .weight(1f)
                        .padding(16.dp),
                    contentAlignment = Alignment.Center
                ) {
                    Text(
                        text = (uiState as OrderUiState.Error).message,
                        color = Color.Red,
                        fontSize = 14.sp
                    )
                }
            }
            is OrderUiState.Success -> {
                if (orders.isEmpty()) {
                    Box(
                        modifier = Modifier
                            .fillMaxSize()
                            .weight(1f),
                        contentAlignment = Alignment.Center
                    ) {
                        Text(
                            text = "Keine Bestellungen gefunden.",
                            color = Slate400,
                            fontSize = 14.sp
                        )
                    }
                } else {
                    LazyColumn(
                        modifier = Modifier
                            .fillMaxSize()
                            .weight(1f),
                        contentPadding = PaddingValues(bottom = 16.dp)
                    ) {
                        items(orders) { order ->
                            OrderCard(order = order, onClick = {
                                viewModel.loadOrderDetails(order.id)
                            })
                        }
                    }
                }
            }
        }
    }

    // Order Details Modal Dialog
    if (selectedOrder != null) {
        OrderDetailDialog(
            order = selectedOrder!!,
            isUpdatingStatus = isUpdatingStatus,
            onDismiss = { viewModel.clearSelectedOrder() },
            onUpdateStatus = { newStatus ->
                viewModel.updateOrderStatus(selectedOrder!!.id, newStatus)
            }
        )
    }
}

@Composable
fun OrderCard(
    order: OrderSummary,
    onClick: () -> Unit
) {
    Card(
        modifier = Modifier
            .fillMaxWidth()
            .padding(horizontal = 16.dp, vertical = 8.dp)
            .clickable(onClick = onClick)
            .border(1.dp, GlassWhite10, RoundedCornerShape(12.dp)),
        colors = CardDefaults.cardColors(containerColor = Slate900),
        shape = RoundedCornerShape(12.dp)
    ) {
        Column(
            modifier = Modifier.padding(16.dp)
        ) {
            Row(
                modifier = Modifier.fillMaxWidth(),
                horizontalArrangement = Arrangement.SpaceBetween,
                verticalAlignment = Alignment.CenterVertically
            ) {
                Text(
                    text = "Bestellung #${order.order_number}",
                    fontWeight = FontWeight.Bold,
                    fontSize = 16.sp,
                    color = Gold
                )
                StatusBadge(status = order.status, statusColorHex = order.status_color)
            }
            Spacer(modifier = Modifier.height(8.dp))
            Text(
                text = order.customer_name,
                color = Slate50,
                fontSize = 14.sp,
                fontWeight = FontWeight.Medium
            )
            Text(
                text = order.email,
                color = Slate400,
                fontSize = 12.sp
            )
            Spacer(modifier = Modifier.height(12.dp))
            Divider(color = GlassWhite10)
            Spacer(modifier = Modifier.height(12.dp))
            Row(
                modifier = Modifier.fillMaxWidth(),
                horizontalArrangement = Arrangement.SpaceBetween,
                verticalAlignment = Alignment.CenterVertically
            ) {
                Column {
                    Text(
                        text = "Datum: " + formatDateString(order.created_at),
                        color = Slate400,
                        fontSize = 11.sp
                    )
                    Text(
                        text = "${order.item_count} Artikel • ${order.payment_method}",
                        color = Slate400,
                        fontSize = 11.sp
                    )
                }
                Text(
                    text = formatPrice(order.total_price),
                    fontWeight = FontWeight.Bold,
                    fontSize = 18.sp,
                    color = Gold
                )
            }
        }
    }
}

@OptIn(ExperimentalMaterial3Api::class)
@Composable
fun OrderDetailDialog(
    order: OrderDetail,
    isUpdatingStatus: Boolean,
    onDismiss: () -> Unit,
    onUpdateStatus: (String) -> Unit
) {
    var dropdownExpanded by remember { mutableStateOf(false) }

    val statusOptions = listOf(
        "pending" to "Ausstehend",
        "processing" to "In Bearbeitung",
        "shipped" to "Versendet",
        "completed" to "Abgeschlossen",
        "cancelled" to "Storniert",
        "refunded" to "Erstattet"
    )

    Dialog(
        onDismissRequest = onDismiss,
        properties = DialogProperties(usePlatformDefaultWidth = false)
    ) {
        Surface(
            modifier = Modifier
                .fillMaxSize()
                .background(SpaceBlack),
            color = SpaceBlack
        ) {
            Column(
                modifier = Modifier
                    .fillMaxSize()
                    .padding(16.dp)
            ) {
                // Header Bar
                Row(
                    modifier = Modifier.fillMaxWidth(),
                    horizontalArrangement = Arrangement.SpaceBetween,
                    verticalAlignment = Alignment.CenterVertically
                ) {
                    Text(
                        text = "Bestellung #${order.order_number}",
                        fontWeight = FontWeight.Bold,
                        fontSize = 20.sp,
                        color = Gold
                    )
                    IconButton(onClick = onDismiss) {
                        Icon(
                            imageVector = Icons.Default.Close,
                            contentDescription = "Schließen",
                            tint = Slate50
                        )
                    }
                }
                Spacer(modifier = Modifier.height(16.dp))

                LazyColumn(
                    modifier = Modifier
                        .fillMaxWidth()
                        .weight(1f)
                ) {
                    // Quick stats
                    item {
                        Card(
                            modifier = Modifier
                                .fillMaxWidth()
                                .padding(vertical = 8.dp)
                                .border(1.dp, GlassWhite10, RoundedCornerShape(12.dp)),
                            colors = CardDefaults.cardColors(containerColor = Slate900),
                            shape = RoundedCornerShape(12.dp)
                        ) {
                            Column(modifier = Modifier.padding(16.dp)) {
                                Row(
                                    modifier = Modifier.fillMaxWidth(),
                                    horizontalArrangement = Arrangement.SpaceBetween
                                ) {
                                    Column {
                                        Text("Kunde", color = Slate400, fontSize = 12.sp)
                                        Text(order.customer_name, color = Slate50, fontWeight = FontWeight.Bold)
                                        Text(order.email, color = Slate400, fontSize = 12.sp)
                                    }
                                    Column(horizontalAlignment = Alignment.End) {
                                        Text("Bestellwert", color = Slate400, fontSize = 12.sp)
                                        Text(formatPrice(order.total_price), color = Gold, fontWeight = FontWeight.Bold, fontSize = 18.sp)
                                    }
                                }
                                Spacer(modifier = Modifier.height(12.dp))
                                Row(
                                    modifier = Modifier.fillMaxWidth(),
                                    horizontalArrangement = Arrangement.SpaceBetween,
                                    verticalAlignment = Alignment.CenterVertically
                                ) {
                                    Column {
                                        Text("Zahlung", color = Slate400, fontSize = 12.sp)
                                        Row(verticalAlignment = Alignment.CenterVertically) {
                                            StatusBadge(status = order.payment_status, statusColorHex = order.payment_status_color)
                                            Spacer(modifier = Modifier.width(6.dp))
                                            Text(order.payment_method, color = Slate50, fontSize = 12.sp)
                                        }
                                    }
                                    if (order.is_express == true) {
                                        Text(
                                            text = "🚀 EXPRESS",
                                            color = Color.Red,
                                            fontWeight = FontWeight.Bold,
                                            fontSize = 12.sp
                                        )
                                    }
                                }
                            }
                        }
                    }

                    // Status Management
                    item {
                        Card(
                            modifier = Modifier
                                .fillMaxWidth()
                                .padding(vertical = 8.dp)
                                .border(1.dp, GlassWhite10, RoundedCornerShape(12.dp)),
                            colors = CardDefaults.cardColors(containerColor = Slate900),
                            shape = RoundedCornerShape(12.dp)
                        ) {
                            Column(modifier = Modifier.padding(16.dp)) {
                                Text("Bestellstatus verwalten", color = Slate400, fontSize = 12.sp)
                                Spacer(modifier = Modifier.height(8.dp))
                                Row(
                                    modifier = Modifier.fillMaxWidth(),
                                    horizontalArrangement = Arrangement.SpaceBetween,
                                    verticalAlignment = Alignment.CenterVertically
                                ) {
                                    if (isUpdatingStatus) {
                                        CircularProgressIndicator(color = Gold, modifier = Modifier.size(24.dp))
                                    } else {
                                        StatusBadge(status = order.status, statusColorHex = order.status_color)
                                    }
                                    
                                    Box {
                                        Button(
                                            onClick = { dropdownExpanded = true },
                                            colors = ButtonDefaults.buttonColors(containerColor = Gold),
                                            shape = RoundedCornerShape(8.dp),
                                            enabled = !isUpdatingStatus
                                        ) {
                                            Text("Status ändern", color = SpaceBlack, fontWeight = FontWeight.Bold)
                                        }
                                        DropdownMenu(
                                            expanded = dropdownExpanded,
                                            onDismissRequest = { dropdownExpanded = false },
                                            modifier = Modifier.background(Slate900)
                                        ) {
                                            statusOptions.forEach { (statusVal, statusLabel) ->
                                                DropdownMenuItem(
                                                    text = { Text(statusLabel, color = Slate50) },
                                                    onClick = {
                                                        onUpdateStatus(statusVal)
                                                        dropdownExpanded = false
                                                    }
                                                )
                                            }
                                        }
                                    }
                                }
                            }
                        }
                    }

                    // Addresses
                    item {
                        Row(
                            modifier = Modifier
                                .fillMaxWidth()
                                .padding(vertical = 8.dp),
                            horizontalArrangement = Arrangement.spacedBy(8.dp)
                        ) {
                            Card(
                                modifier = Modifier
                                    .weight(1f)
                                    .border(1.dp, GlassWhite10, RoundedCornerShape(12.dp)),
                                colors = CardDefaults.cardColors(containerColor = Slate900),
                                shape = RoundedCornerShape(12.dp)
                            ) {
                                Column(modifier = Modifier.padding(12.dp)) {
                                    Text("Rechnungsadresse", color = Slate400, fontSize = 12.sp, fontWeight = FontWeight.SemiBold)
                                    Spacer(modifier = Modifier.height(6.dp))
                                    Text(
                                        text = formatAddress(order.billing_address),
                                        color = Slate50,
                                        fontSize = 12.sp,
                                        lineHeight = 16.sp
                                    )
                                }
                            }
                            Card(
                                modifier = Modifier
                                    .weight(1f)
                                    .border(1.dp, GlassWhite10, RoundedCornerShape(12.dp)),
                                colors = CardDefaults.cardColors(containerColor = Slate900),
                                shape = RoundedCornerShape(12.dp)
                            ) {
                                Column(modifier = Modifier.padding(12.dp)) {
                                    Text("Lieferadresse", color = Slate400, fontSize = 12.sp, fontWeight = FontWeight.SemiBold)
                                    Spacer(modifier = Modifier.height(6.dp))
                                    Text(
                                        text = formatAddress(order.shipping_address),
                                        color = Slate50,
                                        fontSize = 12.sp,
                                        lineHeight = 16.sp
                                    )
                                }
                            }
                        }
                    }

                    // Items
                    item {
                        Card(
                            modifier = Modifier
                                .fillMaxWidth()
                                .padding(vertical = 8.dp)
                                .border(1.dp, GlassWhite10, RoundedCornerShape(12.dp)),
                            colors = CardDefaults.cardColors(containerColor = Slate900),
                            shape = RoundedCornerShape(12.dp)
                        ) {
                            Column(modifier = Modifier.padding(16.dp)) {
                                Text("Bestellte Artikel", color = Slate400, fontSize = 12.sp, fontWeight = FontWeight.SemiBold)
                                Spacer(modifier = Modifier.height(8.dp))
                                order.items.forEachIndexed { index, item ->
                                    Row(
                                        modifier = Modifier
                                            .fillMaxWidth()
                                            .padding(vertical = 6.dp),
                                        horizontalArrangement = Arrangement.SpaceBetween,
                                        verticalAlignment = Alignment.Top
                                    ) {
                                        Column(modifier = Modifier.weight(1f)) {
                                            Text(item.product_name, color = Slate50, fontSize = 14.sp, fontWeight = FontWeight.Medium)
                                            if (item.configuration != null && item.configuration.isNotEmpty()) {
                                                val configStr = item.configuration.entries.joinToString(", ") { "${it.key}: ${it.value}" }
                                                Text(configStr, color = Slate400, fontSize = 11.sp)
                                            }
                                        }
                                        Text(
                                            text = "${item.quantity}x ${formatPrice(item.unit_price)}",
                                            color = Slate400,
                                            fontSize = 12.sp,
                                            modifier = Modifier.padding(horizontal = 8.dp)
                                        )
                                        Text(
                                            text = formatPrice(item.total_price),
                                            color = Slate50,
                                            fontSize = 14.sp,
                                            fontWeight = FontWeight.Bold
                                        )
                                    }
                                    if (index < order.items.size - 1) {
                                        Divider(color = GlassWhite10)
                                    }
                                }
                            }
                        }
                    }

                    // Order Summary & Financials
                    item {
                        Card(
                            modifier = Modifier
                                .fillMaxWidth()
                                .padding(vertical = 8.dp)
                                .border(1.dp, GlassWhite10, RoundedCornerShape(12.dp)),
                            colors = CardDefaults.cardColors(containerColor = Slate900),
                            shape = RoundedCornerShape(12.dp)
                        ) {
                            Column(modifier = Modifier.padding(16.dp)) {
                                Text("Übersicht", color = Slate400, fontSize = 12.sp, fontWeight = FontWeight.SemiBold)
                                Spacer(modifier = Modifier.height(8.dp))
                                
                                val prices = mutableListOf<Pair<String, Long>>()
                                order.subtotal_price?.let { prices.add("Zwischensumme" to it) }
                                order.discount_amount?.let { if (it > 0) prices.add("Rabatt" to -it) }
                                order.volume_discount?.let { if (it > 0) prices.add("Mengenrabatt" to -it) }
                                order.shipping_price?.let { prices.add("Versandkosten" to it) }
                                order.express_price?.let { if (it > 0) prices.add("Express-Zuschlag" to it) }

                                prices.forEach { (label, value) ->
                                    Row(
                                        modifier = Modifier.fillMaxWidth().padding(vertical = 3.dp),
                                        horizontalArrangement = Arrangement.SpaceBetween
                                    ) {
                                        Text(label, color = Slate400, fontSize = 12.sp)
                                        Text(formatPrice(value), color = Slate400, fontSize = 12.sp)
                                    }
                                }

                                Divider(color = GlassWhite10, modifier = Modifier.padding(vertical = 8.dp))
                                Row(
                                    modifier = Modifier.fillMaxWidth(),
                                    horizontalArrangement = Arrangement.SpaceBetween
                                ) {
                                    Text("Gesamtsumme", color = Slate50, fontWeight = FontWeight.Bold, fontSize = 16.sp)
                                    Text(formatPrice(order.total_price), color = Gold, fontWeight = FontWeight.Bold, fontSize = 16.sp)
                                }
                            }
                        }
                    }

                    // Notes
                    if (!order.notes.isNullOrEmpty()) {
                        item {
                            Card(
                                modifier = Modifier
                                    .fillMaxWidth()
                                    .padding(vertical = 8.dp)
                                    .border(1.dp, GlassWhite10, RoundedCornerShape(12.dp)),
                                colors = CardDefaults.cardColors(containerColor = Slate900),
                                shape = RoundedCornerShape(12.dp)
                            ) {
                                Column(modifier = Modifier.padding(16.dp)) {
                                    Text("Bemerkung des Kunden", color = Slate400, fontSize = 12.sp, fontWeight = FontWeight.SemiBold)
                                    Spacer(modifier = Modifier.height(6.dp))
                                    Text(order.notes, color = Slate50, fontSize = 13.sp)
                                }
                            }
                        }
                    }
                }
            }
        }
    }
}

@Composable
fun StatusBadge(status: String, statusColorHex: String?) {
    val backgroundColor = statusColorHex?.let {
        try {
            Color(android.graphics.Color.parseColor(it)).copy(alpha = 0.15f)
        } catch (e: Exception) {
            Color(0x33C5A059)
        }
    } ?: Color(0x33C5A059)

    val textColor = statusColorHex?.let {
        try {
            Color(android.graphics.Color.parseColor(it))
        } catch (e: Exception) {
            Gold
        }
    } ?: Gold

    val label = when (status) {
        "pending" -> "Ausstehend"
        "processing" -> "In Bearbeitung"
        "shipped" -> "Versendet"
        "completed" -> "Abgeschlossen"
        "cancelled" -> "Storniert"
        "refunded" -> "Erstattet"
        "paid" -> "Bezahlt"
        "unpaid" -> "Unbezahlt"
        else -> status.capitalize(Locale.ROOT)
    }

    Surface(
        color = backgroundColor,
        shape = RoundedCornerShape(6.dp),
        modifier = Modifier.border(1.dp, textColor.copy(alpha = 0.3f), RoundedCornerShape(6.dp))
    ) {
        Text(
            text = label.uppercase(Locale.ROOT),
            color = textColor,
            fontSize = 10.sp,
            fontWeight = FontWeight.Bold,
            modifier = Modifier.padding(horizontal = 8.dp, vertical = 4.dp)
        )
    }
}

fun formatPrice(cents: Long): String {
    return String.format(Locale.GERMANY, "%,.2f €", cents / 100.0)
}

fun formatDateString(dateStr: String): String {
    return try {
        val isoFormat = SimpleDateFormat("yyyy-MM-dd'T'HH:mm:ss", Locale.GERMANY)
        val date = isoFormat.parse(dateStr) ?: return dateStr
        val displayFormat = SimpleDateFormat("dd.MM.yyyy HH:mm", Locale.GERMANY)
        displayFormat.format(date)
    } catch (e: Exception) {
        dateStr
    }
}

fun formatAddress(address: Map<String, Any>?): String {
    if (address == null) return "Keine Adresse angegeben"
    val company = address["company"]?.toString()?.trim()
    val firstName = address["first_name"]?.toString() ?: ""
    val lastName = address["last_name"]?.toString() ?: ""
    val street = address["street"]?.toString() ?: ""
    val houseNumber = address["house_number"]?.toString() ?: address["street_number"]?.toString() ?: ""
    val postcode = address["postcode"]?.toString() ?: address["zip"]?.toString() ?: ""
    val city = address["city"]?.toString() ?: ""
    val country = address["country"]?.toString() ?: ""

    val sb = StringBuilder()
    if (!company.isNullOrEmpty()) sb.append(company).append("\n")
    if (firstName.isNotEmpty() || lastName.isNotEmpty()) {
        sb.append("$firstName $lastName").append("\n")
    }
    if (street.isNotEmpty()) {
        sb.append("$street $houseNumber".trim()).append("\n")
    }
    if (postcode.isNotEmpty() || city.isNotEmpty()) {
        sb.append("$postcode $city".trim()).append("\n")
    }
    if (country.isNotEmpty()) {
        sb.append(country)
    }
    return sb.toString().trim()
}
