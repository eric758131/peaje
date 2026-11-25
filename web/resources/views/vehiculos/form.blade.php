@extends('layouts.app')

@section('titulo', $vehiculo->exists ? 'Editar vehículo' : 'Nuevo vehículo')

@php use App\Support\Etiquetas; @endphp

@section('contenido')
<x-encabezado :titulo="$vehiculo->exists ? 'Editar vehículo '.$vehiculo->placa : 'Nuevo vehículo'"
              subtitulo="Puedes llenar placa, categoría y ejes con una foto o un video: la inferencia los detecta." />

<form method="POST" action="{{ $vehiculo->exists ? route('vehiculos.update', $vehiculo) : route('vehiculos.store') }}"
      class="grid grid-cols-1 gap-6 xl:grid-cols-3">
    @csrf
    @if ($vehiculo->exists) @method('PUT') @endif

    <x-tarjeta titulo="Datos del vehículo" class="xl:col-span-2">
        <div class="grid gap-5 sm:grid-cols-2">
            <x-campo etiqueta="Placa" nombre="placa" ayuda="Se guarda en mayúsculas, sin guiones ni espacios.">
                <input id="placa" name="placa" value="{{ old('placa', $vehiculo->placa) }}" required maxlength="10" class="control font-mono uppercase">
            </x-campo>
            <x-campo etiqueta="Propietario" nombre="propietario">
                <input id="propietario" name="propietario" value="{{ old('propietario', $vehiculo->propietario) }}" maxlength="255" class="control">
            </x-campo>
            <x-campo etiqueta="Categoría" nombre="categoria">
                <select id="categoria" name="categoria" required class="control">
                    @foreach ($categorias as $categoria)
                        <option value="{{ $categoria }}" @selected(old('categoria', $vehiculo->categoria) === $categoria)>{{ Etiquetas::categoria($categoria) }}</option>
                    @endforeach
                </select>
            </x-campo>
            <x-campo etiqueta="Ejes" nombre="ejes">
                <input id="ejes" type="number" name="ejes" min="1" max="9" value="{{ old('ejes', $vehiculo->ejes) }}" required class="control">
            </x-campo>
            <x-campo etiqueta="Saldo (Bs)" nombre="saldo">
                <input id="saldo" type="number" step="0.01" min="0" name="saldo" value="{{ old('saldo', $vehiculo->saldo ?? 0) }}" required class="control">
            </x-campo>
            <div class="flex items-end pb-2">
                <x-interruptor nombre="activo" etiqueta="Vehículo activo" ayuda="Uno inactivo se trata como no registrado." :marcado="old('activo', $vehiculo->activo)" />
            </div>
        </div>

        <div class="mt-6 flex justify-end gap-3 border-t border-slate-100 pt-5">
            <x-boton variante="secundario" :href="route('vehiculos.index')">Cancelar</x-boton>
            <x-boton icono="ok">Guardar</x-boton>
        </div>
    </x-tarjeta>

    <x-tarjeta titulo="Autocompletar con IA" subtitulo="Foto o video del vehículo. La imagen inferida se guarda con el vehículo.">
        <div id="autocompletar" data-url="{{ route('inferencia.probar') }}">
            <input id="archivo-autocompletar" type="file" accept="image/*,video/*" class="control-archivo">
            <p id="estado-autocompletar" class="mt-3 text-sm text-slate-500">
                {{ $vehiculo->foto ? 'Sube otra foto para reemplazar la imagen guardada.' : 'La placa, la categoría y los ejes se llenan solos.' }}
            </p>
            {{-- Imagen inferida: la guardada, o la nueva al autocompletar (se envía en foto_inferida). --}}
            <input type="hidden" name="foto_inferida" id="foto-inferida" value="">
            <img id="vista-autocompletar" alt="Imagen inferida del vehículo" src="{{ $vehiculo->fotoUrl() }}"
                 @class(['mt-4 w-full rounded-xl ring-1 ring-slate-200', 'hidden' => ! $vehiculo->foto])>
            @error('foto_inferida')<p class="mt-2 text-xs font-medium text-rose-600">{{ $message }}</p>@enderror
        </div>
    </x-tarjeta>
</form>
@endsection
