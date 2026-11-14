package de.meinseelenfunke.app.ui.screens

import de.meinseelenfunke.app.di.ServiceLocator

import androidx.compose.animation.AnimatedVisibility
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
import androidx.compose.foundation.layout.fillMaxHeight
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.offset
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.PaddingValues
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.layout.width
import androidx.compose.foundation.layout.aspectRatio
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.foundation.verticalScroll
import androidx.compose.foundation.text.ClickableText
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.Add
import androidx.compose.material.icons.filled.Alarm
import androidx.compose.material.icons.filled.ArrowBack
import androidx.compose.material.icons.filled.Bookmark
import androidx.compose.material.icons.filled.CalendarToday
import androidx.compose.material.icons.filled.Campaign
import androidx.compose.material.icons.filled.CheckCircle
import androidx.compose.material.icons.filled.ChevronLeft
import androidx.compose.material.icons.filled.ChevronRight
import androidx.compose.material.icons.filled.Delete
import androidx.compose.material.icons.filled.Edit
import androidx.compose.material.icons.filled.Email
import androidx.compose.material.icons.filled.ExpandLess
import androidx.compose.material.icons.filled.ExpandMore
import androidx.compose.material.icons.filled.Flag
import androidx.compose.material.icons.filled.KeyboardArrowRight
import androidx.compose.material.icons.filled.List
import androidx.compose.material.icons.filled.MusicNote
import androidx.compose.material.icons.filled.PlaylistAddCheck
import androidx.compose.material.icons.filled.Schedule
import androidx.compose.material.icons.filled.ShoppingCart
import androidx.compose.material.icons.filled.Star
import androidx.compose.material.icons.filled.Close
import androidx.compose.material.icons.filled.AccountCircle
import androidx.compose.material.icons.filled.Settings
import androidx.compose.material.icons.filled.Favorite
import androidx.compose.material.icons.filled.Bolt
import androidx.compose.material.icons.filled.Home
import androidx.compose.material.icons.filled.Work
import androidx.compose.material.icons.filled.ShoppingBag
import androidx.compose.material.icons.filled.EmojiEvents
import androidx.compose.material.icons.filled.WbSunny
import androidx.compose.material.icons.filled.NightsStay
import androidx.compose.material.icons.filled.Build
import androidx.compose.material.icons.filled.RocketLaunch
import androidx.compose.material.icons.filled.LocalOffer
import androidx.compose.material.icons.filled.AttachFile
import androidx.compose.material.icons.filled.Description
import androidx.compose.material.icons.filled.Image
import androidx.compose.material.icons.filled.Feed
import androidx.compose.material.icons.filled.GridOn
import androidx.compose.material.icons.filled.FolderOpen
import androidx.compose.material.icons.filled.FilePresent
import androidx.compose.material.icons.filled.PushPin
import androidx.compose.ui.res.painterResource
import android.content.Intent
import android.net.Uri
import androidx.compose.animation.AnimatedVisibility
import androidx.compose.animation.fadeIn
import androidx.compose.animation.fadeOut
import androidx.compose.animation.expandVertically
import androidx.compose.animation.shrinkVertically
import androidx.compose.foundation.BorderStroke
import androidx.compose.foundation.lazy.LazyRow
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.ExperimentalFoundationApi
import androidx.compose.foundation.pager.HorizontalPager
import androidx.compose.foundation.pager.rememberPagerState
import androidx.compose.foundation.lazy.items
import androidx.compose.material3.Surface
import androidx.compose.material3.AlertDialog
import androidx.compose.material3.Button
import androidx.compose.material3.ButtonDefaults
import androidx.compose.material3.Card
import androidx.compose.material3.CardDefaults
import androidx.compose.material3.Checkbox
import androidx.compose.material3.CheckboxDefaults
import androidx.compose.material3.CircularProgressIndicator
import androidx.compose.material3.DropdownMenuItem
import androidx.compose.material3.ExperimentalMaterial3Api
import androidx.compose.material3.ExposedDropdownMenuBox
import androidx.compose.material3.ExposedDropdownMenuDefaults
import androidx.compose.material3.FloatingActionButton
import androidx.compose.material3.Icon
import androidx.compose.material3.IconButton
import androidx.compose.material3.OutlinedTextField
import androidx.compose.material3.OutlinedTextFieldDefaults
import androidx.compose.material3.ScrollableTabRow
import androidx.compose.material3.TabRow
import androidx.compose.material3.Tab
import androidx.compose.material3.TabRowDefaults
import androidx.compose.material3.TabRowDefaults.tabIndicatorOffset
import androidx.compose.material3.Text
import androidx.compose.material3.TextButton
import androidx.compose.runtime.Composable
import androidx.activity.compose.rememberLauncherForActivityResult
import androidx.activity.result.contract.ActivityResultContracts
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.collectAsState
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableIntStateOf
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.rememberCoroutineScope
import androidx.compose.runtime.setValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.draw.scale
import androidx.compose.ui.graphics.Brush
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.graphics.vector.ImageVector
import androidx.compose.ui.focus.FocusRequester
import androidx.compose.ui.focus.focusRequester
import androidx.compose.ui.platform.LocalSoftwareKeyboardController
import androidx.compose.ui.platform.LocalUriHandler
import androidx.compose.ui.text.SpanStyle
import androidx.compose.ui.text.TextStyle
import androidx.compose.ui.text.buildAnnotatedString
import androidx.compose.ui.text.withStyle
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.style.TextAlign
import androidx.compose.ui.text.style.TextDecoration
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import androidx.lifecycle.viewmodel.compose.viewModel
import de.meinseelenfunke.app.data.api.CalendarEvent
import de.meinseelenfunke.app.data.api.ManagementDayRoutine
import de.meinseelenfunke.app.data.api.ManagementShoppingItem
import de.meinseelenfunke.app.data.api.ManagementTask
import de.meinseelenfunke.app.data.api.ManagementTaskList
import de.meinseelenfunke.app.ui.theme.AppTheme
import de.meinseelenfunke.app.ui.theme.Gold
import de.meinseelenfunke.app.ui.theme.Copper
import de.meinseelenfunke.app.ui.theme.SpaceBlue
import de.meinseelenfunke.app.ui.theme.SpaceBlack
import java.text.SimpleDateFormat
import java.util.Calendar
import java.util.Date
import java.util.Locale
import java.util.TimeZone
import kotlinx.coroutines.launch

@OptIn(ExperimentalFoundationApi::class)
@Composable
fun OrganizerScreen(
    initialTab: Int = 0,
    navTrigger: Int = 0,
    onTabScrollCompleted: () -> Unit = {},
    isPageVisible: Boolean = true,
    viewModel: OrganizerViewModel = viewModel(),
    emailViewModel: EmailViewModel = viewModel()
) {
    val uiState by viewModel.uiState.collectAsState()
    val taskLists by viewModel.taskLists.collectAsState()
    val tasks by viewModel.tasks.collectAsState()
    val routines by viewModel.routines.collectAsState()
    val shoppingItems by viewModel.shoppingItems.collectAsState()
    val calendarEvents by viewModel.calendarEvents.collectAsState()
    val isProcessingFile by viewModel.isProcessingFile.collectAsState()

    val scope = rememberCoroutineScope()
    val context = androidx.compose.ui.platform.LocalContext.current
    val pagerState = rememberPagerState(
        initialPage = initialTab.coerceIn(0, 6),
        pageCount = { 7 }
    )
    var isFirstLoad by remember { mutableStateOf(true) }

    LaunchedEffect(pagerState.currentPage) {
        if (isFirstLoad) {
            isFirstLoad = false
        }
    }

    LaunchedEffect(initialTab, navTrigger) {
        val target = initialTab.coerceIn(0, 6)
        if (pagerState.currentPage != target) {
            pagerState.scrollToPage(target)
        }
        onTabScrollCompleted()
    }

    LaunchedEffect(isPageVisible) {
        while (isPageVisible) {
            viewModel.loadAllOrganizerData(showLoading = false)
            kotlinx.coroutines.delay(5000L)
        }
    }
    val tabTitles = listOf("Aufgaben", "Kalender", "Routine", "Einkauf", "E-Mail", "Musik", "Wecker")
    val tabIcons = listOf(
        Icons.Default.CheckCircle,
        Icons.Default.CalendarToday,
        Icons.Default.Schedule,
        Icons.Default.ShoppingCart,
        Icons.Default.Email,
        Icons.Default.MusicNote,
        Icons.Default.Alarm
    )

    Box(
        modifier = Modifier
            .fillMaxSize()
            .background(
                Brush.verticalGradient(
                    colors = AppTheme.colors.bgGradient
                )
            )
    ) {
        if (isProcessingFile) {
            AlertDialog(
                onDismissRequest = {},
                confirmButton = {},
                dismissButton = {},
                title = {
                    Column(
                        modifier = Modifier.fillMaxWidth(),
                        horizontalAlignment = Alignment.CenterHorizontally,
                        verticalArrangement = Arrangement.spacedBy(16.dp)
                    ) {
                        CircularProgressIndicator(color = Gold)
                        Text(
                            text = "Verarbeite Kalenderdatei...",
                            color = AppTheme.colors.textPrimary,
                            fontSize = 16.sp,
                            fontWeight = FontWeight.Bold
                        )
                        Text(
                            text = "Bitte schließe die App nicht.",
                            color = AppTheme.colors.textMuted,
                            fontSize = 12.sp
                        )
                    }
                },
                containerColor = AppTheme.colors.cardBackground,
                shape = RoundedCornerShape(16.dp)
            )
        }
        Column(modifier = Modifier.fillMaxSize()) {
            // Title Header
            Column(modifier = Modifier.padding(horizontal = 16.dp, vertical = 16.dp)) {
                Row(
                    modifier = Modifier.fillMaxWidth(),
                    horizontalArrangement = Arrangement.SpaceBetween,
                    verticalAlignment = Alignment.CenterVertically
                ) {
                    Text(
                        text = "ORGANIZER",
                        fontSize = 22.sp,
                        fontWeight = FontWeight.Bold,
                        color = Gold,
                        letterSpacing = 2.sp
                    )
                    Text(
                        text = tabTitles.getOrElse(pagerState.currentPage) { "Aufgaben" },
                        fontSize = 12.sp,
                        fontWeight = FontWeight.Bold,
                        color = Gold
                    )
                }
                Text(
                    text = "Behalte deinen Tag im Blick",
                    fontSize = 12.sp,
                    color = AppTheme.colors.textMuted
                )
            }

            // Compact Icon Tab bar (7 Tabs: Aufgaben, Kalender, Routine, Einkauf, E-Mail, Musik, Wecker)
            TabRow(
                selectedTabIndex = pagerState.currentPage,
                containerColor = Color.Transparent,
                contentColor = AppTheme.colors.textPrimary,
                indicator = { tabPositions ->
                    if (pagerState.currentPage < tabPositions.size) {
                        TabRowDefaults.SecondaryIndicator(
                            Modifier.tabIndicatorOffset(tabPositions[pagerState.currentPage]),
                            color = Gold
                        )
                    }
                }
            ) {
                tabTitles.forEachIndexed { index, title ->
                    val isSelected = pagerState.currentPage == index
                    Tab(
                        selected = isSelected,
                        onClick = {
                            scope.launch {
                                if (kotlin.math.abs(pagerState.currentPage - index) > 1) {
                                    pagerState.scrollToPage(index)
                                } else {
                                    pagerState.animateScrollToPage(index)
                                }
                            }
                        },
                        selectedContentColor = Gold,
                        unselectedContentColor = AppTheme.colors.textMuted,
                        icon = {
                            Icon(
                                imageVector = tabIcons[index],
                                contentDescription = title,
                                modifier = Modifier.size(20.dp),
                                tint = if (isSelected) Gold else AppTheme.colors.textMuted
                            )
                        }
                    )
                }
            }

            // Tab body (always render HorizontalPager so Musik & cached tabs work immediately offline)
            HorizontalPager(
                state = pagerState,
                modifier = Modifier.weight(1f)
            ) { page ->
                Box(
                    modifier = Modifier
                        .fillMaxSize()
                        .padding(16.dp)
                ) {
                    if (uiState is OrganizerUiState.Loading && page in 0..3) {
                        Box(modifier = Modifier.fillMaxSize(), contentAlignment = Alignment.Center) {
                            CircularProgressIndicator(color = Gold)
                        }
                    } else {
                        when (page) {
                            0 -> Column(
                                modifier = Modifier
                                    .fillMaxSize()
                                    .verticalScroll(rememberScrollState())
                            ) {
                                TasksTabContent(
                                    taskLists = taskLists,
                                    tasks = tasks,
                                    onAddTask = { listId, title, priority, relevantFrom ->
                                        viewModel.addTask(listId, title, priority, relevantFrom)
                                    },
                                    onToggleTask = { viewModel.toggleTask(it) },
                                    onDeleteTask = { viewModel.deleteTask(it) },
                                    onAddSubtask = { parentId, title -> viewModel.addSubtask(parentId, title) },
                                    onDeleteTaskList = { viewModel.deleteTaskList(it) },
                                    onAddTaskList = { name -> viewModel.addTaskList(name) },
                                    onUpdateTaskList = { id, name -> viewModel.updateTaskList(id, name) },
                                    onUpdateTask = { id, title, priority, completed, relFrom, aiPlan -> viewModel.updateTask(id, title, priority, completed, relFrom, aiPlan) },
                                    onUploadTaskFile = { id, bytes, name, mime -> viewModel.uploadTaskFile(id, bytes, name, mime) },
                                    onDeleteTaskFile = { id, path -> viewModel.deleteTaskFile(id, path) }
                                )
                                Spacer(modifier = Modifier.height(80.dp))
                            }
                            1 -> CalendarTabContent(
                                 events = calendarEvents,
                                 onAddEvent = { title, start, end, isAllDay, category, desc, recurrence, reminderMinutes, priority, sendEmail ->
                                     viewModel.addCalendarEvent(title, start, end, isAllDay, category, desc, recurrence, reminderMinutes, priority, sendEmail)
                                 },
                                 onUpdateEvent = { id, title, start, end, isAllDay, category, desc, recurrence, reminderMinutes, priority ->
                                     viewModel.updateCalendarEvent(id, title, start, end, isAllDay, category, desc, recurrence, reminderMinutes, priority)
                                 },
                                 onDeleteEvent = { viewModel.deleteCalendarEvent(it) },
                                 onImportIcs = { uri ->
                                     viewModel.importCalendarEventsFromIcs(
                                         context = context,
                                         uri = uri,
                                         onSuccess = {
                                             android.widget.Toast.makeText(context, "Kalender erfolgreich importiert!", android.widget.Toast.LENGTH_SHORT).show()
                                         },
                                         onFailure = { error ->
                                             android.widget.Toast.makeText(context, "Import fehlgeschlagen: ${error.localizedMessage}", android.widget.Toast.LENGTH_LONG).show()
                                         }
                                     )
                                },
                                onExportIcs = {
                                     viewModel.exportCalendarEventsToIcs(
                                         context = context,
                                         onSuccess = { uri ->
                                             val intent = android.content.Intent(android.content.Intent.ACTION_SEND).apply {
                                                 type = "text/calendar"
                                                 putExtra(android.content.Intent.EXTRA_STREAM, uri)
                                                 addFlags(android.content.Intent.FLAG_GRANT_READ_URI_PERMISSION)
                                             }
                                             context.startActivity(android.content.Intent.createChooser(intent, "Kalender exportieren"))
                                         },
                                         onFailure = { error ->
                                             android.widget.Toast.makeText(context, "Export fehlgeschlagen: ${error.localizedMessage}", android.widget.Toast.LENGTH_LONG).show()
                                         }
                                     )
                                }
                            )
                            2 -> Column(
                                modifier = Modifier
                                    .fillMaxSize()
                                    .verticalScroll(rememberScrollState())
                            ) {
                                RoutinesTabContent(
                                    routines = routines,
                                    onCreateRoutine = { title, msg, dur, start, icon -> viewModel.createRoutine(title, msg, dur, start, icon) },
                                    onUpdateRoutine = { id, title, msg, dur, start, icon, active -> viewModel.updateRoutine(id, title, msg, dur, start, icon, active) },
                                    onDeleteRoutine = { viewModel.deleteRoutine(it) },
                                    onAddStep = { id, title, dur -> viewModel.addRoutineStep(id, title, dur) },
                                    onDeleteStep = { viewModel.deleteRoutineStep(it) }
                                )
                                Spacer(modifier = Modifier.height(80.dp))
                            }
                            3 -> Column(
                                modifier = Modifier
                                    .fillMaxSize()
                                    .verticalScroll(rememberScrollState())
                            ) {
                                ShoppingTabContent(
                                    items = shoppingItems,
                                    onAddItem = { name, qty -> viewModel.addShoppingItem(name, qty) },
                                    onToggleItem = { viewModel.toggleShoppingItem(it) },
                                    onDeleteItem = { viewModel.deleteShoppingItem(it) },
                                    onUpdateQuantity = { id, qty -> viewModel.updateShoppingItemQuantity(id, qty) }
                                )
                                Spacer(modifier = Modifier.height(80.dp))
                            }
                            4 -> EmailTabContent(
                                viewModel = emailViewModel
                            )
                            5 -> MusicTabContent()
                            6 -> AlarmTabContent(
                                viewModel = viewModel
                            )
                        }
                    }
                }
            }
        }
    }
}

