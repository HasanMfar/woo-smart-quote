import 'package:dio/dio.dart';
import '../storage/local_storage.dart';

class ApiClient {
  late final Dio _dio;
  late final String _apiBase;

  ApiClient({String? baseUrl}) {
    String base = baseUrl ?? LocalStorage.getActiveApiBase() ?? '';
    if (base.isEmpty) {
      final store = LocalStorage.getActiveStoreUrl() ?? '';
      if (store.isNotEmpty) {
        base = store.endsWith('/') ? '${store}wp-json/wsq/v1' : '$store/wp-json/wsq/v1';
      }
    }
    if (base.endsWith('/')) {
      base = base.substring(0, base.length - 1);
    }
    _apiBase = base;

    _dio = Dio(
      BaseOptions(
        connectTimeout: const Duration(seconds: 15),
        receiveTimeout: const Duration(seconds: 15),
        headers: {
          'Accept': 'application/json',
          'Content-Type': 'application/json',
        },
      ),
    );

    _dio.interceptors.add(
      InterceptorsWrapper(
        onRequest: (options, handler) async {
          final token = await LocalStorage.getToken();
          if (token != null && token.isNotEmpty) {
            options.headers['Authorization'] = 'Bearer $token';
          }
          return handler.next(options);
        },
        onError: (DioException error, handler) {
          return handler.next(error);
        },
      ),
    );
  }

  Dio get dio => _dio;

  String _buildUrl(String path) {
    if (path.startsWith('http://') || path.startsWith('https://')) {
      return path;
    }
    final cleanPath = path.startsWith('/') ? path : '/$path';
    return '$_apiBase$cleanPath';
  }

  Future<Response<T>> get<T>(
    String path, {
    Map<String, dynamic>? queryParameters,
  }) async {
    return _dio.get<T>(_buildUrl(path), queryParameters: queryParameters);
  }

  Future<Response<T>> post<T>(
    String path, {
    dynamic data,
    Map<String, dynamic>? queryParameters,
  }) async {
    return _dio.post<T>(_buildUrl(path), data: data, queryParameters: queryParameters);
  }
}
