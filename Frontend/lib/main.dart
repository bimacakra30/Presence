import 'package:flutter/material.dart';
import 'package:firebase_core/firebase_core.dart';
import 'package:firebase_auth/firebase_auth.dart';
import 'package:animations/animations.dart';
import 'package:intl/date_symbol_data_local.dart';
import 'package:firebase_messaging/firebase_messaging.dart';
import 'package:flutter/services.dart';
import 'package:workmanager/workmanager.dart'; // Impor Workmanager

import 'firebase_options.dart';
import 'pages/login_page.dart';
import 'pages/home.dart';
import 'package:Presence/services/local_notification_service.dart';
import 'utils/fcm_token_manager.dart';

// Definisi nama task untuk Workmanager
const simplePeriodicTask = "simplePeriodicTask";

// Callback dispatcher untuk Workmanager (harus top-level)
// Perhatikan bahwa fungsi ini harus menjadi fungsi top-level (di luar kelas mana pun)
// dan memiliki anotasi @pragma('vm:entry-point').
@pragma('vm:entry-point')
void callbackDispatcher() {
  Workmanager().executeTask((task, inputData) async {
    // Pastikan Firebase diinisialisasi di sini juga jika ada operasi Firebase
    // di dalam background task yang tidak selalu dijalankan saat aplikasi aktif.
    // Jika Firebase selalu diinisialisasi di main(), maka ini bisa bersifat opsional
    // tetapi aman untuk disertakan jika task membutuhkan akses Firebase.
    await Firebase.initializeApp(); // Inisialisasi Firebase jika belum

    switch (task) {
      case simplePeriodicTask:
        print("Executing periodic task: $simplePeriodicTask");
        // Di sini Anda memanggil fungsi penjadwalan notifikasi Anda
        await _scheduleDailyPresensiRemindersBackground();
        break;
      default:
        print("Unknown task: $task");
        break;
    }
    return Future.value(true);
  });
}

// Fungsi top-level untuk menangani pesan latar belakang FCM
@pragma('vm:entry-point')
Future<void> _firebaseMessagingBackgroundHandler(RemoteMessage message) async {
  // Pastikan Firebase diinisialisasi untuk background
  await Firebase.initializeApp(options: DefaultFirebaseOptions.currentPlatform);
  print("Menangani pesan latar belakang: ${message.messageId}");

  if (message.notification != null) {
    LocalNotificationService.showNotificationFromFCM(
      title: message.notification!.title ?? '',
      body: message.notification!.body ?? '',
      payload: message.data['page_name'] ?? '',
    );
  }
}

// Fungsi top-level untuk menjadwalkan pengingat presensi harian di Background
@pragma('vm:entry-point') // Penting untuk background task
Future<void> _scheduleDailyPresensiRemindersBackground() async {
  // Inisialisasi notifikasi lokal lagi di dalam background task
  // untuk memastikan ini berfungsi saat aplikasi mati
  await LocalNotificationService.initialize();

  // Batalkan notifikasi lama jika ada, untuk menghindari duplikasi
  await LocalNotificationService.cancelNotification(100); // ID untuk pengingat masuk
  await LocalNotificationService.cancelNotification(101); // ID untuk pengingat pulang

  final now = DateTime.now();

  // Jadwalkan pengingat presensi masuk (misal: pukul 07:45 WIB setiap hari)
  DateTime scheduledCheckIn = DateTime(now.year, now.month, now.day, 7, 45);
  // Jika waktu yang dijadwalkan sudah lewat hari ini, jadwalkan untuk besok
  if (scheduledCheckIn.isBefore(now)) {
    scheduledCheckIn = scheduledCheckIn.add(const Duration(days: 1));
  }

  await LocalNotificationService.scheduleNotification(
    id: 100, // ID unik untuk notifikasi ini
    title: 'Pengingat Presensi Masuk!',
    body: 'Waktunya untuk presensi masuk! Jangan sampai terlambat ya.',
    scheduledTime: scheduledCheckIn,
    payload: 'presensi_masuk_reminder',
  );
  debugPrint('Pengingat presensi masuk dijadwalkan pada: $scheduledCheckIn');

  // Jadwalkan pengingat presensi pulang (misal: pukul 16:45 WIB setiap hari)
  DateTime scheduledCheckOut = DateTime(now.year, now.month, now.day, 16, 45);
  // Jika waktu yang dijadwalkan sudah lewat hari ini, jadwalkan untuk besok
  if (scheduledCheckOut.isBefore(now)) {
    scheduledCheckOut = scheduledCheckOut.add(const Duration(days: 1));
  }

  await LocalNotificationService.scheduleNotification(
    id: 101, // ID unik untuk notifikasi ini
    title: 'Pengingat Presensi Pulang!',
    body: 'Jangan lupa untuk presensi pulang sebelum jam kerja berakhir.',
    scheduledTime: scheduledCheckOut,
    payload: 'presensi_pulang_reminder',
  );
  debugPrint('Pengingat presensi pulang dijadwalkan pada: $scheduledCheckOut');
}


