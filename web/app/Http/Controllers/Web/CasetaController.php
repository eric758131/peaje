<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Caseta;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * CRUD de casetas (solo administradores).
 */
class CasetaController extends Controller
{
    public function index(): View
    {
        $casetas = Caseta::query()
            ->withCount(['pasos', 'pasos as pasos_hoy' => fn ($q) => $q->whereDate('created_at', Carbon::today())])
            ->withSum(['pasos as recaudado_hoy' => fn ($q) => $q->whereDate('created_at', Carbon::today())->where('estado', 'cobrado')], 'monto')
            ->orderBy('nombre')
            ->get();

        return view('casetas.index', ['casetas' => $casetas]);
    }

    public function create(): View
    {
        return view('casetas.form', ['caseta' => new Caseta(['activa' => true])]);
    }

    public function store(Request $request): RedirectResponse
    {
        Caseta::create($this->validado($request));

        return redirect()->route('casetas.index')->with('ok', 'Caseta creada.');
    }

    public function edit(Caseta $caseta): View
    {
        return view('casetas.form', ['caseta' => $caseta]);
    }

    public function update(Request $request, Caseta $caseta): RedirectResponse
    {
        $caseta->update($this->validado($request, $caseta));

        return redirect()->route('casetas.index')->with('ok', 'Caseta actualizada.');
    }

    public function destroy(Caseta $caseta): RedirectResponse
    {
        if ($caseta->pasos()->exists()) {
            return back()->with('error', 'La caseta tiene pasos registrados. Desactívala en lugar de eliminarla.');
        }

        $caseta->delete();

        return redirect()->route('casetas.index')->with('ok', 'Caseta eliminada.');
    }

    /** @return array<string, mixed> */
    private function validado(Request $request, ?Caseta $actual = null): array
    {
        $datos = $request->validate([
            'nombre' => ['required', 'string', 'max:100', Rule::unique('casetas', 'nombre')->ignore($actual)],
            'ubicacion' => ['nullable', 'string', 'max:255'],
        ], ['nombre.unique' => 'Ya existe una caseta con ese nombre.']);

        $datos['activa'] = $request->boolean('activa');

        return $datos;
    }
}
