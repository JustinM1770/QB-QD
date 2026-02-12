import 'package:flutter/material.dart';

class AppColors {
  static const Color primary = Color(0xFFFF6B35);      // Naranja QuickBite
  static const Color secondary = Color(0xFF2D3142);    // Gris oscuro
  static const Color accent = Color(0xFF4ECDC4);       // Verde azulado
  static const Color background = Color(0xFFF7F7F7);   // Gris claro
  static const Color success = Color(0xFF4CAF50);      // Verde
  static const Color warning = Color(0xFFFF9800);      // Naranja
  static const Color error = Color(0xFFE53935);        // Rojo
  static const Color online = Color(0xFF4CAF50);       // Verde online
  static const Color offline = Color(0xFF9E9E9E);      // Gris offline
}

class AppTheme {
  static ThemeData lightTheme = ThemeData(
    primaryColor: AppColors.primary,
    colorScheme: ColorScheme.fromSeed(
      seedColor: AppColors.primary,
      brightness: Brightness.light,
    ),
    useMaterial3: true,

    appBarTheme: const AppBarTheme(
      backgroundColor: AppColors.primary,
      foregroundColor: Colors.white,
      elevation: 0,
      centerTitle: true,
    ),

    elevatedButtonTheme: ElevatedButtonThemeData(
      style: ElevatedButton.styleFrom(
        backgroundColor: AppColors.primary,
        foregroundColor: Colors.white,
        padding: const EdgeInsets.symmetric(horizontal: 32, vertical: 16),
        shape: RoundedRectangleBorder(
          borderRadius: BorderRadius.circular(12),
        ),
        elevation: 2,
      ),
    ),

    cardTheme: CardTheme(
      elevation: 2,
      shape: RoundedRectangleBorder(
        borderRadius: BorderRadius.circular(12),
      ),
      margin: const EdgeInsets.symmetric(horizontal: 16, vertical: 8),
    ),

    inputDecorationTheme: InputDecorationTheme(
      border: OutlineInputBorder(
        borderRadius: BorderRadius.circular(12),
      ),
      filled: true,
      fillColor: Colors.white,
      contentPadding: const EdgeInsets.symmetric(horizontal: 16, vertical: 16),
    ),
  );
}
