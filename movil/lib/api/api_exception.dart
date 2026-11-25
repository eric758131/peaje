/// Error devuelto por la API. Conserva el cuerpo JSON para leer campos como `estado` o `errors`.
class ApiException implements Exception {
  ApiException(this.statusCode, this.mensaje, {this.cuerpo});

  final int statusCode;
  final String mensaje;
  final Map<String, dynamic>? cuerpo;

  bool get esNoAutorizado => statusCode == 401 || statusCode == 403;

  @override
  String toString() => mensaje;
}
