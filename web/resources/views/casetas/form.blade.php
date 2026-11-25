@extends('layouts.app')

@section('titulo', $caseta->exists ? 'Editar caseta' : 'Nueva caseta')

@section('contenido')
<x-encabezado :titulo="$caseta->exists ? 'Editar '.$caseta->nombre : 'Nueva caseta'" />

<form method="POST" action="{{ $caseta->exists ? route('casetas.update', $caseta) : route('casetas.store') }}" class="max-w-2xl">
    @csrf
    @if ($caseta->exists) @method('PUT') @endif

    <x-tarjeta>
        <div class="space-y-5">
            <x-campo etiqueta="Nombre" nombre="nombre">
                <input id="nombre" name="nombre" value="{{ old('nombre', $caseta->nombre) }}" required maxlength="100" class="control" placeholder="Caseta Norte">
            </x-campo>
            <x-campo etiqueta="Ubicación" nombre="ubicacion">
                <input id="ubicacion" name="ubicacion" value="{{ old('ubicacion', $caseta->ubicacion) }}" maxlength="255" class="control" placeholder="Km 12, carretera a Oruro">
            </x-campo>
            <x-interruptor nombre="activa" etiqueta="Caseta activa" ayuda="Las inactivas no reciben pasos." :marcado="old('activa', $caseta->activa)" />
        </div>
        <div class="mt-6 flex justify-end gap-3 border-t border-slate-100 pt-5">
            <x-boton variante="secundario" :href="route('casetas.index')">Cancelar</x-boton>
            <x-boton icono="ok">Guardar</x-boton>
        </div>
    </x-tarjeta>
</form>
@endsection
