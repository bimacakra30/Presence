import 'package:cloud_firestore/cloud_firestore.dart';
import 'package:firebase_auth/firebase_auth.dart';
import 'package:firebase_messaging/firebase_messaging.dart';
import 'package:device_info_plus/device_info_plus.dart';
import 'dart:io';

/// Menyimpan atau memperbarui token FCM pengguna saat ini di sub-koleksi 'fcmTokens'
/// di bawah dokumen karyawan di Firestore.
/// Ini memungkinkan satu karyawan memiliki banyak token (satu per perangkat).
Future<void> saveEmployeeFcmTokenToFirestore() async {
  final String? fcmToken = await FirebaseMessaging.instance.getToken();
  final String? userId = FirebaseAuth.instance.currentUser?.uid;

  if (fcmToken == null || userId == null) {
    print(
      'FCM Token atau User ID tidak tersedia. Tidak dapat menyimpan ke Firestore.',
    );
    return;
  }

  String deviceInfo = 'Unknown Device';
  try {
    final DeviceInfoPlugin deviceInfoPlugin = DeviceInfoPlugin();
    if (Platform.isAndroid) {
      final AndroidDeviceInfo androidInfo = await deviceInfoPlugin.androidInfo;
      deviceInfo =
          'Android ${androidInfo.model} (v${androidInfo.version.sdkInt})';
    } else if (Platform.isIOS) {
      final IosDeviceInfo iosInfo = await deviceInfoPlugin.iosInfo;
      deviceInfo = 'iOS ${iosInfo.name} (v${iosInfo.systemVersion})';
    }
  } catch (e) {
    print('Could not get device info: $e');
  }

  try {
    // Referensi ke sub-koleksi 'fcmTokens' di bawah dokumen karyawan
    final DocumentReference tokenRef = FirebaseFirestore.instance
        .collection('employees')
        .doc(userId)
        .collection('fcmTokens') // Menggunakan sub-koleksi
        .doc(fcmToken); // Menggunakan FCM Token sebagai ID dokumen token

    // Simpan token beserta informasi lainnya
    await tokenRef.set(
      {
        'token': fcmToken,
        'userId': userId, // Tetap simpan userId di sini untuk referensi cepat
        'timestamp': FieldValue.serverTimestamp(),
        'deviceInfo': deviceInfo, // Simpan info perangkat
      },
      SetOptions(merge: true), // Gunakan merge untuk update yang aman
    );
    print(
      'FCM Token untuk karyawan $userId dari perangkat $deviceInfo berhasil disimpan/diperbarui di Firestore.',
    );
  } catch (e) {
    print('Error saat menyimpan FCM Token karyawan ke Firestore: $e');
  }
}
