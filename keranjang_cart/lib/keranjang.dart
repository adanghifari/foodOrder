import 'package:flutter/material.dart';

class KeranjangPage extends StatefulWidget {
  const KeranjangPage({super.key});

  @override
  State<KeranjangPage> createState() => _KeranjangPageState();
}

class _KeranjangPageState extends State<KeranjangPage> {
  static const Color lightBrownColor = Color(0xFFC7985F);
  static const Color orangeColor = Color(0xFFC6620C);
  static const Color whiteColor = Color(0xFFFFFFFF);

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: whiteColor,
      appBar: AppBar(
        // Perbaikan: Navigator.pop hanya jika ada halaman sebelumnya, 
        // tapi di sini kita biarkan untuk konsistensi UI
        leading: IconButton(
          icon: const Icon(Icons.arrow_back_ios_new, color: Colors.black, size: 20),
          onPressed: () => Navigator.maybePop(context),
        ),
        title: const Text(
          'Pesanan saya',
          style: TextStyle(
            color: Colors.black,
            fontWeight: FontWeight.bold,
            fontSize: 20,
          ),
        ),
        centerTitle: true,
        backgroundColor: whiteColor,
        elevation: 0,
      ),
      body: SingleChildScrollView(
        padding: const EdgeInsets.symmetric(horizontal: 20.0, vertical: 10),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            _buildOrderItem(
              imagePath: 'assets/nasigoreng.jpg',
              name: 'Nasi Goreng',
              description: 'Nasi Goreng dengan sayur, telur mata sapi dan kerupuk',
              price: 25000,
              quantity: 2,
            ),
            const SizedBox(height: 16),
            _buildOrderItem(
              imagePath: 'assets/matchalatte.jpg',
              name: 'Matcha Latte',
              description: 'Minuman matcha creamy dengan rasa khas teh hijau yang lembut',
              price: 18000,
              quantity: 2,
            ),
            const SizedBox(height: 25),
            _buildLabel('Email'),
            _buildTextField(hintText: 'Email'),
            const SizedBox(height: 15),
            _buildLabel('Nama Pemesan'),
            _buildTextField(hintText: 'Nama Pemesan'),
            const SizedBox(height: 30),
            const Text(
              'Detail Pembayaran',
              style: TextStyle(fontSize: 18, fontWeight: FontWeight.bold),
            ),
            const SizedBox(height: 15),
            _buildPaymentRow('Subtotal', 86000),
            _buildPaymentRow('Biaya Layanan', 5000),
            const Padding(
              padding: EdgeInsets.symmetric(vertical: 8.0),
              child: Divider(thickness: 1),
            ),
            _buildPaymentRow('Total Pembayaran', 91000, isTotal: true),
            const SizedBox(height: 40),
            Row(
              children: [
                Expanded(
                  child: OutlinedButton(
                    onPressed: () {},
                    style: OutlinedButton.styleFrom(
                      side: const BorderSide(color: lightBrownColor),
                      padding: const EdgeInsets.symmetric(vertical: 15),
                      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
                    ),
                    child: const Text('Tambah Item', 
                      style: TextStyle(color: lightBrownColor, fontWeight: FontWeight.bold)),
                  ),
                ),
                const SizedBox(width: 15),
                Expanded(
                  child: ElevatedButton(
                    onPressed: () {
                      // Perbaikan: Tambahkan Navigasi ke Pembayaran
                      Navigator.pushNamed(context, '/pembayaran');
                    },
                    style: ElevatedButton.styleFrom(
                      backgroundColor: lightBrownColor,
                      padding: const EdgeInsets.symmetric(vertical: 15),
                      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
                    ),
                    child: const Text('Bayar', 
                      style: TextStyle(color: Colors.white, fontWeight: FontWeight.bold)),
                  ),
                ),
              ],
            ),
            const SizedBox(height: 20),
          ],
        ),
      ),
    );
  }

  Widget _buildLabel(String text) {
    return Padding(
      padding: const EdgeInsets.only(bottom: 8.0),
      child: Text(text, style: const TextStyle(fontSize: 14, fontWeight: FontWeight.w500)),
    );
  }

  Widget _buildTextField({required String hintText}) {
    return Container(
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(12),
        border: Border.all(color: Colors.grey.shade200),
      ),
      child: TextField(
        decoration: InputDecoration(
          hintText: hintText,
          hintStyle: TextStyle(color: Colors.grey.shade400),
          contentPadding: const EdgeInsets.symmetric(horizontal: 15, vertical: 15),
          border: InputBorder.none,
        ),
      ),
    );
  }

  Widget _buildOrderItem({
    required String imagePath,
    required String name,
    required String description,
    required double price,
    required int quantity,
  }) {
    return Container(
      padding: const EdgeInsets.all(10),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(15),
        boxShadow: [
          BoxShadow(color: Colors.black.withOpacity(0.05), blurRadius: 10, offset: const Offset(0, 5))
        ],
      ),
      child: Row(
        children: [
          ClipRRect(
            borderRadius: BorderRadius.circular(10),
            child: Image.asset(imagePath, width: 80, height: 80, fit: BoxFit.cover),
          ),
          const SizedBox(width: 12),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(name, style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 16)),
                Text(description, 
                  style: const TextStyle(fontSize: 11, color: Colors.grey),
                  maxLines: 2, overflow: TextOverflow.ellipsis),
                const SizedBox(height: 8),
                Row(
                  mainAxisAlignment: MainAxisAlignment.spaceBetween,
                  children: [
                    Text('Rp ${price.toInt()}', style: const TextStyle(fontWeight: FontWeight.bold)),
                    Row(
                      children: [
                        _qtyBtn(Icons.remove),
                        Padding(
                          padding: const EdgeInsets.symmetric(horizontal: 10),
                          child: Text('$quantity', style: const TextStyle(fontWeight: FontWeight.bold)),
                        ),
                        _qtyBtn(Icons.add),
                      ],
                    )
                  ],
                )
              ],
            ),
          )
        ],
      ),
    );
  }

  Widget _qtyBtn(IconData icon) {
    return Container(
      padding: const EdgeInsets.all(4),
      decoration: BoxDecoration(color: lightBrownColor, borderRadius: BorderRadius.circular(6)),
      child: Icon(icon, color: Colors.white, size: 16),
    );
  }

  Widget _buildPaymentRow(String label, double amount, {bool isTotal = false}) {
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 4),
      child: Row(
        mainAxisAlignment: MainAxisAlignment.spaceBetween,
        children: [
          Text(label, style: TextStyle(
            fontSize: isTotal ? 16 : 14, 
            fontWeight: isTotal ? FontWeight.bold : FontWeight.normal
          )),
          Text('Rp ${amount.toInt()}', style: TextStyle(
            fontSize: isTotal ? 16 : 14, 
            fontWeight: isTotal ? FontWeight.bold : FontWeight.normal
          )),
        ],
      ),
    );
  }
}