import 'dart:math';

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

    // Tokens must never live in plaintext Hive storage; drop the legacy mirror.
    await Hive.box(_boxSettings).delete('active_token');
  }

  // Secure Token Storage
  static Future<void> saveToken(String storeUrl, String token) async {
    await _secureStorage.write(key: 'token_$storeUrl', value: token);
  }

  static Future<String?> getToken([String? storeUrl]) async {
    final target = storeUrl ?? getActiveStoreUrl();
    if (target == null || target.isEmpty) {
      return null;
    }
    final token = await _secureStorage.read(key: 'token_$target');
    return (token == null || token.isEmpty) ? null : token;
  }

  static Future<void> clearAuth() async {
    await _secureStorage.deleteAll();
    final settings = Hive.box(_boxSettings);
    await settings.delete('active_store_url');
    await settings.delete('active_api_base');
    await settings.delete('active_store_name');
    await settings.delete('active_currency');
    await settings.delete('active_token');
    await Hive.box(_boxQuotes).clear();
  }

  /// Stable per-install identifier so a re-pair does not create a new device row.
  static String getOrCreateDeviceId() {
    final box = Hive.box(_boxSettings);
    final existing = box.get('device_id');
    if (existing is String && existing.isNotEmpty) {
      return existing;
    }
    final random = Random.secure();
    final bytes = List<int>.generate(16, (_) => random.nextInt(256));
    final id = 'dev_${bytes.map((b) => b.toRadixString(16).padLeft(2, '0')).join()}';
    box.put('device_id', id);
    return id;
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

  // Offline Quotes Cache (scoped per store so stores never see each other's data)
  static Future<void> cacheQuotes(List<Map<String, dynamic>> quotes) async {
    final store = getActiveStoreUrl();
    if (store == null || store.isEmpty) {
      return;
    }
    final box = Hive.box(_boxQuotes);
    await box.put('cached_list_$store', quotes);
    await box.put('last_cached_at_$store', DateTime.now().millisecondsSinceEpoch);
  }

  static List<Map<String, dynamic>>? getCachedQuotes() {
    final store = getActiveStoreUrl();
    if (store == null || store.isEmpty) {
      return null;
    }
    final data = Hive.box(_boxQuotes).get('cached_list_$store');
    if (data is List) {
      return data.map((e) => Map<String, dynamic>.from(e as Map)).toList();
    }
    return null;
  }
}
