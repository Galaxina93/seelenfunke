package de.meinseelenfunke.app.ui.screens

import android.net.Uri
import android.webkit.WebView
import android.webkit.WebViewClient
import android.widget.Toast
import androidx.activity.compose.rememberLauncherForActivityResult
import androidx.activity.result.contract.ActivityResultContracts
import androidx.compose.animation.*
import androidx.compose.foundation.BorderStroke
import androidx.compose.foundation.background
import androidx.compose.foundation.border
import androidx.compose.foundation.clickable
import androidx.compose.foundation.layout.*
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.lazy.LazyRow
import androidx.compose.foundation.lazy.items
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.foundation.text.KeyboardOptions
import androidx.compose.foundation.text.selection.SelectionContainer
import androidx.compose.foundation.verticalScroll
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.*
import androidx.compose.material3.*
import androidx.compose.runtime.*
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.focus.onFocusChanged
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.graphics.vector.ImageVector
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.input.KeyboardType
import androidx.compose.ui.text.input.PasswordVisualTransformation
import androidx.compose.ui.text.input.VisualTransformation
import androidx.compose.ui.text.style.TextAlign
import androidx.compose.ui.text.style.TextOverflow
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import androidx.compose.ui.viewinterop.AndroidView
import androidx.compose.ui.window.Dialog
import androidx.compose.ui.window.DialogProperties
import androidx.compose.ui.window.PopupProperties
import de.meinseelenfunke.app.data.api.EmailAccount
import de.meinseelenfunke.app.data.api.EmailAttachment
import de.meinseelenfunke.app.data.api.EmailMessage
import de.meinseelenfunke.app.data.api.EmailMessageAttachment
import de.meinseelenfunke.app.ui.theme.AppTheme
import de.meinseelenfunke.app.ui.theme.Gold
import de.meinseelenfunke.app.ui.theme.Slate50
import de.meinseelenfunke.app.ui.theme.SpaceBlack

private val ImportantRed = Color(0xFFEF4444)
private val ResolvedGreen = Color(0xFF10B981)

@OptIn(ExperimentalMaterial3Api::class)
@Composable
fun EmailTabContent(
    viewModel: EmailViewModel,
    modifier: Modifier = Modifier
) {
    val uiState by viewModel.uiState.collectAsState()
    val accounts by viewModel.accounts.collectAsState()
    val processedSummary by viewModel.processedSummary.collectAsState()
    val messages by viewModel.messages.collectAsState()
    val selectedAccount by viewModel.selectedAccount.collectAsState()
    val selectedFolder by viewModel.selectedFolder.collectAsState()
    val funnelPriorityFilter by viewModel.funnelPriorityFilter.collectAsState()
    val funnelCategoryFilter by viewModel.funnelCategoryFilter.collectAsState()
    val selectedMessage by viewModel.selectedMessage.collectAsState()
    val searchQuery by viewModel.searchQuery.collectAsState()
    val unreadOnly by viewModel.unreadOnly.collectAsState()
    val isSending by viewModel.isSending.collectAsState()
    val isSyncing by viewModel.isSyncing.collectAsState()
    val isAiSorting by viewModel.isAiSorting.collectAsState()
    val statusBanner by viewModel.statusBanner.collectAsState()

    var showComposeDialog by remember { mutableStateOf(false) }
    var showAccountManagerInline by remember { mutableStateOf(false) }
    var editingAccountInline by remember { mutableStateOf<EmailAccount?>(null) }
    var showAddEditAccountInline by remember { mutableStateOf(false) }
    var showCreateFolderDialog by remember { mutableStateOf(false) }
    var showDeleteFolderDialog by remember { mutableStateOf(false) }
    var folderToDelete by remember { mutableStateOf<Pair<String, String>?>(null) }

    // Dialog states for Move / Auto-Rule from context menu or reader
    var messageForMoveDialog by remember { mutableStateOf<EmailMessage?>(null) }
    var messageForRuleDialog by remember { mutableStateOf<EmailMessage?>(null) }

    val context = LocalContext.current

    val accountFolders = selectedAccount?.folders
    val foldersList = remember(accountFolders) {
        buildEmailFoldersList(accountFolders)
    }

    val isGlobalFunnelView = selectedFolder == "Processed" || selectedFolder == "GlobalArchive"

    // Filter messages locally by priority & category when in Processed / GlobalArchive / Search
    val filteredMessages = remember(messages, funnelPriorityFilter, funnelCategoryFilter) {
        messages.filter { msg ->
            val prioOk = when (funnelPriorityFilter) {
                "high" -> msg.isImportant
                "normal" -> !msg.isImportant
                else -> true
            }
            val catOk = if (funnelCategoryFilter == "all") {
                true
            } else {
                msg.category.equals(funnelCategoryFilter, ignoreCase = true)
            }
            prioOk && catOk
        }
    }

    val categoryCounts = remember(messages) {
        messages
            .mapNotNull { it.category?.trim()?.takeIf { c -> c.isNotEmpty() } }
            .groupingBy { it }
            .eachCount()
            .entries
            .map { it.key to it.value }
            .sortedByDescending { it.second }
    }

    val highCount = remember(messages) { messages.count { it.isImportant } }
    val normalCount = remember(messages, highCount) { (messages.size - highCount).coerceAtLeast(0) }

    Box(modifier = modifier.fillMaxSize()) {
        if (showComposeDialog) {
            ComposeEmailInline(
                viewModel = viewModel,
                fromAccount = selectedAccount,
                isSending = isSending,
                onDismiss = {
                    viewModel.clearComposeAttachments()
                    showComposeDialog = false
                },
                onSend = { to, cc, bcc, subject, body, accountId, draftId, forwardedAtts ->
                    viewModel.sendEmail(
                        context = context,
                        to = to,
                        subject = subject,
                        body = body,
                        cc = cc,
                        bcc = bcc,
                        accountId = accountId,
                        draftId = draftId,
                        forwardedAttachments = forwardedAtts
                    ) { success ->
                        if (success) {
                            Toast.makeText(context, "E-Mail erfolgreich gesendet!", Toast.LENGTH_SHORT).show()
                            showComposeDialog = false
                        } else {
                            Toast.makeText(context, "Fehler beim Senden der E-Mail.", Toast.LENGTH_LONG).show()
                        }
                    }
                }
            )
        } else if (showAccountManagerInline) {
            if (showAddEditAccountInline) {
                AddEditAccountInline(
                    account = editingAccountInline,
                    viewModel = viewModel,
                    onDismiss = {
                        showAddEditAccountInline = false
                        editingAccountInline = null
                    }
                )
            } else {
                AccountManagerInline(
                    viewModel = viewModel,
                    onDismiss = { showAccountManagerInline = false },
                    onAddAccount = {
                        editingAccountInline = null
                        showAddEditAccountInline = true
                    },
                    onEditAccount = { account ->
                        editingAccountInline = account
                        showAddEditAccountInline = true
                    }
                )
            }
        } else {
            Column(
                modifier = Modifier.fillMaxSize(),
                verticalArrangement = Arrangement.spacedBy(8.dp)
            ) {
                EmailTopHeaderRow(
                    isAiSorting = isAiSorting,
                    isSyncing = isSyncing,
                    onAiSort = { viewModel.runAiSort() },
                    onSync = { viewModel.syncEmails() },
                    onOpenAccountManager = { showAccountManagerInline = true }
                )

                EmailStatusBannerRow(
                    statusBanner = statusBanner,
                    onClear = { viewModel.clearStatusBanner() }
                )

                EmailAccountsAndFunnelSection(
                    accounts = accounts,
                    selectedFolder = selectedFolder,
                    processedSummary = processedSummary,
                    isGlobalFunnelView = isGlobalFunnelView,
                    selectedAccount = selectedAccount,
                    onSelectProcessed = { viewModel.selectGlobalProcessed() },
                    onSelectArchive = { viewModel.selectGlobalArchive() },
                    onSelectAccount = { viewModel.selectAccount(it) },
                    onOpenAccountManager = { showAccountManagerInline = true }
                )

                if (!isGlobalFunnelView && selectedAccount != null) {
                    EmailAccountFoldersRow(
                        foldersList = foldersList,
                        selectedFolder = selectedFolder,
                        selectedAccount = selectedAccount,
                        onSelectFolder = { viewModel.selectFolder(it) },
                        onRequestDeleteFolder = { key, label ->
                            folderToDelete = Pair(key, label)
                            showDeleteFolderDialog = true
                        },
                        onRequestCreateFolder = { showCreateFolderDialog = true }
                    )
                }

                if (isGlobalFunnelView || highCount > 0 || categoryCounts.isNotEmpty()) {
                    EmailFunnelFilterPillsRow(
                        totalMessagesCount = messages.size,
                        highCount = highCount,
                        normalCount = normalCount,
                        categoryCounts = categoryCounts,
                        funnelPriorityFilter = funnelPriorityFilter,
                        funnelCategoryFilter = funnelCategoryFilter,
                        onSetPriorityFilter = { viewModel.setFunnelPriorityFilter(it) },
                        onSetCategoryFilter = { viewModel.setFunnelCategoryFilter(it) }
                    )
                }

                EmailSearchAndUnreadRow(
                    searchQuery = searchQuery,
                    unreadOnly = unreadOnly,
                    onSearchChange = { viewModel.setSearchQuery(it) },
                    onToggleUnread = { viewModel.setUnreadOnly(!unreadOnly) }
                )

                EmailMessageListArea(
                    modifier = Modifier
                        .weight(1f)
                        .fillMaxWidth(),
                    uiState = uiState,
                    filteredMessages = filteredMessages,
                    isGlobalFunnelView = isGlobalFunnelView,
                    viewModel = viewModel,
                    onOpenCompose = { showComposeDialog = true },
                    onRequestMove = { messageForMoveDialog = it },
                    onRequestRule = { messageForRuleDialog = it }
                )
            }

            // Floating Action Button to compose new email
            FloatingActionButton(
                onClick = {
                    viewModel.prepareComposeNew()
                    showComposeDialog = true
                },
                containerColor = Gold,
                contentColor = SpaceBlack,
                modifier = Modifier
                    .align(Alignment.BottomEnd)
                    .padding(bottom = 12.dp, end = 8.dp)
            ) {
                Icon(
                    imageVector = Icons.Default.Edit,
                    contentDescription = "Verfassen"
                )
            }
        }

        EmailOverlayDialogs(
            viewModel = viewModel,
            selectedMessage = selectedMessage,
            selectedFolder = selectedFolder,
            foldersList = foldersList,
            messageForMoveDialog = messageForMoveDialog,
            messageForRuleDialog = messageForRuleDialog,
            showCreateFolderDialog = showCreateFolderDialog,
            showDeleteFolderDialog = showDeleteFolderDialog,
            folderToDelete = folderToDelete,
            onOpenCompose = { showComposeDialog = true },
            onSetMoveDialogMessage = { messageForMoveDialog = it },
            onSetRuleDialogMessage = { messageForRuleDialog = it },
            onDismissCreateFolder = { showCreateFolderDialog = false },
            onDismissDeleteFolder = { showDeleteFolderDialog = false }
        )
    }
}

private fun buildEmailFoldersList(accountFolders: Map<String, String>?): List<Triple<String, String, ImageVector>> {
    val base = mutableListOf<Triple<String, String, ImageVector>>()
    if (!accountFolders.isNullOrEmpty()) {
        accountFolders.forEach { (key, label) ->
            val icon = when (key) {
                "INBOX" -> Icons.Default.Inbox
                "Sent" -> Icons.Default.Send
                "Drafts" -> Icons.Default.Drafts
                "Junk" -> Icons.Default.Report
                "Trash" -> Icons.Default.Delete
                "Archive" -> Icons.Default.Archive
                else -> Icons.Default.Folder
            }
            base.add(Triple(key, label, icon))
        }
    } else {
        base.add(Triple("INBOX", "Posteingang", Icons.Default.Inbox))
        base.add(Triple("Sent", "Gesendet", Icons.Default.Send))
        base.add(Triple("Drafts", "Entwürfe", Icons.Default.Drafts))
        base.add(Triple("Junk", "Spam", Icons.Default.Report))
        base.add(Triple("Trash", "Papierkorb", Icons.Default.Delete))
        base.add(Triple("Archive", "Archiv", Icons.Default.Archive))
    }
    return base
}

@Composable
private fun EmailTopHeaderRow(
    isAiSorting: Boolean,
    isSyncing: Boolean,
    onAiSort: () -> Unit,
    onSync: () -> Unit,
    onOpenAccountManager: () -> Unit
) {
    Row(
        modifier = Modifier.fillMaxWidth(),
        horizontalArrangement = Arrangement.SpaceBetween,
        verticalAlignment = Alignment.CenterVertically
    ) {
        Text(
            text = "E-Mail & KI-Trichter",
            fontSize = 17.sp,
            fontWeight = FontWeight.Bold,
            color = Gold
        )

        Row(
            horizontalArrangement = Arrangement.spacedBy(6.dp),
            verticalAlignment = Alignment.CenterVertically
        ) {
            Box(
                modifier = Modifier
                    .clip(RoundedCornerShape(8.dp))
                    .background(Gold.copy(alpha = 0.16f))
                    .border(1.dp, Gold, RoundedCornerShape(8.dp))
                    .clickable(enabled = !isAiSorting) { onAiSort() }
                    .padding(horizontal = 10.dp, vertical = 6.dp),
                contentAlignment = Alignment.Center
            ) {
                Row(
                    verticalAlignment = Alignment.CenterVertically,
                    horizontalArrangement = Arrangement.spacedBy(4.dp)
                ) {
                    if (isAiSorting) {
                        CircularProgressIndicator(
                            color = Gold,
                            strokeWidth = 2.dp,
                            modifier = Modifier.size(13.dp)
                        )
                    } else {
                        Icon(
                            imageVector = Icons.Default.AutoAwesome,
                            contentDescription = "KI-Trichter",
                            tint = Gold,
                            modifier = Modifier.size(14.dp)
                        )
                    }
                    Text(
                        text = if (isAiSorting) "Prüft..." else "KI-Trichter",
                        color = Gold,
                        fontSize = 11.sp,
                        fontWeight = FontWeight.Bold
                    )
                }
            }

            IconButton(
                onClick = onSync,
                enabled = !isSyncing,
                modifier = Modifier
                    .background(AppTheme.colors.glassSurface, shape = RoundedCornerShape(8.dp))
                    .border(1.dp, AppTheme.colors.glassBorder, shape = RoundedCornerShape(8.dp))
                    .size(34.dp)
            ) {
                if (isSyncing) {
                    CircularProgressIndicator(
                        color = Gold,
                        strokeWidth = 2.dp,
                        modifier = Modifier.size(15.dp)
                    )
                } else {
                    Icon(
                        imageVector = Icons.Default.Sync,
                        contentDescription = "Synchronisieren",
                        tint = AppTheme.colors.textPrimary,
                        modifier = Modifier.size(17.dp)
                    )
                }
            }

            IconButton(
                onClick = onOpenAccountManager,
                modifier = Modifier
                    .background(AppTheme.colors.glassSurface, shape = RoundedCornerShape(8.dp))
                    .border(1.dp, AppTheme.colors.glassBorder, shape = RoundedCornerShape(8.dp))
                    .size(34.dp)
            ) {
                Icon(
                    imageVector = Icons.Default.Settings,
                    contentDescription = "Postfächer verwalten",
                    tint = Gold,
                    modifier = Modifier.size(17.dp)
                )
            }
        }
    }
}

