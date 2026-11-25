import 'dart:convert';

import 'package:http/http.dart' as http;
import 'package:image_picker/image_picker.dart' show XFile;

import '../config.dart';
import 'api_exception.dart';

/// Cliente HTTP de la API del peaje. La app no tiene login: agrega la cabecera
/// X-Api-Key (definida al compilar) a cada petición.
class ApiClient {
  ApiClient({http.Client? httpClient, String apiKey = Config.apiKey})
    : _http = httpClient ?? http.Client(),
      _apiKey = apiKey;

  final http.Client _http;
  final String _apiKey;

  static const Duration _timeout = Duration(seconds: 30);

  Uri _uri(String ruta) => Uri.parse('${Config.apiBaseUrl}$ruta');

  Map<String, String> _cabeceras({bool json = true}) => {
    'Accept': 'application/json',
    if (json) 'Content-Type': 'application/json',
    if (_apiKey.isNotEmpty) 'X-Api-Key': _apiKey,
  };

  Future<dynamic> get(String ruta) async {
    final respuesta = await _http
        .get(_uri(ruta), headers: _cabeceras())
        .timeout(_timeout)
        .catchError(_errorDeRed);
    return _decodificar(respuesta);
  }

  Future<dynamic> postJson(String ruta, Map<String, dynamic> cuerpo) async {
    final respuesta = await _http
        .post(_uri(ruta), headers: _cabeceras(), body: jsonEncode(cuerpo))
        .timeout(_timeout)
        .catchError(_errorDeRed);
    return _decodificar(respuesta);
  }

  /// Envía un archivo (frame o foto) y campos de texto en `multipart/form-data`.
  Future<dynamic> postArchivo(
    String ruta, {
    required String rutaArchivo,
    String campoArchivo = 'imagen',
    Map<String, String> campos = const {},
  }) async {
    // XFile lee el archivo en Android, iOS y web (en web la "ruta" es un blob).
    final bytes = await XFile(rutaArchivo).readAsBytes();
    final peticion = http.MultipartRequest('POST', _uri(ruta))
      ..headers.addAll(_cabeceras(json: false))
      ..fields.addAll(campos)
      ..files.add(
        http.MultipartFile.fromBytes(
          campoArchivo,
          bytes,
          filename: 'imagen.jpg',
        ),
      );

    final streamed = await peticion
        .send()
        .timeout(_timeout)
        .catchError(_errorDeRedStreamed);
    final respuesta = await http.Response.fromStream(streamed);
    return _decodificar(respuesta);
  }

  dynamic _decodificar(http.Response respuesta) {
    dynamic cuerpo;
    try {
      cuerpo = respuesta.body.isEmpty
          ? null
          : jsonDecode(utf8.decode(respuesta.bodyBytes));
    } on FormatException {
      // Respondió una página web y no la API: la dirección del servidor está mal.
      throw ApiException(
        respuesta.statusCode,
        'El servidor no respondió como la API (código ${respuesta.statusCode}). '
        'Revisa la dirección: ${Config.apiBaseUrl}',
      );
    }
    final mapa = cuerpo is Map<String, dynamic> ? cuerpo : null;

    if (respuesta.statusCode >= 200 && respuesta.statusCode < 300) {
      return cuerpo;
    }

    final mensaje = respuesta.statusCode == 401
        ? 'El servidor rechazó la clave de la app (API_KEY). Recompila con la clave correcta.'
        : mapa?['message']?.toString() ??
              'Error ${respuesta.statusCode} del servidor.';

    throw ApiException(respuesta.statusCode, mensaje, cuerpo: mapa);
  }

  Never _errorDeRed(Object error, StackTrace stack) {
    throw ApiException(
      0,
      'Sin conexión con el servidor (${Config.apiBaseUrl}). Revisa tu internet.',
    );
  }

  Never _errorDeRedStreamed(Object error, StackTrace stack) {
    throw ApiException(0, 'No se pudo enviar el archivo. Revisa tu internet.');
  }
}
