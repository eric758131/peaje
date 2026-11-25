@props(['etiqueta', 'nombre', 'ayuda' => null])

<div {{ $attributes }}>
    <label for="{{ $nombre }}" class="mb-1.5 block text-sm font-medium text-slate-700">{{ $etiqueta }}</label>
    {{ $slot }}
    @if ($ayuda)<p class="mt-1 text-xs text-slate-500">{{ $ayuda }}</p>@endif
    @error($nombre)<p class="mt-1 text-xs font-medium text-rose-600">{{ $message }}</p>@enderror
</div>
