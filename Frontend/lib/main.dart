import 'package:Presence/pages/loading_page.dart';
import 'package:flutter/material.dart';
import 'package:firebase_core/firebase_core.dart';
import 'package:firebase_auth/firebase_auth.dart';
import 'package:animations/animations.dart';
import 'package:intl/date_symbol_data_local.dart';
import 'package:flutter/services.dart';
import 'package:shared_preferences/shared_preferences.dart';
import 'dart:async';

import 'firebase_options.dart';
import 'pages/login_page.dart';
import 'pages/home.dart';

void main() async {
  WidgetsFlutterBinding.ensureInitialized();

  await initializeDateFormatting('id_ID');
  await Firebase.initializeApp(options: DefaultFirebaseOptions.currentPlatform);

  await SystemChrome.setPreferredOrientations([
    DeviceOrientation.portraitUp,
  ]).then((_) {
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
    _checkAuthAndNavigate();

    // Listen auth changes untuk handle real-time updates
    FirebaseAuth.instance.authStateChanges().listen((user) {
      if (!mounted) return;
      _checkAuthAndNavigate();
    });
  }

  Future<void> _checkAuthAndNavigate() async {
    // Pastikan LoadingPage tetap terlihat minimal untuk animasi
    await Future.delayed(const Duration(milliseconds: 500));

    final user = FirebaseAuth.instance.currentUser;
    if (user != null) {
      final prefs = await SharedPreferences.getInstance();
      await prefs.reload();
      String username = prefs.getString('username') ?? '';

      if (username.isNotEmpty) {
        debugPrint('User authenticated, username found: $username');
        if (mounted && navigatorKey.currentState != null) {
          unawaited(navigatorKey.currentState!.pushReplacementNamed('/home'));
        }
      } else {
        // Jika prefs kosong, force logout dan ke login
        debugPrint('Username not found, forcing logout');
        await FirebaseAuth.instance.signOut();
        await prefs.clear();
        if (mounted && navigatorKey.currentState != null) {
          unawaited(navigatorKey.currentState!.pushReplacementNamed('/login'));
        }
      }
    } else {
      debugPrint('No user authenticated, navigating to login');
      if (mounted && navigatorKey.currentState != null) {
        unawaited(navigatorKey.currentState!.pushReplacementNamed('/login'));
      }
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
      initialRoute: '/loading',
      routes: {
        '/loading': (context) => const LoadingPage(),
        '/login': (context) => const LoginPage(),
        '/home': (context) => const HomePage(),
      },
    );
  }
}
