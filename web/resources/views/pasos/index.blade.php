@extends('layouts.app')

@section('titulo', 'Pasos')

@php use App\Support\Etiquetas; @endphp

@section('contenido')
<x-encabezado titulo="Historial de pasos" subtitulo="Cada paso se crea con la inferencia: no se registran a mano.">
    <x-boton :href="route('registro')" icono="camara">Registrar paso</x-boton>
</x-encabezado>

<x-tarjeta class="mb-6">
    <form method="GET" action="{{ route('pasos.index') }}" class="grid gap-3 sm:grid-cols-2 lg:grid-cols-5">
        <input name="placa" value="{{ request('placa') }}" placeholder="Placa" class="control font-mono uppercase">
        <select name="estado" class="control">
            <option value="">Todos los estados</option>
            @foreach (['cobrado', 'sin_saldo', 'no_registrado'] as $estado)
                <option value="{{ $estado }}" @selected(request('estado') === $estado)>{{ Etiquetas::estado($estado) }}</option>
            @endforeach
        </select>
        <select name="caseta_id" class="control">
            <option value="">Todas las casetas</option>
            @foreach ($casetas as $caseta)
                <option value="{{ $caseta->id }}" @selected((string) request('caseta_id') === (string) $caseta->id)>{{ $caseta->nombre }}</option>
            @endforeach
        </select>
        <input type="date" name="fecha" value="{{ request('fecha') }}" class="control">
        <div class="flex gap-2">
            <x-boton variante="oscuro" icono="buscar" class="flex-1">Filtrar</x-boton>
            @if (request()->hasAny(['placa', 'estado', 'caseta_id', 'fecha']))
                <x-boton variante="fantasma" :href="route('pasos.index')">Limpiar</x-boton>
            @endif
        </div>
    </form>
</x-tarjeta>

<x-tarjeta :relleno="false">
    <div class="overflow-x-auto">
        <table class="tabla">
            <thead>
                <tr><th>Fecha</th><th>Evidencia</th><th>Placa</th><th>Vehículo</th><th class="text-right">Monto</th><th>Estado</th><th>Operador</th></tr>
            </thead>
            <tbody>
                @forelse ($pasos as $paso)
                    <tr>
                        <td class="whitespace-nowrap text-slate-500 tabular-nums">{{ $paso->created_at->format('d/m/Y H:i:s') }}</td>
                        <td>
                            <a href="{{ route('pasos.show', $paso) }}">
                                @if ($paso->imagen_url)
                                    <img src="{{ $paso->imagen_url }}" alt="Evidencia" class="h-11 w-16 rounded-md object-cover ring-1 ring-slate-200" loading="lazy">
                                @else
                                    <span class="grid h-11 w-16 place-items-center rounded-md bg-slate-100 text-slate-400"><x-icono nombre="foto" clase="size-4" /></span>
                                @endif
                            </a>
                        </td>
                        <td>
                            <a href="{{ route('pasos.show', $paso) }}" class="placa hover:border-sky-400">{{ $paso->placa_normalizada ?? '—' }}</a>
                            <p class="mt-1 text-xs text-slate-500">{{ $paso->caseta?->nombre }}</p>
                        </td>
                        <td class="whitespace-nowrap">
                            <span class="font-medium">{{ Etiquetas::categoria($paso->categoria_detectada) }}</span>
                            <span class="block text-xs text-slate-500">{{ $paso->ejes_detectados ?? '—' }} ejes{{ $paso->pasajeros !== null ? ' · '.$paso->pasajeros.' pasajero(s)' : '' }}</span>
                        </td>
                        <td class="text-right font-medium whitespace-nowrap tabular-nums">{{ Etiquetas::bs($paso->monto) }}</td>
                        <td><x-insignia :estado="$paso->estado" /></td>
                        <td class="text-slate-500">{{ $paso->usuario?->nombre ?? '—' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="py-10 text-center text-slate-500">No hay pasos con esos filtros.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</x-tarjeta>

<div class="mt-4">{{ $pasos->links() }}</div>
@endsection
