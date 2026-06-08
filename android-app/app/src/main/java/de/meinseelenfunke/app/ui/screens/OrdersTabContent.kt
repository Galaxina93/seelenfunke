package de.meinseelenfunke.app.ui.screens

import androidx.compose.animation.core.*
import androidx.compose.foundation.background
import androidx.compose.foundation.border
import androidx.compose.foundation.clickable
import androidx.compose.foundation.layout.*
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.lazy.items
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.Close
import androidx.compose.material.icons.filled.FilterList
import androidx.compose.material.icons.filled.Refresh
import androidx.compose.material.icons.filled.Search
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
import coil.compose.AsyncImage
import androidx.compose.ui.layout.ContentScale

@OptIn(ExperimentalMaterial3Api::class)
@Composable
fun OrdersTabContent(
    viewModel: OrderViewModel = viewModel()
) {
    val uiState by viewModel.uiState.collectAsState()
    val orders by viewModel.orders.collectAsState()
    val selectedOrder by viewModel.selectedOrder.collectAsState()
    val statusFilter by viewModel.statusFilter.collectAsState()
    val searchQuery by viewModel.searchQuery.collectAsState()
    val priorityOrder by viewModel.priorityOrder.collectAsState()
    val isUpdatingStatus by viewModel.isUpdatingStatus.collectAsState()
    val isGeneratingDhlLabel by viewModel.isGeneratingDhlLabel.collectAsState()
    val dhlLabelError by viewModel.dhlLabelError.collectAsState()
    val dhlLabelSuccessMessage by viewModel.dhlLabelSuccessMessage.collectAsState()

    LaunchedEffect(selectedOrder) {
        viewModel.clearDhlLabelState()
    }

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
        // Search bar
        OutlinedTextField(
            value = searchQuery,
            onValueChange = { viewModel.setSearchQuery(it) },
            placeholder = { Text("Bestellungen durchsuchen...", color = Slate400) },
            leadingIcon = { Icon(Icons.Default.Search, contentDescription = "Suchen", tint = Gold) },
            trailingIcon = {
                if (searchQuery.isNotEmpty()) {
                    IconButton(onClick = { viewModel.setSearchQuery("") }) {
                        Icon(Icons.Default.Close, contentDescription = "Löschen", tint = Slate400)
                    }
                }
            },
            colors = OutlinedTextFieldDefaults.colors(
                focusedBorderColor = Gold,
                unfocusedBorderColor = GlassWhite10,
                focusedLabelColor = Gold,
                unfocusedLabelColor = Slate400,
                focusedTextColor = Slate50,
                unfocusedTextColor = Slate50,
                cursorColor = Gold
            ),
            modifier = Modifier
                .fillMaxWidth()
                .padding(horizontal = 16.dp, vertical = 8.dp),
            singleLine = true,
            shape = RoundedCornerShape(12.dp)
        )

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
                IconButton(onClick = { 
                    val apiFilter = if (statusFilter == "all") null else statusFilter
                    val apiSearch = if (searchQuery.isEmpty()) null else searchQuery
                    viewModel.loadOrders(apiFilter, apiSearch) 
                }) {
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
                LazyColumn(
                    modifier = Modifier
                        .fillMaxSize()
                        .weight(1f),
                    contentPadding = PaddingValues(bottom = 16.dp)
                ) {
                    if (priorityOrder != null) {
                        item {
                            PriorityTaskCard(order = priorityOrder!!, onClick = {
                                viewModel.loadOrderDetails(priorityOrder!!.id)
                            })
                        }
                    }

                    if (orders.isEmpty()) {
                        item {
                            Box(
                                modifier = Modifier
                                    .fillParentMaxHeight(0.7f)
                                    .fillMaxWidth(),
                                contentAlignment = Alignment.Center
                            ) {
                                Text(
                                    text = "Keine Bestellungen gefunden.",
                                    color = Slate400,
                                    fontSize = 14.sp
                                )
                            }
                        }
                    } else {
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
            isGeneratingDhlLabel = isGeneratingDhlLabel,
            dhlLabelError = dhlLabelError,
            dhlLabelSuccessMessage = dhlLabelSuccessMessage,
            onDismiss = { viewModel.clearSelectedOrder() },
            onUpdateStatus = { newStatus ->
                viewModel.updateOrderStatus(selectedOrder!!.id, newStatus)
            },
            onCreateDhlLabel = { packageCount, weight ->
                viewModel.createDhlLabel(selectedOrder!!.id, packageCount, weight)
            },
            onClearDhlState = {
                viewModel.clearDhlLabelState()
            }
        )
    }
}

@Composable
fun PriorityTaskCard(
    order: OrderSummary,
    onClick: () -> Unit
) {
    Card(
        modifier = Modifier
            .fillMaxWidth()
            .padding(horizontal = 16.dp, vertical = 12.dp)
            .clickable(onClick = onClick)
            .border(1.dp, if (order.is_express == true) Color(0xFFEF4444) else Gold, RoundedCornerShape(16.dp)),
        colors = CardDefaults.cardColors(containerColor = Slate900),
        shape = RoundedCornerShape(16.dp)
    ) {
        Column(
            modifier = Modifier.padding(16.dp)
        ) {
            Row(
                verticalAlignment = Alignment.CenterVertically,
                horizontalArrangement = Arrangement.spacedBy(6.dp)
            ) {
                Text(
                    text = if (order.is_express == true) "🚀" else "📦",
                    fontSize = 14.sp
                )
                Text(
                    text = if (order.is_express == true) "EXPRESS-AUFTRAG" else "WICHTIGSTE AUFGABE",
                    color = if (order.is_express == true) Color(0xFFEF4444) else Gold,
                    fontSize = 11.sp,
                    fontWeight = FontWeight.Bold,
                    letterSpacing = 1.sp
                )
            }
            Spacer(modifier = Modifier.height(8.dp))
            Text(
                text = "Bestellung #${order.order_number} von ${order.customer_name}",
                fontWeight = FontWeight.Bold,
                fontSize = 16.sp,
                color = Slate50
            )
            Spacer(modifier = Modifier.height(8.dp))
            
            val tipText = order.priority_tip ?: if (order.is_express == true) {
                "Dies ist ein dringender Express-Auftrag! Bitte sofort abwickeln, verpacken und versenden."
            } else {
                "Dies ist der älteste offene Auftrag im System. Bitte zügig bearbeiten, um die Wartezeiten gering zu halten."
            }
            
            Surface(
                color = GlassWhite10,
                shape = RoundedCornerShape(8.dp),
                modifier = Modifier.fillMaxWidth()
            ) {
                Text(
                    text = tipText,
                    color = Slate300,
                    fontSize = 12.sp,
                    lineHeight = 16.sp,
                    modifier = Modifier.padding(10.dp)
                )
            }
        }
    }
}

@Composable
fun OrderCard(
    order: OrderSummary,
    onClick: () -> Unit
) {
    val isActive = order.status != "completed" && order.status != "cancelled" && order.status != "refunded"
    val isExpressActive = order.is_express == true && isActive
    val cardBorderColor = if (isExpressActive) Gold else GlassWhite10
    val cardBgColor = if (isExpressActive) Color(0x15C5A059) else Slate900

    val infiniteTransition = rememberInfiniteTransition(label = "pulse")
    val alpha by infiniteTransition.animateFloat(
        initialValue = 0.3f,
        targetValue = 1f,
        animationSpec = infiniteRepeatable(
            animation = keyframes { durationMillis = 800 },
            repeatMode = RepeatMode.Reverse
        ),
        label = "alpha"
    )

    Card(
        modifier = Modifier
            .fillMaxWidth()
            .padding(horizontal = 16.dp, vertical = 8.dp)
            .clickable(onClick = onClick)
            .border(1.dp, cardBorderColor, RoundedCornerShape(12.dp)),
        colors = CardDefaults.cardColors(containerColor = cardBgColor),
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
                Row(
                    verticalAlignment = Alignment.CenterVertically
                ) {
                    Text(
                        text = "Bestellung #${order.order_number}",
                        fontWeight = FontWeight.Bold,
                        fontSize = 16.sp,
                        color = Gold
                    )
                    if (order.is_express == true) {
                        Spacer(modifier = Modifier.width(6.dp))
                        Surface(
                            color = Color(0x22EF4444),
                            shape = RoundedCornerShape(4.dp),
                            modifier = Modifier.border(0.5.dp, Color(0xFFEF4444), RoundedCornerShape(4.dp))
                        ) {
                            Row(
                                verticalAlignment = Alignment.CenterVertically,
                                modifier = Modifier.padding(horizontal = 6.dp, vertical = 2.dp),
                                horizontalArrangement = Arrangement.spacedBy(4.dp)
                            ) {
                                Box(
                                    modifier = Modifier
                                        .size(6.dp)
                                        .background(Color(0xFFEF4444).copy(alpha = alpha), CircleShape)
                                )
                                Text(
                                    text = "EXPRESS",
                                    color = Color(0xFFEF4444),
                                    fontWeight = FontWeight.Bold,
                                    fontSize = 8.sp
                                )
                            }
                        }
                    }
                }
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
    isGeneratingDhlLabel: Boolean,
    dhlLabelError: String?,
    dhlLabelSuccessMessage: String?,
    onDismiss: () -> Unit,
    onUpdateStatus: (String) -> Unit,
    onCreateDhlLabel: (Int, Double) -> Unit,
    onClearDhlState: () -> Unit
) {
    var dropdownExpanded by remember { mutableStateOf(false) }
    var zoomImageUrl by remember { mutableStateOf<String?>(null) }

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
                    .padding(20.dp)
            ) {
                // Header Bar
                Row(
                    modifier = Modifier.fillMaxWidth(),
                    horizontalArrangement = Arrangement.SpaceBetween,
                    verticalAlignment = Alignment.CenterVertically
                ) {
                    Column {
                        Text(
                            text = "Bestellung Details",
                            color = Slate400,
                            fontSize = 12.sp,
                            fontWeight = FontWeight.Bold,
                            letterSpacing = 1.sp
                        )
                        Spacer(modifier = Modifier.height(2.dp))
                        Text(
                            text = "#${order.order_number}",
                            fontWeight = FontWeight.Bold,
                            fontSize = 24.sp,
                            color = Gold
                        )
                    }
                    IconButton(
                        onClick = onDismiss,
                        modifier = Modifier
                            .background(GlassWhite10, RoundedCornerShape(12.dp))
                            .size(40.dp)
                    ) {
                        Icon(
                            imageVector = Icons.Default.Close,
                            contentDescription = "Schließen",
                            tint = Slate50
                        )
                    }
                }
                Spacer(modifier = Modifier.height(20.dp))

                LazyColumn(
                    modifier = Modifier
                        .fillMaxWidth()
                        .weight(1f),
                    verticalArrangement = Arrangement.spacedBy(16.dp),
                    contentPadding = PaddingValues(bottom = 24.dp)
                ) {
                    // Quick Stats & Status Management Card
                    item {
                        Card(
                            modifier = Modifier
                                .fillMaxWidth()
                                .border(1.dp, GlassWhite10, RoundedCornerShape(16.dp)),
                            colors = CardDefaults.cardColors(containerColor = Slate900),
                            shape = RoundedCornerShape(16.dp)
                        ) {
                            Column(modifier = Modifier.padding(16.dp)) {
                                Row(
                                    modifier = Modifier.fillMaxWidth(),
                                    horizontalArrangement = Arrangement.SpaceBetween,
                                    verticalAlignment = Alignment.CenterVertically
                                ) {
                                    Column {
                                        Text("KUNDE", color = Slate400, fontSize = 10.sp, fontWeight = FontWeight.Bold, letterSpacing = 0.5.sp)
                                        Text(order.customer_name, color = Slate50, fontWeight = FontWeight.Bold, fontSize = 16.sp)
                                        Text(order.email, color = Slate400, fontSize = 12.sp)
                                    }
                                    Column(horizontalAlignment = Alignment.End) {
                                        Text("BESTELLWERT", color = Slate400, fontSize = 10.sp, fontWeight = FontWeight.Bold, letterSpacing = 0.5.sp)
                                        Text(formatPrice(order.total_price), color = Gold, fontWeight = FontWeight.Bold, fontSize = 20.sp)
                                    }
                                }
                                
                                Spacer(modifier = Modifier.height(16.dp))
                                Divider(color = GlassWhite10)
                                Spacer(modifier = Modifier.height(16.dp))
                                
                                Row(
                                    modifier = Modifier.fillMaxWidth(),
                                    horizontalArrangement = Arrangement.SpaceBetween,
                                    verticalAlignment = Alignment.CenterVertically
                                ) {
                                    Column {
                                        Text("ZAHLUNG", color = Slate400, fontSize = 10.sp, fontWeight = FontWeight.Bold, letterSpacing = 0.5.sp)
                                        Row(
                                            verticalAlignment = Alignment.CenterVertically,
                                            modifier = Modifier.padding(top = 4.dp)
                                        ) {
                                            StatusBadge(status = order.payment_status, statusColorHex = order.payment_status_color)
                                            Spacer(modifier = Modifier.width(8.dp))
                                            Text(order.payment_method.uppercase(), color = Slate50, fontSize = 12.sp, fontWeight = FontWeight.Bold)
                                        }
                                    }
                                    if (order.is_express == true) {
                                        Surface(
                                            color = Color(0x22EF4444),
                                            shape = RoundedCornerShape(8.dp),
                                            modifier = Modifier.border(1.dp, Color(0x66EF4444), RoundedCornerShape(8.dp))
                                        ) {
                                            Text(
                                                text = "🚀 EXPRESSVERSAND",
                                                color = Color(0xFFEF4444),
                                                fontWeight = FontWeight.Bold,
                                                fontSize = 11.sp,
                                                modifier = Modifier.padding(horizontal = 10.dp, vertical = 6.dp)
                                            )
                                        }
                                    }
                                }

                                Spacer(modifier = Modifier.height(16.dp))
                                Divider(color = GlassWhite10)
                                Spacer(modifier = Modifier.height(16.dp))

                                // Status management
                                Row(
                                    modifier = Modifier.fillMaxWidth(),
                                    horizontalArrangement = Arrangement.SpaceBetween,
                                    verticalAlignment = Alignment.CenterVertically
                                ) {
                                    Column {
                                        Text("STATUS", color = Slate400, fontSize = 10.sp, fontWeight = FontWeight.Bold, letterSpacing = 0.5.sp)
                                        Spacer(modifier = Modifier.height(4.dp))
                                        if (isUpdatingStatus) {
                                            CircularProgressIndicator(color = Gold, modifier = Modifier.size(24.dp))
                                        } else {
                                            StatusBadge(status = order.status, statusColorHex = order.status_color)
                                        }
                                    }
                                    
                                    Box {
                                        Button(
                                            onClick = { dropdownExpanded = true },
                                            colors = ButtonDefaults.buttonColors(containerColor = Gold),
                                            shape = RoundedCornerShape(10.dp),
                                            enabled = !isUpdatingStatus
                                        ) {
                                            Text("Status ändern", color = SpaceBlack, fontWeight = FontWeight.Bold, fontSize = 13.sp)
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

                    // DHL Labels / Shipments Card
                    val shipments = order.shipments
                    val isOnlyDigital = order.items.isNotEmpty() && order.items.all {
                        it.product_name.contains("digital", ignoreCase = true) || 
                        it.product_name.contains("download", ignoreCase = true)
                    }
                    if (!isOnlyDigital) {
                        item {
                            val context = androidx.compose.ui.platform.LocalContext.current
                            var showDhlCreateDialog by remember { mutableStateOf(false) }
                            
                            Card(
                                modifier = Modifier
                                    .fillMaxWidth()
                                    .border(1.dp, GlassWhite10, RoundedCornerShape(16.dp)),
                                colors = CardDefaults.cardColors(containerColor = Slate900),
                                shape = RoundedCornerShape(16.dp)
                            ) {
                                Column(modifier = Modifier.padding(16.dp)) {
                                    Text(
                                        text = "DHL VERSANDSCHEINE & VERFOLGUNG",
                                        color = Slate400,
                                        fontSize = 10.sp,
                                        fontWeight = FontWeight.Bold,
                                        letterSpacing = 0.5.sp
                                    )
                                    Spacer(modifier = Modifier.height(12.dp))
                                    
                                    if (!shipments.isNullOrEmpty()) {
                                        shipments.forEachIndexed { idx, shipment ->
                                            Row(
                                                modifier = Modifier.fillMaxWidth(),
                                                horizontalArrangement = Arrangement.SpaceBetween,
                                                verticalAlignment = Alignment.CenterVertically
                                            ) {
                                                Column(modifier = Modifier.weight(1f)) {
                                                    Text(
                                                        text = "Paket ${idx + 1}: ${shipment.carrier ?: "DHL"}",
                                                        color = Slate50,
                                                        fontSize = 14.sp,
                                                        fontWeight = FontWeight.Bold
                                                    )
                                                    if (!shipment.tracking_number.isNullOrEmpty()) {
                                                        Text(
                                                            text = "Sendungsnummer: ${shipment.tracking_number}",
                                                            color = Gold,
                                                            fontSize = 12.sp,
                                                            modifier = Modifier
                                                                .clickable {
                                                                    val trackingUrl = "https://www.dhl.de/de/privatkunden/pakete-empfangen/verfolgen.html?piececode=${shipment.tracking_number}"
                                                                    try {
                                                                        val intent = android.content.Intent(android.content.Intent.ACTION_VIEW, android.net.Uri.parse(trackingUrl))
                                                                        context.startActivity(intent)
                                                                    } catch (e: Exception) {}
                                                                }
                                                                .padding(vertical = 2.dp)
                                                        )
                                                    }
                                                    if (!shipment.status.isNullOrEmpty()) {
                                                        Text(
                                                            text = "Status: ${shipment.status}",
                                                            color = Slate400,
                                                            fontSize = 11.sp,
                                                            fontWeight = FontWeight.Medium
                                                        )
                                                    }
                                                }
                                                
                                                val labelPath = shipment.shipping_label_path
                                                if (!labelPath.isNullOrEmpty()) {
                                                    Button(
                                                        onClick = {
                                                            val labelUrl = resolveImageUrl(labelPath)
                                                            if (labelUrl != null) {
                                                                try {
                                                                    val intent = android.content.Intent(android.content.Intent.ACTION_VIEW, android.net.Uri.parse(labelUrl))
                                                                    context.startActivity(intent)
                                                                } catch (e: Exception) {}
                                                            }
                                                        },
                                                        colors = ButtonDefaults.buttonColors(containerColor = GlassWhite10),
                                                        shape = RoundedCornerShape(8.dp),
                                                        contentPadding = PaddingValues(horizontal = 12.dp, vertical = 6.dp)
                                                    ) {
                                                        Text("Label öffnen (PDF)", color = Gold, fontSize = 12.sp, fontWeight = FontWeight.Bold)
                                                    }
                                                }
                                            }
                                            if (idx < shipments.size - 1) {
                                                Divider(color = GlassWhite10, modifier = Modifier.padding(vertical = 8.dp))
                                            }
                                        }
                                        Spacer(modifier = Modifier.height(16.dp))
                                    }
                                    
                                    if (isGeneratingDhlLabel) {
                                        Box(
                                            modifier = Modifier.fillMaxWidth().padding(vertical = 8.dp),
                                            contentAlignment = Alignment.Center
                                        ) {
                                            Row(
                                                verticalAlignment = Alignment.CenterVertically,
                                                horizontalArrangement = Arrangement.spacedBy(8.dp)
                                            ) {
                                                CircularProgressIndicator(color = Gold, modifier = Modifier.size(20.dp))
                                                Text("DHL Label wird generiert...", color = Slate300, fontSize = 12.sp)
                                            }
                                        }
                                    } else {
                                        Button(
                                            onClick = { onClearDhlState(); showDhlCreateDialog = true },
                                            colors = ButtonDefaults.buttonColors(containerColor = Gold),
                                            shape = RoundedCornerShape(10.dp),
                                            modifier = Modifier.fillMaxWidth()
                                        ) {
                                            Text(
                                                text = if (shipments.isNullOrEmpty()) "DHL Label erstellen" else "Weiteres DHL Label erstellen",
                                                color = SpaceBlack,
                                                fontWeight = FontWeight.Bold,
                                                fontSize = 13.sp
                                            )
                                        }
                                    }
                                    
                                    dhlLabelError?.let { error ->
                                        Spacer(modifier = Modifier.height(8.dp))
                                        Text(
                                            text = error,
                                            color = Color.Red,
                                            fontSize = 12.sp,
                                            fontWeight = FontWeight.Bold
                                        )
                                    }
                                    
                                    dhlLabelSuccessMessage?.let { msg ->
                                        Spacer(modifier = Modifier.height(8.dp))
                                        Text(
                                            text = msg,
                                            color = Color.Green,
                                            fontSize = 12.sp,
                                            fontWeight = FontWeight.Bold
                                        )
                                    }
                                }
                            }
                            
                            if (showDhlCreateDialog) {
                                DhlCreateDialog(
                                    onDismiss = { showDhlCreateDialog = false },
                                    onCreate = { packageCount, weight ->
                                        onCreateDhlLabel(packageCount, weight)
                                    }
                                )
                            }
                        }
                    }

                    // Addresses (Rechnung / Lieferung side-by-side)
                    item {
                        Row(
                            modifier = Modifier.fillMaxWidth(),
                            horizontalArrangement = Arrangement.spacedBy(12.dp)
                        ) {
                            Card(
                                modifier = Modifier
                                    .weight(1f)
                                    .border(1.dp, GlassWhite10, RoundedCornerShape(16.dp)),
                                colors = CardDefaults.cardColors(containerColor = Slate900),
                                shape = RoundedCornerShape(16.dp)
                            ) {
                                Column(modifier = Modifier.padding(16.dp)) {
                                    Text("Rechnungsadresse", color = Slate400, fontSize = 11.sp, fontWeight = FontWeight.Bold, letterSpacing = 0.5.sp)
                                    Spacer(modifier = Modifier.height(8.dp))
                                    Text(
                                        text = formatAddress(order.billing_address),
                                        color = Slate100,
                                        fontSize = 13.sp,
                                        lineHeight = 18.sp,
                                        fontWeight = FontWeight.Medium
                                    )
                                }
                            }
                            Card(
                                modifier = Modifier
                                    .weight(1f)
                                    .border(1.dp, GlassWhite10, RoundedCornerShape(16.dp)),
                                colors = CardDefaults.cardColors(containerColor = Slate900),
                                shape = RoundedCornerShape(16.dp)
                            ) {
                                Column(modifier = Modifier.padding(16.dp)) {
                                    Text("Lieferadresse", color = Slate400, fontSize = 11.sp, fontWeight = FontWeight.Bold, letterSpacing = 0.5.sp)
                                    Spacer(modifier = Modifier.height(8.dp))
                                    Text(
                                        text = formatAddress(order.shipping_address),
                                        color = Slate100,
                                        fontSize = 13.sp,
                                        lineHeight = 18.sp,
                                        fontWeight = FontWeight.Medium
                                    )
                                }
                            }
                        }
                    }

                    // Items Card (With Image Preview & Styled Config)
                    item {
                        Card(
                            modifier = Modifier
                                .fillMaxWidth()
                                .border(1.dp, GlassWhite10, RoundedCornerShape(16.dp)),
                            colors = CardDefaults.cardColors(containerColor = Slate900),
                            shape = RoundedCornerShape(16.dp)
                        ) {
                            Column(modifier = Modifier.padding(16.dp)) {
                                Text("Bestellte Artikel", color = Slate400, fontSize = 11.sp, fontWeight = FontWeight.Bold, letterSpacing = 0.5.sp)
                                Spacer(modifier = Modifier.height(12.dp))
                                
                                order.items.forEachIndexed { index, item ->
                                    Column(
                                        modifier = Modifier
                                            .fillMaxWidth()
                                            .padding(vertical = 8.dp)
                                    ) {
                                        Row(
                                            modifier = Modifier.fillMaxWidth(),
                                            horizontalArrangement = Arrangement.SpaceBetween,
                                            verticalAlignment = Alignment.Top
                                        ) {
                                            Column(modifier = Modifier.weight(1f)) {
                                                Text(
                                                    text = item.product_name, 
                                                    color = Slate50, 
                                                    fontSize = 14.sp, 
                                                    fontWeight = FontWeight.Bold
                                                )
                                                // Beautiful formatted configuration details
                                                ConfigurationDetails(item.configuration)
                                            }
                                            Spacer(modifier = Modifier.width(8.dp))
                                            Column(horizontalAlignment = Alignment.End) {
                                                Text(
                                                    text = "${item.quantity}x ${formatPrice(item.unit_price)}",
                                                    color = Slate400,
                                                    fontSize = 12.sp
                                                )
                                                Text(
                                                    text = formatPrice(item.total_price),
                                                    color = Slate50,
                                                    fontSize = 14.sp,
                                                    fontWeight = FontWeight.Bold
                                                )
                                            }
                                        }

                                        // Render front and back snapshot images if available in configuration
                                        val snapshotMap = item.configuration?.get("snapshot_path") as? Map<*, *>
                                        val frontPath = snapshotMap?.get("front")?.toString()
                                        val backPath = snapshotMap?.get("back")?.toString()

                                        if (!frontPath.isNullOrEmpty() || !backPath.isNullOrEmpty()) {
                                            Row(
                                                modifier = Modifier.padding(top = 10.dp),
                                                horizontalArrangement = Arrangement.spacedBy(12.dp)
                                            ) {
                                                frontPath?.let { path ->
                                                    val url = resolveImageUrl(path)
                                                    if (url != null) {
                                                        Column(horizontalAlignment = Alignment.CenterHorizontally) {
                                                            Text("Vorderseite", color = Slate400, fontSize = 9.sp, fontWeight = FontWeight.Bold)
                                                            Spacer(modifier = Modifier.height(4.dp))
                                                            AsyncImage(
                                                                model = url,
                                                                contentDescription = "Vorderseite Vorschau",
                                                                modifier = Modifier
                                                                    .size(80.dp)
                                                                    .border(1.dp, GlassWhite10, RoundedCornerShape(8.dp))
                                                                    .background(GlassWhite10, RoundedCornerShape(8.dp))
                                                                    .clickable { zoomImageUrl = url }
                                                                    .padding(2.dp),
                                                                contentScale = ContentScale.Crop
                                                            )
                                                        }
                                                    }
                                                }
                                                backPath?.let { path ->
                                                    val url = resolveImageUrl(path)
                                                    if (url != null) {
                                                        Column(horizontalAlignment = Alignment.CenterHorizontally) {
                                                            Text("Rückseite", color = Slate400, fontSize = 9.sp, fontWeight = FontWeight.Bold)
                                                            Spacer(modifier = Modifier.height(4.dp))
                                                            AsyncImage(
                                                                model = url,
                                                                contentDescription = "Rückseite Vorschau",
                                                                modifier = Modifier
                                                                    .size(80.dp)
                                                                    .border(1.dp, GlassWhite10, RoundedCornerShape(8.dp))
                                                                    .background(GlassWhite10, RoundedCornerShape(8.dp))
                                                                    .clickable { zoomImageUrl = url }
                                                                    .padding(2.dp),
                                                                contentScale = ContentScale.Crop
                                                            )
                                                        }
                                                    }
                                                }
                                            }
                                        }

                                        // Customer Uploaded Files & Laser SVGs
                                        val config = item.configuration
                                        val uploadedFiles = config?.get("files") as? List<*>
                                        val logoStoragePath = config?.get("logo_storage_path")?.toString()
                                        
                                        if (!logoStoragePath.isNullOrEmpty() || !uploadedFiles.isNullOrEmpty()) {
                                            val context = androidx.compose.ui.platform.LocalContext.current
                                            Spacer(modifier = Modifier.height(12.dp))
                                            Text("KUNDENDATEIEN:", color = Slate400, fontSize = 10.sp, fontWeight = FontWeight.Bold, letterSpacing = 0.5.sp)
                                            Spacer(modifier = Modifier.height(6.dp))
                                            
                                            if (!logoStoragePath.isNullOrEmpty()) {
                                                Row(
                                                    modifier = Modifier
                                                        .fillMaxWidth()
                                                        .padding(vertical = 4.dp)
                                                        .background(GlassWhite10, RoundedCornerShape(8.dp))
                                                        .clickable {
                                                            val url = resolveImageUrl(logoStoragePath)
                                                            if (url != null) {
                                                                try {
                                                                    val intent = android.content.Intent(android.content.Intent.ACTION_VIEW, android.net.Uri.parse(url))
                                                                    context.startActivity(intent)
                                                                } catch (e: Exception) {}
                                                            }
                                                        }
                                                        .padding(horizontal = 12.dp, vertical = 8.dp),
                                                    horizontalArrangement = Arrangement.SpaceBetween,
                                                    verticalAlignment = Alignment.CenterVertically
                                                ) {
                                                    Row(verticalAlignment = Alignment.CenterVertically) {
                                                        Text("🖼️", fontSize = 14.sp)
                                                        Spacer(modifier = Modifier.width(8.dp))
                                                        Text("Kunden-Logo", color = Slate200, fontSize = 12.sp, fontWeight = FontWeight.Medium)
                                                    }
                                                    Text("Öffnen", color = Gold, fontSize = 11.sp, fontWeight = FontWeight.Bold)
                                                }
                                            }
                                            
                                            uploadedFiles?.forEach { fileVal ->
                                                val filePath = fileVal?.toString()
                                                if (!filePath.isNullOrEmpty()) {
                                                     val fileName = filePath.substringAfterLast('/')
                                                     Row(
                                                         modifier = Modifier
                                                             .fillMaxWidth()
                                                             .padding(vertical = 4.dp)
                                                             .background(GlassWhite10, RoundedCornerShape(8.dp))
                                                             .clickable {
                                                                 val url = resolveImageUrl(filePath)
                                                                 if (url != null) {
                                                                     try {
                                                                         val intent = android.content.Intent(android.content.Intent.ACTION_VIEW, android.net.Uri.parse(url))
                                                                         context.startActivity(intent)
                                                                     } catch (e: Exception) {}
                                                                 }
                                                             }
                                                             .padding(horizontal = 12.dp, vertical = 8.dp),
                                                         horizontalArrangement = Arrangement.SpaceBetween,
                                                         verticalAlignment = Alignment.CenterVertically
                                                     ) {
                                                         Row(verticalAlignment = Alignment.CenterVertically) {
                                                             Text("📄", fontSize = 14.sp)
                                                             Spacer(modifier = Modifier.width(8.dp))
                                                             Text(fileName, color = Slate200, fontSize = 12.sp, fontWeight = FontWeight.Medium, maxLines = 1)
                                                         }
                                                         Text("Öffnen", color = Gold, fontSize = 11.sp, fontWeight = FontWeight.Bold)
                                                     }
                                                }
                                            }
                                        }

                                        // Laser SVGs
                                        val texts = config?.get("texts") as? List<*>
                                        val logos = config?.get("logos") as? List<*>
                                        val textsBack = config?.get("texts_back") as? List<*>
                                        val logosBack = config?.get("logos_back") as? List<*>

                                        val hasFrontLaser = !texts.isNullOrEmpty() || !logos.isNullOrEmpty()
                                        val hasBackLaser = !textsBack.isNullOrEmpty() || !logosBack.isNullOrEmpty()

                                        if (hasFrontLaser || hasBackLaser) {
                                            val context = androidx.compose.ui.platform.LocalContext.current
                                            val token = de.meinseelenfunke.app.di.ServiceLocator.getAuthToken() ?: ""
                                            val baseUrl = de.meinseelenfunke.app.di.ServiceLocator.getBaseUrl()
                                            
                                            Spacer(modifier = Modifier.height(10.dp))
                                            Row(
                                                modifier = Modifier.fillMaxWidth(),
                                                horizontalArrangement = Arrangement.spacedBy(8.dp)
                                            ) {
                                                if (hasFrontLaser) {
                                                    Button(
                                                        onClick = {
                                                            val downloadUrl = "${baseUrl}funki/shop/order-items/${item.id}/laser-file/front?token=$token"
                                                            try {
                                                                val intent = android.content.Intent(android.content.Intent.ACTION_VIEW, android.net.Uri.parse(downloadUrl))
                                                                context.startActivity(intent)
                                                            } catch (e: Exception) {}
                                                        },
                                                        modifier = Modifier.weight(1f),
                                                        colors = ButtonDefaults.buttonColors(containerColor = GlassWhite10),
                                                        shape = RoundedCornerShape(8.dp),
                                                        contentPadding = PaddingValues(vertical = 8.dp)
                                                    ) {
                                                        Text("⚡ Vorderseite SVG", color = Gold, fontSize = 11.sp, fontWeight = FontWeight.Bold)
                                                    }
                                                }
                                                
                                                if (hasBackLaser) {
                                                    Button(
                                                        onClick = {
                                                            val downloadUrl = "${baseUrl}funki/shop/order-items/${item.id}/laser-file/back?token=$token"
                                                            try {
                                                                val intent = android.content.Intent(android.content.Intent.ACTION_VIEW, android.net.Uri.parse(downloadUrl))
                                                                context.startActivity(intent)
                                                            } catch (e: Exception) {}
                                                        },
                                                        modifier = Modifier.weight(1f),
                                                        colors = ButtonDefaults.buttonColors(containerColor = GlassWhite10),
                                                        shape = RoundedCornerShape(8.dp),
                                                        contentPadding = PaddingValues(vertical = 8.dp)
                                                    ) {
                                                        Text("⚡ Rückseite SVG", color = Gold, fontSize = 11.sp, fontWeight = FontWeight.Bold)
                                                    }
                                                }
                                            }
                                        }
                                    }
                                    if (index < order.items.size - 1) {
                                        Divider(color = GlassWhite10, modifier = Modifier.padding(vertical = 8.dp))
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
                                .border(1.dp, GlassWhite10, RoundedCornerShape(16.dp)),
                            colors = CardDefaults.cardColors(containerColor = Slate900),
                            shape = RoundedCornerShape(16.dp)
                        ) {
                            Column(modifier = Modifier.padding(16.dp)) {
                                Text("Übersicht", color = Slate400, fontSize = 11.sp, fontWeight = FontWeight.Bold, letterSpacing = 0.5.sp)
                                Spacer(modifier = Modifier.height(12.dp))
                                
                                val prices = mutableListOf<Pair<String, Long>>()
                                order.subtotal_price?.let { prices.add("Zwischensumme" to it) }
                                order.discount_amount?.let { if (it > 0) prices.add("Rabatt" to -it) }
                                order.volume_discount?.let { if (it > 0) prices.add("Mengenrabatt" to -it) }
                                order.shipping_price?.let { prices.add("Versandkosten" to it) }
                                order.express_price?.let { if (it > 0) prices.add("Express-Zuschlag" to it) }

                                prices.forEach { (label, value) ->
                                    Row(
                                        modifier = Modifier
                                            .fillMaxWidth()
                                            .padding(vertical = 4.dp),
                                        horizontalArrangement = Arrangement.SpaceBetween
                                    ) {
                                        Text(label, color = Slate400, fontSize = 13.sp)
                                        Text(formatPrice(value), color = Slate200, fontSize = 13.sp, fontWeight = FontWeight.Medium)
                                    }
                                }

                                Divider(color = GlassWhite10, modifier = Modifier.padding(vertical = 10.dp))
                                
                                Row(
                                    modifier = Modifier.fillMaxWidth(),
                                    horizontalArrangement = Arrangement.SpaceBetween,
                                    verticalAlignment = Alignment.CenterVertically
                                ) {
                                    Text("Gesamtsumme", color = Slate50, fontWeight = FontWeight.Bold, fontSize = 16.sp)
                                    Text(formatPrice(order.total_price), color = Gold, fontWeight = FontWeight.Bold, fontSize = 18.sp)
                                }
                            }
                        }
                    }

                    // Customer Notes
                    if (!order.notes.isNullOrEmpty()) {
                        item {
                            Card(
                                modifier = Modifier
                                    .fillMaxWidth()
                                    .border(1.dp, Color(0x33C5A059), RoundedCornerShape(16.dp)),
                                colors = CardDefaults.cardColors(containerColor = Slate900),
                                shape = RoundedCornerShape(16.dp)
                            ) {
                                Column(modifier = Modifier.padding(16.dp)) {
                                    Row(verticalAlignment = Alignment.CenterVertically) {
                                        Surface(
                                            color = Color(0x11C5A059),
                                            shape = RoundedCornerShape(6.dp),
                                            modifier = Modifier.size(24.dp),
                                            contentColor = Gold
                                        ) {
                                            Box(contentAlignment = Alignment.Center) {
                                                Text("!", fontWeight = FontWeight.Bold, fontSize = 12.sp)
                                            }
                                        }
                                        Spacer(modifier = Modifier.width(8.dp))
                                        Text("Bemerkung des Kunden", color = Gold, fontSize = 12.sp, fontWeight = FontWeight.Bold)
                                    }
                                    Spacer(modifier = Modifier.height(8.dp))
                                    Text(
                                        text = order.notes, 
                                        color = Slate100, 
                                        fontSize = 13.sp,
                                        lineHeight = 18.sp,
                                        fontWeight = FontWeight.Medium
                                    )
                                }
                            }
                        }
                    }
                }
            }
        }
    }

    // Image Zoom Modal Dialog
    if (zoomImageUrl != null) {
        Dialog(
            onDismissRequest = { zoomImageUrl = null },
            properties = DialogProperties(usePlatformDefaultWidth = false)
        ) {
            Surface(
                modifier = Modifier
                    .fillMaxSize()
                    .clickable { zoomImageUrl = null },
                color = Color.Black.copy(alpha = 0.9f)
            ) {
                Box(
                    modifier = Modifier.fillMaxSize(),
                    contentAlignment = Alignment.Center
                ) {
                    AsyncImage(
                        model = zoomImageUrl,
                        contentDescription = "Zoomierte Ansicht",
                        modifier = Modifier
                            .fillMaxWidth(0.95f)
                            .fillMaxHeight(0.85f),
                        contentScale = ContentScale.Fit
                    )
                    
                    IconButton(
                        onClick = { zoomImageUrl = null },
                        modifier = Modifier
                            .align(Alignment.TopEnd)
                            .padding(24.dp)
                            .background(Color.Black.copy(alpha = 0.5f), RoundedCornerShape(12.dp))
                            .size(44.dp)
                    ) {
                        Icon(
                            imageVector = Icons.Default.Close,
                            contentDescription = "Schließen",
                            tint = Color.White
                        )
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
        // Date parsing support for ISO 8601 strings
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
    val firstName = (address["first_name"]?.toString() ?: "").trim()
    val lastName = (address["last_name"]?.toString() ?: "").trim()
    val fullName = "$firstName $lastName".trim()
    val street = address["street"]?.toString() ?: ""
    val houseNumber = address["house_number"]?.toString() ?: address["street_number"]?.toString() ?: ""
    val postcode = address["postcode"]?.toString() ?: address["zip"]?.toString() ?: ""
    val city = address["city"]?.toString() ?: ""
    val country = address["country"]?.toString() ?: ""

    val sb = StringBuilder()
    if (!company.isNullOrEmpty() && !company.equals(fullName, ignoreCase = true)) {
        sb.append(company).append("\n")
    }
    if (fullName.isNotEmpty()) {
        sb.append(fullName).append("\n")
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

@Composable
fun ConfigurationDetails(configuration: Map<String, Any>?) {
    if (configuration == null || configuration.isEmpty()) return

    Column(
        modifier = Modifier
            .fillMaxWidth()
            .padding(top = 4.dp),
        verticalArrangement = Arrangement.spacedBy(4.dp)
    ) {
        // 1. Text (legacy or top-level text)
        val topText = configuration["text"]?.toString()?.trim()
        if (!topText.isNullOrEmpty()) {
            TextDetailRow(label = "Text", value = topText)
        }

        // 2. Texts (front text items)
        val texts = configuration["texts"] as? List<*>
        if (texts != null && texts.isNotEmpty()) {
            texts.forEachIndexed { i, textItem ->
                val itemMap = textItem as? Map<*, *>
                if (itemMap != null) {
                    val txt = itemMap["text"]?.toString()?.trim() ?: ""
                    val font = itemMap["font"]?.toString()?.trim() ?: ""
                    if (txt.isNotEmpty()) {
                        val label = if (texts.size > 1) "Text ${i + 1}" else "Text (Vorderseite)"
                        val value = if (font.isNotEmpty()) "$txt ($font)" else txt
                        TextDetailRow(label = label, value = value)
                    }
                }
            }
        }

        // 3. Texts Back (back text items)
        val textsBack = configuration["texts_back"] as? List<*>
        if (textsBack != null && textsBack.isNotEmpty()) {
            textsBack.forEachIndexed { i, textItem ->
                val itemMap = textItem as? Map<*, *>
                if (itemMap != null) {
                    val txt = itemMap["text"]?.toString()?.trim() ?: ""
                    val font = itemMap["font"]?.toString()?.trim() ?: ""
                    if (txt.isNotEmpty()) {
                        val label = if (textsBack.size > 1) "Text Hinten ${i + 1}" else "Text (Rückseite)"
                        val value = if (font.isNotEmpty()) "$txt ($font)" else txt
                        TextDetailRow(label = label, value = value)
                    }
                }
            }
        }
    }
}

@Composable
fun TextDetailRow(label: String, value: String) {
    Row(
        modifier = Modifier.fillMaxWidth(),
        verticalAlignment = Alignment.Top
    ) {
        Text(
            text = "$label: ",
            color = Slate400,
            fontSize = 12.sp,
            fontWeight = FontWeight.Bold,
            modifier = Modifier.widthIn(min = 120.dp)
        )
        Text(
            text = value,
            color = Slate300,
            fontSize = 12.sp,
            fontWeight = FontWeight.Normal
        )
    }
}

fun resolveImageUrl(path: String): String? {
    if (path.isEmpty()) return null
    if (path.startsWith("http://") || path.startsWith("https://")) {
        return path
    }
    val baseUrl = de.meinseelenfunke.app.di.ServiceLocator.getBaseUrl()
    val cleanBaseUrl = if (baseUrl.endsWith("/api/")) {
        baseUrl.substringBeforeLast("/api/")
    } else if (baseUrl.endsWith("/api")) {
        baseUrl.substringBeforeLast("/api")
    } else {
        baseUrl.removeSuffix("/")
    }
    val cleanPath = path.removePrefix("/")
    return "$cleanBaseUrl/storage/$cleanPath"
}

@OptIn(ExperimentalMaterial3Api::class)
@Composable
fun DhlCreateDialog(
    onDismiss: () -> Unit,
    onCreate: (Int, Double) -> Unit
) {
    var packageCountText by remember { mutableStateOf("1") }
    var weightText by remember { mutableStateOf("1.0") }
    var errorMsg by remember { mutableStateOf<String?>(null) }

    Dialog(onDismissRequest = onDismiss) {
        Surface(
            modifier = Modifier
                .width(320.dp)
                .wrapContentHeight()
                .border(1.dp, GlassWhite10, RoundedCornerShape(16.dp)),
            shape = RoundedCornerShape(16.dp),
            color = Slate900
        ) {
            Column(
                modifier = Modifier
                    .padding(20.dp)
                    .fillMaxWidth(),
                horizontalAlignment = Alignment.CenterHorizontally
            ) {
                Text(
                    text = "DHL Label erstellen",
                    color = Gold,
                    fontWeight = FontWeight.Bold,
                    fontSize = 18.sp
                )
                Spacer(modifier = Modifier.height(16.dp))

                OutlinedTextField(
                    value = packageCountText,
                    onValueChange = { packageCountText = it },
                    label = { Text("Anzahl Pakete", color = Slate400) },
                    colors = TextFieldDefaults.outlinedTextFieldColors(
                        focusedBorderColor = Gold,
                        unfocusedBorderColor = GlassWhite10,
                        focusedLabelColor = Gold,
                        unfocusedLabelColor = Slate400,
                        cursorColor = Gold
                    ),
                    modifier = Modifier.fillMaxWidth(),
                    singleLine = true
                )
                Spacer(modifier = Modifier.height(12.dp))

                OutlinedTextField(
                    value = weightText,
                    onValueChange = { weightText = it },
                    label = { Text("Gewicht pro Paket (kg)", color = Slate400) },
                    colors = TextFieldDefaults.outlinedTextFieldColors(
                        focusedBorderColor = Gold,
                        unfocusedBorderColor = GlassWhite10,
                        focusedLabelColor = Gold,
                        unfocusedLabelColor = Slate400,
                        cursorColor = Gold
                    ),
                    modifier = Modifier.fillMaxWidth(),
                    singleLine = true
                )
                
                errorMsg?.let { err ->
                    Spacer(modifier = Modifier.height(8.dp))
                    Text(err, color = Color.Red, fontSize = 12.sp, fontWeight = FontWeight.Bold)
                }

                Spacer(modifier = Modifier.height(20.dp))

                Row(
                    modifier = Modifier.fillMaxWidth(),
                    horizontalArrangement = Arrangement.End
                ) {
                    TextButton(onClick = onDismiss) {
                        Text("Abbrechen", color = Slate400)
                    }
                    Spacer(modifier = Modifier.width(8.dp))
                    Button(
                        onClick = {
                            val count = packageCountText.toIntOrNull()
                            val weight = weightText.toDoubleOrNull()
                            if (count == null || count < 1 || count > 30) {
                                errorMsg = "Paketanzahl muss zwischen 1 und 30 sein."
                                return@Button
                            }
                            if (weight == null || weight < 0.1 || weight > 31.5) {
                                errorMsg = "Gewicht muss zwischen 0.1kg und 31.5kg sein."
                                return@Button
                            }
                            onCreate(count, weight)
                        },
                        colors = ButtonDefaults.buttonColors(containerColor = Gold)
                    ) {
                        Text("Erstellen", color = SpaceBlack, fontWeight = FontWeight.Bold)
                    }
                }
            }
        }
    }
}

