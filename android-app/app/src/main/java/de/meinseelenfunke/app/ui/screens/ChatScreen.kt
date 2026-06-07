package de.meinseelenfunke.app.ui.screens

import androidx.compose.foundation.background
import androidx.compose.foundation.border
import androidx.compose.foundation.clickable
import androidx.compose.ui.graphics.graphicsLayer
import androidx.compose.ui.graphics.CompositingStrategy
import androidx.compose.ui.graphics.BlendMode
import androidx.compose.foundation.layout.PaddingValues
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.Spacer
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.layout.width
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.lazy.LazyRow
import androidx.compose.foundation.lazy.items
import androidx.compose.foundation.lazy.rememberLazyListState
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.Close
import androidx.compose.material.icons.filled.ExitToApp
import androidx.compose.material.icons.filled.Send
import androidx.compose.material.icons.filled.PushPin
import androidx.activity.compose.rememberLauncherForActivityResult
import androidx.activity.result.contract.ActivityResultContracts
import androidx.compose.ui.platform.LocalContext
import android.content.Context
import android.net.Uri
import android.provider.OpenableColumns
import android.util.Base64
import java.io.InputStream
import androidx.compose.material3.ExperimentalMaterial3Api
import androidx.compose.material3.Icon
import androidx.compose.material3.IconButton
import androidx.compose.material3.OutlinedTextField
import androidx.compose.material3.OutlinedTextFieldDefaults
import androidx.compose.material3.Scaffold
import androidx.compose.material3.Text
import androidx.compose.material3.TopAppBar
import androidx.compose.material3.TopAppBarDefaults
import androidx.compose.runtime.Composable
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.collectAsState
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.setValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.draw.drawWithContent
import androidx.compose.ui.graphics.Brush
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import androidx.lifecycle.viewmodel.compose.viewModel
import de.meinseelenfunke.app.data.api.ChatMessage
import de.meinseelenfunke.app.ui.theme.Cyan500
import de.meinseelenfunke.app.ui.theme.Emerald500
import de.meinseelenfunke.app.ui.theme.GlassWhite10
import de.meinseelenfunke.app.ui.theme.GlassWhite20
import de.meinseelenfunke.app.ui.theme.Rose500
import de.meinseelenfunke.app.ui.theme.Slate400
import de.meinseelenfunke.app.ui.theme.Slate50
import de.meinseelenfunke.app.ui.theme.Slate800
import de.meinseelenfunke.app.ui.theme.Slate900

fun parseAgentColor(colorName: String?): Color {
    return when (colorName) {
        "sky-500", "cyan-500" -> Cyan500
        "emerald-500", "teal-500" -> Emerald500
        "indigo-500", "blue-500" -> Color(0xFF6366F1)
        "purple-500" -> Color(0xFFA855F7)
        "amber-500", "yellow-500" -> Color(0xFFF59E0B)
        "red-500" -> Rose500
        "orange-500" -> Color(0xFFF97316)
        else -> Cyan500
    }
}

