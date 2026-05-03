import 'package:flutter/material.dart';

class TentangPage extends StatelessWidget {
  const TentangPage({super.key});

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: const Color(0xFFFFFFFF), // Latar belakang putih #FFFFFF
      appBar: AppBar(
        backgroundColor: Colors.white,
        elevation: 0,
        leading: IconButton(
          icon: const Icon(Icons.arrow_back_ios_new, color: Colors.black, size: 20),
          onPressed: () => Navigator.pop(context),
        ),
        title: const Text(
          "Tentang Kami",
          style: TextStyle(
            color: Colors.black,
            fontWeight: FontWeight.bold,
            fontSize: 20,
          ),
        ),
        centerTitle: true,
      ),
      body: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          const SizedBox(height: 10),
          
          // Menu Informasi Utama
          _buildAboutTile("FAQ"),
          _buildAboutTile("Masukan Aplikasi"),
          _buildAboutTile("Kebijakan Privasi"),
          _buildAboutTile("Syarat & Ketentuan"),
          
          const SizedBox(height: 20),
          
          // Menu Media Sosial (Sesuai Gambar)
          _buildSocialTile("Facebook"),
          _buildSocialTile("Twitter"),
          _buildSocialTile("Instagram"),
        ],
      ),
    );
  }

  // Widget untuk menu dengan ikon panah
  Widget _buildAboutTile(String title) {
    return ListTile(
      contentPadding: const EdgeInsets.symmetric(horizontal: 25),
      title: Text(
        title,
        style: const TextStyle(
          fontSize: 16,
          fontWeight: FontWeight.w600,
          color: Colors.black,
        ),
      ),
      trailing: const Icon(
        Icons.arrow_forward_ios,
        color: Color(0xFFC7985F), // Warna cokelat muda #C7985F
        size: 18,
      ),
      onTap: () {
        // Navigasi ke detail informasi
      },
    );
  }

  // Widget untuk menu media sosial tanpa ikon panah
  Widget _buildSocialTile(String title) {
    return ListTile(
      contentPadding: const EdgeInsets.symmetric(horizontal: 25),
      title: Text(
        title,
        style: const TextStyle(
          fontSize: 16,
          fontWeight: FontWeight.w600,
          color: Colors.black,
        ),
      ),
      onTap: () {
        // Logika membuka link sosial media
      },
    );
  }
}