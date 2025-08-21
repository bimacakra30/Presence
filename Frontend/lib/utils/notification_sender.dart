import 'dart:convert';
import 'package:http/http.dart' as http;
import 'package:firebase_messaging/firebase_messaging.dart'; // Untuk mendapatkan token FCM

/// Utility class for sending Firebase Cloud Notifications.
/// WARNING: Placing the Server Key in client-side code (Flutter app) is INSECURE for production environments.
/// For production, always send notifications from a secure backend (e.g., Firebase Cloud Functions, Node.js server, etc.).
class NotificationSender {
  // Ganti dengan Server Key FCM Anda dari Firebase Console > Project Settings > Cloud Messaging
  // JAGA KERAHASIAAN KUNCI INI!
  static const String _firebaseServerKey = 'YOUR_FIREBASE_SERVER_KEY_HERE'; // <--- GANTI INI DENGAN KUNCI ASLI ANDA!

  /// Sends a push notification to a specific device using its FCM token.
  /// [deviceToken]: The FCM token of the target device.
  /// [title]: The title of the notification.
  /// [body]: The body text of the notification.
  /// [data]: Optional custom data to send with the notification (payload).
  static Future<bool> sendNotification({
    required String deviceToken,
    required String title,
    required String body,
    Map<String, dynamic>? data,
  }) async {
    if (_firebaseServerKey == 'YOUR_FIREBASE_SERVER_KEY_HERE' || _firebaseServerKey.isEmpty) {
      print('ERROR: Firebase Server Key belum diatur di NotificationSender.');
      return false;
    }

    final String fcmUrl = 'https://fcm.googleapis.com/fcm/send';

    final Map<String, dynamic> notificationPayload = {
      'notification': {
        'title': title,
        'body': body,
      },
      'priority': 'high',
      'data': data ?? {}, // Sertakan data custom jika ada, atau objek kosong
      'to': deviceToken,
    };

    try {
      final response = await http.post(
        Uri.parse(fcmUrl),
        headers: <String, String>{
          'Content-Type': 'application/json',
          'Authorization': 'key=$_firebaseServerKey',
        },
        body: jsonEncode(notificationPayload),
      );

      if (response.statusCode == 200) {
        print('Notifikasi berhasil dikirim: ${response.body}');
        return true;
      } else {
        print('Gagal mengirim notifikasi (status: ${response.statusCode}): ${response.body}');
        return false;
      }
    } catch (e) {
      print('Error saat mengirim notifikasi: $e');
      return false;
    }
  }

  /// Retrieves the current device's FCM token.
  static Future<String?> getDeviceFCMToken() async {
    try {
      return await FirebaseMessaging.instance.getToken();
    } catch (e) {
      print('Error getting FCM token: $e');
      return null;
    }
  }
}
