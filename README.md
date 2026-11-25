# Sistema inteligente de control de peaje

Proyecto final del hito de **IAM-811 Machine Learning y Aprendizaje Profundo**.

Una app móvil graba el paso de los vehículos por una caseta. La inteligencia artificial lee la placa, reconoce el tipo de vehículo, cuenta sus ejes y sus pasajeros, y la web cobra la tarifa y muestra cada paso **en tiempo real**. Un agente conversacional responde el estado de cualquier placa, por texto o por foto.

**Producción:** https://peaje.ericluna.shop · **Documentación de la API:** https://peaje.ericluna.shop/docs/api

## Integrantes y roles

Proyecto individual.

| Integrante | Rol |
|---|---|
| Eric | Todos los roles: modelos e inferencia en Colab, web, API, base de datos, tiempo real y agente, app móvil, despliegue, documentación, pruebas y video |

## Arquitectura

```
App móvil (Flutter) ──https, Google──────►  Web Laravel · peaje.ericluna.shop
Navegador ─────────https, Google OAuth──►   ├─ Panel en tiempo real ◄── Pusher (canal privado)
                                            ├─ API REST /api/v1 + MySQL
                                            ├─ Alertas (placa no registrada / sin saldo)
                                            └─ Agente: consulta la base con la herramienta consultar_placa
                                                     │
                                                     ▼  link + token (se configuran en la web)
                                    Google Colab (GPU T4) · inferencia/peaje_colab.ipynb
                                    FastAPI + túnel de Cloudflare, un solo endpoint
                                      ├─ /inferir      M1 placa + OCR · M2 tipo de vehículo · M3 ruedas → ejes · personas
                                      ├─ /agente/chat  Qwen 2.5 3B (Ollama)
                                      └─ /health
```

La web no depende de dónde corre la inferencia: para cambiar de Colab a otro servidor (por ejemplo RunPod) basta con pegar otro link en **Estado de la IA → Conexión con Colab**.

## Cómo funciona

### 1. Paso de un vehículo por la caseta
1. En la app, **Modo Caseta**: el operador elige la caseta y pulsa **Iniciar captura**. La app envía de 2 a 3 frames por segundo a `POST /api/v1/pasos/frame` (hasta 3 a la vez).
2. La web reenvía cada frame al endpoint de Colab (`/inferir`), que devuelve, para **cada vehículo** de la imagen:

   | Dato | Modelo |
   |---|---|
   | Placa y confianza del OCR | **M1**: detector YOLOv9-t + OCR CCT-XS v2 |
   | Tipo de vehículo (moto, auto, minibús, camión, bus) | **M2**: segmentación YOLO26n-seg (una máscara por vehículo) |
   | Número de ejes | **M3**: centro de cada rueda visible (YOLOv8s, Open Images V7) |
   | Pasajeros | Personas dentro del vehículo (segmentación) |

   También devuelve la latencia y una imagen de evidencia anotada.
3. La web normaliza la placa (mayúsculas, sin guiones ni espacios), corrige errores típicos del OCR y descarta las lecturas dudosas.
4. Busca el vehículo y la tarifa (por categoría y ejes) y registra el **paso**:
   - **Cobrado:** el vehículo está registrado y tiene saldo; se descuenta la tarifa.
   - **Sin saldo** o **No registrado:** además genera una **alerta**.
5. El paso y la alerta se emiten por **Pusher**. El **Panel en vivo** los muestra al instante, sin recargar: placa, evidencia, categoría, ejes, pasajeros, monto, estado y hora.
6. **Sin cobros dobles:** en un video la misma placa aparece en muchos frames. Las lecturas del mismo vehículo en la misma caseta dentro de 60 s no se cobran dos veces.

Desde la web también se registran pasos con una foto, un video o la cámara del navegador (**Registrar paso**).

### 2. Agente conversacional
Está en **Consultar placa**, en el **botón flotante** de todas las páginas y en el **Modo Consulta** de la app.

- **Qwen solo redacta.** Los datos los obtiene Laravel de la base de datos con la herramienta `consultar_placa` (function calling).
- **No inventa datos:**
  - si Qwen nombra una placa que no vino de la base, su respuesta se descarta;
  - si olvida una sugerencia, el sistema la agrega.
