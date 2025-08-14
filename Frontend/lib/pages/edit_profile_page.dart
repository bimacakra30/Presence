import 'dart:io';
import 'package:flutter/material.dart';
import 'package:google_fonts/google_fonts.dart';
import 'package:image_picker/image_picker.dart';
import 'package:cloud_firestore/cloud_firestore.dart';
import 'package:firebase_auth/firebase_auth.dart';
import 'package:shared_preferences/shared_preferences.dart';
import '/utils/cloudinary_service.dart';
import 'package:intl/intl.dart'; // Import for DateFormat

class EditProfilePage extends StatefulWidget {
  const EditProfilePage({super.key});

  @override
  _EditProfilePageState createState() => _EditProfilePageState();
}

class _EditProfilePageState extends State<EditProfilePage> {
  final _formKey = GlobalKey<FormState>();
  final TextEditingController _nameController = TextEditingController();
  final TextEditingController _emailController = TextEditingController();
  final TextEditingController _usernameController = TextEditingController();
  final TextEditingController _positionController = TextEditingController();
  final TextEditingController _statusController = TextEditingController();
  final TextEditingController _addressController = TextEditingController(); // New controller for address
  final TextEditingController _dateOfBirthController = TextEditingController(); // New controller for date of birth

  final _picker = ImagePicker();

  String _profilePictureUrl = '';
  bool _isLoading = false;
  DateTime? _selectedDateOfBirth; // To store the selected date object

  @override
  void initState() {
    super.initState();
    _fetchUserData();
  }

