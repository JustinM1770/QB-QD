import 'dart:convert';
import 'package:http/http.dart' as http;
import '../config/api_config.dart';
import 'package:shared/shared.dart';

class PedidoService {
  Future<List<Pedido>> obtenerPedidosDisponibles() async {
    try {
      final response = await http.get(
        Uri.parse(ApiConfig.pedidosDisponibles),
        headers: {'Content-Type': 'application/json'},
      ).timeout(ApiConfig.timeout);

      if (response.statusCode == 200) {
        final data = json.decode(response.body);
        if (data['success'] == true) {
          final List<dynamic> pedidosJson = data['pedidos'] ?? [];
          return pedidosJson.map((p) => Pedido.fromJson(p)).toList();
        } else {
          throw Exception(data['message'] ?? 'Error al obtener pedidos');
        }
      } else {
        throw Exception('Error de conexión: ${response.statusCode}');
      }
    } catch (e) {
      throw Exception('Error al obtener pedidos disponibles: $e');
    }
  }

  Future<bool> aceptarPedido(int pedidoId, int repartidorId) async {
    try {
      final response = await http.post(
        Uri.parse(ApiConfig.aceptarPedido),
        headers: {'Content-Type': 'application/json'},
        body: json.encode({
          'pedido_id': pedidoId,
          'repartidor_id': repartidorId,
        }),
      ).timeout(ApiConfig.timeout);

      if (response.statusCode == 200) {
        final data = json.decode(response.body);
        return data['success'] == true;
      } else {
        throw Exception('Error de conexión: ${response.statusCode}');
      }
    } catch (e) {
      throw Exception('Error al aceptar pedido: $e');
    }
  }

  Future<Pedido?> obtenerPedidoActivo(int repartidorId) async {
    try {
      final response = await http.get(
        Uri.parse('${ApiConfig.pedidoActivo}?repartidor_id=$repartidorId'),
        headers: {'Content-Type': 'application/json'},
      ).timeout(ApiConfig.timeout);

      if (response.statusCode == 200) {
        final data = json.decode(response.body);
        if (data['success'] == true && data['pedido'] != null) {
          return Pedido.fromJson(data['pedido']);
        }
        return null;
      } else {
        throw Exception('Error de conexión: ${response.statusCode}');
      }
    } catch (e) {
      throw Exception('Error al obtener pedido activo: $e');
    }
  }

  Future<bool> actualizarEstado(int pedidoId, String nuevoEstado) async {
    try {
      final response = await http.put(
        Uri.parse(ApiConfig.actualizarEstadoPedido),
        headers: {'Content-Type': 'application/json'},
        body: json.encode({
          'pedido_id': pedidoId,
          'estado': nuevoEstado,
        }),
      ).timeout(ApiConfig.timeout);

      if (response.statusCode == 200) {
        final data = json.decode(response.body);
        return data['success'] == true;
      } else {
        throw Exception('Error de conexión: ${response.statusCode}');
      }
    } catch (e) {
      throw Exception('Error al actualizar estado: $e');
    }
  }

  Future<List<Pedido>> obtenerHistorial(int repartidorId) async {
    try {
      final response = await http.get(
        Uri.parse('${ApiConfig.historialPedidos}?repartidor_id=$repartidorId'),
        headers: {'Content-Type': 'application/json'},
      ).timeout(ApiConfig.timeout);

      if (response.statusCode == 200) {
        final data = json.decode(response.body);
        if (data['success'] == true) {
          final List<dynamic> pedidosJson = data['pedidos'] ?? [];
          return pedidosJson.map((p) => Pedido.fromJson(p)).toList();
        } else {
          throw Exception(data['message'] ?? 'Error al obtener historial');
        }
      } else {
        throw Exception('Error de conexión: ${response.statusCode}');
      }
    } catch (e) {
      throw Exception('Error al obtener historial: $e');
    }
  }

  // NUEVO: Obtener TODOS los pedidos activos (MULTIPEDIDO)
  Future<List<Pedido>> obtenerPedidosActivos(int repartidorId) async {
    try {
      final response = await http.get(
        Uri.parse('${ApiConfig.pedidosActivos}?repartidor_id=$repartidorId'),
        headers: {'Content-Type': 'application/json'},
      ).timeout(ApiConfig.timeout);

      if (response.statusCode == 200) {
        final data = json.decode(response.body);
        if (data['success'] == true) {
          final List<dynamic> pedidosJson = data['pedidos'] ?? [];
          return pedidosJson.map((p) => Pedido.fromJson(p)).toList();
        }
        return [];
      } else {
        throw Exception('Error de conexión: ${response.statusCode}');
      }
    } catch (e) {
      throw Exception('Error al obtener pedidos activos: $e');
    }
  }
}
