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
import androidx.compose.material3.Icon
import androidx.compose.material3.NavigationBar
import androidx.compose.material3.NavigationBarItem
import androidx.compose.material3.NavigationBarItemDefaults
import androidx.compose.material3.Scaffold
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.getValue
import androidx.compose.runtime.remember
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
import de.meinseelenfunke.app.ui.theme.Gold
import de.meinseelenfunke.app.ui.theme.SpaceBlack
import de.meinseelenfunke.app.ui.theme.GlassWhite10
import de.meinseelenfunke.app.ui.theme.Slate400
import kotlinx.coroutines.launch

import androidx.compose.runtime.mutableStateOf
import de.meinseelenfunke.app.util.SoundManager
import de.meinseelenfunke.app.R

sealed class Screen(val route: String, val title: String = "", val icon: ImageVector? = null) {
    object Login : Screen("login")
    object Zentrum : Screen("zentrum", "Zentrum", Icons.Default.Home)
    object Finances : Screen("finances", "Finanzen", Icons.Default.ShoppingCart)
    object Organizer : Screen("organizer", "Organizer", Icons.Default.DateRange)
    object Chat : Screen("chat", "Agenten", Icons.Default.Person)
    object Settings : Screen("settings", "Einstellungen", Icons.Default.Settings)
    object LiveVoice : Screen("live_voice")
}

val bottomNavItems = listOf(
    Screen.Zentrum,
    Screen.Finances,
    Screen.Organizer,
    Screen.Chat,
    Screen.Settings
)

@Composable
fun AppNavigation(
    navController: NavHostController = rememberNavController(),
    startDestination: String = if (ServiceLocator.authRepository.isLoggedIn()) "main" else Screen.Login.route
) {
    LaunchedEffect(navController) {
        de.meinseelenfunke.app.util.NavigationBridge.wakeWordTrigger.collect {
            if (ServiceLocator.authRepository.isLoggedIn()) {
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
                if (ServiceLocator.authRepository.isLoggedIn()) {
                    if (backStackEntry.destination.route != Screen.LiveVoice.route) {
                        de.meinseelenfunke.app.util.NavigationBridge.pendingWakeWordTrigger = false
                        navController.navigate(Screen.LiveVoice.route)
                    }
                }
            }
        }
    }

    NavHost(
        navController = navController,
        startDestination = startDestination
    ) {
        composable(Screen.Login.route) {
            LoginScreen(
                onLoginSuccess = {
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

@Composable
fun MainTabbedScreen(
    navController: NavHostController,
    onNavigateToLiveChat: () -> Unit,
    onLogout: () -> Unit
) {
    val coroutineScope = rememberCoroutineScope()
    val pagerState = rememberPagerState(
        initialPage = 0,
        pageCount = { bottomNavItems.size }
    )
    var organizerInitialTab by remember { mutableIntStateOf(0) }
    var isFirstLoad by remember { mutableStateOf(true) }
    var isInitialTargetPagePass by remember { mutableStateOf(true) }

    LaunchedEffect(Unit) {
        val targetImmediate = de.meinseelenfunke.app.util.NavigationBridge.pendingTab
        if (targetImmediate != null) {
            if (targetImmediate == bottomNavItems.indexOf(Screen.Organizer)) {
                organizerInitialTab = de.meinseelenfunke.app.util.NavigationBridge.organizerSubTab
            }
            coroutineScope.launch {
                pagerState.scrollToPage(targetImmediate)
            }
            de.meinseelenfunke.app.util.NavigationBridge.pendingTab = null
        }

        de.meinseelenfunke.app.util.NavigationBridge.navigationTrigger.collect {
            val target = de.meinseelenfunke.app.util.NavigationBridge.pendingTab
            if (target != null) {
                if (target == bottomNavItems.indexOf(Screen.Organizer)) {
                    organizerInitialTab = de.meinseelenfunke.app.util.NavigationBridge.organizerSubTab
                }
                coroutineScope.launch {
                    pagerState.animateScrollToPage(target)
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
                containerColor = SpaceBlack,
                modifier = Modifier.border(1.dp, GlassWhite10, RoundedCornerShape(topStart = 16.dp, topEnd = 16.dp))
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
                                    tint = if (selected) Gold else Slate400
                                )
                            }
                        },
                        label = {
                            Text(
                                text = screen.title,
                                color = if (selected) Gold else Slate400,
                                fontSize = 10.sp
                            )
                        },
                        colors = NavigationBarItemDefaults.colors(
                            indicatorColor = Color(0x11C5A059)
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
                else -> {}
            }
        }
    }
}
