@extends('layouts.app')

@section('titulo', 'Registrar paso')

@section('contenido')
<x-encabezado titulo="Registrar paso"
              subtitulo="Sube una foto o un video, o usa la cámara. Cada imagen pasa por la inferencia (placa, categoría, ejes y pasajeros) y el paso aparece en el panel en vivo." />

@if ($casetas->isEmpty())
    <x-tarjeta>
        <p class="text-sm text-slate-600">No hay casetas activas. {{ auth()->user()->esAdministrador() ? 'Crea una en' : 'Pide a un administrador que cree una en' }}
            <a class="enlace" href="{{ auth()->user()->esAdministrador() ? route('casetas.create') : '#' }}">Casetas</a>.</p>
    </x-tarjeta>
@else
<div id="registro" data-url="{{ route('registro.frame') }}" data-en-vuelo="{{ config('peaje.frames_en_paralelo') }}" class="grid grid-cols-1 gap-6 xl:grid-cols-5">

    {{-- Entrada --}}
    <div class="space-y-6 xl:col-span-3">
        <x-tarjeta>
            <div class="grid gap-4 sm:grid-cols-2">
                <x-campo etiqueta="Caseta" nombre="caseta">
                    <select id="caseta" class="control">
                        @foreach ($casetas as $caseta)
                            <option value="{{ $caseta->id }}">{{ $caseta->nombre }}{{ $caseta->ubicacion ? ' — '.$caseta->ubicacion : '' }}</option>
                        @endforeach
                    </select>
                </x-campo>
                <x-campo etiqueta="Intervalo entre frames" nombre="intervalo" ayuda="Para video y cámara. Se envían hasta {{ config('peaje.frames_en_paralelo') }} frame(s) a la vez; el ritmo real depende de la inferencia.">
                    <select id="intervalo" class="control">
                        <option value="0.2">0,2 s (5 fps)</option>
                        <option value="0.33">0,33 s (3 fps)</option>
                        <option value="0.5" selected>0,5 s (2 fps)</option>
                        <option value="1">1 s</option>
                        <option value="2">2 s</option>
                    </select>
                </x-campo>
            </div>

            <div class="mt-5 inline-flex rounded-xl bg-slate-100 p-1" role="tablist">
                @foreach (['imagen' => ['Foto', 'foto'], 'video' => ['Video', 'video'], 'camara' => ['Cámara en vivo', 'camara']] as $modo => [$texto, $icono])
                    <button type="button" data-modo="{{ $modo }}" @class([
                        'modo inline-flex items-center gap-2 rounded-lg px-3 py-1.5 text-sm font-medium transition',
                        'bg-white text-slate-900 shadow-sm' => $loop->first,
                        'text-slate-600 hover:text-slate-900' => ! $loop->first,
                    ])><x-icono :nombre="$icono" clase="size-4" /> {{ $texto }}</button>
                @endforeach
            </div>

            <div class="mt-5">
                <div data-panel="imagen" class="flex flex-wrap items-center gap-3">
                    <input id="archivo-imagen" type="file" accept="image/*" class="control-archivo max-w-sm">
                    <x-boton id="analizar-imagen" type="button" icono="chispa">Analizar foto</x-boton>
                </div>
                <div data-panel="video" class="hidden flex-wrap items-center gap-3">
                    <input id="archivo-video" type="file" accept="video/*" class="control-archivo max-w-sm">
                    <x-boton id="procesar-video" type="button" icono="play">Procesar video</x-boton>
                </div>
                <div data-panel="camara" class="hidden flex-wrap items-center gap-3">
                    <x-boton id="iniciar-camara" type="button" icono="camara">Iniciar cámara</x-boton>
                    <p class="text-xs text-slate-500">Usa la cámara trasera en el celular. Requiere HTTPS.</p>
                </div>
                <div class="mt-3 flex items-center gap-3">
                    <div id="detener-caja" class="hidden"><x-boton id="detener" type="button" variante="peligro" icono="stop">Detener</x-boton></div>
                    <div id="progreso" class="hidden h-2 flex-1 overflow-hidden rounded-full bg-slate-100">
                        <div id="progreso-barra" class="h-full w-0 rounded-full bg-sky-500 transition-all"></div>
                    </div>
                    <span id="progreso-texto" class="text-xs text-slate-500 tabular-nums"></span>
                </div>
            </div>
        </x-tarjeta>

        <x-tarjeta titulo="Vista de la inferencia" subtitulo="Máscara del vehículo, placa, llantas (keypoints) y pasajeros (solo conteo, cubiertos)" :relleno="false">
            <div class="relative aspect-video overflow-hidden rounded-b-2xl bg-slate-900">
                <video id="vista-video" class="absolute inset-0 hidden size-full object-contain" playsinline muted></video>
                <img id="evidencia" alt="Evidencia de la inferencia" class="absolute inset-0 hidden size-full object-contain">
                <img id="evidencia-mini" alt="Última evidencia" class="absolute right-3 bottom-3 hidden w-1/3 rounded-lg shadow-lg ring-2 ring-white/80">
                <div id="vista-vacia" class="absolute inset-0 grid place-items-center text-center text-sm text-slate-400">
                    <div><x-icono nombre="foto" clase="mx-auto mb-2 size-10 text-slate-600" />Aún no se analizó ninguna imagen.</div>
                </div>
                <div id="analizando" class="absolute top-3 right-3 hidden items-center gap-2 rounded-full bg-slate-950/70 px-3 py-1 text-xs font-medium text-white">
                    <span class="size-2 animate-pulse rounded-full bg-sky-400"></span> Analizando…
                </div>
            </div>
        </x-tarjeta>
    </div>

    {{-- Resultado --}}
    <div class="space-y-6 xl:col-span-2">
        <x-tarjeta titulo="Último resultado">
            <div id="mensaje" class="mb-4 rounded-xl bg-slate-50 px-4 py-3 text-sm text-slate-600">Esperando una imagen…</div>
            <dl class="grid grid-cols-2 gap-x-4 gap-y-3 text-sm">
                @foreach (['placa' => 'Placa', 'confianza' => 'Confianza OCR', 'categoria' => 'Categoría', 'ejes' => 'Ejes', 'pasajeros' => 'Pasajeros', 'monto' => 'Monto', 'estado' => 'Estado', 'latencia' => 'Latencia', 'propietario' => 'Propietario', 'saldo' => 'Saldo restante'] as $campo => $texto)
                    <div>
                        <dt class="text-xs text-slate-500">{{ $texto }}</dt>
                        <dd id="r-{{ $campo }}" class="mt-0.5 font-medium text-slate-900">—</dd>
                    </div>
                @endforeach
            </dl>
            <a id="r-enlace" href="#" class="enlace mt-4 hidden text-sm">Ver el paso registrado →</a>
        </x-tarjeta>

        <x-tarjeta titulo="Esta sesión" :relleno="false">
            <div class="grid grid-cols-3 divide-x divide-slate-100 border-b border-slate-100 text-center">
                <div class="p-3"><p id="c-frames" class="text-xl font-semibold tabular-nums">0</p><p class="text-xs text-slate-500">Frames</p></div>
                <div class="p-3"><p id="c-pasos" class="text-xl font-semibold text-emerald-600 tabular-nums">0</p><p class="text-xs text-slate-500">Pasos nuevos</p></div>
                <div class="p-3"><p id="c-sin" class="text-xl font-semibold text-slate-500 tabular-nums">0</p><p class="text-xs text-slate-500">Sin placa</p></div>
            </div>
            <ul id="bitacora" class="max-h-96 divide-y divide-slate-100 overflow-y-auto text-sm">
                <li class="p-4 text-center text-slate-500">Sin actividad.</li>
            </ul>
        </x-tarjeta>
    </div>
</div>
@endif
@endsection
