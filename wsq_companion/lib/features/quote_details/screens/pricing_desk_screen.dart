import 'package:flutter/material.dart';
import '../../../core/constants/app_colors.dart';
import '../../../core/constants/app_strings.dart';
import '../../../core/network/api_client.dart';
import '../../../core/network/api_endpoints.dart';
import '../../../core/utils/number_formatter.dart';
import '../models/quote_detail.dart';
import '../../pdf_preview/screens/pdf_preview_screen.dart';

class PricingDeskScreen extends StatefulWidget {
  final int quoteId;

  const PricingDeskScreen({super.key, required this.quoteId});

  @override
  State<PricingDeskScreen> createState() => _PricingDeskScreenState();
}

class _PricingDeskScreenState extends State<PricingDeskScreen> {
  final ApiClient _apiClient = ApiClient();
  QuoteDetail? _quote;
  bool _isLoading = true;
  bool _isSaving = false;
  String _validity = '۲۴ ساعت کاری';
  final TextEditingController _adminNoteController = TextEditingController();
  bool _autoNotify = true;
  final Map<int, TextEditingController> _priceControllers = {};

  final List<String> _validityOptions = [
    '۲۴ ساعت کاری',
    '۴۸ ساعت کاری',
    '۷ روز کاری',
    'تا پایان هفته جاری',
  ];

  @override
  void initState() {
    super.initState();
    _fetchQuoteDetail();
  }

  @override
  void dispose() {
    for (var c in _priceControllers.values) {
      c.dispose();
    }
    _adminNoteController.dispose();
    super.dispose();
  }

  Future<void> _fetchQuoteDetail() async {
    setState(() => _isLoading = true);
    try {
      final res = await _apiClient.get(ApiEndpoints.quoteDetail(widget.quoteId));
      if (res.statusCode == 200 && res.data != null) {
        final q = QuoteDetail.fromJson(res.data as Map<String, dynamic>);
        _validity = q.validity.isNotEmpty ? q.validity : '۲۴ ساعت کاری';
        for (var item in q.items) {
          _priceControllers[item.itemId] = TextEditingController(
            text: item.unitPrice > 0 ? item.unitPrice.toInt().toString() : '',
          );
        }
        setState(() {
          _quote = q;
          _isLoading = false;
        });
      }
    } catch (e) {
      setState(() => _isLoading = false);
    }
  }

  double _calculateGrandTotal() {
    if (_quote == null) return 0.0;
    double sum = 0.0;
    for (var it in _quote!.items) {
      sum += it.total;
    }
    return sum;
  }

