import 'package:flutter/material.dart';

import 'caseta_screen.dart';
import 'consulta_screen.dart';

class HomeScreen extends StatelessWidget {
  const HomeScreen({super.key});

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text('Control de peaje')),
      body: ListView(
        padding: const EdgeInsets.all(16),
        children: [
          _TarjetaModo(
            icono: Icons.videocam,
            titulo: 'Modo Caseta',
            descripcion:
                'Captura video del paso y registra vehículos en tiempo real.',
            onTap: () => Navigator.of(
              context,
            ).push(MaterialPageRoute(builder: (_) => const CasetaScreen())),
          ),
          const SizedBox(height: 12),
          _TarjetaModo(
            icono: Icons.search,
            titulo: 'Modo Consulta',
            descripcion:
                'Consulta una placa por texto o foto, o pregunta al agente.',
            onTap: () => Navigator.of(
              context,
            ).push(MaterialPageRoute(builder: (_) => const ConsultaScreen())),
          ),
        ],
      ),
    );
  }
}

class _TarjetaModo extends StatelessWidget {
  const _TarjetaModo({
    required this.icono,
    required this.titulo,
    required this.descripcion,
    required this.onTap,
  });

  final IconData icono;
  final String titulo;
  final String descripcion;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    return Card(
      child: ListTile(
        contentPadding: const EdgeInsets.all(16),
        leading: Icon(icono, size: 36),
        title: Text(
          titulo,
          style: const TextStyle(fontWeight: FontWeight.bold),
        ),
        subtitle: Text(descripcion),
        trailing: const Icon(Icons.chevron_right),
        onTap: onTap,
      ),
    );
  }
}