@Composable
private fun EmailStatusBannerRow(
    statusBanner: String?,
    onClear: () -> Unit
) {
    AnimatedVisibility(visible = !statusBanner.isNullOrBlank()) {
        statusBanner?.let { bannerText ->
            Row(
                modifier = Modifier
                    .fillMaxWidth()
                    .clip(RoundedCornerShape(8.dp))
                    .background(Gold.copy(alpha = 0.14f))
                    .border(1.dp, Gold.copy(alpha = 0.6f), RoundedCornerShape(8.dp))
                    .padding(horizontal = 10.dp, vertical = 6.dp),
                horizontalArrangement = Arrangement.SpaceBetween,
                verticalAlignment = Alignment.CenterVertically
            ) {
                Text(
                    text = bannerText,
                    color = AppTheme.colors.textPrimary,
                    fontSize = 12.sp,
                    fontWeight = FontWeight.SemiBold,
                    modifier = Modifier.weight(1f)
                )
                Icon(
                    imageVector = Icons.Default.Close,
                    contentDescription = "Schließen",
                    tint = AppTheme.colors.textMuted,
                    modifier = Modifier
                        .size(16.dp)
                        .clickable { onClear() }
                )
            }
        }
    }
}

@Composable
private fun EmailAccountsAndFunnelSection(
    accounts: List<EmailAccount>,
    selectedFolder: String,
    processedSummary: de.meinseelenfunke.app.data.api.EmailProcessedSummary,
    isGlobalFunnelView: Boolean,
    selectedAccount: EmailAccount?,
    onSelectProcessed: () -> Unit,
    onSelectArchive: () -> Unit,
    onSelectAccount: (EmailAccount) -> Unit,
    onOpenAccountManager: () -> Unit
) {
    if (accounts.isNotEmpty()) {
        LazyRow(
            horizontalArrangement = Arrangement.spacedBy(6.dp),
            verticalAlignment = Alignment.CenterVertically,
            modifier = Modifier.fillMaxWidth()
        ) {
            item {
                val isProcessedSelected = selectedFolder == "Processed"
                val badgeCount = if (processedSummary.importantCount > 0) {
                    processedSummary.importantCount
                } else {
                    processedSummary.totalCount
                }
                val hasImportant = processedSummary.importantCount > 0
                Box(
                    modifier = Modifier
                        .clip(RoundedCornerShape(18.dp))
                        .background(if (isProcessedSelected) Gold else AppTheme.colors.cardBackground)
                        .border(
                            1.dp,
                            if (isProcessedSelected) Gold else if (hasImportant) ImportantRed.copy(alpha = 0.7f) else AppTheme.colors.cardBorder,
                            RoundedCornerShape(18.dp)
                        )
                        .clickable { onSelectProcessed() }
                        .padding(horizontal = 11.dp, vertical = 6.dp)
                ) {
                    Row(
                        verticalAlignment = Alignment.CenterVertically,
                        horizontalArrangement = Arrangement.spacedBy(5.dp)
                    ) {
                        Text(
                            text = "✨ Verarbeitet",
                            color = if (isProcessedSelected) SpaceBlack else AppTheme.colors.textPrimary,
                            fontSize = 12.sp,
                            fontWeight = FontWeight.Bold
                        )
                        if (badgeCount > 0) {
                            Box(
                                modifier = Modifier
                                    .clip(CircleShape)
                                    .background(
                                        when {
                                            hasImportant -> ImportantRed
                                            isProcessedSelected -> SpaceBlack
                                            else -> Gold
                                        }
                                    )
                                    .padding(horizontal = 6.dp, vertical = 1.dp)
                            ) {
                                Text(
                                    text = badgeCount.toString(),
                                    color = if (hasImportant) Color.White else if (isProcessedSelected) Gold else SpaceBlack,
                                    fontSize = 10.sp,
                                    fontWeight = FontWeight.Bold
                                )
                            }
                        }
                    }
                }
            }

            item {
                val isArchiveSelected = selectedFolder == "GlobalArchive"
                Box(
                    modifier = Modifier
                        .clip(RoundedCornerShape(18.dp))
                        .background(if (isArchiveSelected) Gold else AppTheme.colors.cardBackground)
                        .border(
                            1.dp,
                            if (isArchiveSelected) Gold else AppTheme.colors.cardBorder,
                            RoundedCornerShape(18.dp)
                        )
                        .clickable { onSelectArchive() }
                        .padding(horizontal = 11.dp, vertical = 6.dp)
                ) {
                    Row(
                        verticalAlignment = Alignment.CenterVertically,
                        horizontalArrangement = Arrangement.spacedBy(5.dp)
                    ) {
                        Text(
                            text = "📦 Archiv",
                            color = if (isArchiveSelected) SpaceBlack else AppTheme.colors.textPrimary,
                            fontSize = 12.sp,
                            fontWeight = FontWeight.Bold
                        )
                        if (processedSummary.archivedCount > 0) {
                            Text(
                                text = "(${processedSummary.archivedCount})",
                                color = if (isArchiveSelected) SpaceBlack.copy(alpha = 0.75f) else AppTheme.colors.textMuted,
                                fontSize = 10.sp,
                                fontWeight = FontWeight.SemiBold
                            )
                        }
                    }
                }
            }

            items(accounts) { account ->
                val isSelected = !isGlobalFunnelView && account.id == selectedAccount?.id
                AccountChip(
                    account = account,
                    isSelected = isSelected,
                    onClick = { onSelectAccount(account) }
                )
            }
        }
    } else {
        Column(
            modifier = Modifier
                .fillMaxWidth()
                .padding(vertical = 8.dp),
            horizontalAlignment = Alignment.CenterHorizontally,
            verticalArrangement = Arrangement.spacedBy(8.dp)
        ) {
            Text(
                text = "Keine E-Mail-Konten eingerichtet.",
                color = AppTheme.colors.textMuted,
                fontSize = 14.sp
            )
            Button(
                onClick = onOpenAccountManager,
                colors = ButtonDefaults.buttonColors(containerColor = Gold, contentColor = SpaceBlack),
                shape = RoundedCornerShape(8.dp)
            ) {
                Icon(Icons.Default.Add, contentDescription = null, modifier = Modifier.size(16.dp))
                Spacer(modifier = Modifier.width(6.dp))
                Text("Konto einrichten", fontSize = 12.sp, fontWeight = FontWeight.Bold)
            }
        }
    }
}

@Composable
private fun EmailAccountFoldersRow(
    foldersList: List<Triple<String, String, ImageVector>>,
    selectedFolder: String,
    selectedAccount: EmailAccount?,
    onSelectFolder: (String) -> Unit,
    onRequestDeleteFolder: (String, String) -> Unit,
    onRequestCreateFolder: () -> Unit
) {
    Row(
        modifier = Modifier.fillMaxWidth(),
        verticalAlignment = Alignment.CenterVertically,
        horizontalArrangement = Arrangement.spacedBy(6.dp)
    ) {
        LazyRow(
            modifier = Modifier.weight(1f),
            horizontalArrangement = Arrangement.spacedBy(6.dp)
        ) {
            items(foldersList) { (folderKey, label, icon) ->
                val isSelected = selectedFolder == folderKey
                val unreadInFolder = selectedAccount?.counts?.get(folderKey) ?: 0
                val totalInFolder = selectedAccount?.totalCounts?.get(folderKey) ?: 0
                Box(
                    modifier = Modifier
                        .clip(RoundedCornerShape(8.dp))
                        .background(if (isSelected) Gold else AppTheme.colors.glassSurface)
                        .border(
                            1.dp,
                            if (isSelected) Gold else AppTheme.colors.glassBorder,
                            RoundedCornerShape(8.dp)
                        )
                        .clickable { onSelectFolder(folderKey) }
                        .padding(horizontal = 10.dp, vertical = 6.dp),
                    contentAlignment = Alignment.Center
                ) {
                    Row(
                        horizontalArrangement = Arrangement.spacedBy(5.dp),
                        verticalAlignment = Alignment.CenterVertically
                    ) {
                        Icon(
                            imageVector = icon,
                            contentDescription = label,
                            tint = if (isSelected) SpaceBlack else AppTheme.colors.textSecondary,
                            modifier = Modifier.size(14.dp)
                        )
                        Text(
                            text = label,
                            fontSize = 11.sp,
                            fontWeight = FontWeight.Bold,
                            color = if (isSelected) SpaceBlack else AppTheme.colors.textPrimary,
                            maxLines = 1
                        )
                        if (unreadInFolder > 0) {
                            Box(
                                modifier = Modifier
                                    .clip(CircleShape)
                                    .background(if (isSelected) SpaceBlack else Gold)
                                    .padding(horizontal = 5.dp, vertical = 1.dp)
                            ) {
                                Text(
                                    text = unreadInFolder.toString(),
                                    color = if (isSelected) Gold else SpaceBlack,
                                    fontSize = 9.sp,
                                    fontWeight = FontWeight.Bold
                                )
                            }
                        } else if (totalInFolder > 0) {
                            Text(
                                text = "($totalInFolder)",
                                color = if (isSelected) SpaceBlack.copy(alpha = 0.7f) else AppTheme.colors.textMuted,
                                fontSize = 10.sp
                            )
                        }

                        val isBaseFolder = folderKey in listOf("INBOX", "Sent", "Trash", "Archive", "Drafts", "Junk")
                        if (!isBaseFolder) {
                            Icon(
                                imageVector = Icons.Default.Close,
                                contentDescription = "Ordner löschen",
                                tint = if (isSelected) SpaceBlack.copy(alpha = 0.6f) else AppTheme.colors.textMuted,
                                modifier = Modifier
                                    .size(13.dp)
                                    .clickable { onRequestDeleteFolder(folderKey, label) }
                            )
                        }
                    }
                }
            }
        }

        IconButton(
            onClick = onRequestCreateFolder,
            modifier = Modifier
                .background(AppTheme.colors.glassSurface, shape = RoundedCornerShape(8.dp))
                .border(1.dp, AppTheme.colors.glassBorder, shape = RoundedCornerShape(8.dp))
                .size(32.dp)
        ) {
            Icon(
                imageVector = Icons.Default.CreateNewFolder,
                contentDescription = "Ordner erstellen",
                tint = Gold,
                modifier = Modifier.size(17.dp)
            )
        }
    }
}

@Composable
private fun EmailFunnelFilterPillsRow(
    totalMessagesCount: Int,
    highCount: Int,
    normalCount: Int,
    categoryCounts: List<Pair<String, Int>>,
    funnelPriorityFilter: String,
    funnelCategoryFilter: String,
    onSetPriorityFilter: (String) -> Unit,
    onSetCategoryFilter: (String) -> Unit
) {
    LazyRow(
        horizontalArrangement = Arrangement.spacedBy(6.dp),
        modifier = Modifier.fillMaxWidth()
    ) {
        item {
            val active = funnelPriorityFilter == "all" && funnelCategoryFilter == "all"
            FunnelFilterChip(
                label = "Alle ($totalMessagesCount)",
                isSelected = active,
                accentColor = Gold,
                onClick = {
                    onSetPriorityFilter("all")
                    onSetCategoryFilter("all")
                }
            )
        }
        item {
            val active = funnelPriorityFilter == "high"
            FunnelFilterChip(
                label = "🔴 Wichtig ($highCount)",
                isSelected = active,
                accentColor = ImportantRed,
                onClick = {
                    onSetPriorityFilter(if (active) "all" else "high")
                }
            )
        }
        item {
            val active = funnelPriorityFilter == "normal"
            FunnelFilterChip(
                label = "⚪ Normal ($normalCount)",
                isSelected = active,
                accentColor = Gold,
                onClick = {
                    onSetPriorityFilter(if (active) "all" else "normal")
                }
            )
        }
        items(categoryCounts) { (catName, count) ->
            val active = funnelCategoryFilter.equals(catName, ignoreCase = true)
            FunnelFilterChip(
                label = "🏷 $catName ($count)",
                isSelected = active,
                accentColor = Gold,
                onClick = {
                    onSetCategoryFilter(if (active) "all" else catName)
                }
            )
        }
    }
}

