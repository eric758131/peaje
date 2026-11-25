<?php

namespace App\Services\Peaje;

use App\Events\AlertaCreada;
use App\Events\PasoRegistrado;
use App\Models\Alerta;
use App\Models\Caseta;
use App\Models\Paso;
use App\Models\Usuario;
use App\Models\Vehiculo;
use App\Support\Placa;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Registra un paso: decide el estado (cobrado, sin saldo o no registrado),
 * descuenta saldo, crea alertas y emite los eventos en tiempo real.
 */
class RegistroPaso
{
    public function __construct(private TarifaCalculator $tarifas) {}

    /**
     * @param  array{placa: ?string, confianza: ?float, categoria: ?string, ejes: ?int, pasajeros: ?int, latencia_ms: ?int}  $lectura
     */
    public function registrar(array $lectura, Caseta $caseta, ?Usuario $operador = null, ?string $imagenUrl = null): Paso
    {
        // placa_corregida: la lectura ajustada al formato boliviano (ProcesadorFrame).
        $placa = Placa::normalizar($lectura['placa_corregida'] ?? $lectura['placa'] ?? null);

        if ($placa === null) {
            throw new \InvalidArgumentException('No se puede registrar un paso sin placa.');
        }

        $paso = DB::transaction(function () use ($lectura, $placa, $caseta, $operador, $imagenUrl) {
            $vehiculo = $placa
                ? Vehiculo::where('placa', $placa)->lockForUpdate()->first()
                : null;

            // Solo datos reales: lo que vio la cámara o, si no vio el vehículo, lo registrado.
            // Sin ninguno de los dos (placa suelta de un vehículo no registrado) no se inventa
            // categoría ni ejes, y el monto queda en 0.
            $vioVehiculo = filled($lectura['categoria'] ?? null);
            $categoria = $vioVehiculo ? $lectura['categoria'] : $vehiculo?->categoria;
            $ejes = $vioVehiculo ? ($lectura['ejes'] ?? $vehiculo?->ejes) : ($vehiculo?->ejes ?? $lectura['ejes'] ?? null);
            $ejes = $ejes !== null ? (int) $ejes : null;
            $monto = $categoria ? $this->tarifas->montoPara($categoria, $ejes ?? 2) : 0.0;

            [$estado, $alerta] = $this->decidir($vehiculo, $monto);

            if ($estado === Paso::COBRADO) {
                $vehiculo->decrement('saldo', $monto);
            }

            $paso = Paso::create([
                'placa_leida' => $lectura['placa'] ?? null,
                'placa_normalizada' => $placa,
                'confianza_ocr' => isset($lectura['confianza']) ? round($lectura['confianza'] * 100, 2) : null,
                'categoria_detectada' => $categoria,
                'ejes_detectados' => $ejes,
                'pasajeros' => $lectura['pasajeros'] ?? null,
                'monto' => $monto,
                'caseta_id' => $caseta->id,
                'usuario_id' => $operador?->id,
                'vehiculo_id' => $vehiculo?->id,
                'imagen_url' => $imagenUrl,
                'estado' => $estado,
                'latencia_ms' => $lectura['latencia_ms'] ?? null,
            ]);

            if ($alerta) {
                $paso->alertas()->create(['tipo' => $alerta, 'atendida' => false]);
            }

            return $paso;
        });

        // El paso ya quedó guardado. Si Pusher falla, no debe romper la respuesta al frame:
        // el panel se actualizará al recargar.
        try {
            $resumen = app(Resumen::class)->delDia();

            PasoRegistrado::dispatch($paso->load(['caseta', 'vehiculo']), $resumen);

            foreach ($paso->alertas as $alerta) {
                AlertaCreada::dispatch($alerta->load('paso.caseta'), $resumen);
            }
        } catch (\Throwable $e) {
            Log::warning('No se pudo emitir el evento en tiempo real.', ['paso' => $paso->id, 'error' => $e->getMessage()]);
        }

        return $paso;
    }

    /**
     * @return array{0: string, 1: ?string} [estado del paso, tipo de alerta o null]
     */
    private function decidir(?Vehiculo $vehiculo, float $monto): array
    {
        if ($vehiculo === null || ! $vehiculo->activo) {
            return [Paso::NO_REGISTRADO, Alerta::NO_REGISTRADA];
        }

        if ((float) $vehiculo->saldo < $monto) {
            return [Paso::SIN_SALDO, Alerta::SIN_SALDO];
        }

        return [Paso::COBRADO, null];
    }
}
