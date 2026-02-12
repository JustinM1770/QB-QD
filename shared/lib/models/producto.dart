class Producto {
  final int id;
  final int negocioId;
  final String nombre;
  final String descripcion;
  final double precio;
  final String? imagen;
  final String? categoria;
  final bool disponible;
  final int? tiempoPreparacion;

  Producto({
    required this.id,
    required this.negocioId,
    required this.nombre,
    required this.descripcion,
    required this.precio,
    this.imagen,
    this.categoria,
    this.disponible = true,
    this.tiempoPreparacion,
  });

  factory Producto.fromJson(Map<String, dynamic> json) {
    return Producto(
      id: int.tryParse(json['id'].toString()) ?? 0,
      negocioId: int.tryParse(json['negocio_id'].toString()) ?? 0,
      nombre: json['nombre'] ?? '',
      descripcion: json['descripcion'] ?? '',
      precio: double.tryParse(json['precio'].toString()) ?? 0.0,
      imagen: json['imagen'],
      categoria: json['categoria'],
      disponible: json['disponible'] == 1 || json['disponible'] == true,
      tiempoPreparacion: json['tiempo_preparacion'] != null
          ? int.tryParse(json['tiempo_preparacion'].toString())
          : null,
    );
  }

  Map<String, dynamic> toJson() {
    return {
      'id': id,
      'negocio_id': negocioId,
      'nombre': nombre,
      'descripcion': descripcion,
      'precio': precio,
      'imagen': imagen,
      'categoria': categoria,
      'disponible': disponible ? 1 : 0,
      'tiempo_preparacion': tiempoPreparacion,
    };
  }
}