// --- TASKS TAB (MICROSOFT TO DO DESIGN & LOGIC) ---
@OptIn(ExperimentalMaterial3Api::class)
@Composable
fun TasksTabContent(
    taskLists: List<ManagementTaskList>,
    tasks: List<ManagementTask>,
    onAddTask: (listId: String, title: String, priority: String, relevantFrom: String?) -> Unit,
    onToggleTask: (String) -> Unit,
    onDeleteTask: (String) -> Unit,
    onAddSubtask: (String, String) -> Unit,
    onDeleteTaskList: (String) -> Unit,
    onAddTaskList: (String) -> Unit,
    onUpdateTaskList: (String, String) -> Unit,
    onUpdateTask: (String, String?, String?, Boolean?, String?, String?) -> Unit,
    onUploadTaskFile: (String, ByteArray, String, String) -> Unit,
    onDeleteTaskFile: (String, String) -> Unit
) {
    var selectedListId by remember { mutableStateOf<String?>(null) }
    var searchQuery by remember { mutableStateOf("") }
    var showSearchBar by remember { mutableStateOf(false) }
    var showCompletedSection by remember { mutableStateOf(true) }

    var listToDelete by remember { mutableStateOf<ManagementTaskList?>(null) }
    var listToRename by remember { mutableStateOf<ManagementTaskList?>(null) }
    var renameListName by remember { mutableStateOf("") }

    val todayIso = remember { SimpleDateFormat("yyyy-MM-dd", Locale.US).format(Date()) }
    val rootTasks = remember(tasks) { tasks.filter { it.parent_id == null } }

    fun isTaskMyDay(task: ManagementTask): Boolean {
        val rf = task.relevant_from ?: return false
        return rf.take(10) == todayIso
    }

    if (selectedListId == null) {
        // ==================== MICROSOFT TO DO HOME OVERVIEW ====================
        val myDayCount = rootTasks.count { !it.is_completed && isTaskMyDay(it) }
        val importantCount = rootTasks.count { !it.is_completed && it.priority.equals("hoch", ignoreCase = true) }
        val plannedCount = rootTasks.count { !it.is_completed && !it.relevant_from.isNullOrBlank() }
        val allCount = rootTasks.count { !it.is_completed }

        var isAddingList by remember { mutableStateOf(false) }
        var newListName by remember { mutableStateOf("") }

        Column(
            modifier = Modifier.fillMaxWidth(),
            verticalArrangement = Arrangement.spacedBy(4.dp)
        ) {
            OutlinedTextField(
                value = searchQuery,
                onValueChange = { searchQuery = it },
                placeholder = { Text("Aufgaben durchsuchen...", fontSize = 13.sp, color = AppTheme.colors.textMuted) },
                singleLine = true,
                trailingIcon = {
                    if (searchQuery.isNotBlank()) {
                        IconButton(onClick = { searchQuery = "" }) {
                            Icon(
                                imageVector = Icons.Default.Close,
                                contentDescription = "Suche leeren",
                                tint = AppTheme.colors.textMuted
                            )
                        }
                    }
                },
                colors = OutlinedTextFieldDefaults.colors(
                    focusedBorderColor = AppTheme.colors.primaryGold,
                    unfocusedBorderColor = AppTheme.colors.inputBorder,
                    focusedTextColor = AppTheme.colors.textPrimary,
                    unfocusedTextColor = AppTheme.colors.textPrimary,
                    focusedContainerColor = AppTheme.colors.cardBackground,
                    unfocusedContainerColor = AppTheme.colors.cardBackground
                ),
                shape = RoundedCornerShape(12.dp),
                modifier = Modifier
                    .fillMaxWidth()
                    .padding(bottom = 8.dp)
            )

            if (searchQuery.isNotBlank()) {
                val matched = rootTasks.filter {
                    it.title.contains(searchQuery, ignoreCase = true) ||
                        (it.ai_plan?.contains(searchQuery, ignoreCase = true) == true)
                }
                Text(
                    text = "SUCHERGEBNISSE (${matched.size})",
                    fontSize = 11.sp,
                    fontWeight = FontWeight.Bold,
                    color = AppTheme.colors.primaryGold,
                    modifier = Modifier.padding(vertical = 6.dp)
                )
                matched.forEach { task ->
                    TaskRow(
                        task = task,
                        allTasks = tasks,
                        listColor = AppTheme.colors.primaryGold,
                        onToggleTask = onToggleTask,
                        onDeleteTask = onDeleteTask,
                        onAddSubtask = onAddSubtask,
                        onUpdateTask = onUpdateTask,
                        onUploadTaskFile = onUploadTaskFile,
                        onDeleteTaskFile = onDeleteTaskFile
                    )
                }
            } else {
                // --- SMART LISTS (Mein Tag, Wichtig, Geplant, Aufgaben) ---
                val smartRows = listOf(
                    Triple("my_day", "Mein Tag", Color(0xFF0EA5E9)) to Pair(Icons.Default.WbSunny, myDayCount),
                    Triple("important", "Wichtig", Color(0xFFEC4899)) to Pair(Icons.Default.Star, importantCount),
                    Triple("planned", "Geplant", Color(0xFF10B981)) to Pair(Icons.Default.CalendarToday, plannedCount),
                    Triple("all", "Alle Aufgaben", Color(0xFF6366F1)) to Pair(Icons.Default.Home, allCount)
                )

                smartRows.forEach { (meta, iconAndCount) ->
                    val (smartId, label, tintColor) = meta
                    val (iconVec, count) = iconAndCount
                    Row(
                        modifier = Modifier
                            .fillMaxWidth()
                            .clip(RoundedCornerShape(10.dp))
                            .clickable { selectedListId = smartId }
                            .padding(horizontal = 8.dp, vertical = 13.dp),
                        verticalAlignment = Alignment.CenterVertically,
                        horizontalArrangement = Arrangement.SpaceBetween
                    ) {
                        Row(verticalAlignment = Alignment.CenterVertically) {
                            Icon(
                                imageVector = iconVec,
                                contentDescription = label,
                                tint = tintColor,
                                modifier = Modifier.size(22.dp)
                            )
                            Spacer(modifier = Modifier.width(16.dp))
                            Text(
                                text = label,
                                fontSize = 15.5.sp,
                                fontWeight = FontWeight.Medium,
                                color = AppTheme.colors.textPrimary
                            )
                        }
                        if (count > 0) {
                            Text(
                                text = count.toString(),
                                fontSize = 14.sp,
                                fontWeight = FontWeight.SemiBold,
                                color = AppTheme.colors.textMuted
                            )
                        }
                    }
                }

                // Divider between Smart Lists and Custom Lists (exact Microsoft To Do layout)
                Box(
                    modifier = Modifier
                        .fillMaxWidth()
                        .padding(vertical = 8.dp)
                        .height(1.dp)
                        .background(AppTheme.colors.cardBorder)
                )

                // --- CUSTOM LISTS ---
                taskLists.forEach { list ->
                    val listColor = parseHexColor(list.color, Color(0xFF6366F1))
                    val activeCount = rootTasks.count { it.task_list_id == list.id && !it.is_completed }
                    val listIcon = if (list.name.equals("Aufgaben", ignoreCase = true)) {
                        Icons.Default.Home
                    } else {
                        Icons.Default.List
                    }

                    Row(
                        modifier = Modifier
                            .fillMaxWidth()
                            .clip(RoundedCornerShape(10.dp))
                            .clickable { selectedListId = list.id }
                            .padding(horizontal = 8.dp, vertical = 13.dp),
                        verticalAlignment = Alignment.CenterVertically,
                        horizontalArrangement = Arrangement.SpaceBetween
                    ) {
                        Row(
                            verticalAlignment = Alignment.CenterVertically,
                            modifier = Modifier.weight(1f)
                        ) {
                            Icon(
                                imageVector = listIcon,
                                contentDescription = null,
                                tint = listColor,
                                modifier = Modifier.size(22.dp)
                            )
                            Spacer(modifier = Modifier.width(16.dp))
                            Text(
                                text = list.name,
                                fontSize = 15.5.sp,
                                fontWeight = FontWeight.Medium,
                                color = AppTheme.colors.textPrimary,
                                maxLines = 1,
                                overflow = androidx.compose.ui.text.style.TextOverflow.Ellipsis
                            )
                        }

                        Row(verticalAlignment = Alignment.CenterVertically) {
                            if (activeCount > 0) {
                                Text(
                                    text = activeCount.toString(),
                                    fontSize = 14.sp,
                                    fontWeight = FontWeight.SemiBold,
                                    color = AppTheme.colors.textMuted,
                                    modifier = Modifier.padding(horizontal = 6.dp)
                                )
                            }
                        }
                    }
                }

                Spacer(modifier = Modifier.height(12.dp))

                // --- BOTTOM "+ Neue Liste" BAR ---
                if (isAddingList) {
                    Card(
                        modifier = Modifier.fillMaxWidth(),
                        colors = CardDefaults.cardColors(containerColor = AppTheme.colors.cardBackground),
                        border = BorderStroke(1.dp, AppTheme.colors.primaryGold.copy(alpha = 0.5f)),
                        shape = RoundedCornerShape(12.dp)
                    ) {
                        Column(
                            modifier = Modifier.padding(12.dp),
                            verticalArrangement = Arrangement.spacedBy(10.dp)
                        ) {
                            OutlinedTextField(
                                value = newListName,
                                onValueChange = { newListName = it },
                                label = { Text("Neue Liste") },
                                colors = OutlinedTextFieldDefaults.colors(
                                    focusedBorderColor = AppTheme.colors.primaryGold,
                                    unfocusedBorderColor = AppTheme.colors.inputBorder,
                                    focusedTextColor = AppTheme.colors.textPrimary,
                                    unfocusedTextColor = AppTheme.colors.textPrimary,
                                    focusedContainerColor = AppTheme.colors.inputBackground,
                                    unfocusedContainerColor = AppTheme.colors.inputBackground,
                                    focusedLabelColor = AppTheme.colors.primaryGold,
                                    unfocusedLabelColor = AppTheme.colors.textMuted
                                ),
                                modifier = Modifier.fillMaxWidth(),
                                singleLine = true
                            )
                            Row(
                                modifier = Modifier.fillMaxWidth(),
                                horizontalArrangement = Arrangement.End
                            ) {
                                TextButton(onClick = {
                                    isAddingList = false
                                    newListName = ""
                                }) {
                                    Text("Abbrechen", color = AppTheme.colors.textMuted)
                                }
                                Spacer(modifier = Modifier.width(8.dp))
                                Button(
                                    onClick = {
                                        if (newListName.isNotBlank()) {
                                            onAddTaskList(newListName.trim())
                                            newListName = ""
                                            isAddingList = false
                                        }
                                    },
                                    colors = ButtonDefaults.buttonColors(
                                        containerColor = AppTheme.colors.primaryGold,
                                        contentColor = Color(0xFF121316)
                                    ),
                                    shape = RoundedCornerShape(8.dp)
                                ) {
                                    Text("Erstellen", fontWeight = FontWeight.Bold)
                                }
                            }
                        }
                    }
                } else {
                    Row(
                        modifier = Modifier
                            .fillMaxWidth()
                            .clip(RoundedCornerShape(10.dp))
                            .clickable { isAddingList = true }
                            .padding(horizontal = 8.dp, vertical = 14.dp),
                        verticalAlignment = Alignment.CenterVertically
                    ) {
                        Icon(
                            imageVector = Icons.Default.Add,
                            contentDescription = "Neue Liste",
                            tint = AppTheme.colors.textSecondary,
                            modifier = Modifier.size(22.dp)
                        )
                        Spacer(modifier = Modifier.width(16.dp))
                        Text(
                            text = "Neue Liste",
                            fontSize = 15.5.sp,
                            fontWeight = FontWeight.Medium,
                            color = AppTheme.colors.textSecondary
                        )
                    }
                }
            }
        }
    } else {
        // ==================== MICROSOFT TO DO LIST DETAIL VIEW ====================
        val isSmartList = selectedListId in listOf("my_day", "important", "planned", "all")
        val selectedList = taskLists.find { it.id == selectedListId }

        val listTitle = when (selectedListId) {
            "my_day" -> "Mein Tag"
            "important" -> "Wichtig"
            "planned" -> "Geplant"
            "all" -> "Alle Aufgaben"
            else -> selectedList?.name ?: "Aufgaben"
        }

        val listColor = when (selectedListId) {
            "my_day" -> Color(0xFF0EA5E9)
            "important" -> Color(0xFFEC4899)
            "planned" -> Color(0xFF10B981)
            "all" -> Color(0xFF6366F1)
            else -> parseHexColor(selectedList?.color, Color(0xFF6366F1))
        }

        val filteredRootTasks = remember(rootTasks, selectedListId) {
            when (selectedListId) {
                "my_day" -> rootTasks.filter { isTaskMyDay(it) }
                "important" -> rootTasks.filter { it.priority.equals("hoch", ignoreCase = true) }
                "planned" -> rootTasks.filter { !it.relevant_from.isNullOrBlank() }
                "all" -> rootTasks
                else -> rootTasks.filter { it.task_list_id == selectedListId }
            }
        }

        val openTasks = remember(filteredRootTasks) {
            filteredRootTasks
                .filter { !it.is_completed }
                .sortedByDescending { it.priority.equals("hoch", ignoreCase = true) }
        }
        val completedTasks = remember(filteredRootTasks) {
            filteredRootTasks.filter { it.is_completed }
        }

        var newTaskTitle by remember { mutableStateOf("") }

        Column(
            modifier = Modifier.fillMaxWidth(),
            verticalArrangement = Arrangement.spacedBy(8.dp)
        ) {
            // Top Bar (Back arrow + List options)
            Row(
                verticalAlignment = Alignment.CenterVertically,
                horizontalArrangement = Arrangement.SpaceBetween,
                modifier = Modifier.fillMaxWidth()
            ) {
                IconButton(onClick = { selectedListId = null }) {
                    Icon(
                        imageVector = Icons.Default.ArrowBack,
                        contentDescription = "Zurück",
                        tint = listColor
                    )
                }

                if (!isSmartList && selectedList != null) {
                    Row(verticalAlignment = Alignment.CenterVertically) {
                        IconButton(onClick = {
                            listToRename = selectedList
                            renameListName = selectedList.name
                        }) {
                            Icon(
                                imageVector = Icons.Default.Edit,
                                contentDescription = "Liste umbenennen",
                                tint = listColor.copy(alpha = 0.85f),
                                modifier = Modifier.size(20.dp)
                            )
                        }
                        if (taskLists.size > 1) {
                            IconButton(onClick = { listToDelete = selectedList }) {
                                Icon(
                                    imageVector = Icons.Default.Delete,
                                    contentDescription = "Liste löschen",
                                    tint = Color(0xFFF87171).copy(alpha = 0.8f),
                                    modifier = Modifier.size(20.dp)
                                )
                            }
                        }
                    }
                }
            }

            // Large Colored Microsoft To Do Title (like "Mein-Seelenfunke Shop" in screenshot)
            Text(
                text = listTitle,
                fontSize = 28.sp,
                fontWeight = FontWeight.Bold,
                color = listColor,
                modifier = Modifier.padding(horizontal = 4.dp, vertical = 2.dp)
            )

            // Quick "+ Aufgabe hinzufügen" Input Row
            Card(
                modifier = Modifier
                    .fillMaxWidth()
                    .padding(bottom = 4.dp),
                colors = CardDefaults.cardColors(containerColor = AppTheme.colors.cardBackground),
                border = BorderStroke(1.dp, AppTheme.colors.cardBorder),
                shape = RoundedCornerShape(10.dp)
            ) {
                Row(
                    modifier = Modifier.padding(horizontal = 12.dp, vertical = 6.dp),
                    verticalAlignment = Alignment.CenterVertically,
                    horizontalArrangement = Arrangement.spacedBy(8.dp)
                ) {
                    Icon(
                        imageVector = Icons.Default.Add,
                        contentDescription = null,
                        tint = listColor,
                        modifier = Modifier.size(22.dp)
                    )
                    OutlinedTextField(
                        value = newTaskTitle,
                        onValueChange = { newTaskTitle = it },
                        placeholder = { Text("Aufgabe hinzufügen", color = AppTheme.colors.textMuted, fontSize = 14.sp) },
                        colors = OutlinedTextFieldDefaults.colors(
                            focusedBorderColor = Color.Transparent,
                            unfocusedBorderColor = Color.Transparent,
                            focusedTextColor = AppTheme.colors.textPrimary,
                            unfocusedTextColor = AppTheme.colors.textPrimary,
                            focusedContainerColor = Color.Transparent,
                            unfocusedContainerColor = Color.Transparent
                        ),
                        modifier = Modifier.weight(1f),
                        singleLine = true
                    )
                    if (newTaskTitle.isNotBlank()) {
                        Button(
                            onClick = {
                                val smartIds = setOf("my_day", "important", "planned", "all", "default")
                                val targetId = if (selectedListId == null || selectedListId in smartIds) {
                                    taskLists.firstOrNull { it.name.equals("Aufgaben", ignoreCase = true) }?.id
                                        ?: taskLists.firstOrNull()?.id
                                        ?: (selectedListId ?: "default")
                                } else {
                                    selectedListId!!
                                }
                                val newPriority = if (selectedListId == "important") "hoch" else "niedrig"
                                val newRelevantFrom = if (selectedListId == "my_day" || selectedListId == "planned") todayIso else null
                                onAddTask(targetId, newTaskTitle.trim(), newPriority, newRelevantFrom)
                                newTaskTitle = ""
                            },
                            colors = ButtonDefaults.buttonColors(
                                containerColor = listColor,
                                contentColor = Color.White
                            ),
                            shape = RoundedCornerShape(8.dp),
                            contentPadding = PaddingValues(horizontal = 12.dp, vertical = 6.dp)
                        ) {
                            Text("OK", fontWeight = FontWeight.Bold, fontSize = 12.sp)
                        }
                    }
                }
            }

            // --- OPEN TASKS ---
            if (openTasks.isEmpty() && completedTasks.isEmpty()) {
                Text(
                    text = "Keine Aufgaben in dieser Liste.",
                    color = AppTheme.colors.textMuted,
                    fontSize = 14.sp,
                    textAlign = TextAlign.Center,
                    modifier = Modifier
                        .fillMaxWidth()
                        .padding(top = 36.dp)
                )
            } else {
                Column(verticalArrangement = Arrangement.spacedBy(6.dp)) {
                    openTasks.forEach { task ->
                        TaskRow(
                            task = task,
                            allTasks = tasks,
                            listColor = listColor,
                            onToggleTask = onToggleTask,
                            onDeleteTask = onDeleteTask,
                            onAddSubtask = onAddSubtask,
                            onUpdateTask = onUpdateTask,
                            onUploadTaskFile = onUploadTaskFile,
                            onDeleteTaskFile = onDeleteTaskFile
                        )
                    }
                }
            }

            // --- COLLAPSIBLE "⌄ Erledigt X" SECTION (Exact Microsoft To Do layout) ---
            if (completedTasks.isNotEmpty()) {
                Row(
                    modifier = Modifier
                        .clip(RoundedCornerShape(8.dp))
                        .clickable { showCompletedSection = !showCompletedSection }
                        .padding(horizontal = 6.dp, vertical = 10.dp),
                    verticalAlignment = Alignment.CenterVertically
                ) {
                    Icon(
                        imageVector = if (showCompletedSection) Icons.Default.ExpandMore else Icons.Default.ChevronRight,
                        contentDescription = null,
                        tint = listColor,
                        modifier = Modifier.size(20.dp)
                    )
                    Spacer(modifier = Modifier.width(6.dp))
                    Text(
                        text = "Erledigt",
                        fontSize = 14.sp,
                        fontWeight = FontWeight.SemiBold,
                        color = listColor
                    )
                    Spacer(modifier = Modifier.width(8.dp))
                    Text(
                        text = completedTasks.size.toString(),
                        fontSize = 14.sp,
                        fontWeight = FontWeight.Normal,
                        color = listColor.copy(alpha = 0.85f)
                    )
                }

                AnimatedVisibility(visible = showCompletedSection) {
                    Column(verticalArrangement = Arrangement.spacedBy(6.dp)) {
                        completedTasks.forEach { task ->
                            TaskRow(
                                task = task,
                                allTasks = tasks,
                                listColor = listColor,
                                onToggleTask = onToggleTask,
                                onDeleteTask = onDeleteTask,
                                onAddSubtask = onAddSubtask,
                                onUpdateTask = onUpdateTask,
                                onUploadTaskFile = onUploadTaskFile,
                                onDeleteTaskFile = onDeleteTaskFile
                            )
                        }
                    }
                }
            }
        }
    }

    if (listToDelete != null) {
        AlertDialog(
            onDismissRequest = { listToDelete = null },
            title = { Text(text = "Liste löschen", color = AppTheme.colors.textPrimary, fontWeight = FontWeight.Bold) },
            text = { Text(text = "Möchtest du '${listToDelete?.name}' samt aller Aufgaben wirklich löschen?", color = AppTheme.colors.textSecondary) },
            confirmButton = {
                TextButton(
                    onClick = {
                        listToDelete?.let {
                            onDeleteTaskList(it.id)
                            if (selectedListId == it.id) selectedListId = null
                        }
                        listToDelete = null
                    }
                ) {
                    Text("Ja, löschen", color = Color.Red, fontWeight = FontWeight.Bold)
                }
            },
            dismissButton = {
                TextButton(onClick = { listToDelete = null }) {
                    Text("Abbrechen", color = AppTheme.colors.textMuted)
                }
            },
            containerColor = AppTheme.colors.cardBackground,
            titleContentColor = AppTheme.colors.textPrimary,
            textContentColor = AppTheme.colors.textSecondary
        )
    }

    if (listToRename != null) {
        AlertDialog(
            onDismissRequest = { listToRename = null },
            title = { Text(text = "Liste umbenennen", color = AppTheme.colors.textPrimary, fontWeight = FontWeight.Bold) },
            text = {
                OutlinedTextField(
                    value = renameListName,
                    onValueChange = { renameListName = it },
                    label = { Text("Name der Liste...") },
                    colors = OutlinedTextFieldDefaults.colors(
                        focusedBorderColor = Gold,
                        unfocusedBorderColor = AppTheme.colors.inputBorder,
                        focusedTextColor = AppTheme.colors.textPrimary,
                        unfocusedTextColor = AppTheme.colors.textPrimary,
                        focusedContainerColor = AppTheme.colors.inputBackground,
                        unfocusedContainerColor = AppTheme.colors.inputBackground,
                        focusedLabelColor = Gold,
                        unfocusedLabelColor = AppTheme.colors.textMuted
                    ),
                    modifier = Modifier.fillMaxWidth(),
                    singleLine = true
                )
            },
            confirmButton = {
                TextButton(
                    onClick = {
                        listToRename?.let { list ->
                            if (renameListName.isNotBlank()) {
                                onUpdateTaskList(list.id, renameListName.trim())
                            }
                        }
                        listToRename = null
                    }
                ) {
                    Text("Speichern", color = Gold, fontWeight = FontWeight.Bold)
                }
            },
            dismissButton = {
                TextButton(onClick = { listToRename = null }) {
                    Text("Abbrechen", color = AppTheme.colors.textMuted)
                }
            },
            containerColor = AppTheme.colors.cardBackground,
            titleContentColor = AppTheme.colors.textPrimary,
            textContentColor = AppTheme.colors.textSecondary
        )
    }
}

