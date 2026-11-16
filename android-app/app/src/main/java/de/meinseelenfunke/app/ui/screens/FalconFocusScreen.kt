package de.meinseelenfunke.app.ui.screens

import android.Manifest
import android.annotation.SuppressLint
import android.app.Activity
import android.content.ActivityNotFoundException
import android.content.Intent
import android.content.pm.PackageManager
import android.net.Uri
import android.speech.RecognizerIntent
import android.view.ViewGroup
import android.webkit.WebResourceRequest
import android.webkit.WebResourceResponse
import android.webkit.WebSettings
import android.webkit.WebView
import android.webkit.WebViewClient
import android.widget.Toast
import androidx.activity.compose.rememberLauncherForActivityResult
import androidx.activity.result.contract.ActivityResultContracts
import androidx.compose.animation.AnimatedVisibility
import androidx.compose.animation.core.FastOutSlowInEasing
import androidx.compose.animation.core.RepeatMode
import androidx.compose.animation.core.animateFloat
import androidx.compose.animation.core.infiniteRepeatable
import androidx.compose.animation.core.rememberInfiniteTransition
import androidx.compose.animation.core.tween
import androidx.compose.animation.fadeIn
import androidx.compose.animation.fadeOut
import androidx.compose.foundation.ExperimentalFoundationApi
import androidx.compose.foundation.Image
import androidx.compose.foundation.background
import androidx.compose.foundation.border
import androidx.compose.foundation.clickable
import androidx.compose.foundation.combinedClickable
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.PaddingValues
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.Spacer
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.imePadding
import androidx.compose.foundation.layout.navigationBarsPadding
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.layout.statusBarsPadding
import androidx.compose.foundation.layout.width
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.Bolt
import androidx.compose.material.icons.filled.CallEnd
import androidx.compose.material.icons.filled.Cameraswitch
import androidx.compose.material.icons.filled.Close
import androidx.compose.material.icons.filled.GridView
import androidx.compose.material.icons.filled.Image
import androidx.compose.material.icons.filled.Mic
import androidx.compose.material.icons.filled.Pause
import androidx.compose.material.icons.filled.PhotoCamera
import androidx.compose.material.icons.filled.SkipNext
import androidx.compose.material.icons.filled.Videocam
import androidx.compose.material.icons.filled.VideocamOff
import androidx.compose.material3.Button
import androidx.compose.material3.ButtonDefaults
import androidx.compose.material3.Card
import androidx.compose.material3.CardDefaults
import androidx.compose.material3.CircularProgressIndicator
import androidx.compose.material3.ExperimentalMaterial3Api
import androidx.compose.material3.Icon
import androidx.compose.material3.ModalBottomSheet
import androidx.compose.material3.OutlinedTextField
import androidx.compose.material3.OutlinedTextFieldDefaults
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.DisposableEffect
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.collectAsState
import androidx.compose.runtime.getValue
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
import androidx.compose.ui.layout.ContentScale
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.platform.LocalLifecycleOwner
import androidx.compose.ui.text.TextStyle
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.style.TextOverflow
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import androidx.compose.ui.viewinterop.AndroidView
import androidx.core.content.ContextCompat
import androidx.lifecycle.Lifecycle
import androidx.lifecycle.LifecycleEventObserver
import androidx.lifecycle.viewmodel.compose.viewModel
import de.meinseelenfunke.app.R
import de.meinseelenfunke.app.ui.components.AgDirectInboxSheet
import de.meinseelenfunke.app.ui.components.AgImageLightboxDialog
import de.meinseelenfunke.app.ui.theme.AppTheme
import de.meinseelenfunke.app.ui.theme.Emerald500
import de.meinseelenfunke.app.ui.theme.Gold
import de.meinseelenfunke.app.ui.theme.Rose500
import de.meinseelenfunke.app.ui.theme.SpaceBlack
import de.meinseelenfunke.app.util.AgDirectInboxManager
import de.meinseelenfunke.app.util.AgDirectStatus
import de.meinseelenfunke.app.util.FalconCameraController
import de.meinseelenfunke.app.util.FalconClientTools
import de.meinseelenfunke.app.util.MusicPlayerManager
import de.meinseelenfunke.app.util.NavigationBridge
import de.meinseelenfunke.app.util.PendingCameraAction
import de.meinseelenfunke.app.util.SoundManager
import kotlinx.coroutines.delay
import kotlinx.coroutines.launch
import java.io.ByteArrayInputStream

private const val FALCON_ASSET_BASE = "file:///android_asset/falcon3d/"
private const val FALCON_ASSET_INDEX = "file:///android_asset/falcon3d/index.html"

fun isAllowedFalconAssetUrl(rawUrl: String?): Boolean {
    if (rawUrl.isNullOrBlank()) return false
    val trimmed = rawUrl.trim()
    if (!trimmed.startsWith(FALCON_ASSET_BASE)) return false
    val decoded = try { Uri.decode(trimmed) } catch (_: Throwable) { return false }
    if (decoded.contains("..") || decoded.contains('\\')) return false
    return decoded.startsWith(FALCON_ASSET_BASE)
}

