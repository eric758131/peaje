@extends('layouts.app')

@section('titulo', 'Alertas')

@php use App\Support\Etiquetas; @endphp

@section('contenido')
<x-encabezado titulo="Alertas" subtitulo="Se generan solas cuando la inferencia lee una placa no registrada o un vehículo sin saldo.">
    @if ($pendientes > 0)
        <form method="POST" action="{{ route('alertas.atender-todas') }}" onsubmit="return confirm('¿Marcar todas como atendidas?')">
            @csrf
            <x-boton variante="secundario" icono="ok">Atender todas ({{ $pendientes }})</x-boton>
        </form>
    @endif
</x-encabezado>

<div class="mb-4 inline-flex rounded-xl bg-slate-100 p-1 text-sm">
    @foreach (['pendientes' => 'Pendientes', 'atendidas' => 'Atendidas', 'todas' => 'Todas'] as $clave => $texto)
        <a href="{{ route('alertas.index', ['ver' => $clave]) }}" @class([
            'rounded-lg px-3 py-1.5 font-medium transition',
            'bg-white text-slate-900 shadow-sm' => $vista === $clave,
            'text-slate-600 hover:text-slate-900' => $vista !== $clave,
        ])>{{ $texto }}</a>
    @endforeach
</div>

<x-tarjeta :relleno="false">
    <div class="overflow-x-auto">
        <table class="tabla">
            <thead><tr><th>Fecha</th><th>Tipo</th><th>Placa</th><th>Caseta</th><th>Evidencia</th><th>Estado</th><th></th></tr></thead>
            <tbody>
                @forelse ($alertas as $alerta)
                    <tr>
                        <td class="whitespace-nowrap text-slate-500 tabular-nums">{{ $alerta->created_at->format('d/m/Y H:i:s') }}</td>
                        <td><x-insignia :estado="$alerta->tipo" /></td>
                        <td>
                            @if ($alerta->paso)
                                <a href="{{ route('pasos.show', $alerta->paso) }}" class="placa hover:border-sky-400">{{ $alerta->paso->placa_normalizada }}</a>
                            @endif
                        </td>
                        <td>{{ $alerta->paso?->caseta?->nombre }}</td>
                        <td>
                            @if ($alerta->paso?->imagen_url)
                                <a href="{{ route('pasos.show', $alerta->paso) }}"><img src="{{ $alerta->paso->imagen_url }}" alt="Evidencia" class="h-11 w-16 rounded-md object-cover ring-1 ring-slate-200" loading="lazy"></a>
                            @endif
                        </td>
                        <td>
                            @if ($alerta->atendida)
                                <x-insignia estado="activo" texto="Atendida" />
                            @else
                                <x-insignia estado="sin_saldo" texto="Pendiente" />
                            @endif
                        </td>
                        <td class="text-right">
                            @unless ($alerta->atendida)
                                <form method="POST" action="{{ route('alertas.atender', $alerta) }}">@csrf
                                    <x-boton variante="secundario" tamano="sm" icono="ok">Atender</x-boton>
                                </form>
                            @endunless
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="py-10 text-center text-slate-500">No hay alertas {{ $vista === 'todas' ? '' : $vista }}.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</x-tarjeta>

<div class="mt-4">{{ $alertas->links() }}</div>
@endsection
