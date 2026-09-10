import 'package:flutter_secure_storage/flutter_secure_storage.dart';

class SecureStorageService {
  final FlutterSecureStorage _storage;

  static const String _keyToken = 'auth_token';
  static const String _keyTenantCode = 'active_tenant_code';
  static const String _keyUserData = 'user_data';
  static const String _keyBaseUrl = 'custom_base_url';

  SecureStorageService({FlutterSecureStorage? storage})
      : _storage = storage ?? const FlutterSecureStorage();

  Future<void> saveToken(String token) async {
    await _storage.write(key: _keyToken, value: token);
  }

  Future<String?> getToken() async {
    return await _storage.read(key: _keyToken);
  }

  Future<void> saveTenantCode(String code) async {
    await _storage.write(key: _keyTenantCode, value: code);
  }

  Future<String?> getTenantCode() async {
    return await _storage.read(key: _keyTenantCode);
  }

  Future<void> saveUserData(String jsonString) async {
    await _storage.write(key: _keyUserData, value: jsonString);
  }

  Future<String?> getUserData() async {
    return await _storage.read(key: _keyUserData);
  }

  Future<void> saveBaseUrl(String url) async {
    await _storage.write(key: _keyBaseUrl, value: url);
  }

  Future<String?> getBaseUrl() async {
    return await _storage.read(key: _keyBaseUrl);
  }

  Future<void> clearAuthData() async {
    await _storage.delete(key: _keyToken);
    await _storage.delete(key: _keyTenantCode);
    await _storage.delete(key: _keyUserData);
  }
}
