class ApiConfig {
  // Cambiar según tu entorno
  static const String baseUrl = 'http://10.0.2.2/api'; // Emulador Android
  // static const String baseUrl = 'http://localhost:8000/api'; // iOS Simulator
  // static const String baseUrl = 'http://192.168.1.X:8000/api'; // Dispositivo físico
  // static const String baseUrl = 'https://tudominio.com/api'; // Producción

  // Endpoints Auth
  static const String login = '$baseUrl/auth/login.php';
  static const String register = '$baseUrl/auth/register.php';
  static const String logout = '$baseUrl/auth/logout.php';

  // Endpoints Repartidor
  static const String perfil = '$baseUrl/repartidores/perfil.php';
  static const String actualizarPerfil = '$baseUrl/repartidores/actualizar.php';
  static const String cambiarEstado = '$baseUrl/repartidores/estado.php';
  static const String estadisticas = '$baseUrl/repartidores/estadisticas.php';

  // Endpoints Pedidos
  static const String pedidosDisponibles = '$baseUrl/pedidos/disponibles.php';
  static const String aceptarPedido = '$baseUrl/pedidos/aceptar.php';
  static const String pedidoActivo = '$baseUrl/pedidos/activo.php';
  static const String pedidosActivos = '$baseUrl/pedidos/activos_repartidor.php'; // MULTIPEDIDO
  static const String actualizarEstadoPedido = '$baseUrl/pedidos/actualizar_estado.php';
  static const String historialPedidos = '$baseUrl/pedidos/historial_repartidor.php';

  // Endpoints Ubicación
  static const String actualizarUbicacion = '$baseUrl/repartidores/ubicacion.php';

  // Configuración
  static const Duration timeout = Duration(seconds: 30);
  static const Duration pollingInterval = Duration(seconds: 10); // Auto-actualización
}
