package de.meinseelenfunke.app.ui.screens

import android.net.Uri
import android.content.Intent
import de.meinseelenfunke.app.di.ServiceLocator
import android.webkit.MimeTypeMap
import androidx.activity.compose.rememberLauncherForActivityResult
import androidx.activity.result.contract.ActivityResultContracts
import androidx.compose.foundation.background
import androidx.compose.foundation.border
import androidx.compose.foundation.clickable
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.Spacer
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.heightIn
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.layout.width
import androidx.compose.animation.AnimatedVisibility
import androidx.compose.foundation.BorderStroke
import androidx.compose.ui.draw.scale
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.foundation.text.KeyboardOptions
import androidx.compose.foundation.verticalScroll
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.Add
import androidx.compose.material.icons.filled.AttachFile
import androidx.compose.material.icons.filled.BusinessCenter
import androidx.compose.material.icons.filled.Delete
import androidx.compose.material.icons.filled.Description
import androidx.compose.material.icons.filled.Info
import androidx.compose.material.icons.filled.Map
import androidx.compose.material.icons.filled.DateRange
import androidx.compose.material.icons.filled.PhotoCamera
import androidx.compose.material.icons.filled.Edit
import androidx.compose.material.icons.filled.Check
import androidx.compose.material.icons.filled.Close
import androidx.compose.material.icons.filled.ExpandMore
import androidx.compose.material.icons.filled.ExpandLess
import androidx.compose.material.icons.filled.PieChart
import androidx.compose.foundation.Canvas
import androidx.compose.animation.core.Animatable
import androidx.compose.animation.core.FastOutSlowInEasing
import androidx.compose.animation.core.tween
import androidx.compose.ui.graphics.drawscope.Stroke
import androidx.compose.ui.graphics.StrokeCap
import androidx.compose.ui.text.style.TextOverflow
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.ui.draw.clip
import androidx.compose.foundation.horizontalScroll
import androidx.compose.material3.AlertDialog
import androidx.compose.material3.Button
import androidx.compose.material3.ButtonDefaults
import androidx.compose.material3.Card
import androidx.compose.material3.CardDefaults
import androidx.compose.material3.CircularProgressIndicator
import androidx.compose.material3.DropdownMenuItem
import androidx.compose.material3.ExperimentalMaterial3Api
import androidx.compose.material3.ExposedDropdownMenuBox
import androidx.compose.material3.ExposedDropdownMenuDefaults
import androidx.compose.material3.Icon
import androidx.compose.material3.IconButton
import androidx.compose.material3.OutlinedTextField
import androidx.compose.material3.OutlinedTextFieldDefaults
import androidx.compose.material3.SnackbarHost
import androidx.compose.material3.SnackbarHostState
import androidx.compose.material3.Switch
import androidx.compose.material3.SwitchDefaults
import androidx.compose.material3.Tab
import androidx.compose.material3.TabRow
import androidx.compose.material3.TabRowDefaults
import androidx.compose.material3.TabRowDefaults.tabIndicatorOffset
import androidx.compose.material3.Text
import androidx.compose.material3.TextButton
import androidx.compose.runtime.Composable
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.collectAsState
import androidx.compose.runtime.getValue
import androidx.compose.foundation.ExperimentalFoundationApi
import androidx.compose.foundation.pager.HorizontalPager
import androidx.compose.foundation.pager.rememberPagerState
import androidx.compose.runtime.mutableIntStateOf
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.rememberCoroutineScope
import androidx.compose.runtime.setValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.graphics.Brush
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.input.KeyboardType
import androidx.compose.ui.text.style.TextAlign
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import androidx.lifecycle.viewmodel.compose.viewModel
import de.meinseelenfunke.app.data.api.FinanceGroup
import de.meinseelenfunke.app.data.api.FinanceSpecialIssue
import de.meinseelenfunke.app.data.api.ReceiptAiScanData
import de.meinseelenfunke.app.ui.theme.AppTheme
import de.meinseelenfunke.app.ui.theme.Gold
import de.meinseelenfunke.app.ui.theme.Copper
import de.meinseelenfunke.app.ui.theme.SpaceBlue
import de.meinseelenfunke.app.ui.theme.SpaceBlack
import de.meinseelenfunke.app.ui.theme.Rose500
import de.meinseelenfunke.app.ui.theme.Emerald500
import kotlinx.coroutines.launch
import java.util.Locale

@OptIn(ExperimentalFoundationApi::class)
@Composable
fun FinanceScreen(
    isPageVisible: Boolean = true,
    viewModel: FinanceViewModel = viewModel()
) {
    val uiState by viewModel.uiState.collectAsState()
    val kpis by viewModel.kpis.collectAsState()
    val variableItems by viewModel.variableItems.collectAsState()
    val fixedGroups by viewModel.fixedGroups.collectAsState()
    val categories by viewModel.categories.collectAsState()
    val isUploading by viewModel.isUploading.collectAsState()
    val isAiScanning by viewModel.isAiScanning.collectAsState()
    val actionResult by viewModel.actionResult.collectAsState()

    val context = LocalContext.current
    val scope = rememberCoroutineScope()
    val snackbarHostState = remember { SnackbarHostState() }

    val pagerState = rememberPagerState(
        initialPage = 0,
        pageCount = { 2 }
    )
    var showQuickEntryDialog by remember { mutableStateOf(false) }

    LaunchedEffect(isPageVisible) {
        if (isPageVisible) {
            viewModel.loadAllFinanceData(showLoading = false)
        }
    }

    LaunchedEffect(actionResult) {
        actionResult?.let { result ->
            result.onSuccess { msg ->
                scope.launch { snackbarHostState.showSnackbar(msg) }
                viewModel.clearActionResult()
            }.onFailure { err ->
                scope.launch { snackbarHostState.showSnackbar(err.localizedMessage ?: "Fehler") }
                viewModel.clearActionResult()
            }
        }
    }

    Box(
        modifier = Modifier
            .fillMaxSize()
            .background(
                Brush.verticalGradient(
                    colors = AppTheme.colors.bgGradient
                )
            )
    ) {
        Column(modifier = Modifier.fillMaxSize()) {
            // Header
            Row(
                modifier = Modifier
                    .fillMaxWidth()
                    .padding(horizontal = 16.dp, vertical = 20.dp),
                horizontalArrangement = Arrangement.SpaceBetween,
                verticalAlignment = Alignment.CenterVertically
            ) {
                Column {
                    Text(
                        text = "FINANZENTRUM",
                        fontSize = 22.sp,
                        fontWeight = FontWeight.Bold,
                        color = Gold,
                        letterSpacing = 2.sp
                    )
                    Text(
                        text = "Verwalte deine Buchungen & Verträge",
                        fontSize = 12.sp,
                        color = AppTheme.colors.textMuted
                    )
                }

                Button(
                    onClick = { showQuickEntryDialog = true },
                    colors = ButtonDefaults.buttonColors(containerColor = Gold, contentColor = SpaceBlack),
                    contentPadding = ButtonDefaults.ButtonWithIconContentPadding,
                    shape = RoundedCornerShape(8.dp)
                ) {
                    Icon(imageVector = Icons.Default.Add, contentDescription = null, modifier = Modifier.size(16.dp))
                    Spacer(modifier = Modifier.width(4.dp))
                    Text("Eintrag", fontWeight = FontWeight.Bold)
                }
            }

            // Top balance indicators
            kpis?.let {
                Row(
                    modifier = Modifier
                        .fillMaxWidth()
                        .padding(horizontal = 16.dp, vertical = 4.dp),
                    horizontalArrangement = Arrangement.spacedBy(12.dp)
                ) {
                    Card(
                        modifier = Modifier
                            .weight(1f)
                            .border(1.dp, AppTheme.colors.cardBorder, RoundedCornerShape(14.dp)),
                        colors = CardDefaults.cardColors(containerColor = AppTheme.colors.cardBackground)
                    ) {
                        Column(modifier = Modifier.padding(12.dp)) {
                            Text("Guthaben", fontSize = 11.sp, color = AppTheme.colors.textMuted)
                            Text(
                                String.format(Locale.GERMANY, "%,.2f €", it.available),
                                fontSize = 18.sp,
                                fontWeight = FontWeight.Bold,
                                color = Gold
                            )
                        }
                    }
                    Card(
                        modifier = Modifier
                            .weight(1f)
                            .border(1.dp, AppTheme.colors.cardBorder, RoundedCornerShape(14.dp)),
                        colors = CardDefaults.cardColors(containerColor = AppTheme.colors.cardBackground)
                    ) {
                        Column(modifier = Modifier.padding(12.dp)) {
                            Text("Variable Ausgaben", fontSize = 11.sp, color = AppTheme.colors.textMuted)
                            Text(
                                String.format(Locale.GERMANY, "%,.2f €", it.special_expenses),
                                fontSize = 18.sp,
                                fontWeight = FontWeight.Bold,
                                color = SpaceBlue
                            )
                        }
                    }
                }
            }

            AnimatedVisibility(visible = showQuickEntryDialog) {
                QuickEntryForm(
                    categories = categories,
                    isUploading = isUploading,
                    isAiScanning = isAiScanning,
                    onDismiss = { showQuickEntryDialog = false },
                    onScanReceipt = { bytes, fileName, mime, autoSave, onResult ->
                        viewModel.scanReceipt(bytes, fileName, mime, autoSave) { scanData ->
                            scanData?.let { onResult(it) }
                            if (autoSave) {
                                showQuickEntryDialog = false
                            }
                        }
                    },
                    onSubmit = { title, amt, cat, isBiz, date, loc, bytes, name, mime ->
                        viewModel.submitQuickEntry(title, amt, cat, isBiz, date, loc, bytes, name, mime)
                        showQuickEntryDialog = false
                    }
                )
            }

            Spacer(modifier = Modifier.height(12.dp))

            // Navigation tabs
            TabRow(
                selectedTabIndex = pagerState.currentPage,
                containerColor = Color.Transparent,
                contentColor = AppTheme.colors.textPrimary,
                indicator = { tabPositions ->
                    TabRowDefaults.SecondaryIndicator(
                        Modifier.tabIndicatorOffset(tabPositions[pagerState.currentPage]),
                        color = Gold
                    )
                }
            ) {
                Tab(
                    selected = pagerState.currentPage == 0,
                    onClick = {
                        scope.launch {
                            pagerState.animateScrollToPage(0)
                        }
                    },
                    text = { 
                        Text(
                            text = "Variable Ausgaben",
                            fontWeight = FontWeight.SemiBold,
                            color = if (pagerState.currentPage == 0) Gold else AppTheme.colors.textMuted
                        ) 
                    }
                )
                Tab(
                    selected = pagerState.currentPage == 1,
                    onClick = {
                        scope.launch {
                            pagerState.animateScrollToPage(1)
                        }
                    },
                    text = { 
                        Text(
                            text = "Fixkosten",
                            fontWeight = FontWeight.SemiBold,
                            color = if (pagerState.currentPage == 1) Gold else AppTheme.colors.textMuted
                        ) 
                    }
                )
            }

            // Content depending on state
            when (uiState) {
                is FinanceUiState.Loading -> {
                    Box(modifier = Modifier.fillMaxSize(), contentAlignment = Alignment.Center) {
                        CircularProgressIndicator(color = Gold)
                    }
                }
                is FinanceUiState.Success -> {
                    HorizontalPager(
                        state = pagerState,
                        modifier = Modifier.weight(1f)
                    ) { page ->
                        if (page == 0) {
                            VariableExpensesList(
                                items = variableItems,
                                categories = categories,
                                onDelete = { viewModel.deleteVariableEntry(it) },
                                onUpdate = { id, title, amount, category, date, isBiz, loc ->
                                    viewModel.updateVariableEntry(id, title, amount, category, date, isBiz, loc)
                                }
                            )
                        } else {
                            FixedExpensesList(
                                groups = fixedGroups,
                                onAddFixedItem = { groupId, name, amount, interval, date, desc, isBiz ->
                                    viewModel.createFixedEntry(groupId, name, amount, interval, date, desc, isBiz)
                                },
                                onUpdateFixedItem = { id, groupId, name, amount, interval, date, desc, isBiz ->
                                    viewModel.updateFixedEntry(id, groupId, name, amount, interval, date, desc, isBiz)
                                },
                                onDeleteFixedItem = { id ->
                                    viewModel.deleteFixedEntry(id)
                                }
                            )
                        }
                    }
                }
                is FinanceUiState.Error -> {
                    Box(modifier = Modifier.fillMaxSize(), contentAlignment = Alignment.Center) {
                        Text(
                            text = (uiState as FinanceUiState.Error).message,
                            color = Color.Red,
                            textAlign = TextAlign.Center,
                            modifier = Modifier.padding(24.dp)
                        )
                    }
                }
            }
        }

        SnackbarHost(
            hostState = snackbarHostState,
            modifier = Modifier.align(Alignment.BottomCenter)
        )
    }


}

