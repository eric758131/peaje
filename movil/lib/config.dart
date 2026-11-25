/// Configuración de la app. Se pasa al compilar, sin escribir secretos en el código:
///
///   flutter run --dart-define=API_BASE_URL=https://peaje.tudominio.com/api/v1 \
///               --dart-define=API_KEY=clave-del-backend
///
/// API_KEY es el valor de MOVIL_API_KEY en el .env del backend.
///
/// La app no tiene login: se identifica ante la API con la cabecera X-Api-Key.
class Config {
  Config._();

  static const String apiBaseUrl = String.fromEnvironment(
    'API_BASE_URL',
    defaultValue: 'https://peaje.tudominio.com/api/v1',
  );

  static const String apiKey = String.fromEnvironment('API_KEY');

  /// Intervalo entre frames en modo Caseta (ms): 400 ms = 2,5 frames por segundo (el lab pide 2-5).
  static const int intervaloFrameMs = 400;

  /// Máximo de frames esperando respuesta a la vez. Si se llega al tope, se salta ese frame.
  static const int maxFramesEnVuelo = 3;
}
