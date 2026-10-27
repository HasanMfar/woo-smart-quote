import 'package:flutter_test/flutter_test.dart';
import 'package:wsq_companion/core/utils/number_formatter.dart';
import 'package:wsq_companion/core/utils/jalali_date_formatter.dart';
import 'package:wsq_companion/features/dashboard/models/quote_summary.dart';
import 'package:wsq_companion/features/quote_details/models/quote_detail.dart';

void main() {
  group('NumberFormatter Tests', () {
    test('Converts English digits to Persian digits correctly', () {
      expect(NumberFormatter.toPersianDigits('1234567890'), '۱۲۳۴۵۶۷۸۹۰');
    });

    test('Formats currency with thousand separators and Persian digits', () {
      expect(NumberFormatter.formatCurrency(12500000), '۱۲,۵۰۰,۰۰۰ تومان');
    });

    test('Parses Persian digit strings back to double', () {
      expect(NumberFormatter.parsePersianDouble('۱۲,۵۰۰,۰۰۰'), 12500000.0);
    });
  });

  group('JalaliDateFormatter Tests', () {
    test('Converts known Gregorian dates to Jalali correctly', () {
      final date = DateTime(2026, 10, 25);
      final jalali = JalaliDateFormatter.formatJalali(date);
      expect(jalali, '۱۴۰۵/۰۸/۰۳');
    });
  });

  group('Quote Models Tests', () {
    test('QuoteSummary parses correctly from JSON', () {
      final json = {
        'id': 101,
        'number': '101',
        'status': 'quote-request',
        'status_label': 'در انتظار بررسی',
        'created_at_jalali': '۱۴۰۵/۰۸/۰۳',
        'customer': {
          'name': 'علی رضایی',
          'phone': '09121111111',
        },
        'items_count': 3,
        'total': 4500000.0,
        'total_formatted': '۴,۵۰۰,۰۰۰ تومان',
        'validity': '۲۴ ساعت کاری',
      };
      final summary = QuoteSummary.fromJson(json);
      expect(summary.id, 101);
      expect(summary.isPending, isTrue);
      expect(summary.isPriced, isFalse);
      expect(summary.customerName, 'علی رضایی');
    });

    test('QuoteDetail parses pricing_history correctly', () {
      final json = {
        'id': 202,
        'number': '202',
        'status': 'quote-sent',
        'status_label': 'پیش‌فاکتور صادر شد',
        'created_at_jalali': '۱۴۰۵/۰۸/۰۳',
        'customer': {
          'name': 'مهدی احمدی',
          'phone': '09122222222',
          'email': 'mehdi@example.test',
          'note': 'ارسال فوری لطفاً',
        },
        'items': [
          {
            'item_id': 10,
            'product_id': 50,
            'variation_id': 0,
            'name': 'کابل شبکه Cat6',
            'quantity': 5,
            'unit_price': 100000.0,
            'discount_percent': 10.0,
            'subtotal': 500000.0,
            'total': 450000.0,
            'image_url': '',
            'sku': 'CBL-06',
          }
        ],
        'total': 450000.0,
        'total_formatted': '۴۵۰,۰۰۰ تومان',
        'currency': 'تومان',
        'validity': '۴۸ ساعت کاری',
        'payment_url': 'https://example.test/pay/202',
        'pdf_url': 'https://example.test/quote-202.pdf',
        'order_notes': [
          {'id': 1, 'content': 'ثبت استعلام', 'date': '2026-10-25 14:00', 'customer_note': false}
        ],
        'pricing_history': [
          {
            'timestamp': 1761400000,
            'date': '۱۴۰۵/۰۸/۰۳',
            'total': 450000.0,
            'total_formatted': '۴۵۰,۰۰۰ تومان',
            'validity': '۴۸ ساعت کاری',
            'admin_note': 'تخفیف ۱۰ درصدی',
            'author': 'مدیر فروشگاه',
            'items_count': 1,
          }
        ],
      };

      final detail = QuoteDetail.fromJson(json);
      expect(detail.id, 202);
      expect(detail.isPriced, isTrue);
      expect(detail.isPending, isFalse);
      expect(detail.pricingHistory.length, 1);
      expect(detail.pricingHistory.first['admin_note'], 'تخفیف ۱۰ درصدی');
      expect(detail.items.first.total, 450000.0);
    });
  });
}
