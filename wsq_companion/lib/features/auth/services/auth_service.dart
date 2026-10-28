import 'dart:convert';

import 'package:dio/dio.dart';

import '../../../core/network/api_endpoints.dart';
import '../../../core/storage/local_storage.dart';

class AuthService {
  /// Pair using the JSON payload embedded in the store QR code.
  static Future<bool> pairWithQrPayload(String qrRaw) async {
    Map<String, dynamic> data;
    try {
      final decoded = jsonDecode(qrRaw);
      if (decoded is! Map<String, dynamic>) {
        return false;
      }
      data = decoded;
    } catch (_) {
      return false;
    }

    final siteUrl = data['site_url'];
    final pairingCode = data['pairing_code'];
    if (siteUrl is! String || pairingCode is! String) {
      return false;
    }

    return _pair(
      siteUrl: siteUrl,
      pairingCode: pairingCode,
      apiBase: data['api_base'] is String ? data['api_base'] as String : null,
      siteName: data['site_name'] is String ? data['site_name'] as String : 'فروشگاه ووکامرس',
      currency: data['currency'] is String ? data['currency'] as String : 'IRT',
    );
  }

  /// Pair using a store URL and pairing code typed by hand.
  static Future<bool> pairWithManualEntry({
    required String siteUrl,
    required String pairingCode,
  }) {
    return _pair(siteUrl: siteUrl, pairingCode: pairingCode);
  }

  static Future<bool> _pair({
    required String siteUrl,
    required String pairingCode,
    String? apiBase,
    String siteName = 'فروشگاه ووکامرس',
    String currency = 'IRT',
  }) async {
    final normalizedUrl = _normalizeSiteUrl(siteUrl);
    if (normalizedUrl == null) {
      return false;
    }

    final normalizedCode = _normalizePairingCode(pairingCode);
    if (normalizedCode == null) {
      return false;
    }

    final effectiveBase = _resolveApiBase(normalizedUrl, apiBase);
    if (effectiveBase == null) {
      return false;
    }

    try {
      final dio = Dio(
        BaseOptions(
          connectTimeout: const Duration(seconds: 15),
          headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
          },
        ),
      );

      final response = await dio.post(
        '$effectiveBase${ApiEndpoints.pair}',
        data: {
          'pairing_code': normalizedCode,
          'device_name': 'Android Mobile Companion',
          'device_id': LocalStorage.getOrCreateDeviceId(),
        },
      );

      if (response.statusCode == 200 &&
          response.data is Map &&
          response.data['success'] == true) {
        final token = response.data['token'];
        if (token is! String || token.isEmpty) {
          return false;
        }

        final resolvedStoreName = response.data['store_name'] is String
            ? response.data['store_name'] as String
            : siteName;
        final resolvedCurrency = response.data['currency_symbol'] is String
            ? response.data['currency_symbol'] as String
            : currency;

        await LocalStorage.setActiveStore(
          storeUrl: normalizedUrl,
          apiBase: effectiveBase,
          storeName: resolvedStoreName,
          currency: resolvedCurrency,
        );
        await LocalStorage.saveToken(normalizedUrl, token);
        return true;
      }
      return false;
    } catch (_) {
      return false;
    }
  }

  static Future<bool> isAuthenticated() async {
    final token = await LocalStorage.getToken();
    final store = LocalStorage.getActiveStoreUrl();
    return token != null && token.isNotEmpty && store != null && store.isNotEmpty;
  }

  /// Accepts only absolute HTTPS store URLs (HTTP is limited to local/private
  /// hosts so bearer tokens are never sent in clear over the public internet).
  static String? _normalizeSiteUrl(String raw) {
    final trimmed = raw.trim();
    if (trimmed.isEmpty) {
      return null;
    }

    final uri = Uri.tryParse(trimmed);
    if (uri == null || !uri.hasScheme || uri.host.isEmpty) {
      return null;
    }
    if (uri.userInfo.isNotEmpty) {
      return null;
    }

    final scheme = uri.scheme.toLowerCase();
    if (scheme != 'https' && !(scheme == 'http' && _isLocalHost(uri.host))) {
      return null;
    }

    var path = uri.path;
    while (path.endsWith('/')) {
      path = path.substring(0, path.length - 1);
    }

    return Uri(
      scheme: scheme,
      host: uri.host,
      port: _explicitPort(uri),
      path: path,
    ).toString();
  }

  /// Resolves the REST base, rejecting any URL that is not on the store origin.
  static String? _resolveApiBase(String siteUrl, String? apiBase) {
    final site = Uri.parse(siteUrl);
    final fallback = '${site.toString()}/wp-json/wsq/v1';

    final candidateRaw = apiBase?.trim() ?? '';
    if (candidateRaw.isEmpty) {
      return fallback;
    }

    final candidate = Uri.tryParse(candidateRaw);
    if (candidate == null ||
        !candidate.hasScheme ||
        candidate.host.isEmpty ||
        candidate.userInfo.isNotEmpty ||
        candidate.hasQuery ||
        candidate.hasFragment) {
      return fallback;
    }
    if (candidate.scheme.toLowerCase() != site.scheme.toLowerCase() ||
        candidate.host.toLowerCase() != site.host.toLowerCase() ||
        _effectivePort(candidate) != _effectivePort(site)) {
      return fallback;
    }

    var base = candidate.toString();
    while (base.endsWith('/')) {
      base = base.substring(0, base.length - 1);
    }
    return base;
  }

  /// Normalizes Persian/Arabic digits and accepts either 1234-5678 or 12345678.
  static String? _normalizePairingCode(String raw) {
    const persian = '۰۱۲۳۴۵۶۷۸۹';
    const arabic = '٠١٢٣٤٥٦٧٨٩';

    final digits = StringBuffer();
    for (final rune in raw.trim().runes) {
      final char = String.fromCharCode(rune);
      final persianIndex = persian.indexOf(char);
      final arabicIndex = arabic.indexOf(char);
      if (persianIndex >= 0) {
        digits.write(persianIndex);
      } else if (arabicIndex >= 0) {
        digits.write(arabicIndex);
      } else if (rune >= 0x30 && rune <= 0x39) {
        digits.write(char);
      }
    }

    final value = digits.toString();
    if (value.length != 8) {
      return null;
    }
    return '${value.substring(0, 4)}-${value.substring(4)}';
  }

  static bool _isLocalHost(String host) {
    final h = host.toLowerCase();
    if (h == 'localhost' || h == '127.0.0.1' || h == '::1') {
      return true;
    }
    if (h.endsWith('.local') || h.endsWith('.test')) {
      return true;
    }
    if (RegExp(r'^10\.\d{1,3}\.\d{1,3}\.\d{1,3}$').hasMatch(h)) {
      return true;
    }
    if (RegExp(r'^192\.168\.\d{1,3}\.\d{1,3}$').hasMatch(h)) {
      return true;
    }
    if (RegExp(r'^172\.(1[6-9]|2\d|3[01])\.\d{1,3}\.\d{1,3}$').hasMatch(h)) {
      return true;
    }
    return false;
  }

  static int? _explicitPort(Uri uri) {
    if (!uri.hasPort) {
      return null;
    }
    if ((uri.scheme == 'https' && uri.port == 443) ||
        (uri.scheme == 'http' && uri.port == 80)) {
      return null;
    }
    return uri.port;
  }

  static int _effectivePort(Uri uri) {
    if (uri.hasPort) {
      return uri.port;
    }
    return uri.scheme.toLowerCase() == 'https' ? 443 : 80;
  }
}
