import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import 'package:google_maps_flutter/google_maps_flutter.dart';
import 'package:url_launcher/url_launcher.dart';
import '../../providers/pedido_provider.dart';
import '../../config/theme.dart';
import '../dashboard/dashboard_screen.dart';

class PedidoActivoScreen extends StatefulWidget {
  const PedidoActivoScreen({super.key});

  @override
  State<PedidoActivoScreen> createState() => _PedidoActivoScreenState();
}

class _PedidoActivoScreenState extends State<PedidoActivoScreen> {
  GoogleMapController? _mapController;
  Set<Marker> _markers = {};

  // Ubicaciones de ejemplo (en producción vendrían del pedido)
  static const LatLng _restaurante = LatLng(19.4326, -99.1332); // CDMX Centro
  static const LatLng _cliente = LatLng(19.4284, -99.1276); // Cerca del centro

  @override
  void initState() {
    super.initState();
    _inicializarMapa();
  }

  void _inicializarMapa() {
    _markers = {
      Marker(
        markerId: const MarkerId('restaurante'),
        position: _restaurante,
        icon: BitmapDescriptor.defaultMarkerWithHue(BitmapDescriptor.hueOrange),
        infoWindow: const InfoWindow(
          title: 'Restaurante',
          snippet: 'Recoge el pedido aquí',
        ),
      ),
      Marker(
        markerId: const MarkerId('cliente'),
        position: _cliente,
        icon: BitmapDescriptor.defaultMarkerWithHue(BitmapDescriptor.hueRed),
        infoWindow: const InfoWindow(
          title: 'Cliente',
          snippet: 'Entrega el pedido aquí',
        ),
      ),
    };
  }