@Composable
private fun EmailSearchAndUnreadRow(
    searchQuery: String,
    unreadOnly: Boolean,
    onSearchChange: (String) -> Unit,
    onToggleUnread: () -> Unit
) {
    Row(
        modifier = Modifier.fillMaxWidth(),
        horizontalArrangement = Arrangement.spacedBy(6.dp),
        verticalAlignment = Alignment.CenterVertically
    ) {
        OutlinedTextField(
            value = searchQuery,
            onValueChange = onSearchChange,
            placeholder = {
                Text(
                    "E-Mails durchsuchen...",
                    color = AppTheme.colors.textMuted,
                    fontSize = 12.sp
                )
            },
            leadingIcon = {
                Icon(
                    Icons.Default.Search,
                    contentDescription = "Suchen",
                    tint = AppTheme.colors.textMuted,
                    modifier = Modifier.size(18.dp)
                )
            },
            trailingIcon = {
                if (searchQuery.isNotEmpty()) {
                    IconButton(onClick = { onSearchChange("") }) {
                        Icon(
                            Icons.Default.Close,
                            contentDescription = "Suche löschen",
                            tint = AppTheme.colors.textMuted,
                            modifier = Modifier.size(16.dp)
                        )
                    }
                }
            },
            singleLine = true,
            modifier = Modifier.weight(1f),
            colors = OutlinedTextFieldDefaults.colors(
                focusedBorderColor = Gold,
                unfocusedBorderColor = AppTheme.colors.inputBorder,
                focusedTextColor = AppTheme.colors.textPrimary,
                unfocusedTextColor = AppTheme.colors.textPrimary,
                focusedContainerColor = AppTheme.colors.inputBackground,
                unfocusedContainerColor = AppTheme.colors.inputBackground
            ),
            shape = RoundedCornerShape(10.dp)
        )

        Box(
            modifier = Modifier
                .clip(RoundedCornerShape(10.dp))
                .background(if (unreadOnly) Gold else AppTheme.colors.glassSurface)
                .border(
                    1.dp,
                    if (unreadOnly) Gold else AppTheme.colors.glassBorder,
                    RoundedCornerShape(10.dp)
                )
                .clickable { onToggleUnread() }
                .padding(horizontal = 10.dp, vertical = 12.dp),
            contentAlignment = Alignment.Center
        ) {
            Row(
                verticalAlignment = Alignment.CenterVertically,
                horizontalArrangement = Arrangement.spacedBy(4.dp)
            ) {
                Icon(
                    imageVector = if (unreadOnly) Icons.Default.MarkEmailUnread else Icons.Default.MailOutline,
                    contentDescription = "Ungelesene filtern",
                    tint = if (unreadOnly) SpaceBlack else AppTheme.colors.textSecondary,
                    modifier = Modifier.size(16.dp)
                )
                Text(
                    text = "Ungelesen",
                    color = if (unreadOnly) SpaceBlack else AppTheme.colors.textSecondary,
                    fontSize = 11.sp,
                    fontWeight = FontWeight.Bold
                )
            }
        }
    }
}

@Composable
private fun EmailMessageListArea(
    modifier: Modifier,
    uiState: EmailUiState,
    filteredMessages: List<EmailMessage>,
    isGlobalFunnelView: Boolean,
    viewModel: EmailViewModel,
    onOpenCompose: () -> Unit,
    onRequestMove: (EmailMessage) -> Unit,
    onRequestRule: (EmailMessage) -> Unit
) {
    when (uiState) {
        is EmailUiState.Loading -> {
            Box(
                modifier = modifier,
                contentAlignment = Alignment.Center
            ) {
                CircularProgressIndicator(color = Gold)
            }
        }
        is EmailUiState.Error -> {
            Box(
                modifier = modifier,
                contentAlignment = Alignment.Center
            ) {
                Column(
                    horizontalAlignment = Alignment.CenterHorizontally,
                    verticalArrangement = Arrangement.spacedBy(8.dp)
                ) {
                    Text(
                        text = uiState.message,
                        color = ImportantRed,
                        textAlign = TextAlign.Center,
                        fontSize = 13.sp
                    )
                    Button(
                        onClick = { viewModel.loadAccountsAndMessages() },
                        colors = ButtonDefaults.buttonColors(containerColor = Gold, contentColor = SpaceBlack)
                    ) {
                        Text("Erneut versuchen", fontWeight = FontWeight.Bold)
                    }
                }
            }
        }
        is EmailUiState.Success -> {
            if (filteredMessages.isEmpty()) {
                Box(
                    modifier = modifier,
                    contentAlignment = Alignment.Center
                ) {
                    Text(
                        text = "Keine E-Mails in dieser Ansicht.",
                        color = AppTheme.colors.textMuted,
                        fontSize = 13.sp
                    )
                }
            } else {
                LazyColumn(
                    modifier = modifier,
                    verticalArrangement = Arrangement.spacedBy(8.dp)
                ) {
                    items(filteredMessages, key = { it.id }) { message ->
                        EmailMessageRow(
                            message = message,
                            showAccountBadge = isGlobalFunnelView,
                            onClick = {
                                if (message.folder == "Drafts") {
                                    viewModel.prepareComposeEditDraft(message)
                                    onOpenCompose()
                                } else {
                                    viewModel.selectMessage(message)
                                }
                            },
                            onResolveImportant = { makeImportant ->
                                viewModel.resolveMessageImportance(message, makeImportant)
                            },
                            onToggleRead = { viewModel.toggleMessageRead(message) },
                            onReply = {
                                viewModel.prepareComposeReply(message)
                                onOpenCompose()
                            },
                            onForward = {
                                viewModel.prepareComposeForward(message)
                                onOpenCompose()
                            },
                            onCreateTask = { viewModel.createTaskFromMail(message) },
                            onSaveContact = { viewModel.createContactFromMail(message) },
                            onArchive = { viewModel.moveMessage(message, "Archive") },
                            onMoveRequest = { onRequestMove(message) },
                            onRuleRequest = { onRequestRule(message) },
                            onMarkSpam = { viewModel.moveMessage(message, "Junk") },
                            onDelete = { viewModel.deleteMessage(message) }
                        )
                    }
                    item {
                        Spacer(modifier = Modifier.height(76.dp))
                    }
                }
            }
        }
    }
}

@Composable
private fun EmailOverlayDialogs(
    viewModel: EmailViewModel,
    selectedMessage: EmailMessage?,
    selectedFolder: String,
    foldersList: List<Triple<String, String, ImageVector>>,
    messageForMoveDialog: EmailMessage?,
    messageForRuleDialog: EmailMessage?,
    showCreateFolderDialog: Boolean,
    showDeleteFolderDialog: Boolean,
    folderToDelete: Pair<String, String>?,
    onOpenCompose: () -> Unit,
    onSetMoveDialogMessage: (EmailMessage?) -> Unit,
    onSetRuleDialogMessage: (EmailMessage?) -> Unit,
    onDismissCreateFolder: () -> Unit,
    onDismissDeleteFolder: () -> Unit
) {
    val context = LocalContext.current

    selectedMessage?.let { msg ->
        EmailDetailDialog(
            message = msg,
            currentFolder = selectedFolder,
            onDismiss = { viewModel.clearSelectedMessage() },
            onDelete = {
                viewModel.deleteMessage(msg)
                viewModel.clearSelectedMessage()
            },
            onToggleUnread = {
                viewModel.toggleMessageRead(msg)
            },
            onReply = {
                viewModel.clearSelectedMessage()
                viewModel.prepareComposeReply(msg)
                onOpenCompose()
            },
            onForward = {
                viewModel.clearSelectedMessage()
                viewModel.prepareComposeForward(msg)
                onOpenCompose()
            },
            onEditDraft = {
                viewModel.clearSelectedMessage()
                viewModel.prepareComposeEditDraft(msg)
                onOpenCompose()
            },
            onArchive = {
                viewModel.moveMessage(msg, "Archive")
                viewModel.clearSelectedMessage()
            },
            onResolveImportant = { makeImportant ->
                viewModel.resolveMessageImportance(msg, makeImportant)
            },
            onCreateTask = {
                viewModel.createTaskFromMail(msg)
            },
            onSaveContact = {
                viewModel.createContactFromMail(msg)
            },
            onMoveRequest = {
                onSetMoveDialogMessage(msg)
            },
            onRuleRequest = {
                onSetRuleDialogMessage(msg)
            },
            onMarkSpam = {
                viewModel.moveMessage(msg, "Junk")
                viewModel.clearSelectedMessage()
            },
            onDownloadAttachment = { att ->
                viewModel.downloadAndOpenAttachment(context, att)
            }
        )
    }

    messageForMoveDialog?.let { targetMsg ->
        AlertDialog(
            onDismissRequest = { onSetMoveDialogMessage(null) },
            containerColor = AppTheme.colors.cardBackground,
            shape = RoundedCornerShape(16.dp),
            title = {
                Text("E-Mail verschieben nach", color = Gold, fontWeight = FontWeight.Bold, fontSize = 17.sp)
            },
            text = {
                Column(verticalArrangement = Arrangement.spacedBy(6.dp)) {
                    foldersList.forEach { (key, label, icon) ->
                        Row(
                            modifier = Modifier
                                .fillMaxWidth()
                                .clip(RoundedCornerShape(8.dp))
                                .background(AppTheme.colors.glassSurface)
                                .border(1.dp, AppTheme.colors.glassBorder, RoundedCornerShape(8.dp))
                                .clickable {
                                    viewModel.moveMessage(targetMsg, key)
                                    onSetMoveDialogMessage(null)
                                }
                                .padding(horizontal = 12.dp, vertical = 10.dp),
                            verticalAlignment = Alignment.CenterVertically,
                            horizontalArrangement = Arrangement.spacedBy(10.dp)
                        ) {
                            Icon(icon, contentDescription = null, tint = Gold, modifier = Modifier.size(18.dp))
                            Text(label, color = AppTheme.colors.textPrimary, fontSize = 14.sp, fontWeight = FontWeight.SemiBold)
                        }
                    }
                }
            },
            confirmButton = {
                TextButton(onClick = { onSetMoveDialogMessage(null) }) {
                    Text("Abbrechen", color = AppTheme.colors.textSecondary)
                }
            }
        )
    }

    messageForRuleDialog?.let { targetMsg ->
        AlertDialog(
            onDismissRequest = { onSetRuleDialogMessage(null) },
            containerColor = AppTheme.colors.cardBackground,
            shape = RoundedCornerShape(16.dp),
            title = {
                Text("Absender-Regel erstellen", color = Gold, fontWeight = FontWeight.Bold, fontSize = 17.sp)
            },
            text = {
                Column(verticalArrangement = Arrangement.spacedBy(6.dp)) {
                    Text(
                        text = "Künftige & bestehende Mails von '${targetMsg.fromEmail ?: targetMsg.from}' automatisch verschieben nach:",
                        color = AppTheme.colors.textSecondary,
                        fontSize = 12.sp
                    )
                    Spacer(modifier = Modifier.height(4.dp))
                    foldersList.forEach { (key, label, icon) ->
                        Row(
                            modifier = Modifier
                                .fillMaxWidth()
                                .clip(RoundedCornerShape(8.dp))
                                .background(AppTheme.colors.glassSurface)
                                .border(1.dp, AppTheme.colors.glassBorder, RoundedCornerShape(8.dp))
                                .clickable {
                                    viewModel.createSenderRule(targetMsg, key, label)
                                    onSetRuleDialogMessage(null)
                                }
                                .padding(horizontal = 12.dp, vertical = 10.dp),
                            verticalAlignment = Alignment.CenterVertically,
                            horizontalArrangement = Arrangement.spacedBy(10.dp)
                        ) {
                            Icon(icon, contentDescription = null, tint = Gold, modifier = Modifier.size(18.dp))
                            Text(label, color = AppTheme.colors.textPrimary, fontSize = 14.sp, fontWeight = FontWeight.SemiBold)
                        }
                    }
                }
            },
            confirmButton = {
                TextButton(onClick = { onSetRuleDialogMessage(null) }) {
                    Text("Abbrechen", color = AppTheme.colors.textSecondary)
                }
            }
        )
    }

    if (showCreateFolderDialog) {
        EmailCreateFolderDialog(
            viewModel = viewModel,
            onDismiss = onDismissCreateFolder
        )
    }

    if (showDeleteFolderDialog) {
        folderToDelete?.let { (folderKey, label) ->
            EmailDeleteFolderDialog(
                viewModel = viewModel,
                folderKey = folderKey,
                label = label,
                onDismiss = onDismissDeleteFolder
            )
        }
    }
}

@Composable
private fun EmailCreateFolderDialog(
    viewModel: EmailViewModel,
    onDismiss: () -> Unit
) {
    val context = LocalContext.current
    var newFolderName by remember { mutableStateOf("") }
    var isCreatingFolder by remember { mutableStateOf(false) }

    AlertDialog(
        onDismissRequest = onDismiss,
        containerColor = AppTheme.colors.cardBackground,
        shape = RoundedCornerShape(16.dp),
        title = {
            Text("Neuen Ordner erstellen", color = Gold, fontWeight = FontWeight.Bold, fontSize = 18.sp)
        },
        text = {
            OutlinedTextField(
                value = newFolderName,
                onValueChange = { newFolderName = it },
                label = { Text("Ordnername", color = AppTheme.colors.textMuted) },
                colors = OutlinedTextFieldDefaults.colors(
                    focusedBorderColor = Gold,
                    unfocusedBorderColor = AppTheme.colors.inputBorder,
                    focusedTextColor = AppTheme.colors.textPrimary,
                    unfocusedTextColor = AppTheme.colors.textPrimary,
                    focusedContainerColor = AppTheme.colors.inputBackground,
                    unfocusedContainerColor = AppTheme.colors.inputBackground
                ),
                modifier = Modifier.fillMaxWidth()
            )
        },
        confirmButton = {
            Button(
                onClick = {
                    if (newFolderName.isNotBlank()) {
                        isCreatingFolder = true
                        viewModel.createFolder(newFolderName) { success ->
                            isCreatingFolder = false
                            onDismiss()
                            if (success) {
                                Toast.makeText(context, "Ordner '$newFolderName' erstellt.", Toast.LENGTH_SHORT).show()
                            } else {
                                Toast.makeText(context, "Fehler beim Erstellen des Ordners.", Toast.LENGTH_SHORT).show()
                            }
                        }
                    }
                },
                enabled = !isCreatingFolder && newFolderName.isNotBlank(),
                colors = ButtonDefaults.buttonColors(containerColor = Gold, contentColor = SpaceBlack)
            ) {
                if (isCreatingFolder) {
                    CircularProgressIndicator(color = SpaceBlack, modifier = Modifier.size(18.dp))
                } else {
                    Text("Erstellen")
                }
            }
        },
        dismissButton = {
            TextButton(onClick = onDismiss) {
                Text("Abbrechen", color = AppTheme.colors.textSecondary)
            }
        }
    )
}

