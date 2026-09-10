import 'package:equatable/equatable.dart';

abstract class Failure extends Equatable {
  final String message;
  final int? statusCode;

  const Failure({this.message = 'Terjadi kesalahan sistem', this.statusCode});

  @override
  List<Object?> get props => [message, statusCode];
}

class ServerFailure extends Failure {
  const ServerFailure({super.message = 'Terjadi kesalahan pada server', super.statusCode});
}

class AuthFailure extends Failure {
  const AuthFailure({super.message = 'Sesi telah berakhir', super.statusCode = 401});
}

class NetworkFailure extends Failure {
  const NetworkFailure({super.message = 'Koneksi internet bermasalah', super.statusCode});
}

class CacheFailure extends Failure {
  const CacheFailure({super.message = 'Gagal membaca cache lokal', super.statusCode});
}

class ValidationFailure extends Failure {
  final Map<String, dynamic>? errors;
  const ValidationFailure({super.message = 'Validasi gagal', super.statusCode = 422, this.errors});

  @override
  List<Object?> get props => [message, statusCode, errors];
}
class OfflineQueuedFailure extends Failure {
  const OfflineQueuedFailure({super.message = 'Data disimpan di antrean offline'});
}
