<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Alerta;
use App\Models\Paso;
use App\Models\Vehiculo;
use App\Services\Peaje\Resumen;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

/**
 * Panel en tiempo real: pasos, recaudación y alertas. Se actualiza con Pusher.
 */
class PanelController extends Controller
{
    public function __invoke(Resumen $resumen): View
    {
        $porCategoria = Paso::query()
            ->whereDate('created_at', Carbon::today())
            ->selectRaw('categoria_detectada as categoria, count(*) as total')
            ->groupBy('categoria_detectada')
            ->pluck('total', 'categoria');

        return view('panel', [
            'resumen' => $resumen->delDia(),
            'pasos' => Paso::with(['caseta', 'vehiculo'])->latest('created_at')->limit(12)->get(),
            'alertas' => Alerta::with('paso.caseta')->where('atendida', false)->latest('created_at')->limit(8)->get(),
            'porCategoria' => collect(Vehiculo::CATEGORIAS)->mapWithKeys(fn ($c) => [$c => (int) ($porCategoria[$c] ?? 0)]),
            'vehiculosRegistrados' => Vehiculo::where('activo', true)->count(),
        ]);
    }
}
