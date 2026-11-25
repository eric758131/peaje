import 'package:flutter/material.dart';

/// Texto y color para los estados de un paso (cobrado, sin saldo, no registrado)
/// y para los estados de consulta del agente.
class Estados {
  Estados._();

  static String textoPaso(String estado) => switch (estado) {
    'cobrado' => 'Cobrado',
    'sin_saldo' => 'Sin saldo',
    'no_registrado' => 'No registrado',
    _ => estado,
  };

  static String textoConsulta(String estado) => switch (estado) {
    'registrada_con_paso' => 'Registrada y con paso',
    'registrada_sin_paso' => 'Registrada, sin paso',
    'identificada_no_registrada' => 'Identificada, no registrada',
    'no_existe' => 'No existe',
    'formato_invalido' => 'Formato inválido',
    'sin_placa' => 'Sin placa legible',
    _ => estado,
  };

  static Color colorPaso(String estado) => switch (estado) {
    'cobrado' => Colors.green.shade700,
    'sin_saldo' => Colors.orange.shade800,
    'no_registrado' => Colors.red.shade700,
    _ => Colors.grey,
  };

  static Color colorConsulta(String estado) => switch (estado) {
    'registrada_con_paso' || 'registrada_sin_paso' => Colors.green.shade700,
    'identificada_no_registrada' => Colors.red.shade700,
    'no_existe' => Colors.orange.shade800,
    _ => Colors.grey,
  };
}
