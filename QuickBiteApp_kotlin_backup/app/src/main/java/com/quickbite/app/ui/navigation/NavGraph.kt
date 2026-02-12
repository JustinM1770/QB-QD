package com.quickbite.app.ui.navigation

import androidx.compose.foundation.layout.padding
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.*
import androidx.compose.material3.*
import androidx.compose.runtime.*
import androidx.compose.ui.Modifier
import androidx.compose.ui.graphics.vector.ImageVector
import androidx.navigation.NavHostController
import androidx.navigation.NavType
import androidx.navigation.compose.*
import androidx.navigation.navArgument

sealed class Screen(val route: String) {
    data object Login : Screen("login")
    data object Register : Screen("register")
    data object Home : Screen("home")
    data object Search : Screen("search?query={query}")
    data object Restaurant : Screen("restaurant/{id}")
    data object Cart : Screen("cart")
    data object Checkout : Screen("checkout")
    data object Orders : Screen("orders")
    data object OrderDetail : Screen("order/{id}")
    data object Profile : Screen("profile")
}

data class BottomNavItem(
    val label: String,
    val icon: ImageVector,
    val route: String
)

val bottomNavItems = listOf(
    BottomNavItem("Inicio", Icons.Default.Home, Screen.Home.route),
    BottomNavItem("Buscar", Icons.Default.Search, "search"),
    BottomNavItem("Pedidos", Icons.Default.Receipt, Screen.Orders.route),
    BottomNavItem("Perfil", Icons.Default.Person, Screen.Profile.route)
)

@OptIn(ExperimentalMaterial3Api::class)
@Composable
fun QuickBiteNavGraph() {
    val navController = rememberNavController()
    val currentRoute = navController.currentBackStackEntryAsState().value?.destination?.route

    val showBottomBar = currentRoute in listOf(
        Screen.Home.route, "search", Screen.Orders.route, Screen.Profile.route
    )

    Scaffold(
        bottomBar = {
            if (showBottomBar) {
                NavigationBar {
                    bottomNavItems.forEach { item ->
                        NavigationBarItem(
                            icon = { Icon(item.icon, contentDescription = item.label) },
                            label = { Text(item.label) },
                            selected = currentRoute == item.route,
                            onClick = {
                                navController.navigate(item.route) {
                                    popUpTo(Screen.Home.route) { saveState = true }
                                    launchSingleTop = true
                                    restoreState = true
                                }
                            }
                        )
                    }
                }
            }
        }
    ) { innerPadding ->
        NavHost(
            navController = navController,
            startDestination = Screen.Login.route,
            modifier = Modifier.padding(innerPadding)
        ) {
            composable(Screen.Login.route) {
                com.quickbite.app.ui.screens.auth.LoginScreen(
                    onLoginSuccess = {
                        navController.navigate(Screen.Home.route) {
                            popUpTo(Screen.Login.route) { inclusive = true }
                        }
                    },
                    onNavigateToRegister = {
                        navController.navigate(Screen.Register.route)
                    }
                )
            }

            composable(Screen.Register.route) {
                com.quickbite.app.ui.screens.auth.RegisterScreen(
                    onRegisterSuccess = {
                        navController.navigate(Screen.Home.route) {
                            popUpTo(Screen.Login.route) { inclusive = true }
                        }
                    },
                    onNavigateBack = { navController.popBackStack() }
                )
            }

            composable(Screen.Home.route) {
                com.quickbite.app.ui.screens.home.HomeScreen(
                    onNavigateToRestaurant = { id ->
                        navController.navigate("restaurant/$id")
                    },
                    onNavigateToSearch = { query ->
                        navController.navigate("search?query=$query")
                    },
                    onNavigateToCart = {
                        navController.navigate(Screen.Cart.route)
                    }
                )
            }

            composable(
                "search?query={query}",
                arguments = listOf(navArgument("query") { defaultValue = ""; type = NavType.StringType })
            ) { backStackEntry ->
                val query = backStackEntry.arguments?.getString("query") ?: ""
                com.quickbite.app.ui.screens.search.SearchScreen(
                    initialQuery = query,
                    onNavigateToRestaurant = { id ->
                        navController.navigate("restaurant/$id")
                    }
                )
            }

            // Also handle "search" without query param
            composable("search") {
                com.quickbite.app.ui.screens.search.SearchScreen(
                    initialQuery = "",
                    onNavigateToRestaurant = { id ->
                        navController.navigate("restaurant/$id")
                    }
                )
            }

            composable(
                "restaurant/{id}",
                arguments = listOf(navArgument("id") { type = NavType.IntType })
            ) { backStackEntry ->
                val id = backStackEntry.arguments?.getInt("id") ?: 0
                com.quickbite.app.ui.screens.restaurant.RestaurantScreen(
                    negocioId = id,
                    onNavigateBack = { navController.popBackStack() },
                    onNavigateToCart = { navController.navigate(Screen.Cart.route) }
                )
            }

            composable(Screen.Cart.route) {
                com.quickbite.app.ui.screens.cart.CartScreen(
                    onNavigateBack = { navController.popBackStack() },
                    onNavigateToCheckout = { navController.navigate(Screen.Checkout.route) }
                )
            }

            composable(Screen.Checkout.route) {
                com.quickbite.app.ui.screens.checkout.CheckoutScreen(
                    onNavigateBack = { navController.popBackStack() },
                    onPedidoCreado = { pedidoId ->
                        navController.navigate("order/$pedidoId") {
                            popUpTo(Screen.Home.route)
                        }
                    }
                )
            }

            composable(Screen.Orders.route) {
                com.quickbite.app.ui.screens.orders.OrderHistoryScreen(
                    onNavigateToDetail = { id ->
                        navController.navigate("order/$id")
                    }
                )
            }

            composable(
                "order/{id}",
                arguments = listOf(navArgument("id") { type = NavType.IntType })
            ) { backStackEntry ->
                val id = backStackEntry.arguments?.getInt("id") ?: 0
                com.quickbite.app.ui.screens.orders.OrderDetailScreen(
                    pedidoId = id,
                    onNavigateBack = { navController.popBackStack() }
                )
            }

            composable(Screen.Profile.route) {
                com.quickbite.app.ui.screens.profile.ProfileScreen(
                    onLogout = {
                        navController.navigate(Screen.Login.route) {
                            popUpTo(0) { inclusive = true }
                        }
                    }
                )
            }
        }
    }
}
