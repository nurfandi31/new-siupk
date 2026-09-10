import 'package:dio/dio.dart';
import '../../../../core/constants/api_constants.dart';
import '../../../../core/error/exceptions.dart';
import '../../../../core/network/dio_client.dart';
// import '../models/auth_models.dart';

abstract class AuthRemoteDataSource {
  Future<Map<String, dynamic>> login({
    required String identifier,
    required String password,
    String? deviceName,
  });
  Future<Map<String, dynamic>> getMe();
  Future<void> logout();
}

class AuthRemoteDataSourceImpl implements AuthRemoteDataSource {
  final DioClient dioClient;

  AuthRemoteDataSourceImpl({required this.dioClient});

  @override
  Future<Map<String, dynamic>> login({
    required String identifier,
    required String password,
    String? deviceName,
  }) async {
    try {
      final response = await dioClient.dio.post(
        ApiConstants.login,
        data: {
          'identifier': identifier,
          'password': password,
          'device_name': deviceName ?? 'Flutter Mobile App',
        },
      );

      final data = response.data;
      if (data is Map<String, dynamic> && data['success'] == true) {
        return data['data'] as Map<String, dynamic>;
      } else {
        throw ServerException(
          message: data['message'] ?? 'Login gagal',
          statusCode: response.statusCode,
        );
      }
    } on DioException catch (e) {
      if (e.error is ServerException) {
        throw e.error as ServerException;
      }
      throw NetworkException(message: 'Tidak dapat terhubung ke server');
    }
  }

  @override
  Future<Map<String, dynamic>> getMe() async {
    try {
      final response = await dioClient.dio.get(ApiConstants.me);
      final data = response.data;
      if (data is Map<String, dynamic> && data['success'] == true) {
        return data['data'] as Map<String, dynamic>;
      } else {
        throw ServerException(
          message: data['message'] ?? 'Gagal memuat profil',
          statusCode: response.statusCode,
        );
      }
    } on DioException catch (e) {
      if (e.error is ServerException) {
        throw e.error as ServerException;
      }
      throw NetworkException(message: 'Tidak dapat terhubung ke server');
    }
  }

  @override
  Future<void> logout() async {
    try {
      await dioClient.dio.post(ApiConstants.logout);
    } catch (_) {}
  }
}

