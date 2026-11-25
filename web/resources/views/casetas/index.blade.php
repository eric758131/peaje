@extends('layouts.app')

@section('titulo', 'Casetas')

@php use App\Support\Etiquetas; @endphp

@section('contenido')
<x-encabezado titulo="Casetas" subtitulo="Puntos de cobro. Solo las casetas activas aparecen en la app y en Registrar paso.">
    <x-boton :href="route('casetas.create')" icono="mas">Nueva caseta</x-boton>
</x-encabezado>

<div class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-3">
    @forelse ($casetas as $caseta)
        <div class="flex flex-col rounded-2xl bg-white p-5 shadow-sm ring-1 ring-slate-200/80">
            <div class="flex items-start justify-between gap-3">
                <div class="flex items-center gap-3">
                    <span class="grid size-10 place-items-center rounded-xl bg-slate-100 text-slate-600"><x-icono nombre="caseta" /></span>
                    <div>
                        <p class="font-semibold text-slate-900">{{ $caseta->nombre }}</p>
                        <p class="text-sm text-slate-500">{{ $caseta->ubicacion ?: 'Sin ubicación' }}</p>
                    </div>
                </div>
                <x-insignia :estado="$caseta->activa ? 'activo' : 'inactivo'" :texto="$caseta->activa ? 'Activa' : 'Inactiva'" />
            </div>
            <dl class="mt-5 grid grid-cols-3 gap-2 text-center">
                <div class="rounded-xl bg-slate-50 py-2"><dd class="font-semibold tabular-nums">{{ $caseta->pasos_hoy }}</dd><dt class="text-[11px] text-slate-500">Pasos hoy</dt></div>
                <div class="rounded-xl bg-slate-50 py-2"><dd class="font-semibold tabular-nums">{{ Etiquetas::bs($caseta->recaudado_hoy ?? 0) }}</dd><dt class="text-[11px] text-slate-500">Hoy</dt></div>
                <div class="rounded-xl bg-slate-50 py-2"><dd class="font-semibold tabular-nums">{{ $caseta->pasos_count }}</dd><dt class="text-[11px] text-slate-500">Total</dt></div>
            </dl>
            <div class="mt-4 flex justify-end gap-1 border-t border-slate-100 pt-3">
                <x-boton variante="fantasma" tamano="sm" icono="lapiz" :href="route('casetas.edit', $caseta)">Editar</x-boton>
                <form method="POST" action="{{ route('casetas.destroy', $caseta) }}" onsubmit="return confirm('¿Eliminar la caseta {{ $caseta->nombre }}?')">
                    @csrf @method('DELETE')
                    <x-boton variante="peligro-fantasma" tamano="sm" icono="basura">Eliminar</x-boton>
                </form>
            </div>
        </div>
    @empty
        <x-tarjeta class="md:col-span-2 xl:col-span-3"><p class="text-center text-sm text-slate-500">No hay casetas. Crea la primera.</p></x-tarjeta>
    @endforelse
</div>
@endsection
