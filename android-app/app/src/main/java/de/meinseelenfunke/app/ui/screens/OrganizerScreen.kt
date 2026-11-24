package de.meinseelenfunke.app.ui.screens

import de.meinseelenfunke.app.di.ServiceLocator

import androidx.compose.animation.AnimatedContent
import androidx.compose.animation.AnimatedVisibility
import androidx.compose.animation.slideInHorizontally
import androidx.compose.animation.slideOutHorizontally
import androidx.compose.animation.togetherWith
import androidx.activity.compose.BackHandler
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
import androidx.compose.foundation.shape.CircleShape
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
import androidx.compose.material.icons.filled.PhoneInTalk
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
import androidx.compose.material3.DropdownMenu
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
import de.meinseelenfunke.app.data.api.ManagementTaskMessageDto
import de.meinseelenfunke.app.ui.components.PhoneSecretaryTabContent
import de.meinseelenfunke.app.ui.theme.AppTheme
import de.meinseelenfunke.app.ui.theme.Gold
import de.meinseelenfunke.app.ui.theme.Copper
import de.meinseelenfunke.app.ui.theme.SpaceBlue
import de.meinseelenfunke.app.ui.theme.SpaceBlack
import de.meinseelenfunke.app.util.PhoneConciergeManager
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
    val taskMessages by viewModel.taskMessages.collectAsState()
    val taskActionError by viewModel.taskActionError.collectAsState()
    val routines by viewModel.routines.collectAsState()
    val shoppingItems by viewModel.shoppingItems.collectAsState()
    val calendarEvents by viewModel.calendarEvents.collectAsState()
    val recentSecretaryCalls by viewModel.recentSecretaryCalls.collectAsState()
    val unseenPhoneEntries by PhoneConciergeManager.unseenCallCountFlow.collectAsState()
    val isProcessingFile by viewModel.isProcessingFile.collectAsState()

    val scope = rememberCoroutineScope()
    val context = androidx.compose.ui.platform.LocalContext.current
    val pagerState = rememberPagerState(
        initialPage = initialTab.coerceIn(0, 7),
        pageCount = { 8 }
    )
    var isFirstLoad by remember { mutableStateOf(true) }

    LaunchedEffect(pagerState.currentPage) {
        if (isFirstLoad) {
            isFirstLoad = false
        }
    }

    LaunchedEffect(initialTab, navTrigger) {
        val target = initialTab.coerceIn(0, 7)
        if (pagerState.currentPage != target) {
            pagerState.scrollToPage(target)
        }
        onTabScrollCompleted()
    }

    val callIdsSignature = remember(recentSecretaryCalls) {
        recentSecretaryCalls.joinToString(",") { it.id }
    }

    LaunchedEffect(pagerState.currentPage, isPageVisible, callIdsSignature) {
        if (isPageVisible && pagerState.currentPage == 7 && recentSecretaryCalls.isNotEmpty()) {
            PhoneConciergeManager.markAllCallsSeen(context, recentSecretaryCalls)
        }
    }

    LaunchedEffect(isPageVisible) {
        while (isPageVisible) {
            viewModel.loadAllOrganizerData(showLoading = false)
            kotlinx.coroutines.delay(5000L)
        }
    }
    val tabTitles = listOf("Aufgaben", "Kalender", "Routine", "Einkauf", "E-Mail", "Musik", "Wecker", "Telefon")
    val tabIcons = listOf(
        Icons.Default.CheckCircle,
        Icons.Default.CalendarToday,
        Icons.Default.Schedule,
        Icons.Default.ShoppingCart,
        Icons.Default.Email,
        Icons.Default.MusicNote,
        Icons.Default.Alarm,
        Icons.Default.PhoneInTalk
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

            // Compact Icon Tab bar (8 Tabs: Aufgaben, Kalender, Routine, Einkauf, E-Mail, Musik, Wecker, Telefon)
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
                            Box {
                                Icon(
                                    imageVector = tabIcons[index],
                                    contentDescription = title,
                                    modifier = Modifier.size(20.dp),
                                    tint = if (isSelected) Gold else AppTheme.colors.textMuted
                                )
                                if (index == 7 && unseenPhoneEntries > 0 && !isSelected) {
                                    Box(
                                        modifier = Modifier
                                            .size(6.dp)
                                            .align(Alignment.TopEnd)
                                            .offset(x = 2.dp, y = (-2).dp)
                                            .clip(CircleShape)
                                            .background(Gold)
                                    )
                                }
                            }
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
                            0 -> TasksTabContent(
                                taskLists = taskLists,
                                tasks = tasks,
                                taskMessages = taskMessages,
                                taskActionError = taskActionError,
                                onAddTask = { listId, title, priority, relevantFrom, dueTime, assigneeKey ->
                                    viewModel.addTask(listId, title, priority, relevantFrom, dueTime, assigneeKey = assigneeKey)
                                },
                                onAssignTask = { id, assigneeKey -> viewModel.assignTask(id, assigneeKey) },
                                onToggleTask = { viewModel.toggleTask(it) },
                                onSetTaskStatus = { id, status -> viewModel.setTaskStatus(id, status) },
                                onSetTaskMine = { id, isMine -> viewModel.setTaskMine(id, isMine) },
                                onReorderTasks = { orderedIds, isMine, scopeKey -> viewModel.reorderTasks(orderedIds, isMine, scopeKey) },
                                onLoadTaskMessages = { viewModel.loadTaskMessages(it) },
                                onClearTaskMessages = { viewModel.clearTaskMessages() },
                                onSendTaskChatMessage = { id, text, files -> viewModel.sendTaskChatMessage(id, text, files) },
                                onEditTaskChatMessage = { id, msgId, text, keepPaths -> viewModel.editTaskChatMessage(id, msgId, text, keepPaths) },
                                onResolveTaskEscalation = { id, dueDate, dueTime, note -> viewModel.resolveTaskEscalation(id, dueDate, dueTime, note) },
                                onDeleteTask = { viewModel.deleteTask(it) },
                                onAddSubtask = { parentId, title -> viewModel.addSubtask(parentId, title) },
                                onDeleteTaskList = { viewModel.deleteTaskList(it) },
                                onAddTaskList = { name -> viewModel.addTaskList(name) },
                                onUpdateTaskList = { id, name -> viewModel.updateTaskList(id, name) },
                                onUpdateTask = { id, title, priority, completed, relFrom, aiPlan, dueTime, durationMin ->
                                    viewModel.updateTask(id, title, priority, completed, relFrom, aiPlan, null, dueTime, durationMin)
                                },
                                onUploadTaskFile = { id, bytes, name, mime -> viewModel.uploadTaskFile(id, bytes, name, mime) },
                                onDeleteTaskFile = { id, path -> viewModel.deleteTaskFile(id, path) }
                            )
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
                            7 -> PhoneSecretaryTabContent(
                                calls = recentSecretaryCalls,
                                onRefresh = { viewModel.refreshSecretaryCalls() },
                                onOpenTasks = {
                                    scope.launch {
                                        pagerState.animateScrollToPage(0)
                                    }
                                }
                            )
                        }
                    }
                }
            }
        }
    }
}

// --- TASKS TAB: PERSOENLICHES TICKET-, CHAT-, ESKALATIONS- & ZEIT-SYSTEM ---
private var lastAndroidTicketFxIndex = -1

private fun ticketStatusWordColor(statusKey: String): Color {
    return when (statusKey.lowercase(Locale.ROOT)) {
        "waiting" -> Color(0xFFFACC15)     // Gelb
        "in_progress" -> Color(0xFFFB923C) // Orange
        "completed" -> Color(0xFF34D399)   // Gruen
        else -> Color(0xFFF87171)          // Rot (Offen)
    }
}

private fun formatAndroidCreatedShort(raw: String?): String {
    if (raw.isNullOrBlank()) return "-"
    val clean = raw.take(16).replace('T', ' ')
    if (clean.length >= 10 && clean[4] == '-' && clean[7] == '-') {
        val y = clean.substring(2, 4)
        val m = clean.substring(5, 7)
        val d = clean.substring(8, 10)
        return "$d.$m.$y"
    }
    return clean
}

private fun playAndroidTicketFx(context: android.content.Context, type: String = "click") {
    try {
        val am = context.getSystemService(android.content.Context.AUDIO_SERVICE) as? android.media.AudioManager ?: return
        if (type == "escalate") {
            am.playSoundEffect(android.media.AudioManager.FX_KEYPRESS_INVALID, 0.58f)
            return
        }
        val profiles = listOf(
            android.media.AudioManager.FX_KEY_CLICK to 0.38f,
            android.media.AudioManager.FX_KEYPRESS_STANDARD to 0.44f,
            android.media.AudioManager.FX_FOCUS_NAVIGATION_UP to 0.42f,
            android.media.AudioManager.FX_FOCUS_NAVIGATION_DOWN to 0.40f,
            android.media.AudioManager.FX_FOCUS_NAVIGATION_LEFT to 0.39f,
            android.media.AudioManager.FX_FOCUS_NAVIGATION_RIGHT to 0.43f,
            android.media.AudioManager.FX_KEYPRESS_SPACEBAR to 0.41f,
            android.media.AudioManager.FX_KEYPRESS_RETURN to 0.45f
        )
        var nextIdx: Int
        do {
            nextIdx = kotlin.random.Random.nextInt(profiles.size)
        } while (nextIdx == lastAndroidTicketFxIndex && profiles.size > 1)
        lastAndroidTicketFxIndex = nextIdx
        val (fx, vol) = profiles[nextIdx]
        am.playSoundEffect(fx, vol)
    } catch (_: Exception) {
    }
}

