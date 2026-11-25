@extends('layouts.app')

@section('titulo', 'Panel en vivo')

@php use App\Support\Etiquetas; @endphp

@section('contenido')
<x-encabezado titulo="Panel en vivo" subtitulo="Cada paso registrado por una caseta aparece aquí al instante, sin recargar.">
    <x-boton :href="route('registro')" icono="camara">Registrar paso</x-boton>
</x-encabezado>

{{-- Indicadores del día --}}
<div class="mb-6 grid grid-cols-2 gap-4 xl:grid-cols-4">
    @foreach ([
        ['contador-pasos', 'Pasos hoy', $resumen['pasos'], 'lista', 'bg-sky-50 text-sky-600'],
        ['contador-recaudado', 'Recaudado hoy', Etiquetas::bs($resumen['recaudado']), 'dinero', 'bg-emerald-50 text-emerald-600'],
        ['contador-alertas', 'Alertas pendientes', $resumen['alertas_pendientes'], 'alerta', 'bg-rose-50 text-rose-600'],
        [null, 'Vehículos activos', $vehiculosRegistrados, 'auto', 'bg-violet-50 text-violet-600'],
    ] as [$id, $titulo, $valor, $icono, $color])
        <div class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-slate-200/80">
            <div class="flex items-center justify-between">
                <p class="text-sm font-medium text-slate-500">{{ $titulo }}</p>
                <span class="grid size-9 place-items-center rounded-xl {{ $color }}"><x-icono :nombre="$icono" /></span>
            </div>
            <p @if ($id) id="{{ $id }}" @endif class="mt-3 text-3xl font-semibold tracking-tight text-slate-900 tabular-nums">{{ $valor }}</p>
        </div>
    @endforeach
</div>

<div class="grid grid-cols-1 gap-6 xl:grid-cols-3">
    <x-tarjeta titulo="Últimos pasos" subtitulo="Placa, evidencia, categoría, ejes, monto y estado" class="xl:col-span-2" :relleno="false">
        <x-slot:acciones><x-boton variante="secundario" tamano="sm" :href="route('pasos.index')">Ver historial</x-boton></x-slot:acciones>
        <div class="overflow-x-auto">
            <table class="tabla">
                <thead>
                    <tr><th>Hora</th><th>Evidencia</th><th>Placa</th><th>Vehículo</th><th class="text-right">Monto</th><th>Estado</th></tr>
                </thead>
                <tbody id="tabla-pasos">
                    @forelse ($pasos as $paso)
                        @include('partials.fila-paso', ['paso' => $paso])
                    @empty
                        <tr id="sin-pasos"><td colspan="6" class="py-10 text-center text-slate-500">Aún no hay pasos registrados.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-tarjeta>

    <div class="space-y-6">
        <x-tarjeta titulo="Alertas" subtitulo="Placas no registradas y sin saldo">
            <x-slot:acciones><x-boton variante="secundario" tamano="sm" :href="route('alertas.index')">Ver todas</x-boton></x-slot:acciones>
            <ul id="lista-alertas" class="space-y-2">
                @forelse ($alertas as $alerta)
                    <li class="flex items-start gap-3 rounded-xl bg-rose-50/60 p-3 ring-1 ring-rose-100">
                        <x-icono nombre="alerta" clase="mt-0.5 size-5 shrink-0 text-rose-500" />
                        <div class="min-w-0 flex-1">
                            <p class="text-sm font-medium text-slate-900">{{ Etiquetas::estado($alerta->tipo) }}
                                <span class="placa ml-1 text-xs">{{ $alerta->paso?->placa_normalizada }}</span></p>
                            <p class="mt-0.5 text-xs text-slate-500">{{ $alerta->paso?->caseta?->nombre }} · {{ $alerta->created_at->format('H:i:s') }}</p>
                        </div>
                    </li>
                @empty
                    <li id="sin-alertas" class="rounded-xl bg-slate-50 p-4 text-center text-sm text-slate-500">No hay alertas pendientes.</li>
                @endforelse
            </ul>
        </x-tarjeta>

        <x-tarjeta titulo="Pasos de hoy por categoría">
            <dl class="grid grid-cols-5 gap-2 text-center">
                @foreach ($porCategoria as $categoria => $total)
                    <div class="rounded-xl bg-slate-50 px-1 py-3">
                        <dd id="categoria-{{ $categoria }}" class="text-xl font-semibold text-slate-900 tabular-nums">{{ $total }}</dd>
                        <dt class="mt-0.5 text-[11px] text-slate-500">{{ Etiquetas::categoria($categoria) }}</dt>
                    </div>
                @endforeach
            </dl>
        </x-tarjeta>
    </div>
</div>

{{-- Plantilla para las filas que llegan en tiempo real --}}
<template id="plantilla-paso">
    @include('partials.fila-paso', ['paso' => null])
</template>
@endsection
