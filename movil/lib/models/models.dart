// Modelos que reflejan las respuestas de la API (ver docs/contrato-movil.md).

import 'dart:convert';
import 'dart:typed_data';

/// Respuesta del agente (`POST /agente/chat`).
class RespuestaAgente {
  RespuestaAgente({
    required this.respuesta,
    required this.conversacion,
    this.sinLlm = false,
    this.evidencia,
  });

  final String respuesta;

  /// Id de la conversación: se reenvía en el próximo mensaje para que el agente recuerde.
  final String? conversacion;

  /// true si Colab no respondió y el texto salió directo de la base de datos.
  final bool sinLlm;

  /// Imagen analizada (JPEG) cuando se envió una foto.
  final Uint8List? evidencia;

  factory RespuestaAgente.desdeJson(Map<String, dynamic> json) {
    final evidencia = json['evidencia'] as String?;
    return RespuestaAgente(
      respuesta: json['respuesta'] as String? ?? 'Sin respuesta.',
      conversacion: json['conversacion'] as String?,
      sinLlm: json['modo'] == 'sin_llm',
      evidencia: evidencia == null
          ? null
          : base64Decode(evidencia.substring(evidencia.indexOf(',') + 1)),
    );
  }
}

class Caseta {
  Caseta({required this.id, required this.nombre, this.ubicacion});

  final int id;
  final String nombre;
  final String? ubicacion;

  factory Caseta.fromJson(Map<String, dynamic> json) => Caseta(
    id: json['id'] as int,
    nombre: json['nombre'] as String,
    ubicacion: json['ubicacion'] as String?,
  );
}

/// Resultado de un frame de caseta (`POST /pasos/frame`, 201).
class PasoRegistrado {
  PasoRegistrado({
    required this.id,
    required this.placa,
    required this.categoria,
    required this.ejes,
    required this.pasajeros,
    required this.monto,
    required this.estado,
    required this.imagenUrl,
    required this.creado,
    required this.confianza,
    required this.latenciaMs,
  });

  final int id;
  final String placa;
  final String? categoria;
  final int? ejes;
  final int? pasajeros;
  final double monto;
  final String estado;
  final String? imagenUrl;
  final DateTime creado;
  final double? confianza;
  final int? latenciaMs;

  factory PasoRegistrado.fromJson(Map<String, dynamic> json) {
    final paso = json['paso'] as Map<String, dynamic>;
    final lectura = json['lectura'] as Map<String, dynamic>? ?? {};

    return PasoRegistrado(
      id: paso['id'] as int,
      placa: paso['placa'] as String,
      categoria: paso['categoria'] as String?,
      ejes: paso['ejes'] as int?,
      pasajeros: paso['pasajeros'] as int?,
      monto: (paso['monto'] as num).toDouble(),
      estado: paso['estado'] as String,
      imagenUrl: paso['imagen_url'] as String?,
      creado: DateTime.parse(paso['created_at'] as String),
      confianza: (lectura['confianza'] as num?)?.toDouble(),
      latenciaMs: lectura['latencia_ms'] as int?,
    );
  }
}

/// Resultado de consultar una placa, por texto o imagen.
class ConsultaPlaca {
  ConsultaPlaca({
    required this.placa,
    required this.estado,
    this.categoria,
    this.ejes,
    this.saldo,
    this.sugerencias = const [],
    this.mensaje,
    this.advertencia,
    this.ultimoPaso,
  });

  final String? placa;
  final String estado;
  final String? categoria;
  final int? ejes;
  final double? saldo;
  final List<String> sugerencias;
  final String? mensaje;
  final String? advertencia;
  final UltimoPaso? ultimoPaso;

  factory ConsultaPlaca.desdeJson(Map<String, dynamic> json) {
    final ultimo = json['ultimo_paso'] as Map<String, dynamic>?;

    return ConsultaPlaca(
      placa: json['placa'] as String?,
      estado: json['estado'] as String,
      categoria: json['categoria'] as String?,
      ejes: json['ejes'] as int?,
      saldo: (json['saldo'] as num?)?.toDouble(),
      sugerencias: (json['sugerencias'] as List<dynamic>? ?? []).cast<String>(),
      mensaje: json['mensaje'] as String?,
      advertencia: json['advertencia'] as String?,
      ultimoPaso: ultimo == null ? null : UltimoPaso.fromJson(ultimo),
    );
  }

  /// La imagen no tenía placa legible (la API responde 422 con estado `sin_placa`).
  factory ConsultaPlaca.sinPlaca(String mensaje) =>
      ConsultaPlaca(placa: null, estado: 'sin_placa', mensaje: mensaje);
}

class UltimoPaso {
  UltimoPaso({
    required this.fecha,
    required this.caseta,
    required this.categoria,
    required this.monto,
  });

  final String fecha;
  final String caseta;
  final String? categoria;
  final double monto;

  factory UltimoPaso.fromJson(Map<String, dynamic> json) => UltimoPaso(
    fecha: json['fecha'] as String,
    caseta: json['caseta'] as String,
    categoria: json['categoria'] as String?,
    monto: (json['monto'] as num).toDouble(),
  );
}
