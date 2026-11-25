@extends('layouts.app')

@section('titulo', 'Vehículo '.$vehiculo->placa)

@php use App\Support\Etiquetas; @endphp

@section('contenido')
<x-encabezado :titulo="$vehiculo->propietario ?: 'Vehículo'" :subtitulo="Etiquetas::categoria($vehiculo->categoria).' · '.$vehiculo->ejes.' ejes'">
    <x-boton variante="secundario" :href="route('vehiculos.index')">Volver</x-boton>
    @if (auth()->user()->esAdministrador())
        <x-boton icono="lapiz" :href="route('vehiculos.edit', $vehiculo)">Editar</x-boton>
    @endif
</x-encabezado>

<div class="mb-6 grid grid-cols-2 gap-4 lg:grid-cols-4">
    <div class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-slate-200/80">
        <p class="text-sm text-slate-500">Placa</p><p class="placa mt-2 text-lg">{{ $vehiculo->placa }}</p>
    </div>
    <div class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-slate-200/80">
        <p class="text-sm text-slate-500">Saldo</p><p class="mt-2 text-2xl font-semibold tabular-nums">{{ Etiquetas::bs($vehiculo->saldo) }}</p>
    </div>
    <div class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-slate-200/80">
        <p class="text-sm text-slate-500">Pasos</p><p class="mt-2 text-2xl font-semibold tabular-nums">{{ $pasos->total() }}</p>
    </div>
    <div class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-slate-200/80">
        <p class="text-sm text-slate-500">Estado</p><div class="mt-3"><x-insignia :estado="$vehiculo->activo ? 'activo' : 'inactivo'" /></div>
    </div>
</div>

@if ($vehiculo->foto)
    <x-tarjeta titulo="Imagen inferida" subtitulo="Placa, máscara del vehículo y ruedas detectadas por la IA. Las personas aparecen cubiertas." class="mb-6">
        <a href="{{ $vehiculo->fotoUrl() }}" target="_blank" rel="noopener">
            <img src="{{ $vehiculo->fotoUrl() }}" alt="Imagen inferida del vehículo {{ $vehiculo->placa }}" class="mx-auto max-h-[32rem] rounded-xl ring-1 ring-slate-200">
        </a>
    </x-tarjeta>
@endif

<x-tarjeta titulo="Historial de pasos" :relleno="false">
    <div class="overflow-x-auto">
        <table class="tabla">
            <thead><tr><th>Fecha</th><th>Evidencia</th><th>Caseta</th><th>Detectado</th><th class="text-right">Monto</th><th>Estado</th></tr></thead>
            <tbody>
                @forelse ($pasos as $paso)
                    <tr>
                        <td class="whitespace-nowrap text-slate-500 tabular-nums"><a class="enlace" href="{{ route('pasos.show', $paso) }}">{{ $paso->created_at->format('d/m/Y H:i') }}</a></td>
                        <td>@if ($paso->imagen_url)<img src="{{ $paso->imagen_url }}" alt="Evidencia" class="h-11 w-16 rounded-md object-cover ring-1 ring-slate-200" loading="lazy">@endif</td>
                        <td>{{ $paso->caseta?->nombre }}</td>
                        <td>{{ Etiquetas::categoria($paso->categoria_detectada) }} · {{ $paso->ejes_detectados ?? '—' }} ejes</td>
                        <td class="text-right font-medium tabular-nums">{{ Etiquetas::bs($paso->monto) }}</td>
                        <td><x-insignia :estado="$paso->estado" /></td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="py-10 text-center text-slate-500">Este vehículo todavía no pasó por el peaje.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</x-tarjeta>
<div class="mt-4">{{ $pasos->links() }}</div>
@endsection
