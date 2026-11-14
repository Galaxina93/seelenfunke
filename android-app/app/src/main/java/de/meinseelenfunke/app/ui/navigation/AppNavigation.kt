@file:OptIn(ExperimentalFoundationApi::class)

package de.meinseelenfunke.app.ui.navigation

import androidx.compose.foundation.ExperimentalFoundationApi
import androidx.compose.foundation.border
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.Spacer
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.layout.width
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.foundation.pager.HorizontalPager
import androidx.compose.foundation.pager.rememberPagerState
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.Settings
import androidx.compose.material.icons.filled.DateRange
import androidx.compose.material.icons.filled.Home
import androidx.compose.material.icons.filled.Person
import androidx.compose.material.icons.filled.ShoppingCart
import androidx.compose.material.icons.filled.AttachMoney
import androidx.compose.material.icons.filled.List
import androidx.compose.material.icons.filled.SystemUpdate
import androidx.compose.material3.AlertDialog
import androidx.compose.material3.Button
import androidx.compose.material3.ButtonDefaults
import androidx.compose.material3.Icon
import androidx.compose.material3.LinearProgressIndicator
import androidx.compose.material3.NavigationBar
import androidx.compose.material3.NavigationBarItem
import androidx.compose.material3.NavigationBarItemDefaults
import androidx.compose.material3.Scaffold
import androidx.compose.material3.Text
import androidx.compose.material3.TextButton
import androidx.compose.ui.Alignment
import androidx.compose.ui.draw.clip
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.style.TextOverflow
import androidx.compose.runtime.Composable
import androidx.compose.runtime.getValue
import androidx.compose.runtime.remember
import androidx.compose.runtime.collectAsState
import androidx.compose.runtime.mutableIntStateOf
import androidx.compose.runtime.setValue
import androidx.compose.runtime.rememberCoroutineScope
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.ui.Modifier
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.graphics.graphicsLayer
import androidx.compose.ui.graphics.vector.ImageVector
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import androidx.navigation.NavHostController
import androidx.navigation.compose.NavHost
import androidx.navigation.compose.composable
import androidx.navigation.compose.currentBackStackEntryAsState
import androidx.navigation.compose.rememberNavController
import de.meinseelenfunke.app.di.ServiceLocator
import de.meinseelenfunke.app.ui.screens.ChatScreen
import de.meinseelenfunke.app.ui.screens.FinanceScreen
import de.meinseelenfunke.app.ui.screens.FunkiraLiveScreen
import de.meinseelenfunke.app.ui.screens.SettingsScreen
import de.meinseelenfunke.app.ui.screens.LoginScreen
import de.meinseelenfunke.app.ui.screens.OrganizerScreen
import de.meinseelenfunke.app.ui.screens.ZentrumScreen
import de.meinseelenfunke.app.ui.screens.OrdersTabContent
import de.meinseelenfunke.app.ui.theme.AppTheme
import de.meinseelenfunke.app.ui.theme.Gold
import de.meinseelenfunke.app.ui.theme.Rose500
import de.meinseelenfunke.app.ui.theme.SpaceBlack
import de.meinseelenfunke.app.ui.theme.GlassWhite10
import de.meinseelenfunke.app.ui.theme.Slate400
import de.meinseelenfunke.app.util.AppUpdateManager
import de.meinseelenfunke.app.util.OtaUpdateState
import kotlinx.coroutines.launch

import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.saveable.rememberSaveable
import androidx.compose.ui.platform.LocalContext
import de.meinseelenfunke.app.ui.screens.BiometricLockScreen
import de.meinseelenfunke.app.util.BiometricHelper
import de.meinseelenfunke.app.util.SoundManager
import de.meinseelenfunke.app.R

