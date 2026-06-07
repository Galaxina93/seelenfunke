package de.meinseelenfunke.app.ui.screens

import android.net.Uri
import android.widget.Toast
import androidx.activity.compose.rememberLauncherForActivityResult
import androidx.activity.result.contract.ActivityResultContracts
import androidx.compose.animation.*
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
import androidx.compose.foundation.verticalScroll
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.*
import androidx.compose.material3.*
import androidx.compose.runtime.*
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.graphics.Brush
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.style.TextAlign
import androidx.compose.ui.text.style.TextOverflow
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import androidx.compose.ui.viewinterop.AndroidView
import androidx.core.text.HtmlCompat
import de.meinseelenfunke.app.data.api.EmailAccount
import de.meinseelenfunke.app.data.api.EmailMessage
import de.meinseelenfunke.app.ui.theme.GlassWhite10
import de.meinseelenfunke.app.ui.theme.Gold
import de.meinseelenfunke.app.ui.theme.Slate300
import de.meinseelenfunke.app.ui.theme.Slate400
import de.meinseelenfunke.app.ui.theme.Slate50
import de.meinseelenfunke.app.ui.theme.SpaceBlack
import java.text.SimpleDateFormat
import java.util.Date
import java.util.Locale

@OptIn(ExperimentalMaterial3Api::class)
@Composable
fun EmailTabContent(
    viewModel: EmailViewModel,
    modifier: Modifier = Modifier
) {
    val uiState by viewModel.uiState.collectAsState()
    val accounts by viewModel.accounts.collectAsState()
    val messages by viewModel.messages.collectAsState()
    val selectedAccount by viewModel.selectedAccount.collectAsState()
    val selectedFolder by viewModel.selectedFolder.collectAsState()
    val selectedMessage by viewModel.selectedMessage.collectAsState()
    val searchQuery by viewModel.searchQuery.collectAsState()
    val unreadOnly by viewModel.unreadOnly.collectAsState()
    val isSending by viewModel.isSending.collectAsState()

    var showComposeDialog by remember { mutableStateOf(false) }
    var showAccountManagerDialog by remember { mutableStateOf(false) }
    val context = LocalContext.current

    Box(
        modifier = modifier
            .fillMaxSize()
            .background(
                Brush.verticalGradient(
                    colors = listOf(Color(0xFF0F172A), Color(0xFF020617)) // Slate900 to very dark
                )
            )
            .padding(16.dp)
    ) {
        Column(
            modifier = Modifier.fillMaxSize(),
            verticalArrangement = Arrangement.spacedBy(12.dp)
        ) {
            // Account Selector & Compose Button Row
            Row(
                modifier = Modifier.fillMaxWidth(),
                horizontalArrangement = Arrangement.SpaceBetween,
                verticalAlignment = Alignment.CenterVertically
            ) {
                Text(
                    text = "E-Mail Postfächer",
                    fontSize = 18.sp,
                    fontWeight = FontWeight.Bold,
                    color = Gold
                )

                IconButton(
                    onClick = { showAccountManagerDialog = true },
                    modifier = Modifier
                        .background(Gold, shape = CircleShape)
                        .size(40.dp)
                ) {
                    Icon(
                        imageVector = Icons.Default.Edit,
                        contentDescription = "Postfächer verwalten",
                        tint = SpaceBlack
                    )
                }
            }

            // Accounts List / Chips
            if (accounts.isNotEmpty()) {
                LazyRow(
                    horizontalArrangement = Arrangement.spacedBy(8.dp),
                    modifier = Modifier.fillMaxWidth()
                ) {
                    items(accounts) { account ->
                        val isSelected = account.id == selectedAccount?.id
                        AccountChip(
                            account = account,
                            isSelected = isSelected,
                            onClick = { viewModel.selectAccount(account) }
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
                        color = Slate400,
                        fontSize = 14.sp
                    )
                    Button(
                        onClick = { showAccountManagerDialog = true },
                        colors = ButtonDefaults.buttonColors(containerColor = Gold, contentColor = SpaceBlack),
                        shape = RoundedCornerShape(8.dp)
                    ) {
                        Icon(
                            imageVector = Icons.Default.Add,
                            contentDescription = null,
                            modifier = Modifier.size(16.dp)
                        )
                        Spacer(modifier = Modifier.width(6.dp))
                        Text("Konto einrichten", fontSize = 12.sp, fontWeight = FontWeight.Bold)
                    }
                }
            }

            // Folder Tabs Row
            Row(
                modifier = Modifier.fillMaxWidth(),
                horizontalArrangement = Arrangement.spacedBy(8.dp)
            ) {
                val folders = listOf(
                    Triple("INBOX", "Posteingang", Icons.Default.Inbox),
                    Triple("SENT", "Gesendet", Icons.Default.Send),
                    Triple("TRASH", "Papierkorb", Icons.Default.Delete)
                )

                folders.forEach { (folderKey, label, icon) ->
                    val isSelected = selectedFolder == folderKey
                    Box(
                        modifier = Modifier
                            .weight(1f)
                            .clip(RoundedCornerShape(8.dp))
                            .background(if (isSelected) Gold else GlassWhite10)
                            .clickable { viewModel.selectFolder(folderKey) }
                            .padding(vertical = 8.dp),
                        contentAlignment = Alignment.Center
                    ) {
                        Row(
                            horizontalArrangement = Arrangement.spacedBy(4.dp),
                            verticalAlignment = Alignment.CenterVertically
                        ) {
                            Icon(
                                imageVector = icon,
                                contentDescription = label,
                                tint = if (isSelected) SpaceBlack else Slate300,
                                modifier = Modifier.size(16.dp)
                            )
                            Text(
                                text = label,
                                fontSize = 11.sp,
                                fontWeight = FontWeight.Bold,
                                color = if (isSelected) SpaceBlack else Slate300,
                                maxLines = 1,
                                overflow = TextOverflow.Ellipsis
                            )
                        }
                    }
                }
            }

            // Search Bar & Filter Row
            Row(
                modifier = Modifier.fillMaxWidth(),
                horizontalArrangement = Arrangement.spacedBy(8.dp),
                verticalAlignment = Alignment.CenterVertically
            ) {
                OutlinedTextField(
                    value = searchQuery,
                    onValueChange = { viewModel.setSearchQuery(it) },
                    placeholder = { Text("E-Mails suchen...", color = Slate400, fontSize = 13.sp) },
                    leadingIcon = { Icon(Icons.Default.Search, contentDescription = "Suchen", tint = Slate400) },
                    modifier = Modifier.weight(1f),
                    colors = OutlinedTextFieldDefaults.colors(
                        focusedBorderColor = Gold,
                        unfocusedBorderColor = GlassWhite10,
                        focusedTextColor = Slate50,
                        unfocusedTextColor = Slate300
                    ),
                    shape = RoundedCornerShape(10.dp)
                )

                // Unread Only Switch / Button
                IconButton(
                    onClick = { viewModel.setUnreadOnly(!unreadOnly) },
                    modifier = Modifier
                        .background(if (unreadOnly) Gold else GlassWhite10, shape = RoundedCornerShape(10.dp))
                        .size(48.dp)
                ) {
                    Icon(
                        imageVector = if (unreadOnly) Icons.Default.Mail else Icons.Default.Drafts,
                        contentDescription = "Ungelesene filtern",
                        tint = if (unreadOnly) SpaceBlack else Slate300
                    )
                }
            }

            // Main Email List Area
            when (uiState) {
                is EmailUiState.Loading -> {
                    Box(
                        modifier = Modifier
                            .weight(1f)
                            .fillMaxWidth(),
                        contentAlignment = Alignment.Center
                    ) {
                        CircularProgressIndicator(color = Gold)
                    }
                }
                is EmailUiState.Error -> {
                    Box(
                        modifier = Modifier
                            .weight(1f)
                            .fillMaxWidth(),
                        contentAlignment = Alignment.Center
                    ) {
                        Column(
                            horizontalAlignment = Alignment.CenterHorizontally,
                            verticalArrangement = Arrangement.spacedBy(8.dp)
                        ) {
                            Text(
                                text = (uiState as EmailUiState.Error).message,
                                color = Color.Red,
                                textAlign = TextAlign.Center,
                                fontSize = 14.sp
                            )
                            Button(
                                onClick = { viewModel.loadAccountsAndMessages() },
                                colors = ButtonDefaults.buttonColors(containerColor = Gold, contentColor = SpaceBlack)
                            ) {
                                Text("Erneut versuchen")
                            }
                        }
                    }
                }
                is EmailUiState.Success -> {
                    if (messages.isEmpty()) {
                        Box(
                            modifier = Modifier
                                .weight(1f)
                                .fillMaxWidth(),
                            contentAlignment = Alignment.Center
                        ) {
                            Text(
                                text = "Keine E-Mails gefunden.",
                                color = Slate400,
                                fontSize = 14.sp
                            )
                        }
                    } else {
                        LazyColumn(
                            modifier = Modifier
                                .weight(1f)
                                .fillMaxWidth(),
                            verticalArrangement = Arrangement.spacedBy(8.dp)
                        ) {
                            items(messages) { message ->
                                EmailMessageRow(
                                    message = message,
                                    onClick = { viewModel.selectMessage(message) }
                                )
                            }
                            item {
                                Spacer(modifier = Modifier.height(80.dp))
                            }
                        }
                    }
                }
            }
        }

        // Floating Action Button to compose mail
        FloatingActionButton(
            onClick = { showComposeDialog = true },
            containerColor = Gold,
            contentColor = SpaceBlack,
            modifier = Modifier
                .align(Alignment.BottomEnd)
                .padding(bottom = 16.dp, end = 16.dp)
        ) {
            Icon(
                imageVector = Icons.Default.Edit,
                contentDescription = "Verfassen"
            )
        }

        // Compose Message Dialog
        if (showComposeDialog) {
            ComposeEmailDialog(
                viewModel = viewModel,
                fromAccount = selectedAccount,
                isSending = isSending,
                onDismiss = {
                    viewModel.clearComposeAttachments()
                    showComposeDialog = false
                },
                onSend = { to, subject, body ->
                    viewModel.sendEmail(context, to, subject, body) { success ->
                        if (success) {
                            Toast.makeText(context, "E-Mail erfolgreich gesendet!", Toast.LENGTH_SHORT).show()
                            showComposeDialog = false
                        } else {
                            Toast.makeText(context, "Fehler beim Senden der E-Mail.", Toast.LENGTH_LONG).show()
                        }
                    }
                }
            )
        }

        // Message Detail Sheet / Dialog
        selectedMessage?.let { msg ->
            EmailDetailDialog(
                message = msg,
                currentFolder = selectedFolder,
                onDismiss = { viewModel.selectAccount(selectedAccount ?: return@EmailDetailDialog) },
                onDelete = {
                    viewModel.deleteMessage(msg)
                },
                onToggleUnread = {
                    viewModel.toggleMessageRead(msg)
                },
                onReply = {
                    showComposeDialog = true
                }
            )
        }

        if (showAccountManagerDialog) {
            AccountManagerDialog(
                viewModel = viewModel,
                onDismiss = { showAccountManagerDialog = false }
            )
        }
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
            .clip(RoundedCornerShape(20.dp))
            .background(if (isSelected) Gold else GlassWhite10)
            .clickable(onClick = onClick)
            .padding(horizontal = 14.dp, vertical = 6.dp),
        contentAlignment = Alignment.Center
    ) {
        Row(
            verticalAlignment = Alignment.CenterVertically,
            horizontalArrangement = Arrangement.spacedBy(6.dp)
        ) {
            Text(
                text = account.email,
                color = if (isSelected) SpaceBlack else Slate50,
                fontSize = 13.sp,
                fontWeight = FontWeight.SemiBold
            )
            if (account.unreadCount > 0) {
                Box(
                    modifier = Modifier
                        .clip(CircleShape)
                        .background(if (isSelected) SpaceBlack else Gold)
                        .padding(horizontal = 6.dp, vertical = 2.dp)
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
    onClick: () -> Unit
) {
    Card(
        modifier = Modifier
            .fillMaxWidth()
            .clickable(onClick = onClick),
        colors = CardDefaults.cardColors(containerColor = GlassWhite10)
    ) {
        Column(
            modifier = Modifier.padding(12.dp)
        ) {
            Row(
                modifier = Modifier.fillMaxWidth(),
                horizontalArrangement = Arrangement.SpaceBetween,
                verticalAlignment = Alignment.Top
            ) {
                Row(
                    verticalAlignment = Alignment.CenterVertically,
                    horizontalArrangement = Arrangement.spacedBy(6.dp),
                    modifier = Modifier.weight(1f)
                ) {
                    if (!message.isRead) {
                        Box(
                            modifier = Modifier
                                .size(8.dp)
                                .background(Gold, shape = CircleShape)
                        )
                    }
                    Text(
                        text = message.from,
                        color = if (message.isRead) Slate300 else Slate50,
                        fontWeight = if (message.isRead) FontWeight.Normal else FontWeight.Bold,
                        fontSize = 14.sp,
                        maxLines = 1,
                        overflow = TextOverflow.Ellipsis
                    )
                }
                Text(
                    text = formatEmailDate(message.receivedAt),
                    color = Slate400,
                    fontSize = 11.sp
                )
            }

            Spacer(modifier = Modifier.height(4.dp))

            Text(
                text = message.subject.ifBlank { "(Kein Betreff)" },
                color = if (message.isRead) Slate300 else Slate50,
                fontWeight = if (message.isRead) FontWeight.Normal else FontWeight.Bold,
                fontSize = 13.sp,
                maxLines = 1,
                overflow = TextOverflow.Ellipsis
            )

            Spacer(modifier = Modifier.height(2.dp))

            Text(
                text = (message.body ?: message.htmlBody ?: "").trim().take(100),
                color = Slate400,
                fontSize = 12.sp,
                maxLines = 2,
                overflow = TextOverflow.Ellipsis
            )
        }
    }
}

@OptIn(ExperimentalMaterial3Api::class)
@Composable
fun ComposeEmailDialog(
    viewModel: EmailViewModel,
    fromAccount: EmailAccount?,
    isSending: Boolean,
    onDismiss: () -> Unit,
    onSend: (to: String, subject: String, body: String) -> Unit
) {
    var to by remember { mutableStateOf("") }
    var subject by remember { mutableStateOf("") }
    var body by remember { mutableStateOf("") }

    val context = LocalContext.current
    val composeAttachments by viewModel.composeAttachments.collectAsState()

    val filePickerLauncher = rememberLauncherForActivityResult(
        contract = ActivityResultContracts.GetMultipleContents()
    ) { uris ->
        uris.forEach { uri ->
            viewModel.addComposeAttachment(uri)
        }
    }

    AlertDialog(
        onDismissRequest = { if (!isSending) onDismiss() },
        containerColor = Color(0xFF0F172A),
        shape = RoundedCornerShape(16.dp),
        title = {
            Text(
                text = "E-Mail verfassen",
                color = Gold,
                fontWeight = FontWeight.Bold,
                fontSize = 18.sp
            )
        },
        text = {
            Column(
                modifier = Modifier
                    .fillMaxWidth()
                    .verticalScroll(rememberScrollState()),
                verticalArrangement = Arrangement.spacedBy(8.dp)
            ) {
                Row(
                    modifier = Modifier.fillMaxWidth(),
                    horizontalArrangement = Arrangement.SpaceBetween,
                    verticalAlignment = Alignment.CenterVertically
                ) {
                    Text(
                        text = "Von: ${fromAccount?.email ?: ""}",
                        color = Slate300,
                        fontSize = 12.sp
                    )

                    IconButton(
                        onClick = { filePickerLauncher.launch("*/*") },
                        modifier = Modifier.size(36.dp)
                    ) {
                        Icon(
                            imageVector = Icons.Default.AttachFile,
                            contentDescription = "Datei anhängen",
                            tint = Gold,
                            modifier = Modifier.size(20.dp)
                        )
                    }
                }

                // Show selected attachments list
                if (composeAttachments.isNotEmpty()) {
                    Text("Anhänge:", color = Slate300, fontSize = 12.sp, fontWeight = FontWeight.Bold)
                    LazyRow(
                        horizontalArrangement = Arrangement.spacedBy(8.dp),
                        modifier = Modifier.fillMaxWidth().padding(vertical = 4.dp)
                    ) {
                        items(composeAttachments) { uri ->
                            val fileName = getFileName(context, uri)
                            Row(
                                verticalAlignment = Alignment.CenterVertically,
                                horizontalArrangement = Arrangement.spacedBy(4.dp),
                                modifier = Modifier
                                    .clip(RoundedCornerShape(8.dp))
                                    .background(GlassWhite10)
                                    .padding(horizontal = 8.dp, vertical = 4.dp)
                            ) {
                                Icon(
                                    imageVector = Icons.Default.AttachFile,
                                    contentDescription = null,
                                    tint = Gold,
                                    modifier = Modifier.size(12.dp)
                                )
                                Text(
                                    text = fileName,
                                    color = Slate50,
                                    fontSize = 10.sp,
                                    maxLines = 1,
                                    overflow = TextOverflow.Ellipsis,
                                    modifier = Modifier.widthIn(max = 100.dp)
                                )
                                Icon(
                                    imageVector = Icons.Default.Close,
                                    contentDescription = "Entfernen",
                                    tint = Slate400,
                                    modifier = Modifier
                                        .size(14.dp)
                                        .clickable { viewModel.removeComposeAttachment(uri) }
                                )
                            }
                        }
                    }
                }

                OutlinedTextField(
                    value = to,
                    onValueChange = { to = it },
                    label = { Text("An (Empfänger)") },
                    colors = OutlinedTextFieldDefaults.colors(
                        focusedBorderColor = Gold,
                        focusedTextColor = Slate50,
                        unfocusedTextColor = Slate300
                    ),
                    modifier = Modifier.fillMaxWidth()
                )

                OutlinedTextField(
                    value = subject,
                    onValueChange = { subject = it },
                    label = { Text("Betreff") },
                    colors = OutlinedTextFieldDefaults.colors(
                        focusedBorderColor = Gold,
                        focusedTextColor = Slate50,
                        unfocusedTextColor = Slate300
                    ),
                    modifier = Modifier.fillMaxWidth()
                )

                OutlinedTextField(
                    value = body,
                    onValueChange = { body = it },
                    label = { Text("Nachricht") },
                    colors = OutlinedTextFieldDefaults.colors(
                        focusedBorderColor = Gold,
                        focusedTextColor = Slate50,
                        unfocusedTextColor = Slate300
                    ),
                    modifier = Modifier
                        .fillMaxWidth()
                        .height(150.dp)
                )
            }
        },
        confirmButton = {
            Button(
                onClick = { onSend(to, subject, body) },
                enabled = !isSending && to.isNotBlank() && subject.isNotBlank() && body.isNotBlank(),
                colors = ButtonDefaults.buttonColors(containerColor = Gold, contentColor = SpaceBlack)
            ) {
                if (isSending) {
                    CircularProgressIndicator(color = SpaceBlack, modifier = Modifier.size(18.dp))
                } else {
                    Text("Senden")
                }
            }
        },
        dismissButton = {
            TextButton(
                onClick = onDismiss,
                enabled = !isSending,
                colors = ButtonDefaults.textButtonColors(contentColor = Slate300)
            ) {
                Text("Abbrechen")
            }
        }
    )
}

@OptIn(ExperimentalMaterial3Api::class)
@Composable
fun AccountManagerDialog(
    viewModel: EmailViewModel,
    onDismiss: () -> Unit
) {
    val accounts by viewModel.accounts.collectAsState()
    var editingAccount by remember { mutableStateOf<EmailAccount?>(null) }
    var showAddEditDialog by remember { mutableStateOf(false) }
    val context = LocalContext.current

    AlertDialog(
        onDismissRequest = onDismiss,
        containerColor = Color(0xFF0F172A),
        shape = RoundedCornerShape(16.dp),
        modifier = Modifier.fillMaxHeight(0.7f),
        title = {
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
                    Icon(Icons.Default.Close, contentDescription = "Schließen", tint = Slate300)
                }
            }
        },
        text = {
            Column(
                modifier = Modifier.fillMaxSize(),
                verticalArrangement = Arrangement.spacedBy(12.dp)
            ) {
                Button(
                    onClick = {
                        editingAccount = null
                        showAddEditDialog = true
                    },
                    colors = ButtonDefaults.buttonColors(containerColor = Gold, contentColor = SpaceBlack),
                    modifier = Modifier.fillMaxWidth(),
                    shape = RoundedCornerShape(8.dp)
                ) {
                    Icon(Icons.Default.Add, contentDescription = null)
                    Spacer(modifier = Modifier.width(8.dp))
                    Text("Neues Postfach hinzufügen", fontWeight = FontWeight.Bold)
                }

                Spacer(modifier = Modifier.height(4.dp))

                if (accounts.isEmpty()) {
                    Text(
                        text = "Keine E-Mail-Konten eingerichtet.",
                        color = Slate400,
                        fontSize = 14.sp,
                        textAlign = TextAlign.Center,
                        modifier = Modifier.fillMaxWidth().padding(vertical = 24.dp)
                    )
                } else {
                    LazyColumn(
                        verticalArrangement = Arrangement.spacedBy(8.dp),
                        modifier = Modifier.fillMaxSize()
                    ) {
                        items(accounts) { account ->
                            Card(
                                colors = CardDefaults.cardColors(containerColor = GlassWhite10),
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
                                            color = Slate50,
                                            fontWeight = FontWeight.Bold,
                                            fontSize = 14.sp
                                        )
                                        Text(
                                            text = account.email,
                                            color = Slate400,
                                            fontSize = 12.sp
                                        )
                                    }

                                    Row {
                                        IconButton(onClick = {
                                            editingAccount = account
                                            showAddEditDialog = true
                                        }) {
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
                                            Icon(Icons.Default.Delete, contentDescription = "Löschen", tint = Color.Red.copy(alpha = 0.8f))
                                        }
                                    }
                                }
                            }
                        }
                    }
                }
            }
        },
        confirmButton = {
            TextButton(onClick = onDismiss, colors = ButtonDefaults.textButtonColors(contentColor = Gold)) {
                Text("Fertig")
            }
        }
    )

    if (showAddEditDialog) {
        AddEditAccountDialog(
            account = editingAccount,
            viewModel = viewModel,
            onDismiss = { showAddEditDialog = false }
        )
    }
}

