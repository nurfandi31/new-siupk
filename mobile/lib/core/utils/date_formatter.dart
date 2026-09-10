import 'package:intl/intl.dart';

class DateFormatter {
  static String formatIndonesian(dynamic date) {
    if (date == null) return '-';
    DateTime? dt;
    if (date is DateTime) {
      dt = date;
    } else if (date is String) {
      dt = DateTime.tryParse(date);
    }
    if (dt == null) return date.toString();

    return DateFormat('d MMMM yyyy', 'id_ID').format(dt);
  }

  static String formatShort(dynamic date) {
    if (date == null) return '-';
    DateTime? dt;
    if (date is DateTime) {
      dt = date;
    } else if (date is String) {
      dt = DateTime.tryParse(date);
    }
    if (dt == null) return date.toString();

    return DateFormat('dd/MM/yyyy').format(dt);
  }
}
