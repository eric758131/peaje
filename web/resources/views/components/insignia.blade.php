@props(['estado', 'texto' => null])

<span {{ $attributes->merge(['class' => 'inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium ring-1 ring-inset '.\App\Support\Etiquetas::colorEstado($estado)]) }}>
    {{ $texto ?? \App\Support\Etiquetas::estado($estado) }}
</span>
