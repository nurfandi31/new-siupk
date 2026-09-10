import 'package:flutter/material.dart';
import '../../../../core/theme/app_colors.dart';

class ChatMessage {
  final String sender; // 'user' or 'ai'
  final String text;
  final DateTime timestamp;

  ChatMessage({required this.sender, required this.text, required this.timestamp});
}

class AssistantChatPage extends StatefulWidget {
  const AssistantChatPage({super.key});

  @override
  State<AssistantChatPage> createState() => _AssistantChatPageState();
}

class _AssistantChatPageState extends State<AssistantChatPage> {
  final TextEditingController _controller = TextEditingController();
  final List<ChatMessage> _messages = [
    ChatMessage(
      sender: 'ai',
      text: 'Halo! Saya Ariel, Asisten AI SIUPK. Ada yang bisa saya bantu terkait data operasional, kas, atau portofolio pinjaman hari ini?',
      timestamp: DateTime.now(),
    ),
  ];
  bool _isTyping = false;

  void _sendMessage(String text) {
    if (text.trim().isEmpty) return;
    setState(() {
      _messages.add(ChatMessage(sender: 'user', text: text.trim(), timestamp: DateTime.now()));
      _isTyping = true;
    });
    _controller.clear();

    Future.delayed(const Duration(milliseconds: 900), () {
      if (!mounted) return;
      String reply = 'Data berhasil divalidasi dengan database tenant.';
      final lower = text.toLowerCase();
      if (lower.contains('kas') || lower.contains('saldo')) {
        reply = 'Total likuiditas kas operasional & rekening bank terpantau aman dan sinkron dengan buku kas harian.';
      } else if (lower.contains('jatuh tempo') || lower.contains('tunggakan')) {
        reply = 'Terdapat 3 kelompok dengan jadwal angsuran jatuh tempo dalam 7 hari ke depan. Peringatan WhatsApp otomatis telah disiapkan.';
      } else if (lower.contains('proposal') || lower.contains('verifikasi')) {
        reply = 'Terdapat proposal usulan pinjaman baru yang menunggu verifikasi lapangan dan persetujuan komite.';
      }

      setState(() {
        _messages.add(ChatMessage(sender: 'ai', text: reply, timestamp: DateTime.now()));
        _isTyping = false;
      });
    });
  }

  @override
  void dispose() {
    _controller.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: AppColors.surface,
      appBar: AppBar(
        title: const Row(
          children: [
            Icon(Icons.auto_awesome_rounded, size: 20, color: AppColors.primary),
            SizedBox(width: 8),
            Text('Ariel AI Assistant'),
          ],
        ),
      ),
      body: Column(
        children: [
          // Preset Prompt Suggestions
          Container(
            padding: const EdgeInsets.symmetric(vertical: 8),
            child: SingleChildScrollView(
              scrollDirection: Axis.horizontal,
              padding: const EdgeInsets.symmetric(horizontal: 16),
              child: Row(
                children: [
                  _buildPromptChip('Berapa saldo kas hari ini?'),
                  const SizedBox(width: 8),
                  _buildPromptChip('Pinjaman jatuh tempo minggu ini'),
                  const SizedBox(width: 8),
                  _buildPromptChip('Status proposal menunggu approval'),
                ],
              ),
            ),
          ),
          const Divider(height: 1, color: AppColors.outlineVariant),
          // Chat Bubbles
          Expanded(
            child: ListView.builder(
              padding: const EdgeInsets.all(16),
              itemCount: _messages.length + (_isTyping ? 1 : 0),
              itemBuilder: (context, index) {
                if (index == _messages.length && _isTyping) {
                  return Align(
                    alignment: Alignment.centerLeft,
                    child: Container(
                      margin: const EdgeInsets.only(bottom: 8),
                      padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 10),
                      decoration: BoxDecoration(
                        color: AppColors.surfaceContainerLowest,
                        borderRadius: BorderRadius.circular(12),
                        border: Border.all(color: AppColors.outlineVariant),
                      ),
                      child: const Row(
                        mainAxisSize: MainAxisSize.min,
                        children: [
                          SizedBox(width: 12, height: 12, child: CircularProgressIndicator(strokeWidth: 2)),
                          SizedBox(width: 8),
                          Text('Ariel sedang mengetik...', style: TextStyle(fontSize: 12, fontStyle: FontStyle.italic)),
                        ],
                      ),
                    ),
                  );
                }

                final msg = _messages[index];
                final isAi = msg.sender == 'ai';
                return Align(
                  alignment: isAi ? Alignment.centerLeft : Alignment.centerRight,
                  child: Container(
                    margin: const EdgeInsets.only(bottom: 10),
                    padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 10),
                    constraints: BoxConstraints(maxWidth: MediaQuery.of(context).size.width * 0.8),
                    decoration: BoxDecoration(
                      color: isAi ? AppColors.surfaceContainerLowest : AppColors.primary,
                      borderRadius: BorderRadius.circular(14),
                      border: isAi ? Border.all(color: AppColors.outlineVariant) : null,
                    ),
                    child: Text(
                      msg.text,
                      style: TextStyle(
                        fontSize: 13,
                        color: isAi ? AppColors.onSurface : Colors.white,
                        height: 1.4,
                      ),
                    ),
                  ),
                );
              },
            ),
          ),
          // Input Area
          Container(
            padding: const EdgeInsets.all(12),
            decoration: const BoxDecoration(
              color: AppColors.surfaceContainerLowest,
              border: Border(top: BorderSide(color: AppColors.outlineVariant)),
            ),
            child: SafeArea(
              child: Row(
                children: [
                  Expanded(
                    child: TextField(
                      controller: _controller,
                      onSubmitted: _sendMessage,
                      decoration: InputDecoration(
                        hintText: 'Tanyakan sesuatu kepada Ariel...',
                        hintStyle: const TextStyle(fontSize: 13),
                        contentPadding: const EdgeInsets.symmetric(horizontal: 14, vertical: 10),
                        filled: true,
                        fillColor: AppColors.surface,
                        border: OutlineInputBorder(
                          borderRadius: BorderRadius.circular(24),
                          borderSide: const BorderSide(color: AppColors.outlineVariant),
                        ),
                      ),
                    ),
                  ),
                  const SizedBox(width: 8),
                  CircleAvatar(
                    backgroundColor: AppColors.primary,
                    radius: 22,
                    child: IconButton(
                      icon: const Icon(Icons.send_rounded, size: 18, color: Colors.white),
                      onPressed: () => _sendMessage(_controller.text),
                    ),
                  ),
                ],
              ),
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildPromptChip(String prompt) {
    return ActionChip(
      label: Text(prompt, style: const TextStyle(fontSize: 11)),
      backgroundColor: AppColors.surfaceContainerLowest,
      side: const BorderSide(color: AppColors.outlineVariant),
      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(16)),
      onPressed: () => _sendMessage(prompt),
    );
  }
}