@Composable
fun TaskRow(
    task: ManagementTask,
    allTasks: List<ManagementTask>,
    listColor: Color,
    onToggleTask: (String) -> Unit,
    onDeleteTask: (String) -> Unit,
    onAddSubtask: (String, String) -> Unit,
    onUpdateTask: (String, String?, String?, Boolean?, String?, String?) -> Unit,
    onUploadTaskFile: (String, ByteArray, String, String) -> Unit,
    onDeleteTaskFile: (String, String) -> Unit
) {
    val context = androidx.compose.ui.platform.LocalContext.current
    var isExpanded by remember { mutableStateOf(false) }
    var newSubtaskTitle by remember { mutableStateOf("") }
    var showDeleteConfirm by remember { mutableStateOf(false) }
    var subtaskDeleteConfirmId by remember { mutableStateOf<String?>(null) }

    val subtasks = allTasks.filter { it.parent_id == task.id }
    val completedSubtasksCount = subtasks.count { it.is_completed }
    val isImportant = task.priority.equals("hoch", ignoreCase = true)
    val todayIso = remember { SimpleDateFormat("yyyy-MM-dd", Locale.US).format(Date()) }
    val isMyDay = task.relevant_from?.take(10) == todayIso

    var editedTitle by remember(task.title) { mutableStateOf(task.title) }
    var editedNote by remember(task.ai_plan) { mutableStateOf(task.ai_plan ?: "") }

    Card(
        modifier = Modifier
            .fillMaxWidth()
            .clickable { isExpanded = !isExpanded },
        colors = CardDefaults.cardColors(
            containerColor = if (task.is_completed) AppTheme.colors.cardBackground.copy(alpha = 0.7f) else AppTheme.colors.cardBackground
        ),
        border = BorderStroke(
            width = 1.dp,
            color = if (isExpanded) listColor.copy(alpha = 0.6f) else AppTheme.colors.cardBorder
        ),
        shape = RoundedCornerShape(10.dp)
    ) {
        Column(modifier = Modifier.padding(horizontal = 14.dp, vertical = 12.dp)) {
            Row(
                verticalAlignment = Alignment.CenterVertically,
                horizontalArrangement = Arrangement.SpaceBetween,
                modifier = Modifier.fillMaxWidth()
            ) {
                Row(
                    verticalAlignment = Alignment.CenterVertically,
                    modifier = Modifier.weight(1f)
                ) {
                    // --- ROUND MICROSOFT TO DO CHECKBOX ---
                    Box(
                        modifier = Modifier
                            .size(22.dp)
                            .clip(RoundedCornerShape(11.dp))
                            .background(if (task.is_completed) listColor else Color.Transparent)
                            .border(
                                width = 2.dp,
                                color = if (task.is_completed) listColor else AppTheme.colors.textMuted,
                                shape = RoundedCornerShape(11.dp)
                            )
                            .clickable { onToggleTask(task.id) },
                        contentAlignment = Alignment.Center
                    ) {
                        if (task.is_completed) {
                            Text(
                                text = "✓",
                                color = Color.White,
                                fontSize = 13.sp,
                                fontWeight = FontWeight.Bold
                            )
                        }
                    }

                    Spacer(modifier = Modifier.width(14.dp))

                    Column(modifier = Modifier.weight(1f)) {
                        Text(
                            text = task.title,
                            color = if (task.is_completed) AppTheme.colors.textMuted else AppTheme.colors.textPrimary,
                            textDecoration = if (task.is_completed) TextDecoration.LineThrough else TextDecoration.None,
                            fontSize = 15.sp,
                            fontWeight = FontWeight.Normal,
                            lineHeight = 20.sp
                        )

                        // Subtitle metadata (like Microsoft To Do: ✓ 2 von 2 · Mein Tag · Notiz)
                        val metaItems = mutableListOf<String>()
                        if (subtasks.isNotEmpty()) {
                            metaItems.add("✓ $completedSubtasksCount von ${subtasks.size}")
                        }
                        if (isMyDay) {
                            metaItems.add("☀ Mein Tag")
                        } else {
                            val localDateStr = de.meinseelenfunke.app.util.DateUtils.parseUtcToLocalDateString(task.relevant_from)
                            if (!localDateStr.isNullOrBlank()) {
                                val displayDate = try {
                                    val parts = localDateStr.split("-")
                                    if (parts.size == 3) "${parts[2]}.${parts[1]}.${parts[0]}" else localDateStr
                                } catch (e: Exception) {
                                    localDateStr
                                }
                                metaItems.add("📅 $displayDate")
                            }
                        }
                        if (!task.ai_plan.isNullOrBlank()) {
                            metaItems.add("📝 Notiz")
                        }
                        if (!task.file_paths.isNullOrEmpty()) {
                            metaItems.add("📎 ${task.file_paths.size}")
                        }

                        if (metaItems.isNotEmpty()) {
                            Text(
                                text = metaItems.joinToString("  ·  "),
                                fontSize = 12.sp,
                                color = AppTheme.colors.textMuted,
                                modifier = Modifier.padding(top = 2.dp)
                            )
                        }
                    }
                }

                // --- STAR TOGGLE ON THE RIGHT (Exact Microsoft To Do layout) ---
                Text(
                    text = if (isImportant) "★" else "☆",
                    color = if (isImportant) listColor else AppTheme.colors.textMuted,
                    fontSize = 20.sp,
                    modifier = Modifier
                        .padding(start = 8.dp)
                        .clickable {
                            val nextPrio = if (isImportant) "niedrig" else "hoch"
                            onUpdateTask(task.id, null, nextPrio, null, null, null)
                        }
                )
            }

            // --- EXPANDED TASK DETAIL INSPECTOR ---
            AnimatedVisibility(visible = isExpanded) {
                Column(
                    modifier = Modifier
                        .fillMaxWidth()
                        .padding(top = 14.dp),
                    verticalArrangement = Arrangement.spacedBy(10.dp)
                ) {
                    Box(
                        modifier = Modifier
                            .fillMaxWidth()
                            .height(1.dp)
                            .background(AppTheme.colors.cardBorder)
                    )

                    // 1. Subtasks / Steps ("Schritte")
                    if (subtasks.isNotEmpty()) {
                        subtasks.forEach { subtask ->
                            Row(
                                verticalAlignment = Alignment.CenterVertically,
                                horizontalArrangement = Arrangement.SpaceBetween,
                                modifier = Modifier
                                    .fillMaxWidth()
                                    .padding(vertical = 3.dp)
                            ) {
                                Row(
                                    verticalAlignment = Alignment.CenterVertically,
                                    modifier = Modifier.weight(1f)
                                ) {
                                    Box(
                                        modifier = Modifier
                                            .size(18.dp)
                                            .clip(RoundedCornerShape(9.dp))
                                            .background(if (subtask.is_completed) listColor else Color.Transparent)
                                            .border(
                                                width = 1.5.dp,
                                                color = if (subtask.is_completed) listColor else AppTheme.colors.textMuted,
                                                shape = RoundedCornerShape(9.dp)
                                            )
                                            .clickable { onToggleTask(subtask.id) },
                                        contentAlignment = Alignment.Center
                                    ) {
                                        if (subtask.is_completed) {
                                            Text("✓", color = Color.White, fontSize = 10.sp, fontWeight = FontWeight.Bold)
                                        }
                                    }
                                    Spacer(modifier = Modifier.width(10.dp))
                                    Text(
                                        text = subtask.title,
                                        color = if (subtask.is_completed) AppTheme.colors.textMuted else AppTheme.colors.textPrimary,
                                        textDecoration = if (subtask.is_completed) TextDecoration.LineThrough else TextDecoration.None,
                                        fontSize = 13.5.sp
                                    )
                                }

                                IconButton(
                                    onClick = { subtaskDeleteConfirmId = subtask.id },
                                    modifier = Modifier.size(24.dp)
                                ) {
                                    Icon(
                                        imageVector = Icons.Default.Close,
                                        contentDescription = "Schritt löschen",
                                        tint = AppTheme.colors.textMuted,
                                        modifier = Modifier.size(15.dp)
                                    )
                                }
                            }
                        }
                    }

                    // Add Step Input
                    Row(
                        verticalAlignment = Alignment.CenterVertically,
                        horizontalArrangement = Arrangement.spacedBy(8.dp),
                        modifier = Modifier.fillMaxWidth()
                    ) {
                        OutlinedTextField(
                            value = newSubtaskTitle,
                            onValueChange = { newSubtaskTitle = it },
                            placeholder = {
                                Text(
                                    text = if (subtasks.isEmpty()) "+ Schritt hinzufügen" else "+ Nächster Schritt",
                                    fontSize = 12.5.sp,
                                    color = listColor
                                )
                            },
                            colors = OutlinedTextFieldDefaults.colors(
                                focusedBorderColor = listColor,
                                unfocusedBorderColor = AppTheme.colors.inputBorder,
                                focusedTextColor = AppTheme.colors.textPrimary,
                                unfocusedTextColor = AppTheme.colors.textPrimary,
                                focusedContainerColor = AppTheme.colors.inputBackground,
                                unfocusedContainerColor = AppTheme.colors.inputBackground
                            ),
                            modifier = Modifier.weight(1f),
                            singleLine = true
                        )

                        if (newSubtaskTitle.isNotBlank()) {
                            Button(
                                onClick = {
                                    onAddSubtask(task.id, newSubtaskTitle.trim())
                                    newSubtaskTitle = ""
                                },
                                colors = ButtonDefaults.buttonColors(containerColor = listColor, contentColor = Color.White),
                                shape = RoundedCornerShape(8.dp)
                            ) {
                                Text("+", fontWeight = FontWeight.Bold)
                            }
                        }
                    }

                    // 2. Quick Actions Row: "Mein Tag" + "Fälligkeitsdatum"
                    Row(
                        modifier = Modifier.fillMaxWidth(),
                        horizontalArrangement = Arrangement.spacedBy(8.dp)
                    ) {
                        Surface(
                            onClick = {
                                val nextDate = if (isMyDay) "" else "$todayIso 00:00:00"
                                onUpdateTask(task.id, null, null, null, nextDate, null)
                            },
                            color = if (isMyDay) Color(0xFF0EA5E9).copy(alpha = 0.15f) else AppTheme.colors.inputBackground,
                            border = BorderStroke(1.dp, if (isMyDay) Color(0xFF0EA5E9) else AppTheme.colors.inputBorder),
                            shape = RoundedCornerShape(8.dp),
                            modifier = Modifier.weight(1f)
                        ) {
                            Row(
                                modifier = Modifier.padding(horizontal = 10.dp, vertical = 9.dp),
                                verticalAlignment = Alignment.CenterVertically,
                                horizontalArrangement = Arrangement.Center
                            ) {
                                Text(
                                    text = if (isMyDay) "☀ In Mein Tag" else "☀ Zu Mein Tag",
                                    color = if (isMyDay) Color(0xFF0EA5E9) else AppTheme.colors.textSecondary,
                                    fontSize = 12.sp,
                                    fontWeight = FontWeight.SemiBold
                                )
                            }
                        }

                        Surface(
                            onClick = {
                                val currentCal = Calendar.getInstance()
                                val datePickerDialog = android.app.DatePickerDialog(
                                    context,
                                    { _, year, month, dayOfMonth ->
                                        val selectedDateStr = String.format(Locale.US, "%04d-%02d-%02d 00:00:00", year, month + 1, dayOfMonth)
                                        onUpdateTask(task.id, null, null, null, selectedDateStr, null)
                                    },
                                    currentCal.get(Calendar.YEAR),
                                    currentCal.get(Calendar.MONTH),
                                    currentCal.get(Calendar.DAY_OF_MONTH)
                                )
                                datePickerDialog.setButton(android.app.DatePickerDialog.BUTTON_NEUTRAL, "Löschen") { _, _ ->
                                    onUpdateTask(task.id, null, null, null, "", null)
                                }
                                datePickerDialog.show()
                            },
                            color = AppTheme.colors.inputBackground,
                            border = BorderStroke(1.dp, AppTheme.colors.inputBorder),
                            shape = RoundedCornerShape(8.dp),
                            modifier = Modifier.weight(1f)
                        ) {
                            Row(
                                modifier = Modifier.padding(horizontal = 10.dp, vertical = 9.dp),
                                verticalAlignment = Alignment.CenterVertically,
                                horizontalArrangement = Arrangement.Center
                            ) {
                                Text(
                                    text = "📅 Fälligkeit",
                                    color = AppTheme.colors.textSecondary,
                                    fontSize = 12.sp,
                                    fontWeight = FontWeight.SemiBold
                                )
                            }
                        }
                    }

                    // 3. Title & Note Editor
                    OutlinedTextField(
                        value = editedTitle,
                        onValueChange = { editedTitle = it },
                        label = { Text("Titel", fontSize = 11.sp) },
                        colors = OutlinedTextFieldDefaults.colors(
                            focusedBorderColor = listColor,
                            unfocusedBorderColor = AppTheme.colors.inputBorder,
                            focusedTextColor = AppTheme.colors.textPrimary,
                            unfocusedTextColor = AppTheme.colors.textPrimary,
                            focusedContainerColor = AppTheme.colors.inputBackground,
                            unfocusedContainerColor = AppTheme.colors.inputBackground
                        ),
                        modifier = Modifier.fillMaxWidth(),
                        singleLine = true
                    )

                    OutlinedTextField(
                        value = editedNote,
                        onValueChange = { editedNote = it },
                        label = { Text("Notiz hinzufügen", fontSize = 11.sp) },
                        colors = OutlinedTextFieldDefaults.colors(
                            focusedBorderColor = listColor,
                            unfocusedBorderColor = AppTheme.colors.inputBorder,
                            focusedTextColor = AppTheme.colors.textPrimary,
                            unfocusedTextColor = AppTheme.colors.textPrimary,
                            focusedContainerColor = AppTheme.colors.inputBackground,
                            unfocusedContainerColor = AppTheme.colors.inputBackground
                        ),
                        modifier = Modifier.fillMaxWidth(),
                        minLines = 2,
                        maxLines = 5
                    )

                    // 4. File Attachments
                    var isUploading by remember { mutableStateOf(false) }
                    val scope = rememberCoroutineScope()

                    val filePickerLauncher = rememberLauncherForActivityResult(
                        contract = ActivityResultContracts.GetContent()
                    ) { uri: Uri? ->
                        uri?.let {
                            val contentResolver = context.contentResolver
                            val mimeType = contentResolver.getType(uri) ?: "application/octet-stream"
                            val fileName = getFileName(context, uri) ?: "upload.bin"
                            try {
                                val inputStream = contentResolver.openInputStream(uri)
                                val fileBytes = inputStream?.readBytes()
                                inputStream?.close()
                                if (fileBytes != null) {
                                    scope.launch {
                                        isUploading = true
                                        onUploadTaskFile(task.id, fileBytes, fileName, mimeType)
                                        isUploading = false
                                    }
                                }
                            } catch (e: Exception) {
                                e.printStackTrace()
                            }
                        }
                    }

                    if (!task.file_paths.isNullOrEmpty()) {
                        Column(
                            verticalArrangement = Arrangement.spacedBy(4.dp),
                            modifier = Modifier.fillMaxWidth()
                        ) {
                            task.file_paths.forEach { path ->
                                val fileName = path.substringAfterLast('/')
                                Row(
                                    verticalAlignment = Alignment.CenterVertically,
                                    horizontalArrangement = Arrangement.SpaceBetween,
                                    modifier = Modifier
                                        .fillMaxWidth()
                                        .background(AppTheme.colors.inputBackground, RoundedCornerShape(8.dp))
                                        .padding(horizontal = 10.dp, vertical = 6.dp)
                                ) {
                                    Text(
                                        text = "📎 $fileName",
                                        color = AppTheme.colors.textSecondary,
                                        fontSize = 12.sp,
                                        maxLines = 1,
                                        modifier = Modifier.weight(1f)
                                    )
                                    IconButton(
                                        onClick = { onDeleteTaskFile(task.id, path) },
                                        modifier = Modifier.size(22.dp)
                                    ) {
                                        Icon(
                                            imageVector = Icons.Default.Close,
                                            contentDescription = "Datei löschen",
                                            tint = Color(0xFFF87171),
                                            modifier = Modifier.size(14.dp)
                                        )
                                    }
                                }
                            }
                        }
                    }

                    // Bottom Action Bar inside Task Inspector (Datei, Speichern, Löschen)
                    Row(
                        modifier = Modifier.fillMaxWidth(),
                        horizontalArrangement = Arrangement.SpaceBetween,
                        verticalAlignment = Alignment.CenterVertically
                    ) {
                        Row(horizontalArrangement = Arrangement.spacedBy(6.dp)) {
                            TextButton(onClick = { filePickerLauncher.launch("*/*") }) {
                                Text("📎 Datei", color = AppTheme.colors.textSecondary, fontSize = 12.sp)
                            }
                            TextButton(onClick = { showDeleteConfirm = true }) {
                                Text("🗑 Löschen", color = Color(0xFFF87171), fontSize = 12.sp)
                            }
                        }

                        Button(
                            onClick = {
                                onUpdateTask(
                                    task.id,
                                    editedTitle.trim().ifBlank { task.title },
                                    null,
                                    null,
                                    null,
                                    editedNote.trim()
                                )
                                isExpanded = false
                            },
                            colors = ButtonDefaults.buttonColors(
                                containerColor = listColor,
                                contentColor = Color.White
                            ),
                            shape = RoundedCornerShape(8.dp)
                        ) {
                            Text("Speichern", fontSize = 12.sp, fontWeight = FontWeight.Bold)
                        }
                    }
                }
            }
        }
    }

    if (showDeleteConfirm) {
        AlertDialog(
            onDismissRequest = { showDeleteConfirm = false },
            title = { Text(text = "Aufgabe löschen", color = AppTheme.colors.textPrimary, fontWeight = FontWeight.Bold) },
            text = { Text(text = "Möchtest du diese Aufgabe wirklich löschen?", color = AppTheme.colors.textSecondary) },
            confirmButton = {
                TextButton(
                    onClick = {
                        onDeleteTask(task.id)
                        showDeleteConfirm = false
                    }
                ) {
                    Text("Ja, löschen", color = Color.Red, fontWeight = FontWeight.Bold)
                }
            },
            dismissButton = {
                TextButton(onClick = { showDeleteConfirm = false }) {
                    Text("Abbrechen", color = AppTheme.colors.textMuted)
                }
            },
            containerColor = AppTheme.colors.cardBackground,
            titleContentColor = AppTheme.colors.textPrimary,
            textContentColor = AppTheme.colors.textSecondary
        )
    }

    if (subtaskDeleteConfirmId != null) {
        AlertDialog(
            onDismissRequest = { subtaskDeleteConfirmId = null },
            title = { Text(text = "Schritt löschen", color = AppTheme.colors.textPrimary, fontWeight = FontWeight.Bold) },
            text = { Text(text = "Möchtest du diesen Schritt wirklich löschen?", color = AppTheme.colors.textSecondary) },
            confirmButton = {
                TextButton(
                    onClick = {
                        subtaskDeleteConfirmId?.let { onDeleteTask(it) }
                        subtaskDeleteConfirmId = null
                    }
                ) {
                    Text("Ja, löschen", color = Color.Red, fontWeight = FontWeight.Bold)
                }
            },
            dismissButton = {
                TextButton(onClick = { subtaskDeleteConfirmId = null }) {
                    Text("Abbrechen", color = AppTheme.colors.textMuted)
                }
            },
            containerColor = AppTheme.colors.cardBackground,
            titleContentColor = AppTheme.colors.textPrimary,
            textContentColor = AppTheme.colors.textSecondary
        )
    }
}

