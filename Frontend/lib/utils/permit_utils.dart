import 'dart:io';
import 'package:cloud_firestore/cloud_firestore.dart';
import 'package:firebase_auth/firebase_auth.dart';
import 'package:intl/intl.dart';
import './cloudinary_service.dart';

// Fungsi untuk mengunggah bukti perizinan ke Cloudinary
Future<Map<String, String>?> uploadPermitProof({
  required File proofFile,
}) async {
  try {
    final uploadResult = await CloudinaryService.uploadImageToCloudinary(
      proofFile,
    );
    if (uploadResult != null) {
      return {
        'url': uploadResult['url'],
        'public_id': uploadResult['public_id'],
      };
    }
  } catch (e) {
    print('Failed to upload proof to Cloudinary: $e');
  }
  return null;
}

// Fungsi untuk mengirim permohonan perizinan ke Firestore
Future<void> submitPermitToFirestore({
  required String employeeName,
  required String permitType,
  required String description,
  required DateTime startDate,
  required DateTime endDate,
  String? proofImageUrl,
  String? proofImagePublicId,
}) async {
  final user = FirebaseAuth.instance.currentUser;
  if (user == null) {
    throw 'User not found, please log in again.';
  }

  try {
    await FirebaseFirestore.instance.collection('permits').add({
      'uid': user.uid,
      'employeeName': employeeName,
      'permitType': permitType,
      'description': description,
      'startDate': DateFormat('yyyy-MM-dd').format(startDate),
      'endDate': DateFormat('yyyy-MM-dd').format(endDate),
      'proofImageUrl': proofImageUrl,
      'proofImagePublicId': proofImagePublicId,
      'submissionDate': DateTime.now().toIso8601String(),
      'status': 'Pending',
    });
  } catch (e) {
    print('Failed to submit permit to Firestore: $e');
    throw 'Gagal mengirim permohonan ke server.';
  }
}