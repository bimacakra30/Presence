// lib/pages/login_page.dart
import 'package:firebase_auth/firebase_auth.dart';
import 'package:cloud_firestore/cloud_firestore.dart';
import 'package:flutter/material.dart';
import 'package:google_sign_in/google_sign_in.dart';
import 'package:shared_preferences/shared_preferences.dart';
import 'loading_page.dart';
import 'package:Presence/components/dialog/forgot_password_dialog.dart';
import 'home.dart';
import 'package:bcrypt/bcrypt.dart'; // Pastikan package ini tersedia

class LoginPage extends StatefulWidget {
  final String? notificationMessage; // <--- Tambahkan properti ini
  const LoginPage({
    super.key,
    this.notificationMessage,
  }); // <--- Perbarui konstruktor

  @override
  State<LoginPage> createState() => _LoginPageState();
}

class _LoginPageState extends State<LoginPage> {
  final emailController =
      TextEditingController(); // Menggunakan ini sebagai controller username
  final passwordController = TextEditingController();
  bool isLoading = false;
  bool isObscured = true;

  String? usernameError;
  String? passwordError;

  @override
  void initState() {
    super.initState();
    // Tampilkan notifikasi jika ada pesan
    if (widget.notificationMessage != null) {
      WidgetsBinding.instance.addPostFrameCallback((_) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            content: Text(widget.notificationMessage!),
            backgroundColor: Colors.green,
          ),
        );
      });
    }
  }

  @override
  void dispose() {
    emailController.dispose();
    passwordController.dispose();
    super.dispose();
  }

  Future<void> login() async {
    final username = emailController.text.trim();
    final password = passwordController.text.trim();

    setState(() {
      usernameError = username.isEmpty ? 'Username wajib diisi' : null;
      passwordError = password.isEmpty ? 'Password wajib diisi' : null;
      isLoading = true;
    });

    if (usernameError != null || passwordError != null) {
      setState(() => isLoading = false);
      return;
    }

    if (!mounted) return;
    Navigator.push(
      context,
      MaterialPageRoute(builder: (_) => const LoadingPage()),
    );

    try {
      // Cari user berdasarkan username
      final query = await FirebaseFirestore.instance
          .collection('employees')
          .where('username', isEqualTo: username)
          .limit(1)
          .get();

      if (query.docs.isEmpty) {
        if (Navigator.canPop(context)) Navigator.pop(context);
        setState(() {
          usernameError = "Username tidak ditemukan";
          isLoading = false;
        });
        return;
      }

      final userDoc = query.docs.first;
      final data = userDoc.data();
      final hashedPassword = data['password'] ?? '';

      // Cocokkan BCrypt
      final isMatch = BCrypt.checkpw(password, hashedPassword);
      if (!isMatch) {
        if (Navigator.canPop(context)) Navigator.pop(context);
        setState(() {
          passwordError = "Password salah";
          isLoading = false;
        });
        return;
      }

      // Simpan data ke SharedPreferences
      final prefs = await SharedPreferences.getInstance();
      await prefs.setString('name', data['name'] ?? '');
      await prefs.setString('email', data['email'] ?? '');
      await prefs.setString('username', data['username'] ?? '');
      await prefs.setString(
        'profilePictureUrl',
        data['profilePictureUrl'] ?? data['profilePictureUrl'] ?? '',
      );
      await prefs.setString('position', data['position'] ?? '');
      await prefs.setString('status', data['status'] ?? '');
      await prefs.setString('address', data['address'] ?? '');
      await prefs.setString('dateOfBirth', data['dateOfBirth'] ?? '');
      await prefs.setString('provider', data['provider'] ?? 'manual');
      await prefs.setString('uid', data['uid'] ?? '');
      await prefs.setString('createdAt', data['createdAt'] ?? '');

      if (!mounted) return;
      Navigator.pushAndRemoveUntil(
        context,
        MaterialPageRoute(builder: (_) => const HomePage()),
        (route) => false,
      );
    } catch (e) {
      if (Navigator.canPop(context)) Navigator.pop(context);
      setState(() {
        passwordError = "Terjadi kesalahan: ${e.toString()}";
        isLoading = false;
      });
    }
  }

  Future<void> loginWithGoogle() async {
    if (!mounted) return;
    Navigator.push(
      context,
      MaterialPageRoute(builder: (_) => const LoadingPage()),
    );

    try {
      final googleSignIn = GoogleSignIn();
      await googleSignIn.signOut(); // pastikan fresh login

      final GoogleSignInAccount? googleUser = await googleSignIn.signIn();
      if (googleUser == null) {
        if (Navigator.canPop(context)) Navigator.pop(context);
        return;
      }

      final googleAuth = await googleUser.authentication;
      final credential = GoogleAuthProvider.credential(
        accessToken: googleAuth.accessToken,
        idToken: googleAuth.idToken,
      );

      final UserCredential userCredential = await FirebaseAuth.instance
          .signInWithCredential(credential);

      final firebaseUser = userCredential.user;
      if (firebaseUser == null) {
        if (Navigator.canPop(context)) Navigator.pop(context);
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(
            content: Text("Login Google gagal"),
            backgroundColor: Colors.red,
          ),
        );
        return;
      }

      // Cek apakah user sudah ada di Firestore
      final userDocRef = FirebaseFirestore.instance
          .collection('employees')
          .doc(firebaseUser.uid);
      final userDoc = await userDocRef.get();

      if (!userDoc.exists) {
        // Buat dokumen baru
        await userDocRef.set({
          'uid': firebaseUser.uid,
          'name': firebaseUser.displayName ?? '',
          'email': firebaseUser.email ?? '',
          'username': firebaseUser.email?.split('@')[0] ?? '',
          'profilePictureUrl': firebaseUser.photoURL ?? '',
          'position': '',
          'status': 'aktif',
          'address': '',
          'dateOfBirth': '',
          'provider': 'google',
          'createdAt': DateTime.now().toIso8601String(),
        });
      } else {
        // Update foto profil kalau ada
        await userDocRef.update({
          'profilePictureUrl': firebaseUser.photoURL ?? '',
        });
      }

      // Ambil data terbaru
      final updatedDoc = await userDocRef.get();
      final data = updatedDoc.data() as Map<String, dynamic>;

      // Simpan ke SharedPreferences
      final prefs = await SharedPreferences.getInstance();
      await prefs.setString('name', data['name'] ?? '');
      await prefs.setString('email', data['email'] ?? '');
      await prefs.setString('username', data['username'] ?? '');
      await prefs.setString(
        'profilePictureUrl',
        data['profilePictureUrl'] ?? '',
      );
      await prefs.setString('position', data['position'] ?? '');
      await prefs.setString('status', data['status'] ?? '');
      await prefs.setString('address', data['address'] ?? '');
      await prefs.setString(
        'dateOfBirth',
        data['dateOfBirth'] ?? '',
      );
      await prefs.setString('provider', data['provider'] ?? 'google');
      await prefs.setString('uid', data['uid'] ?? '');
      await prefs.setString('createdAt', data['createdAt'] ?? '');

      if (!mounted) return;
      Navigator.pushAndRemoveUntil(
        context,
        MaterialPageRoute(builder: (_) => const HomePage()),
        (route) => false,
      );
    } catch (e) {
      if (Navigator.canPop(context)) Navigator.pop(context);
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text("Google login error: ${e.toString()}"),
          backgroundColor: Colors.red,
        ),
      );
    }
  }

  void _showForgotPasswordDialog() {
    showDialog(
      context: context,
      builder: (context) => const ForgotPasswordDialog(),
    );
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      body: Container(
        decoration: const BoxDecoration(
          gradient: LinearGradient(
            colors: [Color(0xFF006989), Color(0xFFA3BAC3)],
            begin: Alignment.topLeft,
            end: Alignment.bottomRight,
          ),
        ),
        child: Center(
          child: SingleChildScrollView(
            child: Container(
              margin: const EdgeInsets.all(24),
              padding: const EdgeInsets.symmetric(horizontal: 24, vertical: 32),
              decoration: BoxDecoration(
                color: Colors.white,
                borderRadius: BorderRadius.circular(24),
                boxShadow: [
                  BoxShadow(
                    color: Colors.black.withOpacity(0.1),
                    blurRadius: 12,
                    offset: const Offset(0, 8),
                  ),
                ],
              ),
              child: Column(
                mainAxisSize: MainAxisSize.min,
                children: [
                  const Text(
                    'Sign In',
                    style: TextStyle(fontSize: 26, fontWeight: FontWeight.bold),
                  ),
                  const SizedBox(height: 24),
                  TextField(
                    controller: emailController,
                    decoration: InputDecoration(
                      prefixIcon: const Icon(Icons.person_outline),
                      hintText: "Username",
                      errorText: usernameError,
                      border: OutlineInputBorder(
                        borderRadius: BorderRadius.circular(12),
                      ),
                    ),
                  ),
                  const SizedBox(height: 16),
                  TextField(
                    controller: passwordController,
                    obscureText: isObscured,
                    decoration: InputDecoration(
                      prefixIcon: const Icon(Icons.lock_outline),
                      suffixIcon: IconButton(
                        icon: Icon(
                          isObscured ? Icons.visibility_off : Icons.visibility,
                        ),
                        onPressed: () {
                          setState(() {
                            isObscured = !isObscured;
                          });
                        },
                      ),
                      hintText: "Password",
                      errorText: passwordError,
                      border: OutlineInputBorder(
                        borderRadius: BorderRadius.circular(12),
                      ),
                    ),
                  ),
                  const SizedBox(height: 8),
                  Align(
                    alignment: Alignment.centerRight,
                    child: TextButton(
                      onPressed: () {
                        _showForgotPasswordDialog();
                      },
                      child: const Text(
                        "Forgot Password?",
                        style: TextStyle(
                          color: Colors.blue,
                          fontWeight: FontWeight.w500,
                        ),
                      ),
                    ),
                  ),
                  const SizedBox(height: 12),
                  SizedBox(
                    width: double.infinity,
                    child: ElevatedButton(
                      onPressed: isLoading ? null : login,
                      style: ElevatedButton.styleFrom(
                        backgroundColor: const Color(0xFF00B3D4),
                        padding: const EdgeInsets.symmetric(vertical: 14),
                        shape: RoundedRectangleBorder(
                          borderRadius: BorderRadius.circular(12),
                        ),
                      ),
                      child: isLoading
                          ? const CircularProgressIndicator(color: Colors.white)
                          : const Text(
                              "Sign In",
                              style: TextStyle(color: Colors.white),
                            ),
                    ),
                  ),
                  const SizedBox(height: 16),
                  Row(
                    children: const [
                      Expanded(child: Divider()),
                      Padding(
                        padding: EdgeInsets.symmetric(horizontal: 8.0),
                        child: Text("Or"),
                      ),
                      Expanded(child: Divider()),
                    ],
                  ),
                  const SizedBox(height: 16),
                  SizedBox(
                    width: double.infinity,
                    child: OutlinedButton(
                      onPressed: isLoading ? null : loginWithGoogle,
                      style: OutlinedButton.styleFrom(
                        padding: const EdgeInsets.symmetric(vertical: 14),
                        shape: RoundedRectangleBorder(
                          borderRadius: BorderRadius.circular(12),
                        ),
                      ),
                      child: const Text("Continue with Google"),
                    ),
                  ),
                  const SizedBox(height: 8),
                ],
              ),
            ),
          ),
        ),
      ),
    );
  }
}
