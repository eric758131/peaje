@extends('layouts.app')

@section('titulo', $tarifa->exists ? 'Editar tarifa' : 'Nueva tarifa')

@php use App\Support\Etiquetas; @endphp

@section('contenido')
<x-encabezado :titulo="$tarifa->exists ? 'Editar tarifa' : 'Nueva tarifa'" />

<form method="POST" action="{{ $tarifa->exists ? route('tarifas.update', $tarifa) : route('tarifas.store') }}" class="max-w-2xl">
    @csrf
    @if ($tarifa->exists) @method('PUT') @endif

    <x-tarjeta>
        <div class="grid gap-5 sm:grid-cols-3">
            <x-campo etiqueta="Categoría" nombre="categoria">
                <select id="categoria" name="categoria" class="control">
                    @foreach ($categorias as $categoria)
                        <option value="{{ $categoria }}" @selected(old('categoria', $tarifa->categoria) === $categoria)>{{ Etiquetas::categoria($categoria) }}</option>
                    @endforeach
                </select>
            </x-campo>
            <x-campo etiqueta="Ejes" nombre="ejes">
                <input id="ejes" type="number" name="ejes" min="1" max="9" value="{{ old('ejes', $tarifa->ejes) }}" required class="control">
            </x-campo>
            <x-campo etiqueta="Monto (Bs)" nombre="monto">
                <input id="monto" type="number" name="monto" step="0.01" min="0" value="{{ old('monto', $tarifa->monto) }}" required class="control">
            </x-campo>
        </div>
        <div class="mt-6 flex justify-end gap-3 border-t border-slate-100 pt-5">
            <x-boton variante="secundario" :href="route('tarifas.index')">Cancelar</x-boton>
            <x-boton icono="ok">Guardar</x-boton>
        </div>
    </x-tarjeta>
</form>
@endsection
