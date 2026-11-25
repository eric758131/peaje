<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Usuario;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Laravel\Socialite\Facades\Socialite;

/**
 * Inicio de sesión web: con Google (OAuth 2.0) o con email y contraseña.
 * En los dos casos solo entran las cuentas activas de la tabla usuarios (lista blanca).
 */
class AuthController extends Controller
{
    private const INTENTOS = 5;

    public function login(): View
    {
        return view('auth.login', [
            'google' => self::googleConfigurado(),
            'contrasena' => (bool) config('peaje.login_con_contrasena'),
        ]);
    }

    public function autenticar(Request $request): RedirectResponse
    {
        abort_unless(config('peaje.login_con_contrasena'), 404);

        $request->merge(['email' => mb_strtolower(trim((string) $request->input('email')))]);

        $credenciales = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $clave = 'login:'.Str::transliterate($credenciales['email']).'|'.$request->ip();

        if (RateLimiter::tooManyAttempts($clave, self::INTENTOS)) {
            throw ValidationException::withMessages([
                'email' => 'Demasiados intentos. Espera '.RateLimiter::availableIn($clave).' segundos.',
            ]);
        }

        if (! Auth::attempt($credenciales, $request->boolean('recordar'))) {
            RateLimiter::hit($clave);

            throw ValidationException::withMessages(['email' => 'El correo o la contraseña no son correctos.']);
        }

        RateLimiter::clear($clave);

        if (! Auth::user()->activo) {
            Auth::logout();

            return redirect()->route('acceso.denegado');
        }

        $request->session()->regenerate();

        return redirect()->intended(route('panel'));
    }

    /** Lleva a la pantalla de Google para elegir la cuenta. */
    public function google(): RedirectResponse
    {
        if (! self::googleConfigurado()) {
            return redirect()->route('login')->with('error', 'El inicio de sesión con Google todavía no está configurado: faltan GOOGLE_CLIENT_ID y GOOGLE_CLIENT_SECRET en el .env (pasos en docs/google-oauth.md).');
        }

        return Socialite::driver('google')->with(['prompt' => 'select_account'])->redirect();
    }

    /** Google vuelve aquí con la cuenta elegida: solo entra si el correo está en la lista blanca y activo. */
    public function googleCallback(Request $request): RedirectResponse
    {
        if (! self::googleConfigurado() || $request->filled('error')) {
            return redirect()->route('login')->with('error', 'Se canceló el inicio de sesión con Google.');
        }

        try {
            $cuenta = Socialite::driver('google')->user();
        } catch (\Throwable $e) {
            Log::warning('Login con Google falló: '.$e->getMessage());

            return redirect()->route('login')->with('error', 'No se pudo iniciar sesión con Google. Inténtalo otra vez.');
        }

        $email = mb_strtolower(trim((string) $cuenta->getEmail()));
        $verificado = (bool) ($cuenta->user['email_verified'] ?? $cuenta->user['verified_email'] ?? true);
        $usuario = $email !== '' && $verificado ? Usuario::where('email', $email)->first() : null;

        if (! $usuario || ! $usuario->activo) {
            return redirect()->route('acceso.denegado')->with('email_denegado', $email ?: null);
        }

        if ($usuario->nombre === $usuario->email && filled($cuenta->getName())) {
            $usuario->forceFill(['nombre' => $cuenta->getName()])->save();
        }

        Auth::login($usuario);
        $request->session()->regenerate();

        return redirect()->intended(route('panel'));
    }

    public function denegado(): View
    {
        return view('auth.denegado', ['email' => session('email_denegado')]);
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }

    public static function googleConfigurado(): bool
    {
        return filled(config('services.google.client_id')) && filled(config('services.google.client_secret'));
    }
}