  @override
  void dispose() {
    _nameController.dispose();
    _emailController.dispose();
    _usernameController.dispose();
    _positionController.dispose();
    _statusController.dispose();
    _addressController.dispose(); // Dispose new controller
    _dateOfBirthController.dispose(); // Dispose new controller
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
          _usernameController.text = data['username'] ?? '';
          _positionController.text = data['position'] ?? '';
          _statusController.text = data['status'] ?? '';
          _addressController.text = data['address'] ?? ''; // Fetch address

          // Fetch and parse dateOfBirth
          if (data['dateOfBirth'] != null && data['dateOfBirth'] is String && data['dateOfBirth'].isNotEmpty) {
            try {
              _selectedDateOfBirth = DateTime.parse(data['dateOfBirth']);
              _dateOfBirthController.text = DateFormat('dd MMMM yyyy').format(_selectedDateOfBirth!);
            } catch (e) {
              debugPrint('Error parsing dateOfBirth from Firestore: $e');
              _dateOfBirthController.text = ''; // Clear if parsing fails
            }
          } else {
            _dateOfBirthController.text = '';
          }

          setState(() {
            _profilePictureUrl = data['profilePictureUrl'] ?? ''; // Corrected case
          });
        } else {
          _nameController.text = user.displayName ?? 'Pengguna Baru';
          _emailController.text = user.email!;
          setState(() {
            _profilePictureUrl = user.photoURL ?? '';
          });
          debugPrint('Dokumen employee tidak ditemukan untuk email: ${user.email}. Menggunakan data default dari Firebase Auth.');
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
    } else {
      debugPrint('Tidak ada pengguna yang terautentikasi atau email tidak ditemukan saat _fetchUserData.');
      if (!mounted) return;
       ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(
          content: Text('Anda tidak terautentikasi. Silakan login ulang.'),
          backgroundColor: Colors.red,
        ),
      );
    }

    if (!mounted) return;
    setState(() {
      _isLoading = false;
    });
  }

  /// Menyimpan data profil ke SharedPreferences
  Future<void> _saveToPrefs({
    String? name,
    String? profilePictureUrl, // Corrected case
    String? username,
    String? position,
    String? status,
    String? address,      // New parameter
    String? dateOfBirth,  // New parameter
  }) async {
    final prefs = await SharedPreferences.getInstance();
    if (name != null) {
      await prefs.setString('name', name);
    }
    if (profilePictureUrl != null) { // Corrected case
      await prefs.setString('profilePictureUrl', profilePictureUrl); // Corrected case
    }
    if (username != null) {
      await prefs.setString('username', username);
    }
    if (position != null) {
      await prefs.setString('position', position);
    }
    if (status != null) {
      await prefs.setString('status', status);
    }
    if (address != null) { // Save address
      await prefs.setString('address', address);
    }
    if (dateOfBirth != null) { // Save date of birth
      await prefs.setString('dateOfBirth', dateOfBirth);
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
        await _updateProfileInFirestore(profilePictureUrl: imageUrl); // Corrected case
        await _saveToPrefs(profilePictureUrl: imageUrl); // Corrected case
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
  Future<void> _updateProfileInFirestore({
    String? name,
    String? email,
    String? profilePictureUrl, // Corrected case
    String? username,
    String? position,
    String? status,
    String? address,      // New parameter
    String? dateOfBirth,  // New parameter
  }) async {
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
      if (profilePictureUrl != null) updates['profilePictureUrl'];// Corrected case
      if (username != null) updates['username'] = username;
      if (position != null) updates['position'] = position;
      if (status != null) updates['status'] = status;
      if (address != null) updates['address'] = address; // Update address
      if (dateOfBirth != null) updates['dateOfBirth'] = dateOfBirth; // Update date of birth

      if (!updates.containsKey('uid')) updates['uid'] = user.uid;
      if (!updates.containsKey('email')) updates['email'] = user.email;

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

  /// Menyimpan perubahan profil (nama, email, username, position, status, address, dateOfBirth)
  /// ke Firestore dan SharedPreferences.
  void _saveProfile() async {
    if (_formKey.currentState!.validate()) {
      if (!mounted) return;
      setState(() {
        _isLoading = true;
      });

      await _updateProfileInFirestore(
        name: _nameController.text,
        email: _emailController.text,
        username: _usernameController.text,
        position: _positionController.text,
        status: _statusController.text,
        address: _addressController.text, // Pass address
        dateOfBirth: _selectedDateOfBirth?.toIso8601String(), // Pass formatted date of birth
        profilePictureUrl: _profilePictureUrl, // Pass current profile picture URL
      );

      // Simpan semua data yang diperbarui ke SharedPreferences
      await _saveToPrefs(
        name: _nameController.text,
        username: _usernameController.text,
        position: _positionController.text,
        status: _statusController.text,
        address: _addressController.text, // Save address
        dateOfBirth: _selectedDateOfBirth?.toIso8601String(), // Save formatted date of birth
        profilePictureUrl: _profilePictureUrl, // Corrected case
      );

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

  /// Fungsi untuk memilih tanggal lahir menggunakan DatePicker
  Future<void> _selectDateOfBirth(BuildContext context) async {
    final DateTime? picked = await showDatePicker(
      context: context,
      initialDate: _selectedDateOfBirth ?? DateTime.now(),
      firstDate: DateTime(1900),
      lastDate: DateTime.now(),
      builder: (context, child) {
        return Theme(
          data: ThemeData.light().copyWith(
            colorScheme: const ColorScheme.light(
              primary: Color(0xFF00A0E3), // Header background color
              onPrimary: Colors.white, // Header text color
              onSurface: Colors.black87, // Body text color
            ),
            textButtonTheme: TextButtonThemeData(
              style: TextButton.styleFrom(
                foregroundColor: const Color(0xFF00A0E3), // Button text color
              ),
            ),
          ),
          child: child!,
        );
      },
    );
    if (picked != null && picked != _selectedDateOfBirth) {
      setState(() {
        _selectedDateOfBirth = picked;
        _dateOfBirthController.text = DateFormat('dd MMMM yyyy').format(picked);
      });
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
                        controller: _usernameController,
                        label: 'Username',
                        icon: Icons.alternate_email,
                        validator: (value) {
                          if (value == null || value.isEmpty) {
                            return 'Username tidak boleh kosong';
                          }
                          return null;
                        },
                      ),
                      const SizedBox(height: 20),
                      _buildTextField(
                        controller: _emailController,
                        label: 'Alamat Email',
                        icon: Icons.email,
                        enabled: false,
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
                      const SizedBox(height: 20),
                      _buildTextField(
                        controller: _positionController,
                        label: 'Posisi',
                        icon: Icons.work,
                        validator: (value) {
                          if (value == null || value.isEmpty) {
                            return 'Posisi tidak boleh kosong';
                          }
                          return null;
                        },
                      ),
                      const SizedBox(height: 20),
                      _buildTextField(
                        controller: _statusController,
                        label: 'Status',
                        icon: Icons.check_circle_outline,
                        validator: (value) {
                          if (value == null || value.isEmpty) {
                            return 'Status tidak boleh kosong';
                          }
                          return null;
                        },
                      ),
                      const SizedBox(height: 20),
                      // New field: Address
                      _buildTextField(
                        controller: _addressController,
                        label: 'Alamat',
                        icon: Icons.location_on_outlined,
                        validator: (value) {
                          if (value == null || value.isEmpty) {
                            return 'Alamat tidak boleh kosong';
                          }
                          return null;
                        },
                      ),
                      const SizedBox(height: 20),
                      // New field: Date of Birth (with DatePicker)
                      _buildTextField(
                        controller: _dateOfBirthController,
                        label: 'Tanggal Lahir',
                        icon: Icons.calendar_today,
                        enabled: true, // It needs to be enabled to show tap feedback
                        readOnly: true, // Make it read-only
                        onTap: () => _selectDateOfBirth(context), // Open date picker on tap
                        validator: (value) {
                          if (value == null || value.isEmpty) {
                            return 'Tanggal lahir tidak boleh kosong';
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
    bool enabled = true,
    bool readOnly = false, // Added readOnly parameter
    VoidCallback? onTap,   // Added onTap parameter for date picker
  }) {
    return TextFormField(
      controller: controller,
      enabled: enabled,
      readOnly: readOnly, // Apply readOnly property
      onTap: onTap,       // Apply onTap property
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
        filled: !enabled || readOnly, // Fill if disabled or read-only
        fillColor: (!enabled || readOnly) ? Colors.grey.shade100 : Colors.transparent,
      ),
      validator: validator,
    );
  }
}