enum class CalendarViewMode { MONTH, DAY }

// --- CALENDAR TAB ---
@OptIn(ExperimentalMaterial3Api::class)
@Composable
fun CalendarTabContent(
    events: List<CalendarEvent>,
    onAddEvent: (title: String, start: String, end: String?, isAllDay: Boolean, category: String, description: String?, recurrence: String?, reminderMinutes: Int?, priority: String?, sendEmail: Boolean) -> Unit,
    onUpdateEvent: (id: String, title: String, start: String, end: String?, isAllDay: Boolean, category: String, description: String?, recurrence: String?, reminderMinutes: Int?, priority: String?) -> Unit,
    onDeleteEvent: (String) -> Unit,
    onImportIcs: (android.net.Uri) -> Unit,
    onExportIcs: () -> Unit
) {
    val cal = Calendar.getInstance()
    var currentYear by remember { mutableStateOf(cal.get(Calendar.YEAR)) }
    var currentMonth by remember { mutableStateOf(cal.get(Calendar.MONTH)) } // 0-11
    var selectedDate by remember { mutableStateOf(cal.time) }

    var showInlineCreator by remember { mutableStateOf(false) }
    var viewMode by remember { mutableStateOf(CalendarViewMode.MONTH) }

    val focusRequester = remember { FocusRequester() }
    val keyboardController = LocalSoftwareKeyboardController.current

    LaunchedEffect(showInlineCreator) {
        if (showInlineCreator) {
            kotlinx.coroutines.delay(200)
            focusRequester.requestFocus()
            keyboardController?.show()
        }
    }

    LaunchedEffect(Unit) {
        val pendingDateStr = de.meinseelenfunke.app.util.NavigationBridge.pendingSelectedDate
        if (pendingDateStr != null) {
            try {
                val date = SimpleDateFormat("yyyy-MM-dd", Locale.US).parse(pendingDateStr)
                if (date != null) {
                    selectedDate = date
                    val selectCal = Calendar.getInstance().apply { time = date }
                    currentYear = selectCal.get(Calendar.YEAR)
                    currentMonth = selectCal.get(Calendar.MONTH)
                    viewMode = CalendarViewMode.DAY
                }
            } catch (e: Exception) {
                // ignore parsing error
            }
            de.meinseelenfunke.app.util.NavigationBridge.pendingSelectedDate = null
        }
        if (de.meinseelenfunke.app.util.NavigationBridge.pendingCreateEvent) {
            showInlineCreator = true
            viewMode = CalendarViewMode.DAY
            de.meinseelenfunke.app.util.NavigationBridge.pendingCreateEvent = false
        }
    }

    // Form inputs
    var editingEventId by remember { mutableStateOf<String?>(null) }
    var title by remember { mutableStateOf("") }
    var description by remember { mutableStateOf("") }
    var selectedCategory by remember { mutableStateOf("general") }
    var isAllDay by remember { mutableStateOf(false) }
    var sendEmail by remember { mutableStateOf(false) }

    val sdfDate = SimpleDateFormat("yyyy-MM-dd", Locale.US)
    var startDateString by remember(selectedDate) { mutableStateOf(sdfDate.format(selectedDate)) }
    var startTimeString by remember { mutableStateOf("12:00") }
    
    var endDateString by remember(selectedDate) { mutableStateOf(sdfDate.format(selectedDate)) }
    var endTimeString by remember { mutableStateOf("13:00") }

    var recurrence by remember { mutableStateOf("none") }
    var priority by remember { mutableStateOf("low") }
    var reminderMinutes by remember { mutableStateOf(-1) }

    val categories = listOf(
        "general" to "Termin",
        "meeting" to "Besprechung",
        "call" to "Anrufe",
        "birthday" to "Geburtstag",
        "vacation" to "Feiertage",
        "travel" to "Reise",
        "project" to "Projekte",
        "customer" to "Kunde",
        "restmuell" to "Restmüll",
        "altpapier" to "Papier",
        "biomuell" to "Bio",
        "gelber_sack" to "Gelber Sack",
        "schadstoffe" to "Schadstoffe",
        "sperrmuell" to "Sperrmüll",
        "gruen" to "Grünabfall",
        "baum" to "Tannenbaum"
    )

    val monthNames = listOf("Januar", "Februar", "März", "April", "Mai", "Juni", "Juli", "August", "September", "Oktober", "November", "Dezember")
    val weekdays = listOf("Mo", "Di", "Mi", "Do", "Fr", "Sa", "So")

    val grid = remember(currentYear, currentMonth) { getCalendarGrid(currentYear, currentMonth) }

    Box(modifier = Modifier.fillMaxSize()) {
        Column(
            modifier = Modifier
                .fillMaxSize()
                .verticalScroll(rememberScrollState()),
            verticalArrangement = Arrangement.spacedBy(16.dp)
        ) {
                if (viewMode == CalendarViewMode.MONTH) {
                    // Month Selector Header
                    Card(
                        modifier = Modifier.fillMaxWidth(),
                        colors = CardDefaults.cardColors(containerColor = AppTheme.colors.cardBackground),
                        border = BorderStroke(1.dp, AppTheme.colors.cardBorder)
                    ) {
                        Row(
                            modifier = Modifier
                                .fillMaxWidth()
                                .padding(8.dp),
                            horizontalArrangement = Arrangement.SpaceBetween,
                            verticalAlignment = Alignment.CenterVertically
                        ) {
                            IconButton(onClick = {
                                if (currentMonth == 0) {
                                    currentMonth = 11
                                    currentYear--
                                } else {
                                    currentMonth--
                                }
                            }) {
                                Icon(imageVector = Icons.Default.ChevronLeft, contentDescription = "Vorheriger Monat", tint = Gold)
                            }

                            Text(
                                text = "${monthNames[currentMonth]} $currentYear",
                                color = Gold,
                                fontWeight = FontWeight.Bold,
                                fontSize = 16.sp
                            )

                            IconButton(onClick = {
                                if (currentMonth == 11) {
                                    currentMonth = 0
                                    currentYear++
                                } else {
                                    currentMonth++
                                }
                            }) {
                                Icon(imageVector = Icons.Default.ChevronRight, contentDescription = "Nächster Monat", tint = Gold)
                            }
                        }
                    }

                    // Calendar Grid Card
                    Card(
                        modifier = Modifier.fillMaxWidth(),
                        colors = CardDefaults.cardColors(containerColor = AppTheme.colors.cardBackground),
                        border = BorderStroke(1.dp, AppTheme.colors.cardBorder)
                    ) {
                        Column(
                            modifier = Modifier.padding(8.dp),
                            verticalArrangement = Arrangement.spacedBy(4.dp)
                        ) {
                            // Weekday Headers
                            Row(modifier = Modifier.fillMaxWidth()) {
                                weekdays.forEach { day ->
                                    Text(
                                        text = day,
                                        color = AppTheme.colors.textMuted,
                                        fontSize = 12.sp,
                                        fontWeight = FontWeight.Bold,
                                        textAlign = TextAlign.Center,
                                        modifier = Modifier.weight(1f)
                                    )
                                }
                            }

                            // Month Days (grouped by week) with multi-day horizontal event bars
                            grid.chunked(7).forEachIndexed { weekIndex, week ->
                                if (weekIndex > 0) {
                                    Spacer(
                                        modifier = Modifier
                                            .fillMaxWidth()
                                            .height(1.dp)
                                            .background(AppTheme.colors.cardBorder.copy(alpha = 0.4f))
                                    )
                                }

                                Column(
                                    modifier = Modifier
                                        .fillMaxWidth()
                                        .padding(vertical = 2.dp)
                                ) {
                                    // Day Numbers Row
                                    Row(modifier = Modifier.fillMaxWidth()) {
                                        week.forEach { day ->
                                            val isSelected = isSameDay(day.date, selectedDate)
                                            val isToday = isTodayDate(day.date)
                                            
                                            Box(
                                                modifier = Modifier
                                                    .weight(1f)
                                                    .padding(vertical = 2.dp),
                                                contentAlignment = Alignment.Center
                                            ) {
                                                Box(
                                                    modifier = Modifier
                                                        .size(24.dp)
                                                        .clip(RoundedCornerShape(12.dp))
                                                        .background(
                                                            if (isSelected) Gold 
                                                            else if (isToday) Gold.copy(alpha = 0.2f) 
                                                            else Color.Transparent
                                                        )
                                                        .border(
                                                            width = 1.dp,
                                                            color = if (isToday && !isSelected) Gold else Color.Transparent,
                                                            shape = RoundedCornerShape(12.dp)
                                                        )
                                                        .clickable {
                                                            selectedDate = day.date
                                                            viewMode = CalendarViewMode.DAY
                                                        },
                                                    contentAlignment = Alignment.Center
                                                ) {
                                                    Text(
                                                        text = day.dayNumber.toString(),
                                                        color = if (isSelected) SpaceBlack 
                                                                else if (isToday) Gold
                                                                else if (day.isCurrentMonth) AppTheme.colors.textPrimary 
                                                                else AppTheme.colors.textMuted.copy(alpha = 0.4f),
                                                        fontSize = 11.sp,
                                                        fontWeight = if (isSelected || isToday) FontWeight.Bold else FontWeight.Normal
                                                    )
                                                }
                                            }
                                        }
                                    }

                                    // Tracks Column
                                    val tracks = layoutWeekTracks(week, events)
                                    Column(
                                        modifier = Modifier
                                            .fillMaxWidth()
                                            .padding(bottom = 4.dp)
                                    ) {
                                        tracks.take(3).forEach { track ->
                                            val spans = getTrackSpans(track)
                                            Row(
                                                modifier = Modifier
                                                    .fillMaxWidth()
                                                    .height(20.dp),
                                                verticalAlignment = Alignment.CenterVertically
                                            ) {
                                                spans.forEach { span ->
                                                    if (span.event == null) {
                                                        Spacer(modifier = Modifier.weight(span.span.toFloat()))
                                                    } else {
                                                        val style = getCategoryStyle(span.event.category)
                                                        Box(
                                                            modifier = Modifier
                                                                .weight(span.span.toFloat())
                                                                .fillMaxHeight()
                                                                .padding(vertical = 1.dp, horizontal = 1.dp)
                                                                .clip(RoundedCornerShape(3.dp))
                                                                .background(style.bg.copy(alpha = 0.85f))
                                                                .border(0.5.dp, style.text.copy(alpha = 0.3f), RoundedCornerShape(3.dp))
                                                                .clickable {
                                                                    selectedDate = parseEventDate(span.event.start) ?: week.first().date
                                                                    viewMode = CalendarViewMode.DAY
                                                                }
                                                                .padding(horizontal = 4.dp),
                                                            contentAlignment = Alignment.CenterStart
                                                        ) {
                                                            Text(
                                                                text = span.event.title,
                                                                color = getGridTextColor(style.bg),
                                                                fontSize = 8.sp,
                                                                fontWeight = FontWeight.Bold,
                                                                maxLines = 1,
                                                                overflow = androidx.compose.ui.text.style.TextOverflow.Ellipsis
                                                            )
                                                        }
                                                    }
                                                }
                                            }
                                        }

                                        if (tracks.size > 3) {
                                            Row(
                                                modifier = Modifier
                                                    .fillMaxWidth()
                                                    .height(14.dp),
                                                verticalAlignment = Alignment.CenterVertically
                                            ) {
                                                for (col in 0..6) {
                                                    var hiddenCount = 0
                                                    for (tIdx in 3 until tracks.size) {
                                                        if (tracks[tIdx][col] != null) {
                                                            hiddenCount++
                                                        }
                                                    }
                                                    Box(
                                                        modifier = Modifier
                                                            .weight(1f)
                                                            .fillMaxHeight(),
                                                        contentAlignment = Alignment.Center
                                                    ) {
                                                        if (hiddenCount > 0) {
                                                            Text(
                                                                text = "+$hiddenCount",
                                                                color = Gold,
                                                                fontSize = 8.sp,
                                                                fontWeight = FontWeight.Bold
                                                            )
                                                        }
                                                    }
                                                }
                                            }
                                        } else if (tracks.isEmpty()) {
                                            Spacer(modifier = Modifier.height(20.dp))
                                        } else {
                                            val emptyRows = 3 - tracks.size
                                            repeat(emptyRows) {
                                                Spacer(modifier = Modifier.height(20.dp))
                                            }
                                        }
                                    }
                                }
                            }
                        }
                    }

                    // ICS Utilities Row
                    Row(
                        modifier = Modifier.fillMaxWidth(),
                        horizontalArrangement = Arrangement.spacedBy(8.dp),
                        verticalAlignment = Alignment.CenterVertically
                    ) {
                        val icsImportLauncher = rememberLauncherForActivityResult(
                            contract = ActivityResultContracts.GetContent()
                        ) { uri ->
                            uri?.let { onImportIcs(uri) }
                        }

                        Button(
                            onClick = { icsImportLauncher.launch("*/*") },
                            colors = ButtonDefaults.buttonColors(containerColor = AppTheme.colors.glassSurface, contentColor = Gold),
                            border = BorderStroke(1.dp, Gold.copy(alpha = 0.3f)),
                            shape = RoundedCornerShape(8.dp),
                            modifier = Modifier.weight(1f)
                        ) {
                            Text("ICS Import", fontSize = 12.sp, fontWeight = FontWeight.Bold)
                        }

                        Button(
                            onClick = { onExportIcs() },
                            colors = ButtonDefaults.buttonColors(containerColor = AppTheme.colors.glassSurface, contentColor = Gold),
                            border = BorderStroke(1.dp, Gold.copy(alpha = 0.3f)),
                            shape = RoundedCornerShape(8.dp),
                            modifier = Modifier.weight(1f)
                        ) {
                            Text("ICS Export", fontSize = 12.sp, fontWeight = FontWeight.Bold)
                        }
                    }

                    // Selected Date Action Row
                    Row(
                        modifier = Modifier.fillMaxWidth(),
                        horizontalArrangement = Arrangement.SpaceBetween,
                        verticalAlignment = Alignment.CenterVertically
                    ) {
                        val sdfSelected = SimpleDateFormat("dd. MMMM yyyy", Locale.GERMAN)
                        Column {
                            Text(
                                text = "GEWÄHLTER TAG",
                                fontSize = 10.sp,
                                fontWeight = FontWeight.Bold,
                                color = Gold,
                                letterSpacing = 1.sp
                            )
                            Text(
                                text = sdfSelected.format(selectedDate),
                                fontSize = 14.sp,
                                fontWeight = FontWeight.Bold,
                                color = AppTheme.colors.textPrimary
                            )
                        }

                        Button(
                            onClick = { viewMode = CalendarViewMode.DAY },
                            colors = ButtonDefaults.buttonColors(containerColor = Gold, contentColor = SpaceBlack),
                            shape = RoundedCornerShape(8.dp)
                        ) {
                            Text("Tagesansicht öffnen", fontWeight = FontWeight.Bold)
                        }
                    }
                } else {
                    // Day View ("Tagesansicht")
                    Row(
                        modifier = Modifier.fillMaxWidth(),
                        verticalAlignment = Alignment.CenterVertically
                    ) {
                        IconButton(onClick = { viewMode = CalendarViewMode.MONTH }) {
                            Icon(
                                imageVector = Icons.Default.ArrowBack,
                                contentDescription = "Zurück zum Kalender",
                                tint = Gold
                            )
                        }
                        Spacer(modifier = Modifier.width(8.dp))
                        val sdfSelected = SimpleDateFormat("EEEE, d. MMMM yyyy", Locale.GERMAN)
                        Text(
                            text = sdfSelected.format(selectedDate),
                            color = AppTheme.colors.textPrimary,
                            fontSize = 18.sp,
                            fontWeight = FontWeight.Bold,
                            modifier = Modifier.weight(1f)
                        )
                    }

                    AnimatedVisibility(
                        visible = showInlineCreator,
                        enter = fadeIn() + expandVertically(),
                        exit = fadeOut() + shrinkVertically()
                    ) {
                        Card(
                            modifier = Modifier
                                .fillMaxWidth()
                                .padding(bottom = 12.dp),
                            colors = CardDefaults.cardColors(containerColor = AppTheme.colors.cardBackground),
                            shape = RoundedCornerShape(12.dp),
                            border = BorderStroke(1.dp, Gold.copy(alpha = 0.4f))
                        ) {
                            Column(
                                modifier = Modifier.padding(12.dp),
                                verticalArrangement = Arrangement.spacedBy(10.dp)
                            ) {
                                Row(
                                    modifier = Modifier.fillMaxWidth(),
                                    horizontalArrangement = Arrangement.SpaceBetween,
                                    verticalAlignment = Alignment.CenterVertically
                                ) {
                                    Text(
                                        text = if (editingEventId != null) "TERMIN BEARBEITEN" else "NEUER TERMIN ANLEGEN",
                                        fontSize = 12.sp,
                                        fontWeight = FontWeight.Bold,
                                        color = Gold,
                                        letterSpacing = 1.sp
                                    )
                                    IconButton(
                                        onClick = {
                                            showInlineCreator = false
                                            editingEventId = null
                                            title = ""
                                            description = ""
                                            selectedCategory = "general"
                                            isAllDay = false
                                            recurrence = "none"
                                            priority = "low"
                                            reminderMinutes = -1
                                        },
                                        modifier = Modifier.size(24.dp)
                                    ) {
                                        Icon(
                                            imageVector = Icons.Default.Close,
                                            contentDescription = "Schließen",
                                            tint = AppTheme.colors.textMuted,
                                            modifier = Modifier.size(16.dp)
                                        )
                                    }
                                }

                                OutlinedTextField(
                                    value = title,
                                    onValueChange = { title = it },
                                    label = { Text("Titel") },
                                    colors = OutlinedTextFieldDefaults.colors(
                                        focusedBorderColor = Gold,
                                        unfocusedBorderColor = AppTheme.colors.inputBorder,
                                        focusedLabelColor = Gold,
                                        unfocusedLabelColor = AppTheme.colors.textMuted,
                                        unfocusedTextColor = AppTheme.colors.textPrimary,
                                        focusedTextColor = AppTheme.colors.textPrimary,
                                        focusedContainerColor = AppTheme.colors.inputBackground,
                                        unfocusedContainerColor = AppTheme.colors.inputBackground
                                    ),
                                    modifier = Modifier
                                        .fillMaxWidth()
                                        .focusRequester(focusRequester),
                                    singleLine = true
                                )

                                var showMoreOptions by remember { mutableStateOf(false) }

                                if (showMoreOptions) {
                                    OutlinedTextField(
                                        value = description,
                                        onValueChange = { description = it },
                                        label = { Text("Beschreibung (optional)") },
                                        colors = OutlinedTextFieldDefaults.colors(
                                            focusedBorderColor = Gold,
                                            unfocusedBorderColor = AppTheme.colors.inputBorder,
                                            focusedLabelColor = Gold,
                                            unfocusedLabelColor = AppTheme.colors.textMuted,
                                            unfocusedTextColor = AppTheme.colors.textPrimary,
                                            focusedTextColor = AppTheme.colors.textPrimary,
                                            focusedContainerColor = AppTheme.colors.inputBackground,
                                            unfocusedContainerColor = AppTheme.colors.inputBackground
                                        ),
                                        modifier = Modifier.fillMaxWidth(),
                                        maxLines = 2
                                    )

                                    // Category Row
                                    Text("Kategorie", fontSize = 11.sp, color = AppTheme.colors.textMuted, fontWeight = FontWeight.Bold)
                                    LazyRow(
                                        horizontalArrangement = Arrangement.spacedBy(8.dp),
                                        modifier = Modifier.fillMaxWidth()
                                    ) {
                                        items(categories.size) { index ->
                                            val cat = categories[index]
                                            val isSelected = selectedCategory == cat.first
                                            val style = getCategoryStyle(cat.first)
                                            val chipBg = if (isSelected) style.bg else AppTheme.colors.glassSurface
                                            val chipText = if (isSelected) style.text else AppTheme.colors.textMuted
                                            val chipBorder = if (isSelected) BorderStroke(1.dp, style.text) else BorderStroke(1.dp, AppTheme.colors.glassBorder)

                                            Surface(
                                                onClick = { selectedCategory = cat.first },
                                                shape = RoundedCornerShape(16.dp),
                                                color = chipBg,
                                                border = chipBorder,
                                                modifier = Modifier.height(28.dp)
                                            ) {
                                                Row(
                                                    modifier = Modifier.padding(horizontal = 10.dp),
                                                    verticalAlignment = Alignment.CenterVertically,
                                                    horizontalArrangement = Arrangement.spacedBy(4.dp)
                                                ) {
                                                    Icon(
                                                        imageVector = style.icon,
                                                        contentDescription = null,
                                                        tint = chipText,
                                                        modifier = Modifier.size(12.dp)
                                                    )
                                                    Text(
                                                        text = cat.second,
                                                        color = chipText,
                                                        fontSize = 11.sp,
                                                        fontWeight = FontWeight.Bold
                                                    )
                                                }
                                            }
                                        }
                                    }

                                    // Times Row
                                    Row(
                                        modifier = Modifier.fillMaxWidth(),
                                        horizontalArrangement = Arrangement.spacedBy(8.dp)
                                    ) {
                                        OutlinedTextField(
                                            value = startDateString,
                                            onValueChange = { startDateString = it },
                                            label = { Text("Beginn Datum") },
                                            colors = OutlinedTextFieldDefaults.colors(
                                                focusedBorderColor = Gold,
                                                unfocusedBorderColor = AppTheme.colors.inputBorder,
                                                focusedLabelColor = Gold,
                                                unfocusedLabelColor = AppTheme.colors.textMuted,
                                                unfocusedTextColor = AppTheme.colors.textPrimary,
                                                focusedTextColor = AppTheme.colors.textPrimary,
                                                focusedContainerColor = AppTheme.colors.inputBackground,
                                                unfocusedContainerColor = AppTheme.colors.inputBackground
                                            ),
                                            modifier = Modifier.weight(1f)
                                        )

                                        if (!isAllDay) {
                                            OutlinedTextField(
                                                value = startTimeString,
                                                onValueChange = { startTimeString = it },
                                                label = { Text("Uhrzeit") },
                                                colors = OutlinedTextFieldDefaults.colors(
                                                    focusedBorderColor = Gold,
                                                    unfocusedBorderColor = AppTheme.colors.inputBorder,
                                                    focusedLabelColor = Gold,
                                                    unfocusedLabelColor = AppTheme.colors.textMuted,
                                                    unfocusedTextColor = AppTheme.colors.textPrimary,
                                                    focusedTextColor = AppTheme.colors.textPrimary,
                                                    focusedContainerColor = AppTheme.colors.inputBackground,
                                                    unfocusedContainerColor = AppTheme.colors.inputBackground
                                                ),
                                                modifier = Modifier.weight(1f)
                                            )
                                        }
                                    }

                                    Row(
                                        modifier = Modifier.fillMaxWidth(),
                                        horizontalArrangement = Arrangement.spacedBy(8.dp)
                                    ) {
                                        OutlinedTextField(
                                            value = endDateString,
                                            onValueChange = { endDateString = it },
                                            label = { Text("Ende Datum") },
                                            colors = OutlinedTextFieldDefaults.colors(
                                                focusedBorderColor = Gold,
                                                unfocusedBorderColor = AppTheme.colors.inputBorder,
                                                focusedLabelColor = Gold,
                                                unfocusedLabelColor = AppTheme.colors.textMuted,
                                                unfocusedTextColor = AppTheme.colors.textPrimary,
                                                focusedTextColor = AppTheme.colors.textPrimary,
                                                focusedContainerColor = AppTheme.colors.inputBackground,
                                                unfocusedContainerColor = AppTheme.colors.inputBackground
                                            ),
                                            modifier = Modifier.weight(1f)
                                        )

                                        if (!isAllDay) {
                                            OutlinedTextField(
                                                value = endTimeString,
                                                onValueChange = { endTimeString = it },
                                                label = { Text("Uhrzeit") },
                                                colors = OutlinedTextFieldDefaults.colors(
                                                    focusedBorderColor = Gold,
                                                    unfocusedBorderColor = AppTheme.colors.inputBorder,
                                                    focusedLabelColor = Gold,
                                                    unfocusedLabelColor = AppTheme.colors.textMuted,
                                                    unfocusedTextColor = AppTheme.colors.textPrimary,
                                                    focusedTextColor = AppTheme.colors.textPrimary,
                                                    focusedContainerColor = AppTheme.colors.inputBackground,
                                                    unfocusedContainerColor = AppTheme.colors.inputBackground
                                                ),
                                                modifier = Modifier.weight(1f)
                                            )
                                        }
                                    }

                                    Row(
                                        modifier = Modifier.fillMaxWidth(),
                                        verticalAlignment = Alignment.CenterVertically
                                    ) {
                                        Checkbox(
                                            checked = isAllDay,
                                            onCheckedChange = { isAllDay = it },
                                            colors = CheckboxDefaults.colors(checkedColor = Gold)
                                        )
                                        Spacer(modifier = Modifier.width(4.dp))
                                        Text("Ganztägig", color = AppTheme.colors.textPrimary, fontSize = 13.sp)
                                    }
 
                                    Row(
                                        modifier = Modifier.fillMaxWidth(),
                                        verticalAlignment = Alignment.CenterVertically
                                    ) {
                                        Checkbox(
                                            checked = sendEmail,
                                            onCheckedChange = { sendEmail = it },
                                            colors = CheckboxDefaults.colors(checkedColor = Gold)
                                        )
                                        Spacer(modifier = Modifier.width(4.dp))
                                        Text("E-Mail versenden", color = AppTheme.colors.textPrimary, fontSize = 13.sp)
                                    }

                                    // Priority, Recurrence, Reminder
                                    Text("Priorität", fontSize = 11.sp, color = AppTheme.colors.textMuted, fontWeight = FontWeight.Bold)
                                    Row(
                                        modifier = Modifier.fillMaxWidth(),
                                        horizontalArrangement = Arrangement.spacedBy(8.dp)
                                    ) {
                                        listOf("low" to "Niedrig", "medium" to "Mittel", "high" to "Hoch").forEach { prio ->
                                            val isSelected = priority == prio.first
                                            val bg = when(prio.first) {
                                                "low" -> if(isSelected) Color.Gray.copy(alpha = 0.3f) else AppTheme.colors.glassSurface
                                                "medium" -> if(isSelected) Color.Yellow.copy(alpha = 0.3f) else AppTheme.colors.glassSurface
                                                else -> if(isSelected) Color.Red.copy(alpha = 0.3f) else AppTheme.colors.glassSurface
                                            }
                                            val textCol = when(prio.first) {
                                                "low" -> if(isSelected) Color.White else AppTheme.colors.textMuted
                                                "medium" -> if(isSelected) Color.Yellow else AppTheme.colors.textMuted
                                                else -> if(isSelected) Color.Red else AppTheme.colors.textMuted
                                            }
                                            val border = if (isSelected) BorderStroke(1.dp, textCol) else BorderStroke(1.dp, AppTheme.colors.glassBorder)

                                            Surface(
                                                onClick = { priority = prio.first },
                                                modifier = Modifier.weight(1f).height(32.dp),
                                                shape = RoundedCornerShape(6.dp),
                                                color = bg,
                                                border = border
                                            ) {
                                                Box(contentAlignment = Alignment.Center) {
                                                    Text(prio.second, color = textCol, fontSize = 11.sp, fontWeight = FontWeight.Bold)
                                                }
                                            }
                                        }
                                    }

                                    Text("Wiederholung", fontSize = 11.sp, color = AppTheme.colors.textMuted, fontWeight = FontWeight.Bold)
                                    LazyRow(
                                        horizontalArrangement = Arrangement.spacedBy(8.dp),
                                        modifier = Modifier.fillMaxWidth()
                                    ) {
                                        val recurrences = listOf(
                                            "none" to "Keine",
                                            "daily" to "Täglich",
                                            "weekly" to "Wöchentlich",
                                            "monthly" to "Monatlich",
                                            "yearly" to "Jährlich"
                                        )
                                        items(recurrences.size) { index ->
                                            val rec = recurrences[index]
                                            val isSelected = recurrence == rec.first
                                            val chipBg = if (isSelected) Gold.copy(alpha = 0.2f) else AppTheme.colors.glassSurface
                                            val chipText = if (isSelected) Gold else AppTheme.colors.textMuted
                                            val chipBorder = if (isSelected) BorderStroke(1.dp, Gold) else BorderStroke(1.dp, AppTheme.colors.glassBorder)

                                            Surface(
                                                onClick = { recurrence = rec.first },
                                                shape = RoundedCornerShape(6.dp),
                                                color = chipBg,
                                                border = chipBorder,
                                                modifier = Modifier.height(28.dp)
                                            ) {
                                                Box(modifier = Modifier.padding(horizontal = 10.dp), contentAlignment = Alignment.Center) {
                                                    Text(rec.second, color = chipText, fontSize = 11.sp, fontWeight = FontWeight.Bold)
                                                }
                                            }
                                        }
                                    }

                                    Text("Erinnerung", fontSize = 11.sp, color = AppTheme.colors.textMuted, fontWeight = FontWeight.Bold)
                                    LazyRow(
                                        horizontalArrangement = Arrangement.spacedBy(8.dp),
                                        modifier = Modifier.fillMaxWidth()
                                    ) {
                                        val reminders = listOf(
                                            -1 to "Keine",
                                            0 to "Pünktlich",
                                            15 to "15 Min",
                                            60 to "1 Std",
                                            1440 to "1 Tag"
                                        )
                                        items(reminders.size) { index ->
                                            val rem = reminders[index]
                                            val isSelected = reminderMinutes == rem.first
                                            val chipBg = if (isSelected) Gold.copy(alpha = 0.2f) else AppTheme.colors.glassSurface
                                            val chipText = if (isSelected) Gold else AppTheme.colors.textMuted
                                            val chipBorder = if (isSelected) BorderStroke(1.dp, Gold) else BorderStroke(1.dp, AppTheme.colors.glassBorder)

                                            Surface(
                                                onClick = { reminderMinutes = rem.first },
                                                shape = RoundedCornerShape(6.dp),
                                                color = chipBg,
                                                border = chipBorder,
                                                modifier = Modifier.height(28.dp)
                                            ) {
                                                Box(modifier = Modifier.padding(horizontal = 10.dp), contentAlignment = Alignment.Center) {
                                                    Text(rem.second, color = chipText, fontSize = 11.sp, fontWeight = FontWeight.Bold)
                                                }
                                            }
                                        }
                                    }
                                } else {
                                    Row(
                                        modifier = Modifier.fillMaxWidth(),
                                        horizontalArrangement = Arrangement.spacedBy(8.dp),
                                        verticalAlignment = Alignment.CenterVertically
                                    ) {
                                        Text("Kategorie:", fontSize = 11.sp, color = AppTheme.colors.textMuted, fontWeight = FontWeight.Bold)
                                        LazyRow(
                                            horizontalArrangement = Arrangement.spacedBy(6.dp),
                                            modifier = Modifier.weight(1f)
                                        ) {
                                            items(categories.size) { index ->
                                                val cat = categories[index]
                                                val isSelected = selectedCategory == cat.first
                                                val style = getCategoryStyle(cat.first)
                                                val chipBg = if (isSelected) style.bg else AppTheme.colors.glassSurface
                                                val chipText = if (isSelected) style.text else AppTheme.colors.textMuted
                                                val chipBorder = if (isSelected) BorderStroke(1.dp, style.text) else BorderStroke(1.dp, AppTheme.colors.glassBorder)

                                                Surface(
                                                    onClick = { selectedCategory = cat.first },
                                                    shape = RoundedCornerShape(12.dp),
                                                    color = chipBg,
                                                    border = chipBorder,
                                                    modifier = Modifier.height(24.dp)
                                                ) {
                                                    Box(modifier = Modifier.padding(horizontal = 8.dp), contentAlignment = Alignment.Center) {
                                                        Text(cat.second, color = chipText, fontSize = 10.sp, fontWeight = FontWeight.Bold)
                                                    }
                                                }
                                            }
                                        }
                                    }
                                }

                                Row(
                                    modifier = Modifier.fillMaxWidth(),
                                    horizontalArrangement = Arrangement.SpaceBetween,
                                    verticalAlignment = Alignment.CenterVertically
                                ) {
                                    TextButton(onClick = { showMoreOptions = !showMoreOptions }) {
                                        Text(
                                            text = if (showMoreOptions) "Weniger Optionen" else "Mehr Optionen...",
                                            color = Gold,
                                            fontSize = 12.sp
                                        )
                                    }

                                    Button(
                                        onClick = {
                                            val startStr = if (isAllDay) {
                                                "${startDateString}T00:00:00"
                                            } else {
                                                "${startDateString}T${startTimeString}:00"
                                            }
                                            val endStr = if (isAllDay) {
                                                "${endDateString}T23:59:59"
                                            } else {
                                                "${endDateString}T${endTimeString}:00"
                                            }
                                            val remVal = if (reminderMinutes == -1) null else reminderMinutes
                                            
                                            val currentEditId = editingEventId
                                            if (currentEditId != null) {
                                                onUpdateEvent(
                                                    currentEditId,
                                                    title,
                                                    startStr,
                                                    endStr,
                                                    isAllDay,
                                                    selectedCategory,
                                                    description.ifBlank { null },
                                                    recurrence,
                                                    remVal,
                                                    priority
                                                )
                                            } else {
                                                onAddEvent(
                                                    title,
                                                    startStr,
                                                    endStr,
                                                    isAllDay,
                                                    selectedCategory,
                                                    description.ifBlank { null },
                                                    recurrence,
                                                    remVal,
                                                    priority,
                                                    sendEmail
                                                )
                                            }
                                            title = ""
                                            description = ""
                                            selectedCategory = "general"
                                            isAllDay = false
                                            recurrence = "none"
                                            priority = "low"
                                            reminderMinutes = -1
                                            sendEmail = false
                                            showInlineCreator = false
                                            editingEventId = null
                                        },
                                        enabled = title.isNotBlank() && startDateString.isNotBlank() && (isAllDay || startTimeString.isNotBlank()),
                                        colors = ButtonDefaults.buttonColors(containerColor = Gold, contentColor = SpaceBlack),
                                        shape = RoundedCornerShape(8.dp),
                                        modifier = Modifier.height(36.dp)
                                    ) {
                                        Text(if (editingEventId != null) "Ändern" else "Speichern", fontWeight = FontWeight.Bold, fontSize = 12.sp)
                                    }
                                }
                            }
                        }
                    }

                    // Events List for Day
                    val selectedDayEvents = getEventsForDay(CalendarDay(selectedDate, true, 0, 0, 0), events)

                    if (selectedDayEvents.isEmpty()) {
                        Text(
                            "Keine Termine an diesem Tag.",
                            color = AppTheme.colors.textMuted,
                            fontSize = 14.sp,
                            textAlign = TextAlign.Center,
                            modifier = Modifier
                                .fillMaxWidth()
                                .padding(vertical = 48.dp)
                        )
                    } else {
                        val (allDayEvents, timedEvents) = selectedDayEvents.partition { it.is_all_day }

                        Column(verticalArrangement = Arrangement.spacedBy(12.dp)) {
                            // Render All-Day Events
                            if (allDayEvents.isNotEmpty()) {
                                Text(
                                    text = "GANZTÄGIG",
                                    fontSize = 10.sp,
                                    fontWeight = FontWeight.Bold,
                                    color = Gold,
                                    modifier = Modifier.padding(start = 4.dp, top = 8.dp, bottom = 4.dp),
                                    letterSpacing = 1.sp
                                )
                                Column(verticalArrangement = Arrangement.spacedBy(4.dp)) {
                                    allDayEvents.forEach { ev ->
                                        val style = getCategoryStyle(ev.category)
                                        Card(
                                            modifier = Modifier.fillMaxWidth().clickable {
                                                val startD = parseEventDate(ev.start) ?: Date()
                                                val endD = ev.end?.let { parseEventDate(it) } ?: startD
                                                editingEventId = ev.id
                                                title = ev.title
                                                description = ev.description ?: ""
                                                selectedCategory = ev.category
                                                isAllDay = ev.is_all_day
                                                recurrence = ev.recurrence ?: "none"
                                                priority = ev.priority ?: "low"
                                                reminderMinutes = ev.reminder_minutes ?: -1
                                                startDateString = sdfDate.format(startD)
                                                endDateString = sdfDate.format(endD)
                                                startTimeString = SimpleDateFormat("HH:mm", Locale.US).format(startD)
                                                endTimeString = SimpleDateFormat("HH:mm", Locale.US).format(endD)
                                                showInlineCreator = true
                                            },
                                            colors = CardDefaults.cardColors(containerColor = style.bg.copy(alpha = 0.15f)),
                                            border = BorderStroke(1.dp, style.text.copy(alpha = 0.3f)),
                                            shape = RoundedCornerShape(8.dp)
                                        ) {
                                            Row(
                                                modifier = Modifier.padding(horizontal = 12.dp, vertical = 8.dp),
                                                verticalAlignment = Alignment.CenterVertically,
                                                horizontalArrangement = Arrangement.SpaceBetween
                                            ) {
                                                Row(
                                                    verticalAlignment = Alignment.CenterVertically,
                                                    modifier = Modifier.weight(1f)
                                                ) {
                                                    Icon(
                                                        imageVector = style.icon,
                                                        contentDescription = null,
                                                        tint = style.text,
                                                        modifier = Modifier.size(16.dp)
                                                    )
                                                    Spacer(modifier = Modifier.width(8.dp))
                                                    Column {
                                                        Text(
                                                            text = ev.title,
                                                            fontSize = 13.sp,
                                                            fontWeight = FontWeight.Bold,
                                                            color = AppTheme.colors.textPrimary
                                                        )
                                                        if (!ev.description.isNullOrBlank()) {
                                                            Text(
                                                                text = ev.description,
                                                                fontSize = 11.sp,
                                                                color = AppTheme.colors.textMuted,
                                                                modifier = Modifier.padding(top = 2.dp)
                                                            )
                                                        }
                                                    }
                                                }
                                                IconButton(onClick = { onDeleteEvent(ev.id) }, modifier = Modifier.size(24.dp)) {
                                                    Icon(
                                                        imageVector = Icons.Default.Delete,
                                                        contentDescription = "Termin löschen",
                                                        tint = Color.Red.copy(alpha = 0.7f),
                                                        modifier = Modifier.size(16.dp)
                                                    )
                                                }
                                            }
                                        }
                                    }
                                }
                            }

                            // Render 24-hour timeline
                            if (timedEvents.isNotEmpty()) {
                                Text(
                                    text = "ZEITLEISTE",
                                    fontSize = 10.sp,
                                    fontWeight = FontWeight.Bold,
                                    color = Gold,
                                    modifier = Modifier.padding(start = 4.dp, top = 8.dp, bottom = 4.dp),
                                    letterSpacing = 1.sp
                                )

                                val hourHeight = 64.dp
                                val timelineHeight = hourHeight * 24

                                Row(
                                    modifier = Modifier
                                        .fillMaxWidth()
                                        .height(timelineHeight),
                                    verticalAlignment = Alignment.Top
                                ) {
                                    // 1. Hour Labels Column (left side)
                                    Column(
                                        modifier = Modifier
                                            .width(55.dp)
                                            .fillMaxHeight()
                                    ) {
                                        for (h in 0..23) {
                                            Box(
                                                modifier = Modifier
                                                    .fillMaxWidth()
                                                    .height(hourHeight),
                                                contentAlignment = Alignment.TopEnd
                                            ) {
                                                Text(
                                                    text = "${h.toString().padStart(2, '0')}:00",
                                                    color = AppTheme.colors.textMuted,
                                                    fontSize = 11.sp,
                                                    fontWeight = FontWeight.Bold,
                                                    modifier = Modifier.padding(end = 8.dp, top = 6.dp)
                                                )
                                            }
                                        }
                                    }

                                    // 2. Schedule Grid Lines + Events Container (right side)
                                    Box(
                                        modifier = Modifier
                                            .weight(1f)
                                            .fillMaxHeight()
                                    ) {
                                        // Grid lines
                                        Column(modifier = Modifier.fillMaxSize()) {
                                            for (h in 0..23) {
                                                Box(
                                                    modifier = Modifier
                                                        .fillMaxWidth()
                                                        .height(hourHeight)
                                                ) {
                                                    Box(
                                                        modifier = Modifier
                                                            .fillMaxWidth()
                                                            .height(1.dp)
                                                            .background(AppTheme.colors.cardBorder.copy(alpha = 0.35f))
                                                    )
                                                }
                                            }
                                            // Bottom boundary line for 23:00 slot (at 24:00)
                                            Box(
                                                modifier = Modifier
                                                    .fillMaxWidth()
                                                    .height(1.dp)
                                                    .background(AppTheme.colors.cardBorder.copy(alpha = 0.35f))
                                            )
                                        }

                                        // Render timed events
                                        val positionedEvents = remember(timedEvents, selectedDate) {
                                            layoutTimedEvents(timedEvents, selectedDate)
                                        }

                                        positionedEvents.forEach { pEvent ->
                                            val ev = pEvent.event
                                            val style = getCategoryStyle(ev.category)

                                            // Calculate vertical offsets
                                            val startDp = (pEvent.startMin / 60f) * hourHeight.value
                                            val durationDp = ((pEvent.endMin - pEvent.startMin) / 60f) * hourHeight.value

                                            val colIndex = pEvent.colIndex
                                            val totalCols = pEvent.totalCols

                                            Row(
                                                modifier = Modifier
                                                    .fillMaxWidth()
                                                    .offset(y = startDp.dp)
                                                    .height(durationDp.dp)
                                                    .padding(horizontal = 2.dp),
                                                verticalAlignment = Alignment.Top
                                            ) {
                                                if (colIndex > 0) {
                                                    Spacer(modifier = Modifier.weight(colIndex.toFloat()))
                                                }

                                                Card(
                                                    modifier = Modifier
                                                        .weight(1f)
                                                        .fillMaxHeight()
                                                        .padding(horizontal = 2.dp, vertical = 1.dp)
                                                        .clickable {
                                                            val startD = parseEventDate(ev.start) ?: Date()
                                                            val endD = ev.end?.let { parseEventDate(it) } ?: startD
                                                            editingEventId = ev.id
                                                            title = ev.title
                                                            description = ev.description ?: ""
                                                            selectedCategory = ev.category
                                                            isAllDay = ev.is_all_day
                                                            recurrence = ev.recurrence ?: "none"
                                                            priority = ev.priority ?: "low"
                                                            reminderMinutes = ev.reminder_minutes ?: -1
                                                            startDateString = sdfDate.format(startD)
                                                            endDateString = sdfDate.format(endD)
                                                            startTimeString = SimpleDateFormat("HH:mm", Locale.US).format(startD)
                                                            endTimeString = SimpleDateFormat("HH:mm", Locale.US).format(endD)
                                                            showInlineCreator = true
                                                        },
                                                    colors = CardDefaults.cardColors(containerColor = style.bg.copy(alpha = 0.15f)),
                                                    border = BorderStroke(1.dp, style.text.copy(alpha = 0.3f)),
                                                    shape = RoundedCornerShape(6.dp)
                                                ) {
                                                    Row(
                                                        modifier = Modifier
                                                            .fillMaxSize()
                                                            .padding(horizontal = 8.dp, vertical = 2.dp),
                                                        verticalAlignment = Alignment.CenterVertically,
                                                        horizontalArrangement = Arrangement.SpaceBetween
                                                    ) {
                                                        Column(modifier = Modifier.weight(1f)) {
                                                            Text(
                                                                text = ev.title,
                                                                color = style.text,
                                                                fontSize = 11.sp,
                                                                fontWeight = FontWeight.Bold,
                                                                maxLines = 1,
                                                                overflow = androidx.compose.ui.text.style.TextOverflow.Ellipsis
                                                            )

                                                            val startD = parseEventDate(ev.start)
                                                            val endD = ev.end?.let { parseEventDate(it) }
                                                            val timeStr = if (startD != null) {
                                                                val timeFmt = SimpleDateFormat("HH:mm", Locale.GERMANY)
                                                                val startFmt = timeFmt.format(startD)
                                                                if (endD != null) {
                                                                    "$startFmt - ${timeFmt.format(endD)}"
                                                                } else {
                                                                    startFmt
                                                                }
                                                            } else {
                                                                ""
                                                            }
                                                            // Hide time range if vertical space is extremely limited (e.g. less than 35dp height)
                                                            if (timeStr.isNotEmpty() && durationDp >= 35f) {
                                                                Text(
                                                                    text = timeStr,
                                                                    color = AppTheme.colors.textMuted,
                                                                    fontSize = 9.sp,
                                                                    maxLines = 1
                                                                )
                                                            }
                                                        }

                                                        IconButton(
                                                            onClick = { onDeleteEvent(ev.id) },
                                                            modifier = Modifier.size(22.dp)
                                                        ) {
                                                            Icon(
                                                                imageVector = Icons.Default.Delete,
                                                                contentDescription = "Termin löschen",
                                                                tint = Color.Red.copy(alpha = 0.7f),
                                                                modifier = Modifier.size(14.dp)
                                                            )
                                                        }
                                                    }
                                                }

                                                if (totalCols - colIndex - 1 > 0) {
                                                    Spacer(modifier = Modifier.weight((totalCols - colIndex - 1).toFloat()))
                                                }
                                            }
                                        }
                                    }
                                }
                            }
                        }
                    }
                }
                Spacer(modifier = Modifier.height(100.dp))
            }

            // Floating Action Button (FAB) in Day View
            if (viewMode == CalendarViewMode.DAY) {
                FloatingActionButton(
                    onClick = {
                        if (showInlineCreator) {
                            showInlineCreator = false
                            editingEventId = null
                            title = ""
                            description = ""
                            selectedCategory = "general"
                            isAllDay = false
                            recurrence = "none"
                            priority = "low"
                            reminderMinutes = -1
                        } else {
                            showInlineCreator = true
                        }
                    },
                    containerColor = Gold,
                    contentColor = SpaceBlack,
                    modifier = Modifier
                        .align(Alignment.BottomEnd)
                        .padding(bottom = 16.dp, end = 16.dp)
                ) {
                    Icon(
                        imageVector = if (showInlineCreator) Icons.Default.Close else Icons.Default.Add,
                        contentDescription = "Termin hinzufügen"
                    )
                }
            }
    }
}