void main() async {
  WidgetsFlutterBinding.ensureInitialized();

  // Inisialisasi Workmanager
  await Workmanager().initialize(
    callbackDispatcher,
    isInDebugMode: true, // Ubah ke false saat rilis
  );
  // Daftarkan task periodik untuk menjadwalkan notifikasi
  // Workmanager akan memastikan task ini berjalan secara periodik
  await Workmanager().registerPeriodicTask(
    simplePeriodicTask, // Nama unik untuk task
    simplePeriodicTask, // Nama unik untuk task
    frequency: const Duration(hours: 12), // Contoh: Jalankan setiap 12 jam untuk mengecek/menjadwalkan ulang notifikasi
    initialDelay: const Duration(seconds: 10), // Mulai setelah 10 detik aplikasi berjalan
    constraints: Constraints(
      networkType: NetworkType.connected, // Task hanya berjalan jika ada koneksi internet
      requiresBatteryNotLow: true, // Task hanya berjalan jika baterai tidak lemah
    ),
  );

  await initializeDateFormatting('id_ID', null);
  await Firebase.initializeApp(
    options: DefaultFirebaseOptions.currentPlatform,
  );

  FirebaseMessaging.onBackgroundMessage(_firebaseMessagingBackgroundHandler);

  await LocalNotificationService.initialize(); // Menggunakan initialize() yang sudah diperbaiki

  SystemChrome.setPreferredOrientations([DeviceOrientation.portraitUp]).then((_) {
    runApp(const MyApp());
  });
}

class MyApp extends StatefulWidget {
  const MyApp({super.key});

  @override
  State<MyApp> createState() => _MyAppState();
}

class _MyAppState extends State<MyApp> {
  final GlobalKey<NavigatorState> navigatorKey = GlobalKey<NavigatorState>();

  @override
  void initState() {
    super.initState();

    FirebaseAuth.instance.authStateChanges().listen((User? user) {
      if (user != null) {
        // Panggil fungsi untuk menyimpan/memperbarui token FCM saat pengguna login
        saveEmployeeFcmTokenToFirestore();
      } else {
        // BATALKAN SEMUA NOTIFIKASI SAAT PENGGUNA LOGOUT
        LocalNotificationService.cancelAllNotifications();
        print("User logged out. All local notifications cancelled.");
      }
    });

    FirebaseMessaging.onMessage.listen((RemoteMessage message) {
      print('Mendapatkan pesan di foreground: ${message.messageId}');
      print('Data pesan: ${message.data}');

      if (message.notification != null) {
        LocalNotificationService.showNotificationFromFCM(
          title: message.notification!.title ?? '',
          body: message.notification!.body ?? '',
          payload: message.data['page_name'] ?? '',
        );
      }
    });

    FirebaseMessaging.onMessageOpenedApp.listen((RemoteMessage message) {
      print('Pesan diklik dan aplikasi dibuka: ${message.messageId}');
      _handleNotificationClick(message);
    });

    FirebaseMessaging.instance.getInitialMessage().then((RemoteMessage? message) {
      if (message != null) {
        print('Pesan awal saat aplikasi diluncurkan dari terminated state: ${message.messageId}');
        _handleNotificationClick(message);
      }
    });
  }

  void _handleNotificationClick(RemoteMessage message) {
    if (message.data['page_name'] == 'login') {
      navigatorKey.currentState?.push(
        MaterialPageRoute(
          builder: (context) => const LoginPage(),
        ),
      );
    } else if (message.data['page_name'] == 'home') {
       navigatorKey.currentState?.push(
        MaterialPageRoute(
          builder: (context) => const HomePage(),
        ),
      );
    } else {
      navigatorKey.currentState?.push(
        MaterialPageRoute(
          builder: (context) => const HomePage(),
        ),
      );
    }
  }

  @override
  Widget build(BuildContext context) {
    return MaterialApp(
      navigatorKey: navigatorKey,
      title: 'Presence App',
      debugShowCheckedModeBanner: false,
      theme: ThemeData(
        colorScheme: ColorScheme.fromSeed(seedColor: Colors.teal),
        useMaterial3: true,
        pageTransitionsTheme: const PageTransitionsTheme(
          builders: {
            TargetPlatform.android: SharedAxisPageTransitionsBuilder(
              transitionType: SharedAxisTransitionType.horizontal,
            ),
            TargetPlatform.iOS: SharedAxisPageTransitionsBuilder(
              transitionType: SharedAxisTransitionType.horizontal,
            ),
          },
        ),
      ),
      home: StreamBuilder<User?>(
        stream: FirebaseAuth.instance.authStateChanges(),
        builder: (context, snapshot) {
          if (snapshot.connectionState == ConnectionState.waiting) {
            return const CircularProgressIndicator();
          }
          if (snapshot.hasData) {
            return const HomePage();
          }
          return const LoginPage();
        },
      ),
    );
  }
}
