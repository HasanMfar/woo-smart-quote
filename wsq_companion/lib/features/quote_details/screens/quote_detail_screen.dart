import 'package:dio/dio.dart';
import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import '../../../core/constants/app_colors.dart';
import '../../../core/network/api_client.dart';
import '../../../core/network/api_endpoints.dart';
import '../../../core/utils/number_formatter.dart';
import '../../../core/widgets/wsq_empty_state.dart';
import '../models/quote_detail.dart';
import 'pricing_desk_screen.dart';
import '../../pdf_preview/screens/pdf_preview_screen.dart';

class QuoteDetailScreen extends StatefulWidget {
  final int quoteId;

  const QuoteDetailScreen({super.key, required this.quoteId});

  @override
  State<QuoteDetailScreen> createState() => _QuoteDetailScreenState();
}

class _QuoteDetailScreenState extends State<QuoteDetailScreen> {
  final ApiClient _apiClient = ApiClient();
  QuoteDetail? _quote;
  bool _isLoading = true;
  bool _isNotifying = false;

  @override
  void initState() {
    super.initState();
    _fetchQuote();
  }

  Future<void> _fetchQuote() async {
    setState(() => _isLoading = true);
    try {
      final res = await _apiClient.get(ApiEndpoints.quoteDetail(widget.quoteId));
      if (!mounted) return;
      if (res.statusCode == 200 && res.data != null) {
        setState(() {
          _quote = QuoteDetail.fromJson(res.data as Map<String, dynamic>);
          _isLoading = false;
        });
      }
    } catch (e) {
      if (!mounted) return;
      setState(() => _isLoading = false);
    }
  }

