import 'package:dio/dio.dart';
import '../storage/local_storage.dart';

class ApiClient {
  final String? _explicitBase;
  late final Dio _dio;

  ApiClient({String? baseUrl}) : _explicitBase = baseUrl {
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
          // Never leak the bearer token to a host other than the paired store.
          if (_sameOrigin(options.uri, _apiBase)) {
            final token = await LocalStorage.getToken();
            if (token != null && token.isNotEmpty) {
              options.headers['Authorization'] = 'Bearer $token';
            }
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

  // Resolved per request so switching stores never keeps talking to the old one.
  String get _apiBase {
    final explicit = _explicitBase;
    if (explicit != null && explicit.isNotEmpty) {
      return _normalize(explicit);
    }
    final stored = LocalStorage.getActiveApiBase();
    if (stored != null && stored.isNotEmpty) {
      return _normalize(stored);
    }
    final store = LocalStorage.getActiveStoreUrl() ?? '';
    if (store.isNotEmpty) {
      return _normalize(store.endsWith('/') ? '${store}wp-json/wsq/v1' : '$store/wp-json/wsq/v1');
    }
    return '';
  }

  static String _normalize(String base) {
    var value = base;
    while (value.endsWith('/')) {
      value = value.substring(0, value.length - 1);
    }
    return value;
  }

  static bool _sameOrigin(Uri request, String base) {
    final baseUri = Uri.tryParse(base);
    if (baseUri == null || baseUri.host.isEmpty) {
      return false;
    }
    return request.scheme.toLowerCase() == baseUri.scheme.toLowerCase() &&
        request.host.toLowerCase() == baseUri.host.toLowerCase() &&
        _port(request) == _port(baseUri);
  }

  static int _port(Uri uri) {
    if (uri.hasPort) {
      return uri.port;
    }
    return uri.scheme.toLowerCase() == 'https' ? 443 : 80;
  }

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
