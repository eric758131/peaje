@extends('layouts.app')

@section('titulo', 'Paso '.$paso->id)

@php use App\Support\Etiquetas; @endphp

@section('contenido')
<x-encabezado :titulo="'Paso #'.$paso->id" :subtitulo="$paso->created_at->format('d/m/Y H:i:s').' · '.$paso->caseta?->nombre">
    <x-boton variante="secundario" :href="route('pasos.index')">Volver</x-boton>
    @if (auth()->user()->esAdministrador())
        <form method="POST" action="{{ route('pasos.destroy', $paso) }}"
              onsubmit="return confirm('¿Anular este paso?{{ $paso->estado === 'cobrado' ? ' Se devolverá el monto al saldo del vehículo.' : '' }}')">
            @csrf @method('DELETE')
            <x-boton variante="peligro" icono="basura">Anular paso</x-boton>
        </form>
    @endif
</x-encabezado>

<div class="grid grid-cols-1 gap-6 xl:grid-cols-3">
    <x-tarjeta titulo="Evidencia" subtitulo="Imagen anotada por la inferencia: vehículo, placa y llantas. Las personas solo se cuentan y aparecen cubiertas." class="xl:col-span-2" :relleno="false">
        @if ($paso->imagen_url)
            <a href="{{ $paso->imagen_url }}" target="_blank"><img src="{{ $paso->imagen_url }}" alt="Evidencia" class="w-full rounded-b-2xl"></a>
        @else
            <p class="p-10 text-center text-sm text-slate-500">Sin imagen de evidencia.</p>
        @endif
    </x-tarjeta>

    <div class="space-y-6">
        <x-tarjeta titulo="Lectura">
            <dl class="space-y-3 text-sm">
                @foreach ([
                    'Placa normalizada' => new \Illuminate\Support\HtmlString('<span class="placa">'.e($paso->placa_normalizada ?? '—').'</span>'),
                    'Texto leído por el OCR' => $paso->placa_leida ?? '—',
                    'Confianza OCR' => $paso->confianza_ocr !== null ? $paso->confianza_ocr.' %' : '—',
                    'Categoría' => Etiquetas::categoria($paso->categoria_detectada),
                    'Ejes' => $paso->ejes_detectados ?? '—',
                    'Pasajeros' => $paso->pasajeros ?? '—',
                    'Latencia de inferencia' => $paso->latencia_ms !== null ? $paso->latencia_ms.' ms' : '—',
                ] as $clave => $valor)
                    <div class="flex items-center justify-between gap-4">
                        <dt class="text-slate-500">{{ $clave }}</dt>
                        <dd class="text-right font-medium text-slate-900">{{ $valor }}</dd>
                    </div>
                @endforeach
            </dl>
        </x-tarjeta>

        <x-tarjeta titulo="Cobro">
            <div class="flex items-center justify-between">
                <p class="text-2xl font-semibold text-slate-900 tabular-nums">{{ Etiquetas::bs($paso->monto) }}</p>
                <x-insignia :estado="$paso->estado" />
            </div>
            <dl class="mt-4 space-y-3 text-sm">
                <div class="flex justify-between"><dt class="text-slate-500">Vehículo</dt>
                    <dd class="font-medium">
                        @if ($paso->vehiculo)
                            <a class="enlace" href="{{ route('vehiculos.show', $paso->vehiculo) }}">{{ $paso->vehiculo->propietario ?: $paso->vehiculo->placa }}</a>
                        @else
                            No registrado
                        @endif
                    </dd></div>
                <div class="flex justify-between"><dt class="text-slate-500">Operador</dt><dd class="font-medium">{{ $paso->usuario?->nombre ?? '—' }}</dd></div>
            </dl>
            @foreach ($paso->alertas as $alerta)
                <div class="mt-4 flex items-center justify-between rounded-xl bg-rose-50 p-3 text-sm ring-1 ring-rose-100">
                    <span class="font-medium text-rose-700">{{ Etiquetas::estado($alerta->tipo) }}</span>
                    @if ($alerta->atendida)
                        <x-insignia estado="activo" texto="Atendida" />
                    @else
                        <form method="POST" action="{{ route('alertas.atender', $alerta) }}">@csrf
                            <x-boton variante="secundario" tamano="sm">Marcar atendida</x-boton>
                        </form>
                    @endif
                </div>
            @endforeach
        </x-tarjeta>
    </div>
</div>
@endsection
