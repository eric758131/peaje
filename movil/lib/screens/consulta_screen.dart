import 'dart:io';
import 'dart:typed_data';

import 'package:flutter/material.dart';
import 'package:image_picker/image_picker.dart';

import '../api/api_exception.dart';
import '../models/models.dart';
import '../servicios.dart';
import '../utils/estados.dart';

/// Modo Consulta: placa escrita, foto de placa, o chat con el agente.
class ConsultaScreen extends StatelessWidget {
  const ConsultaScreen({super.key});

  @override
  Widget build(BuildContext context) {
    return DefaultTabController(
      length: 3,
      child: Scaffold(
        appBar: AppBar(
          title: const Text('Modo Consulta'),
          bottom: const TabBar(
            tabs: [
              Tab(icon: Icon(Icons.keyboard), text: 'Texto'),
              Tab(icon: Icon(Icons.photo_camera), text: 'Foto'),
              Tab(icon: Icon(Icons.smart_toy), text: 'Agente'),
            ],
          ),
        ),
        body: const TabBarView(
          children: [_ConsultaTexto(), _ConsultaFoto(), _ChatAgente()],
        ),
      ),
    );
  }
}

/// Muestra el resultado de una consulta (texto o foto).
class _ResultadoConsulta extends StatelessWidget {
  const _ResultadoConsulta({required this.resultado, this.alTocarSugerencia});

  final ConsultaPlaca resultado;
  final void Function(String placa)? alTocarSugerencia;

  @override
  Widget build(BuildContext context) {
    final color = Estados.colorConsulta(resultado.estado);
    final ultimo = resultado.ultimoPaso;

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
            if (resultado.placa != null)
              Text(
                resultado.placa!,
                style: const TextStyle(
                  fontSize: 26,
                  fontWeight: FontWeight.bold,
                  fontFamily: 'monospace',
                ),
              ),
            const SizedBox(height: 4),
            Text(
              Estados.textoConsulta(resultado.estado),
              style: TextStyle(
                color: color,
                fontWeight: FontWeight.w700,
                fontSize: 16,
              ),
            ),
            if (resultado.mensaje != null) ...[
              const SizedBox(height: 8),
              Text(resultado.mensaje!),
            ],
            if (resultado.advertencia != null) ...[
              const SizedBox(height: 8),
              Text(
                resultado.advertencia!,
                style: const TextStyle(color: Colors.orange),
              ),
            ],
            if (resultado.categoria != null) ...[
              const SizedBox(height: 8),
              Text(
                'Categoría: ${resultado.categoria} · Ejes: ${resultado.ejes ?? '—'}',
              ),
            ],
            if (resultado.saldo != null)
              Text('Saldo: Bs ${resultado.saldo!.toStringAsFixed(2)}'),
            if (ultimo != null) ...[
              const SizedBox(height: 8),
              const Text(
                'Último paso',
                style: TextStyle(fontWeight: FontWeight.bold),
              ),
              Text('${ultimo.fecha} · ${ultimo.caseta}'),
              Text(
                '${ultimo.categoria ?? '—'} · Bs ${ultimo.monto.toStringAsFixed(2)}',
              ),
            ],
            if (resultado.sugerencias.isNotEmpty) ...[
              const SizedBox(height: 12),
              const Text(
                '¿Quisiste decir?',
                style: TextStyle(fontWeight: FontWeight.bold),
              ),
              Wrap(
                spacing: 8,
                children: [
                  for (final s in resultado.sugerencias)
                    ActionChip(
                      label: Text(
                        s,
                        style: const TextStyle(fontFamily: 'monospace'),
                      ),
                      onPressed: alTocarSugerencia == null
                          ? null
                          : () => alTocarSugerencia!(s),
                    ),
                ],
              ),
            ],
          ],
        ),
      ),
    );
  }
}

class _ConsultaTexto extends StatefulWidget {
  const _ConsultaTexto();

  @override
  State<_ConsultaTexto> createState() => _ConsultaTextoState();
}

class _ConsultaTextoState extends State<_ConsultaTexto> {
  final _controlador = TextEditingController();
  bool _cargando = false;
  ConsultaPlaca? _resultado;
  String? _error;

  @override
  void dispose() {
    _controlador.dispose();
    super.dispose();
  }

