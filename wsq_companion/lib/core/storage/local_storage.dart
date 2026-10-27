import 'package:flutter_secure_storage/flutter_secure_storage.dart';
import 'package:hive_flutter/hive_flutter.dart';

class LocalStorage {
  static const FlutterSecureStorage _secureStorage = FlutterSecureStorage(
    aOptions: AndroidOptions(encryptedSharedPreferences: true),
  );

  static const String _boxSettings = 'wsq_settings';
  static const String _boxQuotes = 'wsq_quotes_cache';
  static const String _boxStores = 'wsq_stores';

  static Future<void> init() async {
    await Hive.initFlutter();
    await Hive.openBox(_boxSettings);
    await Hive.openBox(_boxQuotes);
    await Hive.openBox(_boxStores);
  }

  // Secure Token Storage
  static Future<void> saveToken(String storeUrl, String token) async {
    await _secureStorage.write(key: 'token_$storeUrl', value: token);
    await Hive.box(_boxSettings).put('active_token', token);
  }

  static Future<String?> getToken([String? storeUrl]) async {
    if (storeUrl != null) {
      return await _secureStorage.read(key: 'token_$storeUrl');
    }
    final active = Hive.box(_boxSettings).get('active_token') as String?;
    if (active != null) return active;
    final activeStore = getActiveStoreUrl();
    if (activeStore != null) {
      return await _secureStorage.read(key: 'token_$activeStore');
    }
    return null;
  }

  static Future<void> clearAuth() async {
    await _secureStorage.deleteAll();
    await Hive.box(_boxSettings).clear();
    await Hive.box(_boxQuotes).clear();
  }

  // Active Store Settings
  static Future<void> setActiveStore({
    required String storeUrl,
    required String apiBase,
    required String storeName,
    required String currency,
  }) async {
    final box = Hive.box(_boxSettings);
    await box.put('active_store_url', storeUrl);
    await box.put('active_api_base', apiBase);
    await box.put('active_store_name', storeName);
    await box.put('active_currency', currency);

    // Also persist in multi-store list
    final storesBox = Hive.box(_boxStores);
    await storesBox.put(storeUrl, {
      'store_url': storeUrl,
      'api_base': apiBase,
      'store_name': storeName,
      'currency': currency,
      'last_used': DateTime.now().millisecondsSinceEpoch,
    });
  }

  static String? getActiveStoreUrl() {
    return Hive.box(_boxSettings).get('active_store_url') as String?;
  }

  static String? getActiveApiBase() {
    return Hive.box(_boxSettings).get('active_api_base') as String?;
  }

  static String? getActiveStoreName() {
    return Hive.box(_boxSettings).get('active_store_name') as String?;
  }

  static String getCurrency() {
    return (Hive.box(_boxSettings).get('active_currency') as String?) ?? 'تومان';
  }

  static List<Map<String, dynamic>> getAllStores() {
    final box = Hive.box(_boxStores);
    final list = <Map<String, dynamic>>[];
    for (var key in box.keys) {
      final val = box.get(key);
      if (val is Map) {
        list.add(Map<String, dynamic>.from(val));
      }
    }
    return list;
  }

  // Offline Quotes Cache
  static Future<void> cacheQuotes(List<Map<String, dynamic>> quotes) async {
    final box = Hive.box(_boxQuotes);
    await box.put('cached_list', quotes);
    await box.put('last_cached_at', DateTime.now().millisecondsSinceEpoch);
  }

  static List<Map<String, dynamic>>? getCachedQuotes() {
    final data = Hive.box(_boxQuotes).get('cached_list');
    if (data is List) {
      return data.map((e) => Map<String, dynamic>.from(e as Map)).toList();
    }
    return null;
  }
}