@Composable
private fun EmailDeleteFolderDialog(
    viewModel: EmailViewModel,
    folderKey: String,
    label: String,
    onDismiss: () -> Unit
) {
    val context = LocalContext.current
    var isDeletingFolder by remember { mutableStateOf(false) }

    AlertDialog(
        onDismissRequest = onDismiss,
        containerColor = AppTheme.colors.cardBackground,
        shape = RoundedCornerShape(16.dp),
        title = {
            Text("Ordner löschen", color = Gold, fontWeight = FontWeight.Bold, fontSize = 18.sp)
        },
        text = {
            Text(
                "Möchtest du den Ordner '$label' wirklich löschen? Alle E-Mails darin werden in den Posteingang (INBOX) verschoben.",
                color = AppTheme.colors.textSecondary,
                fontSize = 14.sp
            )
        },
        confirmButton = {
            Button(
                onClick = {
                    isDeletingFolder = true
                    viewModel.deleteFolder(folderKey) { success ->
                        isDeletingFolder = false
                        onDismiss()
                        if (success) {
                            Toast.makeText(context, "Ordner '$label' gelöscht.", Toast.LENGTH_SHORT).show()
                        } else {
                            Toast.makeText(context, "Fehler beim Löschen des Ordners.", Toast.LENGTH_SHORT).show()
                        }
                    }
                },
                enabled = !isDeletingFolder,
                colors = ButtonDefaults.buttonColors(containerColor = ImportantRed, contentColor = Slate50)
            ) {
                if (isDeletingFolder) {
                    CircularProgressIndicator(color = Slate50, modifier = Modifier.size(18.dp))
                } else {
                    Text("Löschen")
                }
            }
        },
        dismissButton = {
            TextButton(onClick = onDismiss) {
                Text("Abbrechen", color = AppTheme.colors.textSecondary)
            }
        }
    )
}

@Composable
private fun FunnelFilterChip(
    label: String,
    isSelected: Boolean,
    accentColor: Color,
    onClick: () -> Unit
) {
    Box(
        modifier = Modifier
            .clip(RoundedCornerShape(14.dp))
            .background(if (isSelected) accentColor.copy(alpha = 0.22f) else AppTheme.colors.glassSurface)
            .border(
                1.dp,
                if (isSelected) accentColor else AppTheme.colors.glassBorder,
                RoundedCornerShape(14.dp)
            )
            .clickable(onClick = onClick)
            .padding(horizontal = 10.dp, vertical = 5.dp),
        contentAlignment = Alignment.Center
    ) {
        Text(
            text = label,
            color = if (isSelected) accentColor else AppTheme.colors.textSecondary,
            fontSize = 11.sp,
            fontWeight = if (isSelected) FontWeight.Bold else FontWeight.Medium
        )
    }
}

@Composable
fun AccountChip(
    account: EmailAccount,
    isSelected: Boolean,
    onClick: () -> Unit
) {
    Box(
        modifier = Modifier
            .clip(RoundedCornerShape(18.dp))
            .background(if (isSelected) Gold else AppTheme.colors.cardBackground)
            .border(1.dp, if (isSelected) Gold else AppTheme.colors.cardBorder, RoundedCornerShape(18.dp))
            .clickable(onClick = onClick)
            .padding(horizontal = 12.dp, vertical = 6.dp),
        contentAlignment = Alignment.Center
    ) {
        Row(
            verticalAlignment = Alignment.CenterVertically,
            horizontalArrangement = Arrangement.spacedBy(5.dp)
        ) {
            Text(
                text = account.email,
                color = if (isSelected) SpaceBlack else AppTheme.colors.textPrimary,
                fontSize = 12.sp,
                fontWeight = FontWeight.SemiBold
            )
            if (account.unreadCount > 0) {
                Box(
                    modifier = Modifier
                        .clip(CircleShape)
                        .background(if (isSelected) SpaceBlack else Gold)
                        .padding(horizontal = 6.dp, vertical = 1.dp)
                ) {
                    Text(
                        text = account.unreadCount.toString(),
                        color = if (isSelected) Gold else SpaceBlack,
                        fontSize = 10.sp,
                        fontWeight = FontWeight.Bold
                    )
                }
            }
        }
    }
}

@Composable
fun EmailMessageRow(
    message: EmailMessage,
    showAccountBadge: Boolean = false,
    onClick: () -> Unit,
    onResolveImportant: (Boolean) -> Unit,
    onToggleRead: () -> Unit,
    onReply: () -> Unit,
    onForward: () -> Unit,
    onCreateTask: () -> Unit,
    onSaveContact: () -> Unit,
    onArchive: () -> Unit,
    onMoveRequest: () -> Unit,
    onRuleRequest: () -> Unit,
    onMarkSpam: () -> Unit,
    onDelete: () -> Unit
) {
    var showMenu by remember { mutableStateOf(false) }

    val isUnread = !message.isRead
    val isImportant = message.isImportant
    val isDraft = message.folder == "Drafts"
    val cleanSubject = remember(message.subject) { EmailTextSanitizer.repairSubject(message.subject) }
    val cleanSender = remember(message.fromName, message.fromEmail, message.toEmail, isDraft) {
        if (isDraft) {
            "An: ${(message.toEmail ?: "").ifBlank { "(Kein Empfänger)" }}"
        } else {
            EmailTextSanitizer.cleanSenderName(message.fromName, message.fromEmail)
        }
    }
    val snippet = remember(message.body, message.htmlBody, message.subject) {
        EmailTextSanitizer.getPlainSnippet(message)
    }
    val shortDate = remember(message.receivedAt) {
        EmailTextSanitizer.formatShortDate(message.receivedAt)
    }

    val borderColor = when {
        isImportant -> ImportantRed.copy(alpha = 0.7f)
        isUnread -> Gold.copy(alpha = 0.65f)
        else -> AppTheme.colors.cardBorder
    }

    Card(
        modifier = Modifier
            .fillMaxWidth()
            .clickable(onClick = onClick),
        colors = CardDefaults.cardColors(containerColor = AppTheme.colors.cardBackground),
        border = BorderStroke(1.dp, borderColor),
        shape = RoundedCornerShape(12.dp)
    ) {
        Column(
            modifier = Modifier.padding(horizontal = 12.dp, vertical = 10.dp),
            verticalArrangement = Arrangement.spacedBy(4.dp)
        ) {
            // Row 1: Unread Dot + Sender/Draft + AI Sparkle + Date + 3-Dot Context Menu
            Row(
                modifier = Modifier.fillMaxWidth(),
                horizontalArrangement = Arrangement.SpaceBetween,
                verticalAlignment = Alignment.CenterVertically
            ) {
                Row(
                    verticalAlignment = Alignment.CenterVertically,
                    horizontalArrangement = Arrangement.spacedBy(6.dp),
                    modifier = Modifier.weight(1f)
                ) {
                    if (isUnread) {
                        Box(
                            modifier = Modifier
                                .size(8.dp)
                                .background(if (isImportant) ImportantRed else Gold, shape = CircleShape)
                        )
                    }
                    if (isDraft) {
                        Box(
                            modifier = Modifier
                                .clip(RoundedCornerShape(4.dp))
                                .background(Gold.copy(alpha = 0.2f))
                                .padding(horizontal = 5.dp, vertical = 1.dp)
                        ) {
                            Text("ENTWURF", color = Gold, fontSize = 9.sp, fontWeight = FontWeight.Bold)
                        }
                    }
                    Text(
                        text = cleanSender,
                        color = if (isUnread) AppTheme.colors.textPrimary else AppTheme.colors.textSecondary,
                        fontWeight = if (isUnread) FontWeight.Bold else FontWeight.SemiBold,
                        fontSize = 13.sp,
                        maxLines = 1,
                        overflow = TextOverflow.Ellipsis
                    )
                }

                Row(
                    verticalAlignment = Alignment.CenterVertically,
                    horizontalArrangement = Arrangement.spacedBy(4.dp)
                ) {
                    if (message.aiStatus == "analyzed") {
                        Text(text = "✨", fontSize = 10.sp)
                    }
                    if (!message.attachments.isNullOrEmpty()) {
                        Icon(
                            imageVector = Icons.Default.AttachFile,
                            contentDescription = "Anhang",
                            tint = Gold,
                            modifier = Modifier.size(13.dp)
                        )
                    }
                    Text(
                        text = shortDate,
                        color = if (isUnread) Gold else AppTheme.colors.textMuted,
                        fontSize = 11.sp,
                        fontWeight = if (isUnread) FontWeight.Bold else FontWeight.Normal
                    )

                    Box {
                        IconButton(
                            onClick = { showMenu = true },
                            modifier = Modifier.size(24.dp)
                        ) {
                            Icon(
                                imageVector = Icons.Default.MoreVert,
                                contentDescription = "Aktionen",
                                tint = AppTheme.colors.textMuted,
                                modifier = Modifier.size(16.dp)
                            )
                        }

                        DropdownMenu(
                            expanded = showMenu,
                            onDismissRequest = { showMenu = false },
                            modifier = Modifier
                                .background(AppTheme.colors.cardBackground)
                                .border(1.dp, Gold.copy(alpha = 0.5f), RoundedCornerShape(10.dp))
                        ) {
                            DropdownMenuItem(
                                text = {
                                    Text(
                                        if (isDraft) "📝 Entwurf weiterbearbeiten" else "📖 E-Mail öffnen",
                                        color = AppTheme.colors.textPrimary,
                                        fontSize = 13.sp,
                                        fontWeight = FontWeight.SemiBold
                                    )
                                },
                                onClick = {
                                    showMenu = false
                                    onClick()
                                }
                            )
                            if (isImportant) {
                                DropdownMenuItem(
                                    text = {
                                        Text(
                                            "✓ Aufgabe erledigt (Archivieren)",
                                            color = ResolvedGreen,
                                            fontSize = 13.sp,
                                            fontWeight = FontWeight.Bold
                                        )
                                    },
                                    onClick = {
                                        showMenu = false
                                        onResolveImportant(false)
                                    }
                                )
                            } else {
                                DropdownMenuItem(
                                    text = {
                                        Text(
                                            "🔴 Als Wichtig markieren (Trichter)",
                                            color = ImportantRed,
                                            fontSize = 13.sp,
                                            fontWeight = FontWeight.Bold
                                        )
                                    },
                                    onClick = {
                                        showMenu = false
                                        onResolveImportant(true)
                                    }
                                )
                            }
                            DropdownMenuItem(
                                text = {
                                    Text(
                                        if (message.isRead) "✉ Als ungelesen markieren" else "✉ Als gelesen markieren",
                                        color = AppTheme.colors.textPrimary,
                                        fontSize = 13.sp
                                    )
                                },
                                onClick = {
                                    showMenu = false
                                    onToggleRead()
                                }
                            )
                            DropdownMenuItem(
                                text = { Text("↩ Antworten", color = AppTheme.colors.textPrimary, fontSize = 13.sp) },
                                onClick = {
                                    showMenu = false
                                    onReply()
                                }
                            )
                            DropdownMenuItem(
                                text = { Text("↪ Weiterleiten", color = AppTheme.colors.textPrimary, fontSize = 13.sp) },
                                onClick = {
                                    showMenu = false
                                    onForward()
                                }
                            )
                            DropdownMenuItem(
                                text = { Text("📋 Als Aufgabe (To-Do) anlegen", color = Gold, fontSize = 13.sp) },
                                onClick = {
                                    showMenu = false
                                    onCreateTask()
                                }
                            )
                            DropdownMenuItem(
                                text = { Text("👤 Absender als Kontakt speichern", color = AppTheme.colors.textPrimary, fontSize = 13.sp) },
                                onClick = {
                                    showMenu = false
                                    onSaveContact()
                                }
                            )
                            DropdownMenuItem(
                                text = { Text("📦 Ins Archiv verschieben", color = AppTheme.colors.textPrimary, fontSize = 13.sp) },
                                onClick = {
                                    showMenu = false
                                    onArchive()
                                }
                            )
                            DropdownMenuItem(
                                text = { Text("📁 Verschieben nach...", color = AppTheme.colors.textPrimary, fontSize = 13.sp) },
                                onClick = {
                                    showMenu = false
                                    onMoveRequest()
                                }
                            )
                            DropdownMenuItem(
                                text = { Text("⚡ Absender-Regel (Auto-Ordner)...", color = AppTheme.colors.textPrimary, fontSize = 13.sp) },
                                onClick = {
                                    showMenu = false
                                    onRuleRequest()
                                }
                            )
                            DropdownMenuItem(
                                text = { Text("🛡 Als Spam markieren (Junk)", color = ImportantRed, fontSize = 13.sp) },
                                onClick = {
                                    showMenu = false
                                    onMarkSpam()
                                }
                            )
                            DropdownMenuItem(
                                text = { Text("🗑 Löschen", color = ImportantRed, fontSize = 13.sp, fontWeight = FontWeight.Bold) },
                                onClick = {
                                    showMenu = false
                                    onDelete()
                                }
                            )
                        }
                    }
                }
            }

            // Row 2: Repaired Subject
            Text(
                text = cleanSubject,
                color = if (isUnread) AppTheme.colors.textPrimary else AppTheme.colors.textSecondary,
                fontWeight = if (isUnread) FontWeight.Bold else FontWeight.Medium,
                fontSize = 13.sp,
                maxLines = 2,
                overflow = TextOverflow.Ellipsis
            )

            // Row 3: Badges (WICHTIG, Category, Account)
            val hasBadges = isImportant || !message.category.isNullOrBlank() || (showAccountBadge && !message.accountEmail.isNullOrBlank())
            if (hasBadges) {
                Row(
                    horizontalArrangement = Arrangement.spacedBy(6.dp),
                    verticalAlignment = Alignment.CenterVertically,
                    modifier = Modifier.padding(top = 2.dp)
                ) {
                    if (isImportant) {
                        Box(
                            modifier = Modifier
                                .clip(RoundedCornerShape(5.dp))
                                .background(ImportantRed.copy(alpha = 0.16f))
                                .border(1.dp, ImportantRed.copy(alpha = 0.5f), RoundedCornerShape(5.dp))
                                .padding(horizontal = 6.dp, vertical = 2.dp)
                        ) {
                            Text("🔴 WICHTIG", color = ImportantRed, fontSize = 9.sp, fontWeight = FontWeight.Bold)
                        }
                    }
                    if (!message.category.isNullOrBlank()) {
                        Box(
                            modifier = Modifier
                                .clip(RoundedCornerShape(5.dp))
                                .background(Gold.copy(alpha = 0.14f))
                                .border(1.dp, Gold.copy(alpha = 0.4f), RoundedCornerShape(5.dp))
                                .padding(horizontal = 6.dp, vertical = 2.dp)
                        ) {
                            Text("🏷 ${message.category}", color = Gold, fontSize = 9.sp, fontWeight = FontWeight.SemiBold)
                        }
                    }
                    if (showAccountBadge && !message.accountEmail.isNullOrBlank()) {
                        Text(
                            text = message.accountEmail,
                            color = AppTheme.colors.textMuted,
                            fontSize = 9.sp,
                            maxLines = 1,
                            overflow = TextOverflow.Ellipsis
                        )
                    }
                }
            }

            // Row 4: Inline ActionHint + "✓ Erledigt" Button for Important Mails
            if (isImportant || !message.actionHint.isNullOrBlank()) {
                Row(
                    modifier = Modifier
                        .fillMaxWidth()
                        .padding(top = 2.dp)
                        .clip(RoundedCornerShape(8.dp))
                        .background(ImportantRed.copy(alpha = 0.10f))
                        .border(1.dp, ImportantRed.copy(alpha = 0.35f), RoundedCornerShape(8.dp))
                        .padding(horizontal = 8.dp, vertical = 5.dp),
                    horizontalArrangement = Arrangement.SpaceBetween,
                    verticalAlignment = Alignment.CenterVertically
                ) {
                    Text(
                        text = "👉 ${message.actionHint?.takeIf { it.isNotBlank() } ?: "Wichtige E-Mail"}",
                        color = AppTheme.colors.textPrimary,
                        fontSize = 11.sp,
                        fontWeight = FontWeight.SemiBold,
                        maxLines = 2,
                        overflow = TextOverflow.Ellipsis,
                        modifier = Modifier.weight(1f)
                    )
                    if (isImportant) {
                        Spacer(modifier = Modifier.width(6.dp))
                        Box(
                            modifier = Modifier
                                .clip(RoundedCornerShape(6.dp))
                                .background(ResolvedGreen.copy(alpha = 0.2f))
                                .border(1.dp, ResolvedGreen, RoundedCornerShape(6.dp))
                                .clickable { onResolveImportant(false) }
                                .padding(horizontal = 8.dp, vertical = 3.dp)
                        ) {
                            Text(
                                text = "✓ Erledigt",
                                color = ResolvedGreen,
                                fontSize = 10.sp,
                                fontWeight = FontWeight.Bold
                            )
                        }
                    }
                }
            }

            // Row 5: Clean Plain-Text Snippet
            if (snippet.isNotBlank()) {
                Text(
                    text = snippet,
                    color = AppTheme.colors.textMuted,
                    fontSize = 11.sp,
                    maxLines = 2,
                    overflow = TextOverflow.Ellipsis
                )
            }
        }
    }
}

