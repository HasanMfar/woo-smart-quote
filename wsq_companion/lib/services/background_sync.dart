import 'package:dio/dio.dart';
import 'package:workmanager/workmanager.dart';
import '../core/network/api_endpoints.dart';
import '../core/storage/local_storage.dart';
import 'notification_service.dart';

const String wsqSyncTask = 'com.woosmartquote.companion.sync_quotes';

@pragma('vm:entry-point')
void callbackDispatcher() {
  Workmanager().executeTask((taskName, inputData) async {
    try {
      await LocalStorage.init();
      await NotificationService.init();

      final storeUrl = LocalStorage.getActiveStoreUrl();
      final apiBase = LocalStorage.getActiveApiBase() ??
          (storeUrl != null ? (storeUrl.endsWith('/') ? '${storeUrl}wp-json/wsq/v1' : '$storeUrl/wp-json/wsq/v1') : '');
      final token = await LocalStorage.getToken();

      if (apiBase.isEmpty || token == null || token.isEmpty) {
        return Future.value(true);
      }

      String effectiveBase = apiBase.endsWith('/') ? apiBase.substring(0, apiBase.length - 1) : apiBase;
      final lastSync = inputData?['last_sync'] as int? ?? (DateTime.now().millisecondsSinceEpoch ~/ 1000 - 3600);

      final dio = Dio(
        BaseOptions(
          connectTimeout: const Duration(seconds: 15),
          headers: {
            'Authorization': 'Bearer $token',
            'Accept': 'application/json',
          },
        ),
      );

      final response = await dio.get(
        '$effectiveBase${ApiEndpoints.updates}',
        queryParameters: {'since': lastSync},
      );

      if (response.statusCode == 200 && response.data != null) {
        final data = response.data as Map<String, dynamic>;
        final newCount = data['new_quotes_count'] as int? ?? 0;
        final pendingCount = data['pending_count'] as int? ?? 0;

        if (newCount > 0) {
          await NotificationService.showNotification(
            id: DateTime.now().millisecondsSinceEpoch ~/ 1000,
            title: 'استعلام جدید ثبت شد! 📋',
            body: '$newCount استعلام قیمت جدید در فروشگاه منتظر تعیین قیمت شماست.',
          );
        } else if (pendingCount > 0) {
          // Occasional reminder if there is pending backlog
          await NotificationService.showNotification(
            id: 100,
            title: 'یادآوری استعلام‌های در انتظار',
            body: 'تعداد $pendingCount استعلام قیمت در انتظار پاسخگویی است.',
          );
        }
      }
      return Future.value(true);
    } catch (e) {
      return Future.value(false);
    }
  });
}

class BackgroundSync {
  static Future<void> init() async {
    await Workmanager().initialize(
      callbackDispatcher,
      isInDebugMode: false,
    );

    await Workmanager().registerPeriodicTask(
      'wsq_periodic_sync',
      wsqSyncTask,
      frequency: const Duration(minutes: 15),
      constraints: Constraints(
        networkType: NetworkType.connected,
      ),
      existingWorkPolicy: ExistingWorkPolicy.keep,
    );
  }
}
