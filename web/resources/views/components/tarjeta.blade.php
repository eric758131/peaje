@props(['titulo' => null, 'subtitulo' => null, 'relleno' => true])

<section {{ $attributes->merge(['class' => 'rounded-2xl bg-white shadow-sm ring-1 ring-slate-200/80']) }}>
    @if ($titulo || isset($acciones))
        <header class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-100 px-5 py-4">
            <div>
                <h2 class="text-sm font-semibold text-slate-900">{{ $titulo }}</h2>
                @if ($subtitulo)<p class="mt-0.5 text-xs text-slate-500">{{ $subtitulo }}</p>@endif
            </div>
            @isset($acciones)<div class="flex items-center gap-2">{{ $acciones }}</div>@endisset
        </header>
    @endif

    <div @class(['p-5' => $relleno])>
        {{ $slot }}
    </div>
</section>
