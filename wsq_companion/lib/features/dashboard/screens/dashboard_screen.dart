import 'package:dio/dio.dart';
import 'package:flutter/material.dart';
import '../../../core/constants/app_colors.dart';
import '../../../core/constants/app_strings.dart';
import '../../../core/network/api_client.dart';
import '../../../core/network/api_endpoints.dart';
import '../../../core/storage/local_storage.dart';
import '../../../core/utils/number_formatter.dart';
import '../models/quote_summary.dart';
import '../../quote_details/screens/quote_detail_screen.dart';
import '../../quote_details/screens/pricing_desk_screen.dart';
import '../../multi_store/screens/store_switcher_screen.dart';
import '../../auth/screens/qr_scanner_screen.dart';
import '../../../services/notification_service.dart';
import '../../../core/widgets/wsq_empty_state.dart';

class DashboardScreen extends StatefulWidget {
  const DashboardScreen({super.key});

  @override
  State<DashboardScreen> createState() => _DashboardScreenState();
}

class _DashboardScreenState extends State<DashboardScreen> {
  final ApiClient _apiClient = ApiClient();
  List<QuoteSummary> _quotes = [];
  bool _isLoading = true;
  String? _loadError;
  String _selectedFilter = 'all'; // 'all', 'pending', 'priced'
  String _searchQuery = '';
  final TextEditingController _searchController = TextEditingController();

  @override
  void initState() {
    super.initState();
    _loadQuotes();
    WidgetsBinding.instance.addPostFrameCallback((_) {
      NotificationService.requestPermission();
    });
  }

  Future<void> _confirmLogout() async {
    final confirmed = await showDialog<bool>(
      context: context,
      builder: (ctx) => AlertDialog(
        title: const Text('خروج از حساب'),
        content: const Text('برای اتصال مجدد باید بارکد جفت‌سازی فروشگاه را دوباره اسکن کنید. ادامه می‌دهید؟'),
        actions: [
          TextButton(onPressed: () => Navigator.pop(ctx, false), child: const Text('انصراف')),
          ElevatedButton(onPressed: () => Navigator.pop(ctx, true), child: const Text('خروج')),
        ],
      ),
    );
    if (confirmed != true || !mounted) return;

    await LocalStorage.clearAuth();
    if (!mounted) return;
    Navigator.of(context).pushAndRemoveUntil(
      MaterialPageRoute(builder: (_) => const QrScannerScreen(isRoot: true)),
      (route) => false,
    );
  }

  @override
  void dispose() {
    _searchController.dispose();
    super.dispose();
  }

  Future<void> _loadQuotes() async {
    setState(() {
      _isLoading = true;
      _loadError = null;
    });

    // First load from local offline cache
    final cached = LocalStorage.getCachedQuotes();
    if (cached != null && cached.isNotEmpty && _quotes.isEmpty) {
      _quotes = cached.map((e) => QuoteSummary.fromJson(e)).toList();
    }

    try {
      final response = await _apiClient.get(
        ApiEndpoints.quotes,
        queryParameters: {
          'status': _selectedFilter,
          'search': _searchQuery,
          'per_page': 50,
        },
      );

      if (!mounted) return;

      if (response.statusCode == 200 && response.data != null) {
        final list = (response.data['quotes'] as List? ?? [])
            .map((e) => QuoteSummary.fromJson(e as Map<String, dynamic>))
            .toList();

        setState(() {
          _quotes = list;
          _isLoading = false;
        });

        // Update local cache
        if (_selectedFilter == 'all' && _searchQuery.isEmpty) {
          final jsonList = list.map((e) => e.toJson()).toList();
          await LocalStorage.cacheQuotes(jsonList);
        }
      } else {
        setState(() {
          _isLoading = false;
          _loadError = 'خطا در دریافت اطلاعات از فروشگاه.';
        });
      }
    } catch (e) {
      if (!mounted) return;
      final unauthorized = e is DioException && e.response?.statusCode == 401;
      setState(() {
        _isLoading = false;
        _loadError = unauthorized
            ? 'نشست شما منقضی شده است. لطفاً از حساب خارج شده و دوباره به فروشگاه متصل شوید.'
            : 'اتصال به فروشگاه برقرار نشد. اتصال اینترنت خود را بررسی کنید.';
      });
    }
  }