  Future<void> _notifyCustomer() async {
    if (_quote == null || _isNotifying) return;
    setState(() => _isNotifying = true);

    try {
      final res = await _apiClient.post(
        ApiEndpoints.quoteNotify(widget.quoteId),
        data: {'channel': 'all'},
      );

      if (!mounted) return;
      setState(() => _isNotifying = false);

      if (res.statusCode == 200 && res.data['success'] == true) {
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(
            content: Text('اعلان با موفقیت برای خریدار ارسال گردید.'),
            backgroundColor: AppColors.completed,
          ),
        );
        _fetchQuote();
      } else {
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(content: Text('خطا در ارسال اعلان خریدار'), backgroundColor: AppColors.error),
        );
      }
    } catch (e) {
      if (!mounted) return;
      setState(() => _isNotifying = false);
      final errorMsg = e is DioException && e.response?.statusCode != null
          ? 'خطا در ارتباط با سرور (کد ${e.response?.statusCode})'
          : 'خطا در ارسال اعلان خریدار. لطفاً اتصال اینترنت خود را بررسی کنید.';
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text(errorMsg), backgroundColor: AppColors.error),
      );
    }
  }

  @override
  Widget build(BuildContext context) {
    return Directionality(
      textDirection: TextDirection.rtl,
      child: Scaffold(
        appBar: AppBar(
          title: Text(_quote != null
              ? 'استعلام #${NumberFormatter.toPersianDigits(_quote!.number)}'
              : 'جزئیات استعلام'),
          actions: [
            IconButton(
              icon: const Icon(Icons.refresh),
              onPressed: _fetchQuote,
            ),
          ],
        ),
        body: _isLoading
            ? const Center(child: CircularProgressIndicator())
            : _quote == null
                ? WsqEmptyState(
                    icon: Icons.receipt_long_outlined,
                    title: 'استعلام یافت نشد',
                    message: 'اطلاعات این استعلام در دسترس نیست یا ممکن است حذف شده باشد.',
                    actionLabel: 'تلاش مجدد',
                    actionIcon: Icons.refresh,
                    onAction: _fetchQuote,
                    isError: true,
                  )
                : SingleChildScrollView(
                    padding: const EdgeInsets.all(16),
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        // Status & Customer Card
                        _buildCustomerCard(),
                        const SizedBox(height: 14),

                        // Action Buttons Bar
                        _buildActionRow(),
                        const SizedBox(height: 16),

                        // Items List
                        const Text(
                          'اقلام درخواستی خریدار:',
                          style: TextStyle(fontWeight: FontWeight.bold, fontSize: 16),
                        ),
                        const SizedBox(height: 8),
                        ..._quote!.items.map((it) => _buildItemTile(it)),
                        const SizedBox(height: 14),

                        // Financial Summary
                        _buildFinancialSummary(),
                        const SizedBox(height: 16),

                        // Pricing History
                        if (_quote!.pricingHistory.isNotEmpty) ...[
                          const Text(
                            'سوابق و تغییرات قیمت‌گذاری:',
                            style: TextStyle(fontWeight: FontWeight.bold, fontSize: 15),
                          ),
                          const SizedBox(height: 8),
                          ..._quote!.pricingHistory.map((h) => _buildPricingHistoryCard(h)),
                          const SizedBox(height: 16),
                        ],

                        // Order Notes History
                        if (_quote!.orderNotes.isNotEmpty) ...[
                          const Text(
                            'تاریخچه و یادداشت‌های استعلام:',
                            style: TextStyle(fontWeight: FontWeight.bold, fontSize: 15),
                          ),
                          const SizedBox(height: 8),
                          ..._quote!.orderNotes.map((n) => _buildNoteCard(n)),
                        ],
                      ],
                    ),
                  ),
      ),
    );
  }

  Widget _buildCustomerCard() {
    return Card(
      child: Padding(
        padding: const EdgeInsets.all(16),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              mainAxisAlignment: MainAxisAlignment.spaceBetween,
              children: [
                Text(
                  _quote!.customerName,
                  style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 16),
                ),
                Container(
                  padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
                  decoration: BoxDecoration(
                    color: AppColors.statusBadgeBg(context, isPending: _quote!.isPending),
                    borderRadius: BorderRadius.circular(6),
                  ),
                  child: Text(
                    _quote!.statusLabel,
                    style: TextStyle(
                      color: _quote!.isPending ? AppColors.pending : AppColors.priced,
                      fontWeight: FontWeight.bold,
                      fontSize: 12,
                    ),
                  ),
                ),
              ],
            ),
            const Divider(height: 20),

            _buildInfoRow(Icons.phone, 'شماره تماس خریدار:', NumberFormatter.toPersianDigits(_quote!.customerPhone)),
            if (_quote!.customerEmail.isNotEmpty)
              _buildInfoRow(Icons.email_outlined, 'ایمیل:', _quote!.customerEmail),
            _buildInfoRow(Icons.calendar_today, 'تاریخ ثبت استعلام:', _quote!.createdAtJalali),
            _buildInfoRow(Icons.timer_outlined, 'مهلت اعتبار:', _quote!.validity),

            if (_quote!.customerNote.isNotEmpty) ...[
              const SizedBox(height: 8),
              Container(
                width: double.infinity,
                padding: const EdgeInsets.all(10),
                decoration: BoxDecoration(
                  color: AppColors.recessedBg(context),
                  borderRadius: BorderRadius.circular(8),
                ),
                child: Text(
                  'توضیح خریدار: ${_quote!.customerNote}',
                  style: TextStyle(
                    fontSize: 12,
                    color: Theme.of(context).textTheme.bodyMedium?.color ?? AppColors.textSecondary,
                  ),
                ),
              ),
            ],
          ],
        ),
      ),
    );
  }

  Widget _buildInfoRow(IconData icon, String label, String value) {
    final isDark = Theme.of(context).brightness == Brightness.dark;
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 4),
      child: Row(
        children: [
          Icon(icon, size: 16, color: AppColors.textMuted),
          const SizedBox(width: 6),
          Text(label, style: TextStyle(color: isDark ? AppColors.darkTextSecondary : AppColors.textSecondary, fontSize: 13)),
          const SizedBox(width: 6),
          Text(value, style: TextStyle(fontWeight: FontWeight.bold, fontSize: 13, color: Theme.of(context).textTheme.bodyLarge?.color)),
        ],
      ),
    );
  }

  Widget _buildActionRow() {
    return Column(
      children: [
        if (_quote!.isPending)
          SizedBox(
            width: double.infinity,
            height: 48, // 48dp Android Touch Target
            child: ElevatedButton.icon(
              style: ElevatedButton.styleFrom(backgroundColor: AppColors.primary),
              onPressed: () async {
                HapticFeedback.lightImpact();
                final updated = await Navigator.push(
                  context,
                  MaterialPageRoute(builder: (_) => PricingDeskScreen(quoteId: _quote!.id)),
                );
                if (updated == true) _fetchQuote();
              },
              icon: const Icon(Icons.edit_note),
              label: const Text('ورود به میز کار قیمت‌گذاری (تعیین قیمت)'),
            ),
          )
        else ...[
          Row(
            children: [
              Expanded(
                child: SizedBox(
                  height: 48,
                  child: ElevatedButton.icon(
                    style: ElevatedButton.styleFrom(backgroundColor: AppColors.primary),
                    onPressed: () {
                      HapticFeedback.lightImpact();
                      Navigator.push(
                        context,
                        MaterialPageRoute(
                          builder: (_) => PdfPreviewScreen(
                            quoteId: _quote!.id,
                            quoteNumber: _quote!.number,
                            pdfUrl: _quote!.pdfUrl,
                          ),
                        ),
                      );
                    },
                    icon: const Icon(Icons.picture_as_pdf, size: 18),
                    label: const Text('مشاهده PDF'),
                  ),
                ),
              ),
              const SizedBox(width: 10),
              Expanded(
                child: SizedBox(
                  height: 48,
                  child: ElevatedButton.icon(
                    style: ElevatedButton.styleFrom(backgroundColor: AppColors.accent),
                    onPressed: _isNotifying ? null : () {
                      HapticFeedback.mediumImpact();
                      _notifyCustomer();
                    },
                    icon: _isNotifying
                        ? const SizedBox(width: 18, height: 18, child: CircularProgressIndicator(color: Colors.white, strokeWidth: 2))
                        : const Icon(Icons.send_rounded, size: 18),
                    label: const Text('ارسال به خریدار'),
                  ),
                ),
              ),
            ],
          ),
          const SizedBox(height: 10),
          SizedBox(
            width: double.infinity,
            height: 48, // 48dp Android Touch Target
            child: OutlinedButton.icon(
              onPressed: () async {
                HapticFeedback.lightImpact();
                final updated = await Navigator.push(
                  context,
                  MaterialPageRoute(builder: (_) => PricingDeskScreen(quoteId: _quote!.id)),
                );
                if (updated == true) _fetchQuote();
              },
              icon: const Icon(Icons.edit_note, size: 18),
              label: const Text('ویرایش مجدد قیمت‌ها و تخفیف‌ها'),
            ),
          ),
        ],
      ],
    );
  }

  Widget _buildItemTile(QuoteItem item) {
    return Card(
      margin: const EdgeInsets.only(bottom: 8),
      child: ListTile(
        leading: item.imageUrl.isNotEmpty
            ? ClipRRect(
                borderRadius: BorderRadius.circular(6),
                child: Image.network(
                  item.imageUrl,
                  width: 44,
                  height: 44,
                  fit: BoxFit.cover,
                  errorBuilder: (_, __, ___) => Icon(
                    Icons.inventory_2_outlined,
                    color: Theme.of(context).brightness == Brightness.dark
                        ? AppColors.darkTextMuted
                        : AppColors.textMuted,
                  ),
                ),
              )
            : Icon(
                Icons.inventory_2_outlined,
                color: Theme.of(context).brightness == Brightness.dark
                    ? AppColors.darkTextMuted
                    : AppColors.textMuted,
              ),
        title: Text(item.name, style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 13)),
        subtitle: Text('تعداد: ${NumberFormatter.toPersianDigits(item.quantity)} × ${NumberFormatter.formatCurrency(item.unitPrice)}'),
        trailing: Text(
          NumberFormatter.formatCurrency(item.total),
          style: TextStyle(fontWeight: FontWeight.bold, color: AppColors.priceColor(context)),
        ),
      ),
    );
  }

  Widget _buildFinancialSummary() {
    return Container(
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        color: AppColors.cardBg(context),
        borderRadius: BorderRadius.circular(12),
        border: Border.all(color: AppColors.border(context)),
      ),
      child: Row(
        mainAxisAlignment: MainAxisAlignment.spaceBetween,
        children: [
          const Text('جمع کل نهایی استعلام:', style: TextStyle(fontWeight: FontWeight.bold, fontSize: 14)),
          Text(
            NumberFormatter.formatCurrency(_quote!.total),
            style: TextStyle(fontWeight: FontWeight.bold, fontSize: 17, color: AppColors.priceColor(context)),
          ),
        ],
      ),
    );
  }

  Widget _buildNoteCard(Map<String, dynamic> note) {
    final content = note['content']?.toString() ?? '';
    final date = note['date']?.toString() ?? '';
    final isDark = Theme.of(context).brightness == Brightness.dark;
    return Container(
      width: double.infinity,
      margin: const EdgeInsets.only(bottom: 8),
      padding: const EdgeInsets.all(10),
      decoration: BoxDecoration(
        color: AppColors.recessedBg(context),
        borderRadius: BorderRadius.circular(8),
        border: Border.all(color: AppColors.border(context)),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(
            content,
            style: TextStyle(
              fontSize: 12,
              color: isDark ? AppColors.darkTextPrimary : AppColors.textPrimary,
            ),
          ),
          if (date.isNotEmpty)
            Padding(
              padding: const EdgeInsets.only(top: 4),
              child: Text(
                date,
                style: TextStyle(
                  fontSize: 10,
                  color: isDark ? AppColors.darkTextMuted : AppColors.textMuted,
                ),
              ),
            ),
        ],
      ),
    );
  }

  Widget _buildPricingHistoryCard(Map<String, dynamic> history) {
    final date = history['date']?.toString() ?? '';
    final totalFormatted = history['total_formatted']?.toString() ?? '';
    final author = history['author']?.toString() ?? 'مدیر فروشگاه';
    final note = history['admin_note']?.toString() ?? '';
    final validity = history['validity']?.toString() ?? '';
    final isDark = Theme.of(context).brightness == Brightness.dark;

    return Container(
      width: double.infinity,
      margin: const EdgeInsets.only(bottom: 8),
      padding: const EdgeInsets.all(12),
      decoration: BoxDecoration(
        color: AppColors.cardBg(context),
        borderRadius: BorderRadius.circular(8),
        border: Border.all(
          color: isDark
              ? AppColors.darkCardBorder
              : AppColors.primaryLight.withValues(alpha: 0.3),
        ),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            mainAxisAlignment: MainAxisAlignment.spaceBetween,
            children: [
              Text(
                'مبلغ: $totalFormatted',
                style: TextStyle(fontWeight: FontWeight.bold, fontSize: 13, color: AppColors.priceColor(context)),
              ),
              if (date.isNotEmpty)
                Text(
                  date,
                  style: TextStyle(
                    fontSize: 11,
                    color: isDark ? AppColors.darkTextMuted : AppColors.textMuted,
                  ),
                ),
            ],
          ),
          const SizedBox(height: 4),
          Row(
            children: [
              Icon(Icons.person_outline, size: 14, color: isDark ? AppColors.darkTextMuted : AppColors.textMuted),
              const SizedBox(width: 4),
              Text(
                'ثبت توسط: $author',
                style: TextStyle(
                  fontSize: 11,
                  color: isDark ? AppColors.darkTextSecondary : AppColors.textSecondary,
                ),
              ),
              if (validity.isNotEmpty) ...[
                const SizedBox(width: 12),
                Icon(Icons.timer_outlined, size: 14, color: isDark ? AppColors.darkTextMuted : AppColors.textMuted),
                const SizedBox(width: 4),
                Text(
                  'اعتبار: $validity',
                  style: TextStyle(
                    fontSize: 11,
                    color: isDark ? AppColors.darkTextSecondary : AppColors.textSecondary,
                  ),
                ),
              ],
            ],
          ),
          if (note.isNotEmpty) ...[
            const SizedBox(height: 6),
            Container(
              padding: const EdgeInsets.all(6),
              decoration: BoxDecoration(
                color: AppColors.recessedBg(context),
                borderRadius: BorderRadius.circular(4),
              ),
              child: Text(
                note,
                style: TextStyle(
                  fontSize: 11,
                  color: isDark ? AppColors.darkTextPrimary : AppColors.textPrimary,
                ),
              ),
            ),
          ],
        ],
      ),
    );
  }
}
