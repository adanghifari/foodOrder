import 'package:flutter/material.dart';

class PengaturanPage extends StatefulWidget {
  const PengaturanPage({super.key});

  @override
  State<PengaturanPage> createState() => _PengaturanPageState();
}

class _PengaturanPageState extends State<PengaturanPage> {
  bool _isNotificationOn = true; // Status awal untuk switch notifikasi

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
          "Pengaturan",
          style: TextStyle(
            color: Colors.black,
            fontWeight: FontWeight.bold,
            fontSize: 20,
          ),
        ),
        centerTitle: true,
      ),
      body: Column(
        children: [
          const SizedBox(height: 10),
          
          // List Menu Pengaturan
          _buildMenuSetting("Informasi akun", isSwitch: false),
          _buildMenuSetting("Alamat tersimpan", isSwitch: false),
          _buildMenuSetting("Ubah Email", isSwitch: false),
          _buildMenuSetting("Ganti Password", isSwitch: false),
          
          // Menu Notifikasi dengan Switch Oranye
          _buildMenuSetting(
            "Notifikasi", 
            isSwitch: true, 
            switchValue: _isNotificationOn,
            onSwitchChanged: (value) {
              setState(() {
                _isNotificationOn = value;
              });
            },
          ),

          const SizedBox(height: 40),

          // Tombol Keluar
          TextButton(
            onPressed: () {
              // Logika logout
            },
            child: const Text(
              "Keluar",
              style: TextStyle(
                color: Colors.black,
                fontSize: 16,
                fontWeight: FontWeight.bold,
              ),
            ),
          ),
        ],
      ),
    );
  }

  // Widget Helper untuk Baris Pengaturan
  Widget _buildMenuSetting(
    String title, {
    required bool isSwitch,
    bool? switchValue,
    Function(bool)? onSwitchChanged,
  }) {
    return Padding(
      padding: const EdgeInsets.symmetric(horizontal: 20, vertical: 5),
      child: ListTile(
        contentPadding: const EdgeInsets.symmetric(horizontal: 10),
        title: Text(
          title,
          style: const TextStyle(
            fontSize: 16,
            fontWeight: FontWeight.w500,
            color: Colors.black,
          ),
        ),
        trailing: isSwitch
            ? Switch(
                value: switchValue ?? false,
                onChanged: onSwitchChanged,
                activeColor: const Color(0xFFC6620C), // Warna oranye #C6620C
                activeTrackColor: const Color(0xFFC6620C).withOpacity(0.3),
              )
            : const Icon(
                Icons.arrow_forward_ios,
                color: Colors.black,
                size: 18,
              ),
        onTap: isSwitch ? null : () {
          // Navigasi ke sub-menu jika ada
        },
      ),
    );
  }
}