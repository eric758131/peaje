@extends('layouts.app')

@section('titulo', 'Consultar placa')

@section('contenido')
<x-encabezado titulo="Consultar placa" subtitulo="Escribe una placa, o envía una foto o un video y pregunta qué hay. El agente solo responde con datos del sistema y de la imagen." />

<div class="grid grid-cols-1 gap-6 xl:grid-cols-3">
    <x-tarjeta class="xl:col-span-2" :relleno="false">
        <div data-chat data-reiniciar="{{ route('chat.reiniciar') }}">
            <div class="flex items-center justify-between border-b border-slate-100 px-5 py-3">
                <p class="text-sm font-medium text-slate-700">Agente IA</p>
                <button type="button" data-chat-nueva class="text-xs font-medium text-slate-500 hover:text-slate-900">Nueva conversación</button>
            </div>
            <div id="salida-chat" data-chat-salida class="flex h-[28rem] flex-col gap-3 overflow-y-auto bg-slate-50 p-5"></div>

            <form id="form-chat" data-chat-form action="{{ route('chat.enviar') }}" method="POST" enctype="multipart/form-data"
                  class="flex flex-col gap-3 border-t border-slate-100 p-4 sm:flex-row sm:items-center">
                @csrf
                <input name="mensaje" type="text" maxlength="1000" placeholder="Escribe una placa o una pregunta…" class="control flex-1" autocomplete="off">
                <label class="inline-flex cursor-pointer items-center gap-2 rounded-lg px-3 py-2 text-sm font-medium text-slate-600 ring-1 ring-slate-300 hover:bg-slate-50">
                    <x-icono nombre="foto" clase="size-4" /> Foto / video
                    <input id="archivo-chat" data-chat-archivo name="imagen" type="file" accept="image/*,video/*" class="sr-only">
                </label>
                <span data-chat-nombre class="max-w-40 truncate text-xs text-slate-500"></span>
                <x-boton icono="chat">Enviar</x-boton>
            </form>
        </div>
    </x-tarjeta>

    <x-tarjeta titulo="Qué puede responder">
        <ul class="space-y-3 text-sm">
            @foreach ([
                ['cobrado', 'Registrada y con paso', 'Fecha, caseta, categoría y monto del último paso.'],
                ['registrado', 'Registrada sin paso', 'Está en el sistema pero nunca pasó por el peaje.'],
                ['no_registrado', 'Identificada no registrada', 'La cámara la vio pero no está registrada: posible evasión.'],
                ['sin_placa', 'No existe', 'No hay ningún registro de esa placa.'],
            ] as [$color, $titulo, $texto])
                <li class="flex gap-3">
                    <x-insignia :estado="$color" :texto="$loop->iteration" class="mt-0.5 size-6 shrink-0 justify-center" />
                    <div><p class="font-medium text-slate-900">{{ $titulo }}</p><p class="text-slate-500">{{ $texto }}</p></div>
                </li>
            @endforeach
        </ul>
        <p class="mt-4 rounded-xl bg-slate-50 p-3 text-xs text-slate-500">
            Con una foto también responde qué hay en ella: cuántos vehículos y de qué tipo, cuántas llantas, personas y placas.
            Puedes preguntar después, por ejemplo: «¿cuántas llantas hay?».
            Si el OCR confunde letras y números (O/0, I/1, B/8…), sugiere «¿Quisiste decir…?».
        </p>
    </x-tarjeta>
</div>
@endsection