@Composable
fun EmailDetailDialog(
    message: EmailMessage,
    currentFolder: String,
    onDismiss: () -> Unit,
    onDelete: () -> Unit,
    onToggleUnread: () -> Unit,
    onReply: () -> Unit,
    onForward: () -> Unit,
    onEditDraft: () -> Unit,
    onArchive: () -> Unit,
    onResolveImportant: (Boolean) -> Unit,
    onCreateTask: () -> Unit,
    onSaveContact: () -> Unit,
    onMoveRequest: () -> Unit,
    onRuleRequest: () -> Unit,
    onMarkSpam: () -> Unit,
    onDownloadAttachment: (EmailMessageAttachment) -> Unit
) {
    var showHtmlView by remember(message.id) { mutableStateOf(false) }
    var allowExternalImages by remember(message.id) { mutableStateOf(false) }
    val currentAllowExternalImages by rememberUpdatedState(allowExternalImages)
    var showMoreMenu by remember { mutableStateOf(false) }

    val cleanSubject = remember(message.subject) { EmailTextSanitizer.repairSubject(message.subject) }
    val cleanBody = remember(message.body, message.htmlBody) {
        EmailTextSanitizer.formatCleanEmailBody(message.body, message.htmlBody)
    }
    val hasHtml = !message.htmlBody.isNullOrBlank()
    val attachments = message.attachments ?: emptyList()

    Dialog(
        onDismissRequest = onDismiss,
        properties = DialogProperties(usePlatformDefaultWidth = false)
    ) {
        Column(
            modifier = Modifier
                .fillMaxSize()
                .padding(horizontal = 10.dp, vertical = 16.dp)
                .clip(RoundedCornerShape(16.dp))
                .background(AppTheme.colors.cardBackground)
                .border(1.dp, AppTheme.colors.cardBorder, RoundedCornerShape(16.dp))
        ) {
            // Top Action Bar
            Row(
                modifier = Modifier
                    .fillMaxWidth()
                    .background(AppTheme.colors.glassSurface)
                    .padding(horizontal = 8.dp, vertical = 6.dp),
                horizontalArrangement = Arrangement.SpaceBetween,
                verticalAlignment = Alignment.CenterVertically
            ) {
                IconButton(onClick = onDismiss) {
                    Icon(Icons.Default.ArrowBack, contentDescription = "Zurück", tint = AppTheme.colors.textPrimary)
                }

                Row(
                    horizontalArrangement = Arrangement.spacedBy(2.dp),
                    verticalAlignment = Alignment.CenterVertically
                ) {
                    if (message.folder == "Drafts") {
                        IconButton(onClick = onEditDraft) {
                            Icon(Icons.Default.Edit, contentDescription = "Entwurf bearbeiten", tint = Gold)
                        }
                    } else {
                        IconButton(onClick = onReply) {
                            Icon(Icons.Default.Reply, contentDescription = "Antworten", tint = Gold)
                        }
                        IconButton(onClick = onForward) {
                            Icon(Icons.Default.Forward, contentDescription = "Weiterleiten", tint = AppTheme.colors.textPrimary)
                        }
                    }

                    IconButton(onClick = onToggleUnread) {
                        Icon(
                            imageVector = if (message.isRead) Icons.Default.MarkEmailUnread else Icons.Default.MarkEmailRead,
                            contentDescription = "Gelesen/Ungelesen",
                            tint = AppTheme.colors.textPrimary
                        )
                    }

                    if (currentFolder != "Archive" && currentFolder != "GlobalArchive") {
                        IconButton(onClick = onArchive) {
                            Icon(Icons.Default.Archive, contentDescription = "Archivieren", tint = AppTheme.colors.textPrimary)
                        }
                    }

                    IconButton(onClick = onDelete) {
                        Icon(Icons.Default.Delete, contentDescription = "Löschen", tint = ImportantRed)
                    }

                    Box {
                        IconButton(onClick = { showMoreMenu = true }) {
                            Icon(Icons.Default.MoreVert, contentDescription = "Mehr", tint = AppTheme.colors.textPrimary)
                        }
                        DropdownMenu(
                            expanded = showMoreMenu,
                            onDismissRequest = { showMoreMenu = false },
                            modifier = Modifier
                                .background(AppTheme.colors.cardBackground)
                                .border(1.dp, Gold.copy(alpha = 0.5f), RoundedCornerShape(10.dp))
                        ) {
                            DropdownMenuItem(
                                text = { Text("📋 Als Aufgabe (To-Do) anlegen", color = Gold, fontSize = 13.sp, fontWeight = FontWeight.Bold) },
                                onClick = {
                                    showMoreMenu = false
                                    onCreateTask()
                                }
                            )
                            DropdownMenuItem(
                                text = { Text("👤 Absender als Kontakt speichern", color = AppTheme.colors.textPrimary, fontSize = 13.sp) },
                                onClick = {
                                    showMoreMenu = false
                                    onSaveContact()
                                }
                            )
                            DropdownMenuItem(
                                text = { Text("📁 Verschieben nach...", color = AppTheme.colors.textPrimary, fontSize = 13.sp) },
                                onClick = {
                                    showMoreMenu = false
                                    onMoveRequest()
                                }
                            )
                            DropdownMenuItem(
                                text = { Text("⚡ Absender-Regel (Auto-Ordner)...", color = AppTheme.colors.textPrimary, fontSize = 13.sp) },
                                onClick = {
                                    showMoreMenu = false
                                    onRuleRequest()
                                }
                            )
                            DropdownMenuItem(
                                text = { Text("🛡 Als Spam markieren (Junk)", color = ImportantRed, fontSize = 13.sp) },
                                onClick = {
                                    showMoreMenu = false
                                    onMarkSpam()
                                }
                            )
                        }
                    }
                }
            }

            // Scrollable Detail Content
            Column(
                modifier = Modifier
                    .weight(1f)
                    .fillMaxWidth()
                    .verticalScroll(rememberScrollState())
                    .padding(14.dp),
                verticalArrangement = Arrangement.spacedBy(10.dp)
            ) {
                // Subject
                Text(
                    text = cleanSubject,
                    color = Gold,
                    fontWeight = FontWeight.Bold,
                    fontSize = 17.sp
                )

                // Badges + Importance Toggle Row
                Row(
                    modifier = Modifier.fillMaxWidth(),
                    horizontalArrangement = Arrangement.SpaceBetween,
                    verticalAlignment = Alignment.CenterVertically
                ) {
                    Row(
                        horizontalArrangement = Arrangement.spacedBy(6.dp),
                        verticalAlignment = Alignment.CenterVertically
                    ) {
                        if (message.isImportant) {
                            Box(
                                modifier = Modifier
                                    .clip(RoundedCornerShape(6.dp))
                                    .background(ImportantRed.copy(alpha = 0.16f))
                                    .border(1.dp, ImportantRed, RoundedCornerShape(6.dp))
                                    .padding(horizontal = 7.dp, vertical = 2.dp)
                            ) {
                                Text("🔴 WICHTIG", color = ImportantRed, fontSize = 10.sp, fontWeight = FontWeight.Bold)
                            }
                        }
                        if (!message.category.isNullOrBlank()) {
                            Box(
                                modifier = Modifier
                                    .clip(RoundedCornerShape(6.dp))
                                    .background(Gold.copy(alpha = 0.15f))
                                    .border(1.dp, Gold.copy(alpha = 0.5f), RoundedCornerShape(6.dp))
                                    .padding(horizontal = 7.dp, vertical = 2.dp)
                            ) {
                                Text("🏷 ${message.category}", color = Gold, fontSize = 10.sp, fontWeight = FontWeight.SemiBold)
                            }
                        }
                    }

                    if (message.isImportant) {
                        Box(
                            modifier = Modifier
                                .clip(RoundedCornerShape(6.dp))
                                .background(ResolvedGreen.copy(alpha = 0.18f))
                                .border(1.dp, ResolvedGreen, RoundedCornerShape(6.dp))
                                .clickable { onResolveImportant(false) }
                                .padding(horizontal = 8.dp, vertical = 4.dp)
                        ) {
                            Text("✓ Aufgabe erledigt", color = ResolvedGreen, fontSize = 11.sp, fontWeight = FontWeight.Bold)
                        }
                    } else {
                        Box(
                            modifier = Modifier
                                .clip(RoundedCornerShape(6.dp))
                                .background(AppTheme.colors.glassSurface)
                                .border(1.dp, AppTheme.colors.glassBorder, RoundedCornerShape(6.dp))
                                .clickable { onResolveImportant(true) }
                                .padding(horizontal = 8.dp, vertical = 4.dp)
                        ) {
                            Text("🔴 Als Wichtig markieren", color = AppTheme.colors.textSecondary, fontSize = 10.sp, fontWeight = FontWeight.SemiBold)
                        }
                    }
                }

                // KI Action-Hint Box
                if (!message.actionHint.isNullOrBlank()) {
                    Row(
                        modifier = Modifier
                            .fillMaxWidth()
                            .clip(RoundedCornerShape(8.dp))
                            .background(ImportantRed.copy(alpha = 0.10f))
                            .border(1.dp, ImportantRed.copy(alpha = 0.4f), RoundedCornerShape(8.dp))
                            .padding(10.dp),
                        verticalAlignment = Alignment.CenterVertically
                    ) {
                        Text(
                            text = "👉 ${message.actionHint}",
                            color = AppTheme.colors.textPrimary,
                            fontSize = 12.sp,
                            fontWeight = FontWeight.SemiBold
                        )
                    }
                }

                // Sender / Recipient / Date Metadata Box
                Column(
                    modifier = Modifier
                        .fillMaxWidth()
                        .clip(RoundedCornerShape(10.dp))
                        .background(AppTheme.colors.glassSurface)
                        .border(1.dp, AppTheme.colors.glassBorder, RoundedCornerShape(10.dp))
                        .padding(10.dp),
                    verticalArrangement = Arrangement.spacedBy(3.dp)
                ) {
                    Text(
                        text = "Von: ${message.from}",
                        color = AppTheme.colors.textPrimary,
                        fontSize = 12.sp,
                        fontWeight = FontWeight.SemiBold
                    )
                    Text(
                        text = "An: ${message.to}",
                        color = AppTheme.colors.textSecondary,
                        fontSize = 11.sp
                    )
                    if (!message.ccEmail.isNullOrBlank()) {
                        Text(
                            text = "CC: ${message.ccEmail}",
                            color = AppTheme.colors.textSecondary,
                            fontSize = 11.sp
                        )
                    }
                    Text(
                        text = "Datum: ${EmailTextSanitizer.formatFullDate(message.receivedAt)}",
                        color = AppTheme.colors.textMuted,
                        fontSize = 11.sp
                    )
                }

                // Attachments Section
                if (attachments.isNotEmpty()) {
                    Column(
                        modifier = Modifier
                            .fillMaxWidth()
                            .clip(RoundedCornerShape(10.dp))
                            .background(AppTheme.colors.glassSurface)
                            .border(1.dp, Gold.copy(alpha = 0.4f), RoundedCornerShape(10.dp))
                            .padding(10.dp),
                        verticalArrangement = Arrangement.spacedBy(6.dp)
                    ) {
                        Text(
                            text = "📎 Anhänge (${attachments.size})",
                            color = Gold,
                            fontSize = 12.sp,
                            fontWeight = FontWeight.Bold
                        )
                        attachments.forEach { att ->
                            Row(
                                modifier = Modifier
                                    .fillMaxWidth()
                                    .clip(RoundedCornerShape(8.dp))
                                    .background(AppTheme.colors.cardBackground)
                                    .border(1.dp, AppTheme.colors.cardBorder, RoundedCornerShape(8.dp))
                                    .clickable { onDownloadAttachment(att) }
                                    .padding(horizontal = 10.dp, vertical = 8.dp),
                                horizontalArrangement = Arrangement.SpaceBetween,
                                verticalAlignment = Alignment.CenterVertically
                            ) {
                                Row(
                                    verticalAlignment = Alignment.CenterVertically,
                                    horizontalArrangement = Arrangement.spacedBy(8.dp),
                                    modifier = Modifier.weight(1f)
                                ) {
                                    Icon(
                                        imageVector = Icons.Default.InsertDriveFile,
                                        contentDescription = null,
                                        tint = Gold,
                                        modifier = Modifier.size(18.dp)
                                    )
                                    Column {
                                        Text(
                                            text = att.filename,
                                            color = AppTheme.colors.textPrimary,
                                            fontSize = 12.sp,
                                            fontWeight = FontWeight.SemiBold,
                                            maxLines = 1,
                                            overflow = TextOverflow.Ellipsis
                                        )
                                        Text(
                                            text = EmailTextSanitizer.formatAttachmentSize(att.size),
                                            color = AppTheme.colors.textMuted,
                                            fontSize = 10.sp
                                        )
                                    }
                                }
                                Icon(
                                    imageVector = Icons.Default.OpenInNew,
                                    contentDescription = "Öffnen",
                                    tint = Gold,
                                    modifier = Modifier.size(16.dp)
                                )
                            }
                        }
                    }
                }

                // Toggle between Clean Structured Text and Original HTML WebView + External Image Tracker Guard
                if (hasHtml) {
                    Row(
                        modifier = Modifier.fillMaxWidth(),
                        horizontalArrangement = Arrangement.spacedBy(8.dp, Alignment.End),
                        verticalAlignment = Alignment.CenterVertically
                    ) {
                        if (showHtmlView) {
                            Box(
                                modifier = Modifier
                                    .clip(RoundedCornerShape(8.dp))
                                    .background(if (allowExternalImages) Gold else AppTheme.colors.glassSurface)
                                    .border(
                                        1.dp,
                                        if (allowExternalImages) Gold else AppTheme.colors.glassBorder,
                                        RoundedCornerShape(8.dp)
                                    )
                                    .clickable { allowExternalImages = !allowExternalImages }
                                    .padding(horizontal = 10.dp, vertical = 5.dp)
                            ) {
                                Text(
                                    text = if (allowExternalImages) "Externe Bilder aktiv" else "Externe Bilder laden",
                                    color = if (allowExternalImages) SpaceBlack else AppTheme.colors.textSecondary,
                                    fontSize = 11.sp,
                                    fontWeight = FontWeight.Bold
                                )
                            }
                        }

                        Box(
                            modifier = Modifier
                                .clip(RoundedCornerShape(8.dp))
                                .background(if (showHtmlView) Gold else AppTheme.colors.glassSurface)
                                .border(1.dp, if (showHtmlView) Gold else AppTheme.colors.glassBorder, RoundedCornerShape(8.dp))
                                .clickable { showHtmlView = !showHtmlView }
                                .padding(horizontal = 10.dp, vertical = 5.dp)
                        ) {
                            Text(
                                text = if (showHtmlView) "📄 Bereinigter Text" else "🌐 Original HTML-Ansicht",
                                color = if (showHtmlView) SpaceBlack else AppTheme.colors.textSecondary,
                                fontSize = 11.sp,
                                fontWeight = FontWeight.Bold
                            )
                        }
                    }
                }

                // Email Body Content
                if (showHtmlView && hasHtml) {
                    val rawHtml = message.htmlBody ?: ""
                    Box(
                        modifier = Modifier
                            .fillMaxWidth()
                            .heightIn(min = 300.dp, max = 900.dp)
                            .clip(RoundedCornerShape(10.dp))
                            .border(1.dp, AppTheme.colors.cardBorder, RoundedCornerShape(10.dp))
                    ) {
                        AndroidView(
                            factory = { ctx ->
                                WebView(ctx).apply {
                                    android.webkit.CookieManager.getInstance().setAcceptCookie(false)
                                    android.webkit.CookieManager.getInstance().setAcceptThirdPartyCookies(this, false)
                                    settings.apply {
                                        javaScriptEnabled = false
                                        allowFileAccess = false
                                        allowContentAccess = false
                                        domStorageEnabled = false
                                        databaseEnabled = false
                                        saveFormData = false
                                        blockNetworkLoads = true
                                        mixedContentMode = android.webkit.WebSettings.MIXED_CONTENT_NEVER_ALLOW
                                        loadsImagesAutomatically = true
                                        useWideViewPort = true
                                        loadWithOverviewMode = true
                                    }
                                    webViewClient = object : WebViewClient() {
                                        override fun shouldOverrideUrlLoading(
                                            view: WebView?,
                                            request: android.webkit.WebResourceRequest?
                                        ): Boolean {
                                            val targetUrl = request?.url?.toString() ?: return true
                                            de.meinseelenfunke.app.util.SecureFileOpener.openSafeExternalUrl(ctx, targetUrl)
                                            return true
                                        }

                                        @Deprecated("Deprecated in Java")
                                        override fun shouldOverrideUrlLoading(view: WebView?, url: String?): Boolean {
                                            if (!url.isNullOrBlank()) {
                                                de.meinseelenfunke.app.util.SecureFileOpener.openSafeExternalUrl(ctx, url)
                                            }
                                            return true
                                        }

                                        override fun shouldInterceptRequest(
                                            view: WebView?,
                                            request: android.webkit.WebResourceRequest?
                                        ): android.webkit.WebResourceResponse? {
                                            val scheme = request?.url?.scheme?.lowercase()
                                            if (!de.meinseelenfunke.app.util.EmailHtmlSanitizer.isAllowedWebResourceScheme(
                                                    scheme = scheme,
                                                    allowExternalImages = currentAllowExternalImages
                                                )
                                            ) {
                                                return android.webkit.WebResourceResponse(
                                                    "text/plain",
                                                    "UTF-8",
                                                    java.io.ByteArrayInputStream(ByteArray(0))
                                                )
                                            }
                                            return super.shouldInterceptRequest(view, request)
                                        }
                                    }
                                }
                            },
                            update = { webView ->
                                webView.settings.blockNetworkLoads = !allowExternalImages
                                val wrappedHtml = de.meinseelenfunke.app.util.EmailHtmlSanitizer.wrapSafeHtmlDocument(
                                    rawHtml = rawHtml,
                                    allowExternalImages = allowExternalImages
                                )
                                webView.loadDataWithBaseURL(null, wrappedHtml, "text/html", "UTF-8", null)
                            },
                            modifier = Modifier.fillMaxWidth()
                        )
                    }
                } else {
                    SelectionContainer {
                        Text(
                            text = cleanBody,
                            color = AppTheme.colors.textPrimary,
                            fontSize = 14.sp,
                            lineHeight = 20.sp,
                            modifier = Modifier.fillMaxWidth()
                        )
                    }
                }
            }

            // Bottom Quick Action Bar
            Row(
                modifier = Modifier
                    .fillMaxWidth()
                    .background(AppTheme.colors.glassSurface)
                    .padding(horizontal = 12.dp, vertical = 8.dp),
                horizontalArrangement = Arrangement.spacedBy(8.dp),
                verticalAlignment = Alignment.CenterVertically
            ) {
                Button(
                    onClick = onReply,
                    colors = ButtonDefaults.buttonColors(containerColor = Gold, contentColor = SpaceBlack),
                    shape = RoundedCornerShape(8.dp),
                    modifier = Modifier.weight(1f),
                    contentPadding = PaddingValues(vertical = 8.dp)
                ) {
                    Icon(Icons.Default.Reply, contentDescription = null, modifier = Modifier.size(15.dp))
                    Spacer(modifier = Modifier.width(4.dp))
                    Text("Antworten", fontSize = 12.sp, fontWeight = FontWeight.Bold)
                }

                OutlinedButton(
                    onClick = onForward,
                    border = BorderStroke(1.dp, AppTheme.colors.glassBorder),
                    shape = RoundedCornerShape(8.dp),
                    modifier = Modifier.weight(1f),
                    contentPadding = PaddingValues(vertical = 8.dp)
                ) {
                    Icon(Icons.Default.Forward, contentDescription = null, tint = AppTheme.colors.textPrimary, modifier = Modifier.size(15.dp))
                    Spacer(modifier = Modifier.width(4.dp))
                    Text("Weiterleiten", color = AppTheme.colors.textPrimary, fontSize = 12.sp, fontWeight = FontWeight.SemiBold)
                }

                OutlinedButton(
                    onClick = onCreateTask,
                    border = BorderStroke(1.dp, Gold.copy(alpha = 0.6f)),
                    shape = RoundedCornerShape(8.dp),
                    contentPadding = PaddingValues(horizontal = 10.dp, vertical = 8.dp)
                ) {
                    Text("📋 To-Do", color = Gold, fontSize = 12.sp, fontWeight = FontWeight.Bold)
                }
            }
        }
    }
}