@OptIn(ExperimentalFoundationApi::class, ExperimentalMaterial3Api::class)
@SuppressLint("SetJavaScriptEnabled")
@Composable
fun FalconFocusScreen(
    onOpenDashboard: () -> Unit,
    autoStartVoice: Boolean = false,
    onConsumedAutoStartVoice: () -> Unit = {},
    viewModel: FunkiraLiveViewModel = viewModel()
) {
    val context = LocalContext.current
    val lifecycleOwner = LocalLifecycleOwner.current
    val scope = rememberCoroutineScope()
    val isDark = AppTheme.colors.isDark

    val isConnecting by viewModel.isConnecting.collectAsState()
    val isConnected by viewModel.isConnected.collectAsState()
    val isRecording by viewModel.isRecording.collectAsState()
    val isAiSpeaking by viewModel.isAiSpeakingFlow.collectAsState()
    val aiSpeechAmp by viewModel.aiSpeechAmplitude.collectAsState()
    val userVoiceAmp by viewModel.voiceAmplitude.collectAsState()
    val falconEmotion by viewModel.falconEmotion.collectAsState()
    val liveSubtitle by viewModel.liveSubtitle.collectAsState()
    val errorText by viewModel.error.collectAsState()

    val isVisionActive by FalconCameraController.isVisionActive.collectAsState()
    val previewBitmap by FalconCameraController.previewBitmap.collectAsState()
    val isCapturingPhoto by FalconCameraController.isCapturingPhoto.collectAsState()
    val lastCapturedPhoto by FalconCameraController.lastCapturedPhoto.collectAsState()

    val isMusicPlaying by MusicPlayerManager.isPlaying.collectAsState()
    val agOpenCount by AgDirectInboxManager.openCountFlow.collectAsState()
    val agItems by AgDirectInboxManager.itemsFlow.collectAsState()
    val pendingBlockades by AgDirectInboxManager.pendingBlockadesFlow.collectAsState()
    val agGoCount = remember(agItems, pendingBlockades) {
        pendingBlockades.size
    }

    var showDirectInboxSheet by remember { mutableStateOf(false) }
    var focusInboxItemId by remember { mutableStateOf<String?>(null) }
    var blockadeCardSnoozed by remember { mutableStateOf(false) }
    var showInlineBlockadeReply by remember { mutableStateOf(false) }
    var inlineBlockadeReplyText by remember { mutableStateOf("") }
    var isResolvingBlockade by remember { mutableStateOf(false) }
    var showPhotoLightbox by remember { mutableStateOf(false) }
    var showCameraSourceMenu by remember { mutableStateOf(false) }
    var dismissedSubtitleText by remember { mutableStateOf<String?>(null) }
    var pendingCameraAction by remember { mutableStateOf<PendingCameraAction?>(null) }
    var webViewRef by remember { mutableStateOf<WebView?>(null) }
    var pageLoaded by remember { mutableStateOf(false) }
    var hasPromptedWakeWordMic by androidx.compose.runtime.saveable.rememberSaveable { mutableStateOf(false) }

    val wakeWordMicLauncher = rememberLauncherForActivityResult(
        contract = ActivityResultContracts.RequestPermission()
    ) { granted ->
        if (granted) {
            de.meinseelenfunke.app.services.WakeWordService.syncServiceState(context)
        }
    }

    val micPermissionLauncher = rememberLauncherForActivityResult(
        contract = ActivityResultContracts.RequestPermission()
    ) { granted ->
        if (granted) {
            de.meinseelenfunke.app.services.WakeWordService.syncServiceState(context)
            SoundManager.playSound(R.raw.open_project_brain)
            viewModel.startLiveChat()
        } else {
            Toast.makeText(context, "Mikrofon-Berechtigung benötigt.", Toast.LENGTH_SHORT).show()
        }
    }

    val cameraPermissionLauncher = rememberLauncherForActivityResult(
        contract = ActivityResultContracts.RequestPermission()
    ) { granted ->
        val actionToRun = pendingCameraAction
        pendingCameraAction = null
        if (granted && actionToRun != null) {
            scope.launch {
                when (actionToRun.action) {
                    "take_photo" -> FalconCameraController.captureHighResPhoto(context, actionToRun.lens)
                    "switch_camera" -> FalconCameraController.switchCameraAsync(context, actionToRun.lens)
                    else -> FalconCameraController.startVision(context, actionToRun.lens ?: "back")
                }
            }
        } else if (!granted) {
            Toast.makeText(context, "Kamera-Berechtigung nicht erteilt.", Toast.LENGTH_SHORT).show()
        }
    }

    val imagePickerLauncher = rememberLauncherForActivityResult(
        contract = ActivityResultContracts.GetContent()
    ) { uri: Uri? ->
        uri?.let {
            scope.launch {
                val imported = FalconCameraController.importPhotoFromUri(context, it)
                if (imported != null) {
                    showDirectInboxSheet = true
                }
            }
        }
    }

    val blockadeSpeechLauncher = rememberLauncherForActivityResult(
        contract = ActivityResultContracts.StartActivityForResult()
    ) { result ->
        if (result.resultCode == Activity.RESULT_OK) {
            val spoken = result.data
                ?.getStringArrayListExtra(RecognizerIntent.EXTRA_RESULTS)
                ?.firstOrNull()
                ?.trim()
            if (!spoken.isNullOrEmpty()) {
                showInlineBlockadeReply = true
                inlineBlockadeReplyText = if (inlineBlockadeReplyText.isBlank()) {
                    spoken
                } else {
                    "${inlineBlockadeReplyText.trimEnd()} $spoken"
                }
            }
        }
    }

    // Beharrlichkeits-Mechanik: Wenn Alina "Später" tippt, blendet sich die Warnkarte nach 90 Sekunden
    // (sowie bei jedem ON_RESUME) automatisch wieder ein, solange die Blockade noch offen ist!
    LaunchedEffect(blockadeCardSnoozed, pendingBlockades.size) {
        if (pendingBlockades.isEmpty()) {
            blockadeCardSnoozed = false
            showInlineBlockadeReply = false
            inlineBlockadeReplyText = ""
        } else if (blockadeCardSnoozed) {
            delay(90_000L)
            if (AgDirectInboxManager.pendingBlockadesFlow.value.isNotEmpty()) {
                blockadeCardSnoozed = false
            }
        }
    }

    LaunchedEffect(Unit) {
        AgDirectInboxManager.hydrateLocalState(context)
        AgDirectInboxManager.refreshAsync(context)
        MusicPlayerManager.ensureCatalogLoadedFast(context)
        if (de.meinseelenfunke.app.di.ServiceLocator.isWakeWordEnabled()) {
            val hasMic = ContextCompat.checkSelfPermission(
                context,
                Manifest.permission.RECORD_AUDIO
            ) == PackageManager.PERMISSION_GRANTED
            if (hasMic) {
                de.meinseelenfunke.app.services.WakeWordService.syncServiceState(context)
            } else if (!autoStartVoice &&
                !hasPromptedWakeWordMic &&
                !de.meinseelenfunke.app.util.PhoneConciergeManager.isEnabled(context)
            ) {
                hasPromptedWakeWordMic = true
                wakeWordMicLauncher.launch(Manifest.permission.RECORD_AUDIO)
            }
        }
    }

    LaunchedEffect(Unit) {
        FalconCameraController.permissionRequestEvents.collect { req ->
            pendingCameraAction = req
            cameraPermissionLauncher.launch(Manifest.permission.CAMERA)
        }
    }

    LaunchedEffect(Unit) {
        NavigationBridge.falconExitFocusTrigger.collect {
            FalconCameraController.stopCamera()
            onOpenDashboard()
        }
    }

    LaunchedEffect(autoStartVoice) {
        if (autoStartVoice) {
            onConsumedAutoStartVoice()
            val hasMic = ContextCompat.checkSelfPermission(
                context,
                Manifest.permission.RECORD_AUDIO
            ) == PackageManager.PERMISSION_GRANTED
            if (hasMic) {
                viewModel.startLiveChat()
            } else {
                micPermissionLauncher.launch(Manifest.permission.RECORD_AUDIO)
            }
        }
    }

    LaunchedEffect(errorText) {
        if (!errorText.isNullOrBlank()) {
            Toast.makeText(context, errorText, Toast.LENGTH_SHORT).show()
        }
    }

    DisposableEffect(lifecycleOwner) {
        val observer = LifecycleEventObserver { _, event ->
            when (event) {
                Lifecycle.Event.ON_PAUSE -> {
                    webViewRef?.onPause()
                    if (!isConnected) {
                        FalconCameraController.stopCamera()
                    }
                }
                Lifecycle.Event.ON_RESUME -> {
                    webViewRef?.onResume()
                    blockadeCardSnoozed = false
                    AgDirectInboxManager.refreshFromServer(context)
                }
                else -> {}
            }
        }
        lifecycleOwner.lifecycle.addObserver(observer)
        onDispose {
            lifecycleOwner.lifecycle.removeObserver(observer)
            FalconCameraController.stopCamera()
            webViewRef?.let { wv ->
                try {
                    wv.stopLoading()
                    wv.loadUrl("about:blank")
                    wv.destroy()
                } catch (_: Throwable) {
                }
            }
            webViewRef = null
        }
    }

    // Stream 3D state smoothly into Three.js scene
    val mode3d = FalconClientTools.resolveFalcon3dMode(
        isConnecting = isConnecting,
        isConnected = isConnected,
        isAiSpeaking = isAiSpeaking,
        isRecording = isRecording
    )
    val effectiveAudioLevel = if (isAiSpeaking) {
        aiSpeechAmp
    } else if (isConnected && isRecording) {
        (userVoiceAmp * 0.6f).coerceIn(0f, 1f)
    } else {
        0f
    }

    LaunchedEffect(pageLoaded, mode3d, effectiveAudioLevel, falconEmotion, isDark, isVisionActive) {
        val wv = webViewRef ?: return@LaunchedEffect
        if (!pageLoaded) return@LaunchedEffect
        val json = FalconClientTools.buildFalconSceneStateJson(
            mode = mode3d,
            audioLevel = effectiveAudioLevel,
            emotion = falconEmotion,
            isDark = isDark,
            cameraActive = isVisionActive
        )
        wv.evaluateJavascript("window.FalconScene && window.FalconScene.updateState($json);", null)
        if (isAiSpeaking) {
            delay(45L)
        }
    }

    if (showPhotoLightbox && lastCapturedPhoto?.takeIf { !it.attachedToInbox } != null) {
        val snap = lastCapturedPhoto!!
        AgImageLightboxDialog(
            bitmap = snap.previewBitmap,
            title = snap.fileName,
            onDismiss = { showPhotoLightbox = false },
            onSendToAntigravity = {
                showPhotoLightbox = false
                showDirectInboxSheet = true
            }
        )
    }

    if (showDirectInboxSheet) {
        val autoAttach = lastCapturedPhoto?.takeIf { !it.attachedToInbox }?.toPreparedAgAttachment()
        AgDirectInboxSheet(
            onDismiss = {
                showDirectInboxSheet = false
                focusInboxItemId = null
            },
            initialAttachment = autoAttach,
            initialExpandedItemId = focusInboxItemId,
            onAttachmentSubmitted = {
                FalconCameraController.clearLastCapturedPhoto()
            }
        )
    }

    if (showCameraSourceMenu) {
        ModalBottomSheet(
            onDismissRequest = { showCameraSourceMenu = false },
            containerColor = Color(0xFF10141E),
            contentColor = Color(0xFFF8FAFC),
            dragHandle = null,
            shape = RoundedCornerShape(topStart = 22.dp, topEnd = 22.dp)
        ) {
            Column(
                modifier = Modifier
                    .fillMaxWidth()
                    .navigationBarsPadding()
                    .padding(horizontal = 18.dp, vertical = 16.dp),
                verticalArrangement = Arrangement.spacedBy(8.dp)
            ) {
                // Option 1: Foto aufnehmen
                Row(
                    modifier = Modifier
                        .fillMaxWidth()
                        .clip(RoundedCornerShape(14.dp))
                        .background(Color(0x22FFFFFF))
                        .border(1.dp, Gold.copy(alpha = 0.40f), RoundedCornerShape(14.dp))
                        .clickable {
                            showCameraSourceMenu = false
                            if (FalconCameraController.hasCameraPermission(context)) {
                                scope.launch {
                                    FalconCameraController.captureHighResPhoto(context)
                                }
                            } else {
                                pendingCameraAction = PendingCameraAction("take_photo", "back")
                                cameraPermissionLauncher.launch(Manifest.permission.CAMERA)
                            }
                        }
                        .padding(horizontal = 16.dp, vertical = 14.dp),
                    verticalAlignment = Alignment.CenterVertically,
                    horizontalArrangement = Arrangement.spacedBy(14.dp)
                ) {
                    Box(
                        modifier = Modifier
                            .size(36.dp)
                            .clip(CircleShape)
                            .background(Gold.copy(alpha = 0.16f)),
                        contentAlignment = Alignment.Center
                    ) {
                        Icon(
                            imageVector = Icons.Default.PhotoCamera,
                            contentDescription = null,
                            tint = Gold,
                            modifier = Modifier.size(20.dp)
                        )
                    }
                    Text(
                        text = "Foto aufnehmen",
                        fontSize = 15.sp,
                        fontWeight = FontWeight.SemiBold,
                        color = Color(0xFFF8FAFC)
                    )
                }

                // Option 2: Bild wählen & an Antigravity senden
                Row(
                    modifier = Modifier
                        .fillMaxWidth()
                        .clip(RoundedCornerShape(14.dp))
                        .background(Color(0x22FFFFFF))
                        .border(1.dp, Gold.copy(alpha = 0.40f), RoundedCornerShape(14.dp))
                        .clickable {
                            showCameraSourceMenu = false
                            imagePickerLauncher.launch("image/*")
                        }
                        .padding(horizontal = 16.dp, vertical = 14.dp),
                    verticalAlignment = Alignment.CenterVertically,
                    horizontalArrangement = Arrangement.spacedBy(14.dp)
                ) {
                    Box(
                        modifier = Modifier
                            .size(36.dp)
                            .clip(CircleShape)
                            .background(Gold.copy(alpha = 0.16f)),
                        contentAlignment = Alignment.Center
                    ) {
                        Icon(
                            imageVector = Icons.Default.Image,
                            contentDescription = null,
                            tint = Gold,
                            modifier = Modifier.size(20.dp)
                        )
                    }
                    Text(
                        text = "Bild wählen & an Antigravity senden",
                        fontSize = 15.sp,
                        fontWeight = FontWeight.SemiBold,
                        color = Color(0xFFF8FAFC)
                    )
                }
            }
        }
    }

    val pulseTransition = rememberInfiniteTransition(label = "falconPulse")
    val pulseScale by pulseTransition.animateFloat(
        initialValue = 1f,
        targetValue = if (isConnected || isConnecting) 1.13f else 1.05f,
        animationSpec = infiniteRepeatable(
            animation = tween(if (isConnected) 900 else 1500, easing = FastOutSlowInEasing),
            repeatMode = RepeatMode.Reverse
        ),
        label = "falconPulseScale"
    )

    val stageBg = Color(0xFF080A10)
    val stageHudBg = Color(0xDE10141E)
    val stageHudText = Color(0xFFF8FAFC)
    val stageHudMuted = Color(0xFF94A3B8)

    Box(
        modifier = Modifier
            .fillMaxSize()
            .background(stageBg)
    ) {
        // 1. Fullscreen 60-FPS 3D Falcon Scene (Zero-Trust Hardened Local Asset WebView)
        AndroidView(
            modifier = Modifier.fillMaxSize(),
            factory = { ctx ->
                WebView(ctx).apply {
                    layoutParams = ViewGroup.LayoutParams(
                        ViewGroup.LayoutParams.MATCH_PARENT,
                        ViewGroup.LayoutParams.MATCH_PARENT
                    )
                    setBackgroundColor(android.graphics.Color.parseColor("#080A10"))
                    isVerticalScrollBarEnabled = false
                    isHorizontalScrollBarEnabled = false

                    settings.apply {
                        javaScriptEnabled = true
                        allowFileAccess = true
                        allowContentAccess = false
                        @Suppress("DEPRECATION")
                        allowFileAccessFromFileURLs = false
                        @Suppress("DEPRECATION")
                        allowUniversalAccessFromFileURLs = false
                        blockNetworkLoads = true
                        blockNetworkImage = true
                        domStorageEnabled = false
                        @Suppress("DEPRECATION")
                        databaseEnabled = false
                        @Suppress("DEPRECATION")
                        saveFormData = false
                        mixedContentMode = WebSettings.MIXED_CONTENT_NEVER_ALLOW
                        mediaPlaybackRequiresUserGesture = false
                        setSupportZoom(false)
                        builtInZoomControls = false
                        displayZoomControls = false
                    }

                    webViewClient = object : WebViewClient() {
                        override fun shouldOverrideUrlLoading(
                            view: WebView?,
                            request: WebResourceRequest?
                        ): Boolean {
                            val targetUrl = request?.url?.toString()
                            return !isAllowedFalconAssetUrl(targetUrl)
                        }

                        @Deprecated("Deprecated in Java")
                        override fun shouldOverrideUrlLoading(view: WebView?, url: String?): Boolean {
                            return !isAllowedFalconAssetUrl(url)
                        }

                        override fun shouldInterceptRequest(
                            view: WebView?,
                            request: WebResourceRequest?
                        ): WebResourceResponse? {
                            val reqUrl = request?.url?.toString()
                            if (!isAllowedFalconAssetUrl(reqUrl)) {
                                return WebResourceResponse(
                                    "text/plain",
                                    "UTF-8",
                                    403,
                                    "Forbidden",
                                    emptyMap(),
                                    ByteArrayInputStream(ByteArray(0))
                                )
                            }
                            return super.shouldInterceptRequest(view, request)
                        }

                        override fun onPageFinished(view: WebView?, url: String?) {
                            super.onPageFinished(view, url)
                            pageLoaded = true
                            val initJson = FalconClientTools.buildFalconSceneStateJson(
                                mode = mode3d,
                                audioLevel = effectiveAudioLevel,
                                emotion = falconEmotion,
                                isDark = isDark,
                                cameraActive = isVisionActive
                            )
                            view?.evaluateJavascript(
                                "window.FalconScene && window.FalconScene.updateState($initJson);",
                                null
                            )
                        }
                    }

                    loadUrl(FALCON_ASSET_INDEX)
                    webViewRef = this
                }
            }
        )

        // 2. Top Header Bar (Status Pill Left | Camera, Music Mini, Dashboard Right)
        Row(
            modifier = Modifier
                .fillMaxWidth()
                .statusBarsPadding()
                .padding(horizontal = 16.dp, vertical = 12.dp)
                .align(Alignment.TopCenter),
            horizontalArrangement = Arrangement.SpaceBetween,
            verticalAlignment = Alignment.CenterVertically
        ) {
            // Status Pill: Falcon · Mitgründer + Go Indikator
            Row(
                verticalAlignment = Alignment.CenterVertically,
                horizontalArrangement = Arrangement.spacedBy(8.dp)
            ) {
                Row(
                    modifier = Modifier
                        .clip(RoundedCornerShape(50))
                        .background(stageHudBg)
                        .border(
                            1.dp,
                            if (isConnected) Emerald500.copy(alpha = 0.65f) else Gold.copy(alpha = 0.55f),
                            RoundedCornerShape(50)
                        )
                        .padding(horizontal = 12.dp, vertical = 7.dp),
                    verticalAlignment = Alignment.CenterVertically,
                    horizontalArrangement = Arrangement.spacedBy(7.dp)
                ) {
                    Box(
                        modifier = Modifier
                            .size(8.dp)
                            .clip(CircleShape)
                            .background(
                                when {
                                    isConnected -> Emerald500
                                    isConnecting -> Gold
                                    else -> Gold.copy(alpha = 0.55f)
                                }
                            )
                    )
                    Text(
                        text = "Falcone · Mitgründer",
                        fontSize = 12.5.sp,
                        fontWeight = FontWeight.Bold,
                        color = stageHudText,
                        maxLines = 1
                    )
                }

                if (pendingBlockades.isNotEmpty()) {
                    Row(
                        modifier = Modifier
                            .scale(pulseScale.coerceIn(1f, 1.06f))
                            .clip(RoundedCornerShape(50))
                            .background(Gold.copy(alpha = 0.22f))
                            .border(1.2.dp, Gold, RoundedCornerShape(50))
                            .clickable {
                                if (blockadeCardSnoozed) {
                                    blockadeCardSnoozed = false
                                } else {
                                    focusInboxItemId = pendingBlockades.firstOrNull()?.id
                                    showDirectInboxSheet = true
                                }
                            }
                            .padding(horizontal = 10.dp, vertical = 6.dp),
                        verticalAlignment = Alignment.CenterVertically,
                        horizontalArrangement = Arrangement.spacedBy(4.dp)
                    ) {
                        Text(
                            text = "⚠️ ${pendingBlockades.size} Antwort offen",
                            fontSize = 11.5.sp,
                            fontWeight = FontWeight.ExtraBold,
                            color = Gold,
                            maxLines = 1
                        )
                    }
                }
            }

            // Right Controls: Camera Eyes, Music Mini Indicator, Dashboard
            Row(
                verticalAlignment = Alignment.CenterVertically,
                horizontalArrangement = Arrangement.spacedBy(8.dp)
            ) {
                // Kamera-Augen Toggle
                Box(
                    modifier = Modifier
                        .size(36.dp)
                        .clip(CircleShape)
                        .background(
                            if (isVisionActive) Gold else stageHudBg
                        )
                        .border(
                            1.dp,
                            if (isVisionActive) Gold else Gold.copy(alpha = 0.50f),
                            CircleShape
                        )
                        .clickable {
                            if (isVisionActive) {
                                FalconCameraController.stopCamera()
                            } else if (FalconCameraController.hasCameraPermission(context)) {
                                FalconCameraController.startVisionAsync(context)
                            } else {
                                pendingCameraAction = PendingCameraAction("enable_vision", "back")
                                cameraPermissionLauncher.launch(Manifest.permission.CAMERA)
                            }
                        },
                    contentAlignment = Alignment.Center
                ) {
                    Icon(
                        imageVector = if (isVisionActive) Icons.Default.Videocam else Icons.Default.VideocamOff,
                        contentDescription = "Kamera-Augen",
                        tint = if (isVisionActive) SpaceBlack else Gold,
                        modifier = Modifier.size(18.dp)
                    )
                }

                // Musik-Mini-Indikator (nur sichtbar während Musik läuft)
                AnimatedVisibility(visible = isMusicPlaying, enter = fadeIn(), exit = fadeOut()) {
                    Row(
                        modifier = Modifier
                            .clip(RoundedCornerShape(50))
                            .background(stageHudBg)
                            .border(1.dp, Gold.copy(alpha = 0.55f), RoundedCornerShape(50))
                            .padding(horizontal = 6.dp, vertical = 4.dp),
                        verticalAlignment = Alignment.CenterVertically,
                        horizontalArrangement = Arrangement.spacedBy(4.dp)
                    ) {
                        Box(
                            modifier = Modifier
                                .size(28.dp)
                                .clip(CircleShape)
                                .background(Gold.copy(alpha = 0.16f))
                                .clickable { MusicPlayerManager.togglePlayPause(context) },
                            contentAlignment = Alignment.Center
                        ) {
                            Icon(
                                imageVector = Icons.Default.Pause,
                                contentDescription = "Musik pausieren",
                                tint = Gold,
                                modifier = Modifier.size(15.dp)
                            )
                        }
                        Box(
                            modifier = Modifier
                                .size(28.dp)
                                .clip(CircleShape)
                                .background(Gold.copy(alpha = 0.16f))
                                .clickable { MusicPlayerManager.playNext(context) },
                            contentAlignment = Alignment.Center
                        ) {
                            Icon(
                                imageVector = Icons.Default.SkipNext,
                                contentDescription = "Nächster Titel",
                                tint = Gold,
                                modifier = Modifier.size(16.dp)
                            )
                        }
                    }
                }

                // Dashboard Switch Button
                Row(
                    modifier = Modifier
                        .clip(RoundedCornerShape(50))
                        .background(stageHudBg)
                        .border(1.dp, Gold.copy(alpha = 0.60f), RoundedCornerShape(50))
                        .clickable {
                            FalconCameraController.stopCamera()
                            onOpenDashboard()
                        }
                        .padding(horizontal = 12.dp, vertical = 8.dp),
                    verticalAlignment = Alignment.CenterVertically,
                    horizontalArrangement = Arrangement.spacedBy(6.dp)
                ) {
                    Icon(
                        imageVector = Icons.Default.GridView,
                        contentDescription = null,
                        tint = Gold,
                        modifier = Modifier.size(15.dp)
                    )
                    Text(
                        text = "Dashboard",
                        fontSize = 12.5.sp,
                        fontWeight = FontWeight.Bold,
                        color = stageHudText
                    )
                }
            }
        }

        // 3. Visible Gold PIP Camera Preview (Top Right below Header when Vision is Active)
        AnimatedVisibility(
            visible = isVisionActive,
            enter = fadeIn(),
            exit = fadeOut(),
            modifier = Modifier
                .align(Alignment.TopEnd)
                .statusBarsPadding()
                .padding(top = 62.dp, end = 16.dp)
        ) {
            Card(
                modifier = Modifier
                    .width(104.dp)
                    .height(138.dp)
                    .border(1.5.dp, Gold, RoundedCornerShape(14.dp)),
                colors = CardDefaults.cardColors(containerColor = SpaceBlack),
                shape = RoundedCornerShape(14.dp)
            ) {
                Box(modifier = Modifier.fillMaxSize()) {
                    if (previewBitmap != null) {
                        Image(
                            bitmap = previewBitmap!!,
                            contentDescription = "Falcone Live-Kamera",
                            contentScale = ContentScale.Crop,
                            modifier = Modifier.fillMaxSize()
                        )
                    } else {
                        CircularProgressIndicator(
                            color = Gold,
                            strokeWidth = 2.dp,
                            modifier = Modifier
                                .size(22.dp)
                                .align(Alignment.Center)
                        )
                    }

                    // Switch lens button (bottom-left of PIP)
                    Box(
                        modifier = Modifier
                            .align(Alignment.BottomStart)
                            .padding(5.dp)
                            .size(26.dp)
                            .clip(CircleShape)
                            .background(SpaceBlack.copy(alpha = 0.72f))
                            .border(1.dp, Gold.copy(alpha = 0.65f), CircleShape)
                            .clickable { FalconCameraController.switchCameraAsync(context) },
                        contentAlignment = Alignment.Center
                    ) {
                        Icon(
                            imageVector = Icons.Default.Cameraswitch,
                            contentDescription = "Kamera wechseln",
                            tint = Gold,
                            modifier = Modifier.size(14.dp)
                        )
                    }

                    // Turn off camera button (top-right of PIP)
                    Box(
                        modifier = Modifier
                            .align(Alignment.TopEnd)
                            .padding(5.dp)
                            .size(24.dp)
                            .clip(CircleShape)
                            .background(SpaceBlack.copy(alpha = 0.72f))
                            .border(1.dp, Rose500.copy(alpha = 0.75f), CircleShape)
                            .clickable { FalconCameraController.stopCamera() },
                        contentAlignment = Alignment.Center
                    ) {
                        Icon(
                            imageVector = Icons.Default.Close,
                            contentDescription = "Kamera ausschalten",
                            tint = Rose500,
                            modifier = Modifier.size(13.dp)
                        )
                    }
                }
            }
        }

        // 4. Lower-Third Proaktive Blockade-Karte + Live Subtitle + Bottom Command Dock
        Column(
            modifier = Modifier
                .fillMaxWidth()
                .align(Alignment.BottomCenter)
                .navigationBarsPadding()
                .imePadding()
                .padding(horizontal = 18.dp, vertical = 18.dp),
            horizontalAlignment = Alignment.CenterHorizontally,
            verticalArrangement = Arrangement.spacedBy(12.dp)
        ) {
            val urgentBlockade = pendingBlockades.firstOrNull()
            AnimatedVisibility(
                visible = urgentBlockade != null && !blockadeCardSnoozed,
                enter = fadeIn(),
                exit = fadeOut()
            ) {
                urgentBlockade?.let { blockade ->
                    val channelChip = AgDirectInboxManager.resolveChannelEnum(blockade.channel).chipLabel
                    val questionLine = blockade.pendingQuestion?.takeIf { it.isNotBlank() }
                        ?: blockade.agResponseSummary?.takeIf { it.isNotBlank() }
                        ?: blockade.title

                    Column(
                        modifier = Modifier
                            .fillMaxWidth()
                            .clip(RoundedCornerShape(16.dp))
                            .background(Color(0xEE0D1017))
                            .border(1.2.dp, Gold, RoundedCornerShape(16.dp))
                            .padding(horizontal = 14.dp, vertical = 12.dp),
                        verticalArrangement = Arrangement.spacedBy(8.dp)
                    ) {
                        Row(
                            modifier = Modifier.fillMaxWidth(),
                            horizontalArrangement = Arrangement.SpaceBetween,
                            verticalAlignment = Alignment.CenterVertically
                        ) {
                            Row(
                                modifier = Modifier.weight(1f),
                                verticalAlignment = Alignment.CenterVertically,
                                horizontalArrangement = Arrangement.spacedBy(7.dp)
                            ) {
                                Box(
                                    modifier = Modifier
                                        .clip(RoundedCornerShape(6.dp))
                                        .background(Gold.copy(alpha = 0.18f))
                                        .border(1.dp, Gold.copy(alpha = 0.65f), RoundedCornerShape(6.dp))
                                        .padding(horizontal = 7.dp, vertical = 2.dp)
                                ) {
                                    Text(
                                        text = channelChip,
                                        fontSize = 10.5.sp,
                                        fontWeight = FontWeight.Bold,
                                        color = Gold,
                                        maxLines = 1
                                    )
                                }
                                Text(
                                    text = blockade.title,
                                    fontSize = 13.sp,
                                    fontWeight = FontWeight.Bold,
                                    color = stageHudText,
                                    maxLines = 1,
                                    overflow = TextOverflow.Ellipsis
                                )
                            }

                            Text(
                                text = "Später",
                                fontSize = 11.5.sp,
                                fontWeight = FontWeight.SemiBold,
                                color = stageHudMuted,
                                modifier = Modifier
                                    .clip(RoundedCornerShape(8.dp))
                                    .clickable {
                                        blockadeCardSnoozed = true
                                        showInlineBlockadeReply = false
                                    }
                                    .padding(horizontal = 6.dp, vertical = 3.dp)
                            )
                        }

                        Text(
                            text = questionLine,
                            fontSize = 12.5.sp,
                            lineHeight = 17.sp,
                            color = Color(0xFFE2E8F0),
                            maxLines = 3,
                            overflow = TextOverflow.Ellipsis
                        )

                        if (showInlineBlockadeReply) {
                            Row(
                                modifier = Modifier.fillMaxWidth(),
                                verticalAlignment = Alignment.CenterVertically,
                                horizontalArrangement = Arrangement.spacedBy(8.dp)
                            ) {
                                OutlinedTextField(
                                    value = inlineBlockadeReplyText,
                                    onValueChange = { inlineBlockadeReplyText = it },
                                    modifier = Modifier.weight(1f),
                                    placeholder = {
                                        Text(
                                            text = "Antwort für Antigravity...",
                                            fontSize = 12.5.sp,
                                            color = stageHudMuted
                                        )
                                    },
                                    maxLines = 3,
                                    shape = RoundedCornerShape(10.dp),
                                    textStyle = TextStyle(
                                        fontSize = 13.sp,
                                        color = stageHudText
                                    ),
                                    colors = OutlinedTextFieldDefaults.colors(
                                        focusedBorderColor = Gold,
                                        unfocusedBorderColor = Gold.copy(alpha = 0.40f),
                                        cursorColor = Gold,
                                        focusedContainerColor = Color(0xFF151924),
                                        unfocusedContainerColor = Color(0xFF151924)
                                    )
                                )

                                Box(
                                    modifier = Modifier
                                        .size(42.dp)
                                        .clip(RoundedCornerShape(10.dp))
                                        .background(Gold)
                                        .clickable {
                                            val speechIntent = Intent(RecognizerIntent.ACTION_RECOGNIZE_SPEECH).apply {
                                                putExtra(
                                                    RecognizerIntent.EXTRA_LANGUAGE_MODEL,
                                                    RecognizerIntent.LANGUAGE_MODEL_FREE_FORM
                                                )
                                                putExtra(RecognizerIntent.EXTRA_LANGUAGE, "de-DE")
                                                putExtra(RecognizerIntent.EXTRA_PROMPT, "Antwort sprechen")
                                            }
                                            try {
                                                blockadeSpeechLauncher.launch(speechIntent)
                                            } catch (_: ActivityNotFoundException) {
                                                Toast.makeText(
                                                    context,
                                                    "Spracheingabe auf diesem Gerät nicht verfügbar.",
                                                    Toast.LENGTH_SHORT
                                                ).show()
                                            } catch (_: Exception) {
                                            }
                                        },
                                    contentAlignment = Alignment.Center
                                ) {
                                    Icon(
                                        imageVector = Icons.Default.Mic,
                                        contentDescription = "Antwort diktieren",
                                        tint = SpaceBlack,
                                        modifier = Modifier.size(19.dp)
                                    )
                                }
                            }
                        }

                        Row(
                            modifier = Modifier.fillMaxWidth(),
                            horizontalArrangement = Arrangement.spacedBy(8.dp)
                        ) {
                            Button(
                                onClick = {
                                    if (isResolvingBlockade) return@Button
                                    val customReply = inlineBlockadeReplyText.trim()
                                    val decision = if (customReply.isNotEmpty()) "info_provided" else "approved"
                                    val answerPayload = customReply.ifEmpty { "Go erteilt" }
                                    isResolvingBlockade = true
                                    scope.launch {
                                        val ok = AgDirectInboxManager.resolveBlockade(
                                            context = context,
                                            itemId = blockade.id,
                                            decision = decision,
                                            answerText = answerPayload
                                        )
                                        isResolvingBlockade = false
                                        inlineBlockadeReplyText = ""
                                        showInlineBlockadeReply = false
                                        Toast.makeText(
                                            context,
                                            if (ok) "✓ Freigabe an Antigravity übermittelt!" else "Offline als Freigabe gespeichert",
                                            Toast.LENGTH_SHORT
                                        ).show()
                                        AgDirectInboxManager.refreshFromServer(context)
                                    }
                                },
                                enabled = !isResolvingBlockade,
                                modifier = Modifier
                                    .weight(1f)
                                    .height(38.dp),
                                shape = RoundedCornerShape(10.dp),
                                colors = ButtonDefaults.buttonColors(
                                    containerColor = Gold,
                                    contentColor = SpaceBlack
                                ),
                                contentPadding = PaddingValues(horizontal = 10.dp)
                            ) {
                                Text(
                                    text = if (inlineBlockadeReplyText.isNotBlank()) "✓ Senden & Go" else "✓ Go geben",
                                    fontSize = 12.5.sp,
                                    fontWeight = FontWeight.Bold,
                                    maxLines = 1
                                )
                            }

                            Box(
                                modifier = Modifier
                                    .weight(1f)
                                    .height(38.dp)
                                    .clip(RoundedCornerShape(10.dp))
                                    .background(Color(0x22FFFFFF))
                                    .border(1.dp, Gold.copy(alpha = 0.60f), RoundedCornerShape(10.dp))
                                    .combinedClickable(
                                        onClick = {
                                            showInlineBlockadeReply = !showInlineBlockadeReply
                                        },
                                        onLongClick = {
                                            focusInboxItemId = blockade.id
                                            showDirectInboxSheet = true
                                        }
                                    ),
                                contentAlignment = Alignment.Center
                            ) {
                                Text(
                                    text = if (showInlineBlockadeReply) "Ausblenden" else "Antworten",
                                    fontSize = 12.5.sp,
                                    fontWeight = FontWeight.SemiBold,
                                    color = Gold,
                                    maxLines = 1
                                )
                            }
                        }
                    }
                }
            }
            val currentSub = liveSubtitle?.trim().orEmpty()
            if (currentSub.isNotEmpty() && currentSub != dismissedSubtitleText) {
                Row(
                    modifier = Modifier
                        .fillMaxWidth()
                        .clip(RoundedCornerShape(14.dp))
                        .background(stageHudBg)
                        .border(1.dp, Gold.copy(alpha = 0.45f), RoundedCornerShape(14.dp))
                        .padding(horizontal = 14.dp, vertical = 9.dp),
                    verticalAlignment = Alignment.CenterVertically,
                    horizontalArrangement = Arrangement.SpaceBetween
                ) {
                    Text(
                        text = currentSub,
                        fontSize = 13.sp,
                        lineHeight = 18.sp,
                        color = stageHudText,
                        maxLines = 2,
                        overflow = TextOverflow.Ellipsis,
                        modifier = Modifier.weight(1f)
                    )
                    Spacer(modifier = Modifier.width(8.dp))
                    Box(
                        modifier = Modifier
                            .size(22.dp)
                            .clip(CircleShape)
                            .clickable { dismissedSubtitleText = currentSub },
                        contentAlignment = Alignment.Center
                    ) {
                        Icon(
                            imageVector = Icons.Default.Close,
                            contentDescription = "Untertitel ausblenden",
                            tint = stageHudMuted,
                            modifier = Modifier.size(14.dp)
                        )
                    }
                }
            }

            // Bottom Command Dock
            Row(
                modifier = Modifier.fillMaxWidth(),
                horizontalArrangement = Arrangement.SpaceBetween,
                verticalAlignment = Alignment.CenterVertically
            ) {
                // Left: Photo Shutter + Captured Photo Lightbox Thumbnail
                Row(
                    verticalAlignment = Alignment.CenterVertically,
                    horizontalArrangement = Arrangement.spacedBy(8.dp),
                    modifier = Modifier.weight(1f)
                ) {
                    Box(
                        modifier = Modifier
                            .size(48.dp)
                            .clip(CircleShape)
                            .background(stageHudBg)
                            .border(1.dp, Gold.copy(alpha = 0.60f), CircleShape)
                            .combinedClickable(
                                enabled = !isCapturingPhoto,
                                onClick = { showCameraSourceMenu = true },
                                onLongClick = { imagePickerLauncher.launch("image/*") }
                            ),
                        contentAlignment = Alignment.Center
                    ) {
                        if (isCapturingPhoto) {
                            CircularProgressIndicator(
                                color = Gold,
                                strokeWidth = 2.dp,
                                modifier = Modifier.size(20.dp)
                            )
                        } else {
                            Icon(
                                imageVector = Icons.Default.PhotoCamera,
                                contentDescription = "Foto aufnehmen",
                                tint = Gold,
                                modifier = Modifier.size(21.dp)
                            )
                        }
                    }

                    lastCapturedPhoto?.takeIf { !it.attachedToInbox }?.let { photo ->
                        Box(modifier = Modifier.size(52.dp)) {
                            Image(
                                bitmap = photo.previewBitmap,
                                contentDescription = photo.fileName,
                                contentScale = ContentScale.Crop,
                                modifier = Modifier
                                    .size(44.dp)
                                    .align(Alignment.CenterStart)
                                    .clip(CircleShape)
                                    .border(1.5.dp, Gold, CircleShape)
                                    .clickable { showPhotoLightbox = true }
                            )
                            // Schnell-Verwerfen (X) oben rechts
                            Box(
                                modifier = Modifier
                                    .size(18.dp)
                                    .align(Alignment.TopEnd)
                                    .clip(CircleShape)
                                    .background(SpaceBlack.copy(alpha = 0.90f))
                                    .border(1.dp, Rose500, CircleShape)
                                    .clickable { FalconCameraController.clearLastCapturedPhoto() },
                                contentAlignment = Alignment.Center
                            ) {
                                Icon(
                                    imageVector = Icons.Default.Close,
                                    contentDescription = "Foto verwerfen",
                                    tint = Rose500,
                                    modifier = Modifier.size(11.dp)
                                )
                            }
                            // Schnell-Senden an Antigravity unten rechts
                            Box(
                                modifier = Modifier
                                    .size(20.dp)
                                    .align(Alignment.BottomEnd)
                                    .clip(CircleShape)
                                    .background(Gold)
                                    .border(1.dp, SpaceBlack, CircleShape)
                                    .clickable { showDirectInboxSheet = true },
                                contentAlignment = Alignment.Center
                            ) {
                                Icon(
                                    imageVector = Icons.Default.Bolt,
                                    contentDescription = "An Antigravity senden",
                                    tint = SpaceBlack,
                                    modifier = Modifier.size(13.dp)
                                )
                            }
                        }
                    }
                }

                // Center: Large 64.dp Gold-Puls-Button (Live Call Start / Stop; 0 Tokens when Idle!)
                Box(contentAlignment = Alignment.Center) {
                    Box(
                        modifier = Modifier
                            .size(78.dp)
                            .scale(pulseScale)
                            .background(
                                brush = Brush.radialGradient(
                                    colors = listOf(
                                        (if (isConnected) Emerald500 else Gold).copy(alpha = 0.34f),
                                        Color.Transparent
                                    )
                                ),
                                shape = CircleShape
                            )
                    )

                    Box(
                        modifier = Modifier
                            .size(64.dp)
                            .clip(CircleShape)
                            .background(if (isConnected) Rose500 else Gold)
                            .border(
                                2.dp,
                                if (isConnected) Color.White.copy(alpha = 0.35f) else Gold,
                                CircleShape
                            )
                            .clickable {
                                if (isConnected || isConnecting) {
                                    viewModel.disconnectLiveChat()
                                } else {
                                    val hasMic = ContextCompat.checkSelfPermission(
                                        context,
                                        Manifest.permission.RECORD_AUDIO
                                    ) == PackageManager.PERMISSION_GRANTED
                                    if (hasMic) {
                                        SoundManager.playSound(R.raw.open_project_brain)
                                        viewModel.startLiveChat()
                                    } else {
                                        micPermissionLauncher.launch(Manifest.permission.RECORD_AUDIO)
                                    }
                                }
                            },
                        contentAlignment = Alignment.Center
                    ) {
                        if (isConnecting) {
                            CircularProgressIndicator(
                                color = SpaceBlack,
                                strokeWidth = 2.5.dp,
                                modifier = Modifier.size(26.dp)
                            )
                        } else {
                            Icon(
                                imageVector = if (isConnected) Icons.Default.CallEnd else Icons.Default.Mic,
                                contentDescription = if (isConnected) "Gespräch beenden" else "Mit Falcone sprechen",
                                tint = if (isConnected) Color.White else SpaceBlack,
                                modifier = Modifier.size(27.dp)
                            )
                        }
                    }
                }

                // Right: Direkt-Aufträge & Text-Sheet Button (with agOpenCount badge)
                Box(
                    modifier = Modifier.weight(1f),
                    contentAlignment = Alignment.CenterEnd
                ) {
                    Box(modifier = Modifier.size(50.dp)) {
                        Box(
                            modifier = Modifier
                                .size(48.dp)
                                .align(Alignment.BottomStart)
                                .clip(CircleShape)
                                .background(stageHudBg)
                                .border(1.dp, Gold.copy(alpha = 0.60f), CircleShape)
                                .clickable { showDirectInboxSheet = true },
                            contentAlignment = Alignment.Center
                        ) {
                            Icon(
                                imageVector = Icons.Default.Bolt,
                                contentDescription = "Direktnachricht",
                                tint = Gold,
                                modifier = Modifier.size(21.dp)
                            )
                        }

                        if (agOpenCount > 0) {
                            Box(
                                modifier = Modifier
                                    .align(Alignment.TopEnd)
                                    .clip(RoundedCornerShape(50))
                                    .background(Gold)
                                    .padding(horizontal = 6.dp, vertical = 1.dp),
                                contentAlignment = Alignment.Center
                            ) {
                                Text(
                                    text = agOpenCount.toString(),
                                    fontSize = 10.5.sp,
                                    fontWeight = FontWeight.ExtraBold,
                                    color = SpaceBlack
                                )
                            }
                        }
                    }
                }
            }
        }
    }
}
