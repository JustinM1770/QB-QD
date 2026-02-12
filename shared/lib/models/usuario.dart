class Usuario {
  final int id;
  final String nombre;
  final String email;
  final String? telefono;
  final String tipo; // cliente, negocio, repartidor
  final String? token;
  final String? fotoPerfil;

  Usuario({
    required this.id,
    required this.nombre,
    required this.email,
    this.telefono,
    required this.tipo,
    this.token,
    this.fotoPerfil,
  });

  factory Usuario.fromJson(Map<String, dynamic> json) {
    return Usuario(
      id: int.tryParse(json['id'].toString()) ?? 0,
      nombre: json['nombre'] ?? '',
      email: json['email'] ?? '',
      telefono: json['telefono'],
      tipo: json['tipo'] ?? 'cliente',
      token: json['token'],
      fotoPerfil: json['foto_perfil'],
    );
  }

  Map<String, dynamic> toJson() {
    return {
      'id': id,
      'nombre': nombre,
      'email': email,
      'telefono': telefono,
      'tipo': tipo,
      'token': token,
      'foto_perfil': fotoPerfil,
    };
  }

  Usuario copyWith({
    int? id,
    String? nombre,
    String? email,
    String? telefono,
    String? tipo,
    String? token,
    String? fotoPerfil,
  }) {
    return Usuario(
      id: id ?? this.id,
      nombre: nombre ?? this.nombre,
      email: email ?? this.email,
      telefono: telefono ?? this.telefono,
      tipo: tipo ?? this.tipo,
      token: token ?? this.token,
      fotoPerfil: fotoPerfil ?? this.fotoPerfil,
    );
  }
}