  Future<void> _saveAndDispatch() async {
    if (_quote == null || _isSaving) return;

    setState(() => _isSaving = true);

    final itemsPayload = _quote!.items.map((it) {
      return {
        'item_id': it.itemId,
        'custom_price': it.unitPrice,
        'discount_percent': it.discountPercent,
      };
    }).toList();

    try {
      final res = await _apiClient.post(
        ApiEndpoints.quotePrice(widget.quoteId),
        data: {
          'items': itemsPayload,
          'quote_validity': _validity,
          'admin_note': _adminNoteController.text.trim(),
          'auto_notify': _autoNotify,
        },
      );

      setState(() => _isSaving = false);

      if (res.statusCode == 200 && res.data['success'] == true) {
        if (!mounted) return;
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(
            content: Text(AppStrings.pricingSuccess),
            backgroundColor: AppColors.secondary,
          ),
        );

        final pdfUrl = res.data['pdf_url'] as String?;
        if (pdfUrl != null && pdfUrl.isNotEmpty) {
          Navigator.pushReplacement(
            context,
            MaterialPageRoute(
              builder: (_) => PdfPreviewScreen(
                quoteId: widget.quoteId,
                quoteNumber: _quote!.number,
                pdfUrl: pdfUrl,
              ),
            ),
          );
        } else {
          Navigator.pop(context, true);
        }
      } else {
        if (!mounted) return;
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(content: Text('خطا در ذخیره قیمت‌ها'), backgroundColor: AppColors.error),
        );
      }
    } catch (e) {
      setState(() => _isSaving = false);
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text('خطا در ارتباط با سرور: $e'), backgroundColor: AppColors.error),
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
              ? 'قیمت‌گذاری استعلام #${NumberFormatter.toPersianDigits(_quote!.number)}'
              : AppStrings.pricingDeskTitle),
        ),
        body: _isLoading
            ? const Center(child: CircularProgressIndicator())
            : _quote == null
                ? const Center(child: Text('خطا در بارگذاری استعلام'))
                : Column(
                    children: [
                      Expanded(
                        child: SingleChildScrollView(
                          padding: const EdgeInsets.all(16),
                          child: Column(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: [
                              // Customer Info banner
                              _buildCustomerBanner(),
                              const SizedBox(height: 16),

                              // Items list
                              const Text(
                                'اقلام استعلام و تعیین قیمت:',
                                style: TextStyle(fontWeight: FontWeight.bold, fontSize: 16),
                              ),
                              const SizedBox(height: 8),
                              ..._quote!.items.map((item) => _buildItemPricingCard(item)),

                              const SizedBox(height: 16),

                              // Proposal settings
                              _buildSettingsCard(),

                              const SizedBox(height: 16),

                              // Summary total
                              _buildSummaryCard(),
                            ],
                          ),
                        ),
                      ),

                      // Bottom Sticky Button
                      _buildBottomBar(),
                    ],
                  ),
      ),
    );
  }

  Widget _buildCustomerBanner() {
    return Container(
      padding: const EdgeInsets.all(12),
      decoration: BoxDecoration(
        color: AppColors.primaryLight.withOpacity(0.12),
        borderRadius: BorderRadius.circular(10),
        border: Border.all(color: AppColors.primaryLight.withOpacity(0.3)),
      ),
      child: Row(
        children: [
          const Icon(Icons.person, color: AppColors.primary),
          const SizedBox(width: 8),
          Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(
                _quote!.customerName,
                style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 14),
              ),
              Text(
                NumberFormatter.toPersianDigits(_quote!.customerPhone),
                style: const TextStyle(color: AppColors.textSecondary, fontSize: 12),
              ),
            ],
          ),
          const Spacer(),
          Container(
            padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 4),
            decoration: BoxDecoration(
              color: AppColors.pendingBg,
              borderRadius: BorderRadius.circular(6),
            ),
            child: Text(
              _quote!.statusLabel,
              style: const TextStyle(color: AppColors.pending, fontSize: 12, fontWeight: FontWeight.bold),
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildItemPricingCard(QuoteItem item) {
    final controller = _priceControllers[item.itemId];

    return Card(
      margin: const EdgeInsets.only(bottom: 12),
      child: Padding(
        padding: const EdgeInsets.all(14),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                if (item.imageUrl.isNotEmpty)
                  ClipRRect(
                    borderRadius: BorderRadius.circular(8),
                    child: Image.network(
                      item.imageUrl,
                      width: 50,
                      height: 50,
                      fit: BoxFit.cover,
                      errorBuilder: (_, __, ___) => const Icon(Icons.image, size: 40, color: AppColors.textMuted),
                    ),
                  )
                else
                  Container(
                    width: 50,
                    height: 50,
                    decoration: BoxDecoration(
                      color: AppColors.background,
                      borderRadius: BorderRadius.circular(8),
                    ),
                    child: const Icon(Icons.inventory_2_outlined, color: AppColors.textMuted),
                  ),
                const SizedBox(width: 12),
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(
                        item.name,
                        style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 14),
                      ),
                      const SizedBox(height: 4),
                      Text(
                        'تعداد: ${NumberFormatter.toPersianDigits(item.quantity)}',
                        style: const TextStyle(color: AppColors.textSecondary, fontSize: 12),
                      ),
                    ],
                  ),
                ),
              ],
            ),
            const Divider(height: 18),

            // Price input
            Row(
              children: [
                const Text(
                  AppStrings.customPriceLabel,
                  style: TextStyle(fontWeight: FontWeight.bold, fontSize: 13),
                ),
                const SizedBox(width: 8),
                Expanded(
                  child: TextField(
                    controller: controller,
                    keyboardType: TextInputType.number,
                    decoration: const InputDecoration(
                      hintText: 'مبلغ به تومان',
                      suffixText: 'تومان',
                      isDense: true,
                    ),
                    onChanged: (val) {
                      final parsed = NumberFormatter.parsePersianDouble(val) ?? 0.0;
                      setState(() {
                        item.unitPrice = parsed;
                        item.recalculate();
                      });
                    },
                  ),
                ),
              ],
            ),
            const SizedBox(height: 10),

            // Discount Slider
            Row(
              children: [
                Text(
                  '${AppStrings.discountLabel} ${NumberFormatter.toPersianDigits(item.discountPercent.toInt())}٪',
                  style: const TextStyle(fontSize: 13, color: AppColors.textSecondary),
                ),
                Expanded(
                  child: Slider(
                    value: item.discountPercent,
                    min: 0,
                    max: 50,
                    divisions: 10,
                    label: '${item.discountPercent.toInt()}٪',
                    activeColor: AppColors.secondary,
                    onChanged: (val) {
                      setState(() {
                        item.discountPercent = val;
                        item.recalculate();
                      });
                    },
                  ),
                ),
              ],
            ),

            // Line subtotal
            Container(
              padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 6),
              decoration: BoxDecoration(
                color: AppColors.background,
                borderRadius: BorderRadius.circular(6),
              ),
              child: Row(
                mainAxisAlignment: MainAxisAlignment.spaceBetween,
                children: [
                  const Text('مجموع این ردیف:', style: TextStyle(fontSize: 12, color: AppColors.textMuted)),
                  Text(
                    NumberFormatter.formatCurrency(item.total),
                    style: const TextStyle(fontWeight: FontWeight.bold, color: AppColors.primary, fontSize: 14),
                  ),
                ],
              ),
            ),
          ],
        ),
      ),
    );
  }

  Widget _buildSettingsCard() {
    return Card(
      child: Padding(
        padding: const EdgeInsets.all(14),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            const Text(
              'تنظیمات پیش‌فاکتور رسمی:',
              style: TextStyle(fontWeight: FontWeight.bold, fontSize: 14),
            ),
            const SizedBox(height: 12),

            // Validity selector
            Row(
              children: [
                const Text(AppStrings.validityLabel, style: TextStyle(fontSize: 13)),
                const SizedBox(width: 8),
                Expanded(
                  child: DropdownButtonFormField<String>(
                    value: _validityOptions.contains(_validity) ? _validity : _validityOptions.first,
                    items: _validityOptions.map((opt) {
                      return DropdownMenuItem(value: opt, child: Text(opt));
                    }).toList(),
                    onChanged: (val) {
                      if (val != null) setState(() => _validity = val);
                    },
                    decoration: const InputDecoration(isDense: true),
                  ),
                ),
              ],
            ),
            const SizedBox(height: 12),

            // Admin internal note
            TextField(
              controller: _adminNoteController,
              maxLines: 2,
              decoration: const InputDecoration(
                labelText: AppStrings.adminNoteLabel,
                hintText: 'مثال: تخفیف همکاری ۵ درصدی اعمال شد',
              ),
            ),
            const SizedBox(height: 8),

            // Auto notify toggle
            SwitchListTile(
              contentPadding: EdgeInsets.zero,
              title: const Text(
                'ارسال خودکار به خریدار (بله / ایتا / پیامک)',
                style: TextStyle(fontSize: 13, fontWeight: FontWeight.bold),
              ),
              subtitle: const Text(
                'به‌محض ثبت قیمت، پیام حاوی لینک پرداخت و پیش‌فاکتور برای مشتری ارسال شود.',
                style: TextStyle(fontSize: 11, color: AppColors.textMuted),
              ),
              value: _autoNotify,
              activeColor: AppColors.secondary,
              onChanged: (val) => setState(() => _autoNotify = val),
            ),
          ],
        ),
      ),
    );
  }

  Widget _buildSummaryCard() {
    final grandTotal = _calculateGrandTotal();

    return Container(
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        color: AppColors.primary.withOpacity(0.08),
        borderRadius: BorderRadius.circular(12),
        border: Border.all(color: AppColors.primary.withOpacity(0.2)),
      ),
      child: Row(
        mainAxisAlignment: MainAxisAlignment.spaceBetween,
        children: [
          const Text(
            AppStrings.grandTotalLabel,
            style: TextStyle(fontWeight: FontWeight.bold, fontSize: 15, color: AppColors.primaryDark),
          ),
          Text(
            NumberFormatter.formatCurrency(grandTotal),
            style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 18, color: AppColors.primary),
          ),
        ],
      ),
    );
  }

  Widget _buildBottomBar() {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 12),
      decoration: const BoxDecoration(
        color: Colors.white,
        border: Border(top: BorderSide(color: AppColors.cardBorder)),
      ),
      child: SafeArea(
        child: SizedBox(
          width: double.infinity,
          height: 48,
          child: ElevatedButton.icon(
            style: ElevatedButton.styleFrom(
              backgroundColor: AppColors.secondary,
            ),
            onPressed: _isSaving ? null : _saveAndDispatch,
            icon: _isSaving
                ? const SizedBox(
                    width: 20,
                    height: 20,
                    child: CircularProgressIndicator(color: Colors.white, strokeWidth: 2),
                  )
                : const Icon(Icons.send_rounded),
            label: Text(
              _isSaving ? 'در حال صدور پیش‌فاکتور…' : AppStrings.saveAndSend,
              style: const TextStyle(fontSize: 15, fontWeight: FontWeight.bold),
            ),
          ),
        ),
      ),
    );
  }
}
