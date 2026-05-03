import 'package:flutter/material.dart';

class BantuanPage extends StatelessWidget {
  const BantuanPage({super.key});

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
          "Ada yang bisa kami bantu?",
          style: TextStyle(
            color: Colors.black,
            fontWeight: FontWeight.bold,
            fontSize: 18,
          ),
        ),
        centerTitle: false, // Sesuai gambar, teks agak ke kiri mengikuti back button
      ),
      body: Padding(
        padding: const EdgeInsets.symmetric(vertical: 10),
        child: Column(
          children: [
            _buildHelpTile(Icons.assignment_outlined, "Saya tidak dapat memesan"),
            _buildHelpTile(Icons.restaurant_menu, "Pesanan saya"),
            _buildHelpTile(Icons.credit_card_outlined, "Informasi pembayaran"),
            _buildHelpTile(Icons.email_outlined, "Permintaan bantuan saya"),
            _buildHelpTile(Icons.card_membership_outlined, "Hadiah"),
            _buildHelpTile(Icons.info_outline, "Kebijakan pembatalan"),
          ],
        ),
      ),
    );
  }

  // Widget Helper untuk Baris Bantuan
  Widget _buildHelpTile(IconData icon, String title) {
    return Column(
      children: [
        ListTile(
          contentPadding: const EdgeInsets.symmetric(horizontal: 25, vertical: 5),
          leading: Icon(icon, color: Colors.black54, size: 28),
          title: Text(
            title,
            style: const TextStyle(
              fontSize: 15,
              fontWeight: FontWeight.w500,
              color: Colors.black87,
            ),
          ),
          trailing: const Icon(
            Icons.arrow_forward_ios,
            color: Color(0xFFC7985F), // Warna cokelat muda #C7985F
            size: 18,
          ),
          onTap: () {
            // Aksi saat menu diklik
          },
        ),
        const Divider(
          height: 1,
          thickness: 1,
          indent: 20,
          endIndent: 20,
          color: Color(0xFFEEEEEE),
        ),
      ],
    );
  }
}