import 'package:flutter/material.dart';
import 'dart:async';

class SnackbarUtils {
  static final List<SnackBarInfo> _snackbarQueue = [];
  static bool _isShowing = false;
  static Timer? _timer;

  static void showSnackBar(
    BuildContext context,
    String message, {
    bool isError = false,
    Duration duration = const Duration(seconds: 3),
    bool showIcon = true,
    VoidCallback? onAction,
    String? actionLabel,
  }) {
    // Jangan tampilkan snackbar untuk pesan yang sama dalam 2 detik terakhir
    if (_shouldSkipSnackbar(message)) {
      return;
    }

    final snackbarInfo = SnackBarInfo(
      message: message,
      isError: isError,
      duration: duration,
      showIcon: showIcon,
      onAction: onAction,
      actionLabel: actionLabel,
    );

    _snackbarQueue.add(snackbarInfo);
    _processQueue(context);
  }

  static bool _shouldSkipSnackbar(String message) {
    // Skip snackbar untuk pesan yang terlalu sering muncul
    final now = DateTime.now();
    final recentMessages = _snackbarQueue
        .where((info) => 
            info.message == message && 
            now.difference(info.timestamp).inSeconds < 2)
        .toList();
    
    return recentMessages.isNotEmpty;
  }

  static void _processQueue(BuildContext context) {
    if (_isShowing || _snackbarQueue.isEmpty) return;

    _isShowing = true;
    final snackbarInfo = _snackbarQueue.removeAt(0);

    // Hapus snackbar sebelumnya jika ada
    ScaffoldMessenger.of(context).clearSnackBars();

    ScaffoldMessenger.of(context).showSnackBar(
      SnackBar(
        content: Row(
          children: [
            if (snackbarInfo.showIcon) ...[
              Icon(
                snackbarInfo.isError ? Icons.error_outline : Icons.check_circle_outline,
                color: Colors.white,
                size: 20,
              ),
              const SizedBox(width: 8),
            ],
            Expanded(
              child: Text(
                snackbarInfo.message,
                style: const TextStyle(
                  color: Colors.white,
                  fontSize: 14,
                  fontWeight: FontWeight.w500,
                ),
              ),
            ),
          ],
        ),
        backgroundColor: snackbarInfo.isError 
            ? const Color.fromRGBO(220, 53, 69, 1) // Bootstrap danger color
            : const Color.fromRGBO(40, 167, 69, 1), // Bootstrap success color
        behavior: SnackBarBehavior.floating,
        margin: const EdgeInsets.all(16),
        shape: RoundedRectangleBorder(
          borderRadius: BorderRadius.circular(12),
        ),
        duration: snackbarInfo.duration,
        action: snackbarInfo.onAction != null && snackbarInfo.actionLabel != null
            ? SnackBarAction(
                label: snackbarInfo.actionLabel!,
                textColor: Colors.white,
                onPressed: snackbarInfo.onAction!,
              )
            : null,
        elevation: 8,
      ),
    );

    // Set timer untuk snackbar berikutnya
    _timer?.cancel();
    _timer = Timer(snackbarInfo.duration + const Duration(milliseconds: 500), () {
      _isShowing = false;
      _processQueue(context);
    });
  }

  static void showSuccess(
    BuildContext context,
    String message, {
    Duration duration = const Duration(seconds: 2),
  }) {
    showSnackBar(
      context,
      message,
      isError: false,
      duration: duration,
    );
  }

  static void showError(
    BuildContext context,
    String message, {
    Duration duration = const Duration(seconds: 4),
  }) {
    showSnackBar(
      context,
      message,
      isError: true,
      duration: duration,
    );
  }

  static void showInfo(
    BuildContext context,
    String message, {
    Duration duration = const Duration(seconds: 3),
  }) {
    showSnackBar(
      context,
      message,
      isError: false,
      duration: duration,
      showIcon: false,
    );
  }

  static void clearAll(BuildContext context) {
    _snackbarQueue.clear();
    _timer?.cancel();
    _isShowing = false;
    ScaffoldMessenger.of(context).clearSnackBars();
  }
}

class SnackBarInfo {
  final String message;
  final bool isError;
  final Duration duration;
  final bool showIcon;
  final VoidCallback? onAction;
  final String? actionLabel;
  final DateTime timestamp;

  SnackBarInfo({
    required this.message,
    required this.isError,
    required this.duration,
    required this.showIcon,
    this.onAction,
    this.actionLabel,
  }) : timestamp = DateTime.now();
}

// Fungsi legacy untuk kompatibilitas
void showCustomSnackBar(BuildContext context, String message, {bool isError = false}) {
  SnackbarUtils.showSnackBar(context, message, isError: isError);
}