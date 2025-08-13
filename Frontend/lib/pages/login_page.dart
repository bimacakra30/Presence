// lib/pages/login_page.dart
import 'package:firebase_auth/firebase_auth.dart';
import 'package:cloud_firestore/cloud_firestore.dart';
import 'package:flutter/material.dart';
import 'package:google_sign_in/google_sign_in.dart';
import 'package:shared_preferences/shared_preferences.dart';
import 'loading_page.dart';
import 'package:Presence/components/dialog/forgot_password_dialog.dart';
import 'home.dart';
import 'package:bcrypt/bcrypt.dart';

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
  final emailController = TextEditingController();
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

    Navigator.push(
      context,
      MaterialPageRoute(builder: (_) => const LoadingPage()),
    );

    try {
      final query = await FirebaseFirestore.instance
          .collection('employees')
          .where('username', isEqualTo: username)
          .limit(1)
          .get();

      if (query.docs.isEmpty) {
        Navigator.pop(context);
        setState(() {
          usernameError = "Username tidak ditemukan";
          isLoading = false;
        });
        return;
      }

      final userDoc = query.docs.first;
      final hashedPassword = userDoc['password'];
      final userEmailFromFirestore = userDoc['email'];

      final isMatch = BCrypt.checkpw(password, hashedPassword);

      if (!isMatch) {
        Navigator.pop(context);
        setState(() {
          passwordError = "Password salah";
          isLoading = false;
        });
        return;
      }

      UserCredential userCredential;
      try {
        userCredential = await FirebaseAuth.instance.signInWithEmailAndPassword(
          email: userEmailFromFirestore,
          password: password,
        );
      } on FirebaseAuthException catch (e) {
        if (!mounted) return;
        Navigator.pop(context);
        setState(() {
          passwordError = "Gagal otentikasi Firebase: ${e.message}";
          isLoading = false;
        });
        return;
      }

      final firebaseUser = userCredential.user;
      if (firebaseUser == null) {
        if (!mounted) Navigator.pop(context);
        setState(() {
          usernameError = "Gagal login Firebase Auth (pengguna null)";
          isLoading = false;
        });
        return;
      }

      final prefs = await SharedPreferences.getInstance();
      await prefs.setString('name', userDoc['name'] ?? '');
      await prefs.setString('email', userDoc['email'] ?? '');
      await prefs.setString('username', userDoc['username'] ?? '');
      await prefs.setString(
        'profilePictureUrl',
        userDoc['profilePictureUrl'] ?? '',
      );

      if (!userDoc.data().containsKey('uid') || userDoc['uid'] == null) {
        Navigator.pop(context);
        setState(() {
          usernameError = "Akun tidak valid (uid tidak ditemukan)";
          isLoading = false;
        });
        return;
      }
      await prefs.setString('uid', userDoc['uid']);

      await Future.delayed(const Duration(seconds: 1));

      Navigator.pushAndRemoveUntil(
        context,
        MaterialPageRoute(builder: (_) => const HomePage()),
        (route) => false,
      );
    } catch (e) {
      Navigator.pop(context);
      setState(() {
        passwordError = "Terjadi kesalahan: ${e.toString()}";
        isLoading = false;
      });
    }
  }

  Future<void> loginWithGoogle() async {
    Navigator.push(
      context,
      MaterialPageRoute(builder: (_) => const LoadingPage()),
    );

    try {
      final googleSignIn = GoogleSignIn();
      await googleSignIn.signOut(); // Optional: memastikan login ulang

      final GoogleSignInAccount? googleUser = await googleSignIn.signIn();
      if (googleUser == null) {
        Navigator.pop(context);
        return;
      }

      final email = googleUser.email;

      // Cek apakah email terdaftar di Firestore
      final query = await FirebaseFirestore.instance
          .collection('employees')
          .where('email', isEqualTo: email)
          .limit(1)
          .get();

      if (query.docs.isEmpty) {
        Navigator.pop(context);
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(content: Text("Akun Google belum terdaftar")),
        );
        return;
      }

      final googleAuth = await googleUser.authentication;
      final credential = GoogleAuthProvider.credential(
        accessToken: googleAuth.accessToken,
        idToken: googleAuth.idToken,
      );

      // Login ke Firebase Auth
      await FirebaseAuth.instance.signInWithCredential(credential);

      // Simpan nama dan email ke SharedPreferences
      final userDoc = query.docs.first;
      final prefs = await SharedPreferences.getInstance();
      await prefs.setString('name', userDoc['name'] ?? '');
      await prefs.setString('email', userDoc['email'] ?? '');
      await prefs.setString('profilePictureUrl', userDoc['profilePictureUrl'] ?? '');

      await Future.delayed(const Duration(seconds: 2));

      Navigator.pushAndRemoveUntil(
        context,
        MaterialPageRoute(builder: (_) => const HomePage()),
        (route) => false,
      );
    } catch (e) {
      Navigator.pop(context);
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text("Login Google gagal: ${e.toString()}")),
      );
    }
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
                        showDialog(
                          context: context,
                          builder: (context) => const ForgotPasswordDialog(),
                        );
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
                    child: OutlinedButton.icon(
                      onPressed: isLoading ? null : loginWithGoogle,
                      icon: Image.asset(
                        'assets/images/google_logo.png',
                        height: 20,
                      ),
                      label: const Text("Continue with Google"),
                      style: OutlinedButton.styleFrom(
                        padding: const EdgeInsets.symmetric(vertical: 14),
                        shape: RoundedRectangleBorder(
                          borderRadius: BorderRadius.circular(12),
                        ),
                      ),
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
