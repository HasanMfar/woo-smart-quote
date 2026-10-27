import 'package:intl/intl.dart';

class NumberFormatter {
  static const List<String> _persianDigits = [
    '۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹'
  ];

  static String toPersianDigits(dynamic input) {
    if (input == null) return '';
    final str = input.toString();
    var result = '';
    for (var i = 0; i < str.length; i++) {
      final char = str[i];
      final digit = int.tryParse(char);
      if (digit != null && digit >= 0 && digit <= 9) {
        result += _persianDigits[digit];
      } else {
        result += char;
      }
    }
    return result;
  }

  static String formatCurrency(num amount, {String currency = 'تومان'}) {
    final formatter = NumberFormat('#,###');
    final formatted = formatter.format(amount);
    return '${toPersianDigits(formatted)} $currency';
  }

  static double? parsePersianDouble(String text) {
    var ascii = text;
    for (var i = 0; i < 10; i++) {
      ascii = ascii.replaceAll(_persianDigits[i], i.toString());
    }
    // Remove thousand separators
    ascii = ascii.replaceAll(',', '').replaceAll('٬', '').trim();
    return double.tryParse(ascii);
  }
}