@OptIn(ExperimentalMaterial3Api::class)
@Composable
fun ComposeEmailInline(
    viewModel: EmailViewModel,
    fromAccount: EmailAccount?,
    isSending: Boolean,
    onDismiss: () -> Unit,
    onSend: (
        to: String,
        cc: String?,
        bcc: String?,
        subject: String,
        body: String,
        accountId: String?,
        draftId: String?,
        forwardedAttachments: List<EmailAttachment>
    ) -> Unit
) {
    val prefill by viewModel.composePrefill.collectAsState()
    val accounts by viewModel.accounts.collectAsState()
    val composeAttachments by viewModel.composeAttachments.collectAsState()
    val recipientSuggestions by viewModel.recipientSuggestions.collectAsState()

    var selectedAccountId by remember(prefill, fromAccount) {
        mutableStateOf(prefill.accountId ?: fromAccount?.id ?: accounts.firstOrNull()?.id)
    }
    var to by remember(prefill) { mutableStateOf(prefill.to) }
    var cc by remember(prefill) { mutableStateOf(prefill.cc) }
    var bcc by remember(prefill) { mutableStateOf(prefill.bcc) }
    var showCcBcc by remember(prefill) { mutableStateOf(prefill.cc.isNotBlank() || prefill.bcc.isNotBlank()) }
    var subject by remember(prefill) { mutableStateOf(prefill.subject) }
    var body by remember(prefill) { mutableStateOf(prefill.body) }
    var forwardedAttachments by remember(prefill) { mutableStateOf(prefill.forwardedAttachments) }
    var toFocus by remember { mutableStateOf(false) }
    var showAccountDropdown by remember { mutableStateOf(false) }

    val context = LocalContext.current
    val activeSenderAccount = accounts.find { it.id == selectedAccountId } ?: fromAccount

    val filePickerLauncher = rememberLauncherForActivityResult(
        contract = ActivityResultContracts.GetMultipleContents()
    ) { uris ->
        uris.forEach { uri ->
            viewModel.addComposeAttachment(uri)
        }
    }

    val filteredRecipients = remember(to, recipientSuggestions) {
        val lastToken = to.substringAfterLast(",").substringAfterLast(";").trim()
        if (lastToken.isEmpty()) {
            recipientSuggestions.take(8)
        } else {
            recipientSuggestions.filter {
                it.email.contains(lastToken, ignoreCase = true) ||
                    it.name.contains(lastToken, ignoreCase = true)
            }.take(8)
        }
    }

    val showDropdown = toFocus && filteredRecipients.isNotEmpty()

    val fieldColors = OutlinedTextFieldDefaults.colors(
        focusedBorderColor = Gold,
        unfocusedBorderColor = AppTheme.colors.inputBorder,
        focusedTextColor = AppTheme.colors.textPrimary,
        unfocusedTextColor = AppTheme.colors.textPrimary,
        focusedContainerColor = AppTheme.colors.inputBackground,
        unfocusedContainerColor = AppTheme.colors.inputBackground
    )

    Column(
        modifier = Modifier
            .fillMaxSize()
            .background(AppTheme.colors.cardBackground, shape = RoundedCornerShape(16.dp))
            .border(1.dp, AppTheme.colors.cardBorder, RoundedCornerShape(16.dp))
            .padding(14.dp),
        verticalArrangement = Arrangement.spacedBy(8.dp)
    ) {
        // Header
        Row(
            modifier = Modifier.fillMaxWidth(),
            horizontalArrangement = Arrangement.SpaceBetween,
            verticalAlignment = Alignment.CenterVertically
        ) {
            Text(
                text = prefill.title,
                color = Gold,
                fontWeight = FontWeight.Bold,
                fontSize = 17.sp
            )
            IconButton(onClick = onDismiss, enabled = !isSending, modifier = Modifier.size(32.dp)) {
                Icon(Icons.Default.Close, contentDescription = "Schließen", tint = AppTheme.colors.textSecondary)
            }
        }

        // Sender Selector + CC/BCC Toggle + Attach File
        Row(
            modifier = Modifier.fillMaxWidth(),
            horizontalArrangement = Arrangement.SpaceBetween,
            verticalAlignment = Alignment.CenterVertically
        ) {
            Box(modifier = Modifier.weight(1f)) {
                Row(
                    modifier = Modifier
                        .clip(RoundedCornerShape(8.dp))
                        .background(AppTheme.colors.glassSurface)
                        .border(1.dp, AppTheme.colors.glassBorder, RoundedCornerShape(8.dp))
                        .clickable { if (accounts.size > 1) showAccountDropdown = true }
                        .padding(horizontal = 10.dp, vertical = 6.dp),
                    verticalAlignment = Alignment.CenterVertically,
                    horizontalArrangement = Arrangement.spacedBy(4.dp)
                ) {
                    Text(
                        text = "Von: ${activeSenderAccount?.email ?: ""}",
                        color = AppTheme.colors.textPrimary,
                        fontSize = 12.sp,
                        fontWeight = FontWeight.SemiBold,
                        maxLines = 1,
                        overflow = TextOverflow.Ellipsis
                    )
                    if (accounts.size > 1) {
                        Icon(
                            imageVector = Icons.Default.ArrowDropDown,
                            contentDescription = null,
                            tint = Gold,
                            modifier = Modifier.size(16.dp)
                        )
                    }
                }
                DropdownMenu(
                    expanded = showAccountDropdown,
                    onDismissRequest = { showAccountDropdown = false },
                    modifier = Modifier
                        .background(AppTheme.colors.cardBackground)
                        .border(1.dp, Gold, RoundedCornerShape(8.dp))
                ) {
                    accounts.forEach { acc ->
                        DropdownMenuItem(
                            text = { Text(acc.email, color = AppTheme.colors.textPrimary, fontSize = 13.sp) },
                            onClick = {
                                selectedAccountId = acc.id
                                showAccountDropdown = false
                            }
                        )
                    }
                }
            }

            Spacer(modifier = Modifier.width(6.dp))

            // CC/BCC Button
            Box(
                modifier = Modifier
                    .clip(RoundedCornerShape(8.dp))
                    .background(if (showCcBcc) Gold.copy(alpha = 0.2f) else AppTheme.colors.glassSurface)
                    .border(1.dp, if (showCcBcc) Gold else AppTheme.colors.glassBorder, RoundedCornerShape(8.dp))
                    .clickable { showCcBcc = !showCcBcc }
                    .padding(horizontal = 8.dp, vertical = 6.dp)
            ) {
                Text("CC/BCC", color = if (showCcBcc) Gold else AppTheme.colors.textSecondary, fontSize = 11.sp, fontWeight = FontWeight.Bold)
            }

            Spacer(modifier = Modifier.width(6.dp))

            IconButton(
                onClick = { filePickerLauncher.launch("*/*") },
                modifier = Modifier
                    .background(AppTheme.colors.glassSurface, shape = RoundedCornerShape(8.dp))
                    .border(1.dp, AppTheme.colors.glassBorder, shape = RoundedCornerShape(8.dp))
                    .size(32.dp)
            ) {
                Icon(
                    imageVector = Icons.Default.AttachFile,
                    contentDescription = "Datei anhängen",
                    tint = Gold,
                    modifier = Modifier.size(17.dp)
                )
            }
        }

        // Carried-Over + Newly Picked Attachments
        if (forwardedAttachments.isNotEmpty() || composeAttachments.isNotEmpty()) {
            LazyRow(
                horizontalArrangement = Arrangement.spacedBy(6.dp),
                modifier = Modifier.fillMaxWidth()
            ) {
                items(forwardedAttachments) { att ->
                    Row(
                        verticalAlignment = Alignment.CenterVertically,
                        horizontalArrangement = Arrangement.spacedBy(4.dp),
                        modifier = Modifier
                            .clip(RoundedCornerShape(8.dp))
                            .background(AppTheme.colors.glassSurface)
                            .border(1.dp, Gold.copy(alpha = 0.5f), RoundedCornerShape(8.dp))
                            .padding(horizontal = 8.dp, vertical = 4.dp)
                    ) {
                        Icon(Icons.Default.AttachFile, contentDescription = null, tint = Gold, modifier = Modifier.size(12.dp))
                        Text(
                            text = att.filename,
                            color = AppTheme.colors.textPrimary,
                            fontSize = 10.sp,
                            maxLines = 1,
                            overflow = TextOverflow.Ellipsis,
                            modifier = Modifier.widthIn(max = 120.dp)
                        )
                        Icon(
                            imageVector = Icons.Default.Close,
                            contentDescription = "Entfernen",
                            tint = AppTheme.colors.textMuted,
                            modifier = Modifier
                                .size(13.dp)
                                .clickable {
                                    forwardedAttachments = forwardedAttachments.filterNot { it.id == att.id && it.filename == att.filename }
                                }
                        )
                    }
                }
                items(composeAttachments) { uri ->
                    val fileName = getFileName(context, uri)
                    Row(
                        verticalAlignment = Alignment.CenterVertically,
                        horizontalArrangement = Arrangement.spacedBy(4.dp),
                        modifier = Modifier
                            .clip(RoundedCornerShape(8.dp))
                            .background(AppTheme.colors.glassSurface)
                            .border(1.dp, AppTheme.colors.glassBorder, RoundedCornerShape(8.dp))
                            .padding(horizontal = 8.dp, vertical = 4.dp)
                    ) {
                        Icon(Icons.Default.AttachFile, contentDescription = null, tint = Gold, modifier = Modifier.size(12.dp))
                        Text(
                            text = fileName,
                            color = AppTheme.colors.textPrimary,
                            fontSize = 10.sp,
                            maxLines = 1,
                            overflow = TextOverflow.Ellipsis,
                            modifier = Modifier.widthIn(max = 120.dp)
                        )
                        Icon(
                            imageVector = Icons.Default.Close,
                            contentDescription = "Entfernen",
                            tint = AppTheme.colors.textMuted,
                            modifier = Modifier
                                .size(13.dp)
                                .clickable { viewModel.removeComposeAttachment(uri) }
                        )
                    }
                }
            }
        }

        // "An" Field with Autocomplete
        Box(modifier = Modifier.fillMaxWidth()) {
            OutlinedTextField(
                value = to,
                onValueChange = { to = it },
                label = { Text("An (Empfänger)", color = AppTheme.colors.textMuted, fontSize = 12.sp) },
                singleLine = true,
                colors = fieldColors,
                modifier = Modifier
                    .fillMaxWidth()
                    .onFocusChanged { focusState -> toFocus = focusState.isFocused }
            )

            DropdownMenu(
                expanded = showDropdown,
                onDismissRequest = { toFocus = false },
                properties = PopupProperties(focusable = false),
                modifier = Modifier
                    .fillMaxWidth(0.9f)
                    .background(AppTheme.colors.cardBackground)
                    .border(1.dp, Gold, RoundedCornerShape(8.dp))
            ) {
                filteredRecipients.forEach { rec ->
                    DropdownMenuItem(
                        text = {
                            Column {
                                if (rec.name.isNotBlank()) {
                                    Text(rec.name, color = AppTheme.colors.textPrimary, fontSize = 13.sp, fontWeight = FontWeight.Bold)
                                }
                                Text(rec.email, color = Gold, fontSize = 12.sp)
                            }
                        },
                        onClick = {
                            val prefix = if (to.contains(",")) to.substringBeforeLast(",") + ", " else ""
                            to = prefix + rec.email
                            toFocus = false
                        }
                    )
                }
            }
        }

        // Optional CC & BCC Fields
        AnimatedVisibility(visible = showCcBcc) {
            Row(
                modifier = Modifier.fillMaxWidth(),
                horizontalArrangement = Arrangement.spacedBy(8.dp)
            ) {
                OutlinedTextField(
                    value = cc,
                    onValueChange = { cc = it },
                    label = { Text("CC", color = AppTheme.colors.textMuted, fontSize = 12.sp) },
                    singleLine = true,
                    colors = fieldColors,
                    modifier = Modifier.weight(1f)
                )
                OutlinedTextField(
                    value = bcc,
                    onValueChange = { bcc = it },
                    label = { Text("BCC", color = AppTheme.colors.textMuted, fontSize = 12.sp) },
                    singleLine = true,
                    colors = fieldColors,
                    modifier = Modifier.weight(1f)
                )
            }
        }

        // Subject
        OutlinedTextField(
            value = subject,
            onValueChange = { subject = it },
            label = { Text("Betreff", color = AppTheme.colors.textMuted, fontSize = 12.sp) },
            singleLine = true,
            colors = fieldColors,
            modifier = Modifier.fillMaxWidth()
        )

        // Formatting & Signature Toolbar
        Row(
            modifier = Modifier.fillMaxWidth(),
            horizontalArrangement = Arrangement.spacedBy(6.dp),
            verticalAlignment = Alignment.CenterVertically
        ) {
            ComposeFormatPill("B") { body += " *Text* " }
            ComposeFormatPill("I") { body += " _Text_ " }
            ComposeFormatPill("• Liste") { body += "\n• " }
            ComposeFormatPill("> Zitat") { body += "\n> " }
            ComposeFormatPill("✒ Signatur") {
                val sig = activeSenderAccount?.signature?.takeIf { it.isNotBlank() }
                    ?: "Mit herzlichen Grüßen\nAlina Steinhauer\nMein-Seelenfunke"
                val cleanSig = EmailTextSanitizer.stripHtmlToStructuredText(sig).ifBlank { sig }
                body = body.trimEnd() + "\n\n--\n" + cleanSig
            }
        }

        // Body Editor
        OutlinedTextField(
            value = body,
            onValueChange = { body = it },
            label = { Text("Nachricht", color = AppTheme.colors.textMuted, fontSize = 12.sp) },
            colors = fieldColors,
            modifier = Modifier
                .fillMaxWidth()
                .weight(1f)
        )

        // Bottom Buttons: Cancel, Save Draft, Send
        Row(
            modifier = Modifier.fillMaxWidth(),
            horizontalArrangement = Arrangement.SpaceBetween,
            verticalAlignment = Alignment.CenterVertically
        ) {
            TextButton(
                onClick = onDismiss,
                enabled = !isSending,
                colors = ButtonDefaults.textButtonColors(contentColor = AppTheme.colors.textSecondary)
            ) {
                Text("Abbrechen", fontSize = 12.sp)
            }

            Row(horizontalArrangement = Arrangement.spacedBy(8.dp)) {
                OutlinedButton(
                    onClick = {
                        viewModel.saveDraft(
                            draftId = prefill.draftId,
                            accountId = selectedAccountId,
                            to = to,
                            cc = cc,
                            bcc = bcc,
                            subject = subject,
                            body = body
                        ) { saved ->
                            if (saved) {
                                Toast.makeText(context, "Entwurf gespeichert.", Toast.LENGTH_SHORT).show()
                                onDismiss()
                            }
                        }
                    },
                    enabled = !isSending && (to.isNotBlank() || subject.isNotBlank() || body.isNotBlank()),
                    border = BorderStroke(1.dp, Gold.copy(alpha = 0.6f)),
                    shape = RoundedCornerShape(8.dp)
                ) {
                    Text("💾 Entwurf", color = Gold, fontSize = 12.sp, fontWeight = FontWeight.Bold)
                }

                Button(
                    onClick = {
                        onSend(
                            to,
                            cc.takeIf { it.isNotBlank() },
                            bcc.takeIf { it.isNotBlank() },
                            subject,
                            body,
                            selectedAccountId,
                            prefill.draftId,
                            forwardedAttachments
                        )
                    },
                    enabled = !isSending && to.isNotBlank() && subject.isNotBlank() && body.isNotBlank(),
                    colors = ButtonDefaults.buttonColors(containerColor = Gold, contentColor = SpaceBlack),
                    shape = RoundedCornerShape(8.dp)
                ) {
                    if (isSending) {
                        CircularProgressIndicator(color = SpaceBlack, modifier = Modifier.size(16.dp), strokeWidth = 2.dp)
                    } else {
                        Icon(Icons.Default.Send, contentDescription = null, modifier = Modifier.size(14.dp))
                        Spacer(modifier = Modifier.width(5.dp))
                        Text("Senden", fontSize = 12.sp, fontWeight = FontWeight.Bold)
                    }
                }
            }
        }
    }
}

