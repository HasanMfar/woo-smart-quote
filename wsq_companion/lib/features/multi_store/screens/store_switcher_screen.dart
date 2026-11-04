import 'package:flutter/material.dart';
import '../../../core/constants/app_colors.dart';
import '../../../core/constants/app_strings.dart';
import '../../../core/storage/local_storage.dart';
import '../models/store_profile.dart';
import '../../auth/screens/qr_scanner_screen.dart';
import '../../../core/widgets/wsq_empty_state.dart';

class StoreSwitcherScreen extends StatefulWidget {
  const StoreSwitcherScreen({super.key});

  @override
  State<StoreSwitcherScreen> createState() => _StoreSwitcherScreenState();
}

class _StoreSwitcherScreenState extends State<StoreSwitcherScreen> {
  List<StoreProfile> _stores = [];
  String? _activeUrl;

  @override
  void initState() {
    super.initState();
    _loadStores();
  }

  void _loadStores() {
    final rawList = LocalStorage.getAllStores();
    final list = rawList.map((e) => StoreProfile.fromJson(e)).toList();
    setState(() {
      _stores = list;
      _activeUrl = LocalStorage.getActiveStoreUrl();
    });
  }

  Future<void> _selectStore(StoreProfile store) async {
    final token = await LocalStorage.getToken(store.storeUrl);
    if (token == null) {
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(
          content: Text('برای این فروشگاه اعتبارنامه‌ای یافت نشد. لطفاً بارکد جفت‌سازی را دوباره اسکن کنید.'),
          backgroundColor: AppColors.error,
        ),
      );
      return;
    }

    await LocalStorage.setActiveStore(
      storeUrl: store.storeUrl,
      apiBase: store.apiBase,
      storeName: store.storeName,
      currency: store.currency,
    );
    if (!mounted) return;
    Navigator.pop(context, true);
  }

  @override
  Widget build(BuildContext context) {
    return Directionality(
      textDirection: TextDirection.rtl,
      child: Scaffold(
        appBar: AppBar(
          title: const Text(AppStrings.switchStore),
        ),
        body: ListView(
          padding: const EdgeInsets.all(16),
          children: [
            if (_stores.isNotEmpty) ...[
              const Text(
                'فروشگاه‌های متصل به این اپلیکیشن:',
                style: TextStyle(fontWeight: FontWeight.bold, fontSize: 15),
              ),
              const SizedBox(height: 10),
              ..._stores.map((st) {
                final isActive = st.storeUrl == _activeUrl;
                final isDark = Theme.of(context).brightness == Brightness.dark;
                return Card(
                  color: isActive
                      ? (isDark ? AppColors.primaryLight.withValues(alpha: 0.18) : AppColors.primaryLight.withValues(alpha: 0.08))
                      : AppColors.cardBg(context),
                  shape: RoundedRectangleBorder(
                    borderRadius: BorderRadius.circular(12),
                    side: BorderSide(
                      color: isActive ? (isDark ? AppColors.primaryLight : AppColors.primary) : AppColors.border(context),
                      width: isActive ? 2 : 1,
                    ),
                  ),
                  margin: const EdgeInsets.only(bottom: 10),
                  child: ListTile(
                    leading: CircleAvatar(
                      backgroundColor: isActive
                          ? (isDark ? AppColors.primaryLight : AppColors.primary)
                          : AppColors.recessedBg(context),
                      foregroundColor: isActive
                          ? Colors.white
                          : (isDark ? AppColors.darkTextPrimary : AppColors.textPrimary),
                      child: const Icon(Icons.storefront),
                    ),
                    title: Text(st.storeName, style: const TextStyle(fontWeight: FontWeight.bold)),
                    subtitle: Text(
                      st.storeUrl,
                      textDirection: TextDirection.ltr,
                      style: TextStyle(
                        fontSize: 12,
                        color: isDark ? AppColors.darkTextSecondary : AppColors.textSecondary,
                      ),
                    ),
                    trailing: isActive
                        ? Chip(
                            label: const Text('فعال', style: TextStyle(color: Colors.white, fontSize: 11)),
                            backgroundColor: isDark ? AppColors.primaryLight : AppColors.primary,
                          )
                        : OutlinedButton(
                            onPressed: () => _selectStore(st),
                            child: const Text('انتخاب'),
                          ),
                    onTap: () => _selectStore(st),
                  ),
                );
              }),
            ] else ...[
              const WsqEmptyState(
                icon: Icons.storefront_outlined,
                title: 'هیچ فروشگاهی متصل نیست',
                message: 'با اسکن بارکد جفت‌سازی در تب اپلیکیشن پنل وردپرس، فروشگاه خود را متصل کنید.',
              ),
            ],
            const SizedBox(height: 20),
            ElevatedButton.icon(
              style: ElevatedButton.styleFrom(
                backgroundColor: AppColors.accent,
                minimumSize: const Size(double.infinity, 48),
                padding: const EdgeInsets.symmetric(vertical: 14),
              ),
              onPressed: () async {
                await Navigator.push(
                  context,
                  MaterialPageRoute(builder: (_) => const QrScannerScreen()),
                );
                _loadStores();
              },
              icon: const Icon(Icons.qr_code_scanner),
              label: const Text('افزودن فروشگاه جدید با اسکن بارکد'),
            ),
          ],
        ),
      ),
    );
  }
}
