import 'api/api_client.dart';
import 'api/peaje_api.dart';

/// Servicios compartidos por toda la app. Se crean una sola vez al iniciar.
class Servicios {
  Servicios._();

  static late final ApiClient api;
  static late final PeajeApi peaje;

  static void iniciar() {
    api = ApiClient();
    peaje = PeajeApi(api);
  }
}
