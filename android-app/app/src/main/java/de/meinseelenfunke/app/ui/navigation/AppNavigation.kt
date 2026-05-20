package de.meinseelenfunke.app.ui.navigation

import androidx.compose.foundation.layout.padding
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.Build
import androidx.compose.material.icons.filled.Person
import androidx.compose.material3.Icon
import androidx.compose.material3.NavigationBar
import androidx.compose.material3.NavigationBarItem
import androidx.compose.material3.NavigationBarItemDefaults
import androidx.compose.material3.Scaffold
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.getValue
import androidx.compose.ui.Modifier
import androidx.compose.ui.graphics.vector.ImageVector
import androidx.navigation.NavGraph.Companion.findStartDestination
import androidx.navigation.NavHostController
import androidx.navigation.compose.NavHost
import androidx.navigation.compose.composable
import androidx.navigation.compose.currentBackStackEntryAsState
import androidx.navigation.compose.rememberNavController
import de.meinseelenfunke.app.di.ServiceLocator
import de.meinseelenfunke.app.ui.screens.ChatScreen
import de.meinseelenfunke.app.ui.screens.LocalAiScreen
import de.meinseelenfunke.app.ui.screens.LoginScreen
import de.meinseelenfunke.app.ui.theme.Cyan500
import de.meinseelenfunke.app.ui.theme.Slate400
import de.meinseelenfunke.app.ui.theme.Slate700
import de.meinseelenfunke.app.ui.theme.Slate800
import de.meinseelenfunke.app.ui.theme.Slate900

sealed class Screen(val route: String, val title: String = "", val icon: ImageVector? = null) {
    object Login : Screen("login")
    object Chat : Screen("chat", "Remote Chat", Icons.Default.Person)
    object LocalAi : Screen("local_ai", "Local AI", Icons.Default.Build)
}

val bottomNavItems = listOf(
    Screen.Chat,
    Screen.LocalAi
)

@Composable
fun AppNavigation(
    navController: NavHostController = rememberNavController(),
    startDestination: String = if (ServiceLocator.authRepository.isLoggedIn()) Screen.Chat.route else Screen.Login.route
) {
    val navBackStackEntry by navController.currentBackStackEntryAsState()
    val currentRoute = navBackStackEntry?.destination?.route

    Scaffold(
        bottomBar = {
            if (currentRoute != Screen.Login.route && currentRoute != null) {
                NavigationBar(
                    containerColor = Slate800
                ) {
                    bottomNavItems.forEach { screen ->
                        val selected = currentRoute == screen.route
                        NavigationBarItem(
                            selected = selected,
                            onClick = {
                                navController.navigate(screen.route) {
                                    popUpTo(navController.graph.findStartDestination().id) {
                                        saveState = true
                                    }
                                    launchSingleTop = true
                                    restoreState = true
                                }
                            },
                            icon = {
                                screen.icon?.let {
                                    Icon(
                                        imageVector = it,
                                        contentDescription = screen.title,
                                        tint = if (selected) Cyan500 else Slate400
                                    )
                                }
                            },
                            label = {
                                Text(
                                    text = screen.title,
                                    color = if (selected) Cyan500 else Slate400
                                )
                            },
                            colors = NavigationBarItemDefaults.colors(
                                indicatorColor = Slate700
                            )
                        )
                    }
                }
            }
        }
    ) { innerPadding ->
        NavHost(
            navController = navController,
            startDestination = startDestination,
            modifier = Modifier.padding(innerPadding)
        ) {
            composable(Screen.Login.route) {
                LoginScreen(
                    onLoginSuccess = {
                        navController.navigate(Screen.Chat.route) {
                            popUpTo(Screen.Login.route) { inclusive = true }
                        }
                    }
                )
            }
            composable(Screen.Chat.route) {
                ChatScreen(
                    onLogout = {
                        ServiceLocator.authRepository.logout()
                        navController.navigate(Screen.Login.route) {
                            popUpTo(0) { inclusive = true }
                        }
                    }
                )
            }
            composable(Screen.LocalAi.route) {
                LocalAiScreen()
            }
        }
    }
}
