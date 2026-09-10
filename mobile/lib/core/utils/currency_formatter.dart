import 'package:intl/intl.dart';

class CurrencyFormatter {
  static final NumberFormat _rupiahFormat = NumberFormat.currency(
    locale: 'id_ID',
    symbol: 'Rp ',
    decimalDigits: 0,
  );

  static String format(dynamic amount) {
    if (amount == null) return 'Rp 0';
    final numValue = num.tryParse(amount.toString()) ?? 0;
    return _rupiahFormat.format(numValue);
  }

  static String formatRupiah(dynamic amount) => format(amount);

  static double parse(String formatted) {
    final cleaned = formatted.replaceAll(RegExp(r'[^\d]'), '');
    return double.tryParse(cleaned) ?? 0.0;
  }
}