@OptIn(ExperimentalMaterial3Api::class)
@Composable
fun AddEditAccountDialog(
    account: EmailAccount?,
    viewModel: EmailViewModel,
    onDismiss: () -> Unit
) {
    var name by remember { mutableStateOf(account?.name ?: "") }
    var email by remember { mutableStateOf(account?.email ?: "") }
    var password by remember { mutableStateOf("") }
    
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
    val presets = listOf("Custom", "Gmail", "GMX", "T-Online")
    val context = LocalContext.current
    var isSaving by remember { mutableStateOf(false) }

    LaunchedEffect(selectedPreset) {
        when (selectedPreset) {
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

    AlertDialog(
        onDismissRequest = onDismiss,
        containerColor = Color(0xFF0F172A),
        shape = RoundedCornerShape(16.dp),
        modifier = Modifier.fillMaxHeight(0.9f),
        title = {
            Text(
                text = if (account == null) "Postfach hinzufügen" else "Postfach bearbeiten",
                color = Gold,
                fontWeight = FontWeight.Bold,
                fontSize = 18.sp
            )
        },
        text = {
            Column(
                modifier = Modifier
                    .fillMaxWidth()
                    .verticalScroll(rememberScrollState()),
                verticalArrangement = Arrangement.spacedBy(10.dp)
            ) {
                Text("Anbieter Voreinstellung:", color = Slate400, fontSize = 12.sp, fontWeight = FontWeight.Bold)
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
                                .background(if (isSelected) Gold else GlassWhite10)
                                .clickable { selectedPreset = preset }
                                .padding(vertical = 6.dp),
                            contentAlignment = Alignment.Center
                        ) {
                            Text(preset, color = if (isSelected) SpaceBlack else Slate300, fontSize = 11.sp, fontWeight = FontWeight.Bold)
                        }
                    }
                }

                if (selectedPreset == "Gmail") {
                    Text(
                        text = "Hinweis: Für Gmail musst du in deinem Google-Konto ein 'App-Passwort' generieren und hier eingeben. Dein normales Passwort funktioniert aus Sicherheitsgründen nicht.",
                        color = Gold,
                        fontSize = 10.sp,
                        fontWeight = FontWeight.Bold,
                        modifier = Modifier.padding(vertical = 4.dp)
                    )
                }

                OutlinedTextField(
                    value = name,
                    onValueChange = { name = it },
                    label = { Text("Name (Anzeigename)") },
                    colors = OutlinedTextFieldDefaults.colors(focusedBorderColor = Gold, focusedTextColor = Slate50, unfocusedTextColor = Slate300),
                    modifier = Modifier.fillMaxWidth()
                )

                OutlinedTextField(
                    value = email,
                    onValueChange = { email = it },
                    label = { Text("E-Mail Adresse") },
                    colors = OutlinedTextFieldDefaults.colors(focusedBorderColor = Gold, focusedTextColor = Slate50, unfocusedTextColor = Slate300),
                    modifier = Modifier.fillMaxWidth()
                )

                OutlinedTextField(
                    value = password,
                    onValueChange = { password = it },
                    label = { Text(if (account == null) "Passwort" else "Passwort (leer lassen zum Beibehalten)") },
                    colors = OutlinedTextFieldDefaults.colors(focusedBorderColor = Gold, focusedTextColor = Slate50, unfocusedTextColor = Slate300),
                    modifier = Modifier.fillMaxWidth()
                )

                Text("IMAP-Einstellungen (Empfang)", color = Gold, fontSize = 12.sp, fontWeight = FontWeight.Bold)
                Row(horizontalArrangement = Arrangement.spacedBy(8.dp)) {
                    OutlinedTextField(
                        value = imapHost,
                        onValueChange = { imapHost = it },
                        label = { Text("IMAP-Server") },
                        colors = OutlinedTextFieldDefaults.colors(focusedBorderColor = Gold, focusedTextColor = Slate50, unfocusedTextColor = Slate300),
                        modifier = Modifier.weight(2f)
                    )
                    OutlinedTextField(
                        value = imapPort,
                        onValueChange = { imapPort = it },
                        label = { Text("Port") },
                        colors = OutlinedTextFieldDefaults.colors(focusedBorderColor = Gold, focusedTextColor = Slate50, unfocusedTextColor = Slate300),
                        modifier = Modifier.weight(1f)
                    )
                }
                
                OutlinedTextField(
                    value = imapUsername,
                    onValueChange = { imapUsername = it },
                    label = { Text("IMAP-Benutzername (falls abweichend)") },
                    placeholder = { Text(email) },
                    colors = OutlinedTextFieldDefaults.colors(focusedBorderColor = Gold, focusedTextColor = Slate50, unfocusedTextColor = Slate300),
                    modifier = Modifier.fillMaxWidth()
                )

                Text("SMTP-Einstellungen (Versand)", color = Gold, fontSize = 12.sp, fontWeight = FontWeight.Bold)
                Row(horizontalArrangement = Arrangement.spacedBy(8.dp)) {
                    OutlinedTextField(
                        value = smtpHost,
                        onValueChange = { smtpHost = it },
                        label = { Text("SMTP-Server") },
                        colors = OutlinedTextFieldDefaults.colors(focusedBorderColor = Gold, focusedTextColor = Slate50, unfocusedTextColor = Slate300),
                        modifier = Modifier.weight(2f)
                    )
                    OutlinedTextField(
                        value = smtpPort,
                        onValueChange = { smtpPort = it },
                        label = { Text("Port") },
                        colors = OutlinedTextFieldDefaults.colors(focusedBorderColor = Gold, focusedTextColor = Slate50, unfocusedTextColor = Slate300),
                        modifier = Modifier.weight(1f)
                    )
                }
                
                OutlinedTextField(
                    value = smtpUsername,
                    onValueChange = { smtpUsername = it },
                    label = { Text("SMTP-Benutzername (falls abweichend)") },
                    placeholder = { Text(email) },
                    colors = OutlinedTextFieldDefaults.colors(focusedBorderColor = Gold, focusedTextColor = Slate50, unfocusedTextColor = Slate300),
                    modifier = Modifier.fillMaxWidth()
                )

                Text("Optionen", color = Gold, fontSize = 12.sp, fontWeight = FontWeight.Bold)
                OutlinedTextField(
                    value = signature,
                    onValueChange = { signature = it },
                    label = { Text("Signatur") },
                    colors = OutlinedTextFieldDefaults.colors(focusedBorderColor = Gold, focusedTextColor = Slate50, unfocusedTextColor = Slate300),
                    modifier = Modifier.fillMaxWidth()
                )

                Row(verticalAlignment = Alignment.CenterVertically) {
                    Checkbox(
                        checked = isDefault,
                        onCheckedChange = { isDefault = it },
                        colors = CheckboxDefaults.colors(checkedColor = Gold)
                    )
                    Spacer(modifier = Modifier.width(4.dp))
                    Text("Als Standardpostfach festlegen", color = Slate300, fontSize = 13.sp)
                }

                Row(verticalAlignment = Alignment.CenterVertically) {
                    Checkbox(
                        checked = isCommercial,
                        onCheckedChange = { isCommercial = it },
                        colors = CheckboxDefaults.colors(checkedColor = Gold)
                    )
                    Spacer(modifier = Modifier.width(4.dp))
                    Text("Geschäftliches Konto", color = Slate300, fontSize = 13.sp)
                }
            }
        },
        confirmButton = {
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
                    ) { success ->
                        isSaving = false
                        if (success) {
                            Toast.makeText(context, "Postfach gespeichert.", Toast.LENGTH_SHORT).show()
                            onDismiss()
                        } else {
                            Toast.makeText(context, "Fehler beim Speichern.", Toast.LENGTH_SHORT).show()
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
        },
        dismissButton = {
            TextButton(onClick = onDismiss, enabled = !isSaving) {
                Text("Abbrechen", color = Slate300)
            }
        }
    )
}

