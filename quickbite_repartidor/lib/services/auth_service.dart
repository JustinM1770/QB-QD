import 'dart:convert';
import 'package:http/http.dart' as http;
import '../config/api_config.dart';
import 'package:shared/shared.dart';

class AuthService {
  Future<Usuario> login(String email, String password) async {
    try {
      final response = await http.post(
        Uri.parse(ApiConfig.login),
        headers: {'Content-Type': 'application/json'},
        body: json.encode({
          'email': email,
          'password': password,
          'tipo': 'repartidor',
        }),
      ).timeout(ApiConfig.timeout);

      if (response.statusCode == 200) {
        final data = json.decode(response.body);
        if (data['success'] == true) {
          return Usuario.fromJson(data['usuario']);
        } else {
          throw Exception(data['message'] ?? 'Error en login');
        }
      } else {
        throw Exception('Error de conexión: ${response.statusCode}');
      }
    } catch (e) {
      throw Exception('Error al conectar con el servidor: $e');
    }
  }

  Future<Usuario> register({
    required String nombre,
    required String email,
    required String password,
    required String telefono,
    String? vehiculo,
  }) async {
    try {
      final response = await http.post(
        Uri.parse(ApiConfig.register),
        headers: {'Content-Type': 'application/json'},
        body: json.encode({
          'nombre': nombre,
          'email': email,
          'password': password,
          'telefono': telefono,
          'vehiculo': vehiculo ?? 'moto',
          'tipo': 'repartidor',
        }),
      ).timeout(ApiConfig.timeout);

      if (response.statusCode == 200) {
        final data = json.decode(response.body);
        if (data['success'] == true) {
          return Usuario.fromJson(data['usuario']);
        } else {
          throw Exception(data['message'] ?? 'Error en registro');
        }
      } else {
        throw Exception('Error de conexión: ${response.statusCode}');
      }
    } catch (e) {
      throw Exception('Error al conectar con el servidor: $e');
    }
  }
}
