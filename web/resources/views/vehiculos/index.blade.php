@extends('layouts.app')

@section('titulo', 'Vehículos')

@php
    use App\Support\Etiquetas;
    $admin = auth()->user()->esAdministrador();
@endphp

@section('contenido')
<x-encabezado titulo="Vehículos" subtitulo="Vehículos registrados con su saldo. Una placa que no esté aquí genera una alerta al pasar.">
    @if ($admin)
        <x-boton :href="route('vehiculos.create')" icono="mas">Nuevo vehículo</x-boton>
    @endif
</x-encabezado>

<x-tarjeta class="mb-6">
    <form method="GET" action="{{ route('vehiculos.index') }}" class="grid gap-3 sm:grid-cols-[1fr_12rem_auto]">
        <input name="buscar" value="{{ request('buscar') }}" placeholder="Buscar por placa o propietario" class="control">
        <select name="categoria" class="control">
            <option value="">Todas las categorías</option>
            @foreach ($categorias as $categoria)
                <option value="{{ $categoria }}" @selected(request('categoria') === $categoria)>{{ Etiquetas::categoria($categoria) }}</option>
            @endforeach
        </select>
        <x-boton variante="oscuro" icono="buscar">Buscar</x-boton>
    </form>
</x-tarjeta>

<x-tarjeta :relleno="false">
    <div class="overflow-x-auto">
        <table class="tabla">
            <thead>
                <tr><th>Placa</th><th>Propietario</th><th>Categoría</th><th>Ejes</th><th class="text-right">Saldo</th><th>Pasos</th><th>Estado</th>@if ($admin)<th></th>@endif</tr>
            </thead>
            <tbody>
                @forelse ($vehiculos as $vehiculo)
                    <tr>
                        <td><a href="{{ route('vehiculos.show', $vehiculo) }}" class="placa hover:border-sky-400">{{ $vehiculo->placa }}</a></td>
                        <td class="font-medium text-slate-900">{{ $vehiculo->propietario ?: '—' }}</td>
                        <td>{{ Etiquetas::categoria($vehiculo->categoria) }}</td>
                        <td class="tabular-nums">{{ $vehiculo->ejes }}</td>
                        <td @class(['text-right font-medium tabular-nums', 'text-amber-700' => (float) $vehiculo->saldo < 10])>{{ Etiquetas::bs($vehiculo->saldo) }}</td>
                        <td class="tabular-nums text-slate-500">{{ $vehiculo->pasos_count }}</td>
                        <td><x-insignia :estado="$vehiculo->activo ? 'activo' : 'inactivo'" /></td>
                        @if ($admin)
                            <td class="text-right whitespace-nowrap">
                                <x-boton variante="fantasma" tamano="sm" icono="lapiz" :href="route('vehiculos.edit', $vehiculo)">Editar</x-boton>
                                <form method="POST" action="{{ route('vehiculos.destroy', $vehiculo) }}" class="inline" onsubmit="return confirm('¿Eliminar el vehículo {{ $vehiculo->placa }}?')">
                                    @csrf @method('DELETE')
                                    <x-boton variante="peligro-fantasma" tamano="sm" icono="basura">Eliminar</x-boton>
                                </form>
                            </td>
                        @endif
                    </tr>
                @empty
                    <tr><td colspan="8" class="py-10 text-center text-slate-500">No hay vehículos.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</x-tarjeta>

<div class="mt-4">{{ $vehiculos->links() }}</div>
@endsection
