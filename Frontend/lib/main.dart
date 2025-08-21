import 'package:flutter/material.dart';
import 'package:firebase_core/firebase_core.dart';
import 'package:firebase_auth/firebase_auth.dart';
import 'package:animations/animations.dart';
import 'package:intl/date_symbol_data_local.dart';
import 'package:firebase_messaging/firebase_messaging.dart'; // Tambahkan ini
import 'package:flutter/services.dart'; // Tambahkan ini untuk SystemChrome

import 'firebase_options.dart';
import 'pages/login_page.dart';
import 'pages/home.dart';
import 'services/local_notification_service.dart'; // Import service notifikasi lokal

// Fungsi top-level untuk menangani pesan latar belakang FCM
// Ini harus dideklarasikan di luar kelas dan tidak boleh anonim atau async
@pragma('vm:entry-point')
Future<void> _firebaseMessagingBackgroundHandler(RemoteMessage message) async {
  await Firebase.initializeApp(options: DefaultFirebaseOptions.currentPlatform); // Pastikan Firebase diinisialisasi untuk background
  print("Menangani pesan latar belakang: ${message.messageId}");

  // Anda bisa memproses pesan di sini, misalnya menyimpan ke database lokal
  // atau menampilkan notifikasi lokal jika tidak otomatis ditangani oleh sistem
  LocalNotificationService.showNotificationOnBackground(message);
}

void main() async {
  WidgetsFlutterBinding.ensureInitialized();
  await initializeDateFormatting('id_ID', null);
  await Firebase.initializeApp(
    options: DefaultFirebaseOptions.currentPlatform,
  );

  // Atur handler untuk pesan latar belakang FCM
  FirebaseMessaging.onBackgroundMessage(_firebaseMessagingBackgroundHandler);

  // Inisialisasi notifikasi lokal
  await LocalNotificationService.initializeNotifications();

  // Opsional: Dapatkan token perangkat saat aplikasi dimulai (untuk debugging/pengujian)
  String? fcmToken = await FirebaseMessaging.instance.getToken();
  print("FCM Token: $fcmToken");

  // Opsional: Langganan ke topik umum (misalnya 'all_users')
  // Ini berguna jika Anda ingin mengirim notifikasi ke semua pengguna aplikasi
  // FirebaseMessaging.instance.subscribeToTopic('all_users');

  // Mengatur preferensi orientasi layar
  SystemChrome.setPreferredOrientations([DeviceOrientation.portraitUp]).then((_) {
    runApp(const MyApp());
  });
}

class MyApp extends StatefulWidget { // Ubah menjadi StatefulWidget
  const MyApp({super.key});

  @override
  State<MyApp> createState() => _MyAppState();
}

class _MyAppState extends State<MyApp> {
  @override
  void initState() {
    super.initState();

    // Listener untuk notifikasi saat aplikasi di foreground (sedang dibuka dan aktif)
    FirebaseMessaging.onMessage.listen((RemoteMessage message) {
      print('Mendapatkan pesan di foreground: ${message.messageId}');
      print('Data pesan: ${message.data}');
      LocalNotificationService.showNotificationOnForeground(message);
    });

    // Listener saat notifikasi diklik dan aplikasi dibuka dari background/terminated state
    FirebaseMessaging.onMessageOpenedApp.listen((RemoteMessage message) {
      print('Pesan diklik dan aplikasi dibuka: ${message.messageId}');
      // Contoh: Navigasi ke LoginPage dan menampilkan pesan notifikasi
      if (message.notification != null) {
        Navigator.push(
          context,
          MaterialPageRoute(
            builder: (context) => LoginPage(
              notificationMessage: "Notifikasi diterima: ${message.notification?.title}",
            ),
          ),
        );
      }
    });

    // Handle initial message (saat aplikasi diluncurkan dari terminated state)
    FirebaseMessaging.instance.getInitialMessage().then((RemoteMessage? message) {
      if (message != null) {
        print('Pesan awal saat aplikasi diluncurkan dari terminated state: ${message.messageId}');
        // Contoh: Navigasi ke LoginPage dan menampilkan pesan notifikasi
        if (message.notification != null) {
          Navigator.push(
            context,
            MaterialPageRoute(
              builder: (context) => LoginPage(
                notificationMessage: "Notifikasi awal: ${message.notification?.title}",
              ),
            ),
          );
        }
      }
    });
  }

  @override
  Widget build(BuildContext context) {
    return MaterialApp(
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
      home: FirebaseAuth.instance.currentUser == null
          ? const LoginPage()
          : const HomePage(),
    );
  }
}
