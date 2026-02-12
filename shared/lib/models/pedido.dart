class Pedido {
  final int id;
  final int usuarioId;
  final int negocioId;
  final int? repartidorId;
  final String estado; // pendiente, preparando, listo, en_camino, entregado, cancelado
  final double total;
  final double? costoEnvio;
  final String direccionEntrega;
  final String? instruccionesEntrega;
  final String? metodoPago;
  final DateTime fechaCreacion;
  final DateTime? fechaEntrega;
  final List<DetallePedido>? detalles;

  Pedido({
    required this.id,
    required this.usuarioId,
    required this.negocioId,
    this.repartidorId,
    required this.estado,
    required this.total,
    this.costoEnvio,
    required this.direccionEntrega,
    this.instruccionesEntrega,
    this.metodoPago,
    required this.fechaCreacion,
    this.fechaEntrega,
    this.detalles,
  });

  factory Pedido.fromJson(Map<String, dynamic> json) {
    return Pedido(
      id: int.tryParse(json['id'].toString()) ?? 0,
      usuarioId: int.tryParse(json['usuario_id'].toString()) ?? 0,
      negocioId: int.tryParse(json['negocio_id'].toString()) ?? 0,
      repartidorId: json['repartidor_id'] != null
          ? int.tryParse(json['repartidor_id'].toString())
          : null,
      estado: json['estado'] ?? 'pendiente',
      total: double.tryParse(json['total'].toString()) ?? 0.0,
      costoEnvio: json['costo_envio'] != null
          ? double.tryParse(json['costo_envio'].toString())
          : null,
      direccionEntrega: json['direccion_entrega'] ?? '',
      instruccionesEntrega: json['instrucciones_entrega'],
      metodoPago: json['metodo_pago'],
      fechaCreacion: DateTime.tryParse(json['fecha_creacion'] ?? '') ?? DateTime.now(),
      fechaEntrega: json['fecha_entrega'] != null
          ? DateTime.tryParse(json['fecha_entrega'])
          : null,
      detalles: json['detalles'] != null
          ? (json['detalles'] as List).map((e) => DetallePedido.fromJson(e)).toList()
          : null,
    );
  }

  Map<String, dynamic> toJson() {
    return {
      'id': id,
      'usuario_id': usuarioId,
      'negocio_id': negocioId,
      'repartidor_id': repartidorId,
      'estado': estado,
      'total': total,
      'costo_envio': costoEnvio,
      'direccion_entrega': direccionEntrega,
      'instrucciones_entrega': instruccionesEntrega,
      'metodo_pago': metodoPago,
      'fecha_creacion': fechaCreacion.toIso8601String(),
      'fecha_entrega': fechaEntrega?.toIso8601String(),
      'detalles': detalles?.map((e) => e.toJson()).toList(),
    };
  }
}

class DetallePedido {
  final int id;
  final int pedidoId;
  final int productoId;
  final String nombreProducto;
  final int cantidad;
  final double precioUnitario;
  final double subtotal;

  DetallePedido({
    required this.id,
    required this.pedidoId,
    required this.productoId,
    required this.nombreProducto,
    required this.cantidad,
    required this.precioUnitario,
    required this.subtotal,
  });

  factory DetallePedido.fromJson(Map<String, dynamic> json) {
    return DetallePedido(
      id: int.tryParse(json['id'].toString()) ?? 0,
      pedidoId: int.tryParse(json['pedido_id'].toString()) ?? 0,
      productoId: int.tryParse(json['producto_id'].toString()) ?? 0,
      nombreProducto: json['nombre_producto'] ?? '',
      cantidad: int.tryParse(json['cantidad'].toString()) ?? 0,
      precioUnitario: double.tryParse(json['precio_unitario'].toString()) ?? 0.0,
      subtotal: double.tryParse(json['subtotal'].toString()) ?? 0.0,
    );
  }

  Map<String, dynamic> toJson() {
    return {
      'id': id,
      'pedido_id': pedidoId,
      'producto_id': productoId,
      'nombre_producto': nombreProducto,
      'cantidad': cantidad,
      'precio_unitario': precioUnitario,
      'subtotal': subtotal,
    };
  }
}
