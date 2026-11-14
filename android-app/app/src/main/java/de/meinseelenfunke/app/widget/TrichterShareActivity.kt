package de.meinseelenfunke.app.widget

import android.content.Intent
import android.net.Uri
import android.os.Build
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
import java.io.File

/**
 * Dedicated Android Share Target ("In Trichter senden") for PDFs & images from Mail, WhatsApp,
 * Browser, or Scanner apps. Keeps QuickEntryWidgetActivity exported="false" and rejects any
 * internal/private file:// or own-package content:// URIs against confused-deputy attacks.
 */
class TrichterShareActivity : ComponentActivity() {

    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)
        if (!de.meinseelenfunke.app.util.SecurityIntentHelper.guardWidgetDialogActivity(this)) return

        val sharedUris = extractSafeExternalUris(intent)

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
                            val raw = err.localizedMessage ?: "Fehler beim Senden"
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
                                    text = "EINGANG-TRICHTER",
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
                                initialTrichterMode = true,
                                initialSharedUris = sharedUris,
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
                                        source = "android_share"
                                    )
                                },
                                onSendMultipleToTrichter = { items, customTitle ->
                                    viewModel.sendMultipleToMiniPcTrichter(
                                        items = items,
                                        customTitle = customTitle,
                                        source = "android_share"
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

    private fun extractSafeExternalUris(incoming: Intent?): List<Uri> {
        if (incoming == null) return emptyList()
        val raw = mutableListOf<Uri>()
        try {
            when (incoming.action) {
                Intent.ACTION_SEND -> {
                    val single = if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.TIRAMISU) {
                        incoming.getParcelableExtra(Intent.EXTRA_STREAM, Uri::class.java)
                    } else {
                        @Suppress("DEPRECATION")
                        incoming.getParcelableExtra(Intent.EXTRA_STREAM)
                    }
                    if (single != null) raw.add(single)
                }
                Intent.ACTION_SEND_MULTIPLE -> {
                    val list = if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.TIRAMISU) {
                        incoming.getParcelableArrayListExtra(Intent.EXTRA_STREAM, Uri::class.java)
                    } else {
                        @Suppress("DEPRECATION")
                        incoming.getParcelableArrayListExtra(Intent.EXTRA_STREAM)
                    }
                    if (list != null) raw.addAll(list)
                }
            }
        } catch (_: Exception) {
            return emptyList()
        }

        val appDataCanonical = try { File(applicationInfo.dataDir).canonicalPath } catch (_: Exception) { "/data/data/$packageName" }
        return filterSafeExternalUris(raw, packageName, appDataCanonical)
    }

    companion object {
        fun isSafeExternalShareUri(
            uri: Uri?,
            ownPackageName: String,
            appDataCanonicalPath: String = "/data/data/$ownPackageName"
        ): Boolean {
            if (uri == null) return false
            val scheme = uri.scheme?.lowercase() ?: return false
            if (scheme != "content") return false
            val pkgLower = ownPackageName.lowercase()
            val auth = uri.authority?.lowercase() ?: return false
            if (auth.isBlank() || auth == pkgLower || auth.startsWith("$pkgLower.")) return false
            val path = uri.path ?: ""
            if (path.contains("..")) return false
            if (appDataCanonicalPath.isNotBlank() && path.contains(appDataCanonicalPath, ignoreCase = true)) return false
            if (path.contains("/data/data/$pkgLower", ignoreCase = true) ||
                path.contains("/data/user/", ignoreCase = true)
            ) {
                return false
            }
            return true
        }

        fun filterSafeExternalUris(
            uris: List<Uri>,
            ownPackageName: String,
            appDataCanonicalPath: String = "/data/data/$ownPackageName"
        ): List<Uri> {
            return uris.filter { isSafeExternalShareUri(it, ownPackageName, appDataCanonicalPath) }.take(20)
        }
    }
}
