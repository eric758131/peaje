@extends('layouts.app')

@section('titulo', 'Acceso denegado')

@section('contenido')
<div class="flex min-h-screen items-center justify-center px-6">
    <div class="w-full max-w-md rounded-2xl bg-white p-8 text-center shadow-sm ring-1 ring-slate-200">
        <span class="mx-auto grid size-12 place-items-center rounded-2xl bg-rose-50 text-rose-600"><x-icono nombre="alerta" clase="size-6" /></span>
        <h1 class="mt-4 text-xl font-semibold text-slate-900">Acceso denegado</h1>
        <p class="mt-2 text-sm text-slate-600">
            @if ($email)
                La cuenta <span class="font-medium text-slate-900">{{ $email }}</span> no está autorizada o está desactivada.
            @else
                Tu cuenta no está autorizada o está desactivada.
            @endif
            Pide al administrador que la agregue en Usuarios.
        </p>
        <a href="{{ route('login') }}" class="enlace mt-6 inline-block text-sm">Volver al inicio de sesión</a>
    </div>
</div>
@endsection