// --- ROUTINES TAB ---
private fun isRoutineCurrentlyRunning(startTimeStr: String, durationMinutes: Int): Boolean {
    try {
        val timeParts = startTimeStr.split(":")
        if (timeParts.isEmpty()) return false
        val startHour = timeParts[0].toIntOrNull() ?: return false
        val startMin = if (timeParts.size > 1) timeParts[1].toIntOrNull() ?: 0 else 0

        val now = java.time.LocalTime.now()
        val startLocalTime = java.time.LocalTime.of(startHour, startMin)
        val endLocalTime = startLocalTime.plusMinutes(durationMinutes.toLong())

        return if (endLocalTime.isBefore(startLocalTime)) {
            now.isAfter(startLocalTime) || now.isBefore(endLocalTime)
        } else {
            !now.isBefore(startLocalTime) && now.isBefore(endLocalTime)
        }
    } catch (e: Exception) {
        return false
    }
}

@OptIn(ExperimentalMaterial3Api::class)
@Composable
fun RoutinesTabContent(
    routines: List<ManagementDayRoutine>,
    onCreateRoutine: (title: String, message: String?, durationMinutes: Int, startTime: String, icon: String?) -> Unit,
    onUpdateRoutine: (id: String, title: String?, message: String?, durationMinutes: Int?, startTime: String?, icon: String?, isActive: Boolean?) -> Unit,
    onDeleteRoutine: (String) -> Unit,
    onAddStep: (routineId: String, title: String, durationMinutes: Int) -> Unit,
    onDeleteStep: (stepId: String) -> Unit
) {
    val context = androidx.compose.ui.platform.LocalContext.current
    var showRoutineDialog by remember { mutableStateOf(false) }
    var editingRoutine by remember { mutableStateOf<ManagementDayRoutine?>(null) }

    // Dialog state
    var routineTitle by remember { mutableStateOf("") }
    var routineMessage by remember { mutableStateOf("") }
    var routineStartTime by remember { mutableStateOf("08:00") }
    var routineDuration by remember { mutableStateOf("30") }
    var routineIcon by remember { mutableStateOf("sun") }

    var routineToDelete by remember { mutableStateOf<ManagementDayRoutine?>(null) }

    LaunchedEffect(Unit) {
        de.meinseelenfunke.app.util.FunkenwerkAlarmManager.ensureInitialized(context)
    }
    val alarms by de.meinseelenfunke.app.util.FunkenwerkAlarmManager.alarmsFlow.collectAsState()
    val wakeLogs by de.meinseelenfunke.app.util.FunkenwerkAlarmManager.wakeLogsFlow.collectAsState()

    val firstBaseStartTime = remember(routines) {
        routines.minByOrNull { it.start_time.take(5) }?.start_time?.take(5) ?: "07:00"
    }
    val routineShiftInfo = remember(routines, alarms, wakeLogs) {
        de.meinseelenfunke.app.util.FunkenwerkAlarmManager.computeTodayRoutineShift(firstBaseStartTime)
    }
    val shiftMinutes = routineShiftInfo?.first ?: 0
    val shiftWakeTime = routineShiftInfo?.second

    Column(verticalArrangement = Arrangement.spacedBy(12.dp)) {
        Row(
            modifier = Modifier.fillMaxWidth(),
            horizontalArrangement = Arrangement.SpaceBetween,
            verticalAlignment = Alignment.CenterVertically
        ) {
            Column(modifier = Modifier.weight(1f)) {
                Text(
                    text = "MEINE ROUTINEN",
                    fontSize = 12.sp,
                    fontWeight = FontWeight.Bold,
                    color = Gold,
                    letterSpacing = 1.sp
                )
                if (shiftWakeTime != null) {
                    Text(
                        text = "⏰ Getaktet auf Wecker ($shiftWakeTime Uhr)",
                        fontSize = 11.sp,
                        fontWeight = FontWeight.SemiBold,
                        color = Color(0xFF10B981)
                    )
                }
            }
            IconButton(onClick = {
                editingRoutine = null
                routineTitle = ""
                routineMessage = ""
                routineStartTime = "08:00"
                routineDuration = "30"
                routineIcon = "sun"
                showRoutineDialog = true
            }) {
                Icon(
                    imageVector = Icons.Default.Add,
                    contentDescription = "Routine erstellen",
                    tint = Gold
                )
            }
        }

        if (routines.isEmpty()) {
            Text(
                "Keine aktiven Routinen vorhanden.",
                color = AppTheme.colors.textMuted,
                fontSize = 14.sp,
                textAlign = TextAlign.Center,
                modifier = Modifier.fillMaxWidth().padding(top = 32.dp)
            )
        } else {
            routines.forEach { routine ->
                var expanded by remember { mutableStateOf(false) }
                val baseTimeShort = routine.start_time.take(5)
                val effectiveStartTime = if (shiftMinutes != 0) {
                    de.meinseelenfunke.app.util.FunkenwerkAlarmManager.shiftRoutineTimeString(baseTimeShort, shiftMinutes)
                } else {
                    baseTimeShort
                }
                val isCurrentlyRunning = isRoutineCurrentlyRunning(effectiveStartTime, routine.duration_minutes)

                Card(
                    modifier = Modifier.fillMaxWidth(),
                    colors = CardDefaults.cardColors(
                        containerColor = if (isCurrentlyRunning) Gold.copy(alpha = 0.08f) else AppTheme.colors.cardBackground
                    ),
                    border = BorderStroke(if (isCurrentlyRunning) 1.5.dp else 1.dp, if (isCurrentlyRunning) Gold else AppTheme.colors.cardBorder)
                ) {
                    Column(modifier = Modifier.padding(14.dp)) {
                        Row(
                            modifier = Modifier.fillMaxWidth(),
                            horizontalArrangement = Arrangement.SpaceBetween,
                            verticalAlignment = Alignment.CenterVertically
                        ) {
                            Row(
                                modifier = Modifier.weight(1f).clickable { expanded = !expanded },
                                verticalAlignment = Alignment.CenterVertically
                            ) {
                                Box(
                                    modifier = Modifier
                                        .size(36.dp)
                                        .clip(RoundedCornerShape(8.dp))
                                        .background(Gold.copy(alpha = 0.15f)),
                                    contentAlignment = Alignment.Center
                                ) {
                                    Icon(
                                        imageVector = mapRoutineIconToVector(routine.icon),
                                        contentDescription = null,
                                        tint = Gold,
                                        modifier = Modifier.size(18.dp)
                                    )
                                }
                                Spacer(modifier = Modifier.width(12.dp))
                                Column {
                                    Row(verticalAlignment = Alignment.CenterVertically) {
                                        Text(
                                            text = routine.title,
                                            fontSize = 14.sp,
                                            fontWeight = FontWeight.Bold,
                                            color = AppTheme.colors.textPrimary,
                                            modifier = Modifier.weight(1f, fill = false),
                                            maxLines = 1,
                                            overflow = androidx.compose.ui.text.style.TextOverflow.Ellipsis
                                        )
                                        if (isCurrentlyRunning) {
                                            Spacer(modifier = Modifier.width(6.dp))
                                            Box(
                                                modifier = Modifier
                                                    .background(Gold.copy(alpha = 0.2f), RoundedCornerShape(4.dp))
                                                    .padding(horizontal = 6.dp, vertical = 2.dp)
                                            ) {
                                                Text(
                                                    text = "AKTIV",
                                                    color = Gold,
                                                    fontSize = 9.sp,
                                                    fontWeight = FontWeight.Bold,
                                                    maxLines = 1
                                                )
                                            }
                                        }
                                    }
                                    Text(
                                        text = if (shiftMinutes != 0 && effectiveStartTime != baseTimeShort) {
                                            "Start: $effectiveStartTime Uhr (Basis $baseTimeShort) • ${routine.duration_minutes} Min."
                                        } else {
                                            "Start: $effectiveStartTime Uhr • ${routine.duration_minutes} Min."
                                        },
                                        fontSize = 11.sp,
                                        color = if (shiftMinutes != 0) Gold else AppTheme.colors.textMuted
                                    )
                                }
                            }

                            Row(verticalAlignment = Alignment.CenterVertically) {
                                IconButton(onClick = {
                                    editingRoutine = routine
                                    routineTitle = routine.title
                                    routineMessage = routine.message ?: ""
                                    routineStartTime = routine.start_time.take(5)
                                    routineDuration = routine.duration_minutes.toString()
                                    routineIcon = routine.icon ?: "sun"
                                    showRoutineDialog = true
                                }) {
                                    Icon(
                                        imageVector = Icons.Default.Edit,
                                        contentDescription = "Routine bearbeiten",
                                        tint = AppTheme.colors.textMuted,
                                        modifier = Modifier.size(18.dp)
                                    )
                                }
                                IconButton(onClick = { routineToDelete = routine }) {
                                    Icon(
                                        imageVector = Icons.Default.Delete,
                                        contentDescription = "Routine löschen",
                                        tint = Color.Red.copy(alpha = 0.7f),
                                        modifier = Modifier.size(18.dp)
                                    )
                                }
                                IconButton(onClick = { expanded = !expanded }) {
                                    Icon(
                                        imageVector = if (expanded) Icons.Default.ExpandLess else Icons.Default.ExpandMore,
                                        contentDescription = null,
                                        tint = Gold,
                                        modifier = Modifier.size(20.dp)
                                    )
                                }
                            }
                        }

                        AnimatedVisibility(visible = expanded) {
                            Column(
                                modifier = Modifier
                                    .fillMaxWidth()
                                    .padding(top = 12.dp),
                                verticalArrangement = Arrangement.spacedBy(6.dp)
                            ) {
                                Text(
                                    "Schritte der Routine:",
                                    fontSize = 12.sp,
                                    fontWeight = FontWeight.Bold,
                                    color = AppTheme.colors.textMuted
                                )
                                routine.steps.forEach { step ->
                                    Row(
                                        modifier = Modifier
                                            .fillMaxWidth()
                                            .clip(RoundedCornerShape(6.dp))
                                            .background(AppTheme.colors.glassSurface)
                                            .border(1.dp, AppTheme.colors.glassBorder, RoundedCornerShape(6.dp))
                                            .padding(8.dp),
                                        horizontalArrangement = Arrangement.SpaceBetween,
                                        verticalAlignment = Alignment.CenterVertically
                                    ) {
                                        Row(verticalAlignment = Alignment.CenterVertically) {
                                            Icon(
                                                imageVector = Icons.Default.KeyboardArrowRight,
                                                contentDescription = null,
                                                tint = Gold,
                                                modifier = Modifier.size(14.dp)
                                            )
                                            Spacer(modifier = Modifier.width(4.dp))
                                            Text(step.title, fontSize = 13.sp, color = AppTheme.colors.textPrimary)
                                        }
                                        Row(verticalAlignment = Alignment.CenterVertically) {
                                            Text(
                                                text = "${step.duration_minutes} Min",
                                                fontSize = 11.sp,
                                                color = AppTheme.colors.textMuted
                                            )
                                            Spacer(modifier = Modifier.width(8.dp))
                                            IconButton(
                                                onClick = { onDeleteStep(step.id) },
                                                modifier = Modifier.size(24.dp)
                                            ) {
                                                Icon(
                                                    imageVector = Icons.Default.Delete,
                                                    contentDescription = "Schritt löschen",
                                                    tint = Color.Red.copy(alpha = 0.7f),
                                                    modifier = Modifier.size(14.dp)
                                                )
                                            }
                                        }
                                    }
                                }

                                // Add new step inline form
                                var newStepTitle by remember { mutableStateOf("") }
                                var newStepDuration by remember { mutableStateOf("5") }

                                Row(
                                    modifier = Modifier
                                        .fillMaxWidth()
                                        .padding(top = 8.dp),
                                    verticalAlignment = Alignment.CenterVertically,
                                    horizontalArrangement = Arrangement.spacedBy(8.dp)
                                ) {
                                    OutlinedTextField(
                                        value = newStepTitle,
                                        onValueChange = { newStepTitle = it },
                                        label = { Text("Neuer Schritt...", fontSize = 11.sp) },
                                        colors = OutlinedTextFieldDefaults.colors(
                                            focusedBorderColor = Gold,
                                            unfocusedBorderColor = AppTheme.colors.inputBorder,
                                            focusedTextColor = AppTheme.colors.textPrimary,
                                            unfocusedTextColor = AppTheme.colors.textPrimary,
                                            focusedContainerColor = AppTheme.colors.inputBackground,
                                            unfocusedContainerColor = AppTheme.colors.inputBackground,
                                            focusedLabelColor = Gold,
                                            unfocusedLabelColor = AppTheme.colors.textMuted
                                        ),
                                        modifier = Modifier.weight(1f),
                                        singleLine = true
                                    )
                                    OutlinedTextField(
                                        value = newStepDuration,
                                        onValueChange = { newStepDuration = it },
                                        label = { Text("Min.", fontSize = 11.sp) },
                                        colors = OutlinedTextFieldDefaults.colors(
                                            focusedBorderColor = Gold,
                                            unfocusedBorderColor = AppTheme.colors.inputBorder,
                                            focusedTextColor = AppTheme.colors.textPrimary,
                                            unfocusedTextColor = AppTheme.colors.textPrimary,
                                            focusedContainerColor = AppTheme.colors.inputBackground,
                                            unfocusedContainerColor = AppTheme.colors.inputBackground,
                                            focusedLabelColor = Gold,
                                            unfocusedLabelColor = AppTheme.colors.textMuted
                                        ),
                                        modifier = Modifier.width(70.dp),
                                        singleLine = true
                                    )
                                    IconButton(
                                        onClick = {
                                            if (newStepTitle.isNotBlank()) {
                                                val dur = newStepDuration.toIntOrNull() ?: 5
                                                onAddStep(routine.id, newStepTitle, dur)
                                                newStepTitle = ""
                                            }
                                        },
                                        enabled = newStepTitle.isNotBlank()
                                    ) {
                                        Icon(
                                            imageVector = Icons.Default.Add,
                                            contentDescription = "Schritt hinzufügen",
                                            tint = Gold
                                        )
                                    }
                                }
                            }
                        }
                    }
                }
            }
        }
    }

    // Dialog for Routine Creation / Edition
    if (showRoutineDialog) {
        AlertDialog(
            onDismissRequest = { showRoutineDialog = false },
            title = {
                Text(
                    text = if (editingRoutine == null) "Routine erstellen" else "Routine bearbeiten",
                    color = AppTheme.colors.textPrimary,
                    fontWeight = FontWeight.Bold
                )
            },
            text = {
                Column(verticalArrangement = Arrangement.spacedBy(12.dp)) {
                    OutlinedTextField(
                        value = routineTitle,
                        onValueChange = { routineTitle = it },
                        label = { Text("Titel") },
                        colors = OutlinedTextFieldDefaults.colors(
                            focusedBorderColor = Gold,
                            unfocusedBorderColor = AppTheme.colors.inputBorder,
                            focusedTextColor = AppTheme.colors.textPrimary,
                            unfocusedTextColor = AppTheme.colors.textPrimary,
                            focusedContainerColor = AppTheme.colors.inputBackground,
                            unfocusedContainerColor = AppTheme.colors.inputBackground,
                            focusedLabelColor = Gold,
                            unfocusedLabelColor = AppTheme.colors.textMuted
                        ),
                        modifier = Modifier.fillMaxWidth()
                    )
                    OutlinedTextField(
                        value = routineMessage,
                        onValueChange = { routineMessage = it },
                        label = { Text("Beschreibung (optional)") },
                        colors = OutlinedTextFieldDefaults.colors(
                            focusedBorderColor = Gold,
                            unfocusedBorderColor = AppTheme.colors.inputBorder,
                            focusedTextColor = AppTheme.colors.textPrimary,
                            unfocusedTextColor = AppTheme.colors.textPrimary,
                            focusedContainerColor = AppTheme.colors.inputBackground,
                            unfocusedContainerColor = AppTheme.colors.inputBackground,
                            focusedLabelColor = Gold,
                            unfocusedLabelColor = AppTheme.colors.textMuted
                        ),
                        modifier = Modifier.fillMaxWidth()
                    )
                    
                    // Start Time selector with TimePickerDialog
                    Row(
                        modifier = Modifier.fillMaxWidth(),
                        verticalAlignment = Alignment.CenterVertically
                    ) {
                        OutlinedTextField(
                            value = routineStartTime,
                            onValueChange = { routineStartTime = it },
                            label = { Text("Startzeit (z.B. 08:00)") },
                            colors = OutlinedTextFieldDefaults.colors(
                                focusedBorderColor = Gold,
                                unfocusedBorderColor = AppTheme.colors.inputBorder,
                                focusedTextColor = AppTheme.colors.textPrimary,
                                unfocusedTextColor = AppTheme.colors.textPrimary,
                                focusedContainerColor = AppTheme.colors.inputBackground,
                                unfocusedContainerColor = AppTheme.colors.inputBackground,
                                focusedLabelColor = Gold,
                                unfocusedLabelColor = AppTheme.colors.textMuted
                            ),
                            modifier = Modifier.weight(1f),
                            readOnly = true
                        )
                        Spacer(modifier = Modifier.width(8.dp))
                        Button(
                            onClick = {
                                val parts = routineStartTime.split(":")
                                val initialHour = parts.getOrNull(0)?.toIntOrNull() ?: 8
                                val initialMin = parts.getOrNull(1)?.toIntOrNull() ?: 0
                                android.app.TimePickerDialog(
                                    context,
                                    { _, hourOfDay, minute ->
                                        routineStartTime = String.format(Locale.US, "%02d:%02d", hourOfDay, minute)
                                    },
                                    initialHour,
                                    initialMin,
                                    true
                                ).show()
                            },
                            colors = ButtonDefaults.buttonColors(containerColor = Gold, contentColor = SpaceBlack)
                        ) {
                            Text("Uhrzeit")
                        }
                    }

                    OutlinedTextField(
                        value = routineDuration,
                        onValueChange = { routineDuration = it },
                        label = { Text("Dauer (Minuten)") },
                        colors = OutlinedTextFieldDefaults.colors(
                            focusedBorderColor = Gold,
                            unfocusedBorderColor = AppTheme.colors.inputBorder,
                            focusedTextColor = AppTheme.colors.textPrimary,
                            unfocusedTextColor = AppTheme.colors.textPrimary,
                            focusedContainerColor = AppTheme.colors.inputBackground,
                            unfocusedContainerColor = AppTheme.colors.inputBackground,
                            focusedLabelColor = Gold,
                            unfocusedLabelColor = AppTheme.colors.textMuted
                        ),
                        modifier = Modifier.fillMaxWidth()
                    )

                    // Icon selector
                    Column {
                        Text("Icon auswählen:", fontSize = 12.sp, color = AppTheme.colors.textMuted, modifier = Modifier.padding(bottom = 6.dp))
                        Row(
                            horizontalArrangement = Arrangement.spacedBy(16.dp),
                            modifier = Modifier.fillMaxWidth()
                        ) {
                            listOf("sun" to Icons.Default.Schedule, "work" to Icons.Default.Bookmark, "star" to Icons.Default.Star, "sport" to Icons.Default.CheckCircle).forEach { (name, vector) ->
                                val selected = routineIcon == name
                                Box(
                                    modifier = Modifier
                                        .size(40.dp)
                                        .clip(RoundedCornerShape(8.dp))
                                        .background(if (selected) Gold.copy(alpha = 0.3f) else AppTheme.colors.glassSurface)
                                        .border(1.dp, if (selected) Gold else AppTheme.colors.glassBorder, RoundedCornerShape(8.dp))
                                        .clickable { routineIcon = name },
                                    contentAlignment = Alignment.Center
                                ) {
                                    Icon(imageVector = vector, contentDescription = null, tint = if (selected) Gold else AppTheme.colors.textMuted)
                                }
                            }
                        }
                    }
                }
            },
            confirmButton = {
                Button(
                    onClick = {
                        val dur = routineDuration.toIntOrNull() ?: 30
                        val formatStartTime = if (routineStartTime.length == 5) "$routineStartTime:00" else routineStartTime
                        if (editingRoutine == null) {
                            onCreateRoutine(routineTitle, routineMessage.ifBlank { null }, dur, formatStartTime, routineIcon)
                        } else {
                            onUpdateRoutine(editingRoutine!!.id, routineTitle, routineMessage, dur, formatStartTime, routineIcon, true)
                        }
                        showRoutineDialog = false
                    },
                    colors = ButtonDefaults.buttonColors(containerColor = Gold, contentColor = SpaceBlack)
                ) {
                    Text(if (editingRoutine == null) "Hinzufügen" else "Speichern")
                }
            },
            dismissButton = {
                TextButton(onClick = { showRoutineDialog = false }) {
                    Text("Abbrechen", color = AppTheme.colors.textMuted)
                }
            },
            containerColor = AppTheme.colors.cardBackground,
            titleContentColor = AppTheme.colors.textPrimary,
            textContentColor = AppTheme.colors.textSecondary
        )
    }

    // Delete confirmation dialog
    if (routineToDelete != null) {
        AlertDialog(
            onDismissRequest = { routineToDelete = null },
            title = { Text(text = "Routine löschen", color = AppTheme.colors.textPrimary, fontWeight = FontWeight.Bold) },
            text = { Text(text = "Möchtest du die Routine '${routineToDelete?.title}' wirklich löschen? Alle darin enthaltenen Schritte werden ebenfalls unwiderruflich gelöscht.", color = AppTheme.colors.textSecondary) },
            confirmButton = {
                TextButton(
                    onClick = {
                        routineToDelete?.let { onDeleteRoutine(it.id) }
                        routineToDelete = null
                    }
                ) {
                    Text("Ja, löschen", color = Color.Red, fontWeight = FontWeight.Bold)
                }
            },
            dismissButton = {
                TextButton(onClick = { routineToDelete = null }) {
                    Text("Abbrechen", color = AppTheme.colors.textMuted)
                }
            },
            containerColor = AppTheme.colors.cardBackground,
            titleContentColor = AppTheme.colors.textPrimary,
            textContentColor = AppTheme.colors.textSecondary
        )
    }
}