  @override
  Widget build(BuildContext context) {
    final pedidoProvider = context.watch<PedidoProvider>();
    final pedido = pedidoProvider.pedidoActivo;

    if (pedido == null) {
      return Scaffold(
        appBar: AppBar(title: const Text('Pedido')),
        body: const Center(
          child: Text('No hay pedido activo'),
        ),
      );
    }

    return Scaffold(
      body: SafeArea(
        child: Column(
          children: [
            // Mapa
            Expanded(
              flex: 2,
              child: Stack(
                children: [
                  GoogleMap(
                    initialCameraPosition: const CameraPosition(
                      target: _restaurante,
                      zoom: 14,
                    ),
                    markers: _markers,
                    myLocationEnabled: true,
                    myLocationButtonEnabled: true,
                    zoomControlsEnabled: false,
                    onMapCreated: (controller) {
                      _mapController = controller;
                    },
                  ),

                  // Botón volver
                  Positioned(
                    top: 16,
                    left: 16,
                    child: FloatingActionButton(
                      mini: true,
                      backgroundColor: Colors.white,
                      onPressed: () => Navigator.pop(context),
                      child: const Icon(Icons.arrow_back, color: Colors.black),
                    ),
                  ),

                  // Botón navegación
                  Positioned(
                    top: 16,
                    right: 16,
                    child: FloatingActionButton(
                      mini: true,
                      backgroundColor: AppColors.primary,
                      onPressed: _abrirGoogleMaps,
                      child: const Icon(Icons.navigation),
                    ),
                  ),
                ],
              ),
            ),

            // Información del pedido
            Expanded(
              flex: 1,
              child: Container(
                padding: const EdgeInsets.all(16),
                decoration: BoxDecoration(
                  color: Colors.white,
                  boxShadow: [
                    BoxShadow(
                      color: Colors.black.withOpacity(0.1),
                      blurRadius: 10,
                      offset: const Offset(0, -5),
                    ),
                  ],
                ),
                child: SingleChildScrollView(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.stretch,
                    children: [
                      // Título
                      Text(
                        'Pedido #${pedido.id}',
                        style: const TextStyle(
                          fontSize: 24,
                          fontWeight: FontWeight.bold,
                        ),
                      ),
                      const SizedBox(height: 8),

                      // Total
                      Container(
                        padding: const EdgeInsets.all(12),
                        decoration: BoxDecoration(
                          color: AppColors.primary.withOpacity(0.1),
                          borderRadius: BorderRadius.circular(8),
                        ),
                        child: Row(
                          mainAxisAlignment: MainAxisAlignment.spaceBetween,
                          children: [
                            const Text(
                              'Total:',
                              style: TextStyle(
                                fontSize: 18,
                                fontWeight: FontWeight.bold,
                              ),
                            ),
                            Text(
                              '\$${pedido.total.toStringAsFixed(2)}',
                              style: const TextStyle(
                                fontSize: 24,
                                fontWeight: FontWeight.bold,
                                color: AppColors.primary,
                              ),
                            ),
                          ],
                        ),
                      ),
                      const SizedBox(height: 16),

                      // Dirección
                      Row(
                        children: [
                          const Icon(Icons.location_on, color: AppColors.error),
                          const SizedBox(width: 8),
                          Expanded(
                            child: Text(
                              pedido.direccionEntrega,
                              style: const TextStyle(fontSize: 16),
                            ),
                          ),
                        ],
                      ),
                      const SizedBox(height: 16),

                      // Botón llamar
                      OutlinedButton.icon(
                        onPressed: _llamarCliente,
                        icon: const Icon(Icons.phone),
                        label: const Text('Llamar al Cliente'),
                      ),
                      const SizedBox(height: 16),

                      // Botones de estado
                      _buildBotonesEstado(pedido.estado),
                    ],
                  ),
                ),
              ),
            ),
          ],
        ),
      ),
    );
  }

  Widget _buildBotonesEstado(String estadoActual) {
    if (estadoActual == 'listo') {
      return ElevatedButton.icon(
        onPressed: () => _cambiarEstado('en_camino'),
        icon: const Icon(Icons.directions_bike),
        label: const Text('Iniciar Entrega'),
      );
    } else if (estadoActual == 'en_camino') {
      return ElevatedButton.icon(
        onPressed: () => _cambiarEstado('entregado'),
        icon: const Icon(Icons.check_circle),
        label: const Text('Marcar como Entregado'),
        style: ElevatedButton.styleFrom(
          backgroundColor: AppColors.success,
        ),
      );
    } else {
      return const SizedBox.shrink();
    }
  }

  Future<void> _cambiarEstado(String nuevoEstado) async {
    final confirmar = await showDialog<bool>(
      context: context,
      builder: (context) => AlertDialog(
        title: const Text('Confirmar'),
        content: Text(_getMensajeConfirmacion(nuevoEstado)),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(context, false),
            child: const Text('Cancelar'),
          ),
          ElevatedButton(
            onPressed: () => Navigator.pop(context, true),
            child: const Text('Confirmar'),
          ),
        ],
      ),
    );

    if (confirmar == true && mounted) {
      final pedidoProvider = context.read<PedidoProvider>();
      final exito = await pedidoProvider.actualizarEstado(nuevoEstado);

      if (!mounted) return;

      if (exito) {
        if (nuevoEstado == 'entregado') {
          ScaffoldMessenger.of(context).showSnackBar(
            const SnackBar(
              content: Text('¡Pedido entregado con éxito!'),
              backgroundColor: AppColors.success,
            ),
          );

          // Volver al dashboard
          Navigator.of(context).pushAndRemoveUntil(
            MaterialPageRoute(builder: (_) => const DashboardScreen()),
            (route) => false,
          );
        } else {
          ScaffoldMessenger.of(context).showSnackBar(
            SnackBar(
              content: Text('Estado actualizado a: $nuevoEstado'),
              backgroundColor: AppColors.success,
            ),
          );
          setState(() {});
        }
      } else {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            content: Text(
              pedidoProvider.error ?? 'Error al actualizar estado',
            ),
            backgroundColor: AppColors.error,
          ),
        );
      }
    }
  }

  String _getMensajeConfirmacion(String estado) {
    switch (estado) {
      case 'en_camino':
        return '¿Ya recogiste el pedido y estás en camino al cliente?';
      case 'entregado':
        return '¿Ya entregaste el pedido al cliente?';
      default:
        return '¿Confirmas el cambio de estado?';
    }
  }

  void _abrirGoogleMaps() async {
    const lat = _cliente.latitude;
    const lng = _cliente.longitude;
    final uri = Uri.parse('google.navigation:q=$lat,$lng&mode=d');

    if (await canLaunchUrl(uri)) {
      await launchUrl(uri);
    } else {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(
            content: Text('No se pudo abrir Google Maps'),
            backgroundColor: AppColors.error,
          ),
        );
      }
    }
  }

  void _llamarCliente() async {
    const telefono = '5512345678'; // En producción vendría del pedido
    final uri = Uri.parse('tel:$telefono');

    if (await canLaunchUrl(uri)) {
      await launchUrl(uri);
    } else {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(
            content: Text('No se pudo realizar la llamada'),
            backgroundColor: AppColors.error,
          ),
        );
      }
    }
  }
}
