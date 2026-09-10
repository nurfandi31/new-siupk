import 'dart:convert';
import '../../../../core/error/exceptions.dart';
import '../../../../core/error/failures.dart';
import '../../../../core/storage/secure_storage_service.dart';
import '../../domain/entities/auth_entities.dart';
import '../../domain/repositories/auth_repository.dart';
import '../datasources/auth_remote_datasource.dart';
import '../models/auth_models.dart';

class AuthRepositoryImpl implements AuthRepository {
  final AuthRemoteDataSource remoteDataSource;
  final SecureStorageService storageService;

  AuthRepositoryImpl({
    required this.remoteDataSource,
    required this.storageService,
  });

  @override
  Future<AuthSession> login({
    required String identifier,
    required String password,
    String? deviceName,
  }) async {
    try {
      final rawData = await remoteDataSource.login(
        identifier: identifier,
        password: password,
        deviceName: deviceName,
      );

      final token = rawData['token'] as String;
      final userModel = UserModel.fromJson(rawData['user'] as Map<String, dynamic>);
      final tenantModel = rawData['tenant'] != null
          ? TenantModel.fromJson(rawData['tenant'] as Map<String, dynamic>)
          : null;

      await storageService.saveToken(token);
      if (tenantModel != null) {
        await storageService.saveTenantCode(tenantModel.code);
      }
      await storageService.saveUserData(jsonEncode(userModel.toJson()));

      return AuthSession(
        token: token,
        user: userModel,
        tenant: tenantModel,
      );
    } on ServerException catch (e) {
      throw ServerFailure(message: e.message, statusCode: e.statusCode);
    } on NetworkException catch (e) {
      throw NetworkFailure(message: e.message);
    }
  }

  @override
  Future<AuthSession?> getCurrentSession() async {
    try {
      final token = await storageService.getToken();
      if (token == null || token.isEmpty) {
        return null;
      }

      final rawData = await remoteDataSource.getMe();
      final userModel = UserModel.fromJson(rawData['user'] as Map<String, dynamic>);
      final tenantModel = rawData['tenant'] != null
          ? TenantModel.fromJson(rawData['tenant'] as Map<String, dynamic>)
          : null;

      return AuthSession(
        token: token,
        user: userModel,
        tenant: tenantModel,
      );
    } on ServerException catch (e) {
      if (e.statusCode == 401) {
        await storageService.clearAuthData();
        return null;
      }
      throw ServerFailure(message: e.message, statusCode: e.statusCode);
    } on NetworkException catch (_) {
      final cachedUser = await storageService.getUserData();
      final tenantCode = await storageService.getTenantCode();
      final token = await storageService.getToken();

      if (cachedUser != null && token != null) {
        final userMap = jsonDecode(cachedUser) as Map<String, dynamic>;
        return AuthSession(
          token: token,
          user: UserModel.fromJson(userMap),
          tenant: tenantCode != null
              ? TenantEntity(id: 0, code: tenantCode, name: 'Offline Tenant', status: 'active')
              : null,
        );
      }
      return null;
    }
  }

  @override
  Future<void> logout() async {
    try {
      await remoteDataSource.logout();
    } finally {
      await storageService.clearAuthData();
    }
  }
}
