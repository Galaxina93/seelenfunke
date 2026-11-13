package de.meinseelenfunke.app.widget

import android.os.Bundle
import android.widget.Toast
import androidx.activity.ComponentActivity
import androidx.activity.compose.setContent
import androidx.compose.foundation.background
import androidx.compose.foundation.border
import androidx.compose.foundation.clickable
import androidx.compose.foundation.interaction.MutableInteractionSource
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.heightIn
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.foundation.verticalScroll
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.Close
import androidx.compose.material3.Card
import androidx.compose.material3.CardDefaults
import androidx.compose.material3.Icon
import androidx.compose.material3.IconButton
import androidx.compose.material3.Text
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.collectAsState
import androidx.compose.runtime.getValue
import androidx.compose.runtime.remember
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import androidx.lifecycle.viewmodel.compose.viewModel
import de.meinseelenfunke.app.ui.screens.FinanceViewModel
import de.meinseelenfunke.app.ui.screens.QuickEntryForm
import de.meinseelenfunke.app.ui.theme.Gold
import de.meinseelenfunke.app.ui.theme.SeelenfunkeTheme
import de.meinseelenfunke.app.ui.theme.SpaceBlack

/**
 * Homescreen Popup Dialog for "Beleg erfassen & Schnelleintrag"
 * (AI Receipt Camera Scanner + Manual Quick Expense Entry).
 */
class QuickEntryWidgetActivity : ComponentActivity() {

    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)
        if (!de.meinseelenfunke.app.util.SecurityIntentHelper.guardWidgetDialogActivity(this)) return

        setContent {
            SeelenfunkeTheme(darkTheme = true) {
                val viewModel: FinanceViewModel = viewModel()
                val categories by viewModel.categories.collectAsState()
                val isUploading by viewModel.isUploading.collectAsState()
                val isAiScanning by viewModel.isAiScanning.collectAsState()
                val actionResult by viewModel.actionResult.collectAsState()
                val trichterFeedback by viewModel.trichterFeedback.collectAsState()
                val context = LocalContext.current

                LaunchedEffect(actionResult) {
                    actionResult?.let { result ->
                        result.onSuccess { msg ->
                            val toastMsg = if (msg.startsWith("✅")) msg else "✅ $msg"
                            Toast.makeText(context, toastMsg, Toast.LENGTH_LONG).show()
                            viewModel.clearActionResult()
                            kotlinx.coroutines.delay(650)
                            finish()
                        }.onFailure { err ->
                            val raw = err.localizedMessage ?: "Fehler beim Speichern"
                            val toastMsg = if (raw.startsWith("⚠️")) raw else "⚠️ $raw"
                            Toast.makeText(
                                context,
                                toastMsg,
                                Toast.LENGTH_LONG
                            ).show()
                            viewModel.clearActionResult()
                        }
                    }
                }

                Box(
                    modifier = Modifier
                        .fillMaxSize()
                        .background(Color.Black.copy(alpha = 0.65f))
                        .clickable(
                            interactionSource = remember { MutableInteractionSource() },
                            indication = null
                        ) {
                            if (!isUploading && !isAiScanning) finish()
                        }
                        .padding(horizontal = 16.dp, vertical = 24.dp),
                    contentAlignment = Alignment.Center
                ) {
                    Card(
                        modifier = Modifier
                            .fillMaxWidth()
                            .heightIn(max = 640.dp)
                            .border(1.5.dp, Gold.copy(alpha = 0.55f), RoundedCornerShape(20.dp))
                            .clickable(
                                interactionSource = remember { MutableInteractionSource() },
                                indication = null
                            ) {},
                        shape = RoundedCornerShape(20.dp),
                        colors = CardDefaults.cardColors(containerColor = SpaceBlack)
                    ) {
                        Column(
                            modifier = Modifier
                                .fillMaxWidth()
                                .verticalScroll(rememberScrollState())
                                .padding(16.dp)
                        ) {
                            Row(
                                modifier = Modifier
                                    .fillMaxWidth()
                                    .padding(bottom = 8.dp),
                                horizontalArrangement = Arrangement.SpaceBetween,
                                verticalAlignment = Alignment.CenterVertically
                            ) {
                                Text(
                                    text = "BELEG & SCHNELLEINTRAG",
                                    color = Gold,
                                    fontSize = 15.sp,
                                    fontWeight = FontWeight.Bold,
                                    letterSpacing = 1.sp
                                )
                                IconButton(
                                    onClick = { if (!isUploading && !isAiScanning) finish() },
                                    modifier = Modifier.size(32.dp)
                                ) {
                                    Icon(
                                        imageVector = Icons.Default.Close,
                                        contentDescription = "Schließen",
                                        tint = Color(0xFF94A3B8)
                                    )
                                }
                            }

                            QuickEntryForm(
                                categories = categories,
                                isUploading = isUploading,
                                isAiScanning = isAiScanning,
                                showCard = false,
                                showTitle = false,
                                trichterFeedback = trichterFeedback,
                                onClearTrichterFeedback = { viewModel.clearTrichterFeedback() },
                                onDismiss = { finish() },
                                onScanReceipt = { bytes, fileName, mime, autoSave, onResult ->
                                    viewModel.scanReceipt(bytes, fileName, mime, autoSave) { scanData ->
                                        onResult(scanData)
                                    }
                                },
                                onSendToTrichter = { bytes, name, mime, customTitle ->
                                    viewModel.sendToMiniPcTrichter(
                                        fileBytes = bytes,
                                        fileName = name,
                                        mimeType = mime,
                                        customTitle = customTitle,
                                        source = "android_widget"
                                    )
                                },
                                onSendMultipleToTrichter = { items, customTitle ->
                                    viewModel.sendMultipleToMiniPcTrichter(
                                        items = items,
                                        customTitle = customTitle,
                                        source = "android_widget"
                                    )
                                },
                                onSubmit = { title, amt, cat, isBiz, date, loc, bytes, name, mime ->
                                    viewModel.submitQuickEntry(
                                        title = title,
                                        amount = amt,
                                        category = cat,
                                        isBusiness = isBiz,
                                        date = date,
                                        location = loc,
                                        fileBytes = bytes,
                                        fileName = name,
                                        mimeType = mime
                                    )
                                }
                            )
                        }
                    }
                }
            }
        }
    }
}