- **Responde 4 estados:**
  - **Registrada y con paso:** fecha, caseta, categoría y monto del último paso.
  - **Registrada sin paso.**
  - **Identificada no registrada:** posible evasión.
  - **No existe.**
- **Errores de OCR:** normaliza la placa y, ante confusiones típicas (O/0, I/1, B/8, S/5…), pregunta "¿Quisiste decir…?".
- **Fotos:** dice cuántos vehículos hay y de qué tipo, cuántas llantas y personas, y qué placas se leyeron.
- **Memoria corta:** recuerda la última foto y los últimos mensajes, así que se puede preguntar "¿cuántas llantas hay?" después de enviar una foto.
- **Si Colab está apagado,** responde igual con los datos de la base, sin Qwen, y lo indica.

### 3. Acceso
- **Web:** **Continuar con Google** (OAuth 2.0). Solo entran las cuentas activas de la tabla `usuarios` (lista blanca); las demás ven **Acceso denegado**. La cuenta del docente (`doc.alvaro.pinto.ma@unifranz.edu.bo`) ya está autorizada como **administrador** en producción. También existe el login con correo y contraseña, que se apaga con `LOGIN_CON_CONTRASENA=false`.
- **Roles:**
  - **Administrador:** gestiona vehículos, tarifas, casetas y usuarios.
  - **Operador:** registra pasos y consulta placas.
- **App móvil:** **Continuar con Google** (Google Sign-In). La app envía el ID token a `POST /api/v1/auth/google`, recibe un token propio y lo usa en cada petición (`Authorization: Bearer`). Las cuentas fuera de la lista blanca ven "Acceso denegado". La sesión queda guardada en el teléfono y se cierra con el botón de salir.
- **API:** todas las rutas exigen `X-Api-Key` o un token, excepto `POST /api/v1/auth/google` (recibe el ID token de Google y devuelve un token propio) y `POST /api/v1/auth/login`.

### 4. Páginas de la web

| Página | Para qué |
|---|---|
| Panel en vivo | Pasos, recaudación del día y alertas en tiempo real |
| Registrar paso | Foto, video o cámara del navegador |
| Consultar placa | Chat con el agente (texto, foto o video) |
| Pasos | Historial con imagen de evidencia |
| Alertas | Placas no registradas o sin saldo; se marcan como atendidas |
| Vehículos | CRUD. "Autocompletar con IA" llena placa, categoría y ejes desde una foto y guarda la imagen inferida |
| Casetas, Tarifas, Usuarios | Administración (solo administradores) |
| Estado de la IA | Si el endpoint responde, si el agente está cargado, la conexión con Colab y una prueba de inferencia |

### 5. API REST (`/api/v1`)

| Método | Ruta | Descripción |
|---|---|---|
| POST | `/auth/google` | ID token de Google → token de sesión propio |
| POST | `/pasos/frame` | Recibe un frame, llama a la inferencia, registra el paso y lo emite en tiempo real |
| POST | `/placas/consultar` | Estado de una placa por texto |
| POST | `/placas/consultar-imagen` | Estado de una placa por imagen (M1 + OCR) |
| GET | `/placas/{placa}/historial` | Historial de pasos de una placa |
| GET | `/casetas/{id}/resumen` | Conteo y recaudación del día |
| POST | `/agente/chat` | Mensaje (o foto) al agente; `conversacion` mantiene la memoria |

Extras: `GET /casetas`, `GET /inferencia/estado` y `POST /auth/login`. La documentación interactiva (OpenAPI, generada desde el código) está en `/docs/api`.

### 6. Privacidad
Los pasajeros solo se **cuentan**. En la evidencia, las personas se cubren con su silueta en color sólido. No hay reconocimiento facial ni se guardan recortes de rostros.

## Modelos

Por decisión del proyecto se usan **modelos ya entrenados** sobre datasets públicos, que el notebook descarga solo. No se entrenó con un dataset propio de Roboflow.

| Modelo | Qué es | Para qué |
|---|---|---|
| M1 | YOLOv9-t 384 (placas) + OCR CCT-XS v2 (`fast-plate-ocr`) | Placa y su texto |
| M2 | YOLO26n-seg (COCO), más "Van" de Open Images para el minibús | Tipo de vehículo (máscara por vehículo) y pasajeros |
| M3 | YOLOv8s (Open Images V7, clases Wheel y Tire); el centro de cada rueda es el keypoint | Ejes |
| Agente | Qwen 2.5 3B con Ollama | Redactar las respuestas del chat |

