import 'package:firebase_messaging/firebase_messaging.dart';
import 'package:flutter_local_notifications/flutter_local_notifications.dart';

class LocalNotificationService {
  static final FlutterLocalNotificationsPlugin _flutterLocalNotificationsPlugin =
      FlutterLocalNotificationsPlugin();

  // Inisialisasi pengaturan notifikasi
  static Future<void> initializeNotifications() async {
    const AndroidInitializationSettings initializationSettingsAndroid =
        AndroidInitializationSettings('@mipmap/ic_launcher'); // Ikon aplikasi

    final DarwinInitializationSettings initializationSettingsDarwin =
        DarwinInitializationSettings(
      onDidReceiveLocalNotification: (id, title, body, payload) async {
        // Handle notifikasi saat aplikasi iOS di foreground (older iOS)
      },
    );

    final InitializationSettings initializationSettings = InitializationSettings(
      android: initializationSettingsAndroid,
      iOS: initializationSettingsDarwin,
    );

    await _flutterLocalNotificationsPlugin.initialize(
      initializationSettings,
      onDidReceiveNotificationResponse: (NotificationResponse response) async {
        // Handle saat notifikasi diklik
        print('Notifikasi diklik dengan payload: ${response.payload}');
        // Anda bisa menambahkan logika navigasi di sini berdasarkan payload
      },
    );

    // Permintaan izin notifikasi (untuk Android 13+ dan iOS)
    await _flutterLocalNotificationsPlugin
        .resolvePlatformSpecificImplementation<
            AndroidFlutterLocalNotificationsPlugin>()
        ?.requestNotificationsPermission();
  }

  // Menampilkan notifikasi saat aplikasi di foreground
  static void showNotificationOnForeground(RemoteMessage message) {
    const AndroidNotificationDetails androidPlatformChannelSpecifics =
        AndroidNotificationDetails(
      'presensi_channel', // ID channel unik
      'Presensi Notifikasi', // Nama channel
      channelDescription: 'Channel untuk notifikasi presensi',
      importance: Importance.max,
      priority: Priority.high,
      showWhen: false,
      icon: '@mipmap/ic_launcher', // Ikon notifikasi
    );

    const NotificationDetails platformChannelSpecifics =
        NotificationDetails(android: androidPlatformChannelSpecifics);

    _flutterLocalNotificationsPlugin.show(
      0, // ID notifikasi
      message.notification?.title ?? 'Notifikasi Baru',
      message.notification?.body ?? 'Anda memiliki pesan baru.',
      platformChannelSpecifics,
      payload: message.data['payload'] as String?, // Menyimpan data tambahan
    );
  }

  // Menampilkan notifikasi saat aplikasi di latar belakang (jika perlu)
  // Perhatikan bahwa FCM secara otomatis menangani tampilan notifikasi dari payload 'notification'
  // saat aplikasi di background/terminated. Fungsi ini berguna jika Anda ingin kustomisasi
  // tampilan notifikasi dari data payload, atau jika Anda ingin menampilkan notifikasi
  // saat aplikasi di background dan pesan FCM hanya berisi 'data' payload.
  static void showNotificationOnBackground(RemoteMessage message) {
     const AndroidNotificationDetails androidPlatformChannelSpecifics =
        AndroidNotificationDetails(
      'presensi_channel_bg', // ID channel unik untuk background
      'Presensi Notifikasi (Background)', // Nama channel
      channelDescription: 'Channel untuk notifikasi presensi di latar belakang',
      importance: Importance.max,
      priority: Priority.high,
      showWhen: false,
      icon: '@mipmap/ic_launcher',
    );

    const NotificationDetails platformChannelSpecifics =
        NotificationDetails(android: androidPlatformChannelSpecifics);

    _flutterLocalNotificationsPlugin.show(
      1, // ID notifikasi yang berbeda dari foreground
      message.notification?.title ?? 'Notifikasi Baru (Background)',
      message.notification?.body ?? 'Anda memiliki pesan baru di latar belakang.',
      platformChannelSpecifics,
      payload: message.data['payload'] as String?,
    );
  }
}
