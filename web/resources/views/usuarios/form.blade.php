@extends('layouts.app')

@section('titulo', $usuario->exists ? 'Editar cuenta' : 'Agregar cuenta')

@section('contenido')
<x-encabezado :titulo="$usuario->exists ? 'Editar '.$usuario->nombre : 'Agregar cuenta'"
              subtitulo="La persona entra a la web con este correo y esta contraseña." />

<form method="POST" action="{{ $usuario->exists ? route('usuarios.update', $usuario) : route('usuarios.store') }}" class="max-w-2xl">
    @csrf
    @if ($usuario->exists) @method('PUT') @endif

    <x-tarjeta>
        <div class="grid gap-5 sm:grid-cols-2">
            <x-campo etiqueta="Correo" nombre="email" class="sm:col-span-2">
                <input id="email" type="email" name="email" value="{{ old('email', $usuario->email) }}" required autocomplete="off" class="control" placeholder="nombre@correo.com">
            </x-campo>
            <x-campo etiqueta="Contraseña (opcional)" nombre="password" :ayuda="$usuario->exists ? 'Déjala vacía para no cambiarla. Mínimo 8 caracteres.' : 'Vacía = solo entra con Google. Mínimo 8 caracteres.'">
                <input id="password" type="password" name="password" minlength="8" autocomplete="new-password" class="control">
            </x-campo>
            <x-campo etiqueta="Repetir contraseña" nombre="password_confirmation">
                <input id="password_confirmation" type="password" name="password_confirmation" minlength="8" autocomplete="new-password" class="control">
            </x-campo>
            <x-campo etiqueta="Nombre" nombre="nombre">
                <input id="nombre" name="nombre" value="{{ old('nombre', $usuario->nombre) }}" required maxlength="255" class="control">
            </x-campo>
            <x-campo etiqueta="Rol" nombre="rol">
                <select id="rol" name="rol" class="control">
                    <option value="operador" @selected(old('rol', $usuario->rol) === 'operador')>Operador: registra pasos y consulta placas</option>
                    <option value="administrador" @selected(old('rol', $usuario->rol) === 'administrador')>Administrador: además gestiona vehículos, tarifas, casetas y cuentas</option>
                </select>
            </x-campo>
            <div class="sm:col-span-2">
                <x-interruptor nombre="activo" etiqueta="Cuenta activa" ayuda="Una cuenta desactivada no puede entrar, aunque tenga la sesión abierta." :marcado="old('activo', $usuario->activo)" />
            </div>
        </div>
        <div class="mt-6 flex justify-end gap-3 border-t border-slate-100 pt-5">
            <x-boton variante="secundario" :href="route('usuarios.index')">Cancelar</x-boton>
            <x-boton icono="ok">Guardar</x-boton>
        </div>
    </x-tarjeta>
</form>
@endsection