@OptIn(ExperimentalMaterial3Api::class)
@Composable
fun TasksTabContent(
    taskLists: List<ManagementTaskList>,
    tasks: List<ManagementTask>,
    taskMessages: List<ManagementTaskMessageDto>,
    taskActionError: String?,
    onAddTask: (listId: String, title: String, priority: String, relevantFrom: String?, dueTime: String?, assigneeKey: String?) -> Unit,
    onAssignTask: (String, String?) -> Unit = { _, _ -> },
    onToggleTask: (String) -> Unit,
    onSetTaskStatus: (String, String) -> Unit,
    onSetTaskMine: (String, Boolean) -> Unit,
    onReorderTasks: (List<String>, Boolean, String) -> Unit,
    onLoadTaskMessages: (String) -> Unit,
    onClearTaskMessages: () -> Unit,
    onSendTaskChatMessage: (String, String, List<Triple<ByteArray, String, String>>) -> Unit,
    onEditTaskChatMessage: (String, String, String, List<String>) -> Unit,
    onResolveTaskEscalation: (String, String, String?, String) -> Unit,
    onDeleteTask: (String) -> Unit,
    onAddSubtask: (String, String) -> Unit,
    onDeleteTaskList: (String) -> Unit,
    onAddTaskList: (String) -> Unit,
    onUpdateTaskList: (String, String) -> Unit,
    onUpdateTask: (String, String?, String?, Boolean?, String?, String?, String?, Int?) -> Unit,
    onUploadTaskFile: (String, ByteArray, String, String) -> Unit,
    onDeleteTaskFile: (String, String) -> Unit
) {
    val context = androidx.compose.ui.platform.LocalContext.current
    var assignableActors by remember { mutableStateOf<List<de.meinseelenfunke.app.data.api.TaskAssigneeDto>>(emptyList()) }
    var assigneeDialogCurrentKey by remember { mutableStateOf<String?>(null) }
    var assigneeDialogOnPick by remember { mutableStateOf<((de.meinseelenfunke.app.data.api.TaskAssigneeDto?) -> Unit)?>(null) }

    LaunchedEffect(Unit) {
        try {
            val res = ServiceLocator.getInstance(context).getOrganizerApi().getTaskAssignees()
            if (res.success) {
                assignableActors = res.data
            }
        } catch (_: Exception) {
        }
    }
    var selectedListId by remember { mutableStateOf("all") }
    var selectedTaskId by remember { mutableStateOf<String?>(null) }
    var activeTileTab by remember { mutableStateOf("open") } // "open" = Offene Tickets Tabelle, "mine" = Meine Tickets + Listen
    var searchQuery by remember { mutableStateOf("") }
    var showCompletedSection by remember { mutableStateOf(false) }
    var isAddingList by remember { mutableStateOf(false) }
    var newListName by remember { mutableStateOf("") }

    var listToDelete by remember { mutableStateOf<ManagementTaskList?>(null) }
    var listToRename by remember { mutableStateOf<ManagementTaskList?>(null) }
    var renameListName by remember { mutableStateOf("") }

    val todayIso = remember { SimpleDateFormat("yyyy-MM-dd", Locale.US).format(Date()) }
    val rootTasks = remember(tasks) { tasks.filter { it.parent_id == null } }
    val selectedTask = remember(tasks, selectedTaskId) { rootTasks.find { it.id == selectedTaskId } }

    // 2-Stage Mobile Sliding Window: 0 = 2-Tab Ticket-Workspace (Offene Tabelle | Meine & Listen), 1 = Vollbild Ticket-Detail & Chat
    val currentStage = if (selectedTask != null) 1 else 0

    BackHandler(enabled = currentStage > 0) {
        playAndroidTicketFx(context)
        selectedTaskId = null
        onClearTaskMessages()
    }

    AnimatedContent(
        targetState = currentStage,
        transitionSpec = {
            if (targetState > initialState) {
                slideInHorizontally { width -> width } togetherWith slideOutHorizontally { width -> -width / 3 }
            } else {
                slideInHorizontally { width -> -width / 3 } togetherWith slideOutHorizontally { width -> width }
            }
        },
        modifier = Modifier.fillMaxSize(),
        label = "TicketMobileSlidingWindow"
    ) { stage ->
        when (stage) {
            0 -> {
                val selectedListObj = taskLists.find { it.id == selectedListId }
                val filteredRootTasks = remember(rootTasks, selectedListId, searchQuery) {
                    val byList = if (selectedListId == "all" || selectedListObj == null) {
                        rootTasks
                    } else {
                        rootTasks.filter { it.task_list_id == selectedListId }
                    }
                    if (searchQuery.isBlank()) byList
                    else byList.filter {
                        it.title.contains(searchQuery, ignoreCase = true) ||
                            (it.ai_plan?.contains(searchQuery, ignoreCase = true) == true)
                    }
                }

                val myTickets = remember(filteredRootTasks) {
                    filteredRootTasks
                        .filter { !it.is_completed && it.is_mine }
                        .sortedWith(compareBy<ManagementTask> { it.my_position }.thenBy { it.position })
                }

                val openPoolTickets = remember(filteredRootTasks) {
                    filteredRootTasks
                        .filter { !it.is_completed && !it.is_mine }
                        .sortedWith(compareBy<ManagementTask> { it.position }.thenByDescending { it.created_at ?: "" })
                }

                val completedTasks = remember(filteredRootTasks) {
                    filteredRootTasks.filter { it.is_completed }
                }

                var newTaskTitle by remember { mutableStateOf("") }
                var newTaskDate by remember { mutableStateOf<String?>(null) }
                var newTaskTime by remember { mutableStateOf<String?>(null) }
                var newTaskAssigneeKey by remember { mutableStateOf<String?>(null) }
                var newTaskAssigneeName by remember { mutableStateOf<String?>(null) }

                Column(
                    modifier = Modifier
                        .fillMaxSize()
                        .verticalScroll(rememberScrollState()),
                    verticalArrangement = Arrangement.spacedBy(8.dp)
                ) {
                    // Segmented Mobile Switcher: Offene Tickets | Meine & Listen
                    Row(
                        modifier = Modifier
                            .fillMaxWidth()
                            .background(AppTheme.colors.cardBackground, RoundedCornerShape(12.dp))
                            .border(1.dp, AppTheme.colors.cardBorder, RoundedCornerShape(12.dp))
                            .padding(4.dp),
                        horizontalArrangement = Arrangement.spacedBy(6.dp)
                    ) {
                        val isOpenActive = activeTileTab == "open"
                        Surface(
                            onClick = {
                                playAndroidTicketFx(context)
                                activeTileTab = "open"
                            },
                            color = if (isOpenActive) Gold.copy(alpha = 0.18f) else Color.Transparent,
                            border = if (isOpenActive) BorderStroke(1.dp, Gold) else null,
                            shape = RoundedCornerShape(9.dp),
                            modifier = Modifier.weight(1f)
                        ) {
                            Row(
                                modifier = Modifier.padding(vertical = 9.dp, horizontal = 6.dp),
                                horizontalArrangement = Arrangement.Center,
                                verticalAlignment = Alignment.CenterVertically
                            ) {
                                Text(
                                    text = "Offene Tickets (${openPoolTickets.size})",
                                    fontSize = 12.5.sp,
                                    fontWeight = if (isOpenActive) FontWeight.Bold else FontWeight.Medium,
                                    color = if (isOpenActive) Gold else AppTheme.colors.textSecondary
                                )
                            }
                        }

                        val isMineActive = activeTileTab == "mine"
                        Surface(
                            onClick = {
                                playAndroidTicketFx(context)
                                activeTileTab = "mine"
                            },
                            color = if (isMineActive) Gold.copy(alpha = 0.18f) else Color.Transparent,
                            border = if (isMineActive) BorderStroke(1.dp, Gold) else null,
                            shape = RoundedCornerShape(9.dp),
                            modifier = Modifier.weight(1f)
                        ) {
                            Row(
                                modifier = Modifier.padding(vertical = 9.dp, horizontal = 6.dp),
                                horizontalArrangement = Arrangement.Center,
                                verticalAlignment = Alignment.CenterVertically
                            ) {
                                Text(
                                    text = "Meine (${myTickets.size}) & Listen",
                                    fontSize = 12.5.sp,
                                    fontWeight = if (isMineActive) FontWeight.Bold else FontWeight.Medium,
                                    color = if (isMineActive) Gold else AppTheme.colors.textSecondary
                                )
                            }
                        }
                    }

                    // Compact Search Field
                    OutlinedTextField(
                        value = searchQuery,
                        onValueChange = { searchQuery = it },
                        placeholder = { Text("Tickets suchen...", fontSize = 12.5.sp, color = AppTheme.colors.textMuted) },
                        singleLine = true,
                        trailingIcon = {
                            if (searchQuery.isNotBlank()) {
                                IconButton(onClick = {
                                    playAndroidTicketFx(context)
                                    searchQuery = ""
                                }) {
                                    Icon(imageVector = Icons.Default.Close, contentDescription = "Leeren", tint = AppTheme.colors.textMuted, modifier = Modifier.size(18.dp))
                                }
                            }
                        },
                        colors = OutlinedTextFieldDefaults.colors(
                            focusedBorderColor = Gold,
                            unfocusedBorderColor = AppTheme.colors.inputBorder,
                            focusedTextColor = AppTheme.colors.textPrimary,
                            unfocusedTextColor = AppTheme.colors.textPrimary,
                            focusedContainerColor = AppTheme.colors.cardBackground,
                            unfocusedContainerColor = AppTheme.colors.cardBackground
                        ),
                        shape = RoundedCornerShape(11.dp),
                        modifier = Modifier.fillMaxWidth()
                    )

                    if (selectedListObj != null) {
                        Row(
                            modifier = Modifier.fillMaxWidth(),
                            horizontalArrangement = Arrangement.SpaceBetween,
                            verticalAlignment = Alignment.CenterVertically
                        ) {
                            Surface(
                                onClick = {
                                    playAndroidTicketFx(context)
                                    selectedListId = "all"
                                },
                                color = Gold.copy(alpha = 0.14f),
                                border = BorderStroke(1.dp, Gold.copy(alpha = 0.5f)),
                                shape = RoundedCornerShape(8.dp)
                            ) {
                                Text(
                                    text = "Liste: ${selectedListObj.name}  ✕",
                                    fontSize = 11.5.sp,
                                    fontWeight = FontWeight.Bold,
                                    color = Gold,
                                    modifier = Modifier.padding(horizontal = 10.dp, vertical = 5.dp)
                                )
                            }
                            Row {
                                IconButton(onClick = {
                                    playAndroidTicketFx(context)
                                    listToRename = selectedListObj
                                    renameListName = selectedListObj.name
                                }, modifier = Modifier.size(30.dp)) {
                                    Icon(Icons.Default.Edit, contentDescription = "Umbenennen", tint = Gold, modifier = Modifier.size(17.dp))
                                }
                                if (taskLists.size > 1) {
                                    IconButton(onClick = {
                                        playAndroidTicketFx(context)
                                        listToDelete = selectedListObj
                                    }, modifier = Modifier.size(30.dp)) {
                                        Icon(Icons.Default.Delete, contentDescription = "Loeschen", tint = Color(0xFFF87171), modifier = Modifier.size(17.dp))
                                    }
                                }
                            }
                        }
                    }

                    if (activeTileTab == "open") {
                        // Quick "+ Neues Ticket" Bar
                        Card(
                            modifier = Modifier.fillMaxWidth(),
                            colors = CardDefaults.cardColors(containerColor = AppTheme.colors.cardBackground),
                            border = BorderStroke(1.dp, AppTheme.colors.cardBorder),
                            shape = RoundedCornerShape(12.dp)
                        ) {
                            Column(modifier = Modifier.padding(horizontal = 10.dp, vertical = 5.dp)) {
                                Row(
                                    verticalAlignment = Alignment.CenterVertically,
                                    horizontalArrangement = Arrangement.spacedBy(4.dp)
                                ) {
                                    Icon(imageVector = Icons.Default.Add, contentDescription = null, tint = Gold, modifier = Modifier.size(20.dp))
                                    OutlinedTextField(
                                        value = newTaskTitle,
                                        onValueChange = { newTaskTitle = it },
                                        placeholder = { Text("Neues Ticket erfassen...", color = AppTheme.colors.textMuted, fontSize = 13.sp) },
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
                                    IconButton(
                                        onClick = {
                                            playAndroidTicketFx(context)
                                            assigneeDialogCurrentKey = newTaskAssigneeKey
                                            assigneeDialogOnPick = { picked ->
                                                newTaskAssigneeKey = picked?.key
                                                newTaskAssigneeName = picked?.name
                                            }
                                        },
                                        modifier = Modifier.size(30.dp)
                                    ) {
                                        Icon(Icons.Default.AccountCircle, contentDescription = "Bearbeiter", tint = if (newTaskAssigneeKey != null) Gold else AppTheme.colors.textMuted, modifier = Modifier.size(18.dp))
                                    }
                                    IconButton(
                                        onClick = {
                                            val cal = Calendar.getInstance()
                                            android.app.DatePickerDialog(
                                                context,
                                                { _, y, m, d -> newTaskDate = String.format(Locale.US, "%04d-%02d-%02d", y, m + 1, d) },
                                                cal.get(Calendar.YEAR),
                                                cal.get(Calendar.MONTH),
                                                cal.get(Calendar.DAY_OF_MONTH)
                                            ).show()
                                        },
                                        modifier = Modifier.size(30.dp)
                                    ) {
                                        Icon(Icons.Default.CalendarToday, contentDescription = "Datum", tint = if (newTaskDate != null) Gold else AppTheme.colors.textMuted, modifier = Modifier.size(16.dp))
                                    }
                                    IconButton(
                                        onClick = {
                                            val cal = Calendar.getInstance()
                                            android.app.TimePickerDialog(
                                                context,
                                                { _, h, min ->
                                                    newTaskTime = String.format(Locale.US, "%02d:%02d", h, min)
                                                    if (newTaskDate == null) newTaskDate = todayIso
                                                },
                                                cal.get(Calendar.HOUR_OF_DAY),
                                                0,
                                                true
                                            ).show()
                                        },
                                        modifier = Modifier.size(30.dp)
                                    ) {
                                        Icon(Icons.Default.Schedule, contentDescription = "Uhrzeit", tint = if (newTaskTime != null) Gold else AppTheme.colors.textMuted, modifier = Modifier.size(17.dp))
                                    }
                                    if (newTaskTitle.isNotBlank()) {
                                        Button(
                                            onClick = {
                                                val targetId = if (selectedListId == "all" || selectedListObj == null) {
                                                    taskLists.firstOrNull()?.id ?: "default"
                                                } else selectedListId
                                                playAndroidTicketFx(context)
                                                onAddTask(targetId, newTaskTitle.trim(), "mittel", newTaskDate, newTaskTime, newTaskAssigneeKey)
                                                newTaskTitle = ""
                                                newTaskDate = null
                                                newTaskTime = null
                                                newTaskAssigneeKey = null
                                                newTaskAssigneeName = null
                                            },
                                            colors = ButtonDefaults.buttonColors(containerColor = Gold, contentColor = Color(0xFF121316)),
                                            shape = RoundedCornerShape(8.dp),
                                            contentPadding = PaddingValues(horizontal = 11.dp, vertical = 5.dp)
                                        ) {
                                            Text("Anlegen", fontWeight = FontWeight.Bold, fontSize = 11.5.sp)
                                        }
                                    }
                                }
                                if (newTaskDate != null || newTaskTime != null || newTaskAssigneeName != null) {
                                    Row(
                                        verticalAlignment = Alignment.CenterVertically,
                                        modifier = Modifier.padding(start = 26.dp, bottom = 4.dp)
                                    ) {
                                        val metaParts = mutableListOf<String>()
                                        if (newTaskAssigneeName != null) metaParts.add("👤 $newTaskAssigneeName")
                                        if (newTaskDate != null || newTaskTime != null) {
                                            metaParts.add("📅 ${newTaskDate ?: todayIso}" + (if (newTaskTime != null) " · $newTaskTime Uhr" else ""))
                                        }
                                        Text(
                                            text = metaParts.joinToString("   "),
                                            fontSize = 11.sp,
                                            color = Gold,
                                            fontWeight = FontWeight.SemiBold
                                        )
                                        Spacer(modifier = Modifier.width(8.dp))
                                        Text("✕", fontSize = 12.sp, color = AppTheme.colors.textMuted, modifier = Modifier.clickable {
                                            newTaskDate = null
                                            newTaskTime = null
                                            newTaskAssigneeKey = null
                                            newTaskAssigneeName = null
                                        })
                                    }
                                }
                            }
                        }

                        // OFFENE TICKETS INLINE-TABELLE (Titel | Erstellt am | Status)
                        Card(
                            modifier = Modifier.fillMaxWidth(),
                            colors = CardDefaults.cardColors(containerColor = AppTheme.colors.cardBackground),
                            border = BorderStroke(1.dp, AppTheme.colors.cardBorder),
                            shape = RoundedCornerShape(13.dp)
                        ) {
                            Column(modifier = Modifier.fillMaxWidth()) {
                                // Table Header
                                Row(
                                    modifier = Modifier
                                        .fillMaxWidth()
                                        .background(AppTheme.colors.inputBackground)
                                        .padding(horizontal = 10.dp, vertical = 8.dp),
                                    verticalAlignment = Alignment.CenterVertically
                                ) {
                                    Text("TITEL", fontSize = 10.sp, fontWeight = FontWeight.Bold, color = AppTheme.colors.textMuted, modifier = Modifier.weight(1f))
                                    Text("ERSTELLT", fontSize = 10.sp, fontWeight = FontWeight.Bold, color = AppTheme.colors.textMuted, modifier = Modifier.width(64.dp))
                                    Text("STATUS", fontSize = 10.sp, fontWeight = FontWeight.Bold, color = AppTheme.colors.textMuted, modifier = Modifier.width(96.dp), textAlign = TextAlign.End)
                                }

                                if (openPoolTickets.isEmpty()) {
                                    Text(
                                        text = "Keine offenen Tickets.",
                                        color = AppTheme.colors.textMuted,
                                        fontSize = 13.sp,
                                        textAlign = TextAlign.Center,
                                        modifier = Modifier.fillMaxWidth().padding(vertical = 26.dp)
                                    )
                                } else {
                                    openPoolTickets.forEachIndexed { idx, task ->
                                        TicketOpenTableRow(
                                            task = task,
                                            canMoveUp = idx > 0,
                                            canMoveDown = idx < openPoolTickets.lastIndex,
                                            onOpenDetail = {
                                                playAndroidTicketFx(context)
                                                selectedTaskId = task.id
                                                onLoadTaskMessages(task.id)
                                            },
                                            onSaveInlineTitle = { newTitle ->
                                                playAndroidTicketFx(context)
                                                onUpdateTask(task.id, newTitle, null, null, null, null, null, null)
                                            },
                                            onChangeStatus = { targetStatus ->
                                                if (task.isEscalatedEffective && targetStatus != "completed") {
                                                    playAndroidTicketFx(context, "escalate")
                                                    selectedTaskId = task.id
                                                    onLoadTaskMessages(task.id)
                                                } else {
                                                    playAndroidTicketFx(context)
                                                    onSetTaskStatus(task.id, targetStatus)
                                                }
                                            },
                                            onPinToMine = {
                                                playAndroidTicketFx(context)
                                                onSetTaskMine(task.id, true)
                                            },
                                            onPickAssignee = {
                                                playAndroidTicketFx(context)
                                                assigneeDialogCurrentKey = task.assignee_key
                                                assigneeDialogOnPick = { picked -> onAssignTask(task.id, picked?.key) }
                                            },
                                            onMoveOrder = { delta ->
                                                playAndroidTicketFx(context)
                                                val mutableIds = openPoolTickets.map { it.id }.toMutableList()
                                                val targetIdx = (idx + delta).coerceIn(0, mutableIds.lastIndex)
                                                if (targetIdx != idx) {
                                                    val moved = mutableIds.removeAt(idx)
                                                    mutableIds.add(targetIdx, moved)
                                                    onReorderTasks(mutableIds, false, selectedListId)
                                                }
                                            }
                                        )
                                    }
                                }
                            }
                        }

                        if (completedTasks.isNotEmpty()) {
                            Row(
                                modifier = Modifier
                                    .clip(RoundedCornerShape(8.dp))
                                    .clickable {
                                        playAndroidTicketFx(context)
                                        showCompletedSection = !showCompletedSection
                                    }
                                    .padding(horizontal = 6.dp, vertical = 6.dp),
                                verticalAlignment = Alignment.CenterVertically
                            ) {
                                Icon(
                                    imageVector = if (showCompletedSection) Icons.Default.ExpandMore else Icons.Default.ChevronRight,
                                    contentDescription = null,
                                    tint = Color(0xFF34D399),
                                    modifier = Modifier.size(19.dp)
                                )
                                Spacer(modifier = Modifier.width(6.dp))
                                Text(text = "Erledigt (${completedTasks.size})", fontSize = 13.sp, fontWeight = FontWeight.Bold, color = Color(0xFF34D399))
                            }

                            AnimatedVisibility(visible = showCompletedSection) {
                                Card(
                                    modifier = Modifier.fillMaxWidth(),
                                    colors = CardDefaults.cardColors(containerColor = AppTheme.colors.cardBackground),
                                    border = BorderStroke(1.dp, AppTheme.colors.cardBorder),
                                    shape = RoundedCornerShape(12.dp)
                                ) {
                                    Column {
                                        completedTasks.forEach { task ->
                                            TicketOpenTableRow(
                                                task = task,
                                                canMoveUp = false,
                                                canMoveDown = false,
                                                onOpenDetail = {
                                                    playAndroidTicketFx(context)
                                                    selectedTaskId = task.id
                                                    onLoadTaskMessages(task.id)
                                                },
                                                onSaveInlineTitle = { newTitle ->
                                                    onUpdateTask(task.id, newTitle, null, null, null, null, null, null)
                                                },
                                                onChangeStatus = { st ->
                                                    playAndroidTicketFx(context)
                                                    onSetTaskStatus(task.id, st)
                                                },
                                                onPinToMine = {},
                                                onPickAssignee = {
                                                    playAndroidTicketFx(context)
                                                    assigneeDialogCurrentKey = task.assignee_key
                                                    assigneeDialogOnPick = { picked -> onAssignTask(task.id, picked?.key) }
                                                },
                                                onMoveOrder = {}
                                            )
                                        }
                                    }
                                }
                            }
                        }
                    } else {
                        // TAB 2: MEINE TICKETS OBEN + LISTEN & NEUE LISTE DIREKT UNTERHALB (Punkt 12!)
                        Card(
                            modifier = Modifier.fillMaxWidth(),
                            colors = CardDefaults.cardColors(containerColor = AppTheme.colors.cardBackground),
                            border = BorderStroke(1.dp, Gold.copy(alpha = 0.4f)),
                            shape = RoundedCornerShape(13.dp)
                        ) {
                            Column(modifier = Modifier.padding(12.dp), verticalArrangement = Arrangement.spacedBy(8.dp)) {
                                Row(
                                    modifier = Modifier.fillMaxWidth(),
                                    horizontalArrangement = Arrangement.SpaceBetween,
                                    verticalAlignment = Alignment.CenterVertically
                                ) {
                                    Text("Meine Tickets", fontSize = 15.sp, fontWeight = FontWeight.Bold, color = AppTheme.colors.textPrimary)
                                    Text("${myTickets.size}", fontSize = 12.5.sp, fontWeight = FontWeight.Bold, color = Gold)
                                }

                                if (myTickets.isEmpty()) {
                                    Text(
                                        text = "Keine Tickets in Meine Tickets.",
                                        fontSize = 12.5.sp,
                                        color = AppTheme.colors.textMuted,
                                        modifier = Modifier.padding(vertical = 12.dp)
                                    )
                                } else {
                                    myTickets.forEachIndexed { idx, task ->
                                        MyTicketCardItem(
                                            task = task,
                                            canMoveUp = idx > 0,
                                            canMoveDown = idx < myTickets.lastIndex,
                                            onOpenDetail = {
                                                playAndroidTicketFx(context)
                                                selectedTaskId = task.id
                                                onLoadTaskMessages(task.id)
                                            },
                                            onReleaseFromMine = {
                                                playAndroidTicketFx(context)
                                                onSetTaskMine(task.id, false)
                                            },
                                            onMoveOrder = { delta ->
                                                playAndroidTicketFx(context)
                                                val mutableIds = myTickets.map { it.id }.toMutableList()
                                                val targetIdx = (idx + delta).coerceIn(0, mutableIds.lastIndex)
                                                if (targetIdx != idx) {
                                                    val moved = mutableIds.removeAt(idx)
                                                    mutableIds.add(targetIdx, moved)
                                                    onReorderTasks(mutableIds, true, "all")
                                                }
                                            }
                                        )
                                    }
                                }
                            }
                        }

                        // LISTEN KACHEL DIREKT UNTERHALB VON MEINE TICKETS
                        Card(
                            modifier = Modifier.fillMaxWidth(),
                            colors = CardDefaults.cardColors(containerColor = AppTheme.colors.cardBackground),
                            border = BorderStroke(1.dp, AppTheme.colors.cardBorder),
                            shape = RoundedCornerShape(13.dp)
                        ) {
                            Column(modifier = Modifier.padding(12.dp), verticalArrangement = Arrangement.spacedBy(4.dp)) {
                                Text("Listen", fontSize = 15.sp, fontWeight = FontWeight.Bold, color = AppTheme.colors.textPrimary, modifier = Modifier.padding(bottom = 4.dp))

                                val totalActiveCount = rootTasks.count { !it.is_completed }
                                val isAllSelected = selectedListId == "all"
                                Row(
                                    modifier = Modifier
                                        .fillMaxWidth()
                                        .clip(RoundedCornerShape(9.dp))
                                        .background(if (isAllSelected) Gold.copy(alpha = 0.14f) else Color.Transparent)
                                        .clickable {
                                            playAndroidTicketFx(context)
                                            selectedListId = "all"
                                            activeTileTab = "open"
                                        }
                                        .padding(horizontal = 10.dp, vertical = 10.dp),
                                    horizontalArrangement = Arrangement.SpaceBetween,
                                    verticalAlignment = Alignment.CenterVertically
                                ) {
                                    Row(verticalAlignment = Alignment.CenterVertically) {
                                        Icon(Icons.Default.List, contentDescription = null, tint = Gold, modifier = Modifier.size(19.dp))
                                        Spacer(modifier = Modifier.width(10.dp))
                                        Text("Alle Listen", fontSize = 14.sp, fontWeight = if (isAllSelected) FontWeight.Bold else FontWeight.Medium, color = AppTheme.colors.textPrimary)
                                    }
                                    if (totalActiveCount > 0) {
                                        Text("$totalActiveCount", fontSize = 12.5.sp, fontWeight = FontWeight.Bold, color = Gold)
                                    }
                                }

                                taskLists.forEach { list ->
                                    val listActiveCount = rootTasks.count { it.task_list_id == list.id && !it.is_completed }
                                    val isThisSelected = selectedListId == list.id
                                    Row(
                                        modifier = Modifier
                                            .fillMaxWidth()
                                            .clip(RoundedCornerShape(9.dp))
                                            .background(if (isThisSelected) Gold.copy(alpha = 0.14f) else Color.Transparent)
                                            .clickable {
                                                playAndroidTicketFx(context)
                                                selectedListId = list.id
                                                activeTileTab = "open"
                                            }
                                            .padding(horizontal = 10.dp, vertical = 10.dp),
                                        horizontalArrangement = Arrangement.SpaceBetween,
                                        verticalAlignment = Alignment.CenterVertically
                                    ) {
                                        Row(verticalAlignment = Alignment.CenterVertically, modifier = Modifier.weight(1f)) {
                                            Icon(Icons.Default.Bookmark, contentDescription = null, tint = parseHexColor(list.color, Gold), modifier = Modifier.size(18.dp))
                                            Spacer(modifier = Modifier.width(10.dp))
                                            Text(
                                                text = list.name,
                                                fontSize = 14.sp,
                                                fontWeight = if (isThisSelected) FontWeight.Bold else FontWeight.Medium,
                                                color = AppTheme.colors.textPrimary,
                                                maxLines = 1,
                                                overflow = androidx.compose.ui.text.style.TextOverflow.Ellipsis
                                            )
                                        }
                                        if (listActiveCount > 0) {
                                            Text("$listActiveCount", fontSize = 12.5.sp, fontWeight = FontWeight.SemiBold, color = AppTheme.colors.textMuted)
                                        }
                                    }
                                }

                                Spacer(modifier = Modifier.height(6.dp))

                                if (isAddingList) {
                                    Column(verticalArrangement = Arrangement.spacedBy(8.dp)) {
                                        OutlinedTextField(
                                            value = newListName,
                                            onValueChange = { newListName = it },
                                            placeholder = { Text("Neue Liste...", fontSize = 13.sp) },
                                            singleLine = true,
                                            modifier = Modifier.fillMaxWidth()
                                        )
                                        Row(modifier = Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.End) {
                                            TextButton(onClick = { isAddingList = false; newListName = "" }) {
                                                Text("Abbrechen", color = AppTheme.colors.textMuted)
                                            }
                                            Button(
                                                onClick = {
                                                    if (newListName.isNotBlank()) {
                                                        playAndroidTicketFx(context)
                                                        onAddTaskList(newListName.trim())
                                                        newListName = ""
                                                        isAddingList = false
                                                    }
                                                },
                                                colors = ButtonDefaults.buttonColors(containerColor = Gold, contentColor = Color(0xFF121316)),
                                                shape = RoundedCornerShape(8.dp)
                                            ) {
                                                Text("Anlegen", fontWeight = FontWeight.Bold)
                                            }
                                        }
                                    }
                                } else {
                                    Row(
                                        modifier = Modifier
                                            .fillMaxWidth()
                                            .clip(RoundedCornerShape(9.dp))
                                            .clickable {
                                                playAndroidTicketFx(context)
                                                isAddingList = true
                                            }
                                            .padding(horizontal = 10.dp, vertical = 9.dp),
                                        verticalAlignment = Alignment.CenterVertically
                                    ) {
                                        Icon(Icons.Default.Add, contentDescription = null, tint = Gold, modifier = Modifier.size(19.dp))
                                        Spacer(modifier = Modifier.width(10.dp))
                                        Text("Neue Liste...", fontSize = 13.5.sp, fontWeight = FontWeight.SemiBold, color = Gold)
                                    }
                                }
                            }
                        }
                    }

                    Spacer(modifier = Modifier.height(80.dp))
                }
            }

            // ==================== STAGE 1: VOLLBILD TICKET-DETAIL & CHAT ====================
            else -> {
                val task = selectedTask
                if (task != null) {
                    TicketFullDetailAndChatView(
                        task = task,
                        allTasks = tasks,
                        messages = taskMessages,
                        actionError = taskActionError,
                        onBack = {
                            playAndroidTicketFx(context)
                            selectedTaskId = null
                            onClearTaskMessages()
                        },
                        onSetStatus = { nextStatus ->
                            playAndroidTicketFx(context)
                            onSetTaskStatus(task.id, nextStatus)
                        },
                        onToggleMine = {
                            playAndroidTicketFx(context)
                            onSetTaskMine(task.id, !task.is_mine)
                        },
                        onPickAssignee = {
                            playAndroidTicketFx(context)
                            assigneeDialogCurrentKey = task.assignee_key
                            assigneeDialogOnPick = { picked -> onAssignTask(task.id, picked?.key) }
                        },
                        onToggleSubtask = { onToggleTask(it) },
                        onAddSubtask = { subTitle -> onAddSubtask(task.id, subTitle) },
                        onDeleteTaskOrSub = { idToDelete ->
                            if (idToDelete == task.id) {
                                selectedTaskId = null
                                onClearTaskMessages()
                            }
                            onDeleteTask(idToDelete)
                        },
                        onUpdateTask = { title, prio, relDate, note, dueTime, durMin ->
                            playAndroidTicketFx(context)
                            onUpdateTask(task.id, title, prio, null, relDate, note, dueTime, durMin)
                        },
                        onResolveEscalation = { dueDate, dueTime, statusNote ->
                            playAndroidTicketFx(context)
                            onResolveTaskEscalation(task.id, dueDate, dueTime, statusNote)
                        },
                        onSendChat = { chatText, files ->
                            playAndroidTicketFx(context)
                            onSendTaskChatMessage(task.id, chatText, files)
                        },
                        onEditChat = { msgId, newText, keepPaths ->
                            playAndroidTicketFx(context)
                            onEditTaskChatMessage(task.id, msgId, newText, keepPaths)
                        }
                    )
                }
            }
        }
    }

    if (assigneeDialogOnPick != null) {
        var assigneeQuery by remember { mutableStateOf("") }
        val filteredActors = remember(assignableActors, assigneeQuery) {
            val q = assigneeQuery.trim().lowercase(Locale.GERMAN)
                .replace("ä", "ae").replace("ö", "oe").replace("ü", "ue").replace("ß", "ss")
            if (q.isEmpty()) assignableActors
            else assignableActors.filter { a ->
                val nm = a.name.lowercase(Locale.GERMAN)
                    .replace("ä", "ae").replace("ö", "oe").replace("ü", "ue").replace("ß", "ss")
                val rl = (a.role_label ?: "").lowercase(Locale.GERMAN)
                nm.contains(q) || rl.contains(q) || (a.initials ?: "").lowercase(Locale.GERMAN).contains(q)
            }
        }
        AlertDialog(
            onDismissRequest = { assigneeDialogOnPick = null },
            title = { Text("Bearbeiter wählen", color = AppTheme.colors.textPrimary, fontWeight = FontWeight.Bold, fontSize = 16.sp) },
            text = {
                Column(verticalArrangement = Arrangement.spacedBy(8.dp)) {
                    OutlinedTextField(
                        value = assigneeQuery,
                        onValueChange = { assigneeQuery = it },
                        placeholder = { Text("Bearbeiter suchen...", fontSize = 12.5.sp) },
                        singleLine = true,
                        modifier = Modifier.fillMaxWidth()
                    )
                    Column(
                        modifier = Modifier.verticalScroll(rememberScrollState()),
                        verticalArrangement = Arrangement.spacedBy(4.dp)
                    ) {
                        Row(
                            modifier = Modifier
                                .fillMaxWidth()
                                .clip(RoundedCornerShape(8.dp))
                                .background(if (assigneeDialogCurrentKey.isNullOrBlank()) Gold.copy(alpha = 0.14f) else Color.Transparent)
                                .clickable {
                                    val cb = assigneeDialogOnPick
                                    assigneeDialogOnPick = null
                                    cb?.invoke(null)
                                }
                                .padding(horizontal = 10.dp, vertical = 9.dp),
                            horizontalArrangement = Arrangement.SpaceBetween,
                            verticalAlignment = Alignment.CenterVertically
                        ) {
                            Text("✕ Frei (Kein Bearbeiter)", fontSize = 13.sp, color = AppTheme.colors.textSecondary, fontWeight = FontWeight.SemiBold)
                            if (assigneeDialogCurrentKey.isNullOrBlank()) {
                                Text("✓", color = Gold, fontWeight = FontWeight.Bold)
                            }
                        }
                        filteredActors.forEach { act ->
                            val isSelected = assigneeDialogCurrentKey == act.key
                            Row(
                                modifier = Modifier
                                    .fillMaxWidth()
                                    .clip(RoundedCornerShape(8.dp))
                                    .background(if (isSelected) Gold.copy(alpha = 0.16f) else Color.Transparent)
                                    .clickable {
                                        val cb = assigneeDialogOnPick
                                        assigneeDialogOnPick = null
                                        cb?.invoke(act)
                                    }
                                    .padding(horizontal = 10.dp, vertical = 9.dp),
                                horizontalArrangement = Arrangement.SpaceBetween,
                                verticalAlignment = Alignment.CenterVertically
                            ) {
                                Column(modifier = Modifier.weight(1f)) {
                                    Text("👤 ${act.name}", fontSize = 13.5.sp, fontWeight = FontWeight.Bold, color = AppTheme.colors.textPrimary)
                                    if (!act.role_label.isNullOrBlank()) {
                                        Text(act.role_label, fontSize = 11.sp, color = AppTheme.colors.textMuted)
                                    }
                                }
                                if (isSelected) {
                                    Text("✓", color = Gold, fontWeight = FontWeight.Bold)
                                }
                            }
                        }
                    }
                }
            },
            confirmButton = {
                TextButton(onClick = { assigneeDialogOnPick = null }) {
                    Text("Schließen", color = AppTheme.colors.textMuted)
                }
            },
            containerColor = AppTheme.colors.cardBackground
        )
    }

    if (listToDelete != null) {
        AlertDialog(
            onDismissRequest = { listToDelete = null },
            title = { Text(text = "Liste löschen", color = AppTheme.colors.textPrimary, fontWeight = FontWeight.Bold) },
            text = { Text(text = "Möchtest du '${listToDelete?.name}' samt aller Tickets wirklich löschen?", color = AppTheme.colors.textSecondary) },
            confirmButton = {
                TextButton(
                    onClick = {
                        listToDelete?.let {
                            onDeleteTaskList(it.id)
                            if (selectedListId == it.id) selectedListId = "all"
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
private fun TicketOpenTableRow(
    task: ManagementTask,
    canMoveUp: Boolean,
    canMoveDown: Boolean,
    onOpenDetail: () -> Unit,
    onSaveInlineTitle: (String) -> Unit,
    onChangeStatus: (String) -> Unit,
    onPinToMine: () -> Unit,
    onPickAssignee: () -> Unit = {},
    onMoveOrder: (Int) -> Unit
) {
    var isEditingInlineTitle by remember(task.id) { mutableStateOf(false) }
    var inlineTitleDraft by remember(task.id, task.title) { mutableStateOf(task.title) }
    var statusMenuExpanded by remember { mutableStateOf(false) }

    Column(
        modifier = Modifier
            .fillMaxWidth()
            .background(if (task.isEscalatedEffective) Color(0xFF231217) else Color.Transparent)
            .clickable(enabled = !isEditingInlineTitle) { onOpenDetail() }
            .padding(horizontal = 10.dp, vertical = 9.dp)
    ) {
        Row(
            modifier = Modifier.fillMaxWidth(),
            verticalAlignment = Alignment.CenterVertically
        ) {
            if (isEditingInlineTitle) {
                OutlinedTextField(
                    value = inlineTitleDraft,
                    onValueChange = { inlineTitleDraft = it },
                    singleLine = true,
                    trailingIcon = {
                        TextButton(onClick = {
                            val clean = inlineTitleDraft.trim()
                            if (clean.isNotEmpty() && clean != task.title) {
                                onSaveInlineTitle(clean)
                            }
                            isEditingInlineTitle = false
                        }) {
                            Text("OK", color = Gold, fontWeight = FontWeight.Bold, fontSize = 11.5.sp)
                        }
                    },
                    modifier = Modifier.weight(1f)
                )
            } else {
                Column(modifier = Modifier.weight(1f).padding(end = 6.dp)) {
                    Text(
                        text = task.title,
                        color = if (task.is_completed) AppTheme.colors.textMuted else AppTheme.colors.textPrimary,
                        textDecoration = if (task.is_completed) TextDecoration.LineThrough else TextDecoration.None,
                        fontSize = 13.5.sp,
                        fontWeight = FontWeight.SemiBold,
                        maxLines = 2,
                        overflow = androidx.compose.ui.text.style.TextOverflow.Ellipsis
                    )
                    Row(
                        horizontalArrangement = Arrangement.spacedBy(8.dp),
                        verticalAlignment = Alignment.CenterVertically,
                        modifier = Modifier.padding(top = 3.dp)
                    ) {
                        Text(
                            text = "👤 ${task.assignee_name ?: "Frei"} ▾",
                            fontSize = 10.5.sp,
                            color = if (task.assignee_name.isNullOrBlank()) AppTheme.colors.textMuted else Gold,
                            fontWeight = FontWeight.SemiBold,
                            modifier = Modifier.clickable { onPickAssignee() }
                        )
                        Text(
                            text = "✎ Titel",
                            fontSize = 10.5.sp,
                            color = AppTheme.colors.textMuted,
                            modifier = Modifier.clickable { isEditingInlineTitle = true }
                        )
                        if (!task.is_completed) {
                            Text(
                                text = "📌 Meine",
                                fontSize = 10.5.sp,
                                color = Gold,
                                fontWeight = FontWeight.SemiBold,
                                modifier = Modifier.clickable { onPinToMine() }
                            )
                        }
                        if (canMoveUp) {
                            Text("↑", fontSize = 11.5.sp, color = AppTheme.colors.textMuted, modifier = Modifier.clickable { onMoveOrder(-1) })
                        }
                        if (canMoveDown) {
                            Text("↓", fontSize = 11.5.sp, color = AppTheme.colors.textMuted, modifier = Modifier.clickable { onMoveOrder(1) })
                        }
                        if (task.messages_count > 0) {
                            Text("💬 ${task.messages_count}", fontSize = 10.5.sp, color = AppTheme.colors.textMuted)
                        }
                    }
                }
            }

            if (!isEditingInlineTitle) {
                Text(
                    text = formatAndroidCreatedShort(task.created_at),
                    fontSize = 11.sp,
                    color = AppTheme.colors.textMuted,
                    modifier = Modifier.width(64.dp)
                )

                // Status Column: ONLY the status word carries the color (Zero background pill!)
                Box(modifier = Modifier.width(96.dp), contentAlignment = Alignment.CenterEnd) {
                    Text(
                        text = "${task.statusLabel} ▾",
                        fontSize = 12.sp,
                        fontWeight = FontWeight.Bold,
                        color = ticketStatusWordColor(task.normalizedStatus),
                        textAlign = TextAlign.End,
                        modifier = Modifier
                            .clickable { statusMenuExpanded = true }
                            .padding(vertical = 4.dp)
                    )
                    DropdownMenu(
                        expanded = statusMenuExpanded,
                        onDismissRequest = { statusMenuExpanded = false }
                    ) {
                        listOf(
                            "open" to "Offen",
                            "waiting" to "Warten",
                            "in_progress" to "In Bearbeitung",
                            "completed" to "Erledigt"
                        ).forEach { (stKey, stLabel) ->
                            DropdownMenuItem(
                                text = {
                                    Text(
                                        text = stLabel,
                                        color = ticketStatusWordColor(stKey),
                                        fontWeight = FontWeight.Bold,
                                        fontSize = 13.sp
                                    )
                                },
                                onClick = {
                                    statusMenuExpanded = false
                                    if (stKey != task.normalizedStatus) {
                                        onChangeStatus(stKey)
                                    }
                                }
                            )
                        }
                    }
                }
            }
        }
        Box(
            modifier = Modifier
                .fillMaxWidth()
                .padding(top = 8.dp)
                .height(1.dp)
                .background(AppTheme.colors.cardBorder.copy(alpha = 0.55f))
        )
    }
}

@Composable
private fun MyTicketCardItem(
    task: ManagementTask,
    canMoveUp: Boolean,
    canMoveDown: Boolean,
    onOpenDetail: () -> Unit,
    onReleaseFromMine: () -> Unit,
    onMoveOrder: (Int) -> Unit
) {
    Surface(
        onClick = onOpenDetail,
        color = AppTheme.colors.inputBackground,
        border = BorderStroke(1.dp, if (task.isEscalatedEffective) Color(0xFFEF4444) else AppTheme.colors.cardBorder),
        shape = RoundedCornerShape(10.dp),
        modifier = Modifier.fillMaxWidth()
    ) {
        Row(
            modifier = Modifier.padding(horizontal = 11.dp, vertical = 9.dp),
            verticalAlignment = Alignment.CenterVertically
        ) {
            Column(modifier = Modifier.weight(1f)) {
                Text(
                    text = task.title,
                    fontSize = 13.5.sp,
                    lineHeight = 19.sp,
                    fontWeight = FontWeight.SemiBold,
                    color = AppTheme.colors.textPrimary
                )
                Row(
                    horizontalArrangement = Arrangement.spacedBy(8.dp),
                    verticalAlignment = Alignment.CenterVertically,
                    modifier = Modifier.padding(top = 3.dp)
                ) {
                    Text(
                        text = task.statusLabel,
                        fontSize = 11.5.sp,
                        fontWeight = FontWeight.Bold,
                        color = ticketStatusWordColor(task.normalizedStatus)
                    )
                    if (task.isEscalatedEffective) {
                        Text("⚠ Eskaliert", fontSize = 11.sp, fontWeight = FontWeight.Bold, color = Color(0xFFFCA5A5))
                    }
                    if (canMoveUp) {
                        Text("↑", fontSize = 12.sp, color = AppTheme.colors.textMuted, modifier = Modifier.clickable { onMoveOrder(-1) })
                    }
                    if (canMoveDown) {
                        Text("↓", fontSize = 12.sp, color = AppTheme.colors.textMuted, modifier = Modifier.clickable { onMoveOrder(1) })
                    }
                }
            }
            Text(
                text = "↩",
                fontSize = 14.sp,
                fontWeight = FontWeight.Bold,
                color = Gold,
                modifier = Modifier
                    .padding(start = 8.dp)
                    .clickable { onReleaseFromMine() }
            )
        }
    }
}

@Composable
private fun TicketFullDetailAndChatView(
    task: ManagementTask,
    allTasks: List<ManagementTask>,
    messages: List<ManagementTaskMessageDto>,
    actionError: String?,
    onBack: () -> Unit,
    onSetStatus: (String) -> Unit,
    onToggleMine: () -> Unit,
    onPickAssignee: () -> Unit = {},
    onToggleSubtask: (String) -> Unit,
    onAddSubtask: (String) -> Unit,
    onDeleteTaskOrSub: (String) -> Unit,
    onUpdateTask: (String?, String?, String?, String?, String?, Int?) -> Unit,
    onResolveEscalation: (String, String?, String) -> Unit,
    onSendChat: (String, List<Triple<ByteArray, String, String>>) -> Unit,
    onEditChat: (String, String, List<String>) -> Unit
) {
    val context = androidx.compose.ui.platform.LocalContext.current
    val uriHandler = LocalUriHandler.current
    val subtasks = remember(allTasks, task.id) { allTasks.filter { it.parent_id == task.id } }
    val isEsc = task.isEscalatedEffective

    var editedTitle by remember(task.id, task.title) { mutableStateOf(task.title) }
    var editedNote by remember(task.id, task.ai_plan) { mutableStateOf(task.ai_plan ?: "") }
    var showMetaAccordion by remember(task.id) { mutableStateOf(!task.ai_plan.isNullOrBlank()) }
    var newSubtaskTitle by remember { mutableStateOf("") }
    var showDeleteConfirm by remember { mutableStateOf(false) }
    var detailStatusMenuOpen by remember { mutableStateOf(false) }

    var editingMessageId by remember(task.id) { mutableStateOf<String?>(null) }
    var editingMessageText by remember(task.id) { mutableStateOf("") }
    var editingKeepPaths by remember(task.id) { mutableStateOf<List<String>>(emptyList()) }

    val tomorrowIso = remember {
        val c = Calendar.getInstance()
        c.add(Calendar.DAY_OF_YEAR, 1)
        SimpleDateFormat("yyyy-MM-dd", Locale.US).format(c.time)
    }
    var escDate by remember(task.id) { mutableStateOf(tomorrowIso) }
    var escTime by remember(task.id) { mutableStateOf(task.due_time?.take(5)) }
    var escNote by remember(task.id) { mutableStateOf("") }

    var chatInput by remember(task.id) { mutableStateOf("") }
    var pendingFiles by remember(task.id) { mutableStateOf<List<Triple<ByteArray, String, String>>>(emptyList()) }

    val multiFileLauncher = rememberLauncherForActivityResult(
        contract = ActivityResultContracts.GetMultipleContents()
    ) { uris: List<Uri> ->
        if (uris.isNotEmpty()) {
            val added = mutableListOf<Triple<ByteArray, String, String>>()
            uris.forEach { uri ->
                try {
                    val cr = context.contentResolver
                    val mime = cr.getType(uri) ?: "application/octet-stream"
                    val fName = getFileName(context, uri) ?: "datei.bin"
                    val bytes = cr.openInputStream(uri)?.use { it.readBytes() }
                    if (bytes != null) {
                        added.add(Triple(bytes, fName, mime))
                    }
                } catch (_: Exception) {
                }
            }
            if (added.isNotEmpty()) {
                playAndroidTicketFx(context)
                pendingFiles = pendingFiles + added
            }
        }
    }

    Column(modifier = Modifier.fillMaxSize()) {
        // 1. Sticky Top Bar: Left = Zurueck | Right = Meine Toggle + SINGLE Status Dropdown + Delete
        Row(
            modifier = Modifier
                .fillMaxWidth()
                .padding(bottom = 8.dp),
            verticalAlignment = Alignment.CenterVertically,
            horizontalArrangement = Arrangement.SpaceBetween
        ) {
            Surface(
                onClick = onBack,
                color = AppTheme.colors.cardBackground,
                border = BorderStroke(1.dp, AppTheme.colors.cardBorder),
                shape = RoundedCornerShape(10.dp)
            ) {
                Row(
                    modifier = Modifier.padding(horizontal = 10.dp, vertical = 6.dp),
                    verticalAlignment = Alignment.CenterVertically
                ) {
                    Icon(imageVector = Icons.Default.ArrowBack, contentDescription = "Zurueck", tint = AppTheme.colors.textPrimary, modifier = Modifier.size(18.dp))
                    Spacer(modifier = Modifier.width(4.dp))
                    Text("Zurück", fontSize = 12.5.sp, fontWeight = FontWeight.Bold, color = AppTheme.colors.textPrimary)
                }
            }

            Row(verticalAlignment = Alignment.CenterVertically, horizontalArrangement = Arrangement.spacedBy(6.dp)) {
                Surface(
                    onClick = onPickAssignee,
                    color = AppTheme.colors.cardBackground,
                    border = BorderStroke(1.dp, if (!task.assignee_name.isNullOrBlank()) Gold else AppTheme.colors.cardBorder),
                    shape = RoundedCornerShape(9.dp)
                ) {
                    Text(
                        text = "👤 ${task.assignee_name ?: "Frei"} ▾",
                        fontSize = 11.sp,
                        fontWeight = FontWeight.SemiBold,
                        color = if (!task.assignee_name.isNullOrBlank()) Gold else AppTheme.colors.textSecondary,
                        maxLines = 1,
                        overflow = androidx.compose.ui.text.style.TextOverflow.Ellipsis,
                        modifier = Modifier.padding(horizontal = 8.dp, vertical = 6.dp)
                    )
                }

                Surface(
                    onClick = onToggleMine,
                    color = AppTheme.colors.cardBackground,
                    border = BorderStroke(1.dp, if (task.is_mine) Gold else AppTheme.colors.cardBorder),
                    shape = RoundedCornerShape(9.dp)
                ) {
                    Text(
                        text = if (task.is_mine) "↩ Lösen" else "📌 Meine",
                        fontSize = 11.5.sp,
                        fontWeight = FontWeight.SemiBold,
                        color = if (task.is_mine) Gold else AppTheme.colors.textSecondary,
                        modifier = Modifier.padding(horizontal = 9.dp, vertical = 6.dp)
                    )
                }

                // Single Top-Right Status Dropdown (ONLY the status word carries the color!)
                Box {
                    Surface(
                        onClick = { detailStatusMenuOpen = true },
                        color = AppTheme.colors.cardBackground,
                        border = BorderStroke(1.dp, AppTheme.colors.cardBorder),
                        shape = RoundedCornerShape(9.dp)
                    ) {
                        Text(
                            text = "${task.statusLabel} ▾",
                            fontSize = 12.5.sp,
                            fontWeight = FontWeight.Bold,
                            color = ticketStatusWordColor(task.normalizedStatus),
                            modifier = Modifier.padding(horizontal = 10.dp, vertical = 6.dp)
                        )
                    }
                    DropdownMenu(
                        expanded = detailStatusMenuOpen,
                        onDismissRequest = { detailStatusMenuOpen = false }
                    ) {
                        listOf(
                            "open" to "Offen",
                            "waiting" to "Warten",
                            "in_progress" to "In Bearbeitung",
                            "completed" to "Erledigt"
                        ).forEach { (stKey, stLabel) ->
                            DropdownMenuItem(
                                text = {
                                    Text(
                                        text = stLabel,
                                        color = ticketStatusWordColor(stKey),
                                        fontWeight = FontWeight.Bold,
                                        fontSize = 13.sp
                                    )
                                },
                                onClick = {
                                    detailStatusMenuOpen = false
                                    if (stKey != task.normalizedStatus) {
                                        onSetStatus(stKey)
                                    }
                                }
                            )
                        }
                    }
                }

                IconButton(onClick = { showDeleteConfirm = true }, modifier = Modifier.size(30.dp)) {
                    Icon(imageVector = Icons.Default.Delete, contentDescription = "Loeschen", tint = Color(0xFFF87171), modifier = Modifier.size(19.dp))
                }
            }
        }

        // 2. Scrollable Middle Area (Meta + Escalation Gate + Chronological Ticket-Chat)
        Column(
            modifier = Modifier
                .weight(1f)
                .verticalScroll(rememberScrollState()),
            verticalArrangement = Arrangement.spacedBy(8.dp)
        ) {
            // Compact Ticket Header Card
            Card(
                modifier = Modifier.fillMaxWidth(),
                colors = CardDefaults.cardColors(containerColor = AppTheme.colors.cardBackground),
                border = BorderStroke(1.dp, AppTheme.colors.cardBorder),
                shape = RoundedCornerShape(12.dp)
            ) {
                Column(modifier = Modifier.padding(12.dp), verticalArrangement = Arrangement.spacedBy(8.dp)) {
                    Text(
                        text = task.title,
                        fontSize = 16.sp,
                        fontWeight = FontWeight.Bold,
                        color = AppTheme.colors.textPrimary
                    )

                    val localDateStr = de.meinseelenfunke.app.util.DateUtils.parseUtcToLocalDateString(task.relevant_from)
                    val dispTime = task.due_time?.take(5)

                    Row(
                        modifier = Modifier.fillMaxWidth(),
                        horizontalArrangement = Arrangement.spacedBy(6.dp),
                        verticalAlignment = Alignment.CenterVertically
                    ) {
                        Surface(
                            onClick = {
                                if (isEsc) return@Surface
                                val cal = Calendar.getInstance()
                                android.app.DatePickerDialog(
                                    context,
                                    { _, y, m, d ->
                                        val nextDate = String.format(Locale.US, "%04d-%02d-%02d", y, m + 1, d)
                                        onUpdateTask(null, null, nextDate, null, dispTime, task.duration_minutes)
                                    },
                                    cal.get(Calendar.YEAR),
                                    cal.get(Calendar.MONTH),
                                    cal.get(Calendar.DAY_OF_MONTH)
                                ).show()
                            },
                            color = AppTheme.colors.inputBackground,
                            border = BorderStroke(1.dp, AppTheme.colors.inputBorder),
                            shape = RoundedCornerShape(8.dp)
                        ) {
                            Text(
                                text = "📅 " + (localDateStr ?: "Datum"),
                                fontSize = 11.5.sp,
                                fontWeight = FontWeight.SemiBold,
                                color = if (localDateStr != null) Gold else AppTheme.colors.textSecondary,
                                modifier = Modifier.padding(horizontal = 9.dp, vertical = 6.dp)
                            )
                        }

                        Surface(
                            onClick = {
                                if (isEsc) return@Surface
                                val cal = Calendar.getInstance()
                                android.app.TimePickerDialog(
                                    context,
                                    { _, h, min ->
                                        val nextTime = String.format(Locale.US, "%02d:%02d", h, min)
                                        val effDate = localDateStr ?: SimpleDateFormat("yyyy-MM-dd", Locale.US).format(Date())
                                        onUpdateTask(null, null, effDate, null, nextTime, task.duration_minutes ?: 60)
                                    },
                                    cal.get(Calendar.HOUR_OF_DAY),
                                    0,
                                    true
                                ).show()
                            },
                            color = AppTheme.colors.inputBackground,
                            border = BorderStroke(1.dp, AppTheme.colors.inputBorder),
                            shape = RoundedCornerShape(8.dp)
                        ) {
                            Text(
                                text = "🕒 " + (dispTime ?: "Uhrzeit"),
                                fontSize = 11.5.sp,
                                fontWeight = FontWeight.SemiBold,
                                color = if (dispTime != null) Gold else AppTheme.colors.textSecondary,
                                modifier = Modifier.padding(horizontal = 9.dp, vertical = 6.dp)
                            )
                        }

                        Surface(
                            onClick = { showMetaAccordion = !showMetaAccordion },
                            color = AppTheme.colors.inputBackground,
                            border = BorderStroke(1.dp, AppTheme.colors.inputBorder),
                            shape = RoundedCornerShape(8.dp)
                        ) {
                            Text(
                                text = (if (showMetaAccordion) "▾ " else "▸ ") + "Details (${subtasks.size})",
                                fontSize = 11.5.sp,
                                fontWeight = FontWeight.SemiBold,
                                color = AppTheme.colors.textSecondary,
                                modifier = Modifier.padding(horizontal = 9.dp, vertical = 6.dp)
                            )
                        }
                    }

                    AnimatedVisibility(visible = showMetaAccordion) {
                        Column(verticalArrangement = Arrangement.spacedBy(8.dp), modifier = Modifier.padding(top = 4.dp)) {
                            OutlinedTextField(
                                value = editedTitle,
                                onValueChange = { editedTitle = it },
                                label = { Text("Titel", fontSize = 11.sp) },
                                singleLine = true,
                                modifier = Modifier.fillMaxWidth()
                            )
                            OutlinedTextField(
                                value = editedNote,
                                onValueChange = { editedNote = it },
                                label = { Text("Ticket-Beschreibung", fontSize = 11.sp) },
                                minLines = 3,
                                maxLines = Int.MAX_VALUE,
                                modifier = Modifier.fillMaxWidth()
                            )
                            Button(
                                onClick = {
                                    onUpdateTask(editedTitle.trim().ifBlank { task.title }, null, null, editedNote.trim(), null, null)
                                    showMetaAccordion = false
                                },
                                colors = ButtonDefaults.buttonColors(containerColor = Gold, contentColor = Color(0xFF121316)),
                                shape = RoundedCornerShape(8.dp),
                                modifier = Modifier.align(Alignment.End)
                            ) {
                                Text("Details speichern", fontSize = 12.sp, fontWeight = FontWeight.Bold)
                            }

                            subtasks.forEach { sub ->
                                Row(
                                    verticalAlignment = Alignment.CenterVertically,
                                    horizontalArrangement = Arrangement.SpaceBetween,
                                    modifier = Modifier.fillMaxWidth()
                                ) {
                                    Row(verticalAlignment = Alignment.CenterVertically, modifier = Modifier.weight(1f).clickable { onToggleSubtask(sub.id) }) {
                                        Text(if (sub.is_completed) "☑" else "☐", color = Gold, fontSize = 14.sp)
                                        Spacer(modifier = Modifier.width(8.dp))
                                        Text(
                                            text = sub.title,
                                            fontSize = 13.sp,
                                            color = if (sub.is_completed) AppTheme.colors.textMuted else AppTheme.colors.textPrimary,
                                            textDecoration = if (sub.is_completed) TextDecoration.LineThrough else TextDecoration.None
                                        )
                                    }
                                    IconButton(onClick = { onDeleteTaskOrSub(sub.id) }, modifier = Modifier.size(24.dp)) {
                                        Icon(Icons.Default.Close, contentDescription = null, tint = AppTheme.colors.textMuted, modifier = Modifier.size(14.dp))
                                    }
                                }
                            }

                            Row(verticalAlignment = Alignment.CenterVertically, horizontalArrangement = Arrangement.spacedBy(6.dp)) {
                                OutlinedTextField(
                                    value = newSubtaskTitle,
                                    onValueChange = { newSubtaskTitle = it },
                                    placeholder = { Text("+ Schritt hinzufuegen", fontSize = 12.sp) },
                                    singleLine = true,
                                    modifier = Modifier.weight(1f)
                                )
                                if (newSubtaskTitle.isNotBlank()) {
                                    Button(
                                        onClick = {
                                            onAddSubtask(newSubtaskTitle.trim())
                                            newSubtaskTitle = ""
                                        },
                                        colors = ButtonDefaults.buttonColors(containerColor = Gold, contentColor = Color(0xFF121316)),
                                        shape = RoundedCornerShape(8.dp)
                                    ) {
                                        Text("+")
                                    }
                                }
                            }
                        }
                    }
                }
            }

            // Mandatory Escalation Resolution Gate
            if (isEsc) {
                Card(
                    modifier = Modifier.fillMaxWidth(),
                    colors = CardDefaults.cardColors(containerColor = Color(0xFF281217)),
                    border = BorderStroke(1.dp, Color(0xFFEF4444)),
                    shape = RoundedCornerShape(12.dp)
                ) {
                    Column(modifier = Modifier.padding(12.dp), verticalArrangement = Arrangement.spacedBy(8.dp)) {
                        Text(
                            text = "⚠ " + (task.escalation_reason ?: "Ticket eskaliert: Neues Zieldatum + Status erforderlich"),
                            fontSize = 12.5.sp,
                            fontWeight = FontWeight.Bold,
                            color = Color(0xFFFCA5A5)
                        )

                        Row(horizontalArrangement = Arrangement.spacedBy(8.dp)) {
                            Surface(
                                onClick = {
                                    val cal = Calendar.getInstance()
                                    cal.add(Calendar.DAY_OF_YEAR, 1)
                                    android.app.DatePickerDialog(
                                        context,
                                        { _, y, m, d -> escDate = String.format(Locale.US, "%04d-%02d-%02d", y, m + 1, d) },
                                        cal.get(Calendar.YEAR),
                                        cal.get(Calendar.MONTH),
                                        cal.get(Calendar.DAY_OF_MONTH)
                                    ).show()
                                },
                                color = Color(0xFF181215),
                                border = BorderStroke(1.dp, Color(0xFF7F1D1D)),
                                shape = RoundedCornerShape(8.dp)
                            ) {
                                Text("📅 $escDate", color = Color.White, fontSize = 12.sp, modifier = Modifier.padding(horizontal = 10.dp, vertical = 6.dp))
                            }

                            Surface(
                                onClick = {
                                    val cal = Calendar.getInstance()
                                    android.app.TimePickerDialog(
                                        context,
                                        { _, h, min -> escTime = String.format(Locale.US, "%02d:%02d", h, min) },
                                        cal.get(Calendar.HOUR_OF_DAY),
                                        0,
                                        true
                                    ).show()
                                },
                                color = Color(0xFF181215),
                                border = BorderStroke(1.dp, Color(0xFF7F1D1D)),
                                shape = RoundedCornerShape(8.dp)
                            ) {
                                Text("🕒 ${escTime ?: "Optional"}", color = Color.White, fontSize = 12.sp, modifier = Modifier.padding(horizontal = 10.dp, vertical = 6.dp))
                            }
                        }

                        OutlinedTextField(
                            value = escNote,
                            onValueChange = { escNote = it },
                            placeholder = { Text("Status eintragen (z. B. Warte weiter auf Rueckmeldung)...", fontSize = 12.sp, color = Color(0xFFFCA5A5).copy(alpha = 0.7f)) },
                            colors = OutlinedTextFieldDefaults.colors(
                                focusedBorderColor = Gold,
                                unfocusedBorderColor = Color(0xFF7F1D1D),
                                focusedTextColor = Color.White,
                                unfocusedTextColor = Color.White
                            ),
                            modifier = Modifier.fillMaxWidth(),
                            minLines = 2
                        )

                        Button(
                            onClick = {
                                if (escNote.trim().length >= 3) {
                                    onResolveEscalation(escDate, escTime, escNote.trim())
                                    escNote = ""
                                }
                            },
                            colors = ButtonDefaults.buttonColors(containerColor = Gold, contentColor = Color(0xFF121316)),
                            shape = RoundedCornerShape(8.dp),
                            modifier = Modifier.fillMaxWidth()
                        ) {
                            Text("Eskalation auflösen & Status senden", fontWeight = FontWeight.Bold, fontSize = 12.5.sp)
                        }
                    }
                }
            }

            if (!actionError.isNullOrBlank()) {
                Text(
                    text = actionError,
                    color = Color(0xFFF87171),
                    fontSize = 12.sp,
                    modifier = Modifier.padding(horizontal = 4.dp)
                )
            }

            // Chronological Ticket-Chat Messages
            if (messages.isEmpty()) {
                Box(
                    modifier = Modifier
                        .fillMaxWidth()
                        .padding(vertical = 28.dp),
                    contentAlignment = Alignment.Center
                ) {
                    Text(
                        text = "Noch keine Chat-Einträge vorhanden.",
                        fontSize = 12.5.sp,
                        color = AppTheme.colors.textMuted
                    )
                }
            } else {
                messages.forEach { msg ->
                    val isSysEsc = msg.message_type.equals("system_escalation", ignoreCase = true)
                    val isStatusMsg = msg.message_type.equals("status_change", ignoreCase = true)
                    val isEditingThis = editingMessageId == msg.id
                    val canEditMsg = msg.can_edit && !isSysEsc && !isStatusMsg && !msg.sender_type.equals("system", ignoreCase = true)
                    val sender = msg.sender_name?.takeIf { it.isNotBlank() } ?: "Alina Steinhauer"
                    val initials = sender.split(" ").filter { it.isNotBlank() }.take(2).joinToString("") { it.take(1).uppercase() }.ifBlank { "AS" }

                    Card(
                        modifier = Modifier.fillMaxWidth(),
                        colors = CardDefaults.cardColors(
                            containerColor = if (isSysEsc) Color(0xFF281217) else AppTheme.colors.cardBackground
                        ),
                        border = BorderStroke(1.dp, if (isEditingThis) Gold else if (isSysEsc) Color(0xFF7F1D1D) else AppTheme.colors.cardBorder),
                        shape = RoundedCornerShape(12.dp)
                    ) {
                        Column(modifier = Modifier.padding(11.dp), verticalArrangement = Arrangement.spacedBy(5.dp)) {
                            Row(
                                modifier = Modifier.fillMaxWidth(),
                                verticalAlignment = Alignment.CenterVertically,
                                horizontalArrangement = Arrangement.SpaceBetween
                            ) {
                                Row(verticalAlignment = Alignment.CenterVertically, modifier = Modifier.weight(1f)) {
                                    Box(
                                        modifier = Modifier
                                            .size(24.dp)
                                            .clip(CircleShape)
                                            .background(Gold.copy(alpha = 0.2f))
                                            .border(1.dp, Gold, CircleShape),
                                        contentAlignment = Alignment.Center
                                    ) {
                                        Text(initials, fontSize = 10.sp, fontWeight = FontWeight.Bold, color = Gold)
                                    }
                                    Spacer(modifier = Modifier.width(8.dp))
                                    Text(sender, fontSize = 12.5.sp, fontWeight = FontWeight.Bold, color = AppTheme.colors.textPrimary, maxLines = 1)
                                }
                                Row(verticalAlignment = Alignment.CenterVertically, horizontalArrangement = Arrangement.spacedBy(8.dp)) {
                                    if (canEditMsg && !isEditingThis) {
                                        Text(
                                            text = "✎ Anpassen",
                                            fontSize = 11.sp,
                                            fontWeight = FontWeight.Bold,
                                            color = Gold,
                                            modifier = Modifier.clickable {
                                                playAndroidTicketFx(context)
                                                editingMessageId = msg.id
                                                editingMessageText = msg.content ?: ""
                                                editingKeepPaths = msg.attachments?.mapNotNull { it.path } ?: emptyList()
                                            }
                                        )
                                    }
                                    val timeSuffix = if (!msg.edited_at.isNullOrBlank()) " · angepasst" else ""
                                    Text((msg.created_human ?: "") + timeSuffix, fontSize = 10.5.sp, color = AppTheme.colors.textMuted)
                                }
                            }

                            if (isEditingThis) {
                                Column(
                                    modifier = Modifier.padding(start = 32.dp, top = 2.dp),
                                    verticalArrangement = Arrangement.spacedBy(6.dp)
                                ) {
                                    OutlinedTextField(
                                        value = editingMessageText,
                                        onValueChange = { editingMessageText = it },
                                        minLines = 2,
                                        maxLines = 5,
                                        modifier = Modifier.fillMaxWidth()
                                    )
                                    if (!msg.attachments.isNullOrEmpty()) {
                                        msg.attachments.forEach { att ->
                                            val p = att.path ?: return@forEach
                                            if (p in editingKeepPaths) {
                                                val nm = att.name?.takeIf { it.isNotBlank() } ?: p.substringAfterLast('/')
                                                Text(
                                                    text = "📎 $nm  ✕",
                                                    fontSize = 11.5.sp,
                                                    color = Color(0xFFF87171),
                                                    modifier = Modifier.clickable {
                                                        editingKeepPaths = editingKeepPaths.filterNot { it == p }
                                                    }
                                                )
                                            }
                                        }
                                    }
                                    Row(
                                        modifier = Modifier.fillMaxWidth(),
                                        horizontalArrangement = Arrangement.End
                                    ) {
                                        TextButton(onClick = { editingMessageId = null }) {
                                            Text("Abbrechen", color = AppTheme.colors.textMuted, fontSize = 12.sp)
                                        }
                                        Button(
                                            onClick = {
                                                val trimmed = editingMessageText.trim()
                                                if (trimmed.isNotEmpty() || editingKeepPaths.isNotEmpty()) {
                                                    onEditChat(msg.id, trimmed, editingKeepPaths)
                                                    editingMessageId = null
                                                }
                                            },
                                            colors = ButtonDefaults.buttonColors(containerColor = Gold, contentColor = Color(0xFF121316)),
                                            shape = RoundedCornerShape(8.dp),
                                            contentPadding = PaddingValues(horizontal = 12.dp, vertical = 5.dp)
                                        ) {
                                            Text("Speichern", fontWeight = FontWeight.Bold, fontSize = 12.sp)
                                        }
                                    }
                                }
                            } else {
                                if (!msg.content.isNullOrBlank()) {
                                    Text(
                                        text = msg.content,
                                        fontSize = 13.5.sp,
                                        color = AppTheme.colors.textPrimary,
                                        modifier = Modifier.padding(start = 32.dp)
                                    )
                                }

                                if (!msg.attachments.isNullOrEmpty()) {
                                    Column(
                                        modifier = Modifier.padding(start = 32.dp, top = 2.dp),
                                        verticalArrangement = Arrangement.spacedBy(4.dp)
                                    ) {
                                        msg.attachments.forEach { att ->
                                            val attName = att.name?.takeIf { it.isNotBlank() } ?: att.path?.substringAfterLast('/') ?: "Datei"
                                            Surface(
                                                onClick = {
                                                    val path = att.path ?: return@Surface
                                                    val base = ServiceLocator.getBaseUrl().trimEnd('/')
                                                    val encPath = java.net.URLEncoder.encode(path, "UTF-8")
                                                    val encName = java.net.URLEncoder.encode(attName, "UTF-8")
                                                    try {
                                                        uriHandler.openUri("$base/funki/tasks/${task.id}/attachment?path=$encPath&name=$encName")
                                                    } catch (_: Exception) {
                                                    }
                                                },
                                                color = Gold.copy(alpha = 0.12f),
                                                border = BorderStroke(1.dp, Gold.copy(alpha = 0.4f)),
                                                shape = RoundedCornerShape(8.dp)
                                            ) {
                                                Text(
                                                    text = "📎 $attName",
                                                    fontSize = 12.sp,
                                                    color = Gold,
                                                    fontWeight = FontWeight.SemiBold,
                                                    modifier = Modifier.padding(horizontal = 10.dp, vertical = 5.dp)
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
        }

        // 3. Sticky Bottom Multi-Upload Chat Composer
        Card(
            modifier = Modifier
                .fillMaxWidth()
                .padding(top = 6.dp, bottom = 64.dp),
            colors = CardDefaults.cardColors(containerColor = AppTheme.colors.cardBackground),
            border = BorderStroke(1.dp, AppTheme.colors.cardBorder),
            shape = RoundedCornerShape(14.dp)
        ) {
            Column(modifier = Modifier.padding(8.dp)) {
                if (pendingFiles.isNotEmpty()) {
                    LazyRow(
                        horizontalArrangement = Arrangement.spacedBy(6.dp),
                        modifier = Modifier.padding(bottom = 6.dp)
                    ) {
                        items(pendingFiles.size) { idx ->
                            val (_, fName, _) = pendingFiles[idx]
                            Surface(
                                color = Gold.copy(alpha = 0.16f),
                                border = BorderStroke(1.dp, Gold),
                                shape = RoundedCornerShape(8.dp)
                            ) {
                                Row(
                                    verticalAlignment = Alignment.CenterVertically,
                                    modifier = Modifier.padding(horizontal = 8.dp, vertical = 4.dp)
                                ) {
                                    Text("📎 $fName", fontSize = 11.5.sp, color = AppTheme.colors.textPrimary, maxLines = 1)
                                    Spacer(modifier = Modifier.width(6.dp))
                                    Text(
                                        text = "✕",
                                        fontSize = 12.sp,
                                        fontWeight = FontWeight.Bold,
                                        color = Color(0xFFF87171),
                                        modifier = Modifier.clickable {
                                            pendingFiles = pendingFiles.filterIndexed { i, _ -> i != idx }
                                        }
                                    )
                                }
                            }
                        }
                    }
                }

                Row(
                    verticalAlignment = Alignment.CenterVertically,
                    horizontalArrangement = Arrangement.spacedBy(6.dp)
                ) {
                    IconButton(
                        onClick = { multiFileLauncher.launch("*/*") },
                        modifier = Modifier.size(36.dp)
                    ) {
                        Icon(imageVector = Icons.Default.AttachFile, contentDescription = "Dateien anhaengen", tint = Gold)
                    }

                    OutlinedTextField(
                        value = chatInput,
                        onValueChange = { chatInput = it },
                        placeholder = { Text("Nachricht in Ticket-Chat...", fontSize = 13.sp, color = AppTheme.colors.textMuted) },
                        colors = OutlinedTextFieldDefaults.colors(
                            focusedBorderColor = Gold,
                            unfocusedBorderColor = AppTheme.colors.inputBorder,
                            focusedTextColor = AppTheme.colors.textPrimary,
                            unfocusedTextColor = AppTheme.colors.textPrimary,
                            focusedContainerColor = AppTheme.colors.inputBackground,
                            unfocusedContainerColor = AppTheme.colors.inputBackground
                        ),
                        shape = RoundedCornerShape(10.dp),
                        maxLines = 4,
                        modifier = Modifier.weight(1f)
                    )

                    Button(
                        onClick = {
                            if (chatInput.isNotBlank() || pendingFiles.isNotEmpty()) {
                                val sendFiles = pendingFiles
                                val sendText = chatInput.trim()
                                chatInput = ""
                                pendingFiles = emptyList()
                                onSendChat(sendText, sendFiles)
                            }
                        },
                        colors = ButtonDefaults.buttonColors(containerColor = Gold, contentColor = Color(0xFF121316)),
                        shape = RoundedCornerShape(10.dp),
                        contentPadding = PaddingValues(horizontal = 12.dp, vertical = 10.dp)
                    ) {
                        Text("Senden", fontWeight = FontWeight.Bold, fontSize = 12.sp)
                    }
                }
            }
        }
    }

    if (showDeleteConfirm) {
        AlertDialog(
            onDismissRequest = { showDeleteConfirm = false },
            title = { Text("Ticket löschen", fontWeight = FontWeight.Bold, color = AppTheme.colors.textPrimary) },
            text = { Text("Möchtest du dieses Ticket inklusive Chat-Verlauf wirklich löschen?", color = AppTheme.colors.textSecondary) },
            confirmButton = {
                TextButton(onClick = {
                    showDeleteConfirm = false
                    onDeleteTaskOrSub(task.id)
                }) {
                    Text("Ja, löschen", color = Color.Red, fontWeight = FontWeight.Bold)
                }
            },
            dismissButton = {
                TextButton(onClick = { showDeleteConfirm = false }) {
                    Text("Abbrechen", color = AppTheme.colors.textMuted)
                }
            },
            containerColor = AppTheme.colors.cardBackground
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

