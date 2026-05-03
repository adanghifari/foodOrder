import 'package:flutter/material.dart';
import 'screens/profile_page.dart'; // Import halaman profil

void main() {
  runApp(const MyApp());
}

class MyApp extends StatelessWidget {
  const MyApp({super.key});

  @override
  Widget build(BuildContext context) {
    return MaterialApp(
      debugShowCheckedModeBanner: false,
      title: 'Mobile App',
      theme: ThemeData(
        useMaterial3: true,
        primaryColor: const Color(0xFFC6620C),
      ),
      // Aplikasi dimulai dari ProfilePage
      home: const ProfilePage(),
    );
  }
}