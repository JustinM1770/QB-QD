import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import '../../providers/auth_provider.dart';
import '../../providers/pedido_provider.dart';
import '../../config/theme.dart';
import 'package:shared/shared.dart';
import 'pedido_activo_screen.dart';

class PedidosDisponiblesScreen extends StatefulWidget {
  const PedidosDisponiblesScreen({super.key});

  @override
  State<PedidosDisponiblesScreen> createState() =>
      _PedidosDisponiblesScreenState();
}

class _PedidosDisponiblesScreenState extends State<PedidosDisponiblesScreen> {
  @override
  void initState() {
    super.initState();
    _cargarPedidos();
  }

  Future<void> _cargarPedidos() async {
    await context.read<PedidoProvider>().cargarPedidosDisponibles();
  }

  Future<void> _aceptarPedido(Pedido pedido) async {
    final confirmar = await showDialog<bool>(
      context: context,
      builder: (context) => AlertDialog(
        title: const Text('Aceptar Pedido'),
        content: Text(
          '¿Deseas aceptar el pedido #${pedido.id}?\n\n'
          'Total: \$${pedido.total.toStringAsFixed(2)}\n'
          'Destino: ${pedido.direccionEntrega}',
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(context, false),
            child: const Text('Cancelar'),
          ),
          ElevatedButton(
            onPressed: () => Navigator.pop(context, true),
            child: const Text('Aceptar Pedido'),
          ),
        ],
      ),
    );

    if (confirmar == true && mounted) {
      final authProvider = context.read<AuthProvider>();
      final pedidoProvider = context.read<PedidoProvider>();

      final exito = await pedidoProvider.aceptarPedido(
        pedido.id,
        authProvider.usuario!.id,
      );

      if (!mounted) return;

      if (exito) {
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(
            content: Text('¡Pedido aceptado! Ve a entregarlo'),
            backgroundColor: AppColors.success,
          ),
        );

        Navigator.pushReplacement(
          context,
          MaterialPageRoute(
            builder: (_) => const PedidoActivoScreen(),
          ),
        );
      } else {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            content: Text(
              pedidoProvider.error ?? 'Error al aceptar pedido',
            ),
            backgroundColor: AppColors.error,
          ),
        );
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: const Text('Pedidos Disponibles'),
      ),
      body: Consumer<PedidoProvider>(
        builder: (context, pedidoProvider, child) {
          if (pedidoProvider.isLoading) {
            return const Center(child: CircularProgressIndicator());
          }

          if (pedidoProvider.pedidosDisponibles.isEmpty) {
            return Center(
              child: Column(
                mainAxisAlignment: MainAxisAlignment.center,
                children: [
                  const Icon(
                    Icons.inbox,
                    size: 80,
                    color: Colors.grey,
                  ),
                  const SizedBox(height: 16),
                  const Text(
                    'No hay pedidos disponibles',
                    style: TextStyle(fontSize: 18, color: Colors.grey),
                  ),
                  const SizedBox(height: 32),
                  ElevatedButton.icon(
                    onPressed: _cargarPedidos,
                    icon: const Icon(Icons.refresh),
                    label: const Text('Actualizar'),
                  ),
                ],
              ),
            );
          }

          return RefreshIndicator(
            onRefresh: _cargarPedidos,
            child: ListView.builder(
              padding: const EdgeInsets.all(16),
              itemCount: pedidoProvider.pedidosDisponibles.length,
              itemBuilder: (context, index) {
                final pedido = pedidoProvider.pedidosDisponibles[index];
                return _buildPedidoCard(pedido);
              },
            ),
          );
        },
      ),
    );
  }

  Widget _buildPedidoCard(Pedido pedido) {
    return Card(
      margin: const EdgeInsets.only(bottom: 12),
      child: InkWell(
        onTap: () => _mostrarDetalles(pedido),
        child: Padding(
          padding: const EdgeInsets.all(16),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              // Encabezado
              Row(
                mainAxisAlignment: MainAxisAlignment.spaceBetween,
                children: [
                  Text(
                    'Pedido #${pedido.id}',
                    style: const TextStyle(
                      fontSize: 18,
                      fontWeight: FontWeight.bold,
                    ),
                  ),
                  Container(
                    padding: const EdgeInsets.symmetric(
                      horizontal: 12,
                      vertical: 6,
                    ),
                    decoration: BoxDecoration(
                      color: AppColors.primary,
                      borderRadius: BorderRadius.circular(20),
                    ),
                    child: Text(
                      '\$${pedido.total.toStringAsFixed(2)}',
                      style: const TextStyle(
                        color: Colors.white,
                        fontWeight: FontWeight.bold,
                      ),
                    ),
                  ),
                ],
              ),
              const SizedBox(height: 12),

              // Dirección
              Row(
                children: [
                  const Icon(Icons.location_on, color: AppColors.error, size: 20),
                  const SizedBox(width: 8),
                  Expanded(
                    child: Text(
                      pedido.direccionEntrega,
                      style: const TextStyle(fontSize: 14),
                    ),
                  ),
                ],
              ),
              const SizedBox(height: 8),

              // Información adicional
              if (pedido.instruccionesEntrega != null)
                Row(
                  children: [
                    const Icon(Icons.info_outline, color: Colors.grey, size: 20),
                    const SizedBox(width: 8),
                    Expanded(
                      child: Text(
                        pedido.instruccionesEntrega!,
                        style: const TextStyle(
                          fontSize: 12,
                          color: Colors.grey,
                        ),
                      ),
                    ),
                  ],
                ),

              const SizedBox(height: 12),

              // Botón Aceptar
              SizedBox(
                width: double.infinity,
                child: ElevatedButton.icon(
                  onPressed: () => _aceptarPedido(pedido),
                  icon: const Icon(Icons.check),
                  label: const Text('Aceptar Pedido'),
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }

  void _mostrarDetalles(Pedido pedido) {
    showModalBottomSheet(
      context: context,
      builder: (context) => Container(
        padding: const EdgeInsets.all(24),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(
              'Pedido #${pedido.id}',
              style: const TextStyle(
                fontSize: 24,
                fontWeight: FontWeight.bold,
              ),
            ),
            const Divider(height: 24),
            _buildDetalleRow(
              Icons.attach_money,
              'Total',
              '\$${pedido.total.toStringAsFixed(2)}',
            ),
            _buildDetalleRow(
              Icons.location_on,
              'Dirección',
              pedido.direccionEntrega,
            ),
            if (pedido.metodoPago != null)
              _buildDetalleRow(
                Icons.payment,
                'Pago',
                pedido.metodoPago!,
              ),
            const SizedBox(height: 16),
            SizedBox(
              width: double.infinity,
              child: ElevatedButton(
                onPressed: () {
                  Navigator.pop(context);
                  _aceptarPedido(pedido);
                },
                child: const Text('Aceptar Pedido'),
              ),
            ),
          ],
        ),
      ),
    );
  }

  Widget _buildDetalleRow(IconData icon, String label, String valor) {
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 8),
      child: Row(
        children: [
          Icon(icon, color: AppColors.primary, size: 20),
          const SizedBox(width: 12),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  label,
                  style: const TextStyle(
                    fontSize: 12,
                    color: Colors.grey,
                  ),
                ),
                Text(
                  valor,
                  style: const TextStyle(
                    fontSize: 16,
                    fontWeight: FontWeight.w500,
                  ),
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }
}
