@file:OptIn(ExperimentalFoundationApi::class)

package de.meinseelenfunke.app.ui.navigation

import androidx.compose.foundation.ExperimentalFoundationApi
import androidx.compose.foundation.border
import androidx.compose.foundation.layout.padding
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
import androidx.compose.material3.Icon
import androidx.compose.material3.NavigationBar
import androidx.compose.material3.NavigationBarItem
import androidx.compose.material3.NavigationBarItemDefaults
import androidx.compose.material3.Scaffold
import androidx.compose.material3.Text
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
import de.meinseelenfunke.app.ui.theme.SpaceBlack
import de.meinseelenfunke.app.ui.theme.GlassWhite10
import de.meinseelenfunke.app.ui.theme.Slate400
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
    val isLoggedIn = ServiceLocator.authRepository.isLoggedIn()
    val isBiometricEnabled = ServiceLocator.isBiometricEnabled()
    val isBiometricAvailable = remember(context) { BiometricHelper.isBiometricAvailable(context) }
    val requiresBiometricUnlock = isLoggedIn && isBiometricEnabled && isBiometricAvailable

    // On cold app restart, if user has an active session and biometric is enabled, require fingerprint unlock!
    var isBiometricUnlocked by rememberSaveable {
        mutableStateOf(!requiresBiometricUnlock)
    }

    android.util.Log.d("BiometricDebug", "isLoggedIn: $isLoggedIn, isBiometricEnabled: $isBiometricEnabled, isBiometricAvailable: $isBiometricAvailable, requiresBiometricUnlock: $requiresBiometricUnlock, isBiometricUnlocked: $isBiometricUnlocked")

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
                ServiceLocator.authRepository.logout()
                isBiometricUnlocked = true
                navController.navigate(Screen.Login.route) {
                    popUpTo(0) { inclusive = true }
                }
            }
        )
    } else {
        val startDestination = if (isLoggedIn) "main" else Screen.Login.route

        NavHost(
            navController = navController,
            startDestination = startDestination
        ) {
            composable(Screen.Login.route) {
                LoginScreen(
                    onLoginSuccess = {
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
                        ServiceLocator.authRepository.logout()
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
    }
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
        if (isAdmin) {
            listOf(
                Screen.Zentrum,
                Screen.Orders,
                Screen.Finances,
                Screen.Organizer,
                Screen.Chat,
                Screen.Settings
            )
        } else {
            listOf(
                Screen.Zentrum,
                Screen.Finances,
                Screen.Organizer,
                Screen.Chat,
                Screen.Settings
            )
        }
    }

    val coroutineScope = rememberCoroutineScope()
    val pagerState = androidx.compose.runtime.key(isAdmin) {
        rememberPagerState(
            initialPage = 0,
            pageCount = { bottomNavItems.size }
        )
    }
    var organizerInitialTab by remember { mutableIntStateOf(0) }
    var isFirstLoad by remember { mutableStateOf(true) }
    var isInitialTargetPagePass by remember { mutableStateOf(true) }

    LaunchedEffect(Unit) {
        if (ServiceLocator.authRepository.isLoggedIn()) {
            coroutineScope.launch {
                ServiceLocator.authRepository.getCurrentUser()
            }
        }
        val targetImmediate = de.meinseelenfunke.app.util.NavigationBridge.pendingTab
        if (targetImmediate != null) {
            val targetScreen = when (targetImmediate) {
                0 -> Screen.Zentrum
                1 -> if (isAdmin) Screen.Orders else Screen.Finances
                2 -> Screen.Organizer
                3 -> Screen.Chat
                4 -> Screen.Settings
                else -> Screen.Zentrum
            }
            val resolvedIndex = bottomNavItems.indexOf(targetScreen).coerceAtLeast(0)
            if (targetScreen == Screen.Organizer) {
                organizerInitialTab = de.meinseelenfunke.app.util.NavigationBridge.organizerSubTab
            }
            coroutineScope.launch {
                pagerState.scrollToPage(resolvedIndex)
            }
            de.meinseelenfunke.app.util.NavigationBridge.pendingTab = null
        }

        de.meinseelenfunke.app.util.NavigationBridge.navigationTrigger.collect {
            val target = de.meinseelenfunke.app.util.NavigationBridge.pendingTab
            if (target != null) {
                val targetScreen = when (target) {
                    0 -> Screen.Zentrum
                    1 -> if (isAdmin) Screen.Orders else Screen.Finances
                    2 -> Screen.Organizer
                    3 -> Screen.Chat
                    4 -> Screen.Settings
                    else -> Screen.Zentrum
                }
                val resolvedIndex = bottomNavItems.indexOf(targetScreen).coerceAtLeast(0)
                if (targetScreen == Screen.Organizer) {
                    organizerInitialTab = de.meinseelenfunke.app.util.NavigationBridge.organizerSubTab
                }
                coroutineScope.launch {
                    pagerState.animateScrollToPage(resolvedIndex)
                }
                de.meinseelenfunke.app.util.NavigationBridge.pendingTab = null
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

    LaunchedEffect(pagerState.targetPage) {
        if (isInitialTargetPagePass) {
            isInitialTargetPagePass = false
            return@LaunchedEffect
        }
        val organizerIndex = bottomNavItems.indexOf(Screen.Organizer)
        if (pagerState.targetPage != organizerIndex) {
            organizerInitialTab = 0
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
            modifier = Modifier.padding(innerPadding)
        ) { page ->
            when (bottomNavItems[page]) {
                Screen.Zentrum -> ZentrumScreen(
                    onNavigateToLiveChat = onNavigateToLiveChat,
                    onNavigateToFinances = {
                        coroutineScope.launch {
                            val financesIndex = bottomNavItems.indexOf(Screen.Finances)
                            if (financesIndex >= 0) pagerState.animateScrollToPage(financesIndex)
                        }
                    },
                    onNavigateToOrganizer = {
                        organizerInitialTab = 2 // Redirect to Routinen tab
                        coroutineScope.launch {
                            val organizerIndex = bottomNavItems.indexOf(Screen.Organizer)
                            if (organizerIndex >= 0) pagerState.animateScrollToPage(organizerIndex)
                        }
                    },
                    onLogout = onLogout
                )
                Screen.Finances -> FinanceScreen(
                    isPageVisible = pagerState.currentPage == page
                )
                Screen.Organizer -> OrganizerScreen(
                    initialTab = organizerInitialTab,
                    onTabScrollCompleted = {},
                    isPageVisible = pagerState.currentPage == page
                )
                Screen.Chat -> ChatScreen(
                    onLogout = onLogout
                )
                Screen.Settings -> SettingsScreen()
                Screen.Orders -> OrdersTabContent()
                else -> {}
            }
        }
    }
}
