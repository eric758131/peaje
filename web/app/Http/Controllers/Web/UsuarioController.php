<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Usuario;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Cuentas de acceso a la web (solo administradores).
 * Solo las cuentas activas de esta tabla pueden entrar a la web.
 */
class UsuarioController extends Controller
{
    public function index(): View
    {
        return view('usuarios.index', [
            'usuarios' => Usuario::withCount('pasos')->orderByDesc('activo')->orderBy('nombre')->get(),
        ]);
    }

    public function create(): View
    {
        return view('usuarios.form', ['usuario' => new Usuario(['rol' => Usuario::OPERADOR, 'activo' => true])]);
    }

    public function store(Request $request): RedirectResponse
    {
        Usuario::create($this->validado($request));

        return redirect()->route('usuarios.index')->with('ok', 'Cuenta creada.');
    }

    public function edit(Usuario $usuario): View
    {
        return view('usuarios.form', ['usuario' => $usuario]);
    }

    public function update(Request $request, Usuario $usuario): RedirectResponse
    {
        $datos = $this->validado($request, $usuario);

        if ($usuario->is($request->user()) && ($datos['rol'] !== Usuario::ADMINISTRADOR || ! $datos['activo'])) {
            return back()->withInput()->with('error', 'No puedes quitarte el rol de administrador ni desactivar tu propia cuenta.');
        }

        $usuario->update($datos);

        if (! $usuario->activo) {
            $usuario->tokens()->delete();   // invalida sus tokens de la API
        }

        return redirect()->route('usuarios.index')->with('ok', 'Cuenta actualizada.');
    }

    public function destroy(Request $request, Usuario $usuario): RedirectResponse
    {
        if ($usuario->is($request->user())) {
            return back()->with('error', 'No puedes eliminar tu propia cuenta.');
        }

        $usuario->tokens()->delete();
        $usuario->delete();

        return redirect()->route('usuarios.index')->with('ok', 'Cuenta eliminada.');
    }

    /**
     * La contraseña es opcional: sin ella la cuenta solo entra con Google. Al editar, vacía = se mantiene la actual.
     *
     * @return array<string, mixed>
     */
    private function validado(Request $request, ?Usuario $actual = null): array
    {
        $request->merge(['email' => mb_strtolower(trim((string) $request->input('email')))]);

        $datos = $request->validate([
            'email' => ['required', 'email', 'max:255', Rule::unique('usuarios', 'email')->ignore($actual)],
            'password' => ['nullable', 'string', 'min:8', 'max:255', 'confirmed'],
            'nombre' => ['required', 'string', 'max:255'],
            'rol' => ['required', Rule::in([Usuario::ADMINISTRADOR, Usuario::OPERADOR])],
        ], ['email.unique' => 'Ya existe una cuenta con ese correo.']);

        if (blank($datos['password'] ?? null)) {
            unset($datos['password']);
        }

        $datos['activo'] = $request->boolean('activo');

        return $datos;
    }
}