@OptIn(ExperimentalMaterial3Api::class)
@Composable
fun VariableExpensesList(
    items: List<FinanceSpecialIssue>,
    categories: List<String>,
    onDelete: (String) -> Unit,
    onUpdate: (id: String, title: String, amount: Double, category: String, executionDate: String, isBusiness: Boolean, location: String?) -> Unit
) {
    var editingItemId by remember { mutableStateOf<String?>(null) }

    // States for editing fields
    var editTitle by remember { mutableStateOf("") }
    var editAmountText by remember { mutableStateOf("") }
    var editCategory by remember { mutableStateOf("") }
    var editExecutionDate by remember { mutableStateOf("") }
    var editIsBusiness by remember { mutableStateOf(false) }
    var editLocation by remember { mutableStateOf("") }

    val context = LocalContext.current

    if (items.isEmpty()) {
        Box(
            modifier = Modifier
                .fillMaxSize()
                .padding(24.dp),
            contentAlignment = Alignment.Center
        ) {
            Text("Keine variablen Ausgaben eingetragen.", color = AppTheme.colors.textMuted, textAlign = TextAlign.Center)
        }
    } else {
        Column(
            modifier = Modifier
                .fillMaxSize()
                .verticalScroll(rememberScrollState())
                .padding(16.dp),
            verticalArrangement = Arrangement.spacedBy(10.dp)
        ) {
            items.forEach { item ->
                val isEditing = editingItemId == item.id

                Card(
                    modifier = Modifier
                        .fillMaxWidth()
                        .border(1.dp, AppTheme.colors.cardBorder, RoundedCornerShape(14.dp)),
                    colors = CardDefaults.cardColors(containerColor = AppTheme.colors.cardBackground),
                    shape = RoundedCornerShape(14.dp)
                ) {
                    if (isEditing) {
                        Column(modifier = Modifier.padding(16.dp), verticalArrangement = Arrangement.spacedBy(12.dp)) {
                            Text("Eintrag bearbeiten", color = Gold, fontSize = 12.sp, fontWeight = FontWeight.Bold)

                            OutlinedTextField(
                                value = editTitle,
                                onValueChange = { editTitle = it },
                                label = { Text("Titel") },
                                modifier = Modifier.fillMaxWidth(),
                                colors = OutlinedTextFieldDefaults.colors(
                                    focusedBorderColor = Gold,
                                    unfocusedBorderColor = AppTheme.colors.inputBorder,
                                    focusedTextColor = AppTheme.colors.textPrimary,
                                    unfocusedTextColor = AppTheme.colors.textPrimary
                                )
                            )

                            OutlinedTextField(
                                value = editAmountText,
                                onValueChange = { editAmountText = it },
                                label = { Text("Betrag (€)") },
                                modifier = Modifier.fillMaxWidth(),
                                keyboardOptions = KeyboardOptions(keyboardType = KeyboardType.Number),
                                colors = OutlinedTextFieldDefaults.colors(
                                    focusedBorderColor = Gold,
                                    unfocusedBorderColor = AppTheme.colors.inputBorder,
                                    focusedTextColor = AppTheme.colors.textPrimary,
                                    unfocusedTextColor = AppTheme.colors.textPrimary
                                )
                            )

                            // Date picker Trigger
                            val datePickerDialog = remember {
                                val cal = java.util.Calendar.getInstance()
                                if (editExecutionDate.contains("-")) {
                                    try {
                                        val parts = editExecutionDate.split("-")
                                        cal.set(parts[0].toInt(), parts[1].toInt() - 1, parts[2].toInt())
                                    } catch (e: Exception) {}
                                }
                                android.app.DatePickerDialog(
                                    context,
                                    { _, year, month, dayOfMonth ->
                                        val formattedMonth = String.format("%02d", month + 1)
                                        val formattedDay = String.format("%02d", dayOfMonth)
                                        editExecutionDate = "$year-$formattedMonth-$formattedDay"
                                    },
                                    cal.get(java.util.Calendar.YEAR),
                                    cal.get(java.util.Calendar.MONTH),
                                    cal.get(java.util.Calendar.DAY_OF_MONTH)
                                )
                            }

                            Box(
                                modifier = Modifier
                                    .fillMaxWidth()
                                    .clickable { datePickerDialog.show() }
                            ) {
                                OutlinedTextField(
                                    readOnly = true,
                                    value = editExecutionDate,
                                    onValueChange = {},
                                    label = { Text("Datum") },
                                    trailingIcon = {
                                        Icon(
                                            imageVector = Icons.Default.DateRange,
                                            contentDescription = "Datum wählen",
                                            tint = Gold
                                        )
                                    },
                                    modifier = Modifier.fillMaxWidth(),
                                    colors = OutlinedTextFieldDefaults.colors(
                                        focusedBorderColor = Gold,
                                        unfocusedBorderColor = AppTheme.colors.inputBorder,
                                        focusedTextColor = AppTheme.colors.textPrimary,
                                        unfocusedTextColor = AppTheme.colors.textPrimary,
                                        disabledBorderColor = AppTheme.colors.inputBorder,
                                        disabledLabelColor = AppTheme.colors.textMuted,
                                        disabledTextColor = AppTheme.colors.textPrimary
                                    ),
                                    enabled = false
                                )
                                Box(
                                    modifier = Modifier
                                        .matchParentSize()
                                        .background(Color.Transparent)
                                        .clickable { datePickerDialog.show() }
                                )
                            }

                            // Category selector dropdown
                            var editDropdownExpanded by remember { mutableStateOf(false) }
                            val filteredCats = remember(editCategory) {
                                categories.filter { it.contains(editCategory, ignoreCase = true) }
                            }
                            ExposedDropdownMenuBox(
                                expanded = editDropdownExpanded && filteredCats.isNotEmpty(),
                                onExpandedChange = { editDropdownExpanded = it }
                            ) {
                                OutlinedTextField(
                                    value = editCategory,
                                    onValueChange = {
                                        editCategory = it
                                        editDropdownExpanded = true
                                    },
                                    label = { Text("Kategorie") },
                                    trailingIcon = { ExposedDropdownMenuDefaults.TrailingIcon(expanded = editDropdownExpanded) },
                                    colors = OutlinedTextFieldDefaults.colors(
                                        focusedBorderColor = Gold,
                                        unfocusedBorderColor = AppTheme.colors.inputBorder,
                                        focusedTextColor = AppTheme.colors.textPrimary,
                                        unfocusedTextColor = AppTheme.colors.textPrimary
                                    ),
                                    modifier = Modifier
                                        .fillMaxWidth()
                                        .menuAnchor()
                                )
                                ExposedDropdownMenu(
                                    expanded = editDropdownExpanded && filteredCats.isNotEmpty(),
                                    onDismissRequest = { editDropdownExpanded = false }
                                ) {
                                    filteredCats.forEach { selection ->
                                        val isSelected = editCategory == selection
                                        DropdownMenuItem(
                                            text = {
                                                Text(
                                                    text = selection,
                                                    color = if (isSelected) SpaceBlack else AppTheme.colors.textPrimary,
                                                    fontWeight = if (isSelected) FontWeight.Bold else FontWeight.Normal
                                                )
                                            },
                                            onClick = {
                                                editCategory = selection
                                                editDropdownExpanded = false
                                            },
                                            modifier = Modifier
                                                .fillMaxWidth()
                                                .background(if (isSelected) Gold else Color.Transparent)
                                        )
                                    }
                                }
                            }

                            OutlinedTextField(
                                value = editLocation,
                                onValueChange = { editLocation = it },
                                label = { Text("Ort / Geschäft (optional)") },
                                modifier = Modifier.fillMaxWidth(),
                                colors = OutlinedTextFieldDefaults.colors(
                                    focusedBorderColor = Gold,
                                    unfocusedBorderColor = AppTheme.colors.inputBorder,
                                    focusedTextColor = AppTheme.colors.textPrimary,
                                    unfocusedTextColor = AppTheme.colors.textPrimary
                                )
                            )

                            Row(
                                modifier = Modifier.fillMaxWidth(),
                                horizontalArrangement = Arrangement.SpaceBetween,
                                verticalAlignment = Alignment.CenterVertically
                            ) {
                                Text("Gewerbliche Ausgabe?", color = AppTheme.colors.textPrimary, fontSize = 14.sp)
                                Switch(
                                    checked = editIsBusiness,
                                    onCheckedChange = { editIsBusiness = it },
                                    colors = SwitchDefaults.colors(
                                        checkedThumbColor = Gold,
                                        checkedTrackColor = Color(0x33C5A059)
                                    )
                                )
                            }

                            Row(
                                modifier = Modifier.fillMaxWidth(),
                                horizontalArrangement = Arrangement.End,
                                verticalAlignment = Alignment.CenterVertically
                            ) {
                                TextButton(onClick = { editingItemId = null }) {
                                    Text("Abbrechen", color = AppTheme.colors.textMuted)
                                }
                                Spacer(modifier = Modifier.width(8.dp))
                                Button(
                                    onClick = {
                                        val amt = editAmountText.replace(',', '.').toDoubleOrNull() ?: 0.0
                                        onUpdate(item.id, editTitle, amt, editCategory, editExecutionDate, editIsBusiness, editLocation.ifBlank { null })
                                        editingItemId = null
                                    },
                                    enabled = editTitle.isNotBlank() && editAmountText.replace(',', '.').toDoubleOrNull() != null,
                                    colors = ButtonDefaults.buttonColors(containerColor = Gold, contentColor = SpaceBlack)
                                ) {
                                    Text("Speichern")
                                }
                            }
                        }
                    } else {
                        Row(
                            modifier = Modifier.padding(16.dp),
                            verticalAlignment = Alignment.CenterVertically,
                            horizontalArrangement = Arrangement.SpaceBetween
                        ) {
                            Column(modifier = Modifier.weight(1f)) {
                                Row(verticalAlignment = Alignment.CenterVertically) {
                                    Text(
                                        text = item.title,
                                        fontSize = 15.sp,
                                        fontWeight = FontWeight.Bold,
                                        color = AppTheme.colors.textPrimary
                                    )
                                    Spacer(modifier = Modifier.width(6.dp))
                                    if (item.is_business) {
                                        Icon(
                                            imageVector = Icons.Default.BusinessCenter,
                                            contentDescription = "Gewerblich",
                                            tint = Copper,
                                            modifier = Modifier.size(14.dp)
                                        )
                                    }
                                    if (!item.file_paths.isNullOrEmpty()) {
                                        Spacer(modifier = Modifier.width(6.dp))
                                        IconButton(
                                            onClick = {
                                                val path = item.file_paths.first()
                                                val token = ServiceLocator.getAuthToken() ?: ""
                                                val baseUrl = ServiceLocator.getBaseUrl()
                                                val url = "${baseUrl}funki/financials/receipt?path=${android.net.Uri.encode(path)}&token=${android.net.Uri.encode(token)}"
                                                try {
                                                    val intent = Intent(Intent.ACTION_VIEW, Uri.parse(url))
                                                    context.startActivity(intent)
                                                } catch (e: Exception) {
                                                    e.printStackTrace()
                                                }
                                            },
                                            modifier = Modifier.size(24.dp)
                                        ) {
                                            Icon(
                                                imageVector = Icons.Default.AttachFile,
                                                contentDescription = "Beleg anzeigen",
                                                tint = Gold,
                                                modifier = Modifier.size(16.dp)
                                            )
                                        }
                                    }
                                }
                                Text(
                                    text = "${(item.category ?: "").uppercase(Locale.getDefault())} • ${formatDateString(item.execution_date)}",
                                    fontSize = 11.sp,
                                    color = AppTheme.colors.textMuted,
                                    modifier = Modifier.padding(top = 2.dp)
                                )
                                if (!item.location.isNullOrBlank()) {
                                    Row(
                                        verticalAlignment = Alignment.CenterVertically,
                                        modifier = Modifier.padding(top = 4.dp)
                                    ) {
                                        Icon(
                                            imageVector = Icons.Default.Map,
                                            contentDescription = null,
                                            tint = AppTheme.colors.textMuted,
                                            modifier = Modifier.size(10.dp)
                                        )
                                        Spacer(modifier = Modifier.width(4.dp))
                                        Text(item.location, fontSize = 10.sp, color = AppTheme.colors.textMuted)
                                    }
                                }
                            }

                            Row(verticalAlignment = Alignment.CenterVertically) {
                                Text(
                                    text = String.format(Locale.GERMANY, "%,.2f €", item.amount),
                                    fontSize = 16.sp,
                                    fontWeight = FontWeight.Bold,
                                    color = if (item.amount < 0) Rose500 else Emerald500
                                )
                                Spacer(modifier = Modifier.width(4.dp))
                                IconButton(onClick = {
                                    // Start editing
                                    editTitle = item.title
                                    editAmountText = item.amount.toString()
                                    editCategory = item.category ?: ""
                                    editExecutionDate = item.execution_date ?: ""
                                    editIsBusiness = item.is_business
                                    editLocation = item.location ?: ""
                                    editingItemId = item.id
                                }) {
                                    Icon(
                                        imageVector = Icons.Default.Edit,
                                        contentDescription = "Bearbeiten",
                                        tint = Gold,
                                        modifier = Modifier.size(18.dp)
                                    )
                                }
                                IconButton(onClick = { onDelete(item.id) }) {
                                    Icon(
                                        imageVector = Icons.Default.Delete,
                                        contentDescription = "Löschen",
                                        tint = Color.Red.copy(alpha = 0.7f),
                                        modifier = Modifier.size(18.dp)
                                    )
                                }
                            }
                        }
                    }
                }
            }
            Spacer(modifier = Modifier.height(60.dp))
        }
    }
}

