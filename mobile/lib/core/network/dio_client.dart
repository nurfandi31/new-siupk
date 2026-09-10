import 'package:dio/dio.dart';
import '../constants/api_constants.dart';
import '../error/exceptions.dart';
import '../storage/secure_storage_service.dart';

class DioClient {
  final SecureStorageService storageService;
  late final Dio dio;

  DioClient({required this.storageService}) {
    dio = Dio(
      BaseOptions(
        baseUrl: ApiConstants.baseUrl,
        connectTimeout: const Duration(seconds: 30),
        receiveTimeout: const Duration(seconds: 30),
        headers: {
          ApiConstants.headerAccept: ApiConstants.contentTypeJson,
          'Content-Type': ApiConstants.contentTypeJson,
        },
      ),
    );

    dio.interceptors.add(
      InterceptorsWrapper(
        onRequest: (options, handler) async {
          final token = await storageService.getToken();
          if (token != null && token.isNotEmpty) {
            options.headers[ApiConstants.headerAuthorization] = 'Bearer $token';
          }

          final tenantCode = await storageService.getTenantCode();
          if (tenantCode != null && tenantCode.isNotEmpty) {
            options.headers[ApiConstants.headerTenantCode] = tenantCode;
          }

          final customBaseUrl = await storageService.getBaseUrl();
          if (customBaseUrl != null && customBaseUrl.isNotEmpty) {
            options.baseUrl = customBaseUrl;
          }

          return handler.next(options);
        },
        onError: (DioException error, handler) {
          if (error.response != null) {
            final statusCode = error.response?.statusCode ?? 500;
            final data = error.response?.data;
            String message = 'Terjadi kesalahan pada server';

            if (data is Map<String, dynamic>) {
              message = data['message'] as String? ?? message;
            }

            if (statusCode == 401) {
              storageService.clearAuthData();
            }

            return handler.reject(
              DioException(
                requestOptions: error.requestOptions,
                response: error.response,
                type: error.type,
                error: ServerException(
                  message: message,
                  statusCode: statusCode,
                  errors: data is Map ? data['errors'] : null,
                ),
              ),
            );
          }

          return handler.reject(
            DioException(
              requestOptions: error.requestOptions,
              type: error.type,
              error: NetworkException(message: 'Tidak dapat terhubung ke server. Periksa koneksi internet Anda.'),
            ),
          );
        },
      ),
    );
  }
}