  @override
  Widget build(BuildContext context) {
    final storeName = LocalStorage.getActiveStoreName() ?? AppStrings.appName;

    return Directionality(
      textDirection: TextDirection.rtl,
      child: Scaffold(
        appBar: AppBar(
          title: Text(storeName),
          leading: IconButton(
            icon: const Icon(Icons.storefront_outlined),
            tooltip: AppStrings.switchStore,
            onPressed: () async {
              final previousStore = LocalStorage.getActiveStoreUrl();
              await Navigator.push(
                context,
                MaterialPageRoute(builder: (_) => const StoreSwitcherScreen()),
              );
              if (!mounted) return;
              // Adding a store also switches to it, so compare the active store
              // instead of relying on the route result.
              if (LocalStorage.getActiveStoreUrl() != previousStore) {
                setState(() {
                  _quotes = [];
                  _searchQuery = '';
                  _searchController.clear();
                });
                _loadQuotes();
              }
            },
          ),
          actions: [
            IconButton(
              icon: const Icon(Icons.refresh),
              onPressed: _loadQuotes,
            ),
            IconButton(
              icon: const Icon(Icons.logout),
              tooltip: 'خروج از حساب',
              onPressed: _confirmLogout,
            ),
          ],
        ),
        body: Column(
          children: [
            // Search Input
            Padding(
              padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 8),
              child: TextField(
                controller: _searchController,
                decoration: InputDecoration(
                  hintText: AppStrings.searchPlaceholder,
                  prefixIcon: const Icon(Icons.search),
                  suffixIcon: _searchQuery.isNotEmpty
                      ? IconButton(
                          icon: const Icon(Icons.clear),
                          onPressed: () {
                            _searchController.clear();
                            setState(() => _searchQuery = '');
                            _loadQuotes();
                          },
                        )
                      : null,
                ),
                onSubmitted: (val) {
                  setState(() => _searchQuery = val.trim());
                  _loadQuotes();
                },
              ),
            ),

            // Filter Tabs
            Padding(
              padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 4),
              child: Row(
                children: [
                  _buildFilterChip('all', AppStrings.tabAll),
                  const SizedBox(width: 8),
                  _buildFilterChip('pending', AppStrings.tabPending),
                  const SizedBox(width: 8),
                  _buildFilterChip('priced', AppStrings.tabPriced),
                ],
              ),
            ),
            const SizedBox(height: 8),

            // Quotes List
            Expanded(
              child: _isLoading && _quotes.isEmpty
                  ? const Center(child: CircularProgressIndicator())
                  : RefreshIndicator(
                      onRefresh: _loadQuotes,
                      child: _quotes.isEmpty
                          ? LayoutBuilder(
                              builder: (context, constraints) => SingleChildScrollView(
                                physics: const AlwaysScrollableScrollPhysics(),
                                child: ConstrainedBox(
                                  constraints: BoxConstraints(minHeight: constraints.maxHeight),
                                  child: _buildEmptyOrErrorState(),
                                ),
                              ),
                            )
                          : ListView.builder(
                              padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 8),
                              itemCount: _quotes.length,
                              itemBuilder: (context, index) {
                                return _buildQuoteCard(_quotes[index]);
                              },
                            ),
                    ),
            ),
          ],
        ),
      ),
    );
  }

  Widget _buildEmptyOrErrorState() {
    if (_loadError != null) {
      return WsqEmptyState(
        icon: Icons.cloud_off_outlined,
        title: 'خطا در ارتباط',
        message: _loadError!,
        actionLabel: AppStrings.retry,
        actionIcon: Icons.refresh,
        onAction: _loadQuotes,
        isError: true,
      );
    }

    if (_searchQuery.isNotEmpty) {
      return WsqEmptyState(
        icon: Icons.search_off_outlined,
        title: 'نتیجه‌ای یافت نشد',
        message: 'هیچ استعلامی با عبارت «$_searchQuery» پیدا نشد. عبارت دیگری را امتحان کنید.',
        actionLabel: 'پاک کردن جستجو',
        actionIcon: Icons.clear,
        onAction: () {
          _searchController.clear();
          setState(() {
            _searchQuery = '';
          });
          _loadQuotes();
        },
      );
    }

    return WsqEmptyState(
      icon: Icons.receipt_long_outlined,
      title: AppStrings.emptyQuotes,
      message: 'استعلام‌های ثبت‌شده در فروشگاه به صورت خودکار در این صفحه نمایش داده می‌شوند.',
      actionLabel: 'به‌روزرسانی لیست',
      actionIcon: Icons.refresh,
      onAction: _loadQuotes,
    );
  }

  Widget _buildFilterChip(String filterKey, String label) {
    final isSelected = _selectedFilter == filterKey;
    final isDark = Theme.of(context).brightness == Brightness.dark;
    return ChoiceChip(
      label: Text(label),
      selected: isSelected,
      selectedColor: isDark ? AppColors.primaryLight : AppColors.primary,
      backgroundColor: isDark ? AppColors.darkSurface : Colors.white,
      side: BorderSide(
        color: isSelected
            ? (isDark ? AppColors.primaryLight : AppColors.primary)
            : (isDark ? AppColors.darkCardBorder : AppColors.cardBorder),
      ),
      labelStyle: TextStyle(
        color: isSelected
            ? Colors.white
            : (isDark ? AppColors.darkTextSecondary : AppColors.textSecondary),
        fontWeight: isSelected ? FontWeight.bold : FontWeight.normal,
      ),
      onSelected: (selected) {
        if (selected) {
          setState(() => _selectedFilter = filterKey);
          _loadQuotes();
        }
      },
    );
  }

  Widget _buildQuoteCard(QuoteSummary quote) {
    final isDark = Theme.of(context).brightness == Brightness.dark;
    final badgeBg = AppColors.statusBadgeBg(context, isPending: quote.isPending);

    return Card(
      margin: const EdgeInsets.only(bottom: 12),
      child: InkWell(
        borderRadius: BorderRadius.circular(12),
        onTap: () async {
          final updated = await Navigator.push(
            context,
            MaterialPageRoute(
              builder: (_) => QuoteDetailScreen(quoteId: quote.id),
            ),
          );
          if (updated == true) {
            _loadQuotes();
          }
        },
        child: Padding(
          padding: const EdgeInsets.all(16),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              // Header row
              Row(
                mainAxisAlignment: MainAxisAlignment.spaceBetween,
                children: [
                  Row(
                    children: [
                      Container(
                        padding: const EdgeInsets.all(6),
                        decoration: BoxDecoration(
                          color: badgeBg,
                          borderRadius: BorderRadius.circular(8),
                        ),
                        child: Icon(
                          quote.isPending ? Icons.pending_actions : Icons.verified,
                          color: quote.isPending ? AppColors.pending : AppColors.priced,
                          size: 20,
                        ),
                      ),
                      const SizedBox(width: 8),
                      Text(
                        'استعلام #${NumberFormatter.toPersianDigits(quote.number)}',
                        style: TextStyle(
                          fontWeight: FontWeight.bold,
                          fontSize: 16,
                          color: Theme.of(context).textTheme.bodyLarge?.color,
                        ),
                      ),
                    ],
                  ),
                  Container(
                    padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 4),
                    decoration: BoxDecoration(
                      color: badgeBg,
                      borderRadius: BorderRadius.circular(6),
                    ),
                    child: Text(
                      quote.statusLabel,
                      style: TextStyle(
                        fontSize: 12,
                        fontWeight: FontWeight.bold,
                        color: quote.isPending ? AppColors.pending : AppColors.priced,
                      ),
                    ),
                  ),
                ],
              ),
              const SizedBox(height: 12),

              // Buyer details
              Row(
                children: [
                  const Icon(Icons.person_outline, size: 16, color: AppColors.textMuted),
                  const SizedBox(width: 4),
                  Text(
                    quote.customerName,
                    style: TextStyle(
                      color: Theme.of(context).textTheme.bodyLarge?.color ?? AppColors.textPrimary,
                      fontSize: 14,
                    ),
                  ),
                  const Spacer(),
                  const Icon(Icons.calendar_today_outlined, size: 16, color: AppColors.textMuted),
                  const SizedBox(width: 4),
                  Text(
                    quote.createdAtJalali,
                    style: TextStyle(
                      color: Theme.of(context).textTheme.bodyMedium?.color ?? AppColors.textSecondary,
                      fontSize: 13,
                    ),
                  ),
                ],
              ),
              const SizedBox(height: 6),

              Row(
                children: [
                  const Icon(Icons.phone_outlined, size: 16, color: AppColors.textMuted),
                  const SizedBox(width: 4),
                  Text(
                    NumberFormatter.toPersianDigits(quote.customerPhone),
                    style: TextStyle(
                      color: Theme.of(context).textTheme.bodyMedium?.color ?? AppColors.textSecondary,
                      fontSize: 13,
                    ),
                  ),
                  const Spacer(),
                  Text(
                    '${NumberFormatter.toPersianDigits(quote.itemsCount)} قلم محصول',
                    style: TextStyle(
                      color: isDark ? const Color(0xFF60A5FA) : AppColors.primary,
                      fontWeight: FontWeight.bold,
                      fontSize: 13,
                    ),
                  ),
                ],
              ),
              const Divider(height: 20),

              // Bottom Actions Row
              Row(
                mainAxisAlignment: MainAxisAlignment.spaceBetween,
                children: [
                  Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      const Text('مجموع استعلام:', style: TextStyle(fontSize: 11, color: AppColors.textMuted)),
                      Text(
                        NumberFormatter.formatCurrency(quote.total),
                        style: TextStyle(
                          fontSize: 15,
                          fontWeight: FontWeight.bold,
                          color: AppColors.priceColor(context),
                        ),
                      ),
                    ],
                  ),
                  ElevatedButton.icon(
                    style: ElevatedButton.styleFrom(
                      backgroundColor: isDark ? AppColors.primaryLight : AppColors.primary,
                      padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 10),
                      minimumSize: const Size(0, 48), // 48dp Android Touch Target
                    ),
                    onPressed: () async {
                      if (quote.isPending) {
                        final updated = await Navigator.push(
                          context,
                          MaterialPageRoute(
                            builder: (_) => PricingDeskScreen(quoteId: quote.id),
                          ),
                        );
                        if (updated == true) _loadQuotes();
                      } else {
                        Navigator.push(
                          context,
                          MaterialPageRoute(
                            builder: (_) => QuoteDetailScreen(quoteId: quote.id),
                          ),
                        );
                      }
                    },
                    icon: Icon(quote.isPending ? Icons.edit_note : Icons.visibility, size: 18),
                    label: Text(quote.isPending ? 'تعیین قیمت' : 'جزئیات و PDF'),
                  ),
                ],
              ),
            ],
          ),
        ),
      ),
    );
  }
}
