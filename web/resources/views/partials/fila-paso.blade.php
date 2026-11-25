{{-- Fila de paso. Con $paso = null sirve de plantilla para el JavaScript (data-campo). --}}
@php use App\Support\Etiquetas; @endphp
<tr>
    <td class="whitespace-nowrap text-slate-500 tabular-nums" data-campo="hora">{{ $paso?->created_at->format('H:i:s') }}</td>
    <td>
        <a data-campo="evidencia-enlace" href="{{ $paso?->imagen_url ?? '#' }}" target="_blank" class="block">
            <img data-campo="evidencia" src="{{ $paso?->imagen_url }}" alt="Evidencia"
                 @class(['h-11 w-16 rounded-md bg-slate-100 object-cover ring-1 ring-slate-200', 'invisible' => ! $paso?->imagen_url])>
        </a>
    </td>
    <td>
        <a data-campo="placa-enlace" href="{{ $paso ? route('pasos.show', $paso) : '#' }}" class="placa hover:border-sky-400">{{ $paso?->placa_normalizada ?? '—' }}</a>
        <p class="mt-1 text-xs text-slate-500" data-campo="caseta">{{ $paso?->caseta?->nombre }}</p>
    </td>
    <td class="whitespace-nowrap">
        <span class="font-medium text-slate-800" data-campo="categoria">{{ Etiquetas::categoria($paso?->categoria_detectada) }}</span>
        <span class="block text-xs text-slate-500"><span data-campo="ejes">{{ $paso?->ejes_detectados ?? '—' }}</span> ejes<span data-campo="pasajeros-texto">{{ $paso?->pasajeros !== null ? ' · '.$paso->pasajeros.' pasajero(s)' : '' }}</span></span>
    </td>
    <td class="text-right font-medium whitespace-nowrap tabular-nums" data-campo="monto">{{ $paso ? Etiquetas::bs($paso->monto) : '' }}</td>
    <td><span data-campo="estado" class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium ring-1 ring-inset {{ Etiquetas::colorEstado($paso?->estado) }}">{{ Etiquetas::estado($paso?->estado) }}</span></td>
</tr>
