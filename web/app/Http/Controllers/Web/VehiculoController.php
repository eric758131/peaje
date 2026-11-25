<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Vehiculo;
use App\Support\Placa;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * CRUD de vehículos. Ver todos los vehículos: cualquier autorizado.
 * Crear, editar y borrar: solo administradores (ver rutas).
 */
class VehiculoController extends Controller
{
    public function index(Request $request): View
    {
        $vehiculos = Vehiculo::query()
            ->when($request->string('buscar')->trim()->isNotEmpty(), function ($q) use ($request) {
                $texto = Placa::normalizar($request->string('buscar')->toString()) ?? '';
                $q->where(fn ($q) => $q->where('placa', 'like', "%{$texto}%")
                    ->orWhere('propietario', 'like', '%'.$request->string('buscar')->trim().'%'));
            })
            ->when($request->filled('categoria'), fn ($q) => $q->where('categoria', $request->string('categoria')))
            ->withCount('pasos')
            ->orderBy('placa')
            ->paginate(20)
            ->withQueryString();

        return view('vehiculos.index', ['vehiculos' => $vehiculos, 'categorias' => Vehiculo::CATEGORIAS]);
    }

    public function show(Vehiculo $vehiculo): View
    {
        return view('vehiculos.show', [
            'vehiculo' => $vehiculo,
            'pasos' => $vehiculo->pasos()->with('caseta')->latest('created_at')->paginate(15),
        ]);
    }

    public function create(): View
    {
        return view('vehiculos.form', ['vehiculo' => new Vehiculo(['categoria' => 'auto', 'ejes' => 2, 'saldo' => 0, 'activo' => true]), 'categorias' => Vehiculo::CATEGORIAS]);
    }

    public function store(Request $request): RedirectResponse
    {
        $datos = $this->validado($request);
        $vehiculo = Vehiculo::create($datos);
        $this->guardarFotoInferida($request, $vehiculo);

        return redirect()->route('vehiculos.show', $vehiculo)->with('ok', 'Vehículo registrado.');
    }

    public function edit(Vehiculo $vehiculo): View
    {
        return view('vehiculos.form', ['vehiculo' => $vehiculo, 'categorias' => Vehiculo::CATEGORIAS]);
    }

    public function update(Request $request, Vehiculo $vehiculo): RedirectResponse
    {
        $vehiculo->update($this->validado($request, $vehiculo));
        $this->guardarFotoInferida($request, $vehiculo);

        return redirect()->route('vehiculos.show', $vehiculo)->with('ok', 'Vehículo actualizado.');
    }

    public function destroy(Vehiculo $vehiculo): RedirectResponse
    {
        if ($vehiculo->foto) {
            Storage::disk('public')->delete($vehiculo->foto);
        }
        $vehiculo->delete();

        return redirect()->route('vehiculos.index')->with('ok', 'Vehículo eliminado.');
    }

    private function validado(Request $request, ?Vehiculo $actual = null): array
    {
        $request->merge(['placa' => Placa::normalizar($request->input('placa'))]);

        $datos = $request->validate([
            'placa' => ['required', 'string', 'max:10', Rule::unique('vehiculos', 'placa')->ignore($actual)],
            'propietario' => ['nullable', 'string', 'max:255'],
            'categoria' => ['required', Rule::in(Vehiculo::CATEGORIAS)],
            'ejes' => ['required', 'integer', 'min:1', 'max:9'],
            'saldo' => ['required', 'numeric', 'min:0'],
        ], [
            'placa.unique' => 'Ya existe un vehículo con esa placa.',
        ]);

        // Un checkbox sin marcar no llega en la petición: se interpreta como false.
        $datos['activo'] = $request->boolean('activo');

        return $datos;
    }

    /**
     * La imagen inferida que dejó "Autocompletar con IA" (data URL JPEG) se guarda con el vehículo.
     */
    private function guardarFotoInferida(Request $request, Vehiculo $vehiculo): void
    {
        $request->validate([
            'foto_inferida' => ['nullable', 'string', 'max:6000000', 'starts_with:data:image/jpeg;base64,'],
        ], ['foto_inferida.starts_with' => 'La imagen inferida no es válida.']);

        $dataUrl = (string) $request->input('foto_inferida');
        if ($dataUrl === '') {
            return;
        }

        $jpeg = base64_decode(substr($dataUrl, strlen('data:image/jpeg;base64,')), true);
        if ($jpeg !== false && str_starts_with($jpeg, "\xFF\xD8")) {
            $vehiculo->guardarFoto($jpeg);
        }
    }
}
