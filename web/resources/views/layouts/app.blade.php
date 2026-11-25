<!DOCTYPE html>
<html lang="es" class="h-full bg-slate-50">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('titulo', 'Peaje') · Control de peaje</title>
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700|jetbrains-mono:500,600" rel="stylesheet">
    <script>window.PEAJE = @json(\App\Support\Etiquetas::paraJs());</script>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="h-full font-sans text-slate-800 antialiased">

@auth
@php
    $usuario = auth()->user();
    $pendientes = \App\Models\Alerta::where('atendida', false)->count();
    $menu = [
        ['Operación', [
            ['panel', 'Panel en vivo', 'panel', 'panel'],
            ['registro', 'Registrar paso', 'camara', 'registro'],
            ['chat', 'Consultar placa', 'chat', 'chat'],
        ]],
        ['Registros', [
            ['pasos.index', 'Pasos', 'lista', 'pasos.*'],
            ['alertas.index', 'Alertas', 'alerta', 'alertas.*'],
            ['vehiculos.index', 'Vehículos', 'auto', 'vehiculos.*'],
        ]],
    ];
    if ($usuario->esAdministrador()) {
        $menu[] = ['Administración', [
            ['casetas.index', 'Casetas', 'caseta', 'casetas.*'],
            ['tarifas.index', 'Tarifas', 'dinero', 'tarifas.*'],
            ['usuarios.index', 'Usuarios', 'usuarios', 'usuarios.*'],
        ]];
    }
    $menu[] = ['Sistema', [['estado', 'Estado de la IA', 'chip', 'estado']]];
@endphp

<div class="min-h-full">
    {{-- Barra lateral --}}
    <aside id="barra-lateral" class="fixed inset-y-0 left-0 z-40 flex w-64 -translate-x-full flex-col bg-slate-950 text-slate-300 transition-transform lg:translate-x-0">
        <div class="flex h-16 items-center gap-3 px-5">
            <span class="grid size-9 place-items-center rounded-xl bg-gradient-to-br from-sky-400 to-indigo-500 text-white shadow-lg shadow-sky-900/40">
                <x-icono nombre="caseta" clase="size-5" />
            </span>
            <div class="leading-tight">
                <p class="text-sm font-semibold text-white">Control de peaje</p>
                <p class="text-xs text-slate-500">Sistema inteligente</p>
            </div>
        </div>

        <nav class="flex-1 space-y-6 overflow-y-auto px-3 py-4">
            @foreach ($menu as [$grupo, $items])
                <div>
                    <p class="px-3 pb-2 text-[11px] font-semibold tracking-wider text-slate-500 uppercase">{{ $grupo }}</p>
                    <ul class="space-y-0.5">
                        @foreach ($items as [$ruta, $texto, $icono, $patron])
                            @php $activa = request()->routeIs($patron); @endphp
                            <li>
                                <a href="{{ route($ruta) }}" @class([
                                    'group flex items-center gap-3 rounded-lg px-3 py-2 text-sm font-medium transition',
                                    'bg-white/10 text-white' => $activa,
                                    'hover:bg-white/5 hover:text-white' => ! $activa,
                                ])>
                                    <x-icono :nombre="$icono" :clase="$activa ? 'size-5 text-sky-400' : 'size-5 text-slate-500 group-hover:text-slate-300'" />
                                    <span class="flex-1">{{ $texto }}</span>
                                    @if ($ruta === 'alertas.index')
                                        <span id="insignia-alertas" @class(['rounded-full bg-rose-500 px-2 py-0.5 text-[11px] font-semibold text-white', 'hidden' => $pendientes === 0])>{{ $pendientes }}</span>
                                    @endif
                                </a>
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endforeach
        </nav>

        <div class="border-t border-white/5 p-4">
            <div class="flex items-center gap-3">
                <span class="grid size-9 place-items-center rounded-full bg-slate-800 text-sm font-semibold text-white">
                    {{ mb_strtoupper(mb_substr($usuario->nombre, 0, 1)) }}
                </span>
                <div class="min-w-0 flex-1 leading-tight">
                    <p class="truncate text-sm font-medium text-white">{{ $usuario->nombre }}</p>
                    <p class="truncate text-xs text-slate-500">{{ ucfirst($usuario->rol) }}</p>
                </div>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button class="rounded-lg p-2 text-slate-500 hover:bg-white/5 hover:text-white" title="Salir">
                        <x-icono nombre="salir" />
                    </button>
                </form>
            </div>
        </div>
    </aside>
    <div id="fondo-menu" class="fixed inset-0 z-30 hidden bg-slate-950/50 lg:hidden"></div>

    <div class="lg:pl-64">
        {{-- Barra superior --}}
        <header class="sticky top-0 z-20 flex h-16 items-center gap-4 border-b border-slate-200 bg-white/80 px-4 backdrop-blur sm:px-6 lg:px-8">
            <button id="abrir-menu" type="button" class="-ml-1 rounded-lg p-2 text-slate-600 hover:bg-slate-100 lg:hidden" aria-label="Abrir menú">
                <x-icono nombre="menu" />
            </button>
            <p class="text-sm font-medium text-slate-500">@yield('titulo', 'Peaje')</p>
            <div class="ml-auto flex items-center gap-3">
                <span id="indicador-vivo" class="inline-flex items-center gap-1.5 rounded-full bg-slate-100 px-2.5 py-1 text-xs font-medium text-slate-600">
                    <span class="size-1.5 rounded-full bg-slate-400"></span> sin conexión en vivo
                </span>
            </div>
        </header>

        <main class="px-4 py-6 sm:px-6 lg:px-8">
            @if (session('ok'))
                <div class="mb-5 flex items-start gap-3 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">
                    <x-icono nombre="ok" clase="mt-0.5 size-4 shrink-0" />
                    <span>{{ session('ok') }}</span>
                </div>
            @endif
            @if (session('error'))
                <div class="mb-5 flex items-start gap-3 rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800">
                    <x-icono nombre="alerta" clase="mt-0.5 size-4 shrink-0" />
                    <span>{{ session('error') }}</span>
                </div>
            @endif

            @yield('contenido')
        </main>
    </div>
</div>
@unless (request()->routeIs('chat'))
    @include('partials.chat-flotante')
@endunless
@else
    <main>@yield('contenido')</main>
@endauth

@stack('scripts')
</body>
</html>