@Composable
fun FixedExpensesPieChart(
    groups: List<FinanceGroup>,
    selectedGroupId: String?,
    onSelectGroup: (String?) -> Unit
) {
    val groupStats = remember(groups) {
        val palette = listOf(
            SpaceBlue,              // 0xFF6496FF
            Copper,                 // 0xFFD97753
            Emerald500,             // 0xFF10B981
            Gold,                   // 0xFFC5A059
            Color(0xFF8B5CF6),      // Purple
            Color(0xFFEC4899),      // Pink
            Color(0xFF14B8A6),      // Teal
            Color(0xFFF59E0B),      // Amber
            Color(0xFF06B6D4),      // Cyan
            Color(0xFFE11D48)       // Rose
        )
        // Only include cost/expense groups for the Fixkosten (Fixed Cost) chart!
        val expenseGroups = groups.filter { g ->
            !g.type.equals("income", ignoreCase = true) && 
            !g.name.contains("Einnahme", ignoreCase = true) && 
            g.items.isNotEmpty()
        }
        val allMonthly = expenseGroups.sumOf { g ->
            g.items.sumOf { item ->
                val amt = kotlin.math.abs(item.amount)
                if (item.interval_months > 0) amt / item.interval_months else amt
            }
        }

        expenseGroups.mapIndexed { idx, g ->
            val monthly = g.items.sumOf { item ->
                val amt = kotlin.math.abs(item.amount)
                if (item.interval_months > 0) amt / item.interval_months else amt
            }
            val percentage = if (allMonthly > 0.0) (monthly / allMonthly).toFloat() else 0f
            Triple(g, monthly, percentage) to palette[idx % palette.size]
        }
    }

    val totalMonthly = remember(groupStats) {
        groupStats.sumOf { it.first.second }
    }

    val animProgress = remember { Animatable(0f) }
    LaunchedEffect(groups) {
        animProgress.snapTo(0f)
        animProgress.animateTo(
            targetValue = 1f,
            animationSpec = tween(durationMillis = 900, easing = FastOutSlowInEasing)
        )
    }

    val selectedStat = groupStats.firstOrNull { it.first.first.id == selectedGroupId }

    Card(
        modifier = Modifier
            .fillMaxWidth()
            .border(1.dp, AppTheme.colors.cardBorder, RoundedCornerShape(20.dp)),
        colors = CardDefaults.cardColors(containerColor = AppTheme.colors.cardBackground),
        shape = RoundedCornerShape(20.dp)
    ) {
        Column(
            modifier = Modifier.padding(16.dp),
            verticalArrangement = Arrangement.spacedBy(14.dp)
        ) {
            // Header
            Row(
                modifier = Modifier.fillMaxWidth(),
                horizontalArrangement = Arrangement.SpaceBetween,
                verticalAlignment = Alignment.CenterVertically
            ) {
                Row(
                    verticalAlignment = Alignment.CenterVertically,
                    horizontalArrangement = Arrangement.spacedBy(8.dp)
                ) {
                    Icon(
                        imageVector = Icons.Default.PieChart,
                        contentDescription = null,
                        tint = Gold,
                        modifier = Modifier.size(20.dp)
                    )
                    Text(
                        text = "Fixkosten Analyse",
                        fontSize = 15.sp,
                        fontWeight = FontWeight.Bold,
                        color = AppTheme.colors.textPrimary
                    )
                }

                Box(
                    modifier = Modifier
                        .clip(RoundedCornerShape(12.dp))
                        .background(AppTheme.colors.glassSurface)
                        .border(1.dp, AppTheme.colors.glassBorder, RoundedCornerShape(12.dp))
                        .padding(horizontal = 8.dp, vertical = 4.dp)
                ) {
                    Text(
                        text = "Monatlicher Anteil",
                        fontSize = 10.sp,
                        fontWeight = FontWeight.Medium,
                        color = AppTheme.colors.textMuted
                    )
                }
            }

            if (groupStats.isEmpty() || totalMonthly <= 0.0) {
                Box(
                    modifier = Modifier
                        .fillMaxWidth()
                        .height(130.dp),
                    contentAlignment = Alignment.Center
                ) {
                    Text(
                        text = "Keine aktiven Fixkosten für Auswertung erfasst.",
                        color = AppTheme.colors.textMuted,
                        fontSize = 12.sp
                    )
                }
            } else {
                // Donut Chart with Center KPI Display
                Box(
                    modifier = Modifier
                        .fillMaxWidth()
                        .height(190.dp),
                    contentAlignment = Alignment.Center
                ) {
                    val trackColor = if (AppTheme.colors.isDark) Color(0x1AFFFFFF) else Color(0x12000000)

                    Canvas(modifier = Modifier.size(175.dp)) {
                        val strokeWidth = 22.dp.toPx()
                        val selectedStrokeWidth = 28.dp.toPx()

                        // Background track
                        drawCircle(
                            color = trackColor,
                            style = Stroke(width = strokeWidth)
                        )

                        var startAngle = -90f
                        groupStats.forEach { (stat, color) ->
                            val (group, _, percentage) = stat
                            val isSelected = group.id == selectedGroupId
                            val sweepAngle = percentage * 360f * animProgress.value

                            if (sweepAngle > 0.5f) {
                                drawArc(
                                    color = color,
                                    startAngle = startAngle,
                                    sweepAngle = sweepAngle - (if (groupStats.size > 1) 2f else 0f),
                                    useCenter = false,
                                    style = Stroke(
                                        width = if (isSelected) selectedStrokeWidth else strokeWidth,
                                        cap = StrokeCap.Round
                                    )
                                )
                            }
                            startAngle += sweepAngle
                        }
                    }

                    // Center Content Info
                    Column(
                        horizontalAlignment = Alignment.CenterHorizontally,
                        verticalArrangement = Arrangement.Center,
                        modifier = Modifier
                            .size(115.dp)
                            .padding(4.dp)
                    ) {
                        if (selectedStat != null) {
                            Text(
                                text = selectedStat.first.first.name,
                                fontSize = 11.sp,
                                fontWeight = FontWeight.Bold,
                                color = AppTheme.colors.textPrimary,
                                maxLines = 1,
                                overflow = TextOverflow.Ellipsis,
                                textAlign = TextAlign.Center
                            )
                            Text(
                                text = String.format(Locale.GERMANY, "%,.2f €", selectedStat.first.second),
                                fontSize = 16.sp,
                                fontWeight = FontWeight.ExtraBold,
                                color = selectedStat.second
                            )
                            Text(
                                text = String.format(Locale.GERMANY, "%.1f %% Anteil", selectedStat.first.third * 100f),
                                fontSize = 10.sp,
                                color = AppTheme.colors.textMuted
                            )
                        } else {
                            Text(
                                text = "Gesamt Fix / M.",
                                fontSize = 10.sp,
                                fontWeight = FontWeight.Medium,
                                color = AppTheme.colors.textMuted
                            )
                            Text(
                                text = String.format(Locale.GERMANY, "%,.2f €", totalMonthly * animProgress.value),
                                fontSize = 17.sp,
                                fontWeight = FontWeight.ExtraBold,
                                color = Gold
                            )
                            Text(
                                text = "${groupStats.size} Gruppen",
                                fontSize = 10.sp,
                                color = AppTheme.colors.textMuted
                            )
                        }
                    }
                }

                // Interactive Legend Chips
                Row(
                    modifier = Modifier
                        .fillMaxWidth()
                        .horizontalScroll(rememberScrollState()),
                    horizontalArrangement = Arrangement.spacedBy(8.dp)
                ) {
                    groupStats.forEach { (stat, color) ->
                        val (group, monthly, percentage) = stat
                        val isSelected = group.id == selectedGroupId

                        Row(
                            modifier = Modifier
                                .clip(RoundedCornerShape(12.dp))
                                .background(if (isSelected) color.copy(alpha = 0.18f) else AppTheme.colors.glassSurface)
                                .border(
                                    1.dp,
                                    if (isSelected) color else AppTheme.colors.glassBorder,
                                    RoundedCornerShape(12.dp)
                                )
                                .clickable {
                                    onSelectGroup(if (isSelected) null else group.id)
                                }
                                .padding(horizontal = 10.dp, vertical = 6.dp),
                            verticalAlignment = Alignment.CenterVertically,
                            horizontalArrangement = Arrangement.spacedBy(6.dp)
                        ) {
                            Box(
                                modifier = Modifier
                                    .size(8.dp)
                                    .background(color, CircleShape)
                            )
                            Text(
                                text = group.name,
                                fontSize = 11.sp,
                                fontWeight = if (isSelected) FontWeight.Bold else FontWeight.Medium,
                                color = if (isSelected) color else AppTheme.colors.textPrimary
                            )
                            Text(
                                text = String.format(Locale.GERMANY, "%.0f%%", percentage * 100f),
                                fontSize = 10.sp,
                                fontWeight = FontWeight.Bold,
                                color = AppTheme.colors.textMuted
                            )
                        }
                    }
                }
            }
        }
    }
}

