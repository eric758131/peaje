import 'dart:async';
import 'dart:io';

import 'package:camera/camera.dart';
import 'package:flutter/material.dart';

import '../api/api_exception.dart';
import '../config.dart';
import '../models/models.dart';
import '../servicios.dart';
import '../utils/estados.dart';

/// Modo Caseta: captura frames de la cámara y los envía a la API.
///
/// Envía un frame y espera la respuesta antes de enviar el siguiente, así no se acumulan
/// peticiones si la red es lenta. Por eso la tasa real depende de la latencia del modelo.
class CasetaScreen extends StatefulWidget {
  const CasetaScreen({super.key});

  @override
  State<CasetaScreen> createState() => _CasetaScreenState();
}

class _CasetaScreenState extends State<CasetaScreen> {
  CameraController? _camara;
  Timer? _timer;

  List<Caseta> _casetas = [];
  int? _casetaId;
  String? _errorCarga;

  bool _activo = false;

  /// Frames enviados que aún esperan respuesta. Se mandan varios a la vez para llegar a
  /// 2-5 frames por segundo aunque cada respuesta tarde; el servidor evita cobrar dos veces.
  int _enVuelo = 0;
  int _framesEnviados = 0;

  PasoRegistrado? _ultimo;
  final List<PasoRegistrado> _historial = [];
  String? _aviso;

  @override
  void initState() {
    super.initState();
    _cargarCasetas();
    _iniciarCamara();
  }

  @override
  void dispose() {
    _timer?.cancel();
    _camara?.dispose();
    super.dispose();
  }

  Future<void> _cargarCasetas() async {
    try {
      final lista = await Servicios.peaje.casetas();
      if (!mounted) return;
      setState(() {
        _casetas = lista;
        _casetaId = lista.isNotEmpty ? lista.first.id : null;
        _errorCarga = lista.isEmpty
            ? 'No hay casetas activas en el sistema.'
            : null;
      });
    } on ApiException catch (e) {
      if (mounted) setState(() => _errorCarga = e.mensaje);
    } catch (e) {
      if (mounted) {
        setState(() => _errorCarga = 'No se pudieron cargar las casetas: $e');
      }
    }
  }

  Future<void> _iniciarCamara() async {
    try {
      final camaras = await availableCameras();
      if (camaras.isEmpty) {
        if (mounted) {
          setState(() => _aviso = 'Este teléfono no tiene cámara disponible.');
        }
        return;
      }

      final trasera = camaras.firstWhere(
        (c) => c.lensDirection == CameraLensDirection.back,
        orElse: () => camaras.first,
      );

      final controlador = CameraController(
        trasera,
        ResolutionPreset
            .medium, // suficiente para leer placas y evita frames muy pesados
        enableAudio: false,
      );
      await controlador.initialize();

      if (!mounted) {
        await controlador.dispose();
        return;
      }
      setState(() => _camara = controlador);
    } on CameraException catch (e) {
      if (mounted) {
        setState(
          () =>
              _aviso = 'No se pudo abrir la cámara: ${e.description ?? e.code}',
        );
      }
    }
  }

  void _alternarCaptura() {
    if (_activo) {
      _timer?.cancel();
      setState(() => _activo = false);
      return;
    }

    if (_casetaId == null || _camara == null || !_camara!.value.isInitialized) {
      return;
    }

    _timer = Timer.periodic(
      const Duration(milliseconds: Config.intervaloFrameMs),
      (_) => _enviarFrame(),
    );
    setState(() {
      _activo = true;
      _aviso = null;
    });
  }

  Future<void> _enviarFrame() async {
    final camara = _camara;
    final casetaId = _casetaId;
    if (!_activo ||
        _enVuelo >= Config.maxFramesEnVuelo ||
        camara == null ||
        casetaId == null ||
        !camara.value.isInitialized) {
      return;
    }
    if (camara.value.isTakingPicture) return;

    _enVuelo++;
    File? archivo;

    try {
      final foto = await camara.takePicture();
      archivo = File(foto.path);

      final paso = await Servicios.peaje.enviarFrame(
        rutaImagen: foto.path,
        casetaId: casetaId,
      );
      if (!mounted) return;

      setState(() {
        _framesEnviados++;
        if (paso == null) {
          _aviso = 'Sin placa legible en este frame.';
        } else {
          _aviso = null;
          _ultimo = paso;
          _historial.insert(0, paso);
          if (_historial.length > 10) _historial.removeLast();
        }
      });
    } on ApiException catch (e) {
      if (!mounted) return;
      if (e.esNoAutorizado) {
        _detener();
        return;
      }
      setState(() => _aviso = e.mensaje);
    } on CameraException catch (e) {
      if (mounted) {
        setState(() => _aviso = 'Error de cámara: ${e.description ?? e.code}');
      }
    } finally {
      // El frame ya subió: se borra para no llenar el almacenamiento del teléfono.
      try {
        await archivo?.delete();
      } catch (_) {}
      _enVuelo--;
    }
  }

