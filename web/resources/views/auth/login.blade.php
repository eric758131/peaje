@extends('layouts.app')

@section('titulo', 'Iniciar sesión')

@section('contenido')
<div class="grid min-h-screen lg:grid-cols-2">
    <div class="relative hidden overflow-hidden bg-slate-950 lg:block">
        <div class="absolute inset-0 bg-[radial-gradient(circle_at_30%_20%,rgba(56,189,248,0.25),transparent_50%),radial-gradient(circle_at_80%_80%,rgba(99,102,241,0.25),transparent_50%)]"></div>
        <div class="relative flex h-full flex-col justify-between p-12 text-white">
            <div class="flex items-center gap-3">
                <span class="grid size-10 place-items-center rounded-xl bg-gradient-to-br from-sky-400 to-indigo-500"><x-icono nombre="caseta" /></span>
                <span class="text-lg font-semibold">Control de peaje</span>
            </div>
            <div>
                <h2 class="text-3xl font-semibold tracking-tight">Placa, categoría, ejes y pasajeros<br>en cada paso.</h2>
                <p class="mt-4 max-w-md text-slate-400">La inferencia lee cada foto o frame de video y el panel se actualiza en tiempo real.</p>
                <ul class="mt-8 space-y-3 text-sm text-slate-300">
                    <li class="flex items-center gap-2"><x-icono nombre="ok" clase="size-4 text-sky-400" /> M1 · Detección de placa y OCR</li>
                    <li class="flex items-center gap-2"><x-icono nombre="ok" clase="size-4 text-sky-400" /> M2 · Segmentación del vehículo y pasajeros</li>
                    <li class="flex items-center gap-2"><x-icono nombre="ok" clase="size-4 text-sky-400" /> M3 · Llantas y número de ejes</li>
                </ul>
            </div>
            <p class="text-xs text-slate-500">IAM-811 · Machine Learning y Aprendizaje Profundo</p>
        </div>
    </div>

    <div class="flex items-center justify-center px-6 py-12">
        <div class="w-full max-w-sm">
            <h1 class="text-2xl font-semibold tracking-tight text-slate-900">Iniciar sesión</h1>
            <p class="mt-2 text-sm text-slate-500">Solo entran las cuentas autorizadas por el administrador.</p>

            @if (session('error'))
                <div class="mt-6 flex items-start gap-2 rounded-xl bg-rose-50 px-4 py-3 text-sm text-rose-700 ring-1 ring-rose-200">
                    <x-icono nombre="alerta" clase="mt-0.5 size-4 shrink-0" /><span>{{ session('error') }}</span>
                </div>
            @endif

            {{-- El botón se muestra siempre: si Google aún no está configurado, al pulsarlo se explica qué falta. --}}
            <a href="{{ route('login.google') }}"
                   class="mt-8 flex w-full items-center justify-center gap-3 rounded-xl bg-white px-4 py-3 text-sm font-semibold text-slate-800 shadow-sm ring-1 ring-slate-300 transition hover:bg-slate-50">
                    <svg class="size-5" viewBox="0 0 48 48" aria-hidden="true"><path fill="#FFC107" d="M43.6 20.5H42V20H24v8h11.3C33.7 32.7 29.2 36 24 36c-6.6 0-12-5.4-12-12s5.4-12 12-12c3.1 0 5.8 1.2 7.9 3.1l5.7-5.7C34 6.1 29.3 4 24 4 12.9 4 4 12.9 4 24s8.9 20 20 20 20-8.9 20-20c0-1.3-.1-2.4-.4-3.5z"/><path fill="#FF3D00" d="m6.3 14.7 6.6 4.8C14.7 15.1 19 12 24 12c3.1 0 5.8 1.2 7.9 3.1l5.7-5.7C34 6.1 29.3 4 24 4 16.3 4 9.7 8.3 6.3 14.7z"/><path fill="#4CAF50" d="M24 44c5.2 0 9.9-2 13.4-5.2l-6.2-5.2C29.2 35.1 26.7 36 24 36c-5.2 0-9.6-3.3-11.3-8l-6.5 5C9.5 39.6 16.2 44 24 44z"/><path fill="#1976D2" d="M43.6 20.5H42V20H24v8h11.3c-.8 2.2-2.2 4.2-4.1 5.6l6.2 5.2C37 39.2 44 34 44 24c0-1.3-.1-2.4-.4-3.5z"/></svg>
                    Continuar con Google
            </a>

            @if ($contrasena)
            <div class="my-6 flex items-center gap-3 text-xs text-slate-400"><span class="h-px flex-1 bg-slate-200"></span>o con tu correo<span class="h-px flex-1 bg-slate-200"></span></div>
            <form method="POST" action="{{ route('login.autenticar') }}" class="space-y-5">
                @csrf
                <x-campo etiqueta="Correo" nombre="email">
                    <input id="email" type="email" name="email" value="{{ old('email') }}" required autofocus autocomplete="username" class="control" placeholder="nombre@correo.com">
                </x-campo>
                <x-campo etiqueta="Contraseña" nombre="password">
                    <input id="password" type="password" name="password" required autocomplete="current-password" class="control">
                </x-campo>
                <label class="flex items-center gap-2 text-sm text-slate-600">
                    <input type="checkbox" name="recordar" value="1" class="rounded border-slate-300">
                    Mantener la sesión iniciada
                </label>
                <button type="submit"
                        class="flex w-full items-center justify-center rounded-xl bg-slate-900 px-4 py-3 text-sm font-semibold text-white shadow-sm transition hover:bg-slate-800">
                    Iniciar sesión
                </button>
            </form>
            @endif
        </div>
    </div>
</div>
@endsection
