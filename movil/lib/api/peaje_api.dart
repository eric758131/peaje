import '../models/models.dart';
import 'api_client.dart';
import 'api_exception.dart';

/// Endpoints de la API del peaje, en el mismo orden que docs/contrato-movil.md.
class PeajeApi {
  PeajeApi(this._api);

  final ApiClient _api;

  Future<List<Caseta>> casetas() async {
    final lista = await _api.get('/casetas') as List<dynamic>;
    return lista
        .map((e) => Caseta.fromJson(e as Map<String, dynamic>))
        .toList();
  }

  /// Envía un frame de caseta. Devuelve `null` si la imagen no tenía placa legible.
  Future<PasoRegistrado?> enviarFrame({
    required String rutaImagen,
    required int casetaId,
  }) async {
    try {
      final json =
          await _api.postArchivo(
                '/pasos/frame',
                rutaArchivo: rutaImagen,
                campos: {'caseta_id': casetaId.toString()},
              )
              as Map<String, dynamic>;
      return PasoRegistrado.fromJson(json);
    } on ApiException catch (e) {
      if (e.cuerpo?['estado'] == 'sin_placa') return null;
      rethrow;
    }
  }

  Future<ConsultaPlaca> consultarTexto(String placa) async {
    final json =
        await _api.postJson('/placas/consultar', {'placa': placa})
            as Map<String, dynamic>;
    return ConsultaPlaca.desdeJson(json);
  }

  Future<ConsultaPlaca> consultarFoto(String rutaImagen) async {
    try {
      final json =
          await _api.postArchivo(
                '/placas/consultar-imagen',
                rutaArchivo: rutaImagen,
              )
              as Map<String, dynamic>;
      return ConsultaPlaca.desdeJson(json);
    } on ApiException catch (e) {
      if (e.cuerpo?['estado'] == 'sin_placa') {
        return ConsultaPlaca.sinPlaca(
          e.cuerpo?['mensaje'] as String? ?? e.mensaje,
        );
      }
      rethrow;
    }
  }

  /// Mensaje al agente: texto, o una foto con una pregunta opcional.
  /// Enviar el mismo [conversacion] en cada mensaje hace que recuerde la última foto
  /// y lo conversado ("¿cuántas llantas hay?"). Si es null, el servidor crea uno.
  Future<RespuestaAgente> chat({
    String mensaje = '',
    String? rutaImagen,
    String? conversacion,
  }) async {
    final campos = {
      if (mensaje.isNotEmpty) 'mensaje': mensaje,
      'conversacion': ?conversacion,
    };
    final json =
        (rutaImagen == null
                ? await _api.postJson('/agente/chat', campos)
                : await _api.postArchivo(
                    '/agente/chat',
                    rutaArchivo: rutaImagen,
                    campos: campos,
                  ))
            as Map<String, dynamic>;
    return RespuestaAgente.desdeJson(json);
  }
}
