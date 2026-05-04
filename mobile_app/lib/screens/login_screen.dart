import 'package:flutter/material.dart';
import 'register_screen.dart';

class LoginScreen extends StatefulWidget {
  const LoginScreen({super.key});

  @override
  State<LoginScreen> createState() => _LoginScreenState();
}

class _LoginScreenState extends State<LoginScreen> {
  bool isObscure = true;
  bool remember = false;

  static const Color primaryBrown = Color(0xFFA0522D);
  static const Color lightOrange = Color(0xFFF5A623);

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: Colors.white,
      body: SingleChildScrollView(
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            _buildHeader(context),
            Padding(
              padding: const EdgeInsets.symmetric(horizontal: 24),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  const SizedBox(height: 24),
                  _buildTab(context),
                  const SizedBox(height: 28),
                  _buildLabel("Email"),
                  const SizedBox(height: 6),
                  _buildInput(hint: "adann@gmail.com", keyboardType: TextInputType.emailAddress),
                  const SizedBox(height: 18),
                  _buildLabel("Password"),
                  const SizedBox(height: 6),
                  _buildPasswordInput(),
                  const SizedBox(height: 12),
                  Row(
                    children: [
                      SizedBox(
                        width: 20,
                        height: 20,
                        child: Checkbox(
                          value: remember,
                          activeColor: primaryBrown,
                          side: const BorderSide(color: Colors.grey),
                          shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(3)),
                          onChanged: (val) => setState(() => remember = val!),
                        ),
                      ),
                      const SizedBox(width: 8),
                      const Text("Remember me", style: TextStyle(fontSize: 13, color: Colors.black54)),
                      const Spacer(),
                      GestureDetector(
                        onTap: () {},
                        child: const Text(
                          "Forgot Password ?",
                          style: TextStyle(fontSize: 13, color: Color(0xFF1565C0), fontWeight: FontWeight.w500),
                        ),
                      ),
                    ],
                  ),
                  const SizedBox(height: 28),
                  _buildButton(),
                  const SizedBox(height: 40),
                ],
              ),
            ),
          ],
        ),
      ),
    );
  }

  // ===== HEADER =====
  Widget _buildHeader(BuildContext context) {
    final double screenWidth = MediaQuery.of(context).size.width;
    return SizedBox(
      height: 220,
      width: double.infinity,
      child: Container(
        width: double.infinity,
        height: 220,
        decoration: const BoxDecoration(
          gradient: LinearGradient(
            colors: [Color(0xFFD9A066), Color(0xFFF4F4F4)],
            begin: Alignment.topCenter,
            end: Alignment.bottomCenter,
          ),
        ),
        child: Center(
          child: Padding(
            padding: const EdgeInsets.only(bottom: 10),
            child: Image.asset("assets/logo.png", width: screenWidth * 0.80),
          ),
        ),
      ),
    );
  }

  // ===== TAB =====
  Widget _buildTab(BuildContext context) {
    return Container(
      height: 48,
      decoration: BoxDecoration(
        color: Colors.grey.shade100,
        borderRadius: BorderRadius.circular(10),
        border: Border.all(color: Colors.grey.shade300),
      ),
      child: Row(
        children: [
          Expanded(
            child: Container(
              margin: const EdgeInsets.all(4),
              decoration: BoxDecoration(
                color: Colors.white,
                borderRadius: BorderRadius.circular(8),
                boxShadow: [BoxShadow(color: Colors.black.withOpacity(0.06), blurRadius: 6, offset: const Offset(0, 2))],
              ),
              child: const Center(child: Text("Masuk", style: TextStyle(fontWeight: FontWeight.w600, fontSize: 14))),
            ),
          ),
          Expanded(
            child: GestureDetector(
              onTap: () => Navigator.push(context, MaterialPageRoute(builder: (_) => const RegisterScreen())),
              child: const Center(child: Text("Daftar", style: TextStyle(fontSize: 14, color: Colors.grey))),
            ),
          ),
        ],
      ),
    );
  }

  // ===== LABEL =====
  Widget _buildLabel(String text) {
    return Text(text, style: const TextStyle(fontSize: 13, fontWeight: FontWeight.w500, color: Colors.black54));
  }

  // ===== INPUT =====
  Widget _buildInput({required String hint, TextInputType keyboardType = TextInputType.text}) {
    return Container(
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(8),
        border: Border.all(color: Colors.grey.shade400),
      ),
      child: TextField(
        keyboardType: keyboardType,
        style: const TextStyle(fontSize: 14),
        decoration: InputDecoration(
          hintText: hint,
          hintStyle: TextStyle(color: Colors.grey.shade400, fontSize: 14),
          border: InputBorder.none,
          contentPadding: const EdgeInsets.symmetric(horizontal: 16, vertical: 14),
        ),
      ),
    );
  }

  // ===== PASSWORD =====
  Widget _buildPasswordInput() {
    return Container(
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(8),
        border: Border.all(color: Colors.grey.shade400),
      ),
      child: TextField(
        obscureText: isObscure,
        style: const TextStyle(fontSize: 14),
        decoration: InputDecoration(
          hintText: "••••••••",
          hintStyle: TextStyle(color: Colors.grey.shade400, fontSize: 14),
          border: InputBorder.none,
          contentPadding: const EdgeInsets.symmetric(horizontal: 16, vertical: 14),
          suffixIcon: IconButton(
            icon: Icon(isObscure ? Icons.visibility_off_outlined : Icons.visibility_outlined, color: Colors.grey, size: 20),
            onPressed: () => setState(() => isObscure = !isObscure),
          ),
        ),
      ),
    );
  }

  // ===== BUTTON =====
  Widget _buildButton() {
    return SizedBox(
      width: double.infinity,
      height: 52,
      child: ElevatedButton(
        onPressed: () {},
        style: ElevatedButton.styleFrom(
          backgroundColor: primaryBrown,
          foregroundColor: Colors.white,
          elevation: 2,
          shadowColor: primaryBrown.withOpacity(0.4),
          shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(10)),
        ),
        child: const Text("Log In", style: TextStyle(fontSize: 16, fontWeight: FontWeight.w600, letterSpacing: 0.5)),
      ),
    );
  }
}

// ===== ORANGE WAVE CLIPPER =====
// Kiri rendah → melengkung naik ke kanan (sesuai referensi Image 2)
class _OrangeWaveClipper extends CustomClipper<Path> {
  @override
  Path getClip(Size size) {
    final path = Path();

    // Mulai pojok kiri bawah
    path.moveTo(0, size.height);

    // Kiri: mulai agak ke bawah
    path.lineTo(0, size.height * 0.65);

    // Kurva naik dari kiri-bawah ke kanan-atas
    path.quadraticBezierTo(
      size.width * 0.45, // control point tengah
      size.height * -0.2, // tarikan ke atas
      size.width, // ujung kanan
      size.height * 0.2, // kanan lebih tinggi
    );

    // Pojok kanan bawah
    path.lineTo(size.width, size.height);

    path.close();
    return path;
  }

  @override
  bool shouldReclip(_OrangeWaveClipper oldClipper) => false;
}