# Aplicación móvil (Flutter)

App Android que consume la API del peaje. **No tiene login**: se identifica ante la API con la cabecera `X-Api-Key`. Contrato: [../docs/contrato-movil.md](../docs/contrato-movil.md).

## Modos

- **Modo Caseta:** elige la caseta, captura video con la cámara trasera y envía frames a `POST /pasos/frame`. Muestra placa, categoría, ejes, tarifa y estado (cobrado, sin saldo o no registrado). Envía un frame y espera la respuesta antes del siguiente.
- **Modo Consulta:** tres pestañas: placa escrita, foto de placa (cámara o galería) y chat con el agente.

## Estructura

```
lib/
├── main.dart              Arranque: abre directo en el inicio
├── config.dart            API_BASE_URL y API_KEY (vía --dart-define)
├── servicios.dart         Servicios compartidos (cliente de la API)
├── api/                   Cliente HTTP, excepciones y endpoints de la API
├── models/                Respuestas de la API
├── screens/               Inicio, Caseta y Consulta
└── utils/estados.dart     Textos y colores de cada estado
test/models_test.dart      Pruebas de lectura de respuestas y estados
```

## Requisitos

- Flutter 3.41 o superior, Android SDK y Java 17+.
- Un teléfono Android o un emulador.

## Clave de la app

La app envía `API_KEY` en la cabecera `X-Api-Key`. Debe ser igual a `MOVIL_API_KEY` del `.env` del backend.
No la escribas en el código: pásala al compilar con `--dart-define`.

## Ejecutar en desarrollo

```bash
cd movil
flutter pub get
flutter run \
  --dart-define=API_BASE_URL=https://peaje.tudominio.com/api/v1 \
  --dart-define=API_KEY=<MOVIL_API_KEY del backend>
```

## Generar el APK

```bash
flutter build apk --release \
  --dart-define=API_BASE_URL=https://peaje.tudominio.com/api/v1 \
  --dart-define=API_KEY=<MOVIL_API_KEY del backend>
```

El archivo queda en `build/app/outputs/flutter-apk/app-release.apk`.

**Nota:** el release actual está firmado con la clave de depuración. Para entregarlo como app de producción, crea un keystore propio y configúralo en `android/app/build.gradle.kts`.

## Pruebas

```bash
flutter analyze
flutter test
```

## Pendiente

- [ ] Probar el modo Caseta con video real y el endpoint de inferencia en Colab.
- [ ] Medir la tasa de frames real y la latencia de punta a punta (va en el informe).
