import 'package:flutter_test/flutter_test.dart';
import 'package:peaje_movil/models/models.dart';
import 'package:peaje_movil/utils/estados.dart';

void main() {
  group('RespuestaAgente', () {
    test('lee la respuesta de POST /agente/chat con foto', () {
      final r = RespuestaAgente.desdeJson({
        'respuesta': 'En la imagen detecté 2 vehículos (1 bus y 1 camión).',
        'modo': 'inferencia',
        'conversacion': 'abc-123',
        'evidencia': 'data:image/jpeg;base64,/9j/AA==',
      });

      expect(r.respuesta, startsWith('En la imagen detecté 2 vehículos'));
      expect(r.conversacion, 'abc-123');
      expect(r.sinLlm, isFalse);
      expect(r.evidencia, [0xFF, 0xD8, 0xFF, 0x00]);
    });

    test('marca las respuestas sin LLM', () {
      final r = RespuestaAgente.desdeJson({
        'respuesta': 'No existe ningún registro de la placa 9999ZZZ.',
        'modo': 'sin_llm',
        'conversacion': 'abc-123',
      });

      expect(r.sinLlm, isTrue);
      expect(r.evidencia, isNull);
    });
  });

  group('PasoRegistrado', () {
    test('lee la respuesta de POST /pasos/frame', () {
      final paso = PasoRegistrado.fromJson({
        'paso': {
          'id': 42,
          'placa': '1234ABC',
          'categoria': 'auto',
          'ejes': 2,
          'pasajeros': null,
          'monto': 10,
          'estado': 'cobrado',
          'imagen_url': 'https://peaje.test/storage/evidencias/a.jpg',
          'created_at': '2026-11-18T14:03:21-04:00',
        },
        'lectura': {'placa': '1234-abc', 'confianza': 0.92, 'latencia_ms': 180},
      });

      expect(paso.id, 42);
      expect(paso.placa, '1234ABC');
      expect(paso.monto, 10.0);
      expect(paso.estado, 'cobrado');
      expect(paso.confianza, 0.92);
      expect(paso.latenciaMs, 180);
      expect(paso.pasajeros, isNull);
    });
  });

  group('ConsultaPlaca', () {
    test('lee un estado con último paso y sugerencias', () {
      final consulta = ConsultaPlaca.desdeJson({
        'placa': '1234ABC',
        'estado': 'registrada_con_paso',
        'categoria': 'auto',
        'ejes': 2,
        'saldo': 40,
        'sugerencias': <String>[],
        'ultimo_paso': {
          'fecha': '2026-11-18 14:03:21',
          'caseta': 'Caseta Norte',
          'categoria': 'auto',
          'ejes': 2,
          'monto': 10,
          'estado_paso': 'cobrado',
        },
      });

      expect(consulta.estado, 'registrada_con_paso');
      expect(consulta.saldo, 40.0);
      expect(consulta.ultimoPaso?.caseta, 'Caseta Norte');
      expect(consulta.sugerencias, isEmpty);
    });

    test('lee sugerencias de OCR', () {
      final consulta = ConsultaPlaca.desdeJson({
        'placa': '1234A8C',
        'estado': 'formato_invalido',
        'sugerencias': ['1234ABC'],
      });

      expect(consulta.sugerencias, ['1234ABC']);
    });

    test('sin placa legible', () {
      final consulta = ConsultaPlaca.sinPlaca('No pude leer una placa.');

      expect(consulta.estado, 'sin_placa');
      expect(consulta.placa, isNull);
      expect(consulta.mensaje, 'No pude leer una placa.');
    });
  });

  group('Estados', () {
    test('cada estado de paso tiene texto propio', () {
      expect(Estados.textoPaso('cobrado'), 'Cobrado');
      expect(Estados.textoPaso('sin_saldo'), 'Sin saldo');
      expect(Estados.textoPaso('no_registrado'), 'No registrado');
    });

    test('los 4 estados del agente tienen texto', () {
      expect(
        Estados.textoConsulta('registrada_con_paso'),
        isNot('registrada_con_paso'),
      );
      expect(
        Estados.textoConsulta('registrada_sin_paso'),
        isNot('registrada_sin_paso'),
      );
      expect(
        Estados.textoConsulta('identificada_no_registrada'),
        isNot('identificada_no_registrada'),
      );
      expect(Estados.textoConsulta('no_existe'), isNot('no_existe'));
    });
  });
}
