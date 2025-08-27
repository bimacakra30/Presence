import 'package:flutter/material.dart';
import 'package:firebase_core/firebase_core.dart';
import 'package:firebase_auth/firebase_auth.dart';
import 'package:animations/animations.dart';
import 'package:intl/date_symbol_data_local.dart';
import 'package:firebase_messaging/firebase_messaging.dart';
import 'package:flutter/services.dart';

import 'firebase_options.dart';
import 'pages/login_page.dart';
import 'pages/home.dart';
import 'services/local_notification_service.dart';
import 'utils/fcm_token_manager.dart';

// Fungsi top-level untuk menangani pesan latar belakang FCM
// Ini harus dideklarasikan di luar kelas dan tidak boleh anonim atau async
@pragma('vm:entry-point')
Future<void> _firebaseMessagingBackgroundHandler(RemoteMessage message) async {
  // Pastikan Firebase diinisialisasi untuk background
  await Firebase.initializeApp(options: DefaultFirebaseOptions.currentPlatform);
  print("Menangani pesan latar belakang: ${message.messageId}");

  // Logika untuk menampilkan notifikasi lokal dari background
  // Cek apakah notifikasi ada di dalam pesan FCM
  if (message.notification != null) {
    // Panggil fungsi untuk menampilkan notifikasi lokal
    LocalNotificationService.showNotificationFromFCM(
      title: message.notification!.title ?? '',
      body: message.notification!.body ?? '',
      payload: message.data['page_name'] ?? '', // Menggunakan data payload untuk navigasi
    );
  }
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
  await LocalNotificationService.initialize();

  // Atur preferensi orientasi layar
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

    // Call function untuk save/update token FCM saat user login
    FirebaseAuth.instance.authStateChanges().listen((User? user) {
      if (user != null) {
        saveEmployeeFcmTokenToFirestore();
      }
    });

    // Listener untuk notifikasi saat aplikasi di foreground
    FirebaseMessaging.onMessage.listen((RemoteMessage message) {
      print('Mendapatkan pesan di foreground: ${message.messageId}');
      print('Data pesan: ${message.data}');

      // Tampilkan notifikasi lokal
      if (message.notification != null) {
        LocalNotificationService.showNotificationFromFCM(
          title: message.notification!.title ?? '',
          body: message.notification!.body ?? '',
          payload: message.data['page_name'] ?? '',
        );
      }
    });

    // Listener saat notifikasi diklik dan aplikasi dibuka dari background/terminated state
    FirebaseMessaging.onMessageOpenedApp.listen((RemoteMessage message) {
      print('Pesan diklik dan aplikasi dibuka: ${message.messageId}');
      _handleNotificationClick(message);
    });

    // Handle initial message (saat aplikasi diluncurkan dari terminated state)
    FirebaseMessaging.instance.getInitialMessage().then((RemoteMessage? message) {
      if (message != null) {
        print('Pesan awal saat aplikasi diluncurkan dari terminated state: ${message.messageId}');
        _handleNotificationClick(message);
      }
    });
  }

  // Fungsi untuk menangani navigasi dari notifikasi
  void _handleNotificationClick(RemoteMessage message) {
    // Navigasi ke halaman login jika payload-nya 'login'
    if (message.data['page_name'] == 'login') {
      navigatorKey.currentState?.push(
        MaterialPageRoute(
          builder: (context) => const LoginPage(),
        ),
      );
    } 
    // Navigasi ke halaman home jika payload-nya 'home'
    else if (message.data['page_name'] == 'home') {
       navigatorKey.currentState?.push(
        MaterialPageRoute(
          builder: (context) => const HomePage(),
        ),
      );
    }
    // Navigasi ke halaman default jika tidak ada payload yang cocok
    else {
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
      navigatorKey: navigatorKey, // Gunakan GlobalKey di sini
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
            return const CircularProgressIndicator(); // Tampilkan loading
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
