import '../entities/auth_entities.dart';

abstract class AuthRepository {
  Future<AuthSession> login({
    required String identifier,
    required String password,
    String? deviceName,
  });

  Future<AuthSession?> getCurrentSession();

  Future<void> logout();
}
