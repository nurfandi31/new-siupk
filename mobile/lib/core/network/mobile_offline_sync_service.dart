import 'dart:async';

import 'package:dio/dio.dart';

import '../storage/offline_queue_service.dart';
import '../storage/secure_storage_service.dart';

class MobileOfflineSyncService {
  MobileOfflineSyncService({
    required this.dio,
    required this.queue,
    required this.storage,
  });

  final Dio dio;
  final OfflineQueueService queue;
  final SecureStorageService storage;

  Timer? _timer;
  bool _running = false;

  void startPeriodicFlush({Duration interval = const Duration(minutes: 1)}) {
    _timer?.cancel();
    _timer = Timer.periodic(interval, (_) => flushQueue());
  }

  void stop() {
    _timer?.cancel();
    _timer = null;
  }

  Future<int> flushQueue() async {
    if (_running) return 0;
    _running = true;

    try {
      return await queue.flush((item) async {
        try {
          final method = item.method.toUpperCase();
          if (method == 'GET') return true;

          final options = RequestOptions(
            path: item.path,
            method: method,
            data: item.payload,
          );
          await dio.fetch(options);
          return true;
        } on DioException catch (e) {
          if (e.response?.statusCode != null &&
              e.response!.statusCode! >= 400 &&
              e.response!.statusCode! < 500) {
            // Do not retry client errors forever
            return true;
          }
          return false;
        } catch (_) {
          return false;
        }
      });
    } finally {
      _running = false;
    }
  }
}
