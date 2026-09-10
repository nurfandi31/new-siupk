import '../entities/auth_entities.dart';
import '../repositories/auth_repository.dart';

class LoginUseCase {
  final AuthRepository repository;

  LoginUseCase({required this.repository});

  Future<AuthSession> execute({
    required String identifier,
    required String password,
    String? deviceName,
  }) {
    return repository.login(
      identifier: identifier,
      password: password,
      deviceName: deviceName,
    );
  }
}

class GetCurrentSessionUseCase {
  final AuthRepository repository;

  GetCurrentSessionUseCase({required this.repository});

  Future<AuthSession?> execute() {
    return repository.getCurrentSession();
  }
}

class LogoutUseCase {
  final AuthRepository repository;

  LogoutUseCase({required this.repository});

  Future<void> execute() {
    return repository.logout();
  }
}