@OptIn(ExperimentalMaterial3Api::class)
@Composable
fun FixedExpensesList(
    groups: List<FinanceGroup>,
    onAddFixedItem: (groupId: String, name: String, amount: Double, intervalMonths: Int, firstPaymentDate: String, description: String?, isBusiness: Boolean) -> Unit,
    onUpdateFixedItem: (id: String, groupId: String, name: String, amount: Double, intervalMonths: Int, firstPaymentDate: String, description: String?, isBusiness: Boolean) -> Unit,
    onDeleteFixedItem: (id: String) -> Unit
) {
    var expandedGroupIds by remember { mutableStateOf(setOf<String>()) }
    var editingCostId by remember { mutableStateOf<String?>(null) }
    var addingToGroupId by remember { mutableStateOf<String?>(null) }
    var selectedChartGroupId by remember { mutableStateOf<String?>(null) }

    // States for editing/adding
    var nameInput by remember { mutableStateOf("") }
    var amountInput by remember { mutableStateOf("") }
    var intervalInput by remember { mutableIntStateOf(1) }
    var firstPaymentDateInput by remember { mutableStateOf("") }
    var descriptionInput by remember { mutableStateOf("") }
    var isBusinessInput by remember { mutableStateOf(false) }

    val context = LocalContext.current

    if (groups.isEmpty()) {
        Box(
            modifier = Modifier
                .fillMaxSize()
                .padding(24.dp),
            contentAlignment = Alignment.Center
        ) {
            Text("Keine Fixkosten-Gruppen vorhanden.", color = AppTheme.colors.textMuted, textAlign = TextAlign.Center)
        }
    } else {
        Column(
            modifier = Modifier
                .fillMaxSize()
                .verticalScroll(rememberScrollState())
                .padding(16.dp),
            verticalArrangement = Arrangement.spacedBy(16.dp)
        ) {
            // Visual Analytics Pie/Donut Chart for Fixed Expenses
            FixedExpensesPieChart(
                groups = groups,
                selectedGroupId = selectedChartGroupId,
                onSelectGroup = { id ->
                    selectedChartGroupId = id
                    if (id != null) {
                        expandedGroupIds = expandedGroupIds + id
                    }
                }
            )

            groups.forEach { group ->
                val isExpanded = expandedGroupIds.contains(group.id)
                val isAddingHere = addingToGroupId == group.id

                Column(verticalArrangement = Arrangement.spacedBy(8.dp)) {
                    // Collapsible Header
                    Row(
                        modifier = Modifier
                            .fillMaxWidth()
                            .clickable {
                                expandedGroupIds = if (isExpanded) expandedGroupIds - group.id else expandedGroupIds + group.id
                            }
                            .padding(vertical = 4.dp),
                        horizontalArrangement = Arrangement.SpaceBetween,
                        verticalAlignment = Alignment.CenterVertically
                    ) {
                        Text(
                            text = group.name.uppercase(Locale.getDefault()),
                            fontSize = 12.sp,
                            fontWeight = FontWeight.Bold,
                            color = Gold,
                            letterSpacing = 1.sp
                        )
                        Icon(
                            imageVector = if (isExpanded) Icons.Default.ExpandLess else Icons.Default.ExpandMore,
                            contentDescription = null,
                            tint = Gold,
                            modifier = Modifier.size(18.dp)
                        )
                    }

                    if (isExpanded) {
                        // Display cost items
                        group.items.forEach { cost ->
                            val isEditing = editingCostId == cost.id

                            Card(
                                modifier = Modifier
                                    .fillMaxWidth()
                                    .border(1.dp, AppTheme.colors.cardBorder, RoundedCornerShape(12.dp)),
                                colors = CardDefaults.cardColors(containerColor = AppTheme.colors.cardBackground),
                                shape = RoundedCornerShape(12.dp)
                            ) {
                                if (isEditing) {
                                    // Cost Edit Form
                                    Column(
                                        modifier = Modifier.padding(14.dp),
                                        verticalArrangement = Arrangement.spacedBy(10.dp)
                                    ) {
                                        Text("Fixkostenstelle bearbeiten", color = Gold, fontSize = 12.sp, fontWeight = FontWeight.Bold)

                                        OutlinedTextField(
                                            value = nameInput,
                                            onValueChange = { nameInput = it },
                                            label = { Text("Name") },
                                            modifier = Modifier.fillMaxWidth(),
                                            colors = OutlinedTextFieldDefaults.colors(
                                                focusedBorderColor = Gold,
                                                unfocusedBorderColor = AppTheme.colors.inputBorder,
                                                focusedTextColor = AppTheme.colors.textPrimary,
                                                unfocusedTextColor = AppTheme.colors.textPrimary
                                            )
                                        )

                                        OutlinedTextField(
                                            value = amountInput,
                                            onValueChange = { amountInput = it },
                                            label = { Text("Betrag (€)") },
                                            modifier = Modifier.fillMaxWidth(),
                                            keyboardOptions = KeyboardOptions(keyboardType = KeyboardType.Number),
                                            colors = OutlinedTextFieldDefaults.colors(
                                                focusedBorderColor = Gold,
                                                unfocusedBorderColor = AppTheme.colors.inputBorder,
                                                focusedTextColor = AppTheme.colors.textPrimary,
                                                unfocusedTextColor = AppTheme.colors.textPrimary
                                            )
                                        )

                                        // Interval Selection Dropdown
                                        var intervalDropdownExpanded by remember { mutableStateOf(false) }
                                        val intervals = listOf(1 to "Monatlich", 3 to "Vierteljährlich", 6 to "Halbjährlich", 12 to "Jährlich")
                                        ExposedDropdownMenuBox(
                                            expanded = intervalDropdownExpanded,
                                            onExpandedChange = { intervalDropdownExpanded = it }
                                        ) {
                                            OutlinedTextField(
                                                readOnly = true,
                                                value = intervals.find { it.first == intervalInput }?.second ?: "Alle $intervalInput Monate",
                                                onValueChange = {},
                                                label = { Text("Intervall") },
                                                trailingIcon = { ExposedDropdownMenuDefaults.TrailingIcon(expanded = intervalDropdownExpanded) },
                                                colors = OutlinedTextFieldDefaults.colors(
                                                    focusedBorderColor = Gold,
                                                    unfocusedBorderColor = AppTheme.colors.inputBorder,
                                                    focusedTextColor = AppTheme.colors.textPrimary,
                                                    unfocusedTextColor = AppTheme.colors.textPrimary
                                                ),
                                                modifier = Modifier.fillMaxWidth().menuAnchor()
                                            )
                                            ExposedDropdownMenu(
                                                expanded = intervalDropdownExpanded,
                                                onDismissRequest = { intervalDropdownExpanded = false }
                                            ) {
                                                intervals.forEach { (months, label) ->
                                                    val isSelected = intervalInput == months
                                                    DropdownMenuItem(
                                                        text = {
                                                            Text(
                                                                text = label,
                                                                color = if (isSelected) SpaceBlack else AppTheme.colors.textPrimary,
                                                                fontWeight = if (isSelected) FontWeight.Bold else FontWeight.Normal
                                                            )
                                                        },
                                                        onClick = {
                                                            intervalInput = months
                                                            intervalDropdownExpanded = false
                                                        },
                                                        modifier = Modifier
                                                            .fillMaxWidth()
                                                            .background(if (isSelected) Gold else Color.Transparent)
                                                    )
                                                }
                                            }
                                        }

                                        // Date selection picker
                                        val datePickerDialog = remember {
                                            val cal = java.util.Calendar.getInstance()
                                            if (firstPaymentDateInput.contains("-")) {
                                                try {
                                                    val parts = firstPaymentDateInput.split("-")
                                                    cal.set(parts[0].toInt(), parts[1].toInt() - 1, parts[2].toInt())
                                                } catch (e: Exception) {}
                                            }
                                            android.app.DatePickerDialog(
                                                context,
                                                { _, year, month, dayOfMonth ->
                                                    val formattedMonth = String.format("%02d", month + 1)
                                                    val formattedDay = String.format("%02d", dayOfMonth)
                                                    firstPaymentDateInput = "$year-$formattedMonth-$formattedDay"
                                                },
                                                cal.get(java.util.Calendar.YEAR),
                                                cal.get(java.util.Calendar.MONTH),
                                                cal.get(java.util.Calendar.DAY_OF_MONTH)
                                            )
                                        }

                                        Box(
                                            modifier = Modifier
                                                .fillMaxWidth()
                                                .clickable { datePickerDialog.show() }
                                        ) {
                                            OutlinedTextField(
                                                readOnly = true,
                                                value = firstPaymentDateInput,
                                                onValueChange = {},
                                                label = { Text("Erstzahlung") },
                                                trailingIcon = {
                                                    Icon(
                                                        imageVector = Icons.Default.DateRange,
                                                        contentDescription = null,
                                                        tint = Gold
                                                    )
                                                },
                                                modifier = Modifier.fillMaxWidth(),
                                                colors = OutlinedTextFieldDefaults.colors(
                                                    focusedBorderColor = Gold,
                                                    unfocusedBorderColor = AppTheme.colors.inputBorder,
                                                    focusedTextColor = AppTheme.colors.textPrimary,
                                                    unfocusedTextColor = AppTheme.colors.textPrimary,
                                                    disabledBorderColor = AppTheme.colors.inputBorder,
                                                    disabledLabelColor = AppTheme.colors.textMuted,
                                                    disabledTextColor = AppTheme.colors.textPrimary
                                                ),
                                                enabled = false
                                            )
                                            Box(
                                                modifier = Modifier
                                                    .matchParentSize()
                                                    .background(Color.Transparent)
                                                    .clickable { datePickerDialog.show() }
                                            )
                                        }

                                        OutlinedTextField(
                                            value = descriptionInput,
                                            onValueChange = { descriptionInput = it },
                                            label = { Text("Beschreibung (optional)") },
                                            modifier = Modifier.fillMaxWidth(),
                                            colors = OutlinedTextFieldDefaults.colors(
                                                focusedBorderColor = Gold,
                                                unfocusedBorderColor = AppTheme.colors.inputBorder,
                                                focusedTextColor = AppTheme.colors.textPrimary,
                                                unfocusedTextColor = AppTheme.colors.textPrimary
                                            )
                                        )

                                        Row(
                                            modifier = Modifier.fillMaxWidth(),
                                            horizontalArrangement = Arrangement.SpaceBetween,
                                            verticalAlignment = Alignment.CenterVertically
                                        ) {
                                            Text("Gewerblich?", color = AppTheme.colors.textPrimary, fontSize = 14.sp)
                                            Switch(
                                                checked = isBusinessInput,
                                                onCheckedChange = { isBusinessInput = it },
                                                colors = SwitchDefaults.colors(
                                                    checkedThumbColor = Gold,
                                                    checkedTrackColor = Color(0x33C5A059)
                                                )
                                            )
                                        }

                                        Row(
                                            modifier = Modifier.fillMaxWidth(),
                                            horizontalArrangement = Arrangement.End,
                                            verticalAlignment = Alignment.CenterVertically
                                        ) {
                                            TextButton(onClick = { editingCostId = null }) {
                                                Text("Abbrechen", color = AppTheme.colors.textMuted)
                                            }
                                            Spacer(modifier = Modifier.width(8.dp))
                                            Button(
                                                onClick = {
                                                    val amt = amountInput.replace(',', '.').toDoubleOrNull() ?: 0.0
                                                    onUpdateFixedItem(
                                                        cost.id,
                                                        group.id,
                                                        nameInput,
                                                        amt,
                                                        intervalInput,
                                                        firstPaymentDateInput.ifBlank { java.time.LocalDate.now().toString() },
                                                        descriptionInput.ifBlank { null },
                                                        isBusinessInput
                                                    )
                                                    editingCostId = null
                                                },
                                                enabled = nameInput.isNotBlank() && amountInput.replace(',', '.').toDoubleOrNull() != null,
                                                colors = ButtonDefaults.buttonColors(containerColor = Gold, contentColor = SpaceBlack)
                                            ) {
                                                Text("Speichern")
                                            }
                                        }
                                    }
                                } else {
                                    // Cost Display Item
                                    Row(
                                        modifier = Modifier.padding(14.dp),
                                        verticalAlignment = Alignment.CenterVertically,
                                        horizontalArrangement = Arrangement.SpaceBetween
                                    ) {
                                        Column(modifier = Modifier.weight(1f)) {
                                            Row(verticalAlignment = Alignment.CenterVertically) {
                                                Text(
                                                    text = cost.name,
                                                    fontSize = 14.sp,
                                                    fontWeight = FontWeight.Bold,
                                                    color = AppTheme.colors.textPrimary
                                                )
                                                if (cost.is_business) {
                                                    Spacer(modifier = Modifier.width(6.dp))
                                                    Icon(
                                                        imageVector = Icons.Default.BusinessCenter,
                                                        contentDescription = "Gewerblich",
                                                        tint = Copper,
                                                        modifier = Modifier.size(12.dp)
                                                    )
                                                }
                                                if (!cost.contract_file_path.isNullOrBlank()) {
                                                    Spacer(modifier = Modifier.width(6.dp))
                                                    IconButton(
                                                        onClick = {
                                                            val token = ServiceLocator.getAuthToken() ?: ""
                                                            val baseUrl = ServiceLocator.getBaseUrl()
                                                            val url = "${baseUrl}funki/financials/receipt?path=${android.net.Uri.encode(cost.contract_file_path)}&token=${android.net.Uri.encode(token)}"
                                                            try {
                                                                val intent = Intent(Intent.ACTION_VIEW, Uri.parse(url))
                                                                context.startActivity(intent)
                                                            } catch (e: Exception) {
                                                                e.printStackTrace()
                                                            }
                                                        },
                                                        modifier = Modifier.size(20.dp)
                                                    ) {
                                                        Icon(
                                                            imageVector = Icons.Default.AttachFile,
                                                            contentDescription = "Vertrag anzeigen",
                                                            tint = Gold,
                                                            modifier = Modifier.size(14.dp)
                                                        )
                                                    }
                                                }
                                            }
                                            val intervalText = when (cost.interval_months) {
                                                1 -> "Monatlich"
                                                3 -> "Vierteljährlich"
                                                6 -> "Halbjährlich"
                                                12 -> "Jährlich"
                                                else -> "Alle ${cost.interval_months} Monate"
                                            }
                                            Text(
                                                text = "$intervalText • Erstzahlung: ${formatDateString(cost.first_payment_date)}",
                                                fontSize = 11.sp,
                                                color = AppTheme.colors.textMuted,
                                                modifier = Modifier.padding(top = 2.dp)
                                            )
                                            if (!cost.description.isNullOrBlank()) {
                                                Text(
                                                    text = cost.description,
                                                    fontSize = 10.sp,
                                                    color = AppTheme.colors.textMuted,
                                                    modifier = Modifier.padding(top = 2.dp)
                                                )
                                            }
                                        }

                                        Row(verticalAlignment = Alignment.CenterVertically) {
                                            Text(
                                                text = String.format(Locale.GERMANY, "%,.2f €", cost.amount),
                                                fontSize = 14.sp,
                                                fontWeight = FontWeight.Bold,
                                                color = if (cost.amount < 0) Rose500 else Emerald500
                                            )
                                            Spacer(modifier = Modifier.width(4.dp))
                                            IconButton(onClick = {
                                                nameInput = cost.name
                                                amountInput = cost.amount.toString()
                                                intervalInput = cost.interval_months
                                                firstPaymentDateInput = cost.first_payment_date ?: ""
                                                descriptionInput = cost.description ?: ""
                                                isBusinessInput = cost.is_business
                                                editingCostId = cost.id
                                                addingToGroupId = null
                                            }) {
                                                Icon(
                                                    imageVector = Icons.Default.Edit,
                                                    contentDescription = "Bearbeiten",
                                                    tint = Gold,
                                                    modifier = Modifier.size(16.dp)
                                                )
                                            }
                                            IconButton(onClick = { onDeleteFixedItem(cost.id) }) {
                                                Icon(
                                                    imageVector = Icons.Default.Delete,
                                                    contentDescription = "Löschen",
                                                    tint = Color.Red.copy(alpha = 0.7f),
                                                    modifier = Modifier.size(16.dp)
                                                )
                                            }
                                        }
                                    }
                                }
                            }
                        }

                        // Add Cost Item Row / Button inside Group
                        if (isAddingHere) {
                            Card(
                                modifier = Modifier
                                    .fillMaxWidth()
                                    .border(1.dp, Gold.copy(alpha = 0.5f), RoundedCornerShape(12.dp)),
                                colors = CardDefaults.cardColors(containerColor = AppTheme.colors.cardBackground)
                            ) {
                                Column(
                                    modifier = Modifier.padding(14.dp),
                                    verticalArrangement = Arrangement.spacedBy(10.dp)
                                ) {
                                    Text("Neue Fixkostenstelle hinzufügen", color = Gold, fontSize = 12.sp, fontWeight = FontWeight.Bold)

                                    OutlinedTextField(
                                        value = nameInput,
                                        onValueChange = { nameInput = it },
                                        label = { Text("Name") },
                                        modifier = Modifier.fillMaxWidth(),
                                        colors = OutlinedTextFieldDefaults.colors(
                                            focusedBorderColor = Gold,
                                            unfocusedBorderColor = AppTheme.colors.inputBorder,
                                            focusedTextColor = AppTheme.colors.textPrimary,
                                            unfocusedTextColor = AppTheme.colors.textPrimary
                                        )
                                    )

                                    OutlinedTextField(
                                        value = amountInput,
                                        onValueChange = { amountInput = it },
                                        label = { Text("Betrag (€)") },
                                        modifier = Modifier.fillMaxWidth(),
                                        keyboardOptions = KeyboardOptions(keyboardType = KeyboardType.Number),
                                        colors = OutlinedTextFieldDefaults.colors(
                                            focusedBorderColor = Gold,
                                            unfocusedBorderColor = AppTheme.colors.inputBorder,
                                            focusedTextColor = AppTheme.colors.textPrimary,
                                            unfocusedTextColor = AppTheme.colors.textPrimary
                                        )
                                    )

                                    // Interval Selector Dropdown
                                    var addingDropdownExpanded by remember { mutableStateOf(false) }
                                    val intervals = listOf(1 to "Monatlich", 3 to "Vierteljährlich", 6 to "Halbjährlich", 12 to "Jährlich")
                                    ExposedDropdownMenuBox(
                                        expanded = addingDropdownExpanded,
                                        onExpandedChange = { addingDropdownExpanded = it }
                                    ) {
                                        OutlinedTextField(
                                            readOnly = true,
                                            value = intervals.find { it.first == intervalInput }?.second ?: "Alle $intervalInput Monate",
                                            onValueChange = {},
                                            label = { Text("Intervall") },
                                            trailingIcon = { ExposedDropdownMenuDefaults.TrailingIcon(expanded = addingDropdownExpanded) },
                                            colors = OutlinedTextFieldDefaults.colors(
                                                focusedBorderColor = Gold,
                                                unfocusedBorderColor = AppTheme.colors.inputBorder,
                                                focusedTextColor = AppTheme.colors.textPrimary,
                                                unfocusedTextColor = AppTheme.colors.textPrimary
                                            ),
                                            modifier = Modifier.fillMaxWidth().menuAnchor()
                                        )
                                        ExposedDropdownMenu(
                                            expanded = addingDropdownExpanded,
                                            onDismissRequest = { addingDropdownExpanded = false }
                                        ) {
                                            intervals.forEach { (months, label) ->
                                                val isSelected = intervalInput == months
                                                DropdownMenuItem(
                                                    text = {
                                                        Text(
                                                            text = label,
                                                            color = if (isSelected) SpaceBlack else AppTheme.colors.textPrimary,
                                                            fontWeight = if (isSelected) FontWeight.Bold else FontWeight.Normal
                                                        )
                                                    },
                                                    onClick = {
                                                        intervalInput = months
                                                        addingDropdownExpanded = false
                                                    },
                                                    modifier = Modifier
                                                        .fillMaxWidth()
                                                        .background(if (isSelected) Gold else Color.Transparent)
                                                )
                                            }
                                        }
                                    }

                                    // Date Picker
                                    val datePickerDialog = remember {
                                        val cal = java.util.Calendar.getInstance()
                                        android.app.DatePickerDialog(
                                            context,
                                            { _, year, month, dayOfMonth ->
                                                val formattedMonth = String.format("%02d", month + 1)
                                                val formattedDay = String.format("%02d", dayOfMonth)
                                                firstPaymentDateInput = "$year-$formattedMonth-$formattedDay"
                                            },
                                            cal.get(java.util.Calendar.YEAR),
                                            cal.get(java.util.Calendar.MONTH),
                                            cal.get(java.util.Calendar.DAY_OF_MONTH)
                                        )
                                    }

                                    Box(
                                        modifier = Modifier
                                            .fillMaxWidth()
                                            .clickable { datePickerDialog.show() }
                                    ) {
                                        OutlinedTextField(
                                            readOnly = true,
                                            value = firstPaymentDateInput.ifBlank { "Bitte Datum wählen" },
                                            onValueChange = {},
                                            label = { Text("Erstzahlung") },
                                            trailingIcon = {
                                                Icon(
                                                    imageVector = Icons.Default.DateRange,
                                                    contentDescription = null,
                                                    tint = Gold
                                                )
                                            },
                                            modifier = Modifier.fillMaxWidth(),
                                            colors = OutlinedTextFieldDefaults.colors(
                                                focusedBorderColor = Gold,
                                                unfocusedBorderColor = AppTheme.colors.inputBorder,
                                                focusedTextColor = AppTheme.colors.textPrimary,
                                                unfocusedTextColor = AppTheme.colors.textPrimary,
                                                disabledBorderColor = AppTheme.colors.inputBorder,
                                                disabledLabelColor = AppTheme.colors.textMuted,
                                                disabledTextColor = AppTheme.colors.textPrimary
                                            ),
                                            enabled = false
                                        )
                                        Box(
                                            modifier = Modifier
                                                .matchParentSize()
                                                .background(Color.Transparent)
                                                .clickable { datePickerDialog.show() }
                                        )
                                    }

                                    OutlinedTextField(
                                        value = descriptionInput,
                                        onValueChange = { descriptionInput = it },
                                        label = { Text("Beschreibung (optional)") },
                                        modifier = Modifier.fillMaxWidth(),
                                        colors = OutlinedTextFieldDefaults.colors(
                                            focusedBorderColor = Gold,
                                            unfocusedBorderColor = AppTheme.colors.inputBorder,
                                            focusedTextColor = AppTheme.colors.textPrimary,
                                            unfocusedTextColor = AppTheme.colors.textPrimary
                                        )
                                    )

                                    Row(
                                        modifier = Modifier.fillMaxWidth(),
                                        horizontalArrangement = Arrangement.SpaceBetween,
                                        verticalAlignment = Alignment.CenterVertically
                                    ) {
                                        Text("Gewerblich?", color = AppTheme.colors.textPrimary, fontSize = 14.sp)
                                        Switch(
                                            checked = isBusinessInput,
                                            onCheckedChange = { isBusinessInput = it },
                                            colors = SwitchDefaults.colors(
                                                checkedThumbColor = Gold,
                                                checkedTrackColor = Color(0x33C5A059)
                                            )
                                        )
                                    }

                                    Row(
                                        modifier = Modifier.fillMaxWidth(),
                                        horizontalArrangement = Arrangement.End,
                                        verticalAlignment = Alignment.CenterVertically
                                    ) {
                                        TextButton(onClick = { addingToGroupId = null }) {
                                            Text("Abbrechen", color = AppTheme.colors.textMuted)
                                        }
                                        Spacer(modifier = Modifier.width(8.dp))
                                        Button(
                                            onClick = {
                                                val amt = amountInput.replace(',', '.').toDoubleOrNull() ?: 0.0
                                                val firstDate = firstPaymentDateInput.ifBlank { java.time.LocalDate.now().toString() }
                                                onAddFixedItem(
                                                    group.id,
                                                    nameInput,
                                                    amt,
                                                    intervalInput,
                                                    firstDate,
                                                    descriptionInput.ifBlank { null },
                                                    isBusinessInput
                                                )
                                                addingToGroupId = null
                                            },
                                            enabled = nameInput.isNotBlank() && amountInput.replace(',', '.').toDoubleOrNull() != null,
                                            colors = ButtonDefaults.buttonColors(containerColor = Gold, contentColor = SpaceBlack)
                                        ) {
                                            Text("Hinzufügen")
                                        }
                                    }
                                }
                            }
                        } else {
                            Row(
                                modifier = Modifier
                                    .fillMaxWidth()
                                    .clickable {
                                        nameInput = ""
                                        amountInput = ""
                                        intervalInput = 1
                                        firstPaymentDateInput = java.time.LocalDate.now().toString()
                                        descriptionInput = ""
                                        isBusinessInput = false
                                        addingToGroupId = group.id
                                        editingCostId = null
                                    }
                                    .border(1.dp, AppTheme.colors.cardBorder, RoundedCornerShape(8.dp))
                                    .padding(vertical = 12.dp),
                                horizontalArrangement = Arrangement.Center,
                                verticalAlignment = Alignment.CenterVertically
                            ) {
                                Icon(
                                    imageVector = Icons.Default.Add,
                                    contentDescription = null,
                                    tint = Gold,
                                    modifier = Modifier.size(16.dp)
                                )
                                Spacer(modifier = Modifier.width(8.dp))
                                Text(
                                    text = "Fixkostenstelle hinzufügen",
                                    color = Gold,
                                    fontSize = 12.sp,
                                    fontWeight = FontWeight.Bold
                                )
                            }
                        }
                    }
                }
            }
            Spacer(modifier = Modifier.height(60.dp))
        }
    }
}