sealed class Screen(val route: String, val title: String = "", val icon: ImageVector? = null) {
    object Login : Screen("login")
    object Zentrum : Screen("zentrum", "Zentrum", Icons.Default.Home)
    object Finances : Screen("finances", "Finanzen", Icons.Default.AttachMoney)
    object Organizer : Screen("organizer", "Organizer", Icons.Default.DateRange)
    object Chat : Screen("chat", "Agenten", Icons.Default.Person)
    object Settings : Screen("settings", "Einstellungen", Icons.Default.Settings)
    object Orders : Screen("orders", "Bestellungen", Icons.Default.ShoppingCart)
    object LiveVoice : Screen("live_voice")
}

@Composable
fun AppNavigation(
    navController: NavHostController = rememberNavController()
) {
    val context = LocalContext.current
    var isLoggedIn by rememberSaveable {
        mutableStateOf(ServiceLocator.authRepository.isLoggedIn())
    }
    val isBiometricEnabled = ServiceLocator.isBiometricEnabled()
    val isBiometricAvailable = remember(context) { BiometricHelper.isBiometricAvailable(context) }
    val requiresBiometricUnlock = isLoggedIn && isBiometricEnabled && isBiometricAvailable

    // On cold app restart, if user has an active session and biometric is enabled, require fingerprint unlock!
    var isBiometricUnlocked by rememberSaveable {
        mutableStateOf(!requiresBiometricUnlock)
    }

    val lifecycleOwner = androidx.compose.ui.platform.LocalLifecycleOwner.current
    var backgroundedAtElapsedMs by rememberSaveable { androidx.compose.runtime.mutableLongStateOf(0L) }

    // Auto-relock after 3 minutes in background & auto-check for OTA updates on app start/resume
    androidx.compose.runtime.DisposableEffect(lifecycleOwner, isLoggedIn) {
        val observer = androidx.lifecycle.LifecycleEventObserver { _, event ->
            when (event) {
                androidx.lifecycle.Lifecycle.Event.ON_STOP -> {
                    backgroundedAtElapsedMs = android.os.SystemClock.elapsedRealtime()
                }
                androidx.lifecycle.Lifecycle.Event.ON_RESUME -> {
                    val nowElapsed = android.os.SystemClock.elapsedRealtime()
                    val wasInBackgroundMs = if (backgroundedAtElapsedMs > 0L) (nowElapsed - backgroundedAtElapsedMs) else 0L
                    val shouldRelock = ServiceLocator.authRepository.isLoggedIn()
                        && ServiceLocator.isBiometricEnabled()
                        && BiometricHelper.isBiometricAvailable(context)
                        && backgroundedAtElapsedMs > 0L
                        && wasInBackgroundMs >= 180_000L
                        && !de.meinseelenfunke.app.util.FunkenwerkAlarmManager.isAlarmRingingFlow.value
                        && !de.meinseelenfunke.app.util.NavigationBridge.isLiveCallActive
                    if (shouldRelock) {
                        isBiometricUnlocked = false
                    } else {
                        AppUpdateManager.resumePendingInstallIfPermitted(context)
                        if (
                            wasInBackgroundMs >= 10_000L
                            && ServiceLocator.authRepository.isLoggedIn()
                            && !de.meinseelenfunke.app.util.FunkenwerkAlarmManager.isAlarmRingingFlow.value
                            && !de.meinseelenfunke.app.util.NavigationBridge.isLiveCallActive
                        ) {
                            AppUpdateManager.onAppStartCheck(context)
                        }
                    }
                    backgroundedAtElapsedMs = 0L
                }
                else -> {}
            }
        }
        lifecycleOwner.lifecycle.addObserver(observer)
        onDispose {
            lifecycleOwner.lifecycle.removeObserver(observer)
        }
    }

    LaunchedEffect(isLoggedIn, isBiometricUnlocked) {
        var ctx = context
        while (ctx is android.content.ContextWrapper) {
            if (ctx is android.app.Activity) break
            ctx = ctx.baseContext
        }
        val activity = ctx as? android.app.Activity
        if (isLoggedIn && !isBiometricUnlocked) {
            activity?.window?.addFlags(android.view.WindowManager.LayoutParams.FLAG_SECURE)
        } else {
            activity?.window?.clearFlags(android.view.WindowManager.LayoutParams.FLAG_SECURE)
            if (isLoggedIn && isBiometricUnlocked) {
                AppUpdateManager.onAppStartCheck(context)
            }
        }
    }

    LaunchedEffect(navController) {
        de.meinseelenfunke.app.util.NavigationBridge.wakeWordTrigger.collect {
            if (ServiceLocator.authRepository.isLoggedIn() && isBiometricUnlocked) {
                try {
                    val currentRoute = navController.currentDestination?.route
                    if (currentRoute != Screen.LiveVoice.route) {
                        de.meinseelenfunke.app.util.NavigationBridge.pendingWakeWordTrigger = false
                        navController.navigate(Screen.LiveVoice.route)
                    }
                } catch (e: IllegalStateException) {
                    // NavController graph is not yet initialized; preserve the pending trigger so the backstack listener handles it later.
                }
            }
        }
    }

    LaunchedEffect(navController) {
        navController.currentBackStackEntryFlow.collect { backStackEntry ->
            if (de.meinseelenfunke.app.util.NavigationBridge.pendingWakeWordTrigger) {
                if (ServiceLocator.authRepository.isLoggedIn() && isBiometricUnlocked) {
                    if (backStackEntry.destination.route != Screen.LiveVoice.route) {
                        de.meinseelenfunke.app.util.NavigationBridge.pendingWakeWordTrigger = false
                        navController.navigate(Screen.LiveVoice.route)
                    }
                }
            }
        }
    }

    if (isLoggedIn && !isBiometricUnlocked) {
        BiometricLockScreen(
            onUnlockSuccess = {
                isBiometricUnlocked = true
            },
            onUsePassword = {
                AppUpdateManager.resetOnLogout(context)
                ServiceLocator.authRepository.logout()
                isLoggedIn = false
                isBiometricUnlocked = true
                navController.navigate(Screen.Login.route) {
                    popUpTo(0) { inclusive = true }
                }
            }
        )
    } else {
        val startDestination = remember { if (ServiceLocator.authRepository.isLoggedIn()) "main" else Screen.Login.route }

        NavHost(
            navController = navController,
            startDestination = startDestination,
            enterTransition = {
                androidx.compose.animation.fadeIn(
                    animationSpec = androidx.compose.animation.core.tween(260, easing = androidx.compose.animation.core.FastOutSlowInEasing)
                ) + androidx.compose.animation.scaleIn(
                    initialScale = 0.94f,
                    animationSpec = androidx.compose.animation.core.tween(260, easing = androidx.compose.animation.core.FastOutSlowInEasing)
                )
            },
            exitTransition = {
                androidx.compose.animation.fadeOut(
                    animationSpec = androidx.compose.animation.core.tween(200)
                ) + androidx.compose.animation.scaleOut(
                    targetScale = 0.96f,
                    animationSpec = androidx.compose.animation.core.tween(200)
                )
            },
            popEnterTransition = {
                androidx.compose.animation.fadeIn(
                    animationSpec = androidx.compose.animation.core.tween(240, easing = androidx.compose.animation.core.FastOutSlowInEasing)
                ) + androidx.compose.animation.scaleIn(
                    initialScale = 0.96f,
                    animationSpec = androidx.compose.animation.core.tween(240, easing = androidx.compose.animation.core.FastOutSlowInEasing)
                )
            },
            popExitTransition = {
                androidx.compose.animation.fadeOut(
                    animationSpec = androidx.compose.animation.core.tween(200)
                ) + androidx.compose.animation.scaleOut(
                    targetScale = 0.92f,
                    animationSpec = androidx.compose.animation.core.tween(200)
                )
            }
        ) {
            composable(Screen.Login.route) {
                LoginScreen(
                    onLoginSuccess = {
                        isLoggedIn = true
                        isBiometricUnlocked = true
                        navController.navigate("main") {
                            popUpTo(Screen.Login.route) { inclusive = true }
                        }
                    }
                )
            }
            composable("main") {
                MainTabbedScreen(
                    navController = navController,
                    onNavigateToLiveChat = { navController.navigate(Screen.LiveVoice.route) },
                    onLogout = {
                        AppUpdateManager.resetOnLogout(context)
                        ServiceLocator.authRepository.logout()
                        isLoggedIn = false
                        isBiometricUnlocked = false
                        navController.navigate(Screen.Login.route) {
                            popUpTo(0) { inclusive = true }
                        }
                    }
                )
            }
            composable(Screen.LiveVoice.route) {
                FunkiraLiveScreen(
                    onBack = { navController.popBackStack() }
                )
            }
        }

        if (isLoggedIn) {
            StartupOtaUpdatePrompt()
        }
    }
}

