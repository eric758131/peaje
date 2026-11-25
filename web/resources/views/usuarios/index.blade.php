@extends('layouts.app')

@section('titulo', 'Usuarios')

@section('contenido')
<x-encabezado titulo="Usuarios" subtitulo="Solo las cuentas activas pueden entrar a la web con su correo y contraseña.">
    <x-boton :href="route('usuarios.create')" icono="mas">Agregar cuenta</x-boton>
</x-encabezado>

<x-tarjeta :relleno="false">
    <div class="overflow-x-auto">
        <table class="tabla">
            <thead><tr><th>Cuenta</th><th>Rol</th><th>Pasos registrados</th><th>Estado</th><th></th></tr></thead>
            <tbody>
                @foreach ($usuarios as $usuario)
                    <tr>
                        <td>
                            <div class="flex items-center gap-3">
                                <span class="grid size-9 shrink-0 place-items-center rounded-full bg-slate-100 text-sm font-semibold text-slate-600">{{ mb_strtoupper(mb_substr($usuario->nombre, 0, 1)) }}</span>
                                <div>
                                    <p class="font-medium text-slate-900">{{ $usuario->nombre }} @if ($usuario->is(auth()->user()))<span class="text-xs text-slate-400">(tú)</span>@endif</p>
                                    <p class="text-xs text-slate-500">{{ $usuario->email }}</p>
                                </div>
                            </div>
                        </td>
                        <td><x-insignia :estado="$usuario->rol" /></td>
                        <td class="tabular-nums text-slate-500">{{ $usuario->pasos_count }}</td>
                        <td><x-insignia :estado="$usuario->activo ? 'activo' : 'inactivo'" /></td>
                        <td class="text-right whitespace-nowrap">
                            <x-boton variante="fantasma" tamano="sm" icono="lapiz" :href="route('usuarios.edit', $usuario)">Editar</x-boton>
                            @unless ($usuario->is(auth()->user()))
                                <form method="POST" action="{{ route('usuarios.destroy', $usuario) }}" class="inline" onsubmit="return confirm('¿Eliminar la cuenta {{ $usuario->email }}?')">
                                    @csrf @method('DELETE')
                                    <x-boton variante="peligro-fantasma" tamano="sm" icono="basura">Quitar</x-boton>
                                </form>
                            @endunless
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</x-tarjeta>
@endsection