// --- SHOPPING TAB ---
@Composable
fun ShoppingTabContent(
    items: List<ManagementShoppingItem>,
    onAddItem: (String, Int) -> Unit,
    onToggleItem: (String) -> Unit,
    onDeleteItem: (String) -> Unit,
    onUpdateQuantity: (String, Int) -> Unit
) {
    var newItemName by remember { mutableStateOf("") }
    var newQuantity by remember { mutableIntStateOf(1) }

    // Input form
    Card(
        modifier = Modifier.fillMaxWidth(),
        colors = CardDefaults.cardColors(containerColor = AppTheme.colors.cardBackground),
        border = BorderStroke(1.dp, AppTheme.colors.cardBorder)
    ) {
        Row(
            modifier = Modifier.padding(12.dp),
            verticalAlignment = Alignment.CenterVertically,
            horizontalArrangement = Arrangement.spacedBy(8.dp)
        ) {
            OutlinedTextField(
                value = newItemName,
                onValueChange = { newItemName = it },
                label = { Text("Neuer Artikel") },
                colors = OutlinedTextFieldDefaults.colors(
                    focusedBorderColor = Gold,
                    unfocusedBorderColor = AppTheme.colors.inputBorder,
                    focusedTextColor = AppTheme.colors.textPrimary,
                    unfocusedTextColor = AppTheme.colors.textPrimary,
                    focusedContainerColor = AppTheme.colors.inputBackground,
                    unfocusedContainerColor = AppTheme.colors.inputBackground,
                    focusedLabelColor = Gold,
                    unfocusedLabelColor = AppTheme.colors.textMuted
                ),
                modifier = Modifier.weight(1f)
            )

            // Quantity stepper for new item
            Row(
                verticalAlignment = Alignment.CenterVertically,
                modifier = Modifier
                    .background(AppTheme.colors.glassSurface, RoundedCornerShape(8.dp))
                    .border(1.dp, AppTheme.colors.glassBorder, RoundedCornerShape(8.dp))
                    .padding(horizontal = 4.dp, vertical = 6.dp)
            ) {
                Text(
                    "-",
                    color = if (newQuantity > 1) Gold else AppTheme.colors.textMuted,
                    fontWeight = FontWeight.Bold,
                    fontSize = 16.sp,
                    modifier = Modifier
                        .clickable(enabled = newQuantity > 1) { newQuantity-- }
                        .padding(horizontal = 6.dp)
                )
                Text(
                    "${newQuantity}x",
                    color = AppTheme.colors.textPrimary,
                    fontWeight = FontWeight.Bold,
                    fontSize = 12.sp,
                    modifier = Modifier.padding(horizontal = 2.dp)
                )
                Text(
                    "+",
                    color = Gold,
                    fontWeight = FontWeight.Bold,
                    fontSize = 16.sp,
                    modifier = Modifier
                        .clickable { newQuantity++ }
                        .padding(horizontal = 6.dp)
                )
            }

            Button(
                onClick = {
                    onAddItem(newItemName, newQuantity)
                    newItemName = ""
                    newQuantity = 1
                },
                enabled = newItemName.isNotBlank(),
                colors = ButtonDefaults.buttonColors(containerColor = Gold, contentColor = SpaceBlack),
                shape = RoundedCornerShape(8.dp)
            ) {
                Icon(imageVector = Icons.Default.Add, contentDescription = "Hinzufügen")
            }
        }
    }

    Spacer(modifier = Modifier.height(16.dp))

    // List of shopping items categorized by needed vs stocked
    if (items.isEmpty()) {
        Text(
            "Einkaufsliste ist leer.",
            color = AppTheme.colors.textMuted,
            fontSize = 14.sp,
            textAlign = TextAlign.Center,
            modifier = Modifier.fillMaxWidth().padding(top = 32.dp)
        )
    } else {
        val neededItems = items.filter { it.status == "needed" }
        val stockedItems = items.filter { it.status == "stocked" }

        Column(verticalArrangement = Arrangement.spacedBy(16.dp)) {
            // Needed Items Section
            if (neededItems.isNotEmpty()) {
                Column(verticalArrangement = Arrangement.spacedBy(8.dp)) {
                    Text(
                        text = "WIRD BENÖTIGT",
                        fontSize = 11.sp,
                        fontWeight = FontWeight.Bold,
                        color = Gold,
                        letterSpacing = 1.sp
                    )
                    neededItems.forEach { item ->
                        ShoppingItemRow(item = item, onToggle = onToggleItem, onDelete = onDeleteItem, onUpdateQuantity = onUpdateQuantity)
                    }
                }
            }

            // Stocked Items Section
            if (stockedItems.isNotEmpty()) {
                Column(verticalArrangement = Arrangement.spacedBy(8.dp)) {
                    Text(
                        text = "BEREITS GEKAUFT",
                        fontSize = 11.sp,
                        fontWeight = FontWeight.Bold,
                        color = AppTheme.colors.textMuted,
                        letterSpacing = 1.sp
                    )
                    stockedItems.forEach { item ->
                        ShoppingItemRow(item = item, onToggle = onToggleItem, onDelete = onDeleteItem, onUpdateQuantity = onUpdateQuantity)
                    }
                }
            }
        }
    }
}

