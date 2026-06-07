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
import de.meinseelenfunke.app.ui.theme.Gold
import de.meinseelenfunke.app.ui.theme.Copper
import de.meinseelenfunke.app.ui.theme.SpaceBlue
import de.meinseelenfunke.app.ui.theme.SpaceBlack
import de.meinseelenfunke.app.ui.theme.GlassWhite10
import de.meinseelenfunke.app.ui.theme.GlassWhite20
import de.meinseelenfunke.app.ui.theme.Slate400
import de.meinseelenfunke.app.ui.theme.Slate50
import de.meinseelenfunke.app.ui.theme.Slate900
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
                    colors = listOf(SpaceBlack, Slate900)
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
                        color = Slate400
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
                        modifier = Modifier.weight(1f),
                        colors = CardDefaults.cardColors(containerColor = GlassWhite10)
                    ) {
                        Column(modifier = Modifier.padding(12.dp)) {
                            Text("Guthaben", fontSize = 11.sp, color = Slate400)
                            Text(
                                String.format(Locale.GERMANY, "%,.2f €", it.available),
                                fontSize = 18.sp,
                                fontWeight = FontWeight.Bold,
                                color = Gold
                            )
                        }
                    }
                    Card(
                        modifier = Modifier.weight(1f),
                        colors = CardDefaults.cardColors(containerColor = GlassWhite10)
                    ) {
                        Column(modifier = Modifier.padding(12.dp)) {
                            Text("Variable Ausgaben", fontSize = 11.sp, color = Slate400)
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
                    onDismiss = { showQuickEntryDialog = false },
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
                contentColor = Slate50,
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
                    text = { Text("Variable Ausgaben", fontWeight = FontWeight.SemiBold) }
                )
                Tab(
                    selected = pagerState.currentPage == 1,
                    onClick = {
                        scope.launch {
                            pagerState.animateScrollToPage(1)
                        }
                    },
                    text = { Text("Fixkosten Verträge", fontWeight = FontWeight.SemiBold) }
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
            Text("Keine variablen Ausgaben eingetragen.", color = Slate400, textAlign = TextAlign.Center)
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
                    modifier = Modifier.fillMaxWidth(),
                    colors = CardDefaults.cardColors(containerColor = GlassWhite10)
                ) {
                    if (isEditing) {
                        Column(modifier = Modifier.padding(16.dp), verticalArrangement = Arrangement.spacedBy(12.dp)) {
                            Text("Eintrag bearbeiten", color = Gold, fontSize = 12.sp, fontWeight = FontWeight.Bold)

                            OutlinedTextField(
                                value = editTitle,
                                onValueChange = { editTitle = it },
                                label = { Text("Titel") },
                                modifier = Modifier.fillMaxWidth(),
                                colors = OutlinedTextFieldDefaults.colors(focusedBorderColor = Gold, unfocusedTextColor = Slate50)
                            )

                            OutlinedTextField(
                                value = editAmountText,
                                onValueChange = { editAmountText = it },
                                label = { Text("Betrag (€)") },
                                modifier = Modifier.fillMaxWidth(),
                                keyboardOptions = KeyboardOptions(keyboardType = KeyboardType.Number),
                                colors = OutlinedTextFieldDefaults.colors(focusedBorderColor = Gold, unfocusedTextColor = Slate50)
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
                                        unfocusedTextColor = Slate50,
                                        disabledBorderColor = Slate400,
                                        disabledLabelColor = Slate400,
                                        disabledTextColor = Slate50
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
                                    colors = OutlinedTextFieldDefaults.colors(focusedBorderColor = Gold, unfocusedTextColor = Slate50),
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
                                                    color = if (isSelected) SpaceBlack else Slate50,
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
                                colors = OutlinedTextFieldDefaults.colors(focusedBorderColor = Gold, unfocusedTextColor = Slate50)
                            )

                            Row(
                                modifier = Modifier.fillMaxWidth(),
                                horizontalArrangement = Arrangement.SpaceBetween,
                                verticalAlignment = Alignment.CenterVertically
                            ) {
                                Text("Gewerbliche Ausgabe?", color = Slate50, fontSize = 14.sp)
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
                                    Text("Abbrechen", color = Slate400)
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
                                        color = Slate50
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
                                    color = Slate400,
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
                                            tint = Slate400,
                                            modifier = Modifier.size(10.dp)
                                        )
                                        Spacer(modifier = Modifier.width(4.dp))
                                        Text(item.location, fontSize = 10.sp, color = Slate400)
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
            Text("Keine Fixkosten-Gruppen vorhanden.", color = Slate400, textAlign = TextAlign.Center)
        }
    } else {
        Column(
            modifier = Modifier
                .fillMaxSize()
                .verticalScroll(rememberScrollState())
                .padding(16.dp),
            verticalArrangement = Arrangement.spacedBy(16.dp)
        ) {
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
                                modifier = Modifier.fillMaxWidth(),
                                colors = CardDefaults.cardColors(containerColor = GlassWhite10)
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
                                            colors = OutlinedTextFieldDefaults.colors(focusedBorderColor = Gold, unfocusedTextColor = Slate50)
                                        )

                                        OutlinedTextField(
                                            value = amountInput,
                                            onValueChange = { amountInput = it },
                                            label = { Text("Betrag (€)") },
                                            modifier = Modifier.fillMaxWidth(),
                                            keyboardOptions = KeyboardOptions(keyboardType = KeyboardType.Number),
                                            colors = OutlinedTextFieldDefaults.colors(focusedBorderColor = Gold, unfocusedTextColor = Slate50)
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
                                                colors = OutlinedTextFieldDefaults.colors(focusedBorderColor = Gold, unfocusedTextColor = Slate50),
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
                                                                color = if (isSelected) SpaceBlack else Slate50,
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
                                                    unfocusedTextColor = Slate50,
                                                    disabledBorderColor = Slate400,
                                                    disabledLabelColor = Slate400,
                                                    disabledTextColor = Slate50
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
                                            colors = OutlinedTextFieldDefaults.colors(focusedBorderColor = Gold, unfocusedTextColor = Slate50)
                                        )

                                        Row(
                                            modifier = Modifier.fillMaxWidth(),
                                            horizontalArrangement = Arrangement.SpaceBetween,
                                            verticalAlignment = Alignment.CenterVertically
                                        ) {
                                            Text("Gewerblich?", color = Slate50, fontSize = 14.sp)
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
                                                Text("Abbrechen", color = Slate400)
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
                                                    color = Slate50
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
                                                color = Slate400,
                                                modifier = Modifier.padding(top = 2.dp)
                                            )
                                            if (!cost.description.isNullOrBlank()) {
                                                Text(
                                                    text = cost.description,
                                                    fontSize = 10.sp,
                                                    color = Slate400,
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
                                modifier = Modifier.fillMaxWidth().border(1.dp, Gold.copy(alpha = 0.5f), RoundedCornerShape(12.dp)),
                                colors = CardDefaults.cardColors(containerColor = GlassWhite10)
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
                                        colors = OutlinedTextFieldDefaults.colors(focusedBorderColor = Gold, unfocusedTextColor = Slate50)
                                    )

                                    OutlinedTextField(
                                        value = amountInput,
                                        onValueChange = { amountInput = it },
                                        label = { Text("Betrag (€)") },
                                        modifier = Modifier.fillMaxWidth(),
                                        keyboardOptions = KeyboardOptions(keyboardType = KeyboardType.Number),
                                        colors = OutlinedTextFieldDefaults.colors(focusedBorderColor = Gold, unfocusedTextColor = Slate50)
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
                                            colors = OutlinedTextFieldDefaults.colors(focusedBorderColor = Gold, unfocusedTextColor = Slate50),
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
                                                            color = if (isSelected) SpaceBlack else Slate50,
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
                                                unfocusedTextColor = Slate50,
                                                disabledBorderColor = Slate400,
                                                disabledLabelColor = Slate400,
                                                disabledTextColor = Slate50
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
                                        colors = OutlinedTextFieldDefaults.colors(focusedBorderColor = Gold, unfocusedTextColor = Slate50)
                                    )

                                    Row(
                                        modifier = Modifier.fillMaxWidth(),
                                        horizontalArrangement = Arrangement.SpaceBetween,
                                        verticalAlignment = Alignment.CenterVertically
                                    ) {
                                        Text("Gewerblich?", color = Slate50, fontSize = 14.sp)
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
                                            Text("Abbrechen", color = Slate400)
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
                                    .border(1.dp, GlassWhite10, RoundedCornerShape(8.dp))
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
    onDismiss: () -> Unit,
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
    
    // File upload details
    var pickedFileUri by remember { mutableStateOf<Uri?>(null) }
    var pickedFileName by remember { mutableStateOf<String?>(null) }
    var pickedFileBytes by remember { mutableStateOf<ByteArray?>(null) }
    var pickedFileMime by remember { mutableStateOf<String?>(null) }

    val today = java.time.LocalDate.now().toString()
    var executionDate by remember { mutableStateOf(today) }

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
            
            // Extract filename
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

            // Extract bytes
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
                    android.util.Log.d("FinanceScreen", "Compressed image picked, size: ${compressedBytes.size}")
                } else {
                    contentResolver.openInputStream(it)?.use { input ->
                        pickedFileBytes = input.readBytes()
                    }
                    android.util.Log.d("FinanceScreen", "Raw file bytes read, size: ${pickedFileBytes?.size}")
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
                } catch (e: Exception) {
                    e.printStackTrace()
                }
            }
        }
    }

    var dropdownExpanded by remember { mutableStateOf(false) }

    Card(
        modifier = Modifier
            .fillMaxWidth()
            .heightIn(max = 480.dp)
            .padding(16.dp),
        colors = CardDefaults.cardColors(containerColor = Slate900),
        border = BorderStroke(1.dp, GlassWhite20)
    ) {
        Column(
            modifier = Modifier
                .fillMaxWidth()
                .padding(16.dp)
                .verticalScroll(rememberScrollState()),
            verticalArrangement = Arrangement.spacedBy(12.dp)
        ) {
            Text("Schnelleintrag hinzufügen", color = Slate50, fontSize = 16.sp, fontWeight = FontWeight.Bold)

            OutlinedTextField(
                value = title,
                onValueChange = { title = it },
                label = { Text("Titel") },
                modifier = Modifier.fillMaxWidth(),
                colors = OutlinedTextFieldDefaults.colors(focusedBorderColor = Gold, unfocusedTextColor = Slate50)
            )

            Row(
                modifier = Modifier.fillMaxWidth(),
                horizontalArrangement = Arrangement.spacedBy(8.dp),
                verticalAlignment = Alignment.CenterVertically
            ) {
                OutlinedTextField(
                    value = amountText,
                    onValueChange = { amountText = it },
                    label = { Text("Betrag (€)") },
                    modifier = Modifier.weight(1f),
                    keyboardOptions = KeyboardOptions(keyboardType = KeyboardType.Number),
                    colors = OutlinedTextFieldDefaults.colors(focusedBorderColor = Gold, unfocusedTextColor = Slate50)
                )

                // Date Picker field
                Box(
                    modifier = Modifier
                        .weight(1f)
                        .clickable { datePickerDialog.show() }
                ) {
                    OutlinedTextField(
                        readOnly = true,
                        value = executionDate,
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
                            unfocusedTextColor = Slate50,
                            disabledBorderColor = Slate400,
                            disabledLabelColor = Slate400,
                            disabledTextColor = Slate50
                        ),
                        enabled = false
                    )
                    // Transparent overlay to capture clicks
                    Box(
                        modifier = Modifier
                            .matchParentSize()
                            .background(Color.Transparent)
                            .clickable { datePickerDialog.show() }
                    )
                }
            }

            // Category Dropdown (Editable Combobox)
            val filteredCategories = remember(selectedCategory, categories) {
                categories.filter { it.contains(selectedCategory, ignoreCase = true) }
            }
            ExposedDropdownMenuBox(
                expanded = dropdownExpanded && filteredCategories.isNotEmpty(),
                onExpandedChange = { dropdownExpanded = it }
            ) {
                OutlinedTextField(
                    readOnly = false,
                    value = selectedCategory,
                    onValueChange = { 
                        selectedCategory = it
                        dropdownExpanded = true
                    },
                    label = { Text("Kategorie") },
                    trailingIcon = { ExposedDropdownMenuDefaults.TrailingIcon(expanded = dropdownExpanded) },
                    colors = OutlinedTextFieldDefaults.colors(focusedBorderColor = Gold, unfocusedTextColor = Slate50),
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
                                    color = if (isSelected) SpaceBlack else Slate50,
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

            // Business Switch
            Row(
                modifier = Modifier.fillMaxWidth(),
                horizontalArrangement = Arrangement.SpaceBetween,
                verticalAlignment = Alignment.CenterVertically
            ) {
                Text("Gewerbliche Ausgabe?", color = Slate50, fontSize = 14.sp)
                Switch(
                    checked = isBusiness,
                    onCheckedChange = { isBusiness = it },
                    colors = SwitchDefaults.colors(
                        checkedThumbColor = Gold,
                        checkedTrackColor = Color(0x33C5A059)
                    )
                )
            }

            OutlinedTextField(
                value = location,
                onValueChange = { location = it },
                label = { Text("Ort (optional)") },
                modifier = Modifier.fillMaxWidth(),
                colors = OutlinedTextFieldDefaults.colors(focusedBorderColor = Gold, unfocusedTextColor = Slate50)
            )

            // Receipt File attachment indicator
            Text("Beleg hinzufügen", color = Slate400, fontSize = 12.sp, modifier = Modifier.padding(top = 4.dp))
            Row(
                modifier = Modifier.fillMaxWidth(),
                horizontalArrangement = Arrangement.spacedBy(8.dp)
            ) {
                // File picker button
                Row(
                    modifier = Modifier
                        .weight(1f)
                        .border(1.dp, GlassWhite20, RoundedCornerShape(8.dp))
                        .clickable { filePickerLauncher.launch("*/*") }
                        .padding(10.dp),
                    verticalAlignment = Alignment.CenterVertically
                ) {
                    Icon(
                        imageVector = Icons.Default.AttachFile,
                        contentDescription = "Anhang",
                        tint = Gold,
                        modifier = Modifier.size(16.dp)
                    )
                    Spacer(modifier = Modifier.width(8.dp))
                    Text("Datei", color = Slate50, fontSize = 12.sp, fontWeight = FontWeight.Bold)
                }

                // Camera launcher button
                Row(
                    modifier = Modifier
                        .weight(1f)
                        .border(1.dp, GlassWhite20, RoundedCornerShape(8.dp))
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
                        .padding(10.dp),
                    verticalAlignment = Alignment.CenterVertically
                ) {
                    Icon(
                        imageVector = Icons.Default.PhotoCamera,
                        contentDescription = "Kamera",
                        tint = Gold,
                        modifier = Modifier.size(16.dp)
                    )
                    Spacer(modifier = Modifier.width(8.dp))
                    Text("Kamera", color = Slate50, fontSize = 12.sp, fontWeight = FontWeight.Bold)
                }
            }

            if (pickedFileName != null) {
                Row(
                    modifier = Modifier
                        .fillMaxWidth()
                        .background(Color(0x11C5A059), RoundedCornerShape(4.dp))
                        .padding(8.dp),
                    verticalAlignment = Alignment.CenterVertically,
                    horizontalArrangement = Arrangement.SpaceBetween
                ) {
                    Text(
                        text = "Ausgewählt: $pickedFileName",
                        color = Gold,
                        fontSize = 11.sp,
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
                        }
                    )
                }
            }

            Row(
                modifier = Modifier.fillMaxWidth(),
                horizontalArrangement = Arrangement.End,
                verticalAlignment = Alignment.CenterVertically
            ) {
                TextButton(onClick = onDismiss, enabled = !isUploading) {
                    Text("Abbrechen", color = Slate400)
                }
                Spacer(modifier = Modifier.width(8.dp))
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
                    enabled = !isUploading && title.isNotBlank() && amountText.replace(',', '.').toDoubleOrNull() != null,
                    colors = ButtonDefaults.buttonColors(containerColor = Gold, contentColor = SpaceBlack)
                ) {
                    if (isUploading) {
                        CircularProgressIndicator(color = SpaceBlack, modifier = Modifier.size(16.dp))
                    } else {
                        Text("Speichern")
                    }
                }
            }
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
