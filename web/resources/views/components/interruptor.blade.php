@props(['nombre', 'etiqueta', 'marcado' => false, 'ayuda' => null])

<label class="flex cursor-pointer items-start gap-3">
    <input type="hidden" name="{{ $nombre }}" value="0">
    <input type="checkbox" name="{{ $nombre }}" value="1" @checked($marcado) class="peer sr-only">
    <span class="relative mt-0.5 inline-flex h-5 w-9 shrink-0 rounded-full bg-slate-300 transition peer-checked:bg-sky-600 peer-focus-visible:ring-2 peer-focus-visible:ring-sky-500 peer-focus-visible:ring-offset-2 after:absolute after:top-0.5 after:left-0.5 after:size-4 after:rounded-full after:bg-white after:shadow after:transition peer-checked:after:translate-x-4"></span>
    <span>
        <span class="block text-sm font-medium text-slate-700">{{ $etiqueta }}</span>
        @if ($ayuda)<span class="block text-xs text-slate-500">{{ $ayuda }}</span>@endif
    </span>
</label>