@Composable
fun ShoppingItemRow(
    item: ManagementShoppingItem,
    onToggle: (String) -> Unit,
    onDelete: (String) -> Unit,
    onUpdateQuantity: (String, Int) -> Unit
) {
    Card(
        modifier = Modifier.fillMaxWidth(),
        colors = CardDefaults.cardColors(containerColor = AppTheme.colors.cardBackground),
        border = BorderStroke(1.dp, AppTheme.colors.cardBorder)
    ) {
        Row(
            modifier = Modifier.padding(10.dp),
            verticalAlignment = Alignment.CenterVertically,
            horizontalArrangement = Arrangement.SpaceBetween
        ) {
            Row(
                verticalAlignment = Alignment.CenterVertically,
                modifier = Modifier.weight(1f)
            ) {
                Checkbox(
                    checked = item.status == "stocked",
                    onCheckedChange = { onToggle(item.id) },
                    colors = CheckboxDefaults.colors(
                        checkedColor = Gold,
                        checkmarkColor = SpaceBlack,
                        uncheckedColor = AppTheme.colors.textMuted
                    )
                )
                Spacer(modifier = Modifier.width(8.dp))
                Column {
                    Text(
                        text = item.name,
                        fontSize = 14.sp,
                        color = if (item.status == "stocked") AppTheme.colors.textMuted else AppTheme.colors.textPrimary,
                        textDecoration = if (item.status == "stocked") TextDecoration.LineThrough else TextDecoration.None
                    )
                    item.category?.let {
                        Text(it.name, fontSize = 11.sp, color = AppTheme.colors.textMuted)
                    }
                }
            }

            // Quantity stepper and delete action
            Row(
                verticalAlignment = Alignment.CenterVertically,
                horizontalArrangement = Arrangement.spacedBy(6.dp)
            ) {
                if (item.status == "needed") {
                    Box(
                        modifier = Modifier
                            .size(24.dp)
                            .background(AppTheme.colors.glassSurface, RoundedCornerShape(4.dp))
                            .border(0.5.dp, AppTheme.colors.glassBorder, RoundedCornerShape(4.dp))
                            .clickable { if (item.quantity > 1) onUpdateQuantity(item.id, item.quantity - 1) },
                        contentAlignment = Alignment.Center
                    ) {
                        Text("-", color = AppTheme.colors.textPrimary, fontSize = 14.sp, fontWeight = FontWeight.Bold)
                    }

                    Text(
                        "${item.quantity}x",
                        color = Gold,
                        fontSize = 12.sp,
                        fontWeight = FontWeight.Bold,
                        modifier = Modifier.padding(horizontal = 2.dp)
                    )

                    Box(
                        modifier = Modifier
                            .size(24.dp)
                            .background(AppTheme.colors.glassSurface, RoundedCornerShape(4.dp))
                            .border(0.5.dp, AppTheme.colors.glassBorder, RoundedCornerShape(4.dp))
                            .clickable { onUpdateQuantity(item.id, item.quantity + 1) },
                        contentAlignment = Alignment.Center
                    ) {
                        Text("+", color = AppTheme.colors.textPrimary, fontSize = 14.sp, fontWeight = FontWeight.Bold)
                    }
                } else {
                    Text(
                        "${item.quantity}x",
                        color = AppTheme.colors.textMuted,
                        fontSize = 12.sp,
                        fontWeight = FontWeight.Bold,
                        modifier = Modifier.padding(horizontal = 4.dp)
                    )
                }

                IconButton(onClick = { onDelete(item.id) }, modifier = Modifier.size(32.dp)) {
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



// --- LINK PARSING COMPOSE COMPONENT ---
@Composable
fun LinkableText(
    text: String,
    baseColor: Color,
    textDecoration: TextDecoration = TextDecoration.None,
    fontSize: androidx.compose.ui.unit.TextUnit = 14.sp,
    fontWeight: FontWeight = FontWeight.Normal
) {
    val uriHandler = LocalUriHandler.current
    val annotatedString = remember(text, baseColor) {
        buildAnnotatedString {
            val urlRegex = "(https?://[\\w\\d:#@%/;$~_?\\+-=\\\\\\.&]+)".toRegex()
            var lastIndex = 0

            urlRegex.findAll(text).forEach { matchResult ->
                val start = matchResult.range.first
                val end = matchResult.range.last + 1

                if (start > lastIndex) {
                    append(text.substring(lastIndex, start))
                }

                pushStringAnnotation(tag = "URL", annotation = matchResult.value)
                withStyle(
                    style = SpanStyle(
                        color = Gold,
                        textDecoration = TextDecoration.Underline,
                        fontWeight = FontWeight.Bold
                    )
                ) {
                    append(matchResult.value)
                }
                pop()
                lastIndex = end
            }

            if (lastIndex < text.length) {
                append(text.substring(lastIndex))
            }
        }
    }

    ClickableText(
        text = annotatedString,
        style = TextStyle(
            color = baseColor,
            fontSize = fontSize,
            fontWeight = fontWeight,
            textDecoration = textDecoration
        ),
        onClick = { offset ->
            annotatedString.getStringAnnotations(tag = "URL", start = offset, end = offset)
                .firstOrNull()?.let { annotation ->
                    try {
                        uriHandler.openUri(annotation.item)
                    } catch (e: Exception) {
                        // Ignore error
                    }
                }
        }
    )
}

// --- HELPER UTILITIES ---

fun parseHexColor(hex: String?, fallback: Color = Gold): Color {
    if (hex.isNullOrBlank()) return fallback
    return try {
        Color(android.graphics.Color.parseColor(hex))
    } catch (e: Exception) {
        fallback
    }
}

fun mapIconStringToVector(iconName: String?): ImageVector {
    return when (iconName?.lowercase(Locale.ROOT)) {
        "bookmark" -> Icons.Default.Bookmark
        "star" -> Icons.Default.Star
        "heart" -> Icons.Default.Favorite
        "bolt" -> Icons.Default.Bolt
        "home" -> Icons.Default.Home
        "briefcase" -> Icons.Default.Work
        "shopping-bag" -> Icons.Default.ShoppingBag
        "trophy" -> Icons.Default.EmojiEvents
        "sun" -> Icons.Default.WbSunny
        "moon" -> Icons.Default.NightsStay
        "wrench" -> Icons.Default.Build
        "rocket-launch" -> Icons.Default.RocketLaunch
        "tag" -> Icons.Default.LocalOffer
        "flag" -> Icons.Default.Flag
        // Keep older/fallback mappings
        "speaker-wave", "speaker", "volume-up" -> Icons.Default.Campaign
        "sparkles", "sparkle" -> Icons.Default.Star
        "cube", "box" -> Icons.Default.Bookmark
        "shopping-cart", "shopping" -> Icons.Default.ShoppingCart
        "calendar" -> Icons.Default.CalendarToday
        "list" -> Icons.Default.List
        else -> Icons.Default.List
    }
}

fun mapRoutineIconToVector(iconName: String?): ImageVector {
    return when (iconName?.lowercase(Locale.ROOT)) {
        "sun", "break" -> Icons.Default.Schedule
        "briefcase", "work", "computer-desktop" -> Icons.Default.Bookmark
        "sparkles", "hygiene", "star" -> Icons.Default.Star
        "trophy", "sport" -> Icons.Default.CheckCircle
        "fire" -> Icons.Default.Campaign
        "moon", "sleep" -> Icons.Default.Schedule
        "cake", "food" -> Icons.Default.ShoppingCart
        else -> Icons.Default.Schedule
    }
}

data class CategoryStyle(
    val label: String,
    val bg: Color,
    val text: Color,
    val icon: ImageVector
)

fun getGridTextColor(bgColor: Color): Color {
    val red = bgColor.red
    val green = bgColor.green
    val blue = bgColor.blue
    val brightness = 0.299f * red + 0.587f * green + 0.114f * blue
    return if (brightness > 0.55f) {
        Color(0xFF0F172A) // Dark slate for light backgrounds
    } else {
        Color.White // White for dark backgrounds
    }
}

fun getCategoryStyle(category: String): CategoryStyle {
    return when (category.lowercase(Locale.ROOT)) {
        "restmuell" -> CategoryStyle("Restmüll", Color(0xFF1F2937), Color(0xFFF3F4F6), Icons.Default.Delete)
        "altpapier" -> CategoryStyle("Papier", Color(0x333B82F6), Color(0xFF60A5FA), Icons.Default.Bookmark)
        "biomuell" -> CategoryStyle("Bio", Color(0x5578350F), Color(0xFFF59E0B), Icons.Default.Delete)
        "gelber_sack" -> CategoryStyle("Gelber Sack", Color(0x33F59E0B), Color(0xFFFBBF24), Icons.Default.ShoppingCart)
        "schadstoffe" -> CategoryStyle("Schadstoffe", Color(0x33EF4444), Color(0xFFF87171), Icons.Default.Delete)
        "sperrmuell" -> CategoryStyle("Sperrmüll", Color(0x33F97316), Color(0xFFFB923C), Icons.Default.Schedule)
        "gruen" -> CategoryStyle("Grünabfall", Color(0x3310B981), Color(0xFF34D399), Icons.Default.Delete)
        "baum" -> CategoryStyle("Tannenbaum", Color(0x3314B8A6), Color(0xFF2DD4BF), Icons.Default.Star)
        "call" -> CategoryStyle("Anrufe", Color(0x33D946EF), Color(0xFFE879F9), Icons.Default.Campaign)
        "meeting" -> CategoryStyle("Besprechung", Color(0x336366F1), Color(0xFF818CF8), Icons.Default.Schedule)
        "birthday" -> CategoryStyle("Geburtstag", Color(0x33EC4899), Color(0xFFF472B6), Icons.Default.Star)
        "vacation" -> CategoryStyle("Feiertage", Color(0x3306B6D4), Color(0xFF22D3EE), Icons.Default.Star)
        "travel" -> CategoryStyle("Reise", Color(0x33F59E0B), Color(0xFFFBBF24), Icons.Default.Bookmark)
        "project" -> CategoryStyle("Projekte", Color(0x33C5A059), Color(0xFFC5A059), Icons.Default.Bookmark)
        "customer" -> CategoryStyle("Kunde", Color(0x1A10B981), Color(0xFF10B981), Icons.Default.AccountCircle)
        else -> CategoryStyle("Termin", Color(0xFF1F2937), Color(0xFF9CA3AF), Icons.Default.CalendarToday)
    }
}

data class CalendarDay(
    val date: Date,
    val isCurrentMonth: Boolean,
    val dayNumber: Int,
    val month: Int,
    val year: Int
)

fun getCalendarGrid(year: Int, month: Int): List<CalendarDay> {
    val cal = Calendar.getInstance()
    cal.set(Calendar.YEAR, year)
    cal.set(Calendar.MONTH, month)
    cal.set(Calendar.DAY_OF_MONTH, 1)

    val firstDayOfWeek = cal.get(Calendar.DAY_OF_WEEK)
    val prefixDays = when (firstDayOfWeek) {
        Calendar.MONDAY -> 0
        Calendar.TUESDAY -> 1
        Calendar.WEDNESDAY -> 2
        Calendar.THURSDAY -> 3
        Calendar.FRIDAY -> 4
        Calendar.SATURDAY -> 5
        Calendar.SUNDAY -> 6
        else -> 0
    }

    val daysInMonth = cal.getActualMaximum(Calendar.DAY_OF_MONTH)
    val list = mutableListOf<CalendarDay>()

    // Days of previous month
    val prevCal = cal.clone() as Calendar
    prevCal.add(Calendar.MONTH, -1)
    val prevMonthDays = prevCal.getActualMaximum(Calendar.DAY_OF_MONTH)
    for (i in prefixDays - 1 downTo 0) {
        val d = prevMonthDays - i
        val dayCal = prevCal.clone() as Calendar
        dayCal.set(Calendar.DAY_OF_MONTH, d)
        list.add(CalendarDay(dayCal.time, false, d, prevCal.get(Calendar.MONTH), prevCal.get(Calendar.YEAR)))
    }

    // Days of current month
    for (d in 1..daysInMonth) {
        val dayCal = cal.clone() as Calendar
        dayCal.set(Calendar.DAY_OF_MONTH, d)
        list.add(CalendarDay(dayCal.time, true, d, month, year))
    }

    // Days of next month to complete the 42 cells grid
    val nextCal = cal.clone() as Calendar
    nextCal.add(Calendar.MONTH, 1)
    val remaining = 42 - list.size
    for (d in 1..remaining) {
        val dayCal = nextCal.clone() as Calendar
        dayCal.set(Calendar.DAY_OF_MONTH, d)
        list.add(CalendarDay(dayCal.time, false, d, nextCal.get(Calendar.MONTH), nextCal.get(Calendar.YEAR)))
    }

    return list
}

private fun parseEventDate(dateStr: String): Date? {
    if (dateStr.isBlank()) return null
    try {
        if (dateStr.contains("T")) {
            return try {
                val odt = java.time.OffsetDateTime.parse(dateStr)
                Date(odt.toInstant().toEpochMilli())
            } catch (e: Exception) {
                try {
                    val ldt = java.time.LocalDateTime.parse(dateStr)
                    val zdt = ldt.atZone(java.time.ZoneId.systemDefault())
                    Date(zdt.toInstant().toEpochMilli())
                } catch (e2: Exception) {
                    val sdf = SimpleDateFormat("yyyy-MM-dd'T'HH:mm:ss", Locale.US).apply {
                        timeZone = TimeZone.getTimeZone("UTC")
                    }
                    sdf.parse(dateStr)
                }
            }
        } else {
            return SimpleDateFormat("yyyy-MM-dd", Locale.US).parse(dateStr)
        }
    } catch (e: Exception) {
        return null
    }
}

fun getEventsForDay(day: CalendarDay, events: List<CalendarEvent>): List<CalendarEvent> {
    return events.filter { event ->
        val startD = parseEventDate(event.start) ?: return@filter false
        val endD = if (!event.end.isNullOrBlank()) parseEventDate(event.end) else startD
        if (endD == null) return@filter false

        val checkCal = Calendar.getInstance().apply {
            time = day.date
            set(Calendar.HOUR_OF_DAY, 12)
            set(Calendar.MINUTE, 0)
            set(Calendar.SECOND, 0)
            set(Calendar.MILLISECOND, 0)
        }
        val startMidnight = Calendar.getInstance().apply {
            time = startD
            set(Calendar.HOUR_OF_DAY, 0)
            set(Calendar.MINUTE, 0)
            set(Calendar.SECOND, 0)
            set(Calendar.MILLISECOND, 0)
        }
        val endMidnight = Calendar.getInstance().apply {
            time = endD
            set(Calendar.HOUR_OF_DAY, 23)
            set(Calendar.MINUTE, 59)
            set(Calendar.SECOND, 59)
            set(Calendar.MILLISECOND, 999)
        }

        checkCal.timeInMillis in startMidnight.timeInMillis..endMidnight.timeInMillis
    }
}

fun isTodayDate(date: Date): Boolean {
    val cal = Calendar.getInstance()
    val today = cal.time
    return isSameDay(date, today)
}

fun isSameDay(d1: Date, d2: Date): Boolean {
    val cal1 = Calendar.getInstance().apply { time = d1 }
    val cal2 = Calendar.getInstance().apply { time = d2 }
    return cal1.get(Calendar.YEAR) == cal2.get(Calendar.YEAR) &&
           cal1.get(Calendar.DAY_OF_YEAR) == cal2.get(Calendar.DAY_OF_YEAR)
}

fun getEventsOverlappingWeek(week: List<CalendarDay>, events: List<CalendarEvent>): List<CalendarEvent> {
    if (week.isEmpty()) return emptyList()
    val weekStart = week.first().date
    val weekEnd = week.last().date
    
    val weekStartCal = Calendar.getInstance().apply {
        time = weekStart
        set(Calendar.HOUR_OF_DAY, 0)
        set(Calendar.MINUTE, 0)
        set(Calendar.SECOND, 0)
        set(Calendar.MILLISECOND, 0)
    }
    
    val weekEndCal = Calendar.getInstance().apply {
        time = weekEnd
        set(Calendar.HOUR_OF_DAY, 23)
        set(Calendar.MINUTE, 59)
        set(Calendar.SECOND, 59)
        set(Calendar.MILLISECOND, 999)
    }
    
    return events.filter { event ->
        val startD = parseEventDate(event.start) ?: return@filter false
        val endD = if (!event.end.isNullOrBlank()) parseEventDate(event.end) else startD
        if (endD == null) return@filter false
        
        val startMidnight = Calendar.getInstance().apply {
            time = startD
            set(Calendar.HOUR_OF_DAY, 0)
            set(Calendar.MINUTE, 0)
            set(Calendar.SECOND, 0)
            set(Calendar.MILLISECOND, 0)
        }
        val endMidnight = Calendar.getInstance().apply {
            time = endD
            set(Calendar.HOUR_OF_DAY, 23)
            set(Calendar.MINUTE, 59)
            set(Calendar.SECOND, 59)
            set(Calendar.MILLISECOND, 999)
        }
        
        startMidnight.timeInMillis <= weekEndCal.timeInMillis && endMidnight.timeInMillis >= weekStartCal.timeInMillis
    }
}

fun getEventRangeInWeek(event: CalendarEvent, week: List<CalendarDay>): Pair<Int, Int> {
    if (week.isEmpty()) return Pair(0, 0)
    val startD = parseEventDate(event.start) ?: return Pair(0, 0)
    val endD = if (!event.end.isNullOrBlank()) parseEventDate(event.end) else startD
    
    val startMidnight = Calendar.getInstance().apply {
        time = startD
        set(Calendar.HOUR_OF_DAY, 0)
        set(Calendar.MINUTE, 0)
        set(Calendar.SECOND, 0)
        set(Calendar.MILLISECOND, 0)
    }
    
    val endMidnight = Calendar.getInstance().apply {
        time = endD ?: startD
        set(Calendar.HOUR_OF_DAY, 23)
        set(Calendar.MINUTE, 59)
        set(Calendar.SECOND, 59)
        set(Calendar.MILLISECOND, 999)
    }
    
    var startCol = 0
    for (i in 0..6) {
        val dayMidnight = Calendar.getInstance().apply {
            time = week[i].date
            set(Calendar.HOUR_OF_DAY, 23)
            set(Calendar.MINUTE, 59)
            set(Calendar.SECOND, 59)
            set(Calendar.MILLISECOND, 999)
        }
        if (startMidnight.timeInMillis <= dayMidnight.timeInMillis) {
            startCol = i
            break
        }
    }
    
    var endCol = 6
    for (i in 6 downTo 0) {
        val dayMidnight = Calendar.getInstance().apply {
            time = week[i].date
            set(Calendar.HOUR_OF_DAY, 0)
            set(Calendar.MINUTE, 0)
            set(Calendar.SECOND, 0)
            set(Calendar.MILLISECOND, 0)
        }
        if (endMidnight.timeInMillis >= dayMidnight.timeInMillis) {
            endCol = i
            break
        }
    }
    
    if (startCol > endCol) {
        startCol = endCol
    }
    return Pair(startCol, endCol)
}

fun getEventSpanInWeek(event: CalendarEvent, week: List<CalendarDay>): Int {
    val range = getEventRangeInWeek(event, week)
    return range.second - range.first + 1
}

fun getEventStartEpoch(event: CalendarEvent): Long {
    return parseEventDate(event.start)?.time ?: 0L
}

data class TrackSpan(
    val event: CalendarEvent?,
    val span: Int
)

fun getTrackSpans(track: List<CalendarEvent?>): List<TrackSpan> {
    val spans = mutableListOf<TrackSpan>()
    if (track.isEmpty()) return spans
    var currentEvent = track[0]
    var currentSpan = 1
    for (i in 1..6) {
        val ev = track[i]
        if (ev?.id == currentEvent?.id) {
            currentSpan++
        } else {
            spans.add(TrackSpan(currentEvent, currentSpan))
            currentEvent = ev
            currentSpan = 1
        }
    }
    spans.add(TrackSpan(currentEvent, currentSpan))
    return spans
}

fun layoutWeekTracks(week: List<CalendarDay>, events: List<CalendarEvent>): List<List<CalendarEvent?>> {
    val weekEvents = getEventsOverlappingWeek(week, events)
    val sortedEvents = weekEvents.sortedWith(
        compareByDescending<CalendarEvent> { getEventSpanInWeek(it, week) }
            .thenBy { getEventStartEpoch(it) }
            .thenBy { it.title }
    )
    
    val tracks = mutableListOf<MutableList<CalendarEvent?>>()
    for (event in sortedEvents) {
        val range = getEventRangeInWeek(event, week)
        var placed = false
        for (track in tracks) {
            var free = true
            for (col in range.first..range.second) {
                if (track[col] != null) {
                    free = false
                    break
                }
            }
            if (free) {
                for (col in range.first..range.second) {
                    track[col] = event
                }
                placed = true
                break
            }
        }
        if (!placed) {
            val newTrack = MutableList<CalendarEvent?>(7) { null }
            for (col in range.first..range.second) {
                newTrack[col] = event
            }
            tracks.add(newTrack)
        }
    }
    return tracks
}

data class PositionedEvent(
    val event: CalendarEvent,
    val startMin: Float,
    val endMin: Float,
    var colIndex: Int = 0,
    var totalCols: Int = 1
)

fun layoutTimedEvents(
    timedEvents: List<CalendarEvent>,
    selectedDate: Date
): List<PositionedEvent> {
    val sdfDateOnly = SimpleDateFormat("yyyy-MM-dd", Locale.US)
    val selectedDateStr = sdfDateOnly.format(selectedDate)

    val positioned = timedEvents.mapNotNull { ev ->
        val startD = parseEventDate(ev.start) ?: return@mapNotNull null
        val endD = ev.end?.let { parseEventDate(it) } ?: Date(startD.time + 60 * 60 * 1000)

        val startCal = Calendar.getInstance().apply { time = startD }
        val endCal = Calendar.getInstance().apply { time = endD }

        val startMin = if (sdfDateOnly.format(startD) != selectedDateStr) {
            0f
        } else {
            startCal.get(Calendar.HOUR_OF_DAY) * 60f + startCal.get(Calendar.MINUTE)
        }

        val endMin = if (sdfDateOnly.format(endD) != selectedDateStr) {
            1440f
        } else {
            endCal.get(Calendar.HOUR_OF_DAY) * 60f + endCal.get(Calendar.MINUTE)
        }

        val duration = maxOf(endMin - startMin, 30f) // Clamp duration to minimum 30 minutes
        PositionedEvent(ev, startMin, startMin + duration)
    }.sortedBy { it.startMin }

    val clusters = mutableListOf<MutableList<PositionedEvent>>()
    var currentCluster = mutableListOf<PositionedEvent>()
    var clusterEnd = 0f

    for (pEvent in positioned) {
        if (currentCluster.isEmpty()) {
            currentCluster.add(pEvent)
            clusterEnd = pEvent.endMin
        } else if (pEvent.startMin < clusterEnd) {
            currentCluster.add(pEvent)
            clusterEnd = maxOf(clusterEnd, pEvent.endMin)
        } else {
            clusters.add(currentCluster)
            currentCluster = mutableListOf(pEvent)
            clusterEnd = pEvent.endMin
        }
    }
    if (currentCluster.isNotEmpty()) {
        clusters.add(currentCluster)
    }

    for (cluster in clusters) {
        val columns = mutableListOf<MutableList<PositionedEvent>>()
        for (pEvent in cluster) {
            var placed = false
            for (colIdx in columns.indices) {
                val lastEventInCol = columns[colIdx].last()
                if (pEvent.startMin >= lastEventInCol.endMin) {
                    columns[colIdx].add(pEvent)
                    pEvent.colIndex = colIdx
                    placed = true
                    break
                }
            }
            if (!placed) {
                val newCol = mutableListOf(pEvent)
                columns.add(newCol)
                pEvent.colIndex = columns.size - 1
            }
        }
        val totalCols = columns.size
        for (pEvent in cluster) {
            pEvent.totalCols = totalCols
        }
    }

    return positioned
}

private fun getFileName(context: android.content.Context, uri: android.net.Uri): String? {
    var result: String? = null
    if (uri.scheme == "content") {
        val cursor = context.contentResolver.query(uri, null, null, null, null)
        try {
            if (cursor != null && cursor.moveToFirst()) {
                val index = cursor.getColumnIndex(android.provider.OpenableColumns.DISPLAY_NAME)
                if (index != -1) {
                    result = cursor.getString(index)
                }
            }
        } finally {
            cursor?.close()
        }
    }
    if (result == null) {
        result = uri.path
        val cut = result?.lastIndexOf('/')
        if (cut != null && cut != -1) {
            result = result.substring(cut + 1)
        }
    }
    return result
}

