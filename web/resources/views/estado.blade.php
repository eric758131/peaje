@extends('layouts.app')

@section('titulo', 'Estado de la IA')

@section('contenido')
@php
    $d = $estado['diagnostico'] ?? null;
    $si = fn ($v) => $v ? 'Sí' : 'No';
@endphp

<x-encabezado titulo="Estado de la inferencia" subtitulo="Modelos: M1 placa + OCR · M2 segmentación (categoría y pasajeros) · M3 llantas (ejes)">
    <x-boton variante="secundario" :href="route('estado')">Volver a comprobar</x-boton>
</x-encabezado>

<div class="grid grid-cols-1 gap-6 xl:grid-cols-3">
    <div class="space-y-6">
        <x-tarjeta>
            <div class="flex items-center gap-4">
                <span @class(['grid size-12 place-items-center rounded-2xl', 'bg-emerald-50 text-emerald-600' => $estado['disponible'], 'bg-rose-50 text-rose-600' => ! $estado['disponible']])>
                    <x-icono :nombre="$estado['disponible'] ? 'ok' : 'alerta'" clase="size-6" />
                </span>
                <div>
                    <p @class(['text-lg font-semibold', 'text-emerald-700' => $estado['disponible'], 'text-rose-700' => ! $estado['disponible']])>
                        {{ $estado['disponible'] ? 'Operativo' : 'No disponible' }}
                    </p>
                    <p class="text-sm text-slate-500">Driver: <span class="font-medium">{{ ($estado['driver'] ?? '') === 'http' ? 'Colab / endpoint externo' : 'local (ONNX en PHP)' }}</span></p>
                </div>
            </div>
            <p class="mt-4 rounded-xl bg-slate-50 p-3 text-sm text-slate-600">{{ $estado['detalle'] }}</p>
            @if (! empty($estado['url']))
                <p class="mt-2 truncate font-mono text-xs text-slate-500" title="{{ $estado['url'] }}">{{ $estado['url'] }}</p>
            @endif
            @if (array_key_exists('agente', $estado) && $estado['agente'] !== null)
                <p class="mt-3 flex items-center gap-2 text-sm">
                    <span @class(['text-emerald-600' => $estado['agente'], 'text-amber-600' => ! $estado['agente']])><x-icono :nombre="$estado['agente'] ? 'chat' : 'alerta'" clase="size-4" /></span>
                    <span class="text-slate-500">Agente IA:</span>
                    <span class="font-medium text-slate-900">{{ $estado['agente'] ?: 'no cargado en Colab (responde solo con la base)' }}</span>
                </p>
            @endif
        </x-tarjeta>

        @if (auth()->user()->esAdministrador())
            <x-tarjeta titulo="Conexión con Colab" subtitulo="Pega el link y el token que muestra la celda 10 cada vez que reinicias el notebook.">
                <form method="POST" action="{{ route('estado.conexion') }}" class="space-y-4">
                    @csrf
                    @method('PUT')
                    <x-campo etiqueta="Inferencia" nombre="driver">
                        <select id="driver" name="driver" class="control">
                            <option value="http" @selected(old('driver', $conexion['driver']) === 'http')>Colab (endpoint externo)</option>
                            <option value="local" @selected(old('driver', $conexion['driver']) !== 'http')>Local (ONNX en PHP)</option>
                        </select>
                    </x-campo>
                    <x-campo etiqueta="Link" nombre="url">
                        <input id="url" name="url" type="url" value="{{ old('url', $conexion['url']) }}" placeholder="https://xxxx.trycloudflare.com" maxlength="500" class="control font-mono text-xs">
                    </x-campo>
                    <x-campo etiqueta="Token" nombre="token" :ayuda="$conexion['token'] ? 'Hay un token guardado. Déjalo vacío para mantenerlo.' : 'El token que imprime la celda 10.'">
                        <input id="token" name="token" type="password" autocomplete="off" maxlength="255" placeholder="{{ $conexion['token'] ? '•••••••• (guardado)' : '' }}" class="control font-mono text-xs">
                    </x-campo>
                    <div class="flex flex-wrap items-center justify-between gap-3 pt-1">
                        <x-boton icono="ok">Guardar y probar</x-boton>
                        @if ($conexion['desde_web'])
                            <x-boton variante="fantasma" tamano="sm" form="form-restablecer">Usar el .env</x-boton>
                        @endif
                    </div>
                    <p class="text-xs text-slate-500">
                        {{ $conexion['desde_web'] ? 'Se usa la conexión guardada aquí (tiene prioridad sobre el .env).' : 'Ahora se usa la conexión del archivo .env.' }}
                        El token se guarda cifrado.
                    </p>
                </form>
                @if ($conexion['desde_web'])
                    <form id="form-restablecer" method="POST" action="{{ route('estado.conexion.restablecer') }}">
                        @csrf
                        @method('DELETE')
                    </form>
                @endif
            </x-tarjeta>
        @endif

        @if ($d)
            <x-tarjeta titulo="Diagnóstico del servidor" :relleno="false">
                <dl class="divide-y divide-slate-100 text-sm">
                    @foreach ([
                        'PHP' => $d['php'].' ('.$d['sapi'].')',
                        'Extensión FFI cargada' => $si($d['ffi_cargada']),
                        'ffi.enable' => $d['ffi_enable'] === false ? '—' : ($d['ffi_enable'] ?: '0'),
                        'FFI en la petición web' => $si($d['ffi_permitido']),
                        'proc_open disponible' => $si($d['proc_open']),
                        'Modo' => $d['modo_configurado'].' → '.($d['modo_efectivo'] ?? 'ninguno'),
                        'PHP de consola' => $d['php_consola'],
                        'FFI en la consola' => isset($d['consola']) ? $si($d['consola']['ffi_permitido'] ?? false) : 'no se consultó',
                        'Extensión GD' => $si($d['gd']),
                        'memory_limit' => $d['memoria'],
                        'ONNX Runtime' => $d['libreria']['existe'] ? 'Encontrada' : 'Falta: '.$d['libreria']['ruta'],
                    ] as $clave => $valor)
                        <div class="flex justify-between gap-4 px-5 py-2.5">
                            <dt class="text-slate-500">{{ $clave }}</dt>
                            <dd class="text-right font-medium break-all text-slate-900">{{ $valor }}</dd>
                        </div>
                    @endforeach
                </dl>
            </x-tarjeta>
        @endif
    </div>

    <div class="space-y-6 xl:col-span-2">
        @if ($d)
            <x-tarjeta titulo="Modelos" :relleno="false">
                <table class="tabla">
                    <thead><tr><th>Modelo</th><th>Archivo</th><th>Tamaño</th><th>Estado</th></tr></thead>
                    <tbody>
                        @foreach ([
                            'placas' => 'M1 · Detector de placas (YOLOv9-t)',
                            'ocr' => 'M1 · OCR de placas (CCT)',
                            'vehiculos' => 'M2 · Segmentación de vehículos y personas (YOLO26n-seg, COCO)',
                            'llantas' => 'M3 · Llantas → ejes, y minibús (YOLOv8s, Open Images V7)',
                        ] as $clave => $texto)
                            @php $m = $d['modelos'][$clave] ?? null; @endphp
                            <tr>
                                <td class="font-medium text-slate-900">{{ $texto }}</td>
                                <td class="font-mono text-xs text-slate-500">{{ $m['archivo'] ?? '—' }}</td>
                                <td class="whitespace-nowrap tabular-nums">{{ $m && $m['mb'] ? $m['mb'].' MB' : '—' }}</td>
                                <td><x-insignia :estado="$m && $m['existe'] ? 'activo' : 'no_registrado'" :texto="$m && $m['existe'] ? 'Listo' : 'Falta'" /></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </x-tarjeta>
        @endif

        <x-tarjeta titulo="Probar la inferencia" subtitulo="Analiza una foto o un video sin registrar pasos.">
            <form id="form-prueba" action="{{ route('inferencia.probar') }}" class="flex flex-wrap items-center gap-3">
                <input id="archivo-prueba" type="file" accept="image/*,video/*" class="control-archivo max-w-sm">
                <x-boton icono="chispa">Analizar</x-boton>
            </form>
            <div class="mt-5 grid gap-4 lg:grid-cols-2">
                <img id="vista-prueba" alt="Evidencia" class="hidden w-full rounded-xl ring-1 ring-slate-200">
                <pre id="salida-prueba" class="max-h-96 overflow-auto rounded-xl bg-slate-950 p-4 font-mono text-xs text-slate-200">Sube una imagen para ver la respuesta JSON.</pre>
            </div>
        </x-tarjeta>
    </div>
</div>
@endsection
