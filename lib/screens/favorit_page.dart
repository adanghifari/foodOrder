import 'package:flutter/material.dart';

class FavoritPage extends StatelessWidget {
  const FavoritPage({super.key});

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
          "Favorit Anda",
          style: TextStyle(
            color: Colors.black,
            fontWeight: FontWeight.bold,
            fontSize: 20,
          ),
        ),
        centerTitle: true,
      ),
      body: ListView(
        padding: const EdgeInsets.symmetric(horizontal: 20, vertical: 10),
        children: [
          // Item Favorit 1: Nasi Goreng
          _buildFavoriteCard(
            "Nasi Goreng",
            "Nasi Goreng dengan sayur, telur mata sapi dan kerupuk",
            "Rp 25.000",
            "assets/nasigoreng.jpg",
          ),
          const SizedBox(height: 15),
          // Item Favorit 2: Matcha Latte
          _buildFavoriteCard(
            "Matcha Latte",
            "Minuman matcha creamy dengan rasa khas teh hijau yang lembut dan menenangkan",
            "Rp 18.000",
            "assets/matchalatte.jpg",
          ),
        ],
      ),
    );
  }

  // Widget Helper untuk Kartu Favorit
  Widget _buildFavoriteCard(String title, String desc, String price, String imagePath) {
    return Container(
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(15),
        boxShadow: [
          BoxShadow(
            color: Colors.black.withOpacity(0.05),
            blurRadius: 10,
            offset: const Offset(0, 5),
          ),
        ],
      ),
      child: Padding(
        padding: const EdgeInsets.all(12.0),
        child: Row(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            // Gambar Produk
            ClipRRect(
              borderRadius: BorderRadius.circular(12),
              child: Image.asset(
                imagePath,
                width: 100,
                height: 80,
                fit: BoxFit.cover,
              ),
            ),
            const SizedBox(width: 15),
            // Detail Teks
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    title,
                    style: const TextStyle(
                      fontSize: 16,
                      fontWeight: FontWeight.bold,
                    ),
                  ),
                  const SizedBox(height: 4),
                  Text(
                    desc,
                    style: TextStyle(
                      fontSize: 12,
                      color: Colors.grey[600],
                    ),
                    maxLines: 2,
                    overflow: TextOverflow.ellipsis,
                  ),
                  const SizedBox(height: 8),
                  Text(
                    price,
                    style: const TextStyle(
                      fontSize: 14,
                      fontWeight: FontWeight.bold,
                    ),
                  ),
                ],
              ),
            ),
            // Ikon Hati Berwarna (Menandakan sudah difavoritkan)
            const Icon(
              Icons.favorite, 
              size: 18, 
              color: Color(0xFFC7985F), // Menggunakan warna cokelat muda sesuai aksen
            ),
          ],
        ),
      ),
    );
  }
}