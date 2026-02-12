import 'dart:async';
import 'package:flutter/material.dart';
import '../services/pedido_service.dart';
import '../config/api_config.dart';
import 'package:shared/shared.dart';

class PedidoProvider extends ChangeNotifier {
  final PedidoService _pedidoService = PedidoService();

  List<Pedido> _pedidosDisponibles = [];
  List<Pedido> _pedidosActivos = []; // MULTIPEDIDO
  List<Pedido> _historial = [];
  bool _isLoading = false;
  String? _error;
  Timer? _pollingTimer;
  bool _pollingEnabled = false;

  List<Pedido> get pedidosDisponibles => _pedidosDisponibles;
  List<Pedido> get pedidosActivos => _pedidosActivos;
  Pedido? get pedidoActivo => _pedidosActivos.isNotEmpty ? _pedidosActivos.first : null;
  List<Pedido> get historial => _historial;
  bool get isLoading => _isLoading;
  String? get error => _error;
  bool get tienePedidoActivo => _pedidosActivos.isNotEmpty;
  int get cantidadPedidosActivos => _pedidosActivos.length;

  @override
  void dispose() {
    detenerPolling();
    super.dispose();
  }

  void iniciarPolling() {
    if (_pollingEnabled) return;
    _pollingEnabled = true;
    _pollingTimer = Timer.periodic(ApiConfig.pollingInterval, (timer) async {
      if (!_pollingEnabled) {
        timer.cancel();
        return;
      }
      try {
        await cargarPedidosDisponibles(silent: true);
      } catch (e) {
        debugPrint('Error en polling: \$e');
      }
    });
    debugPrint('🔄 Polling iniciado cada ${ApiConfig.pollingInterval.inSeconds}s');
  }

  void detenerPolling() {
    _pollingEnabled = false;
    _pollingTimer?.cancel();
    _pollingTimer = null;
  }

  Future<void> cargarPedidosDisponibles({bool silent = false}) async {
    if (!silent) {
      _isLoading = true;
      _error = null;
      notifyListeners();
    }
    try {
      _pedidosDisponibles = await _pedidoService.obtenerPedidosDisponibles();
      if (!silent) _isLoading = false;
      notifyListeners();
    } catch (e) {
      _error = e.toString().replaceAll('Exception: ', '');
      if (!silent) _isLoading = false;
      notifyListeners();
    }
  }

  Future<void> cargarPedidosActivos(int repartidorId, {bool silent = false}) async {
    if (!silent) _isLoading = true;
    try {
      _pedidosActivos = await _pedidoService.obtenerPedidosActivos(repartidorId);
      if (!silent) _isLoading = false;
      notifyListeners();
    } catch (e) {
      _error = e.toString().replaceAll('Exception: ', '');
      if (!silent) _isLoading = false;
      notifyListeners();
    }
  }

  Future<bool> aceptarPedido(int pedidoId, int repartidorId) async {
    _isLoading = true;
    _error = null;
    notifyListeners();
    try {
      final exito = await _pedidoService.aceptarPedido(pedidoId, repartidorId);
      if (exito) {
        final pedido = _pedidosDisponibles.firstWhere((p) => p.id == pedidoId);
        _pedidosActivos.add(pedido);
        _pedidosDisponibles.removeWhere((p) => p.id == pedidoId);
      }
      _isLoading = false;
      notifyListeners();
      return exito;
    } catch (e) {
      _error = e.toString().replaceAll('Exception: ', '');
      _isLoading = false;
      notifyListeners();
      return false;
    }
  }

  Future<void> cargarPedidoActivo(int repartidorId) async {
    await cargarPedidosActivos(repartidorId);
  }

  Future<bool> actualizarEstado(int pedidoId, String nuevoEstado) async {
    _isLoading = true;
    notifyListeners();
    try {
      final exito = await _pedidoService.actualizarEstado(pedidoId, nuevoEstado);
      if (exito && nuevoEstado == 'entregado') {
        final pedido = _pedidosActivos.firstWhere((p) => p.id == pedidoId);
        _historial.insert(0, pedido);
        _pedidosActivos.removeWhere((p) => p.id == pedidoId);
      }
      _isLoading = false;
      notifyListeners();
      return exito;
    } catch (e) {
      _error = e.toString().replaceAll('Exception: ', '');
      _isLoading = false;
      notifyListeners();
      return false;
    }
  }

  Future<void> cargarHistorial(int repartidorId) async {
    _isLoading = true;
    notifyListeners();
    try {
      _historial = await _pedidoService.obtenerHistorial(repartidorId);
      _isLoading = false;
      notifyListeners();
    } catch (e) {
      _error = e.toString().replaceAll('Exception: ', '');
      _isLoading = false;
      notifyListeners();
    }
  }

  void limpiarError() {
    _error = null;
    notifyListeners();
  }

  Pedido? obtenerPedidoPorId(int pedidoId) {
    try {
      return _pedidosActivos.firstWhere((p) => p.id == pedidoId);
    } catch (e) {
      return null;
    }
  }
}
