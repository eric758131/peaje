<?php

/*
|--------------------------------------------------------------------------
| Configuración del sistema de peaje
|--------------------------------------------------------------------------
*/

return [

    /*
    | Inferencia (M1 placa+OCR, M2 categoría y pasajeros, M3 ejes).
    |
    | driver = local: los modelos ONNX corren dentro de PHP (extensión FFI + ONNX Runtime).
    |                 El servidor no necesita Python.
    | driver = http:  se llama a un endpoint externo (Colab o RunPod) en INFERENCE_URL.
    */
    'inferencia' => [
        'driver' => env('INFERENCE_DRIVER', 'local'),

        // Driver http
        'url' => env('INFERENCE_URL'),
        'token' => env('INFERENCE_TOKEN'),
        'timeout' => (int) env('INFERENCE_TIMEOUT', 60),
        // Lado máximo de la imagen que se envía al endpoint (se recomprime a JPEG 85 porque el túnel es lento).
        // No bajar de 1280: con 1024 o menos el OCR se equivoca más en placas chicas. 0 = enviar tal cual.
        'lado_envio' => (int) env('INFERENCE_LADO_ENVIO', 1280),

        // Driver local
        'local' => [
            // auto: usa FFI dentro de la petición si está permitido; si no, lanza un proceso PHP de consola.
            // ffi:  solo dentro de la petición.  cli: siempre en un proceso de consola.
            'modo' => env('INFERENCE_LOCAL_MODO', 'auto'),
            // URL de `php artisan peaje:servidor-inferencia` (modelos cargados en memoria, más rápido).
            // Si está vacía o no responde, se usa el modo de arriba. Solo para desarrollo o un VPS.
            'servidor' => env('INFERENCE_LOCAL_SERVIDOR'),
            // Ruta del PHP de consola para el modo cli (en cPanel suele ser /usr/local/bin/php).
            'php_cli' => env('INFERENCE_PHP_CLI'),
            // Argumentos extra para ese PHP, separados por espacios (ej. "-d extension=ffi").
            'php_cli_args' => array_values(array_filter(explode(' ', (string) env('INFERENCE_PHP_CLI_ARGS', '')))),
            // Ruta a libonnxruntime.so / onnxruntime.dll. Vacío: la que descarga el paquete.
            'onnxruntime_lib' => env('ONNXRUNTIME_LIB'),
            // Hilos de ONNX Runtime. En hosting compartido conviene 1 o 2.
            'hilos' => (int) env('INFERENCE_HILOS', 2),
            // Desactiva el modelo de llantas si el servidor tiene poca memoria (los ejes se estiman por categoría).
            'llantas' => (bool) env('INFERENCE_LLANTAS', true),
            // Lado máximo de la imagen de trabajo y de la evidencia (las fotos grandes se reducen).
            'lado_maximo' => (int) env('INFERENCE_LADO_MAXIMO', 1280),
            'modelos' => [
                'placas' => base_path('modelos/placas-detector.onnx'),
                'ocr' => base_path('modelos/placas-ocr.onnx'),
                'vehiculos' => base_path('modelos/vehiculos-seg.onnx'),
                'llantas' => base_path('modelos/llantas-oiv7.onnx'),
            ],
        ],
    ],

    /*
    | Si la misma placa vuelve a leerse en la misma caseta dentro de esta ventana (segundos),
    | no se cobra de nuevo: un video envía muchos frames del mismo vehículo.
    */
    'ventana_duplicado' => (int) env('VENTANA_DUPLICADO', 60),

    /*
    | Frames que la caseta web envía a la vez sin esperar respuesta (video y cámara).
    | 3 para llegar a 2-5 fps con el endpoint de Colab y un hosting con varios procesos PHP.
    | En la PC con `composer servir` usa 1: ese servidor atiende una petición a la vez.
    */
    'frames_en_paralelo' => max(1, (int) env('FRAMES_EN_PARALELO', 3)),

    /*
    | Ejes por defecto cuando no se ven las llantas.
    */
    'ejes_por_categoria' => ['moto' => 2, 'auto' => 2, 'minibus' => 2, 'camion' => 2, 'bus' => 2],

    /*
    | Cuentas que se cargan como administradores iniciales (se usa en el seeder).
    | Se leen de .env para no subir correos al repositorio.
    */
    'admins_iniciales' => array_filter(array_map('trim', explode(',', (string) env('ADMIN_EMAILS', '')))),

    /*
    | Contraseña inicial de esas cuentas. Vacía: el seeder genera una y la muestra en consola.
    */
    'admin_password' => env('ADMIN_PASSWORD'),

    /*
    | Inicio de sesión con contraseña además de Google. La guía pide acceso solo con Google:
    | en producción, cuando Google ya funcione, poner LOGIN_CON_CONTRASENA=false.
    */
    'login_con_contrasena' => (bool) env('LOGIN_CON_CONTRASENA', true),

    /*
    | Clave de la app móvil. La app no tiene login: envía esta clave en la cabecera
    | X-Api-Key en cada petición. Vacía = la API solo acepta tokens de POST /api/v1/auth/google o /auth/login.
    */
    'api_key' => env('MOVIL_API_KEY'),

    /*
    | Variantes de OCR: caracteres que se confunden al leer una placa (uno o varios por carácter).
    */
    'confusiones_ocr' => [
        'O' => ['0', 'D', 'Q'], '0' => ['O', 'D', 'Q'], 'D' => ['0', 'O'], 'Q' => ['0', 'O'],
        'I' => ['1', 'L'], 'L' => ['1', 'I'], '1' => ['I', 'L'],
        'B' => '8', '8' => 'B',
        'S' => '5', '5' => 'S',
        'Z' => '2', '2' => 'Z',
        'G' => '6', '6' => 'G',
        'A' => '4', '4' => 'A',
        'T' => '7', '7' => 'T',
    ],

    /*
    | Confianza mínima del OCR para aceptar una lectura sin marcarla como dudosa.
    */
    /*
    | Agente conversacional: Qwen servido con Ollama en el mismo Colab de la inferencia
    | (usa el mismo link y token). Si Colab no responde, contesta con los datos de la base sin LLM.
    */
    'agente' => [
        'timeout' => (int) env('AGENTE_TIMEOUT', 60),
    ],

    'confianza_minima_ocr' => (float) env('CONFIANZA_MINIMA_OCR', 0.6),

    /*
    | Una lectura SIN formato boliviano (ni corrigiendo un carácter) solo se registra como paso
    | si el OCR supera esta confianza: placa extranjera bien leída. Debajo, es lectura dudosa.
    */
    'confianza_sin_formato' => (float) env('CONFIANZA_SIN_FORMATO', 0.9),

    /*
    | En un frame con varios vehículos, los que no son el principal (lejanos, placa chica) solo
    | generan paso si el OCR supera esta confianza. Se siguen viendo todos en la evidencia.
    */
    'confianza_vehiculo_secundario' => (float) env('CONFIANZA_VEHICULO_SECUNDARIO', 0.9),

    /*
    | Formato de placa boliviana: 3 o 4 dígitos seguidos de 3 letras (ej. 1234ABC).
    */
    'formato_placa' => '/^\d{3,4}[A-Z]{3}$/',
];
