import 'package:flutter/material.dart';
// Import halaman tujuan navigasi
import 'pesanan_page.dart';
import 'favorit_page.dart';
import 'pengaturan_page.dart';
import 'bantuan_page.dart';
import 'tentang_page.dart';

class ProfilePage extends StatelessWidget {
  const ProfilePage({super.key});

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: const Color(0xFFFFFFFF), // Latar belakang putih
      body: Stack(
        children: [
          // 1. Background Header dengan Gradasi Oranye #C6620C
          Container(
            height: MediaQuery.of(context).size.height * 0.45,
            decoration: const BoxDecoration(
              gradient: LinearGradient(
                begin: Alignment.topCenter,
                end: Alignment.bottomCenter,
                colors: [
                  Color(0xFFC6620C), // Oranye utama
                  Color(0xFFFFFFFF), // Gradasi ke putih
                ],
              ),
            ),
          ),

          SafeArea(
            child: Column(
              children: [
                // 2. Custom Top Bar
                Padding(
                  padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 10),
                  child: Row(
                    mainAxisAlignment: MainAxisAlignment.spaceBetween,
                    children: [
                      IconButton(
                        icon: const Icon(Icons.arrow_back_ios_new, color: Colors.white, size: 20),
                        onPressed: () => Navigator.pop(context),
                      ),
                      const Text(
                        'Profil',
                        style: TextStyle(
                          fontSize: 24,
                          fontWeight: FontWeight.bold,
                          color: Colors.white,
                        ),
                      ),
                      IconButton(
                        icon: const Icon(Icons.settings_outlined, color: Colors.white),
                        onPressed: () {
                          Navigator.push(
                            context,
                            MaterialPageRoute(builder: (context) => const PengaturanPage()),
                          );
                        },
                      ),
                    ],
                  ),
                ),

                const SizedBox(height: 20),

                // 3. Card Utama (Box Profil)
                Padding(
                  padding: const EdgeInsets.symmetric(horizontal: 25),
                  child: Container(
                    padding: const EdgeInsets.all(20),
                    decoration: BoxDecoration(
                      color: Colors.white,
                      borderRadius: BorderRadius.circular(25),
                      boxShadow: [
                        BoxShadow(
                          color: Colors.black.withOpacity(0.1),
                          blurRadius: 20,
                          offset: const Offset(0, 10),
                        ),
                      ],
                    ),
                    child: Column(
                      children: [
                        // Bagian Header Profil (Foto & Nama)
                        Row(
                          children: [
                            const CircleAvatar(
                              radius: 45,
                              backgroundImage: AssetImage('assets/fotoprofile.jpg'),
                            ),
                            const SizedBox(width: 15),
                            const Expanded(
                              child: Column(
                                crossAxisAlignment: CrossAxisAlignment.start,
                                children: [
                                  Text(
                                    'Muhammad Mingyu',
                                    style: TextStyle(
                                      fontSize: 18,
                                      fontWeight: FontWeight.bold,
                                      color: Colors.black,
                                    ),
                                  ),
                                  Text(
                                    'mingyuucakepmaxximal@gmail.com',
                                    style: TextStyle(
                                      fontSize: 13,
                                      color: Colors.grey,
                                      decoration: TextDecoration.underline,
                                    ),
                                  ),
                                  Text(
                                    '08981235676',
                                    style: TextStyle(
                                      fontSize: 13,
                                      color: Colors.grey,
                                    ),
                                  ),
                                ],
                              ),
                            ),
                            IconButton(
                              icon: const Icon(Icons.edit, size: 20, color: Colors.black),
                              onPressed: () {}, // Tambahkan navigasi edit jika perlu
                            ),
                          ],
                        ),

                        const SizedBox(height: 25),
                        const Divider(thickness: 1, color: Color(0xFFEEEEEE)),

                        // 4. Daftar Menu Navigasi
                        _buildMenuTile(
                          context,
                          icon: Icons.assignment_outlined,
                          title: 'Pesanan saya',
                          destination: const PesananPage(),
                        ),
                        _buildMenuTile(
                          context,
                          icon: Icons.bookmark_border,
                          title: 'Favorit',
                          destination: const FavoritPage(),
                        ),
                        _buildMenuTile(
                          context,
                          icon: Icons.help_outline,
                          title: 'Dapatkan bantuan',
                          destination: const BantuanPage(),
                        ),
                        _buildMenuTile(
                          context,
                          icon: Icons.info_outline,
                          title: 'Tentang',
                          destination: const TentangPage(),
                        ),
                        _buildMenuTile(
                          context,
                          icon: Icons.exit_to_app,
                          title: 'Keluar',
                          destination: null, // Logika logout biasanya berbeda
                          isLast: true,
                        ),
                      ],
                    ),
                  ),
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }

  // Widget Helper untuk Baris Menu
  Widget _buildMenuTile(
    BuildContext context, {
    required IconData icon,
    required String title,
    required Widget? destination,
    bool isLast = false,
  }) {
    return Column(
      children: [
        ListTile(
          contentPadding: EdgeInsets.zero,
          leading: Container(
            padding: const EdgeInsets.all(8),
            decoration: BoxDecoration(
              color: const Color(0xFFC7985F).withOpacity(0.1), // Menggunakan cokelat muda
              borderRadius: BorderRadius.circular(10),
            ),
            child: Icon(icon, color: const Color(0xFFC7985F), size: 22),
          ),
          title: Text(
            title,
            style: const TextStyle(
              fontSize: 15,
              fontWeight: FontWeight.w500,
              color: Colors.black,
            ),
          ),
          onTap: () {
            if (destination != null) {
              Navigator.push(
                context,
                MaterialPageRoute(builder: (context) => destination),
              );
            } else {
              // Contoh aksi Logout
              print("User Logout");
            }
          },
        ),
        if (!isLast)
          const Divider(thickness: 1, color: Color(0xFFEEEEEE), height: 1),
      ],
    );
  }
}