<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Caseta;
use App\Models\Paso;
use App\Support\Placa;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

/**
 * Historial de pasos con imagen de evidencia.
 * Los pasos solo se crean con la inferencia (caseta web o app). El administrador puede anularlos.
 */
class PasoController extends Controller
{
    public function index(Request $request): View
    {
        $placa = Placa::normalizar($request->string('placa')->toString());

        $pasos = Paso::with(['caseta', 'vehiculo', 'usuario'])
            ->when($request->filled('estado'), fn ($q) => $q->where('estado', $request->string('estado')))
            ->when($request->filled('caseta_id'), fn ($q) => $q->where('caseta_id', $request->integer('caseta_id')))
            ->when($placa, fn ($q) => $q->where('placa_normalizada', 'like', "%{$placa}%"))
            ->when($request->filled('fecha'), fn ($q) => $q->whereDate('created_at', $request->date('fecha')))
            ->latest('created_at')
            ->paginate(20)
            ->withQueryString();

        return view('pasos.index', ['pasos' => $pasos, 'casetas' => Caseta::orderBy('nombre')->get()]);
    }

    public function show(Paso $paso): View
    {
        return view('pasos.show', ['paso' => $paso->load(['caseta', 'vehiculo', 'usuario', 'alertas'])]);
    }

    /**
     * Anula un paso: si se cobró, devuelve el monto al saldo del vehículo.
     */
    public function destroy(Paso $paso): RedirectResponse
    {
        DB::transaction(function () use ($paso) {
            if ($paso->estado === Paso::COBRADO && $paso->vehiculo) {
                $paso->vehiculo->increment('saldo', $paso->monto);
            }
            $paso->delete();   // las alertas se borran en cascada
        });

        $this->borrarEvidencia($paso->imagen_url);

        return redirect()->route('pasos.index')->with('ok', 'Paso anulado.'.($paso->estado === Paso::COBRADO ? ' Se devolvió el monto al saldo.' : ''));
    }

    private function borrarEvidencia(?string $url): void
    {
        $prefijo = rtrim(Storage::disk('public')->url(''), '/').'/';

        if ($url && str_starts_with($url, $prefijo)) {
            Storage::disk('public')->delete(substr($url, strlen($prefijo)));
        }
    }
}
