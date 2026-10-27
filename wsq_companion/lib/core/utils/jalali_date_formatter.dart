import 'number_formatter.dart';

class JalaliDateFormatter {
  /// Converts a Gregorian DateTime to a Jalali formatted date string (YYYY/MM/DD).
  static String formatJalali(DateTime date) {
    final jalali = _gregorianToJalali(date.year, date.month, date.day);
    final y = jalali[0].toString().padLeft(4, '0');
    final m = jalali[1].toString().padLeft(2, '0');
    final d = jalali[2].toString().padLeft(2, '0');
    return NumberFormatter.toPersianDigits('$y/$m/$d');
  }

  static List<int> _gregorianToJalali(int gy, int gm, int gd) {
    final gDM = [0, 31, 59, 90, 120, 151, 181, 212, 243, 273, 304, 334];
    final gy2 = (gm > 2) ? (gy + 1) : gy;
    var days = 355666 +
        (365 * gy) +
        ((gy2 + 3) ~/ 4) -
        ((gy2 + 99) ~/ 100) +
        ((gy2 + 399) ~/ 400) +
        gd +
        gDM[gm - 1];
    var jy = -1595 + (33 * (days ~/ 12053));
    days %= 12053;
    jy += 4 * (days ~/ 1461);
    days %= 1461;
    if (days > 365) {
      jy += (days - 1) ~/ 365;
      days = (days - 1) % 365;
    }
    final int jm;
    final int jd;
    if (days < 186) {
      jm = 1 + (days ~/ 31);
      jd = 1 + (days % 31);
    } else {
      jm = 7 + ((days - 186) ~/ 30);
      jd = 1 + ((days - 186) % 30);
    }
    return [jy, jm, jd];
  }
}
