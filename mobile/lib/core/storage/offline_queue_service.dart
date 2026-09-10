import 'dart:async';
import 'dart:convert';

import 'package:flutter_secure_storage/flutter_secure_storage.dart';

class OfflineQueueItem {
  const OfflineQueueItem({
    required this.id,
    required this.path,
    required this.method,
    required this.payload,
    required this.createdAt,
    this.attempts = 0,
  });

  final String id;
  final String path;
  final String method;
  final Map<String, dynamic> payload;
  final DateTime createdAt;
  final int attempts;

  OfflineQueueItem copyWith({int? attempts}) => OfflineQueueItem(
        id: id,
        path: path,
        method: method,
        payload: payload,
        createdAt: createdAt,
        attempts: attempts ?? this.attempts,
      );

  Map<String, dynamic> toJson() => {
        'id': id,
        'path': path,
        'method': method,
        'payload': payload,
        'created_at': createdAt.toIso8601String(),
        'attempts': attempts,
      };

  factory OfflineQueueItem.fromJson(Map<String, dynamic> json) =>
      OfflineQueueItem(
        id: json['id'] as String,
        path: json['path'] as String,
        method: json['method'] as String,
        payload: Map<String, dynamic>.from(json['payload'] as Map),
        createdAt: DateTime.parse(json['created_at'] as String),
        attempts: json['attempts'] as int? ?? 0,
      );
}

class OfflineQueueService {
  static const _storage = FlutterSecureStorage(
    aOptions: AndroidOptions(encryptedSharedPreferences: true),
  );

  static const _queueKey = 'offline_mutation_queue';
  static const _maxAttempts = 5;

  final StreamController<int> _countController = StreamController<int>.broadcast();
  Stream<int> get pendingCount => _countController.stream;

  Future<void> enqueue({
    required String path,
    required String method,
    required Map<String, dynamic> payload,
  }) async {
    final queue = await _readQueue();
    final item = OfflineQueueItem(
      id: DateTime.now().microsecondsSinceEpoch.toString(),
      path: path,
      method: method,
      payload: payload,
      createdAt: DateTime.now(),
    );
    queue.add(item);
    await _writeQueue(queue);
  }

  Future<int> get pendingLength async => (await _readQueue()).length;

  Future<int> flush(
    Future<bool> Function(OfflineQueueItem item) submit,
  ) async {
    final queue = await _readQueue();
    final remaining = <OfflineQueueItem>[];
    var synced = 0;

    for (final item in queue) {
      final allowed = await submit(item);
      if (allowed) {
        synced++;
        continue;
      }

      final nextAttempt = item.attempts + 1;
      if (nextAttempt < _maxAttempts) {
        remaining.add(item.copyWith(attempts: nextAttempt));
      }
    }

    await _writeQueue(remaining);
    return synced;
  }

  Future<void> clear() => _storage.delete(key: _queueKey);

  Future<List<OfflineQueueItem>> _readQueue() async {
    final raw = await _storage.read(key: _queueKey);
    if (raw == null || raw.isEmpty) return [];
    try {
      final decoded = jsonDecode(raw) as List<dynamic>;
      return decoded
          .map((e) => OfflineQueueItem.fromJson(Map<String, dynamic>.from(e as Map)))
          .toList();
    } catch (_) {
      return [];
    }
  }

  Future<void> _writeQueue(List<OfflineQueueItem> queue) async {
    if (queue.isEmpty) {
      await _storage.delete(key: _queueKey);
    } else {
      await _storage.write(
        key: _queueKey,
        value: jsonEncode(queue.map((e) => e.toJson()).toList()),
      );
    }
  }
}