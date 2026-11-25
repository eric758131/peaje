<?php

use App\Models\Usuario;
use Illuminate\Support\Facades\Broadcast;

/*
| Canal privado del panel. Solo cuentas activas escuchan.
*/
Broadcast::channel('peaje', function ($usuario) {
    return $usuario instanceof Usuario && $usuario->activo;
});
