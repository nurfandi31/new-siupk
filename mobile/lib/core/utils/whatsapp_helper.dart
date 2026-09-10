import 'package:flutter/services.dart';

class WhatsAppHelper {
  static String generateLink({
    required String phoneNumber,
    required String message,
  }) {
    String cleanNumber = phoneNumber.replaceAll(RegExp(r'[^\d]'), '');
    if (cleanNumber.startsWith('0')) {
      cleanNumber = '62${cleanNumber.substring(1)}';
    }

    final encodedMessage = Uri.encodeComponent(message);
    return 'https://wa.me/$cleanNumber?text=$encodedMessage';
  }

  static Future<void> copyMessageToClipboard(String message) async {
    await Clipboard.setData(ClipboardData(text: message));
  }
}
