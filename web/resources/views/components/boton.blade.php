@props(['variante' => 'primario', 'href' => null, 'icono' => null, 'tamano' => 'md'])

@php
    $base = 'inline-flex items-center justify-center gap-1.5 rounded-lg font-medium transition focus-visible:outline-2 focus-visible:outline-offset-2 disabled:cursor-not-allowed disabled:opacity-50';
    $tamanos = ['sm' => 'px-2.5 py-1.5 text-xs', 'md' => 'px-3.5 py-2 text-sm'];
    $variantes = [
        'primario' => 'bg-sky-600 text-white shadow-sm hover:bg-sky-500 focus-visible:outline-sky-600',
        'oscuro' => 'bg-slate-900 text-white shadow-sm hover:bg-slate-700 focus-visible:outline-slate-900',
        'secundario' => 'bg-white text-slate-700 shadow-xs ring-1 ring-slate-300 ring-inset hover:bg-slate-50',
        'peligro' => 'bg-rose-600 text-white shadow-sm hover:bg-rose-500 focus-visible:outline-rose-600',
        'fantasma' => 'text-slate-600 hover:bg-slate-100 hover:text-slate-900',
        'peligro-fantasma' => 'text-rose-600 hover:bg-rose-50',
    ];
    $clases = $base.' '.$tamanos[$tamano].' '.($variantes[$variante] ?? $variantes['primario']);
@endphp

@if ($href)
    <a href="{{ $href }}" {{ $attributes->merge(['class' => $clases]) }}>
        @if ($icono)<x-icono :nombre="$icono" clase="size-4" />@endif
        {{ $slot }}
    </a>
@else
    <button {{ $attributes->merge(['class' => $clases, 'type' => 'submit']) }}>
        @if ($icono)<x-icono :nombre="$icono" clase="size-4" />@endif
        {{ $slot }}
    </button>
@endif
