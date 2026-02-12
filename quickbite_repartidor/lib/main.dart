import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import 'config/theme.dart';
import 'providers/auth_provider.dart';
import 'providers/pedido_provider.dart';
import 'screens/auth/login_screen.dart';

void main() {
  runApp(const QuickBiteRepartidorApp());
}

class QuickBiteRepartidorApp extends StatelessWidget {
  const QuickBiteRepartidorApp({super.key});

  @override
  Widget build(BuildContext context) {
    return MultiProvider(
      providers: [
        ChangeNotifierProvider(create: (_) => AuthProvider()),
        ChangeNotifierProvider(create: (_) => PedidoProvider()),
      ],
      child: MaterialApp(
        title: 'QuickBite Repartidor',
        theme: AppTheme.lightTheme,
        debugShowCheckedModeBanner: false,
        home: const LoginScreen(),
      ),
    );
  }
}
