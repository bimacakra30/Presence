import 'package:flutter/material.dart';

void showCustomSnackBar(BuildContext context, String message, {bool isError = false}) {
  final snackBar = SnackBar(
    content: Text(message),
    backgroundColor: isError ? Colors.redAccent : Colors.green,
    behavior: SnackBarBehavior.floating,
    margin: const EdgeInsets.all(16.0),
    shape: RoundedRectangleBorder(
      borderRadius: BorderRadius.circular(12.0),
    ),
    // Durasi SnackBar ditampilkan. Sesuaikan jika perlu.
    duration: const Duration(seconds: 4), 
  );
  ScaffoldMessenger.of(context).showSnackBar(snackBar);
}