@OptIn(ExperimentalMaterial3Api::class)
@Composable
fun QuickEntryForm(
    categories: List<String>,
    isUploading: Boolean,
    isAiScanning: Boolean = false,
    showCard: Boolean = true,
    showTitle: Boolean = false,
    onDismiss: () -> Unit,
    onScanReceipt: (
        fileBytes: ByteArray,
        fileName: String,
        mimeType: String,
        autoSave: Boolean,
        onSuccess: (ReceiptAiScanData) -> Unit
    ) -> Unit = { _, _, _, _, _ -> },
    onSubmit: (
        title: String,
        amount: Double,
        category: String?,
        isBusiness: Boolean,
        date: String?,
        location: String?,
        fileBytes: ByteArray?,
        fileName: String?,
        mimeType: String?
    ) -> Unit
) {
    var title by remember { mutableStateOf("") }
    var amountText by remember { mutableStateOf("") }
    var selectedCategory by remember { mutableStateOf("") }
    var isBusiness by remember { mutableStateOf(false) }
    var location by remember { mutableStateOf("") }

    val today = java.time.LocalDate.now().toString()
    var executionDate by remember { mutableStateOf(today) }

    // AI Scan options & state
    var autoScanWithAi by remember { mutableStateOf(true) }
    var autoSaveOnScan by remember { mutableStateOf(false) }
    var aiScanResultNote by remember { mutableStateOf<String?>(null) }
    
    // File upload details
    var pickedFileUri by remember { mutableStateOf<Uri?>(null) }
    var pickedFileName by remember { mutableStateOf<String?>(null) }
    var pickedFileBytes by remember { mutableStateOf<ByteArray?>(null) }
    var pickedFileMime by remember { mutableStateOf<String?>(null) }

    val triggerAiScan: (ByteArray, String, String) -> Unit = { bytes, name, mime ->
        aiScanResultNote = null
        onScanReceipt(bytes, name, mime, autoSaveOnScan) { scanData ->
            if (scanData.title.isNotBlank()) title = scanData.title
            if (scanData.amount_abs > 0.0) {
                amountText = String.format(Locale.US, "%.2f", scanData.amount_abs)
            } else if (scanData.amount_formatted.isNotBlank()) {
                amountText = scanData.amount_formatted
            }
            scanData.category?.let { cat ->
                if (cat.isNotBlank()) {
                    val matched = categories.firstOrNull { c -> c.equals(cat, ignoreCase = true) } ?: cat
                    selectedCategory = matched
                }
            }
            scanData.date?.let { if (it.isNotBlank()) executionDate = it }
            val loc = scanData.merchant ?: scanData.location
            loc?.let { if (it.isNotBlank() && location.isBlank()) location = it }
            if (scanData.is_business) isBusiness = true
            aiScanResultNote = "✨ KI-Foto-Analyse: ${scanData.title} | ${scanData.amount_formatted} € (automatisch befüllt)"
        }
    }

    val context = LocalContext.current
    val contentResolver = context.contentResolver

    val datePickerDialog = android.app.DatePickerDialog(
        context,
        { _, year, month, dayOfMonth ->
            val formattedMonth = String.format("%02d", month + 1)
            val formattedDay = String.format("%02d", dayOfMonth)
            executionDate = "$year-$formattedMonth-$formattedDay"
        },
        java.util.Calendar.getInstance().get(java.util.Calendar.YEAR),
        java.util.Calendar.getInstance().get(java.util.Calendar.MONTH),
        java.util.Calendar.getInstance().get(java.util.Calendar.DAY_OF_MONTH)
    )

    val filePickerLauncher = rememberLauncherForActivityResult(
        contract = ActivityResultContracts.GetContent()
    ) { uri ->
        uri?.let {
            pickedFileUri = it
            pickedFileMime = contentResolver.getType(it)
            
            val cursor = contentResolver.query(it, null, null, null, null)
            cursor?.use { c ->
                val nameIndex = c.getColumnIndex(android.provider.OpenableColumns.DISPLAY_NAME)
                if (nameIndex != -1 && c.moveToFirst()) {
                    pickedFileName = c.getString(nameIndex)
                }
            }
            if (pickedFileName == null) {
                val ext = MimeTypeMap.getSingleton().getExtensionFromMimeType(pickedFileMime)
                pickedFileName = "beleg_${System.currentTimeMillis()}.$ext"
            }

            try {
                val isImage = pickedFileMime?.startsWith("image/") == true
                var compressedBytes: ByteArray? = null
                if (isImage) {
                    compressedBytes = de.meinseelenfunke.app.util.ImageUtils.compressImageUri(context, it)
                }
                
                if (compressedBytes != null) {
                    pickedFileBytes = compressedBytes
                    pickedFileMime = "image/jpeg"
                    val name = pickedFileName ?: "beleg_${System.currentTimeMillis()}.jpg"
                    pickedFileName = if (name.endsWith(".jpg", ignoreCase = true) || name.endsWith(".jpeg", ignoreCase = true)) {
                        name
                    } else {
                        val dotIndex = name.lastIndexOf('.')
                        if (dotIndex != -1) {
                            name.substring(0, dotIndex) + ".jpg"
                        } else {
                            "$name.jpg"
                        }
                    }
                } else {
                    contentResolver.openInputStream(it)?.use { input ->
                        pickedFileBytes = input.readBytes()
                    }
                }

                if (autoScanWithAi && pickedFileBytes != null && pickedFileName != null && pickedFileMime != null) {
                    triggerAiScan(pickedFileBytes!!, pickedFileName!!, pickedFileMime!!)
                }
            } catch (e: Exception) {
                e.printStackTrace()
            }
        }
    }

    var cameraTempUri by remember { mutableStateOf<android.net.Uri?>(null) }

    val cameraLauncher = rememberLauncherForActivityResult(
        contract = ActivityResultContracts.TakePicture()
    ) { success ->
        if (success) {
            cameraTempUri?.let { uri ->
                try {
                    val compressedBytes = de.meinseelenfunke.app.util.ImageUtils.compressImageUri(context, uri)
                    if (compressedBytes != null) {
                        pickedFileBytes = compressedBytes
                        pickedFileMime = "image/jpeg"
                        pickedFileName = "beleg_kamera_${System.currentTimeMillis()}.jpg"
                        pickedFileUri = null
                    } else {
                        context.contentResolver.openInputStream(uri)?.use { input ->
                            pickedFileBytes = input.readBytes()
                        }
                        pickedFileMime = "image/jpeg"
                        pickedFileName = "beleg_kamera_${System.currentTimeMillis()}.jpg"
                        pickedFileUri = null
                    }

                    if (autoScanWithAi && pickedFileBytes != null && pickedFileName != null && pickedFileMime != null) {
                        triggerAiScan(pickedFileBytes!!, pickedFileName!!, pickedFileMime!!)
                    }
                } catch (e: Exception) {
                    e.printStackTrace()
                }
            }
        }
    }

    var dropdownExpanded by remember { mutableStateOf(false) }

    val formBody = @Composable {
        Column(
            modifier = Modifier.fillMaxWidth(),
            verticalArrangement = Arrangement.spacedBy(10.dp)
        ) {
            if (showTitle) {
                Text(
                    text = "Schnelleintrag hinzufügen",
                    color = AppTheme.colors.textPrimary,
                    fontSize = 15.sp,
                    fontWeight = FontWeight.Bold
                )
            }

            // Zeile 1: Titel (1.3f) + Betrag (0.9f)
            Row(
                modifier = Modifier.fillMaxWidth(),
                horizontalArrangement = Arrangement.spacedBy(8.dp),
                verticalAlignment = Alignment.CenterVertically
            ) {
                OutlinedTextField(
                    value = title,
                    onValueChange = { title = it },
                    label = { Text("Titel") },
                    singleLine = true,
                    modifier = Modifier.weight(1.3f),
                    colors = OutlinedTextFieldDefaults.colors(
                        focusedBorderColor = Gold,
                        unfocusedBorderColor = AppTheme.colors.inputBorder,
                        focusedTextColor = AppTheme.colors.textPrimary,
                        unfocusedTextColor = AppTheme.colors.textPrimary
                    )
                )

                OutlinedTextField(
                    value = amountText,
                    onValueChange = { amountText = it },
                    label = { Text("Betrag (€)") },
                    singleLine = true,
                    modifier = Modifier.weight(0.9f),
                    keyboardOptions = KeyboardOptions(keyboardType = KeyboardType.Decimal),
                    colors = OutlinedTextFieldDefaults.colors(
                        focusedBorderColor = Gold,
                        unfocusedBorderColor = AppTheme.colors.inputBorder,
                        focusedTextColor = AppTheme.colors.textPrimary,
                        unfocusedTextColor = AppTheme.colors.textPrimary
                    )
                )
            }

            // Zeile 2: Kategorie (1.2f) + Datum (0.9f)
            Row(
                modifier = Modifier.fillMaxWidth(),
                horizontalArrangement = Arrangement.spacedBy(8.dp),
                verticalAlignment = Alignment.CenterVertically
            ) {
                val filteredCategories = remember(selectedCategory, categories) {
                    categories.filter { it.contains(selectedCategory, ignoreCase = true) }
                }
                ExposedDropdownMenuBox(
                    expanded = dropdownExpanded && filteredCategories.isNotEmpty(),
                    onExpandedChange = { dropdownExpanded = it },
                    modifier = Modifier.weight(1.2f)
                ) {
                    OutlinedTextField(
                        readOnly = false,
                        value = selectedCategory,
                        onValueChange = { 
                            selectedCategory = it
                            dropdownExpanded = true
                        },
                        singleLine = true,
                        label = { Text("Kategorie") },
                        trailingIcon = { ExposedDropdownMenuDefaults.TrailingIcon(expanded = dropdownExpanded) },
                        colors = OutlinedTextFieldDefaults.colors(
                            focusedBorderColor = Gold,
                            unfocusedBorderColor = AppTheme.colors.inputBorder,
                            focusedTextColor = AppTheme.colors.textPrimary,
                            unfocusedTextColor = AppTheme.colors.textPrimary
                        ),
                        modifier = Modifier
                            .fillMaxWidth()
                            .menuAnchor()
                    )
                    ExposedDropdownMenu(
                        expanded = dropdownExpanded && filteredCategories.isNotEmpty(),
                        onDismissRequest = { dropdownExpanded = false }
                    ) {
                        filteredCategories.forEach { selection ->
                            val isSelected = selectedCategory == selection
                            DropdownMenuItem(
                                text = {
                                    Text(
                                        text = selection,
                                        color = if (isSelected) SpaceBlack else AppTheme.colors.textPrimary,
                                        fontWeight = if (isSelected) FontWeight.Bold else FontWeight.Normal
                                    )
                                },
                                onClick = {
                                    selectedCategory = selection
                                    dropdownExpanded = false
                                },
                                modifier = Modifier
                                    .fillMaxWidth()
                                    .background(if (isSelected) Gold else Color.Transparent)
                            )
                        }
                    }
                }

                // Date Picker field
                Box(
                    modifier = Modifier
                        .weight(0.9f)
                        .clickable { datePickerDialog.show() }
                ) {
                    OutlinedTextField(
                        readOnly = true,
                        value = executionDate,
                        onValueChange = {},
                        singleLine = true,
                        label = { Text("Datum") },
                        trailingIcon = {
                            Icon(
                                imageVector = Icons.Default.DateRange,
                                contentDescription = "Datum wählen",
                                tint = Gold,
                                modifier = Modifier.size(18.dp)
                            )
                        },
                        modifier = Modifier.fillMaxWidth(),
                        colors = OutlinedTextFieldDefaults.colors(
                            focusedBorderColor = Gold,
                            unfocusedBorderColor = AppTheme.colors.inputBorder,
                            focusedTextColor = AppTheme.colors.textPrimary,
                            unfocusedTextColor = AppTheme.colors.textPrimary,
                            disabledBorderColor = AppTheme.colors.inputBorder,
                            disabledLabelColor = AppTheme.colors.textMuted,
                            disabledTextColor = AppTheme.colors.textPrimary
                        ),
                        enabled = false
                    )
                    Box(
                        modifier = Modifier
                            .matchParentSize()
                            .background(Color.Transparent)
                            .clickable { datePickerDialog.show() }
                    )
                }
            }

            // Zeile 3: Beleg Buttons (Kamera / Datei) & Gewerblich Switch
            Row(
                modifier = Modifier.fillMaxWidth(),
                horizontalArrangement = Arrangement.SpaceBetween,
                verticalAlignment = Alignment.CenterVertically
            ) {
                Row(
                    horizontalArrangement = Arrangement.spacedBy(8.dp),
                    verticalAlignment = Alignment.CenterVertically
                ) {
                    // KI Foto Scan Button
                    Row(
                        modifier = Modifier
                            .clip(RoundedCornerShape(8.dp))
                            .background(Color(0x22C5A059))
                            .border(1.dp, Gold, RoundedCornerShape(8.dp))
                            .clickable {
                                try {
                                    val cacheDir = context.cacheDir
                                    val tempFile = java.io.File.createTempFile("camera_temp_", ".jpg", cacheDir).apply {
                                        deleteOnExit()
                                    }
                                    val uri = androidx.core.content.FileProvider.getUriForFile(
                                        context,
                                        "de.meinseelenfunke.app.fileprovider",
                                        tempFile
                                    )
                                    cameraTempUri = uri
                                    cameraLauncher.launch(uri)
                                } catch (e: Exception) {
                                    e.printStackTrace()
                                }
                            }
                            .padding(horizontal = 10.dp, vertical = 7.dp),
                        verticalAlignment = Alignment.CenterVertically
                    ) {
                        Icon(
                            imageVector = Icons.Default.PhotoCamera,
                            contentDescription = "KI-Foto-Scan",
                            tint = Gold,
                            modifier = Modifier.size(15.dp)
                        )
                        Spacer(modifier = Modifier.width(5.dp))
                        Text(
                            text = "✨ KI-Foto-Scan",
                            color = Gold,
                            fontSize = 12.sp,
                            fontWeight = FontWeight.Bold
                        )
                    }

                    // Datei Button
                    Row(
                        modifier = Modifier
                            .clip(RoundedCornerShape(8.dp))
                            .background(AppTheme.colors.glassSurface)
                            .border(1.dp, AppTheme.colors.glassBorder, RoundedCornerShape(8.dp))
                            .clickable { filePickerLauncher.launch("*/*") }
                            .padding(horizontal = 10.dp, vertical = 7.dp),
                        verticalAlignment = Alignment.CenterVertically
                    ) {
                        Icon(
                            imageVector = Icons.Default.AttachFile,
                            contentDescription = "Anhang",
                            tint = AppTheme.colors.textMuted,
                            modifier = Modifier.size(15.dp)
                        )
                        Spacer(modifier = Modifier.width(4.dp))
                        Text("Datei", color = AppTheme.colors.textPrimary, fontSize = 12.sp, fontWeight = FontWeight.Medium)
                    }
                }

                // Business Switch (kompakt)
                Row(
                    verticalAlignment = Alignment.CenterVertically,
                    modifier = Modifier.clickable { isBusiness = !isBusiness }
                ) {
                    Text("Gewerblich", color = AppTheme.colors.textPrimary, fontSize = 12.sp)
                    Spacer(modifier = Modifier.width(4.dp))
                    Switch(
                        checked = isBusiness,
                        onCheckedChange = { isBusiness = it },
                        modifier = Modifier.scale(0.85f),
                        colors = SwitchDefaults.colors(
                            checkedThumbColor = Gold,
                            checkedTrackColor = Color(0x33C5A059)
                        )
                    )
                }
            }

            // Ausgewählte Datei Badge
            if (pickedFileName != null) {
                Row(
                    modifier = Modifier
                        .fillMaxWidth()
                        .background(Color(0x11C5A059), RoundedCornerShape(6.dp))
                        .padding(horizontal = 10.dp, vertical = 6.dp),
                    verticalAlignment = Alignment.CenterVertically,
                    horizontalArrangement = Arrangement.SpaceBetween
                ) {
                    Text(
                        text = "📎 $pickedFileName",
                        color = Gold,
                        fontSize = 11.sp,
                        maxLines = 1,
                        overflow = TextOverflow.Ellipsis,
                        modifier = Modifier.weight(1f)
                    )
                    Text(
                        text = "Löschen",
                        color = Color.Red,
                        fontSize = 11.sp,
                        fontWeight = FontWeight.Bold,
                        modifier = Modifier.clickable {
                            pickedFileName = null
                            pickedFileBytes = null
                            pickedFileMime = null
                            pickedFileUri = null
                            aiScanResultNote = null
                        }
                    )
                }
            }

            // AI Scanning indicator banner
            if (isAiScanning) {
                Card(
                    colors = CardDefaults.cardColors(containerColor = Color(0x33C5A059)),
                    border = BorderStroke(1.dp, Gold),
                    modifier = Modifier.fillMaxWidth()
                ) {
                    Row(
                        modifier = Modifier.padding(10.dp),
                        verticalAlignment = Alignment.CenterVertically,
                        horizontalArrangement = Arrangement.spacedBy(10.dp)
                    ) {
                        CircularProgressIndicator(
                            color = Gold,
                            modifier = Modifier.size(18.dp),
                            strokeWidth = 2.dp
                        )
                        Text(
                            text = "🤖 Gemini Vision scannt Beleg...",
                            color = Gold,
                            fontWeight = FontWeight.Bold,
                            fontSize = 12.sp
                        )
                    }
                }
            }

            // AI Scan Result Note banner
            if (aiScanResultNote != null && !isAiScanning) {
                Card(
                    colors = CardDefaults.cardColors(containerColor = Color(0x2210B981)),
                    border = BorderStroke(1.dp, Emerald500),
                    modifier = Modifier.fillMaxWidth()
                ) {
                    Row(
                        modifier = Modifier.padding(8.dp),
                        verticalAlignment = Alignment.CenterVertically,
                        horizontalArrangement = Arrangement.spacedBy(6.dp)
                    ) {
                        Icon(
                            imageVector = Icons.Default.Check,
                            contentDescription = null,
                            tint = Emerald500,
                            modifier = Modifier.size(15.dp)
                        )
                        Text(
                            text = aiScanResultNote ?: "",
                            color = AppTheme.colors.textPrimary,
                            fontSize = 11.sp,
                            fontWeight = FontWeight.Medium
                        )
                    }
                }
            }

            // Zeile 4: Aktionen (Abbrechen / Eintragen)
            Row(
                modifier = Modifier
                    .fillMaxWidth()
                    .padding(top = 4.dp),
                horizontalArrangement = Arrangement.End,
                verticalAlignment = Alignment.CenterVertically
            ) {
                TextButton(onClick = onDismiss, enabled = !isUploading && !isAiScanning) {
                    Text("Abbrechen", color = AppTheme.colors.textMuted, fontSize = 13.sp)
                }
                Spacer(modifier = Modifier.width(6.dp))
                Button(
                    onClick = {
                        val amount = amountText.replace(',', '.').toDoubleOrNull() ?: 0.0
                        onSubmit(
                            title,
                            amount,
                            selectedCategory.ifBlank { null },
                            isBusiness,
                            executionDate,
                            location.ifBlank { null },
                            pickedFileBytes,
                            pickedFileName,
                            pickedFileMime
                        )
                    },
                    enabled = !isUploading && !isAiScanning && title.isNotBlank() && amountText.replace(',', '.').toDoubleOrNull() != null,
                    colors = ButtonDefaults.buttonColors(containerColor = Gold, contentColor = SpaceBlack),
                    shape = RoundedCornerShape(10.dp)
                ) {
                    if (isUploading || isAiScanning) {
                        CircularProgressIndicator(color = SpaceBlack, modifier = Modifier.size(16.dp))
                    } else {
                        Text("Eintragen", fontWeight = FontWeight.Bold, fontSize = 13.sp)
                    }
                }
            }
        }
    }

    if (showCard) {
        Card(
            modifier = Modifier
                .fillMaxWidth()
                .padding(vertical = 4.dp),
            colors = CardDefaults.cardColors(containerColor = AppTheme.colors.cardBackground),
            border = BorderStroke(1.dp, AppTheme.colors.cardBorder),
            shape = RoundedCornerShape(16.dp)
        ) {
            Box(modifier = Modifier.padding(14.dp)) {
                formBody()
            }
        }
    } else {
        Box(modifier = Modifier.padding(horizontal = 14.dp, vertical = 10.dp)) {
            formBody()
        }
    }
}

private fun formatDateString(dateStr: String?): String {
    if (dateStr.isNullOrBlank()) return ""
    try {
        if (dateStr.contains("T")) {
            val isoParser = java.text.SimpleDateFormat("yyyy-MM-dd'T'HH:mm:ss", java.util.Locale.US).apply {
                timeZone = java.util.TimeZone.getTimeZone("UTC")
            }
            val date = isoParser.parse(dateStr)
            if (date != null) {
                val formatter = java.text.SimpleDateFormat("dd.MM.yyyy", java.util.Locale.GERMANY).apply {
                    timeZone = java.util.TimeZone.getDefault()
                }
                return formatter.format(date)
            }
        }
        val simpleParser = java.text.SimpleDateFormat("yyyy-MM-dd", java.util.Locale.US)
        val date = simpleParser.parse(dateStr)
        if (date != null) {
            val formatter = java.text.SimpleDateFormat("dd.MM.yyyy", java.util.Locale.GERMANY)
            return formatter.format(date)
        }
    } catch (e: Exception) {
        e.printStackTrace()
    }
    return dateStr
}
