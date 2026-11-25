import 'package:flutter/material.dart';

import 'screens/home_screen.dart';
import 'servicios.dart';

void main() {
  WidgetsFlutterBinding.ensureInitialized();
  Servicios.iniciar();
  runApp(const PeajeApp());
}

/// La app no tiene login: abre directo en el inicio.
class PeajeApp extends StatelessWidget {
  const PeajeApp({super.key});

  @override
  Widget build(BuildContext context) {
    return MaterialApp(
      title: 'Control de peaje',
      debugShowCheckedModeBanner: false,
      theme: ThemeData(
        colorScheme: ColorScheme.fromSeed(seedColor: Colors.blue),
      ),
      home: const HomeScreen(),
    );
  }
}
