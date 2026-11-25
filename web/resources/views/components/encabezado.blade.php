@props(['titulo', 'subtitulo' => null])

<div class="mb-6 flex flex-wrap items-end justify-between gap-4">
    <div>
        <h1 class="text-2xl font-semibold tracking-tight text-slate-900">{{ $titulo }}</h1>
        @if ($subtitulo)<p class="mt-1 text-sm text-slate-500">{{ $subtitulo }}</p>@endif
    </div>
    @if (trim($slot) !== '')
        <div class="flex flex-wrap items-center gap-2">{{ $slot }}</div>
    @endif
</div>
