// edit_profile_page.dart

import 'dart:io';
import 'package:flutter/material.dart';
import 'package:google_fonts/google_fonts.dart';
import 'package:image_picker/image_picker.dart';
import 'package:cloud_firestore/cloud_firestore.dart';
import 'package:firebase_auth/firebase_auth.dart';
import 'package:shared_preferences/shared_preferences.dart'; // Import package SharedPreferences
import '/utils/cloudinary_service.dart'; // Ganti dengan path yang benar

class EditProfilePage extends StatefulWidget {
  const EditProfilePage({super.key});

  @override
  _EditProfilePageState createState() => _EditProfilePageState();
}

class _EditProfilePageState extends State<EditProfilePage> {
  final _formKey = GlobalKey<FormState>();
  final TextEditingController _nameController = TextEditingController();
  final TextEditingController _emailController = TextEditingController();
  final _picker = ImagePicker();

  String _profilePictureUrl = '';
  bool _isLoading = false;

  @override
  void initState() {
    super.initState();
    _fetchUserData();
  }

  @override
  void dispose() {
    _nameController.dispose();
    _emailController.dispose();
    super.dispose();
  }

  /// Mengambil data pengguna dari Firestore.
  Future<void> _fetchUserData() async {
    setState(() {
      _isLoading = true;
    });
    
    final user = FirebaseAuth.instance.currentUser;
    if (user != null && user.email != null) {
      try {
        final querySnapshot = await FirebaseFirestore.instance
            .collection('employees')
            .where('email', isEqualTo: user.email)
            .limit(1)
            .get();

        if (!mounted) return;

        if (querySnapshot.docs.isNotEmpty) {
          final doc = querySnapshot.docs.first;
          final data = doc.data();
          _nameController.text = data['name'] ?? '';
          _emailController.text = data['email'] ?? '';
          setState(() {
            _profilePictureUrl = data['profilePictureUrl'] ?? '';
          });
        } else {
          _nameController.text = user.displayName ?? 'Pengguna Baru';
          _emailController.text = user.email!;
          setState(() {
            _profilePictureUrl = user.photoURL ?? '';
          });
          debugPrint('Dokumen employee tidak ditemukan untuk email: ${user.email}');
          if (!mounted) return;
          ScaffoldMessenger.of(context).showSnackBar(
            const SnackBar(
              content: Text('Dokumen profil tidak ditemukan di Firestore. Membuat profil baru saat disimpan.'),
              backgroundColor: Colors.orange,
            ),
          );
        }
      } catch (e) {
        debugPrint('Error fetching user data: $e');
        if (!mounted) return;
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            content: Text('Gagal memuat data profil: ${e.toString()}'),
            backgroundColor: Colors.red,
          ),
        );
      }
    }

    if (!mounted) return;
    setState(() {
      _isLoading = false;
    });
  }

  /// Menyimpan data profil ke SharedPreferences
  Future<void> _saveToPrefs({String? name, String? profilePictureUrl}) async {
    final prefs = await SharedPreferences.getInstance();
    if (name != null) {
      await prefs.setString('name', name);
    }
    if (profilePictureUrl != null) {
      await prefs.setString('profilePictureUrl', profilePictureUrl);
    }
  }

  /// Mengunggah gambar ke Cloudinary dan memperbarui URL-nya di Firestore.
  Future<void> _changeProfilePicture() async {
    final pickedFile = await _picker.pickImage(source: ImageSource.gallery);
    if (pickedFile != null) {
      final imageFile = File(pickedFile.path);
      
      if (!mounted) return;
      setState(() {
        _isLoading = true;
      });

      final uploadResult = await CloudinaryService.uploadImageToCloudinary(imageFile);

      if (!mounted) return;
      if (uploadResult != null && uploadResult['url'] != null) {
        final imageUrl = uploadResult['url'] as String;
        setState(() {
          _profilePictureUrl = imageUrl;
        });
        await _updateProfileInFirestore(profilePictureUrl: imageUrl);
        // Simpan ke SharedPreferences setelah berhasil
        await _saveToPrefs(profilePictureUrl: imageUrl);
        if (!mounted) return;
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(
            content: Text('Foto profil berhasil diperbarui!'),
            backgroundColor: Colors.green,
          ),
        );
      } else {
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(
            content: Text('Gagal mengunggah foto profil.'),
            backgroundColor: Colors.red,
          ),
        );
      }
      
      if (!mounted) return;
      setState(() {
        _isLoading = false;
      });
    }
  }

  /// Memperbarui data profil di Firestore.
  Future<void> _updateProfileInFirestore({String? name, String? email, String? profilePictureUrl}) async {
    final user = FirebaseAuth.instance.currentUser;
    if (user != null && user.email != null) {
      final querySnapshot = await FirebaseFirestore.instance
          .collection('employees')
          .where('email', isEqualTo: user.email)
          .limit(1)
          .get();
      
      final DocumentReference<Map<String, dynamic>> docRef;
      if (querySnapshot.docs.isNotEmpty) {
        docRef = querySnapshot.docs.first.reference;
      } else {
        docRef = FirebaseFirestore.instance.collection('employees').doc(user.uid);
      }

      final updates = <String, dynamic>{};
      if (name != null) updates['name'] = name;
      if (email != null) updates['email'] = email;
      if (profilePictureUrl != null) updates['profilePictureUrl'] = profilePictureUrl;
      
      if (!updates.containsKey('uid')) updates['uid'] = user.uid;
      
      try {
        await docRef.set(updates, SetOptions(merge: true));
      } catch (e) {
        debugPrint('Error updating profile in Firestore: $e');
        if (!mounted) return;
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            content: Text('Gagal menyimpan perubahan: ${e.toString()}'),
            backgroundColor: Colors.red,
          ),
        );
      }
    } else {
      debugPrint('Tidak ada pengguna yang terautentikasi atau email tidak ditemukan.');
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(
          content: Text('Gagal menyimpan. Pengguna tidak terautentikasi.'),
          backgroundColor: Colors.red,
        ),
      );
    }
  }

  /// Menyimpan perubahan profil (nama dan email) ke Firestore dan SharedPreferences.
  void _saveProfile() async {
    if (_formKey.currentState!.validate()) {
      if (!mounted) return;
      setState(() {
        _isLoading = true;
      });

      await _updateProfileInFirestore(
        name: _nameController.text,
        email: _emailController.text,
      );

      // Simpan nama baru ke SharedPreferences
      await _saveToPrefs(name: _nameController.text);

      if (!mounted) return;
      setState(() {
        _isLoading = false;
      });
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(
          content: Text('Profil berhasil diperbarui!'),
          backgroundColor: Colors.green,
        ),
      );
      Navigator.of(context).pop();
    }
  }

  /// Helper untuk mendapatkan inisial dari nama
  String _getInitials(String name) {
    if (name.isEmpty) {
      return '';
    }
    final nameParts = name.trim().split(' ');
    if (nameParts.length > 1) {
      return (nameParts[0][0] + nameParts[1][0]).toUpperCase();
    }
    return nameParts[0][0].toUpperCase();
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: Text(
          'Edit Profil',
          style: GoogleFonts.poppins(
            fontSize: 20,
            fontWeight: FontWeight.w600,
            color: Colors.black87,
          ),
        ),
        backgroundColor: Colors.white,
        elevation: 0,
        centerTitle: true,
        iconTheme: const IconThemeData(color: Colors.black87),
        actions: [
          if (!_isLoading)
            IconButton(
              icon: const Icon(Icons.check),
              onPressed: _saveProfile,
              color: Colors.green,
            ),
          if (_isLoading)
            const Padding(
              padding: EdgeInsets.all(16.0),
              child: SizedBox(
                width: 20,
                height: 20,
                child: CircularProgressIndicator(strokeWidth: 2),
              ),
            ),
        ],
      ),
      body: _isLoading
          ? const Center(child: CircularProgressIndicator())
          : SingleChildScrollView(
              child: Padding(
                padding: const EdgeInsets.all(20.0),
                child: Form(
                  key: _formKey,
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.center,
                    children: [
                      Stack(
                        children: [
                          CircleAvatar(
                            radius: 60,
                            backgroundColor: Colors.blueGrey,
                            backgroundImage: _profilePictureUrl.isNotEmpty
                                ? NetworkImage(_profilePictureUrl)
                                : null,
                            child: _profilePictureUrl.isEmpty
                                ? Text(
                                    _getInitials(_nameController.text),
                                    style: GoogleFonts.poppins(
                                      fontSize: 48,
                                      fontWeight: FontWeight.bold,
                                      color: Colors.white,
                                    ),
                                  )
                                : null,
                          ),
                          Positioned(
                            bottom: 0,
                            right: 0,
                            child: InkWell(
                              onTap: _changeProfilePicture,
                              borderRadius: BorderRadius.circular(20),
                              child: Container(
                                padding: const EdgeInsets.all(4),
                                decoration: BoxDecoration(
                                  color: Colors.white,
                                  shape: BoxShape.circle,
                                  border: Border.all(color: Colors.grey.shade300, width: 2),
                                ),
                                child: Icon(
                                  Icons.camera_alt,
                                  color: Colors.grey.shade700,
                                  size: 20,
                                ),
                              ),
                            ),
                          ),
                        ],
                      ),
                      const SizedBox(height: 30),
                      _buildTextField(
                        controller: _nameController,
                        label: 'Nama Lengkap',
                        icon: Icons.person,
                        validator: (value) {
                          if (value == null || value.isEmpty) {
                            return 'Nama tidak boleh kosong';
                          }
                          return null;
                        },
                      ),
                      const SizedBox(height: 20),
                      _buildTextField(
                        controller: _emailController,
                        label: 'Alamat Email',
                        icon: Icons.email,
                        validator: (value) {
                          if (value == null || value.isEmpty) {
                            return 'Email tidak boleh kosong';
                          }
                          if (!RegExp(r'^[^@]+@[^@]+\.[^@]+').hasMatch(value)) {
                            return 'Masukkan email yang valid';
                          }
                          return null;
                        },
                      ),
                    ],
                  ),
                ),
              ),
            ),
    );
  }

  Widget _buildTextField({
    required TextEditingController controller,
    required String label,
    required IconData icon,
    required String? Function(String?) validator,
  }) {
    return TextFormField(
      controller: controller,
      decoration: InputDecoration(
        labelText: label,
        labelStyle: TextStyle(color: Colors.grey.shade600),
        prefixIcon: Icon(icon, color: Colors.grey.shade600),
        border: OutlineInputBorder(
          borderRadius: BorderRadius.circular(12),
          borderSide: BorderSide(color: Colors.grey.shade300),
        ),
        enabledBorder: OutlineInputBorder(
          borderRadius: BorderRadius.circular(12),
          borderSide: BorderSide(color: Colors.grey.shade300),
        ),
        focusedBorder: OutlineInputBorder(
          borderRadius: BorderRadius.circular(12),
          borderSide: const BorderSide(color: Colors.blue, width: 2),
        ),
      ),
      validator: validator,
    );
  }
}