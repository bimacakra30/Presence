import 'package:firebase_auth/firebase_auth.dart';
import 'package:cloud_firestore/cloud_firestore.dart';
import 'package:flutter/material.dart';
import 'package:google_sign_in/google_sign_in.dart';
import 'package:shared_preferences/shared_preferences.dart';
import 'package:connectivity_plus/connectivity_plus.dart';
import 'loading_page.dart';
import 'home.dart';
import 'package:Presence/components/dialog/forgot_password_dialog.dart';

class LoginPage extends StatefulWidget {
  final String? notificationMessage;
  const LoginPage({super.key, this.notificationMessage});

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

  Future<bool> checkConnectivity() async {
    var connectivityResult = await Connectivity().checkConnectivity();
    return connectivityResult != ConnectivityResult.none;
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
      if (!(await checkConnectivity())) {
        if (!mounted) return;
        if (Navigator.canPop(context)) Navigator.pop(context);
        setState(() {
          passwordError = "Tidak ada koneksi internet.";
          isLoading = false;
        });
        return;
      }

      debugPrint('Mencari username: $username di Firestore');
      final query = await FirebaseFirestore.instance
          .collection('employees')
          .where('username', isEqualTo: username)
          .limit(1)
          .get();

      if (query.docs.isEmpty) {
        debugPrint('Username $username tidak ditemukan di Firestore');
        if (!mounted) return;
        if (Navigator.canPop(context)) Navigator.pop(context);
        setState(() {
          usernameError = "Username tidak ditemukan";
          isLoading = false;
        });
        return;
      }

      final userDoc = query.docs.first;
      final userEmail = userDoc['email'];

      debugPrint('Mencoba login Firebase Auth dengan email: $userEmail');
      final userCredential = await FirebaseAuth.instance
          .signInWithEmailAndPassword(email: userEmail, password: password);

      final firebaseUser = userCredential.user;
      if (firebaseUser == null) {
        debugPrint('Firebase user null setelah autentikasi');
        if (!mounted) return;
        if (Navigator.canPop(context)) Navigator.pop(context);
        setState(() {
          usernameError = "Gagal login: Pengguna tidak ditemukan";
          isLoading = false;
        });
        return;
      }
      debugPrint('Berhasil login Firebase Auth. UID: ${firebaseUser.uid}');

      final employeeDocRef = FirebaseFirestore.instance
          .collection('employees')
          .doc(firebaseUser.uid);
      final employeeDocSnapshot = await employeeDocRef.get();

      if (!employeeDocSnapshot.exists) {
        debugPrint(
          'Dokumen Firestore untuk UID ${firebaseUser.uid} tidak ditemukan. Membuat baru.',
        );
        await employeeDocRef.set({
          ...userDoc.data(),
          'uid': firebaseUser.uid,
          'email': firebaseUser.email ?? userDoc['email'],
          'provider': 'email/password',
        });
        if (userDoc.id != firebaseUser.uid) {
          debugPrint('Menghapus dokumen lama dengan ID: ${userDoc.id}');
          await userDoc.reference.delete();
        }
      } else {
        debugPrint(
          'Dokumen Firestore untuk UID ${firebaseUser.uid} ditemukan. Memperbarui data.',
        );
        final existingData = employeeDocSnapshot.data() as Map<String, dynamic>;
        final updates = <String, dynamic>{};
        if (existingData['uid'] != firebaseUser.uid) {
          updates['uid'] = firebaseUser.uid;
        }
        if (existingData['email'] != firebaseUser.email) {
          updates['email'] = firebaseUser.email;
        }
        if (existingData['provider'] != 'email/password') {
          updates['provider'] = 'email/password';
        }
        if (updates.isNotEmpty) {
          await employeeDocRef.update(updates);
          debugPrint('Memperbarui dokumen Firestore dengan: $updates');
        }
      }

      final finalUserDoc = await employeeDocRef.get();
      final dataToSave = finalUserDoc.data();

      if (dataToSave == null) {
        debugPrint('Data Firestore kosong setelah sinkronisasi');
        if (!mounted) return;
        if (Navigator.canPop(context)) Navigator.pop(context);
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(
            content: Text("Error: Data profil tidak ditemukan"),
            backgroundColor: Colors.red,
          ),
        );
        setState(() => isLoading = false);
        return;
      }
      debugPrint('Data Firestore final: $dataToSave');