@Composable
private fun StartupOtaUpdatePrompt() {
    val context = LocalContext.current
    val updateState by AppUpdateManager.state.collectAsState()
    val promptDismissed by AppUpdateManager.promptDismissed.collectAsState()
    val isAlarmRinging by de.meinseelenfunke.app.util.FunkenwerkAlarmManager.isAlarmRingingFlow.collectAsState()

    if (promptDismissed || isAlarmRinging || de.meinseelenfunke.app.util.NavigationBridge.isLiveCallActive) {
        return
    }

    val activeManifest = when (val st = updateState) {
        is OtaUpdateState.Available -> st.manifest
        is OtaUpdateState.Downloading -> st.manifest
        is OtaUpdateState.AwaitingInstallPermission -> st.manifest
        is OtaUpdateState.Error -> AppUpdateManager.getLastManifest()
        else -> null
    } ?: return

    val isDownloading = updateState is OtaUpdateState.Downloading
    val isAwaitingPerm = updateState is OtaUpdateState.AwaitingInstallPermission
    val (installedVerName, _) = remember(activeManifest.versionCode) {
        AppUpdateManager.getInstalledVersionInfo(context)
    }

    AlertDialog(
        onDismissRequest = {
            if (!isDownloading) {
                AppUpdateManager.dismissStartupPrompt()
            }
        },
        containerColor = AppTheme.colors.cardBackground,
        title = {
            Row(verticalAlignment = Alignment.CenterVertically) {
                Icon(
                    imageVector = Icons.Default.SystemUpdate,
                    contentDescription = null,
                    tint = Gold,
                    modifier = Modifier.size(22.dp)
                )
                Spacer(modifier = Modifier.width(8.dp))
                Text(
                    text = "Update v${activeManifest.versionName}",
                    fontWeight = FontWeight.Bold,
                    fontSize = 17.sp,
                    color = Gold
                )
            }
        },
        text = {
            Column(verticalArrangement = Arrangement.spacedBy(10.dp)) {
                Text(
                    text = "v$installedVerName  →  v${activeManifest.versionName}",
                    fontSize = 13.sp,
                    fontWeight = FontWeight.SemiBold,
                    color = AppTheme.colors.textPrimary
                )

                if (activeManifest.releaseNotes.isNotBlank()) {
                    Text(
                        text = activeManifest.releaseNotes,
                        fontSize = 12.sp,
                        color = AppTheme.colors.textMuted,
                        maxLines = 2,
                        overflow = TextOverflow.Ellipsis
                    )
                }

                when (val st = updateState) {
                    is OtaUpdateState.Downloading -> {
                        Column(verticalArrangement = Arrangement.spacedBy(6.dp)) {
                            Row(
                                modifier = Modifier.fillMaxWidth(),
                                horizontalArrangement = Arrangement.SpaceBetween
                            ) {
                                Text(
                                    text = "Wird geladen...",
                                    fontSize = 12.sp,
                                    color = AppTheme.colors.textMuted
                                )
                                Text(
                                    text = "${st.progressPercent} %",
                                    fontSize = 12.sp,
                                    fontWeight = FontWeight.Bold,
                                    color = Gold
                                )
                            }
                            LinearProgressIndicator(
                                progress = { st.progressPercent / 100f },
                                modifier = Modifier
                                    .fillMaxWidth()
                                    .height(6.dp)
                                    .clip(RoundedCornerShape(3.dp)),
                                color = Gold,
                                trackColor = AppTheme.colors.glassSurface
                            )
                        }
                    }
                    is OtaUpdateState.Error -> {
                        Text(
                            text = st.message,
                            fontSize = 12.sp,
                            fontWeight = FontWeight.Medium,
                            color = Rose500
                        )
                    }
                    else -> Unit
                }
            }
        },
        confirmButton = {
            Button(
                onClick = {
                    if (isAwaitingPerm) {
                        AppUpdateManager.continueInstallOrOpenPermission(context)
                    } else {
                        AppUpdateManager.downloadAndInstall(context, activeManifest)
                    }
                },
                enabled = !isDownloading,
                shape = RoundedCornerShape(10.dp),
                colors = ButtonDefaults.buttonColors(
                    containerColor = Gold,
                    contentColor = SpaceBlack
                )
            ) {
                Text(
                    text = when {
                        isDownloading -> "Lädt..."
                        isAwaitingPerm -> "Fortsetzen"
                        else -> "Installieren"
                    },
                    fontWeight = FontWeight.Bold,
                    fontSize = 13.sp
                )
            }
        },
        dismissButton = {
            if (!isDownloading) {
                TextButton(onClick = { AppUpdateManager.dismissStartupPrompt() }) {
                    Text(
                        text = "Später",
                        color = AppTheme.colors.textMuted,
                        fontWeight = FontWeight.SemiBold,
                        fontSize = 13.sp
                    )
                }
            }
        }
    )
}