private fun getFileName(context: android.content.Context, uri: android.net.Uri): String {
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

@Composable
fun EmailDetailDialog(
    message: EmailMessage,
    currentFolder: String,
    onDismiss: () -> Unit,
    onDelete: () -> Unit,
    onToggleUnread: () -> Unit,
    onReply: () -> Unit
) {
    AlertDialog(
        onDismissRequest = onDismiss,
        containerColor = Color(0xFF0F172A),
        shape = RoundedCornerShape(16.dp),
        modifier = Modifier.fillMaxHeight(0.85f),
        title = {
            Row(
                modifier = Modifier.fillMaxWidth(),
                horizontalArrangement = Arrangement.SpaceBetween,
                verticalAlignment = Alignment.CenterVertically
            ) {
                IconButton(onClick = onDismiss) {
                    Icon(Icons.Default.ArrowBack, contentDescription = "Schließen", tint = Slate50)
                }

                Row(
                    horizontalArrangement = Arrangement.spacedBy(8.dp)
                ) {
                    IconButton(onClick = {
                        onReply()
                    }) {
                        Icon(Icons.Default.Reply, contentDescription = "Antworten", tint = Slate50)
                    }

                    IconButton(onClick = {
                        onToggleUnread()
                        onDismiss()
                    }) {
                        Icon(
                            imageVector = if (message.isRead) Icons.Default.Mail else Icons.Default.Drafts,
                            contentDescription = if (message.isRead) "Als ungelesen markieren" else "Als gelesen markieren",
                            tint = Slate50
                        )
                    }

                    if (currentFolder != "TRASH") {
                        IconButton(onClick = {
                            onDelete()
                            onDismiss()
                        }) {
                            Icon(Icons.Default.Delete, contentDescription = "Löschen", tint = Slate50)
                        }
                    }
                }
            }
        },
        text = {
            Column(
                modifier = Modifier
                    .fillMaxWidth()
                    .verticalScroll(rememberScrollState()),
                verticalArrangement = Arrangement.spacedBy(12.dp)
            ) {
                // Subject
                Text(
                    text = message.subject.ifBlank { "(Kein Betreff)" },
                    color = Gold,
                    fontWeight = FontWeight.Bold,
                    fontSize = 18.sp
                )

                Spacer(modifier = Modifier.height(1.dp).fillMaxWidth().background(GlassWhite10))

                // Sender, Receiver, Date Details
                Column(verticalArrangement = Arrangement.spacedBy(2.dp)) {
                    Text(
                        text = "Von: ${message.from}",
                        color = Slate50,
                        fontSize = 12.sp,
                        fontWeight = FontWeight.SemiBold
                    )
                    Text(
                        text = "An: ${message.to}",
                        color = Slate300,
                        fontSize = 11.sp
                    )
                    Text(
                        text = "Datum: ${formatDetailedEmailDate(message.receivedAt)}",
                        color = Slate400,
                        fontSize = 11.sp
                    )
                }

                Spacer(modifier = Modifier.height(1.dp).fillMaxWidth().background(GlassWhite10))

                // HTML or Plain Text Content
                val htmlContent = message.htmlBody
                if (!htmlContent.isNullOrBlank()) {
                    AndroidView(
                        factory = { context ->
                            android.widget.TextView(context).apply {
                                setTextColor(android.graphics.Color.WHITE)
                                setTextSize(14f)
                                movementMethod = android.text.method.LinkMovementMethod.getInstance()
                            }
                        },
                        update = { textView ->
                            textView.text = HtmlCompat.fromHtml(htmlContent, HtmlCompat.FROM_HTML_MODE_LEGACY)
                        },
                        modifier = Modifier.fillMaxWidth()
                    )
                } else {
                    Text(
                        text = message.body ?: "",
                        color = Slate50,
                        fontSize = 14.sp
                    )
                }
            }
        },
        confirmButton = {
            TextButton(onClick = onDismiss, colors = ButtonDefaults.textButtonColors(contentColor = Gold)) {
                Text("Fertig")
            }
        }
    )
}

fun formatEmailDate(dateStr: String): String {
    return try {
        // Date formats look like 2026-06-04 18:37:29 or ISO 8601
        val parser = SimpleDateFormat("yyyy-MM-dd'T'HH:mm:ss.SSS'Z'", Locale.GERMANY).apply {
            timeZone = java.util.TimeZone.getTimeZone("UTC")
        }
        val date = parser.parse(dateStr) ?: Date()
        val format = SimpleDateFormat("dd.MM.yyyy", Locale.GERMANY)
        format.format(date)
    } catch (e: Exception) {
        try {
            val parser = SimpleDateFormat("yyyy-MM-dd HH:mm:ss", Locale.GERMANY)
            val date = parser.parse(dateStr) ?: Date()
            val format = SimpleDateFormat("dd.MM.yyyy", Locale.GERMANY)
            format.format(date)
        } catch (ex: Exception) {
            dateStr
        }
    }
}

fun formatDetailedEmailDate(dateStr: String): String {
    return try {
        val parser = SimpleDateFormat("yyyy-MM-dd'T'HH:mm:ss.SSS'Z'", Locale.GERMANY).apply {
            timeZone = java.util.TimeZone.getTimeZone("UTC")
        }
        val date = parser.parse(dateStr) ?: Date()
        val format = SimpleDateFormat("dd.MM.yyyy HH:mm", Locale.GERMANY)
        format.format(date)
    } catch (e: Exception) {
        try {
            val parser = SimpleDateFormat("yyyy-MM-dd HH:mm:ss", Locale.GERMANY)
            val date = parser.parse(dateStr) ?: Date()
            val format = SimpleDateFormat("dd.MM.yyyy HH:mm", Locale.GERMANY)
            format.format(date)
        } catch (ex: Exception) {
            dateStr
        }
    }
}
