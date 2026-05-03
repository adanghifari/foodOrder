import 'package:flutter/material.dart';
import 'keranjang.dart';
import 'pembayaranPage.dart';

void main() {
  runApp(const MyApp());
}

class MyApp extends StatelessWidget {
  const MyApp({super.key});

  @override
  Widget build(BuildContext context) {
    return MaterialApp(
      debugShowCheckedModeBanner: false,
      title: 'KedaiKlik App',
      theme: ThemeData(
        scaffoldBackgroundColor: const Color(0xFFFFFFFF),
        useMaterial3: true,
      ),
      // Perbaikan: Hapus 'const' di sini karena KeranjangPage adalah StatefulWidget
      home: KeranjangPage(), 
      
      routes: {
        '/pembayaran': (context) => const PembayaranPage(),
      },
    );
  }
}