@Composable
private fun ComposeFormatPill(label: String, onClick: () -> Unit) {
    Box(
        modifier = Modifier
            .clip(RoundedCornerShape(6.dp))
            .background(AppTheme.colors.glassSurface)
            .border(1.dp, AppTheme.colors.glassBorder, RoundedCornerShape(6.dp))
            .clickable(onClick = onClick)
            .padding(horizontal = 8.dp, vertical = 4.dp)
    ) {
        Text(label, color = AppTheme.colors.textSecondary, fontSize = 11.sp, fontWeight = FontWeight.Bold)
    }
}

@OptIn(ExperimentalMaterial3Api::class)
@Composable
fun AccountManagerInline(
    viewModel: EmailViewModel,
    onDismiss: () -> Unit,
    onAddAccount: () -> Unit,
    onEditAccount: (EmailAccount) -> Unit
) {
    val accounts by viewModel.accounts.collectAsState()
    val context = LocalContext.current

    Column(
        modifier = Modifier
            .fillMaxSize()
            .background(AppTheme.colors.cardBackground, shape = RoundedCornerShape(16.dp))
            .border(1.dp, AppTheme.colors.cardBorder, RoundedCornerShape(16.dp))
            .padding(16.dp)
    ) {
        Row(
            modifier = Modifier.fillMaxWidth(),
            horizontalArrangement = Arrangement.SpaceBetween,
            verticalAlignment = Alignment.CenterVertically
        ) {
            Text(
                text = "Postfächer verwalten",
                color = Gold,
                fontWeight = FontWeight.Bold,
                fontSize = 18.sp
            )
            IconButton(onClick = onDismiss) {
                Icon(Icons.Default.Close, contentDescription = "Schließen", tint = AppTheme.colors.textSecondary)
            }
        }

        Spacer(modifier = Modifier.height(12.dp))

        Button(
            onClick = onAddAccount,
            colors = ButtonDefaults.buttonColors(containerColor = Gold, contentColor = SpaceBlack),
            modifier = Modifier.fillMaxWidth(),
            shape = RoundedCornerShape(8.dp)
        ) {
            Icon(Icons.Default.Add, contentDescription = null)
            Spacer(modifier = Modifier.width(8.dp))
            Text("Neues Postfach hinzufügen", fontWeight = FontWeight.Bold)
        }

        Spacer(modifier = Modifier.height(12.dp))

        if (accounts.isEmpty()) {
            Box(
                modifier = Modifier
                    .weight(1f)
                    .fillMaxWidth(),
                contentAlignment = Alignment.Center
            ) {
                Text(
                    text = "Keine E-Mail-Konten eingerichtet.",
                    color = AppTheme.colors.textMuted,
                    fontSize = 14.sp
                )
            }
        } else {
            LazyColumn(
                verticalArrangement = Arrangement.spacedBy(8.dp),
                modifier = Modifier
                    .weight(1f)
                    .fillMaxWidth()
            ) {
                items(accounts) { account ->
                    Card(
                        colors = CardDefaults.cardColors(containerColor = AppTheme.colors.glassSurface),
                        border = BorderStroke(1.dp, AppTheme.colors.glassBorder),
                        shape = RoundedCornerShape(10.dp),
                        modifier = Modifier.fillMaxWidth()
                    ) {
                        Row(
                            modifier = Modifier.padding(12.dp),
                            verticalAlignment = Alignment.CenterVertically,
                            horizontalArrangement = Arrangement.SpaceBetween
                        ) {
                            Column(modifier = Modifier.weight(1f)) {
                                Text(
                                    text = account.name ?: "Unbenanntes Konto",
                                    color = AppTheme.colors.textPrimary,
                                    fontWeight = FontWeight.Bold,
                                    fontSize = 14.sp
                                )
                                Text(
                                    text = account.email,
                                    color = AppTheme.colors.textMuted,
                                    fontSize = 12.sp
                                )
                            }

                            Row {
                                IconButton(onClick = { onEditAccount(account) }) {
                                    Icon(Icons.Default.Edit, contentDescription = "Bearbeiten", tint = Gold)
                                }

                                IconButton(onClick = {
                                    viewModel.deleteAccount(account.id) { success ->
                                        if (success) {
                                            Toast.makeText(context, "Postfach gelöscht.", Toast.LENGTH_SHORT).show()
                                        } else {
                                            Toast.makeText(context, "Fehler beim Löschen.", Toast.LENGTH_SHORT).show()
                                        }
                                    }
                                }) {
                                    Icon(Icons.Default.Delete, contentDescription = "Löschen", tint = ImportantRed)
                                }
                            }
                        }
                    }
                }
            }
        }
    }
}

