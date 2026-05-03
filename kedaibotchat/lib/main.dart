import 'package:flutter/material.dart';

void main() {
  runApp(const MyApp());
}

class MyApp extends StatelessWidget {
  const MyApp({super.key});

  @override
  Widget build(BuildContext context) {
    return MaterialApp(
      title: 'KedaiBot',
      debugShowCheckedModeBanner: false, // Menghilangkan banner debug
      theme: ThemeData(
        useMaterial3: true,
      ),
      home: const ChatScreen(),
    );
  }
}

class ChatScreen extends StatefulWidget {
  const ChatScreen({super.key});

  @override
  State<ChatScreen> createState() => _ChatScreenState();
}

class _ChatScreenState extends State<ChatScreen> {
  // Definisi Warna sesuai permintaan
  final Color backgroundColor = const Color(0xFFFFFFFF);
  final Color chatBubbleColor = const Color(0xFFF3F3F3);
  final Color quickReplyBgColor = const Color(0xFFFFEEE1); 
  final Color quickReplyTextColor = const Color(0xFFC6620C);
  final Color userChatBubbleColor = const Color(0xFFC6620C); 
  final Color lightBrownColor = const Color(0xFFC7985F); 

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: backgroundColor,
      appBar: AppBar(
        backgroundColor: backgroundColor,
        surfaceTintColor: Colors.transparent, // Mencegah warna berubah saat scroll di Material 3
        elevation: 0,
        leading: const Icon(Icons.arrow_back, color: Colors.black),
        title: Row(
          children: [
            CircleAvatar(
              radius: 18,
              backgroundColor: Colors.white,
              child: ClipOval(
                child: Image.asset(
                  'assets/images/logokedaiklik.jpg',
                  fit: BoxFit.cover,
                  errorBuilder: (context, error, stackTrace) => 
                      const Icon(Icons.person, color: Colors.grey),
                ),
              ),
            ),
            const SizedBox(width: 10),
            const Text(
              'KedaiBot',
              style: TextStyle(color: Colors.black, fontSize: 18, fontWeight: FontWeight.bold),
            ),
          ],
        ),
        bottom: PreferredSize(
          preferredSize: const Size.fromHeight(1.0),
          child: Container(color: Colors.grey.withOpacity(0.2), height: 1.0),
        ),
      ),
      body: Column(
        children: [
          Expanded(
            child: ListView(
              padding: const EdgeInsets.all(16.0),
              children: [
                Center(
                  child: Text(
                    'Rabu 13:21',
                    style: TextStyle(color: Colors.grey[600], fontSize: 12),
                  ),
                ),
                const SizedBox(height: 16),

                // Pesan Bot 1
                Row(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    _botAvatarWidget(),
                    const SizedBox(width: 8),
                    _botChatBubble(
                      text: 'Halo, saya KedaiBot! 👋 Saya siap membantu Anda, ada yang bisa saya bantu?',
                    ),
                  ],
                ),
                const SizedBox(height: 16),

                // Quick Replies
                _quickReplyButton(text: 'Lihat Menu Favorit'),
                const SizedBox(height: 8),
                _quickReplyButton(text: 'Rekomendasi makan siang enak'),
                const SizedBox(height: 16),

                // Pesan User
                _userChatBubble(text: 'Rekomendasi makan siang enak'),
                const SizedBox(height: 16),

                // Pesan Bot 2
                Row(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    _botAvatarWidget(),
                    const SizedBox(width: 8),
                    _botChatBubble(
                      text: 'Baik, berikut beberapa rekomendasi untuk Anda :\n• Nasi Goreng Spesial\n• Ayam Geprek\n• Es Teh Manis',
                    ),
                  ],
                ),
              ],
            ),
          ),
          _chatInputWidget(),
        ],
      ),
    );
  }

  Widget _botAvatarWidget() {
    return CircleAvatar(
      radius: 18,
      backgroundColor: Colors.white,
      child: ClipOval(
        child: Image.asset(
          'assets/images/logokedaiklik.jpg',
          fit: BoxFit.cover,
          errorBuilder: (context, error, stackTrace) => 
              const Icon(Icons.person, color: Colors.grey),
        ),
      ),
    );
  }

  Widget _botChatBubble({required String text}) {
    return Container(
      constraints: BoxConstraints(maxWidth: MediaQuery.of(context).size.width * 0.75),
      padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 12),
      decoration: BoxDecoration(
        color: chatBubbleColor,
        borderRadius: const BorderRadius.only(
          topRight: Radius.circular(16),
          bottomLeft: Radius.circular(16),
          bottomRight: Radius.circular(16),
        ),
      ),
      child: Text(
        text,
        style: const TextStyle(color: Colors.black, fontSize: 14, height: 1.4),
      ),
    );
  }

  Widget _userChatBubble({required String text}) {
    return Row(
      mainAxisAlignment: MainAxisAlignment.end,
      children: [
        Container(
          constraints: BoxConstraints(maxWidth: MediaQuery.of(context).size.width * 0.75),
          padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 12),
          decoration: BoxDecoration(
            color: userChatBubbleColor,
            borderRadius: const BorderRadius.only(
              topLeft: Radius.circular(16),
              topRight: Radius.circular(16),
              bottomLeft: Radius.circular(16),
            ),
          ),
          child: Text(
            text,
            style: const TextStyle(color: Colors.white, fontSize: 14),
          ),
        ),
      ],
    );
  }

  Widget _quickReplyButton({required String text}) {
    return Row(
      mainAxisAlignment: MainAxisAlignment.end,
      children: [
        OutlinedButton(
          onPressed: () {},
          style: OutlinedButton.styleFrom(
            backgroundColor: quickReplyBgColor,
            side: BorderSide.none,
            shape: RoundedRectangleBorder( // PERBAIKAN: Gunakan RoundedRectangleBorder
              borderRadius: BorderRadius.circular(20),
            ),
            padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 10),
          ),
          child: Text(
            text,
            style: TextStyle(color: quickReplyTextColor, fontSize: 14, fontWeight: FontWeight.w500),
          ),
        ),
      ],
    );
  }

  Widget _chatInputWidget() {
    return Container(
      padding: const EdgeInsets.all(16.0),
      decoration: BoxDecoration(
        color: backgroundColor,
        boxShadow: [
          BoxShadow(color: Colors.black.withOpacity(0.05), blurRadius: 10, offset: const Offset(0, -5))
        ],
      ),
      child: Row(
        children: [
          Expanded(
            child: TextField(
              decoration: InputDecoration(
                hintText: 'Ketik Pesan...',
                contentPadding: const EdgeInsets.symmetric(horizontal: 20, vertical: 12),
                border: OutlineInputBorder(
                  borderRadius: BorderRadius.circular(30),
                  borderSide: const BorderSide(color: Colors.black),
                ),
                enabledBorder: OutlineInputBorder(
                  borderRadius: BorderRadius.circular(30),
                  borderSide: const BorderSide(color: Colors.black),
                ),
              ),
            ),
          ),
          const SizedBox(width: 12),
          Container(
            padding: const EdgeInsets.all(12),
            decoration: BoxDecoration(
              color: userChatBubbleColor,
              shape: BoxShape.circle,
            ),
            child: const Icon(Icons.send_rounded, color: Colors.white),
          ),
        ],
      ),
    );
  }
}