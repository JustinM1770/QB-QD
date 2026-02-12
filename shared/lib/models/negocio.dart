class Negocio {
  final int id;
  final String nombre;
  final String descripcion;
  final String? logo;
  final String? portada;
  final String direccion;
  final double? latitud;
  final double? longitud;
  final String? telefono;
  final String? horarioApertura;
  final String? horarioCierre;
  final bool abierto;
  final double? calificacion;
  final String? categoria;
  final double? costoEnvio;
  final int? tiempoEntregaMin;

  Negocio({
    required this.id,
    required this.nombre,
    required this.descripcion,
    this.logo,
    this.portada,
    required this.direccion,
    this.latitud,
    this.longitud,
    this.telefono,
    this.horarioApertura,
    this.horarioCierre,
    this.abierto = true,
    this.calificacion,
    this.categoria,
    this.costoEnvio,
    this.tiempoEntregaMin,
  });

  factory Negocio.fromJson(Map<String, dynamic> json) {
    return Negocio(
      id: int.tryParse(json['id'].toString()) ?? 0,
      nombre: json['nombre'] ?? '',
      descripcion: json['descripcion'] ?? '',
      logo: json['logo'],
      portada: json['portada'],
      direccion: json['direccion'] ?? '',
      latitud: json['latitud'] != null ? double.tryParse(json['latitud'].toString()) : null,
      longitud: json['longitud'] != null ? double.tryParse(json['longitud'].toString()) : null,
      telefono: json['telefono'],
      horarioApertura: json['horario_apertura'],
      horarioCierre: json['horario_cierre'],
      abierto: json['abierto'] == 1 || json['abierto'] == true,
      calificacion: json['calificacion'] != null ? double.tryParse(json['calificacion'].toString()) : null,
      categoria: json['categoria'],
      costoEnvio: json['costo_envio'] != null ? double.tryParse(json['costo_envio'].toString()) : null,
      tiempoEntregaMin: json['tiempo_entrega_min'] != null ? int.tryParse(json['tiempo_entrega_min'].toString()) : null,
    );
  }

  Map<String, dynamic> toJson() {
    return {
      'id': id,
      'nombre': nombre,
      'descripcion': descripcion,
      'logo': logo,
      'portada': portada,
      'direccion': direccion,
      'latitud': latitud,
      'longitud': longitud,
      'telefono': telefono,
      'horario_apertura': horarioApertura,
      'horario_cierre': horarioCierre,
      'abierto': abierto ? 1 : 0,
      'calificacion': calificacion,
      'categoria': categoria,
      'costo_envio': costoEnvio,
      'tiempo_entrega_min': tiempoEntregaMin,
    };
  }
}
