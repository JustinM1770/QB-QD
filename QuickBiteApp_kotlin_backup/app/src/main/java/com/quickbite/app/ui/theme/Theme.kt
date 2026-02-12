package com.quickbite.app.ui.theme

import androidx.compose.material3.*
import androidx.compose.runtime.Composable

private val LightColorScheme = lightColorScheme(
    primary = Orange,
    onPrimary = androidx.compose.ui.graphics.Color.White,
    primaryContainer = OrangeLight,
    secondary = Green,
    onSecondary = androidx.compose.ui.graphics.Color.White,
    background = Gray50,
    surface = androidx.compose.ui.graphics.Color.White,
    onBackground = Gray900,
    onSurface = Gray900,
    error = Red,
    outline = Gray400
)

@Composable
fun QuickBiteTheme(content: @Composable () -> Unit) {
    MaterialTheme(
        colorScheme = LightColorScheme,
        typography = Typography,
        content = content
    )
}
