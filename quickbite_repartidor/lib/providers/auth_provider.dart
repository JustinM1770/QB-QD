import 'package:flutter/material.dart';
import 'package:shared_preferences/shared_preferences.dart';
import '../services/auth_service.dart';
import 'package:shared/shared.dart';

class AuthProvider extends ChangeNotifier {
  final AuthService _authService = AuthService();

  Usuario? _usuario;
  bool _isLoading = false;
  String? _error;
  bool _isOnline = false;

  Usuario? get usuario => _usuario;
  bool get isLoading => _isLoading;
  String? get error => _error;
  bool get isAuthenticated => _usuario != null;
  bool get isOnline => _isOnline;

  // Login
  Future<bool> login(String email, String password) async {
    _isLoading = true;
    _error = null;
    notifyListeners();

    try {
      _usuario = await _authService.login(email, password);
      await _guardarToken(_usuario!.token ?? '');
      await _guardarUsuarioId(_usuario!.id);
      _isLoading = false;
      notifyListeners();
      return true;
    } catch (e) {
      _error = e.toString().replaceAll('Exception: ', '');
      _isLoading = false;
      notifyListeners();
      return false;
    }
  }

  // Registro
  Future<bool> register({
    required String nombre,
    required String email,
    required String password,
    required String telefono,
    String? vehiculo,
  }) async {
    _isLoading = true;
    _error = null;
    notifyListeners();

    try {
      _usuario = await _authService.register(
        nombre: nombre,
        email: email,
        password: password,
        telefono: telefono,
        vehiculo: vehiculo,
      );
      await _guardarToken(_usuario!.token ?? '');
      await _guardarUsuarioId(_usuario!.id);
      _isLoading = false;
      notifyListeners();
      return true;
    } catch (e) {
      _error = e.toString().replaceAll('Exception: ', '');
      _isLoading = false;
      notifyListeners();
      return false;
    }
  }

  // Logout
  Future<void> logout() async {
    _usuario = null;
    _isOnline = false;
    await _limpiarToken();
    notifyListeners();
  }

  // Cambiar estado online/offline
  void toggleOnlineStatus() {
    _isOnline = !_isOnline;
    notifyListeners();
    // TODO: Actualizar estado en servidor
  }

  void setOnlineStatus(bool status) {
    _isOnline = status;
    notifyListeners();
  }

  // Guardar token
  Future<void> _guardarToken(String token) async {
    final prefs = await SharedPreferences.getInstance();
    await prefs.setString('auth_token', token);
  }

  // Guardar ID de usuario
  Future<void> _guardarUsuarioId(int id) async {
    final prefs = await SharedPreferences.getInstance();
    await prefs.setInt('usuario_id', id);
  }

  // Limpiar token
  Future<void> _limpiarToken() async {
    final prefs = await SharedPreferences.getInstance();
    await prefs.remove('auth_token');
    await prefs.remove('usuario_id');
  }

  // Verificar si hay sesión activa
  Future<void> verificarSesion() async {
    final prefs = await SharedPreferences.getInstance();
    final token = prefs.getString('auth_token');
    final usuarioId = prefs.getInt('usuario_id');

    if (token != null && usuarioId != null) {
      // TODO: Validar token con servidor
      // Por ahora solo recreamos usuario básico
      _usuario = Usuario(
        id: usuarioId,
        nombre: 'Repartidor',
        email: '',
        tipo: 'repartidor',
        token: token,
      );
      notifyListeners();
    }
  }

  void limpiarError() {
    _error = null;
    notifyListeners();
  }
}