| Dataset | Imágenes | Clases | Clases que usa el sistema |
|---|---|---|---|
| [COCO 2017](https://docs.ultralytics.com/datasets/detect/coco) | 118.287 de entrenamiento, 5.000 de validación | 80 | car, motorcycle, bus, truck, person |
| [Open Images V7](https://docs.ultralytics.com/datasets/detect/open-images-v7) | 1.743.042 de entrenamiento, 41.620 de validación | 601 | Wheel, Tire, Van |
| Conjunto propio de evaluación | 20 fotos de vehículos registrados | 3 | auto (17), minibús (2), camión (1) |

**Métricas medidas en producción** con las 20 fotos propias (detalle en el informe técnico):

| Métrica | Resultado |
|---|---|
| Placas leídas exactamente (OCR) | 90 % (18 de 20), 94,3 % de caracteres correctos |
| Error en el conteo de ejes | 0 (18 de 18 correctos) |
| mAP50-95 de referencia | M2: 39,6 cajas y 33,9 máscaras en COCO · M3: 27,7 en Open Images V7 |
| Latencia de punta a punta | 3,75 s de media, 4,71 s en el percentil 95 |

## Tecnologías

| Componente | Tecnología |
|---|---|
| Web, panel y API | Laravel 12 (PHP 8.2), Blade, Tailwind, Vite |
| Base de datos | MySQL |
| Autenticación | Google OAuth 2.0 (Laravel Socialite), Sanctum para tokens de la API |
| Tiempo real | Pusher Channels + Laravel Echo |
| Documentación API | Scramble (OpenAPI) |
| Inferencia | Google Colab (GPU T4), Ultralytics YOLO, fast-plate-ocr, ONNX Runtime, FastAPI, Cloudflare Tunnel |
| Agente | Ollama + Qwen 2.5 3B, function calling desde Laravel |
| App móvil | Flutter (camera, image_picker, http, google_sign_in, shared_preferences) |
| Hosting | cPanel compartido con SSL (subdominio `peaje.ericluna.shop`) |

## Estructura del repositorio

```
sistema-peaje/
├── web/              Laravel: web, API, agente, tiempo real
├── inferencia/       peaje_colab.ipynb: un solo notebook con entorno, modelos, endpoint y agente;
│                     requirements.txt con las versiones fijas
├── movil/            App Flutter (Modo Caseta y Modo Consulta)
├── .env.example      Plantilla de variables (copiar a web/.env)
└── README.md
```

## Puesta en marcha

### Encender la inferencia (antes de usar el sistema)
1. Abrir `inferencia/peaje_colab.ipynb` en Google Colab → **Entorno de ejecución → Cambiar tipo de entorno → GPU T4** → **Ejecutar todo**.
2. La celda 10 muestra un **link** y un **token**. En la web, **Estado de la IA → Conexión con Colab** → pegarlos → **Guardar y probar**. Debe decir **Operativo** y **Agente IA: qwen2.5:3b**.
3. Dejar Colab abierto con la **celda 11** corriendo. Colab gratis se apaga a los ~90 min sin actividad o a las 12 h; el link y el token cambian en cada reinicio.

### Despliegue en cPanel (sin Terminal)
1. **Subdominio:** crear `peaje.ericluna.shop` con Document Root `peaje/public` y activar SSL y **Force HTTPS Redirect**.
2. **PHP:** versión 8.2 con las extensiones `pdo_mysql`, `mbstring`, `fileinfo`, `gd`, `curl`, `openssl`, `xml`, `ctype` y `tokenizer`.
3. **Paquete:** en tu PC, armar la carpeta `web/` con `composer install --no-dev`, compilar los assets con las variables `VITE_PUSHER_*` (`npm run build`) y agregar un `.env` de producción. Comprimirla, subirla a `/home/<usuario>/` y descomprimirla como `peaje/`.
4. **Base de datos:** crear la base y su usuario en **MySQL Databases** e importar el `.sql` en phpMyAdmin.
5. **Variables importantes del `.env`:**
   - `APP_ENV=production`, `APP_DEBUG=false` y `APP_URL` con el dominio;
   - `DB_*`, `PUSHER_*`, `GOOGLE_CLIENT_ID` y `GOOGLE_CLIENT_SECRET`, `MOVIL_API_KEY`;
   - `PUBLIC_STORAGE_EN_PUBLIC=true`, porque sin Terminal no se puede ejecutar `storage:link`.
6. **Google Cloud:** registrar el origen `https://peaje.ericluna.shop` y la URI de redirección `https://peaje.ericluna.shop/auth/google/callback`.

### App móvil
Desde la carpeta `movil/`:
```
flutter build apk --release --dart-define=API_BASE_URL=https://peaje.ericluna.shop/api/v1
```
Para el login con Google en Android hay que crear en Google Cloud un **ID de cliente de tipo Android** con el paquete `shop.ericluna.peaje` y el SHA-1 del certificado con que se firma el APK (`keytool -list -v -keystore %USERPROFILE%\.android\debug.keystore -alias androiddebugkey -storepass android`). La app usa el ID de cliente **web** (`GOOGLE_CLIENT_ID`) para pedir el ID token.
El APK queda en `movil/build/app/outputs/flutter-apk/app-release.apk`. Funciona por WiFi o con datos móviles.

### Desarrollo local
```bash
cd web
composer install
cp ../.env.example .env        # DB_* apuntando al MySQL local
php artisan key:generate
php artisan migrate --seed     # en local crea admin@peaje.test y operador@peaje.test (contraseña: password)
npm install && npm run build
composer servir                # web en http://127.0.0.1:8000 + servicio de inferencia local
php artisan test               # pruebas
```
- `composer servir-red` hace lo mismo, pero acepta conexiones de la red local, para probar la app en un teléfono por WiFi con un APK `--debug`.
- Sin Colab, la web puede usar los modelos ONNX dentro de PHP: elige **Local (ONNX en PHP)** en *Estado de la IA*. Es más lento y el agente responde sin Qwen.

## Entornos (versiones fijas por servicio)

| Servicio | Versiones | Diagnóstico |
|---|---|---|
| Inferencia (Colab) | [`inferencia/requirements.txt`](inferencia/requirements.txt); la celda 2 del notebook instala esas versiones exactas | La celda 2 imprime Python, torch, CUDA, GPU y ultralytics. En local: `python -m venv .venv` y `pip install -r requirements.txt` |
| Web (Laravel) | `web/composer.lock` y `web/package-lock.json` | `php artisan about` y la página **Estado de la IA** |
| App móvil | `movil/pubspec.lock` | `flutter --version` |

## Pruebas
- **Web:** 128 pruebas automáticas (PHPUnit). Cubren API, roles, login con Google, inferencia, agente y memoria, alertas, tiempo real y CRUD.
- **App:** `flutter analyze` sin advertencias y 10 pruebas de los modelos de datos.
- **De punta a punta:** en el navegador, en un emulador Android contra el servidor de producción y en un teléfono real.

## Enlaces

- **Subdominio (producción):** https://peaje.ericluna.shop
- **Documentación de la API:** https://peaje.ericluna.shop/docs/api
- **Repositorio:** https://github.com/eric758131/peaje
- **Endpoint de inferencia:** Google Colab con túnel de Cloudflare (`https://<túnel>.trycloudflare.com`, rutas `/health`, `/inferir` y `/agente/chat`). El link cambia en cada sesión de Colab y se configura en **Estado de la IA → Conexión con Colab**; esa página muestra el link activo.
- **Datasets:** no se usó un dataset propio de Roboflow; los modelos están entrenados en [COCO 2017](https://docs.ultralytics.com/datasets/detect/coco) y [Open Images V7](https://docs.ultralytics.com/datasets/detect/open-images-v7).
- **Carpeta de evidencias (video, informe, APK, capturas, API):** _(enlace de Google Drive)_

## Seguridad

- No se suben `.env`, tokens, contraseñas ni claves (`MOVIL_API_KEY`, `GOOGLE_CLIENT_SECRET`, `PUSHER_APP_SECRET`, `INFERENCE_TOKEN`…). Solo `.env.example`.
- El token de Colab se guarda cifrado en la base de datos.
- Las contraseñas se guardan con bcrypt y el login se bloquea tras 5 intentos fallidos.
- En producción no existen cuentas de prueba.
