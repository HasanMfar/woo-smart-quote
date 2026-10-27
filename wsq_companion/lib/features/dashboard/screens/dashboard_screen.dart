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

class DashboardScreen extends StatefulWidget {
  const DashboardScreen({super.key});

  @override
  State<DashboardScreen> createState() => _DashboardScreenState();
}

class _DashboardScreenState extends State<DashboardScreen> {
  final ApiClient _apiClient = ApiClient();
  List<QuoteSummary> _quotes = [];
  bool _isLoading = true;
  String _selectedFilter = 'all'; // 'all', 'pending', 'priced'
  String _searchQuery = '';
  final TextEditingController _searchController = TextEditingController();

  @override
  void initState() {
    super.initState();
    _loadQuotes();
  }

  @override
  void dispose() {
    _searchController.dispose();
    super.dispose();
  }

  Future<void> _loadQuotes() async {
    setState(() => _isLoading = true);

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
      }
    } catch (e) {
      setState(() => _isLoading = false);
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
              final changed = await Navigator.push(
                context,
                MaterialPageRoute(builder: (_) => const StoreSwitcherScreen()),
              );
              if (changed == true) {
                _loadQuotes();
              }
            },
          ),
          actions: [
            IconButton(
              icon: const Icon(Icons.refresh),
              onPressed: _loadQuotes,
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
                          ? const Center(
                              child: Text(
                                AppStrings.emptyQuotes,
                                style: TextStyle(color: AppColors.textMuted, fontSize: 16),
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

  Widget _buildFilterChip(String filterKey, String label) {
    final isSelected = _selectedFilter == filterKey;
    return ChoiceChip(
      label: Text(label),
      selected: isSelected,
      selectedColor: AppColors.primary,
      backgroundColor: Colors.white,
      labelStyle: TextStyle(
        color: isSelected ? Colors.white : AppColors.textSecondary,
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
                          color: quote.isPending ? AppColors.pendingBg : AppColors.pricedBg,
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
                        style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 16),
                      ),
                    ],
                  ),
                  Container(
                    padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 4),
                    decoration: BoxDecoration(
                      color: quote.isPending ? AppColors.pendingBg : AppColors.pricedBg,
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
                    style: const TextStyle(color: AppColors.textPrimary, fontSize: 14),
                  ),
                  const Spacer(),
                  const Icon(Icons.calendar_today_outlined, size: 16, color: AppColors.textMuted),
                  const SizedBox(width: 4),
                  Text(
                    quote.createdAtJalali,
                    style: const TextStyle(color: AppColors.textSecondary, fontSize: 13),
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
                    style: const TextStyle(color: AppColors.textSecondary, fontSize: 13),
                  ),
                  const Spacer(),
                  Text(
                    '${NumberFormatter.toPersianDigits(quote.itemsCount)} قلم محصول',
                    style: const TextStyle(color: AppColors.primary, fontWeight: FontWeight.bold, fontSize: 13),
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
                        style: const TextStyle(fontSize: 15, fontWeight: FontWeight.bold, color: AppColors.primaryDark),
                      ),
                    ],
                  ),
                  ElevatedButton.icon(
                    style: ElevatedButton.styleFrom(
                      backgroundColor: quote.isPending ? AppColors.primary : AppColors.secondary,
                      padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 8),
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