@Composable
fun MainTabbedScreen(
    navController: NavHostController,
    onNavigateToLiveChat: () -> Unit,
    onLogout: () -> Unit
) {
    val userType by ServiceLocator.userTypeState.collectAsState(initial = ServiceLocator.getUserType())
    val isAdmin = userType == "admin"
    val bottomNavItems = remember(isAdmin) {
        de.meinseelenfunke.app.util.NavigationBridge.bottomNavScreensForRole(isAdmin)
    }

    val coroutineScope = rememberCoroutineScope()
    val pagerState = androidx.compose.runtime.key(isAdmin) {
        rememberPagerState(
            initialPage = 0,
            pageCount = { bottomNavItems.size }
        )
    }
    var organizerInitialTab by remember { mutableIntStateOf(0) }
    var organizerNavTrigger by remember { mutableIntStateOf(0) }
    var isFirstLoad by remember { mutableStateOf(true) }

    LaunchedEffect(isAdmin) {
        if (ServiceLocator.authRepository.isLoggedIn()) {
            coroutineScope.launch {
                ServiceLocator.authRepository.getCurrentUser()
            }
        }
        val targetImmediate = de.meinseelenfunke.app.util.NavigationBridge.consumePendingTarget()
        if (targetImmediate != null) {
            val targetScreen = de.meinseelenfunke.app.util.NavigationBridge.resolveScreen(targetImmediate, isAdmin)
            val resolvedIndex = de.meinseelenfunke.app.util.NavigationBridge.resolvePageIndex(targetImmediate, isAdmin)
            if (targetScreen == Screen.Organizer) {
                organizerInitialTab = de.meinseelenfunke.app.util.NavigationBridge.organizerSubTab
                organizerNavTrigger++
            }
            coroutineScope.launch {
                pagerState.scrollToPage(resolvedIndex)
            }
        }

        de.meinseelenfunke.app.util.NavigationBridge.navigationTrigger.collect {
            val target = de.meinseelenfunke.app.util.NavigationBridge.consumePendingTarget()
            if (target != null) {
                val targetScreen = de.meinseelenfunke.app.util.NavigationBridge.resolveScreen(target, isAdmin)
                val resolvedIndex = de.meinseelenfunke.app.util.NavigationBridge.resolvePageIndex(target, isAdmin)
                if (targetScreen == Screen.Organizer) {
                    organizerInitialTab = de.meinseelenfunke.app.util.NavigationBridge.organizerSubTab
                    organizerNavTrigger++
                }
                coroutineScope.launch {
                    pagerState.animateScrollToPage(resolvedIndex)
                }
            }
        }
    }

    LaunchedEffect(Unit) {
        de.meinseelenfunke.app.util.NavigationBridge.logoutTrigger.collect {
            onLogout()
        }
    }

    LaunchedEffect(pagerState.currentPage) {
        if (isFirstLoad) {
            isFirstLoad = false
        }
    }

    Scaffold(
        bottomBar = {
            NavigationBar(
                containerColor = AppTheme.colors.bottomBarBackground,
                modifier = Modifier.border(1.dp, AppTheme.colors.cardBorder, RoundedCornerShape(topStart = 20.dp, topEnd = 20.dp))
            ) {
                bottomNavItems.forEachIndexed { index, screen ->
                    val selected = pagerState.currentPage == index
                    NavigationBarItem(
                        selected = selected,
                        onClick = {
                            coroutineScope.launch {
                                pagerState.animateScrollToPage(index)
                            }
                        },
                        icon = {
                            screen.icon?.let {
                                Icon(
                                    imageVector = it,
                                    contentDescription = screen.title,
                                    tint = if (selected) Gold else AppTheme.colors.textMuted
                                )
                            }
                        },
                        colors = NavigationBarItemDefaults.colors(
                            indicatorColor = if (AppTheme.colors.isDark) Color(0x22C5A059) else Color(0x33C5A059)
                        )
                    )
                }
            }
        }
    ) { innerPadding ->
        HorizontalPager(
            state = pagerState,
            beyondBoundsPageCount = 1,
            modifier = Modifier.padding(innerPadding)
        ) { page ->
            val pageOffset = ((pagerState.currentPage - page) + pagerState.currentPageOffsetFraction)
            val absOffset = kotlin.math.abs(pageOffset).coerceIn(0f, 1f)
            androidx.compose.foundation.layout.Box(
                modifier = Modifier.graphicsLayer {
                    val scale = 1f - (absOffset * 0.035f)
                    scaleX = scale
                    scaleY = scale
                    alpha = 1f - (absOffset * 0.22f)
                }
            ) {
                when (bottomNavItems[page]) {
                    Screen.Zentrum -> ZentrumScreen(
                        onNavigateToLiveChat = onNavigateToLiveChat,
                        onNavigateToFinances = {
                            coroutineScope.launch {
                                val financesIndex = bottomNavItems.indexOf(Screen.Finances)
                                if (financesIndex >= 0) pagerState.animateScrollToPage(financesIndex)
                            }
                        },
                        onNavigateToOrganizer = { subTab ->
                            organizerInitialTab = subTab.coerceIn(0, 6)
                            organizerNavTrigger++
                            coroutineScope.launch {
                                val organizerIndex = bottomNavItems.indexOf(Screen.Organizer)
                                if (organizerIndex >= 0) pagerState.animateScrollToPage(organizerIndex)
                            }
                        },
                        onNavigateToOrders = {
                            coroutineScope.launch {
                                val ordersIndex = bottomNavItems.indexOf(Screen.Orders)
                                if (ordersIndex >= 0) pagerState.animateScrollToPage(ordersIndex)
                            }
                        },
                        onNavigateToSettings = {
                            coroutineScope.launch {
                                val settingsIndex = bottomNavItems.indexOf(Screen.Settings)
                                if (settingsIndex >= 0) pagerState.animateScrollToPage(settingsIndex)
                            }
                        },
                        onLogout = onLogout,
                        isPageVisible = pagerState.currentPage == page
                    )
                    Screen.Finances -> FinanceScreen(
                        isPageVisible = pagerState.currentPage == page
                    )
                    Screen.Organizer -> OrganizerScreen(
                        initialTab = organizerInitialTab,
                        navTrigger = organizerNavTrigger,
                        onTabScrollCompleted = {},
                        isPageVisible = pagerState.currentPage == page
                    )
                    Screen.Chat -> ChatScreen(
                        onLogout = onLogout
                    )
                    Screen.Settings -> SettingsScreen(
                        onLogout = onLogout
                    )
                    Screen.Orders -> OrdersTabContent()
                    else -> {}
                }
            }
        }
    }
}