@OptIn(ExperimentalMaterial3Api::class)
@Composable
fun ChatScreen(
    onLogout: () -> Unit,
    viewModel: ChatViewModel = viewModel()
) {
    val messages by viewModel.messages.collectAsState()
    val isLoading by viewModel.isLoading.collectAsState()
    val error by viewModel.error.collectAsState()
    val agents by viewModel.agents.collectAsState()
    val selectedAgent by viewModel.selectedAgent.collectAsState()

    var textInput by remember { mutableStateOf("") }
    val listState = rememberLazyListState()

    val activeAgentName = selectedAgent?.name ?: "Funkira"
    val activeAgentColor = parseAgentColor(selectedAgent?.color)

    // Auto-scroll to the bottom when new messages arrive
    LaunchedEffect(messages.size) {
        if (messages.isNotEmpty()) {
            listState.animateScrollToItem(messages.size - 1)
        }
    }

    Scaffold(
        // topBar has been removed to give Chat more space, agent description and title are removed
    ) { innerPadding ->
        Column(
            modifier = Modifier
                .fillMaxSize()
                .padding(innerPadding)
                .background(
                    Brush.verticalGradient(
                        colors = listOf(Slate900, Color(0xFF020617))
                    )
                )
        ) {
            // Horizontal Agent Selector
            if (agents.isNotEmpty()) {
                Box(
                    modifier = Modifier
                        .fillMaxWidth()
                        .graphicsLayer(compositingStrategy = CompositingStrategy.Offscreen)
                        .drawWithContent {
                            drawContent()
                            // Fade out left edge
                            drawRect(
                                brush = Brush.horizontalGradient(
                                    colors = listOf(Color.Transparent, Color.Black),
                                    startX = 0f,
                                    endX = 24.dp.toPx()
                                ),
                                blendMode = BlendMode.DstIn
                            )
                            // Fade out right edge
                            drawRect(
                                brush = Brush.horizontalGradient(
                                    colors = listOf(Color.Black, Color.Transparent),
                                    startX = size.width - 24.dp.toPx(),
                                    endX = size.width
                                ),
                                blendMode = BlendMode.DstIn
                            )
                        }
                ) {
                    LazyRow(
                        modifier = Modifier
                            .fillMaxWidth()
                            .padding(vertical = 12.dp),
                        horizontalArrangement = Arrangement.spacedBy(14.dp),
                        verticalAlignment = Alignment.CenterVertically
                    ) {
                        items(agents) { agent ->
                            val isSelected = agent.id == selectedAgent?.id
                            val agentColor = parseAgentColor(agent.color)

                            Column(
                                horizontalAlignment = Alignment.CenterHorizontally,
                                modifier = Modifier
                                    .clickable { viewModel.selectAgent(agent) }
                            ) {
                                Box(
                                    modifier = Modifier
                                        .size(48.dp)
                                        .clip(CircleShape)
                                        .background(if (isSelected) agentColor else GlassWhite10)
                                        .border(
                                            width = if (isSelected) 2.dp else 1.dp,
                                            color = if (isSelected) Color.White else agentColor.copy(alpha = 0.5f),
                                            shape = CircleShape
                                        ),
                                    contentAlignment = Alignment.Center
                                ) {
                                    Text(
                                        text = agent.name.take(2).uppercase(),
                                        color = if (isSelected) Slate900 else Slate50,
                                        fontSize = 14.sp,
                                        fontWeight = FontWeight.Bold
                                    )
                                }

                                Spacer(modifier = Modifier.height(4.dp))

                                Text(
                                    text = agent.name,
                                    color = if (isSelected) Slate50 else Slate400,
                                    fontSize = 10.sp,
                                    fontWeight = if (isSelected) FontWeight.Bold else FontWeight.Normal
                                )
                            }
                        }
                    }
                }
            }

            // Chat message list
            LazyColumn(
                state = listState,
                modifier = Modifier
                    .weight(1f)
                    .fillMaxWidth()
                    .padding(horizontal = 16.dp),
                horizontalAlignment = Alignment.CenterHorizontally
            ) {
                item { Spacer(modifier = Modifier.height(16.dp)) }

                items(messages) { message ->
                    ChatMessageRow(message, activeAgentName, activeAgentColor)
                }

                if (isLoading) {
                    item {
                        Box(
                            modifier = Modifier
                                .fillMaxWidth()
                                .padding(vertical = 8.dp),
                            contentAlignment = Alignment.CenterStart
                        ) {
                            Text(
                                text = "$activeAgentName tippt...",
                                fontSize = 13.sp,
                                color = Emerald500,
                                modifier = Modifier.padding(start = 12.dp)
                            )
                        }
                    }
                }

                error?.let {
                    item {
                        Text(
                            text = it,
                            color = Rose500,
                            fontSize = 13.sp,
                            modifier = Modifier.padding(vertical = 8.dp)
                        )
                    }
                }
            }

            // Attachment Preview Banner
            val selectedFile by viewModel.selectedFile.collectAsState()
            selectedFile?.let { file ->
                Row(
                    modifier = Modifier
                        .fillMaxWidth()
                        .background(Slate800.copy(alpha = 0.9f))
                        .padding(horizontal = 16.dp, vertical = 8.dp)
                        .border(1.dp, activeAgentColor.copy(alpha = 0.3f), RoundedCornerShape(8.dp))
                        .padding(8.dp),
                    verticalAlignment = Alignment.CenterVertically,
                    horizontalArrangement = Arrangement.SpaceBetween
                ) {
                    Row(
                        verticalAlignment = Alignment.CenterVertically,
                        modifier = Modifier.weight(1f)
                    ) {
                        Icon(
                            imageVector = Icons.Default.PushPin,
                            contentDescription = "Datei angehängt",
                            tint = activeAgentColor,
                            modifier = Modifier.size(20.dp)
                        )
                        Spacer(modifier = Modifier.width(8.dp))
                        Column {
                            Text(
                                text = file.name,
                                color = Slate50,
                                fontSize = 13.sp,
                                fontWeight = FontWeight.Bold,
                                maxLines = 1
                            )
                            Text(
                                text = "${file.mimeType} • ${(file.sizeBytes / 1024.0).toInt()} KB",
                                color = Slate400,
                                fontSize = 11.sp
                            )
                        }
                    }
                    IconButton(
                        onClick = { viewModel.selectFile(null) },
                        modifier = Modifier.size(24.dp)
                    ) {
                        Icon(
                            imageVector = Icons.Default.Close,
                            contentDescription = "Entfernen",
                            tint = Rose500,
                            modifier = Modifier.size(16.dp)
                        )
                    }
                }
            }

            // Input Row
            Row(
                modifier = Modifier
                    .fillMaxWidth()
                    .background(Slate800)
                    .padding(horizontal = 12.dp, vertical = 8.dp),
                verticalAlignment = Alignment.CenterVertically
            ) {
                val context = LocalContext.current
                val filePickerLauncher = rememberLauncherForActivityResult(
                    contract = ActivityResultContracts.GetContent()
                ) { uri ->
                    uri?.let {
                        val file = getSelectedFileFromUri(context, it)
                        viewModel.selectFile(file)
                    }
                }

                IconButton(
                    onClick = { filePickerLauncher.launch("*/*") }
                ) {
                    Icon(
                        imageVector = Icons.Default.PushPin,
                        contentDescription = "Datei anhängen",
                        tint = activeAgentColor
                    )
                }

                OutlinedTextField(
                    value = textInput,
                    onValueChange = { textInput = it },
                    placeholder = { Text("Schreibe...") },
                    modifier = Modifier.weight(1f),
                    colors = OutlinedTextFieldDefaults.colors(
                        focusedBorderColor = activeAgentColor,
                        unfocusedBorderColor = Color.Transparent,
                        focusedTextColor = Slate50,
                        unfocusedTextColor = Slate50,
                        focusedPlaceholderColor = Slate400,
                        unfocusedPlaceholderColor = Slate400
                    ),
                    maxLines = 4,
                    shape = RoundedCornerShape(24.dp)
                )

                Spacer(modifier = Modifier.width(8.dp))

                IconButton(
                    onClick = {
                        if (textInput.isNotBlank() || selectedFile != null) {
                            viewModel.sendMessage(textInput)
                            textInput = ""
                        }
                    },
                    modifier = Modifier
                        .background(activeAgentColor, shape = RoundedCornerShape(50))
                        .padding(8.dp)
                ) {
                    Icon(
                        imageVector = Icons.Default.Send,
                        contentDescription = "Senden",
                        tint = Slate900
                    )
                }
            }
        }
    }
}

