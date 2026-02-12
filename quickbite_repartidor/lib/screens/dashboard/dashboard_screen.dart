import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import '../../providers/auth_provider.dart';
import '../../providers/pedido_provider.dart';
import '../../config/theme.dart';
import '../pedidos/pedidos_disponibles_screen.dart';
import '../pedidos/pedido_activo_screen.dart';

class DashboardScreen extends StatefulWidget {
  const DashboardScreen({super.key});

  @override
  State<DashboardScreen> createState() => _DashboardScreenState();
}

class _DashboardScreenState extends State<DashboardScreen> {
  @override
  void initState() {
    super.initState();
    _cargarDatos();
  }

  Future<void> _cargarDatos() async {
    final authProvider = context.read<AuthProvider>();
    final pedidoProvider = context.read<PedidoProvider>();

    if (authProvider.usuario != null) {
      await pedidoProvider.cargarPedidoActivo(authProvider.usuario!.id);
      if (authProvider.isOnline) {
        await pedidoProvider.cargarPedidosDisponibles();
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: const Text('QuickBite Repartidor'),
        actions: [
          IconButton(
            icon: const Icon(Icons.logout),
            onPressed: () => _confirmarLogout(context),
          ),
        ],
      ),
      body: RefreshIndicator(
        onRefresh: _cargarDatos,
        child: SingleChildScrollView(
          physics: const AlwaysScrollableScrollPhysics(),
          padding: const EdgeInsets.all(16),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              // Estado Online/Offline
              _buildEstadoCard(),
              const SizedBox(height: 16),

              // Estadísticas
              _buildEstadisticasCard(),
              const SizedBox(height: 16),

              // Pedido Activo
              Consumer<PedidoProvider>(
                builder: (context, pedidoProvider, child) {
                  if (pedidoProvider.tienePedidoActivo) {
                    return _buildPedidoActivoCard(pedidoProvider);
                  }
                  return const SizedBox.shrink();
                },
              ),

              // Pedidos Disponibles
              Consumer<AuthProvider>(
                builder: (context, authProvider, child) {
                  if (authProvider.isOnline) {
                    return _buildPedidosDisponiblesSection();
                  }
                  return _buildOfflineMessage();
                },
              ),
            ],
          ),
        ),
      ),
    );
  }

  Widget _buildEstadoCard() {
    return Consumer<AuthProvider>(
      builder: (context, authProvider, child) {
        return Card(
          child: Padding(
            padding: const EdgeInsets.all(16),
            child: Row(
              children: [
                Icon(
                  authProvider.isOnline
                      ? Icons.check_circle
                      : Icons.cancel,
                  color: authProvider.isOnline
                      ? AppColors.online
                      : AppColors.offline,
                  size: 32,
                ),
                const SizedBox(width: 16),
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(
                        authProvider.isOnline ? 'En Línea' : 'Fuera de Línea',
                        style: Theme.of(context).textTheme.titleLarge?.copyWith(
                              fontWeight: FontWeight.bold,
                            ),
                      ),
                      Text(
                        authProvider.isOnline
                            ? 'Recibiendo pedidos'
                            : 'No recibes pedidos',
                        style: Theme.of(context).textTheme.bodyMedium,
                      ),
                    ],
                  ),
                ),
                Switch(
                  value: authProvider.isOnline,
                  activeColor: AppColors.online,
                  onChanged: (value) {
                    authProvider.toggleOnlineStatus();
                    if (value) {
                      _cargarDatos();
                    }
                  },
                ),
              ],
            ),
          ),
        );
      },
    );
  }

  Widget _buildEstadisticasCard() {
    return Card(
      child: Padding(
        padding: const EdgeInsets.all(16),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(
              'Estadísticas de Hoy',
              style: Theme.of(context).textTheme.titleMedium?.copyWith(
                    fontWeight: FontWeight.bold,
                  ),
            ),
            const SizedBox(height: 16),
            Row(
              mainAxisAlignment: MainAxisAlignment.spaceAround,
              children: [
                _buildEstadistica(
                  icon: Icons.delivery_dining,
                  label: 'Entregas',
                  valor: '12',
                  color: AppColors.primary,
                ),
                _buildEstadistica(
                  icon: Icons.attach_money,
                  label: 'Ganancias',
                  valor: '\$240',
                  color: AppColors.success,
                ),
                _buildEstadistica(
                  icon: Icons.star,
                  label: 'Calificación',
                  valor: '4.8',
                  color: AppColors.warning,
                ),
              ],
            ),
          ],
        ),
      ),
    );
  }

  Widget _buildEstadistica({
    required IconData icon,
    required String label,
    required String valor,
    required Color color,
  }) {
    return Column(
      children: [
        Icon(icon, color: color, size: 32),
        const SizedBox(height: 8),
        Text(
          valor,
          style: Theme.of(context).textTheme.titleLarge?.copyWith(
                fontWeight: FontWeight.bold,
                color: color,
              ),
        ),
        Text(
          label,
          style: Theme.of(context).textTheme.bodySmall,
        ),
      ],
    );
  }

  Widget _buildPedidoActivoCard(PedidoProvider pedidoProvider) {
    return Card(
      color: AppColors.primary.withOpacity(0.1),
      child: InkWell(
        onTap: () {
          Navigator.push(
            context,
            MaterialPageRoute(
              builder: (_) => const PedidoActivoScreen(),
            ),
          );
        },
        child: Padding(
          padding: const EdgeInsets.all(16),
          child: Row(
            children: [
              const Icon(
                Icons.shopping_bag,
                color: AppColors.primary,
                size: 32,
              ),
              const SizedBox(width: 16),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      'Pedido en Curso',
                      style: Theme.of(context).textTheme.titleMedium?.copyWith(
                            fontWeight: FontWeight.bold,
                            color: AppColors.primary,
                          ),
                    ),
                    Text(
                      'Pedido #${pedidoProvider.pedidoActivo!.id}',
                      style: Theme.of(context).textTheme.bodyMedium,
                    ),
                  ],
                ),
              ),
              const Icon(
                Icons.arrow_forward_ios,
                color: AppColors.primary,
              ),
            ],
          ),
        ),
      ),
    );
  }

  Widget _buildPedidosDisponiblesSection() {
    return Consumer<PedidoProvider>(
      builder: (context, pedidoProvider, child) {
        if (pedidoProvider.tienePedidoActivo) {
          return const SizedBox.shrink();
        }

        return Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            Row(
              mainAxisAlignment: MainAxisAlignment.spaceBetween,
              children: [
                Text(
                  'Pedidos Disponibles',
                  style: Theme.of(context).textTheme.titleMedium?.copyWith(
                        fontWeight: FontWeight.bold,
                      ),
                ),
                TextButton(
                  onPressed: () {
                    Navigator.push(
                      context,
                      MaterialPageRoute(
                        builder: (_) => const PedidosDisponiblesScreen(),
                      ),
                    );
                  },
                  child: const Text('Ver todos'),
                ),
              ],
            ),
            const SizedBox(height: 8),
            if (pedidoProvider.isLoading)
              const Center(child: CircularProgressIndicator())
            else if (pedidoProvider.pedidosDisponibles.isEmpty)
              const Card(
                child: Padding(
                  padding: EdgeInsets.all(24),
                  child: Column(
                    children: [
                      Icon(Icons.inbox, size: 48, color: Colors.grey),
                      SizedBox(height: 8),
                      Text('No hay pedidos disponibles'),
                    ],
                  ),
                ),
              )
            else
              SizedBox(
                height: 200,
                child: ListView.builder(
                  scrollDirection: Axis.horizontal,
                  itemCount: pedidoProvider.pedidosDisponibles.length > 5
                      ? 5
                      : pedidoProvider.pedidosDisponibles.length,
                  itemBuilder: (context, index) {
                    final pedido = pedidoProvider.pedidosDisponibles[index];
                    return Container(
                      width: 160,
                      margin: const EdgeInsets.only(right: 8),
                      child: Card(
                        child: InkWell(
                          onTap: () {
                            Navigator.push(
                              context,
                              MaterialPageRoute(
                                builder: (_) =>
                                    const PedidosDisponiblesScreen(),
                              ),
                            );
                          },
                          child: Padding(
                            padding: const EdgeInsets.all(12),
                            child: Column(
                              crossAxisAlignment: CrossAxisAlignment.start,
                              mainAxisAlignment: MainAxisAlignment.spaceBetween,
                              children: [
                                Text(
                                  'Pedido #${pedido.id}',
                                  style: const TextStyle(
                                    fontWeight: FontWeight.bold,
                                  ),
                                ),
                                Text(
                                  '\$${pedido.total.toStringAsFixed(2)}',
                                  style: const TextStyle(
                                    fontSize: 20,
                                    color: AppColors.primary,
                                    fontWeight: FontWeight.bold,
                                  ),
                                ),
                                const Icon(Icons.location_on, size: 16),
                                Text(
                                  pedido.direccionEntrega,
                                  maxLines: 2,
                                  overflow: TextOverflow.ellipsis,
                                  style: const TextStyle(fontSize: 12),
                                ),
                              ],
                            ),
                          ),
                        ),
                      ),
                    );
                  },
                ),
              ),
          ],
        );
      },
    );
  }

  Widget _buildOfflineMessage() {
    return const Card(
      child: Padding(
        padding: EdgeInsets.all(24),
        child: Column(
          children: [
            Icon(Icons.info_outline, size: 48, color: Colors.grey),
            SizedBox(height: 8),
            Text(
              'Estás fuera de línea',
              style: TextStyle(
                fontSize: 18,
                fontWeight: FontWeight.bold,
              ),
            ),
            SizedBox(height: 4),
            Text(
              'Activa el modo en línea para recibir pedidos',
              textAlign: TextAlign.center,
              style: TextStyle(color: Colors.grey),
            ),
          ],
        ),
      ),
    );
  }

  Future<void> _confirmarLogout(BuildContext context) async {
    final confirmar = await showDialog<bool>(
      context: context,
      builder: (context) => AlertDialog(
        title: const Text('Cerrar Sesión'),
        content: const Text('¿Estás seguro que deseas salir?'),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(context, false),
            child: const Text('Cancelar'),
          ),
          TextButton(
            onPressed: () => Navigator.pop(context, true),
            child: const Text('Salir'),
          ),
        ],
      ),
    );

    if (confirmar == true && context.mounted) {
      await context.read<AuthProvider>().logout();
      if (context.mounted) {
        Navigator.of(context).pushReplacementNamed('/');
      }
    }
  }
}