      final prefs = await SharedPreferences.getInstance();
      await prefs.setString('name', dataToSave['name'] ?? 'Unknown');
      await prefs.setString('email', dataToSave['email'] ?? '');
      await prefs.setString('username', dataToSave['username'] ?? '');
      await prefs.setString(
        'profilePictureUrl',
        dataToSave['profilePictureUrl'] ?? '',
      );
      await prefs.setString('position', dataToSave['position'] ?? '');
      await prefs.setString('status', dataToSave['status'] ?? 'aktif');
      await prefs.setString('address', dataToSave['address'] ?? '');
      await prefs.setString('dateOfBirth', dataToSave['dateOfBirth'] ?? '');
      await prefs.setString(
        'provider',
        dataToSave['provider'] ?? 'email/password',
      );
      await prefs.setString('uid', dataToSave['uid'] ?? firebaseUser.uid);
      await prefs.setString(
        'createdAt',
        dataToSave['createdAt'] ?? DateTime.now().toIso8601String(),
      );

      debugPrint('Data disimpan ke SharedPreferences. Menuju HomePage.');
      await Future.delayed(const Duration(seconds: 1));

      if (!mounted) return;
      Navigator.pushAndRemoveUntil(
        context,
        MaterialPageRoute(builder: (_) => const HomePage()),
        (route) => false,
      );
    } on FirebaseAuthException catch (e) {
      debugPrint('Firebase Auth error: ${e.code} - ${e.message}');
      if (!mounted) return;
      if (Navigator.canPop(context)) Navigator.pop(context);
      String errorMessage;
      if (e.code == 'user-not-found') {
        errorMessage = 'Akun tidak ditemukan. Silakan daftar terlebih dahulu.';
      } else if (e.code == 'wrong-password') {
        errorMessage = 'Password yang Anda masukkan salah.';
      } else if (e.code == 'invalid-credential') {
        errorMessage = 'Password yang anda masukkan salah.';
        _showResetDialog(e.email ?? '');
      } else if (e.code == 'network-request-failed') {
        errorMessage = 'Koneksi jaringan bermasalah. Silakan coba lagi.';
      } else {
        errorMessage = 'Gagal login: ${e.message}';
      }
      setState(() {
        passwordError = errorMessage;
        isLoading = false;
      });
    } catch (e) {
      debugPrint('Error umum saat login: $e');
      if (!mounted) return;
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
      if (!(await checkConnectivity())) {
        if (!mounted) return;
        if (Navigator.canPop(context)) Navigator.pop(context);
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(
            content: Text("Tidak ada koneksi internet."),
            backgroundColor: Colors.red,
          ),
        );
        return;
      }

      debugPrint('Mencoba Google Sign-In');
      final googleSignIn = GoogleSignIn();
      await googleSignIn.signOut();
      final googleUser = await googleSignIn.signIn();

      if (googleUser == null) {
        debugPrint('Google Sign-In dibatalkan oleh pengguna');
        if (!mounted) return;
        if (Navigator.canPop(context)) Navigator.pop(context);
        return;
      }
      debugPrint('Google user diperoleh: ${googleUser.email}');

      final googleAuth = await googleUser.authentication;
      final credential = GoogleAuthProvider.credential(
        accessToken: googleAuth.accessToken,
        idToken: googleAuth.idToken,
      );

      debugPrint('Mencoba Firebase Auth signInWithCredential');
      final userCredential = await FirebaseAuth.instance.signInWithCredential(
        credential,
      );
      final firebaseUser = userCredential.user;

      if (firebaseUser == null) {
        debugPrint('Firebase user null setelah signInWithCredential');
        if (!mounted) return;
        if (Navigator.canPop(context)) Navigator.pop(context);
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(
            content: Text("Gagal login Google: Pengguna tidak ditemukan"),
            backgroundColor: Colors.red,
          ),
        );
        return;
      }
      debugPrint('Firebase user UID: ${firebaseUser.uid}');

      // Periksa apakah akun memiliki kredensial email/password
      final authProviders = await firebaseUser.providerData;
      bool hasPasswordProvider = authProviders.any(
        (provider) => provider.providerId == 'password',
      );

      if (!hasPasswordProvider &&
          userCredential.additionalUserInfo?.isNewUser != true) {
        // Panggil dialog untuk setel password
        await _showSetPasswordDialog(
          firebaseUser.email ?? '',
        ); // Sekarang aman dengan Future<void>
      }

      // Sinkronkan dokumen Firestore
      final employeeDocRef = FirebaseFirestore.instance
          .collection('employees')
          .doc(firebaseUser.uid);
      final employeeDocSnapshot = await employeeDocRef.get();

      if (!employeeDocSnapshot.exists) {
        debugPrint(
          'Dokumen Firestore untuk UID ${firebaseUser.uid} tidak ditemukan. Membuat baru.',
        );
        final queryByEmail = await FirebaseFirestore.instance
            .collection('employees')
            .where('email', isEqualTo: firebaseUser.email)
            .limit(1)
            .get();

        if (queryByEmail.docs.isNotEmpty) {
          final oldDoc = queryByEmail.docs.first;
          final oldData = oldDoc.data();
          await employeeDocRef.set(oldData);
          debugPrint('Migrasi dokumen lama ke UID: ${firebaseUser.uid}');
          if (oldDoc.id != firebaseUser.uid) {
            debugPrint('Menghapus dokumen lama dengan ID: ${oldDoc.id}');
            await oldDoc.reference.delete();
          }
        } else {
          await employeeDocRef.set({
            'uid': firebaseUser.uid,
            'name': firebaseUser.displayName ?? 'Unknown',
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
          debugPrint(
            'Membuat dokumen Firestore baru untuk UID: ${firebaseUser.uid}',
          );
        }
      } else {
        debugPrint(
          'Dokumen Firestore untuk UID ${firebaseUser.uid} ditemukan. Memperbarui data.',
        );
        final existingData = employeeDocSnapshot.data() as Map<String, dynamic>;
        final updates = <String, dynamic>{};
        if (existingData['uid'] != firebaseUser.uid) {
          updates['uid'] = firebaseUser.uid;
        }
        if (existingData['email'] != firebaseUser.email) {
          updates['email'] = firebaseUser.email;
        }
        if (firebaseUser.photoURL != null &&
            existingData['profilePictureUrl'] != firebaseUser.photoURL) {
          updates['profilePictureUrl'] = firebaseUser.photoURL;
        }
        if (existingData['provider'] != 'google') {
          updates['provider'] = 'google';
        }
        if (existingData['name'] != firebaseUser.displayName) {
          updates['name'] = firebaseUser.displayName;
        }
        if (updates.isNotEmpty) {
          await employeeDocRef.update(updates);
          debugPrint('Memperbarui dokumen Firestore dengan: $updates');
        }
      }

      final finalUserDoc = await employeeDocRef.get();
      final dataToSave = finalUserDoc.data();

      if (dataToSave == null) {
        debugPrint('Data Firestore kosong setelah login Google');
        if (!mounted) return;
        if (Navigator.canPop(context)) Navigator.pop(context);
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(
            content: Text("Error: Data profil tidak ditemukan"),
            backgroundColor: Colors.red,
          ),
        );
        return;
      }
      debugPrint('Data Firestore final untuk Google user: $dataToSave');

      final prefs = await SharedPreferences.getInstance();
      await prefs.setString('name', dataToSave['name'] ?? 'Unknown');
      await prefs.setString('email', dataToSave['email'] ?? '');
      await prefs.setString('username', dataToSave['username'] ?? '');
      await prefs.setString(
        'profilePictureUrl',
        dataToSave['profilePictureUrl'] ?? '',
      );
      await prefs.setString('position', dataToSave['position'] ?? '');
      await prefs.setString('status', dataToSave['status'] ?? 'aktif');
      await prefs.setString('address', dataToSave['address'] ?? '');
      await prefs.setString('dateOfBirth', dataToSave['dateOfBirth'] ?? '');
      await prefs.setString('provider', dataToSave['provider'] ?? 'google');
      await prefs.setString('uid', dataToSave['uid'] ?? firebaseUser.uid);
      await prefs.setString(
        'createdAt',
        dataToSave['createdAt'] ?? DateTime.now().toIso8601String(),
      );

      debugPrint(
        'Data disimpan ke SharedPreferences untuk Google user. Menuju HomePage.',
      );
      await Future.delayed(const Duration(seconds: 2));

      if (!mounted) return;
      Navigator.pushAndRemoveUntil(
        context,
        MaterialPageRoute(builder: (_) => const HomePage()),
        (route) => false,
      );
    } on FirebaseAuthException catch (e) {
      debugPrint(
        'Firebase Auth error (Google Sign-In): ${e.code} - ${e.message}',
      );
      if (!mounted) return;
      if (Navigator.canPop(context)) Navigator.pop(context);
      String errorMessage;
      if (e.code == 'account-exists-with-different-credential') {
        errorMessage =
            'Akun sudah ada dengan metode lain. Silakan login dengan metode yang sesuai atau hubungkan akun.';
      } else if (e.code == 'invalid-credential') {
        errorMessage = 'Kredensial Google tidak valid. Silakan coba lagi.';
      } else if (e.code == 'network-request-failed') {
        errorMessage = 'Koneksi jaringan bermasalah. Silakan coba lagi.';
      } else {
        errorMessage = 'Gagal login Google: ${e.message}';
      }
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text(errorMessage), backgroundColor: Colors.red),
      );
    } catch (e) {
      debugPrint('Error umum saat login Google: $e');
      if (!mounted) return;
      if (Navigator.canPop(context)) Navigator.pop(context);
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text("Terjadi kesalahan saat login Google: ${e.toString()}"),
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

  void _showResetDialog(String email) {
    showDialog(
      context: context,
      barrierDismissible: false, // Mencegah dismiss dengan tap di luar
      builder: (context) => AlertDialog(
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(16)),
        elevation: 8,
        backgroundColor: Colors.white,
        title: Row(
          children: [
            Container(
              padding: const EdgeInsets.all(8),
              decoration: BoxDecoration(
                color: Colors.orange.shade50,
                borderRadius: BorderRadius.circular(8),
              ),
              child: Icon(
                Icons.account_circle_outlined,
                color: Colors.orange.shade600,
                size: 24,
              ),
            ),
            const SizedBox(width: 12),
            const Expanded(
              child: Text(
                'Akun Tidak Ditemukan',
                style: TextStyle(
                  fontSize: 18,
                  fontWeight: FontWeight.w600,
                  color: Color(0xFF2D3748),
                ),
              ),
            ),
          ],
        ),
        content: Container(
          constraints: const BoxConstraints(maxWidth: 300),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(
                'Sepertinya akun dengan email $email hanya terkait dengan Google.',
                style: const TextStyle(
                  fontSize: 14,
                  color: Color(0xFF4A5568),
                  height: 1.4,
                ),
              ),
              const SizedBox(height: 16),
              Container(
                padding: const EdgeInsets.all(12),
                decoration: BoxDecoration(
                  color: Colors.blue.shade50,
                  borderRadius: BorderRadius.circular(8),
                  border: Border.all(color: Colors.blue.shade200, width: 1),
                ),
                child: Row(
                  children: [
                    Icon(
                      Icons.info_outline,
                      color: Colors.blue.shade600,
                      size: 20,
                    ),
                    const SizedBox(width: 8),
                    const Expanded(
                      child: Text(
                        'Anda dapat mereset password untuk mengatur ulang akses',
                        style: TextStyle(
                          fontSize: 13,
                          color: Color(0xFF2B6CB0),
                          fontWeight: FontWeight.w500,
                        ),
                      ),
                    ),
                  ],
                ),
              ),
            ],
          ),
        ),
        actionsPadding: const EdgeInsets.fromLTRB(16, 8, 16, 16),
        actions: [
          Row(
            children: [
              Expanded(
                child: TextButton(
                  onPressed: () => Navigator.pop(context),
                  style: TextButton.styleFrom(
                    padding: const EdgeInsets.symmetric(vertical: 12),
                    shape: RoundedRectangleBorder(
                      borderRadius: BorderRadius.circular(8),
                      side: BorderSide(color: Colors.grey.shade300),
                    ),
                  ),
                  child: const Text(
                    'Batal',
                    style: TextStyle(
                      color: Color(0xFF718096),
                      fontWeight: FontWeight.w500,
                    ),
                  ),
                ),
              ),
              const SizedBox(width: 12),
              Expanded(
                child: ElevatedButton(
                  onPressed: () {
                    Navigator.pop(context);
                    _showForgotPasswordDialog();
                  },
                  style: ElevatedButton.styleFrom(
                    backgroundColor: Colors.blue.shade600,
                    foregroundColor: Colors.white,
                    padding: const EdgeInsets.symmetric(vertical: 12),
                    elevation: 2,
                    shape: RoundedRectangleBorder(
                      borderRadius: BorderRadius.circular(8),
                    ),
                  ),
                  child: const Text(
                    'Reset Password',
                    style: TextStyle(fontWeight: FontWeight.w600),
                  ),
                ),
              ),
            ],
          ),
        ],
      ),
    );
  }

  Future<void> _showSetPasswordDialog(String email) async {
    final passwordController = TextEditingController();
    await showDialog(
      context: context,
      builder: (context) => AlertDialog(
        title: const Text('Setel Password Baru'),
        content: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            TextField(
              controller: passwordController,
              decoration: const InputDecoration(labelText: 'Password Baru'),
              obscureText: true,
            ),
          ],
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(context),
            child: const Text('Batal'),
          ),
          TextButton(
            onPressed: () async {
              try {
                await FirebaseAuth.instance.currentUser?.updatePassword(
                  passwordController.text,
                );
                ScaffoldMessenger.of(context).showSnackBar(
                  const SnackBar(
                    content: Text('Password berhasil disetel'),
                    backgroundColor: Colors.green,
                  ),
                );
                Navigator.pop(context);
              } catch (e) {
                ScaffoldMessenger.of(context).showSnackBar(
                  SnackBar(
                    content: Text('Gagal menyimpan password: $e'),
                    backgroundColor: Colors.red,
                  ),
                );
              }
            },
            child: const Text('Simpan'),
          ),
        ],
      ),
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
                      onPressed: _showForgotPasswordDialog,
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