  void _detener() {
    _timer?.cancel();
    if (mounted) setState(() => _activo = false);
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text('Modo Caseta')),
      body: Column(
        children: [
          _selectorCaseta(),
          Expanded(flex: 5, child: _vistaCamara()),
          Expanded(flex: 4, child: _panelResultados()),
        ],
      ),
    );
  }

  Widget _selectorCaseta() {
    if (_errorCarga != null) {
      return Padding(
        padding: const EdgeInsets.all(12),
        child: Row(
          children: [
            Expanded(
              child: Text(
                _errorCarga!,
                style: TextStyle(color: Theme.of(context).colorScheme.error),
              ),
            ),
            TextButton(
              onPressed: _cargarCasetas,
              child: const Text('Reintentar'),
            ),
          ],
        ),
      );
    }

    return Padding(
      padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 8),
      child: Row(
        children: [
          const Text('Caseta: '),
          const SizedBox(width: 8),
          Expanded(
            child: DropdownButton<int>(
              isExpanded: true,
              value: _casetaId,
              hint: const Text('Cargando casetas...'),
              items: _casetas
                  .map(
                    (c) => DropdownMenuItem(value: c.id, child: Text(c.nombre)),
                  )
                  .toList(),
              onChanged: _activo
                  ? null
                  : (valor) => setState(() => _casetaId = valor),
            ),
          ),
        ],
      ),
    );
  }

  Widget _vistaCamara() {
    final camara = _camara;

    if (camara == null || !camara.value.isInitialized) {
      return Center(child: Text(_aviso ?? 'Iniciando cámara...'));
    }

    return Stack(
      fit: StackFit.expand,
      children: [
        ClipRRect(
          child: FittedBox(
            fit: BoxFit.cover,
            child: SizedBox(
              width: camara.value.previewSize?.height ?? 1,
              height: camara.value.previewSize?.width ?? 1,
              child: CameraPreview(camara),
            ),
          ),
        ),
        Positioned(
          left: 12,
          top: 12,
          child: _Insignia(
            texto: _activo ? 'EN VIVO · $_framesEnviados frames' : 'Detenido',
            color: _activo ? Colors.red.shade700 : Colors.grey.shade800,
          ),
        ),
        Positioned(
          left: 0,
          right: 0,
          bottom: 12,
          child: Center(
            child: FloatingActionButton.extended(
              onPressed: _casetaId == null ? null : _alternarCaptura,
              icon: Icon(_activo ? Icons.stop : Icons.play_arrow),
              label: Text(_activo ? 'Detener' : 'Iniciar captura'),
            ),
          ),
        ),
      ],
    );
  }

  Widget _panelResultados() {
    final ultimo = _ultimo;

    return ListView(
      padding: const EdgeInsets.all(12),
      children: [
        if (_aviso != null)
          Padding(
            padding: const EdgeInsets.only(bottom: 8),
            child: Text(_aviso!, style: const TextStyle(color: Colors.orange)),
          ),
        if (ultimo == null)
          const Text('Aún no hay vehículos registrados en esta sesión.')
        else
          _TarjetaPaso(paso: ultimo),
        if (_historial.length > 1) ...[
          const SizedBox(height: 16),
          const Text(
            'Últimos pasos',
            style: TextStyle(fontWeight: FontWeight.bold),
          ),
          const SizedBox(height: 4),
          for (final p in _historial.skip(1))
            ListTile(
              dense: true,
              contentPadding: EdgeInsets.zero,
              title: Text(
                p.placa,
                style: const TextStyle(fontFamily: 'monospace'),
              ),
              subtitle: Text('${p.categoria ?? '—'} · ${p.ejes ?? '—'} ejes'),
              trailing: Text(
                Estados.textoPaso(p.estado),
                style: TextStyle(
                  color: Estados.colorPaso(p.estado),
                  fontWeight: FontWeight.w600,
                ),
              ),
            ),
        ],
      ],
    );
  }
}

class _TarjetaPaso extends StatelessWidget {
  const _TarjetaPaso({required this.paso});

  final PasoRegistrado paso;

  @override
  Widget build(BuildContext context) {
    final color = Estados.colorPaso(paso.estado);
    final hora =
        '${paso.creado.hour.toString().padLeft(2, '0')}:${paso.creado.minute.toString().padLeft(2, '0')}:${paso.creado.second.toString().padLeft(2, '0')}';

    return Card(
      shape: RoundedRectangleBorder(
        borderRadius: BorderRadius.circular(12),
        side: BorderSide(color: color, width: 2),
      ),
      child: Padding(
        padding: const EdgeInsets.all(16),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              children: [
                Expanded(
                  child: Text(
                    paso.placa,
                    style: const TextStyle(
                      fontSize: 28,
                      fontWeight: FontWeight.bold,
                      fontFamily: 'monospace',
                    ),
                  ),
                ),
                _Insignia(texto: Estados.textoPaso(paso.estado), color: color),
              ],
            ),
            const SizedBox(height: 8),
            _Fila('Categoría', paso.categoria ?? '—'),
            _Fila('Ejes', paso.ejes?.toString() ?? '—'),
            if (paso.pasajeros != null)
              _Fila('Pasajeros', paso.pasajeros.toString()),
            _Fila('Tarifa', 'Bs ${paso.monto.toStringAsFixed(2)}'),
            _Fila('Hora', hora),
            if (paso.latenciaMs != null)
              _Fila('Latencia', '${paso.latenciaMs} ms'),
          ],
        ),
      ),
    );
  }
}

class _Fila extends StatelessWidget {
  const _Fila(this.etiqueta, this.valor);

  final String etiqueta;
  final String valor;

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 2),
      child: Row(
        children: [
          SizedBox(
            width: 100,
            child: Text(etiqueta, style: const TextStyle(color: Colors.grey)),
          ),
          Expanded(
            child: Text(
              valor,
              style: const TextStyle(fontWeight: FontWeight.w600),
            ),
          ),
        ],
      ),
    );
  }
}

class _Insignia extends StatelessWidget {
  const _Insignia({required this.texto, required this.color});

  final String texto;
  final Color color;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
      decoration: BoxDecoration(
        color: color,
        borderRadius: BorderRadius.circular(20),
      ),
      child: Text(
        texto,
        style: const TextStyle(
          color: Colors.white,
          fontWeight: FontWeight.w600,
        ),
      ),
    );
  }
}
