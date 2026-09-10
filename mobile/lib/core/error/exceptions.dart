class ServerException implements Exception {
  final String message;
  final int? statusCode;
  final dynamic errors;

  ServerException({required this.message, this.statusCode, this.errors});

  @override
  String toString() => 'ServerException: $message (code: $statusCode)';
}

class CacheException implements Exception {
  final String message;
  CacheException({required this.message});
}

class NetworkException implements Exception {
  final String message;
  NetworkException({required this.message});
}
