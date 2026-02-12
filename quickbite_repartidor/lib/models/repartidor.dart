class Repartidor {
  final int id;
  final String nombre;
  final String email;
  final String? telefono;
  final String? vehiculo; // moto, bicicleta, auto
  final bool online;
  final double? calificacion;
  final int? pedidosCompletados;
  final double? gananciasHoy;

  Repartidor({
    required this.id,
    required this.nombre,
    required this.email,
    this.telefono,
    this.vehiculo,
    this.online = false,
    this.calificacion,
    this.pedidosCompletados,
    this.gananciasHoy,
  });

  factory Repartidor.fromJson(Map<String, dynamic> json) {
    return Repartidor(
      id: int.tryParse(json['id'].toString()) ?? 0,
      nombre: json['nombre'] ?? '',
      email: json['email'] ?? '',
      telefono: json['telefono'],
      vehiculo: json['vehiculo'],
      online: json['online'] == 1 || json['online'] == true,
      calificacion: json['calificacion'] != null
          ? double.tryParse(json['calificacion'].toString())
          : null,
      pedidosCompletados: json['pedidos_completados'] != null
          ? int.tryParse(json['pedidos_completados'].toString())
          : null,
      gananciasHoy: json['ganancias_hoy'] != null
          ? double.tryParse(json['ganancias_hoy'].toString())
          : null,
    );
  }

  Map<String, dynamic> toJson() {
    return {
      'id': id,
      'nombre': nombre,
      'email': email,
      'telefono': telefono,
      'vehiculo': vehiculo,
      'online': online ? 1 : 0,
      'calificacion': calificacion,
      'pedidos_completados': pedidosCompletados,
      'ganancias_hoy': gananciasHoy,
    };
  }

  Repartidor copyWith({
    int? id,
    String? nombre,
    String? email,
    String? telefono,
    String? vehiculo,
    bool? online,
    double? calificacion,
    int? pedidosCompletados,
    double? gananciasHoy,
  }) {
    return Repartidor(
      id: id ?? this.id,
      nombre: nombre ?? this.nombre,
      email: email ?? this.email,
      telefono: telefono ?? this.telefono,
      vehiculo: vehiculo ?? this.vehiculo,
      online: online ?? this.online,
      calificacion: calificacion ?? this.calificacion,
      pedidosCompletados: pedidosCompletados ?? this.pedidosCompletados,
      gananciasHoy: gananciasHoy ?? this.gananciasHoy,
    );
  }
}