@Composable
fun ChatMessageRow(message: ChatMessage, activeAgentName: String, activeAgentColor: Color) {
    val isUser = message.role == "user"
    val bubbleColor = if (isUser) activeAgentColor else GlassWhite10
    val textColor = if (isUser) Slate900 else Slate50
    val alignment = if (isUser) Alignment.End else Alignment.Start

    Column(
        modifier = Modifier
            .fillMaxWidth()
            .padding(vertical = 4.dp),
        horizontalAlignment = alignment
    ) {
        Box(
            modifier = Modifier
                .clip(
                    RoundedCornerShape(
                        topStart = 16.dp,
                        topEnd = 16.dp,
                        bottomStart = if (isUser) 16.dp else 4.dp,
                        bottomEnd = if (isUser) 4.dp else 16.dp
                    )
                )
                .background(bubbleColor)
                .padding(horizontal = 16.dp, vertical = 10.dp)
        ) {
            Text(
                text = message.content,
                color = textColor,
                fontSize = 15.sp,
                lineHeight = 20.sp
            )
        }

        Text(
            text = if (isUser) "Du" else activeAgentName,
            fontSize = 10.sp,
            color = Slate400,
            modifier = Modifier.padding(top = 2.dp, start = 4.dp, end = 4.dp)
        )
    }
}

fun getSelectedFileFromUri(context: Context, uri: Uri): SelectedFile? {
    val contentResolver = context.contentResolver
    val mimeType = contentResolver.getType(uri) ?: "application/octet-stream"
    var name = "attachment"
    var size = 0L

    contentResolver.query(uri, null, null, null, null)?.use { cursor ->
        if (cursor.moveToFirst()) {
            val nameIndex = cursor.getColumnIndex(OpenableColumns.DISPLAY_NAME)
            if (nameIndex != -1) {
                name = cursor.getString(nameIndex)
            }
            val sizeIndex = cursor.getColumnIndex(OpenableColumns.SIZE)
            if (sizeIndex != -1) {
                size = cursor.getLong(sizeIndex)
            }
        }
    }

    return try {
        val inputStream: InputStream? = contentResolver.openInputStream(uri)
        if (inputStream != null) {
            val bytes = inputStream.readBytes()
            inputStream.close()
            
            val base64Content = if (mimeType.startsWith("image/")) {
                Base64.encodeToString(bytes, Base64.NO_WRAP)
            } else {
                null
            }

            val textContent = if (!mimeType.startsWith("image/") && (mimeType.startsWith("text/") || name.endsWith(".txt") || name.endsWith(".json") || name.endsWith(".csv") || name.endsWith(".xml") || name.endsWith(".md") || name.endsWith(".log") || name.endsWith(".js") || name.endsWith(".ts") || name.endsWith(".html") || name.endsWith(".css") || name.endsWith(".yaml") || name.endsWith(".yml"))) {
                String(bytes, Charsets.UTF_8)
            } else {
                null
            }

            SelectedFile(
                uriString = uri.toString(),
                name = name,
                mimeType = mimeType,
                sizeBytes = size,
                base64Content = base64Content,
                textContent = textContent
            )
        } else {
            null
        }
    } catch (e: Exception) {
        e.printStackTrace()
        null
    }
}