  Future<void> _consultar([String? placaDirecta]) async {
    final placa = (placaDirecta ?? _controlador.text).trim();
    if (placa.isEmpty) return;

    setState(() {
      _cargando = true;
      _error = null;
      _resultado = null;
      if (placaDirecta != null) _controlador.text = placaDirecta;
    });

    try {
      final r = await Servicios.peaje.consultarTexto(placa);
      if (mounted) setState(() => _resultado = r);
    } on ApiException catch (e) {
      if (mounted) setState(() => _error = e.mensaje);
    } finally {
      if (mounted) setState(() => _cargando = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    return ListView(
      padding: const EdgeInsets.all(16),
      children: [
        TextField(
          controller: _controlador,
          textCapitalization: TextCapitalization.characters,
          decoration: const InputDecoration(
            labelText: 'Placa',
            hintText: 'Ej. 1234ABC o 1234-abc',
            border: OutlineInputBorder(),
          ),
          onSubmitted: (_) => _consultar(),
        ),
        const SizedBox(height: 12),
        FilledButton.icon(
          onPressed: _cargando ? null : () => _consultar(),
          icon: const Icon(Icons.search),
          label: const Text('Consultar'),
        ),
        const SizedBox(height: 16),
        if (_cargando) const LinearProgressIndicator(),
        if (_error != null)
          Text(
            _error!,
            style: TextStyle(color: Theme.of(context).colorScheme.error),
          ),
        if (_resultado != null)
          _ResultadoConsulta(
            resultado: _resultado!,
            alTocarSugerencia: (placa) => _consultar(placa),
          ),
      ],
    );
  }
}

class _ConsultaFoto extends StatefulWidget {
  const _ConsultaFoto();

  @override
  State<_ConsultaFoto> createState() => _ConsultaFotoState();
}

class _ConsultaFotoState extends State<_ConsultaFoto> {
  final _selector = ImagePicker();
  bool _cargando = false;
  File? _imagen;
  ConsultaPlaca? _resultado;
  String? _error;

  Future<XFile?> _elegirFoto(ImageSource origen) async {
    try {
      return await _selector.pickImage(
        source: origen,
        imageQuality: 85,
        maxWidth: 1600,
      );
    } catch (_) {
      // Permiso denegado o cámara no disponible.
      if (mounted) {
        setState(
          () => _error =
              'No se pudo abrir la cámara o la galería. Revisa los permisos.',
        );
      }
      return null;
    }
  }

  Future<void> _tomarFoto(ImageSource origen) async {
    final foto = await _elegirFoto(origen);
    if (foto == null) return;

    final ruta = foto.path;
    setState(() {
      _imagen = File(ruta);
      _resultado = null;
      _error = null;
      _cargando = true;
    });

    try {
      final r = await Servicios.peaje.consultarFoto(ruta);
      if (mounted) setState(() => _resultado = r);
    } on ApiException catch (e) {
      if (mounted) setState(() => _error = e.mensaje);
    } finally {
      if (mounted) setState(() => _cargando = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    return ListView(
      padding: const EdgeInsets.all(16),
      children: [
        Row(
          children: [
            Expanded(
              child: FilledButton.icon(
                onPressed: _cargando
                    ? null
                    : () => _tomarFoto(ImageSource.camera),
                icon: const Icon(Icons.photo_camera),
                label: const Text('Tomar foto'),
              ),
            ),
            const SizedBox(width: 12),
            Expanded(
              child: OutlinedButton.icon(
                onPressed: _cargando
                    ? null
                    : () => _tomarFoto(ImageSource.gallery),
                icon: const Icon(Icons.photo_library),
                label: const Text('Galería'),
              ),
            ),
          ],
        ),
        const SizedBox(height: 16),
        if (_imagen != null)
          ClipRRect(
            borderRadius: BorderRadius.circular(12),
            child: Image.file(_imagen!, height: 200, fit: BoxFit.contain),
          ),
        if (_cargando)
          const Padding(
            padding: EdgeInsets.all(12),
            child: LinearProgressIndicator(),
          ),
        if (_error != null)
          Text(
            _error!,
            style: TextStyle(color: Theme.of(context).colorScheme.error),
          ),
        if (_resultado != null) ...[
          const SizedBox(height: 12),
          _ResultadoConsulta(resultado: _resultado!),
        ],
      ],
    );
  }
}

class _ChatAgente extends StatefulWidget {
  const _ChatAgente();

  @override
  State<_ChatAgente> createState() => _ChatAgenteState();
}

/// Un mensaje del chat. [imagen]: la foto enviada o la imagen analizada que devuelve el agente.
typedef _Mensaje = ({
  bool esUsuario,
  String texto,
  Uint8List? imagen,
  bool sinLlm,
});

class _ChatAgenteState extends State<_ChatAgente> {
  static const _bienvenida =
      'Hola. Escribe una placa, o envía una foto y pregúntame qué hay: '
      'vehículos, tipos, llantas, personas y placas.';

  final _controlador = TextEditingController();
  final _scroll = ScrollController();
  final _selector = ImagePicker();
  final List<_Mensaje> _mensajes = [
    (esUsuario: false, texto: _bienvenida, imagen: null, sinLlm: false),
  ];
  bool _cargando = false;

  /// Id de la conversación: el agente recuerda la última foto y lo conversado.
  String? _conversacion;

  @override
  void dispose() {
    _controlador.dispose();
    _scroll.dispose();
    super.dispose();
  }

  void _agregar(_Mensaje m) {
    if (mounted) setState(() => _mensajes.add(m));
    _bajar();
  }

  Future<void> _enviar({XFile? foto}) async {
    final texto = _controlador.text.trim();
    if ((texto.isEmpty && foto == null) || _cargando) return;

    final bytes = foto == null ? null : await foto.readAsBytes();
    _controlador.clear();
    setState(() => _cargando = true);
    _agregar((
      esUsuario: true,
      texto: foto == null ? texto : (texto.isEmpty ? '[foto]' : texto),
      imagen: bytes,
      sinLlm: false,
    ));

    try {
      final r = await Servicios.peaje.chat(
        mensaje: texto,
        rutaImagen: foto?.path,
        conversacion: _conversacion,
      );
      _conversacion = r.conversacion ?? _conversacion;
      _agregar((
        esUsuario: false,
        texto: r.respuesta,
        imagen: r.evidencia,
        sinLlm: r.sinLlm,
      ));
    } on ApiException catch (e) {
      _agregar((
        esUsuario: false,
        texto: e.mensaje,
        imagen: null,
        sinLlm: false,
      ));
    } finally {
      if (mounted) setState(() => _cargando = false);
    }
  }

  Future<void> _elegirFoto(ImageSource origen) async {
    try {
      final foto = await _selector.pickImage(
        source: origen,
        imageQuality: 85,
        maxWidth: 1600,
      );
      if (foto != null) await _enviar(foto: foto);
    } catch (_) {
      _agregar((
        esUsuario: false,
        texto: 'No se pudo abrir la cámara o la galería. Revisa los permisos.',
        imagen: null,
        sinLlm: false,
      ));
    }
  }

  void _nuevaConversacion() {
    setState(() {
      _conversacion = null;
      _mensajes
        ..clear()
        ..add((
          esUsuario: false,
          texto: _bienvenida,
          imagen: null,
          sinLlm: false,
        ));
    });
  }

  void _bajar() {
    WidgetsBinding.instance.addPostFrameCallback((_) {
      if (_scroll.hasClients) {
        _scroll.animateTo(
          _scroll.position.maxScrollExtent,
          duration: const Duration(milliseconds: 200),
          curve: Curves.easeOut,
        );
      }
    });
  }

  @override
  Widget build(BuildContext context) {
    final colores = Theme.of(context).colorScheme;
    return Column(
      children: [
        Align(
          alignment: Alignment.centerRight,
          child: TextButton.icon(
            onPressed: _cargando ? null : _nuevaConversacion,
            icon: const Icon(Icons.refresh, size: 18),
            label: const Text('Nueva conversación'),
          ),
        ),
        Expanded(
          child: ListView.builder(
            controller: _scroll,
            padding: const EdgeInsets.fromLTRB(12, 0, 12, 12),
            itemCount: _mensajes.length,
            itemBuilder: (context, i) {
              final m = _mensajes[i];
              return Align(
                alignment: m.esUsuario
                    ? Alignment.centerRight
                    : Alignment.centerLeft,
                child: Container(
                  margin: const EdgeInsets.symmetric(vertical: 4),
                  padding: const EdgeInsets.symmetric(
                    horizontal: 12,
                    vertical: 8,
                  ),
                  constraints: BoxConstraints(
                    maxWidth: MediaQuery.of(context).size.width * 0.8,
                  ),
                  decoration: BoxDecoration(
                    color: m.esUsuario
                        ? colores.primaryContainer
                        : Colors.grey.shade200,
                    borderRadius: BorderRadius.circular(12),
                  ),
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      if (m.imagen != null)
                        Padding(
                          padding: const EdgeInsets.only(bottom: 6),
                          child: ClipRRect(
                            borderRadius: BorderRadius.circular(8),
                            child: Image.memory(
                              m.imagen!,
                              height: 180,
                              fit: BoxFit.contain,
                            ),
                          ),
                        ),
                      Text(m.texto),
                      if (m.sinLlm)
                        Padding(
                          padding: const EdgeInsets.only(top: 4),
                          child: Text(
                            'Respuesta directa de la base (el agente de Colab no está disponible).',
                            style: Theme.of(context).textTheme.bodySmall
                                ?.copyWith(color: Colors.grey.shade600),
                          ),
                        ),
                    ],
                  ),
                ),
              );
            },
          ),
        ),
        if (_cargando) const LinearProgressIndicator(),
        SafeArea(
          child: Padding(
            padding: const EdgeInsets.fromLTRB(8, 4, 12, 8),
            child: Row(
              children: [
                IconButton(
                  tooltip: 'Tomar foto',
                  onPressed: _cargando
                      ? null
                      : () => _elegirFoto(ImageSource.camera),
                  icon: const Icon(Icons.photo_camera),
                ),
                IconButton(
                  tooltip: 'Foto de la galería',
                  onPressed: _cargando
                      ? null
                      : () => _elegirFoto(ImageSource.gallery),
                  icon: const Icon(Icons.photo_library),
                ),
                Expanded(
                  child: TextField(
                    controller: _controlador,
                    decoration: const InputDecoration(
                      hintText: 'Placa o pregunta',
                      border: OutlineInputBorder(),
                    ),
                    onSubmitted: (_) => _enviar(),
                  ),
                ),
                const SizedBox(width: 8),
                IconButton.filled(
                  onPressed: _cargando ? null : () => _enviar(),
                  icon: const Icon(Icons.send),
                ),
              ],
            ),
          ),
        ),
      ],
    );
  }
}
