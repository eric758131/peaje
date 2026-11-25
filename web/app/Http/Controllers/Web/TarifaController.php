<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Tarifa;
use App\Models\Vehiculo;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * CRUD de tarifas por categoría y número de ejes (solo administradores).
 */
class TarifaController extends Controller
{
    public function index(): View
    {
        $orden = array_flip(Vehiculo::CATEGORIAS);

        $tarifas = Tarifa::all()
            ->sortBy(fn ($t) => [$orden[$t->categoria] ?? 99, $t->ejes])
            ->groupBy('categoria');

        return view('tarifas.index', ['tarifas' => $tarifas]);
    }

    public function create(Request $request): View
    {
        return view('tarifas.form', [
            'tarifa' => new Tarifa(['categoria' => $request->query('categoria', 'auto'), 'ejes' => 2]),
            'categorias' => Vehiculo::CATEGORIAS,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        Tarifa::create($this->validado($request));

        return redirect()->route('tarifas.index')->with('ok', 'Tarifa creada.');
    }

    public function edit(Tarifa $tarifa): View
    {
        return view('tarifas.form', ['tarifa' => $tarifa, 'categorias' => Vehiculo::CATEGORIAS]);
    }

    public function update(Request $request, Tarifa $tarifa): RedirectResponse
    {
        $tarifa->update($this->validado($request, $tarifa));

        return redirect()->route('tarifas.index')->with('ok', 'Tarifa actualizada.');
    }

    public function destroy(Tarifa $tarifa): RedirectResponse
    {
        $tarifa->delete();

        return redirect()->route('tarifas.index')->with('ok', 'Tarifa eliminada.');
    }

    /** @return array<string, mixed> */
    private function validado(Request $request, ?Tarifa $actual = null): array
    {
        return $request->validate([
            'categoria' => ['required', Rule::in(Vehiculo::CATEGORIAS)],
            'ejes' => ['required', 'integer', 'min:1', 'max:9',
                Rule::unique('tarifas', 'ejes')->where('categoria', $request->input('categoria'))->ignore($actual)],
            'monto' => ['required', 'numeric', 'min:0', 'max:99999'],
        ], ['ejes.unique' => 'Ya existe una tarifa para esa categoría y número de ejes.']);
    }
}
