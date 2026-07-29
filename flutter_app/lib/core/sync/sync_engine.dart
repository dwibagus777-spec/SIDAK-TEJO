import 'dart:async';
import 'package:connectivity_plus/connectivity_plus.dart';

enum SyncActionType {
  createTemuan,
  updateTemuan,
  updateWoStatus,
  uploadEvidenPhoto,
  checkinGps,
}

class SyncItem {
  final String id;
  final SyncActionType actionType;
  final Map<String, dynamic> payload;
  final DateTime createdAt;
  bool isSynced;

  SyncItem({
    required this.id,
    required this.actionType,
    required this.payload,
    required this.createdAt,
    this.isSynced = false,
  });
}

class SyncEngine {
  final List<SyncItem> _queue = [];
  bool _isSyncing = false;

  SyncEngine() {
    _initConnectivityListener();
  }

  void _initConnectivityListener() {
    Connectivity().onConnectivityChanged.listen((result) {
      if (result != ConnectivityResult.none) {
        processQueue();
      }
    });
  }

  void addToQueue(SyncActionType type, Map<String, dynamic> payload) {
    final item = SyncItem(
      id: DateTime.now().millisecondsSinceEpoch.toString(),
      actionType: type,
      payload: payload,
      createdAt: DateTime.now(),
    );
    _queue.add(item);
    processQueue();
  }

  Future<void> processQueue() async {
    if (_isSyncing || _queue.isEmpty) return;

    _isSyncing = true;
    final pendingItems = List<SyncItem>.from(_queue.where((e) => !e.isSynced));

    for (var item in pendingItems) {
      try {
        // Execute sync logic with Dio Client
        item.isSynced = true;
      } catch (e) {
        // Log sync retry exception
      }
    }

    _queue.removeWhere((e) => e.isSynced);
    _isSyncing = false;
  }
}