@OptIn(ExperimentalMaterial3Api::class)
@Composable
fun AddEditAccountInline(
    account: EmailAccount?,
    viewModel: EmailViewModel,
    onDismiss: () -> Unit
) {
    var name by remember { mutableStateOf(account?.name ?: "") }
    var email by remember { mutableStateOf(account?.email ?: "") }
    var password by remember { mutableStateOf("") }
    var passwordVisible by remember { mutableStateOf(false) }

    var imapHost by remember { mutableStateOf(account?.imapHost ?: "") }
    var imapPort by remember { mutableStateOf(account?.imapPort?.toString() ?: "993") }
    var imapEncryption by remember { mutableStateOf(account?.imapEncryption ?: "ssl") }
    var imapUsername by remember { mutableStateOf(account?.imapUsername ?: "") }

    var smtpHost by remember { mutableStateOf(account?.smtpHost ?: "") }
    var smtpPort by remember { mutableStateOf(account?.smtpPort?.toString() ?: "465") }
    var smtpEncryption by remember { mutableStateOf(account?.smtpEncryption ?: "ssl") }
    var smtpUsername by remember { mutableStateOf(account?.smtpUsername ?: "") }

    var signature by remember { mutableStateOf(account?.signature ?: "") }
    var isDefault by remember { mutableStateOf(account?.isDefault ?: false) }
    var isCommercial by remember { mutableStateOf(account?.isCommercial ?: true) }

    var selectedPreset by remember { mutableStateOf("Custom") }
    val presets = listOf("Custom", "Mittwald", "Gmail", "GMX", "T-Online")
    val context = LocalContext.current
    var isSaving by remember { mutableStateOf(false) }

    LaunchedEffect(selectedPreset) {
        when (selectedPreset) {
            "Mittwald" -> {
                email = "kontakt@mein-seelenfunke.de"
                name = "kontakt@mein-seelenfunke.de"
                imapHost = "mail.agenturserver.de"
                imapPort = "993"
                imapEncryption = "ssl"
                smtpHost = "mail.agenturserver.de"
                smtpPort = "465"
                smtpEncryption = "ssl"
            }
            "Gmail" -> {
                imapHost = "imap.gmail.com"
                imapPort = "993"
                imapEncryption = "ssl"
                smtpHost = "smtp.gmail.com"
                smtpPort = "465"
                smtpEncryption = "ssl"
            }
            "GMX" -> {
                imapHost = "imap.gmx.net"
                imapPort = "993"
                imapEncryption = "ssl"
                smtpHost = "mail.gmx.net"
                smtpPort = "465"
                smtpEncryption = "ssl"
            }
            "T-Online" -> {
                imapHost = "secureimap.t-online.de"
                imapPort = "993"
                imapEncryption = "ssl"
                smtpHost = "securesmtp.t-online.de"
                smtpPort = "465"
                smtpEncryption = "ssl"
            }
        }
    }

    Column(
        modifier = Modifier
            .fillMaxSize()
            .background(AppTheme.colors.cardBackground, shape = RoundedCornerShape(16.dp))
            .border(1.dp, AppTheme.colors.cardBorder, RoundedCornerShape(16.dp))
            .padding(16.dp)
    ) {
        Row(
            modifier = Modifier.fillMaxWidth(),
            horizontalArrangement = Arrangement.SpaceBetween,
            verticalAlignment = Alignment.CenterVertically
        ) {
            Text(
                text = if (account == null) "Postfach hinzufügen" else "Postfach bearbeiten",
                color = Gold,
                fontWeight = FontWeight.Bold,
                fontSize = 18.sp
            )
            IconButton(onClick = onDismiss, enabled = !isSaving) {
                Icon(Icons.Default.Close, contentDescription = "Schließen", tint = AppTheme.colors.textSecondary)
            }
        }

        Spacer(modifier = Modifier.height(12.dp))

        Column(
            modifier = Modifier
                .weight(1f)
                .fillMaxWidth()
                .verticalScroll(rememberScrollState()),
            verticalArrangement = Arrangement.spacedBy(10.dp)
        ) {
            Text("Anbieter Voreinstellung:", color = AppTheme.colors.textMuted, fontSize = 12.sp, fontWeight = FontWeight.Bold)
            Row(
                horizontalArrangement = Arrangement.spacedBy(8.dp),
                modifier = Modifier.fillMaxWidth()
            ) {
                presets.forEach { preset ->
                    val isSelected = selectedPreset == preset
                    Box(
                        modifier = Modifier
                            .weight(1f)
                            .clip(RoundedCornerShape(8.dp))
                            .background(if (isSelected) Gold else AppTheme.colors.glassSurface)
                            .border(1.dp, if (isSelected) Gold else AppTheme.colors.glassBorder, RoundedCornerShape(8.dp))
                            .clickable { selectedPreset = preset }
                            .padding(vertical = 6.dp),
                        contentAlignment = Alignment.Center
                    ) {
                        Text(preset, color = if (isSelected) SpaceBlack else AppTheme.colors.textPrimary, fontSize = 11.sp, fontWeight = FontWeight.Bold)
                    }
                }
            }

            if (selectedPreset == "Gmail") {
                Text(
                    text = "Hinweis: Für Gmail musst du in deinem Google-Konto ein 'App-Passwort' generieren.",
                    color = Gold,
                    fontSize = 10.sp,
                    fontWeight = FontWeight.Bold,
                    modifier = Modifier.padding(vertical = 4.dp)
                )
            }

            val fieldColors = OutlinedTextFieldDefaults.colors(
                focusedBorderColor = Gold,
                unfocusedBorderColor = AppTheme.colors.inputBorder,
                focusedTextColor = AppTheme.colors.textPrimary,
                unfocusedTextColor = AppTheme.colors.textPrimary,
                focusedContainerColor = AppTheme.colors.inputBackground,
                unfocusedContainerColor = AppTheme.colors.inputBackground,
                focusedLabelColor = Gold,
                unfocusedLabelColor = AppTheme.colors.textMuted
            )

            OutlinedTextField(
                value = name,
                onValueChange = { name = it },
                label = { Text("Name (Anzeigename)") },
                colors = fieldColors,
                modifier = Modifier.fillMaxWidth()
            )

            OutlinedTextField(
                value = email,
                onValueChange = { email = it },
                label = { Text("E-Mail Adresse") },
                colors = fieldColors,
                modifier = Modifier.fillMaxWidth()
            )

            OutlinedTextField(
                value = password,
                onValueChange = { password = it },
                label = { Text(if (account == null) "Passwort" else "Passwort (leer lassen zum Beibehalten)") },
                colors = fieldColors,
                modifier = Modifier.fillMaxWidth(),
                visualTransformation = if (passwordVisible) VisualTransformation.None else PasswordVisualTransformation(),
                keyboardOptions = KeyboardOptions(keyboardType = KeyboardType.Password),
                trailingIcon = {
                    val image = if (passwordVisible) Icons.Filled.Visibility else Icons.Filled.VisibilityOff
                    val description = if (passwordVisible) "Passwort ausblenden" else "Passwort anzeigen"
                    IconButton(onClick = { passwordVisible = !passwordVisible }) {
                        Icon(imageVector = image, contentDescription = description, tint = Gold)
                    }
                }
            )

            Text("IMAP-Einstellungen (Empfang)", color = Gold, fontSize = 12.sp, fontWeight = FontWeight.Bold)
            Row(horizontalArrangement = Arrangement.spacedBy(8.dp)) {
                OutlinedTextField(
                    value = imapHost,
                    onValueChange = { imapHost = it },
                    label = { Text("IMAP-Server") },
                    colors = fieldColors,
                    modifier = Modifier.weight(2f)
                )
                OutlinedTextField(
                    value = imapPort,
                    onValueChange = { imapPort = it },
                    label = { Text("Port") },
                    colors = fieldColors,
                    modifier = Modifier.weight(1f)
                )
            }

            OutlinedTextField(
                value = imapUsername,
                onValueChange = { imapUsername = it },
                label = { Text("IMAP-Benutzername (falls abweichend)") },
                placeholder = { Text(email) },
                colors = fieldColors,
                modifier = Modifier.fillMaxWidth()
            )

            Text("SMTP-Einstellungen (Versand)", color = Gold, fontSize = 12.sp, fontWeight = FontWeight.Bold)
            Row(horizontalArrangement = Arrangement.spacedBy(8.dp)) {
                OutlinedTextField(
                    value = smtpHost,
                    onValueChange = { smtpHost = it },
                    label = { Text("SMTP-Server") },
                    colors = fieldColors,
                    modifier = Modifier.weight(2f)
                )
                OutlinedTextField(
                    value = smtpPort,
                    onValueChange = { smtpPort = it },
                    label = { Text("Port") },
                    colors = fieldColors,
                    modifier = Modifier.weight(1f)
                )
            }

            OutlinedTextField(
                value = smtpUsername,
                onValueChange = { smtpUsername = it },
                label = { Text("SMTP-Benutzername (falls abweichend)") },
                placeholder = { Text(email) },
                colors = fieldColors,
                modifier = Modifier.fillMaxWidth()
            )

            Text("Optionen", color = Gold, fontSize = 12.sp, fontWeight = FontWeight.Bold)
            OutlinedTextField(
                value = signature,
                onValueChange = { signature = it },
                label = { Text("Signatur") },
                colors = fieldColors,
                modifier = Modifier.fillMaxWidth()
            )

            Row(verticalAlignment = Alignment.CenterVertically) {
                Checkbox(
                    checked = isDefault,
                    onCheckedChange = { isDefault = it },
                    colors = CheckboxDefaults.colors(checkedColor = Gold)
                )
                Spacer(modifier = Modifier.width(4.dp))
                Text("Als Standardpostfach festlegen", color = AppTheme.colors.textPrimary, fontSize = 13.sp)
            }

            Row(verticalAlignment = Alignment.CenterVertically) {
                Checkbox(
                    checked = isCommercial,
                    onCheckedChange = { isCommercial = it },
                    colors = CheckboxDefaults.colors(checkedColor = Gold)
                )
                Spacer(modifier = Modifier.width(4.dp))
                Text("Geschäftliches Konto", color = AppTheme.colors.textPrimary, fontSize = 13.sp)
            }
        }

        Spacer(modifier = Modifier.height(12.dp))

        Row(
            modifier = Modifier.fillMaxWidth(),
            horizontalArrangement = Arrangement.End,
            verticalAlignment = Alignment.CenterVertically
        ) {
            TextButton(onClick = onDismiss, enabled = !isSaving) {
                Text("Abbrechen", color = AppTheme.colors.textSecondary)
            }
            Spacer(modifier = Modifier.width(8.dp))
            Button(
                onClick = {
                    isSaving = true
                    val portImap = imapPort.toIntOrNull() ?: 993
                    val portSmtp = smtpPort.toIntOrNull() ?: 465
                    val pass = password.ifBlank { null }
                    viewModel.saveAccount(
                        id = account?.id,
                        name = name,
                        email = email,
                        password = pass,
                        imapHost = imapHost,
                        imapPort = portImap,
                        imapEncryption = imapEncryption,
                        imapUsername = imapUsername.ifBlank { null },
                        smtpHost = smtpHost,
                        smtpPort = portSmtp,
                        smtpEncryption = smtpEncryption,
                        smtpUsername = smtpUsername.ifBlank { null },
                        signature = signature.ifBlank { null },
                        isDefault = isDefault,
                        isCommercial = isCommercial
                    ) { success, errorMessage ->
                        isSaving = false
                        if (success) {
                            Toast.makeText(context, "Postfach gespeichert.", Toast.LENGTH_SHORT).show()
                            onDismiss()
                        } else {
                            Toast.makeText(context, "Fehler beim Speichern: ${errorMessage ?: "Unbekannter Fehler"}", Toast.LENGTH_LONG).show()
                        }
                    }
                },
                enabled = !isSaving && name.isNotBlank() && email.isNotBlank() && imapHost.isNotBlank() && smtpHost.isNotBlank(),
                colors = ButtonDefaults.buttonColors(containerColor = Gold, contentColor = SpaceBlack)
            ) {
                if (isSaving) {
                    CircularProgressIndicator(color = SpaceBlack, modifier = Modifier.size(18.dp))
                } else {
                    Text("Speichern")
                }
            }
        }
    }
}

private fun getFileName(context: android.content.Context, uri: Uri): String {
    var name = ""
    val cursor = context.contentResolver.query(uri, null, null, null, null)
    cursor?.use {
        if (it.moveToFirst()) {
            val nameIndex = it.getColumnIndex(android.provider.OpenableColumns.DISPLAY_NAME)
            if (nameIndex != -1) {
                name = it.getString(nameIndex)
            }
        }
    }
    if (name.isEmpty()) {
        name = uri.lastPathSegment ?: "file"
    }
    return name
}
