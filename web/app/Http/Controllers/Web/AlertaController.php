<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Alerta;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AlertaController extends Controller
{
    public function index(Request $request): View
    {
        $vista = $request->query('ver', 'pendientes');

        $alertas = Alerta::with('paso.caseta')
            ->when($vista === 'pendientes', fn ($q) => $q->where('atendida', false))
            ->when($vista === 'atendidas', fn ($q) => $q->where('atendida', true))
            ->latest('created_at')
            ->paginate(20)
            ->withQueryString();

        return view('alertas.index', [
            'alertas' => $alertas,
            'vista' => $vista,
            'pendientes' => Alerta::where('atendida', false)->count(),
        ]);
    }

    public function atender(Alerta $alerta): RedirectResponse
    {
        $alerta->update(['atendida' => true]);

        return back()->with('ok', 'Alerta marcada como atendida.');
    }

    public function atenderTodas(): RedirectResponse
    {
        $cantidad = Alerta::where('atendida', false)->update(['atendida' => true]);

        return back()->with('ok', "{$cantidad} alerta(s) marcadas como atendidas.");
    }
}
