@extends('layouts.app')

@section('titulo', 'Tarifas')

@php use App\Support\Etiquetas; @endphp

@section('contenido')
<x-encabezado titulo="Tarifas" subtitulo="Monto por categoría y número de ejes. Si no hay tarifa exacta, se usa la más cercana de la misma categoría.">
    <x-boton :href="route('tarifas.create')" icono="mas">Nueva tarifa</x-boton>
</x-encabezado>

<div class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-3">
    @foreach (Etiquetas::CATEGORIAS as $categoria => $nombre)
        <x-tarjeta :titulo="$nombre" :relleno="false">
            <x-slot:acciones>
                <x-boton variante="fantasma" tamano="sm" icono="mas" :href="route('tarifas.create', ['categoria' => $categoria])">Agregar</x-boton>
            </x-slot:acciones>
            <ul class="divide-y divide-slate-100">
                @forelse ($tarifas[$categoria] ?? [] as $tarifa)
                    <li class="flex items-center justify-between gap-3 px-5 py-3">
                        <span class="text-sm text-slate-600">{{ $tarifa->ejes }} ejes</span>
                        <span class="ml-auto font-semibold tabular-nums">{{ Etiquetas::bs($tarifa->monto) }}</span>
                        <a href="{{ route('tarifas.edit', $tarifa) }}" class="rounded-lg p-1.5 text-slate-400 hover:bg-slate-100 hover:text-slate-700" title="Editar"><x-icono nombre="lapiz" clase="size-4" /></a>
                        <form method="POST" action="{{ route('tarifas.destroy', $tarifa) }}" onsubmit="return confirm('¿Eliminar esta tarifa?')">
                            @csrf @method('DELETE')
                            <button class="rounded-lg p-1.5 text-slate-400 hover:bg-rose-50 hover:text-rose-600" title="Eliminar"><x-icono nombre="basura" clase="size-4" /></button>
                        </form>
                    </li>
                @empty
                    <li class="px-5 py-4 text-sm text-slate-500">Sin tarifas: el cobro será Bs 0.</li>
                @endforelse
            </ul>
        </x-tarjeta>
    @endforeach
</div>
@endsection
