import 'package:flutter/material.dart';
import 'package:flutter_local_notifications/flutter_local_notifications.dart';
import 'package:flutter_timezone/flutter_timezone.dart';
import 'package:timezone/data/latest_all.dart' as tz;
import 'package:timezone/timezone.dart' as tz;

class LocalNotificationService {
  static final FlutterLocalNotificationsPlugin flutterLocalNotificationsPlugin =
      FlutterLocalNotificationsPlugin();

  /// Menginisialisasi pengaturan untuk notifikasi lokal.
  /// Harus dipanggil sekali saat aplikasi pertama kali dijalankan.
  static Future<void> initialize() async {
    // Inisialisasi zona waktu untuk penjadwalan notifikasi
    tz.initializeTimeZones();
    final String? timeZoneName = await FlutterTimezone.getLocalTimezone();
    if (timeZoneName != null) {
      tz.setLocalLocation(tz.getLocation(timeZoneName));
    }

    // Pengaturan untuk Android
    const AndroidInitializationSettings initializationSettingsAndroid =
        AndroidInitializationSettings('@mipmap/ic_launcher');

    // Pengaturan untuk iOS
    const DarwinInitializationSettings initializationSettingsIOS =
        DarwinInitializationSettings(
      requestAlertPermission: true,
      requestBadgePermission: true,
      requestSoundPermission: true,
    );

    // Menggabungkan pengaturan untuk semua platform
    final InitializationSettings initializationSettings = InitializationSettings(
      android: initializationSettingsAndroid,
      iOS: initializationSettingsIOS,
    );

    // Memulai proses inisialisasi
    await flutterLocalNotificationsPlugin.initialize(
      initializationSettings,
      onDidReceiveNotificationResponse: onDidReceiveNotificationResponse,
      onDidReceiveBackgroundNotificationResponse: onDidReceiveNotificationResponse,
    );
  }

  /// Handler untuk respons notifikasi.
  /// Dipanggil saat pengguna mengetuk notifikasi.
  @pragma('vm:entry-point')
  static void onDidReceiveNotificationResponse(NotificationResponse notificationResponse) async {
    final String? payload = notificationResponse.payload;
    if (payload != null) {
      debugPrint('Payload notifikasi: $payload');
      // TODO: Tambahkan navigasi atau logika sesuai payload
    }
  }

  /// Menampilkan notifikasi lokal dari pesan FCM.
  /// Fungsi ini dipanggil dari main.dart saat pesan FCM diterima.
  static Future<void> showNotificationFromFCM({
    required String title,
    required String body,
    String? payload,
  }) async {
    const AndroidNotificationDetails androidPlatformChannelSpecifics =
        AndroidNotificationDetails(
      // ID Channel, harus unik dan sama dengan di AndroidManifest
      'presensi_channel',
      'Notifikasi Presensi',
      channelDescription: 'Channel untuk pengingat presensi.',
      importance: Importance.max, // Memastikan notifikasi muncul sebagai pop-up
      priority: Priority.high,
    );

    const NotificationDetails platformChannelSpecifics =
        NotificationDetails(android: androidPlatformChannelSpecifics);

    await flutterLocalNotificationsPlugin.show(
      0, // ID notifikasi
      title,
      body,
      platformChannelSpecifics,
      payload: payload,
    );
  }

  /// Menjadwalkan notifikasi agar muncul pada waktu tertentu.
  static Future<void> scheduleNotification({
    required int id,
    required String title,
    required String body,
    required DateTime scheduledTime,
    String? payload,
  }) async {
    final tz.TZDateTime scheduledDate = tz.TZDateTime.from(
      scheduledTime,
      tz.local,
    );

    // Konfigurasi detail notifikasi untuk Android
    const AndroidNotificationDetails androidPlatformChannelSpecifics =
        AndroidNotificationDetails(
      'presensi_channel', // ID Channel, harus unik dan sama dengan di AndroidManifest
      'Notifikasi Presensi',
      channelDescription: 'Channel untuk pengingat presensi.',
      importance: Importance.max, // Memastikan notifikasi muncul sebagai pop-up
      priority: Priority.high,
      ticker: 'ticker',
    );

    // Menggabungkan konfigurasi untuk semua platform
    const NotificationDetails platformChannelSpecifics =
        NotificationDetails(android: androidPlatformChannelSpecifics);

    // Menjadwalkan notifikasi
    await flutterLocalNotificationsPlugin.zonedSchedule(
      id,
      title,
      body,
      scheduledDate,
      platformChannelSpecifics,
      uiLocalNotificationDateInterpretation:
          UILocalNotificationDateInterpretation.absoluteTime,
      matchDateTimeComponents: DateTimeComponents.time, // Ulangi setiap hari pada waktu yang sama
      androidScheduleMode: AndroidScheduleMode.exactAllowWhileIdle,
      payload: payload,
    );
  }

  /// Membatalkan notifikasi berdasarkan ID.
  static Future<void> cancelNotification(int id) async {
    await flutterLocalNotificationsPlugin.cancel(id);
  }

  /// Membatalkan semua notifikasi yang dijadwalkan.
  static Future<void> cancelAllNotifications() async {
    await flutterLocalNotificationsPlugin.cancelAll();
  }
}
