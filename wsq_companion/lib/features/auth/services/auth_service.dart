import 'dart:convert';
import 'package:dio/dio.dart';
import '../../../core/network/api_endpoints.dart';
import '../../../core/storage/local_storage.dart';

class AuthService {
  /// Pair with store by sending pairing code to /wsq/v1/auth/pair
  static Future<bool> pairWithQrPayload(String qrRaw) async {
    try {
      final data = jsonDecode(qrRaw) as Map<String, dynamic>;
      final siteUrl = data['site_url'] as String;
      final pairingCode = data['pairing_code'] as String;
      final apiBase = (data['api_base'] as String?) ??
          (siteUrl.endsWith('/') ? '${siteUrl}wp-json/wsq/v1' : '$siteUrl/wp-json/wsq/v1');
      final siteName = (data['site_name'] as String?) ?? 'فروشگاه ووکامرس';
      final currency = (data['currency'] as String?) ?? 'IRT';

      String effectiveBase = apiBase.endsWith('/') ? apiBase.substring(0, apiBase.length - 1) : apiBase;

      final dio = Dio(
        BaseOptions(
          connectTimeout: const Duration(seconds: 15),
          headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
          },
        ),
      );

      final pairUrl = '$effectiveBase${ApiEndpoints.pair}';
      final response = await dio.post(
        pairUrl,
        data: {
          'pairing_code': pairingCode,
          'device_name': 'Android Mobile Companion',
          'device_id': 'device_${DateTime.now().millisecondsSinceEpoch}',
        },
      );

      if (response.statusCode == 200 && response.data != null && response.data['success'] == true) {
        final token = response.data['token'] as String;
        final resolvedStoreName = (response.data['store_name'] as String?) ?? siteName;
        final resolvedCurrency = (response.data['currency_symbol'] as String?) ?? currency;

        await LocalStorage.setActiveStore(
          storeUrl: siteUrl,
          apiBase: effectiveBase,
          storeName: resolvedStoreName,
          currency: resolvedCurrency,
        );
        await LocalStorage.saveToken(siteUrl, token);
        return true;
      }
      return false;
    } catch (e) {
      return false;
    }
  }

  static Future<bool> isAuthenticated() async {
    final token = await LocalStorage.getToken();
    final store = LocalStorage.getActiveStoreUrl();
    return token != null && token.isNotEmpty && store != null && store.isNotEmpty;
  }
}
