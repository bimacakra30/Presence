import 'dart:io';
import 'package:flutter/material.dart';
import 'package:google_fonts/google_fonts.dart';
import 'package:image_picker/image_picker.dart';
import 'package:cloud_firestore/cloud_firestore.dart';
import 'package:firebase_auth/firebase_auth.dart';
import 'package:shared_preferences/shared_preferences.dart';
import '/utils/cloudinary_service.dart'; // Ganti dengan path yang benar
import 'package:intl/intl.dart'; // Import for DateFormat

class EditProfilePage extends StatefulWidget {
  const EditProfilePage({super.key});

  @override
  _EditProfilePageState createState() => _EditProfilePageState();
}

class _EditProfilePageState extends State<EditProfilePage>
    with SingleTickerProviderStateMixin {
  final _formKey = GlobalKey<FormState>();
  final TextEditingController _nameController = TextEditingController();
  final TextEditingController _emailController = TextEditingController();
  final TextEditingController _usernameController = TextEditingController();
  final TextEditingController _positionController = TextEditingController();
  final TextEditingController _statusController = TextEditingController();
  final TextEditingController _addressController = TextEditingController();
  final TextEditingController _dateOfBirthController = TextEditingController();

  final _picker = ImagePicker();
  late AnimationController _animationController;
  late Animation<double> _fadeAnimation;

  String _profilePictureUrl = '';
  bool _isLoading = false;
  bool _isSaving = false;
  DateTime? _selectedDateOfBirth;
  DateTime? _createdAt;

  @override
  void initState() {
    super.initState();
    _animationController = AnimationController(
      duration: const Duration(milliseconds: 800),
      vsync: this,
    );
    _fadeAnimation = Tween<double>(
      begin: 0.0,
      end: 1.0,
    ).animate(CurvedAnimation(
      parent: _animationController,
      curve: Curves.easeInOut,
    ));
    _fetchUserData();
  }

  @override
  void dispose() {
    _animationController.dispose();
    _nameController.dispose();
    _emailController.dispose();
    _usernameController.dispose();
    _positionController.dispose();
    _statusController.dispose();
    _addressController.dispose();
    _dateOfBirthController.dispose();
    super.dispose();
  }

  /// Mengambil data pengguna dari Firestore.
  Future<void> _fetchUserData() async {
    setState(() {
      _isLoading = true;
    });

    final user = FirebaseAuth.instance.currentUser;
    if (user != null) {
      try {
        final DocumentSnapshot docSnapshot = await FirebaseFirestore.instance
            .collection('employees')
            .doc(user.uid)
            .get();

        if (!mounted) return;

        if (docSnapshot.exists && docSnapshot.data() != null) {
          final data = docSnapshot.data() as Map<String, dynamic>;

          _nameController.text = data['name'] ?? '';
          _emailController.text = data['email'] ?? '';
          _usernameController.text = data['username'] ?? '';
          _positionController.text = data['position'] ?? '';
          _statusController.text = data['status'] ?? '';
          _addressController.text = data['address'] ?? '';

          if (data['dateOfBirth'] != null &&
              data['dateOfBirth'] is String &&
              data['dateOfBirth'].isNotEmpty) {
            try {
              _selectedDateOfBirth = DateTime.parse(data['dateOfBirth']);
              _dateOfBirthController.text = DateFormat(
                'dd MMMM yyyy',
              ).format(_selectedDateOfBirth!);
            } catch (e) {
              debugPrint('Error parsing dateOfBirth from Firestore: $e');
              _dateOfBirthController.text = '';
            }
          } else {
            _dateOfBirthController.text = '';
          }

          // Fetch and parse 'createdAt'
          if (data['createdAt'] != null &&
              data['createdAt'] is String &&
              data['createdAt'].isNotEmpty) {
            try {
              _createdAt = DateTime.parse(data['createdAt']);
            } catch (e) {
              debugPrint('Error parsing createdAt from Firestore: $e');
              _createdAt = null;
            }
          }

          setState(() {
            _profilePictureUrl = data['profilePictureUrl'] ?? '';
          });
        } else {
          _nameController.text = user.displayName ?? 'Pengguna Baru';
          _emailController.text = user.email ?? '';
          setState(() {
            _profilePictureUrl = user.photoURL ?? '';
          });
          debugPrint(
              'Dokumen employee tidak ditemukan untuk UID: ${user.uid}. Menggunakan data default dari Firebase Auth.');
          if (!mounted) return;
          _showSnackBar(
            'Dokumen profil tidak ditemukan di Firestore. Profil akan dibuat saat disimpan.',
            isError: false,
          );
        }
      } catch (e) {
        debugPrint('Error fetching user data: $e');
        if (!mounted) return;
        _showSnackBar('Gagal memuat data profil: ${e.toString()}', isError: true);
      }
    } else {
      debugPrint('Tidak ada pengguna yang terautentikasi saat _fetchUserData.');
      if (!mounted) return;
      _showSnackBar('Anda tidak terautentikasi. Silakan login ulang.', isError: true);
    }

    if (!mounted) return;
    setState(() {
      _isLoading = false;
    });
    _animationController.forward();
  }

  /// Enhanced SnackBar
  void _showSnackBar(String message, {bool isError = false}) {
    ScaffoldMessenger.of(context).showSnackBar(
      SnackBar(
        content: Row(
          children: [
            Icon(
              isError ? Icons.error_outline : Icons.info_outline,
              color: Colors.white,
            ),
            const SizedBox(width: 8),
            Expanded(
              child: Text(
                message,
                style: GoogleFonts.poppins(
                  fontSize: 14,
                  fontWeight: FontWeight.w500,
                ),
              ),
            ),
          ],
        ),
        backgroundColor: isError ? Colors.red.shade600 : Colors.orange.shade600,
        behavior: SnackBarBehavior.floating,
        shape: RoundedRectangleBorder(
          borderRadius: BorderRadius.circular(12),
        ),
        margin: const EdgeInsets.all(16),
      ),
    );
  }

  /// Menghitung durasi aktif karyawan
  String _calculateActiveDuration() {
    if (_createdAt == null) {
      return 'Data aktif tidak tersedia';
    }

    final now = DateTime.now();
    final difference = now.difference(_createdAt!);

    int years = 0;
    int months = 0;

    // Calculate years
    years = now.year - _createdAt!.year;
    if (now.month < _createdAt!.month ||
        (now.month == _createdAt!.month && now.day < _createdAt!.day)) {
      years--;
    }

    // Calculate months (remaining after years)
    months = now.month - _createdAt!.month;
    if (months < 0) {
      months += 12;
    }
    if (now.day < _createdAt!.day) {
      months--;
    }
    if (months < 0) {
      months += 12;
    }

    String durationString = '';
    if (years > 0) {
      durationString += '$years tahun ';
    }
    if (months > 0) {
      durationString += '$months bulan';
    }

    if (years == 0 && months == 0) {
      if (difference.inDays > 0) {
        durationString = '${difference.inDays} hari';
      } else {
        durationString = 'Baru saja aktif';
      }
    }

    return durationString.trim();
  }

  /// Menyimpan data profil ke SharedPreferences
  Future<void> _saveToPrefs({
    String? name,
    String? profilePictureUrl,
    String? username,
    String? address,
    String? dateOfBirth,
  }) async {
    final prefs = await SharedPreferences.getInstance();
    if (name != null) {
      await prefs.setString('name', name);
    }
    if (profilePictureUrl != null) {
      await prefs.setString('profilePictureUrl', profilePictureUrl);
    }
    if (username != null) {
      await prefs.setString('username', username);
    }
    if (address != null) {
      await prefs.setString('address', address);
    }
    if (dateOfBirth != null) {
      await prefs.setString('dateOfBirth', dateOfBirth);
    }
  }

  /// Enhanced Profile Picture Selection with Bottom Sheet
  Future<void> _showImageSourceDialog() async {
    showModalBottomSheet(
      context: context,
      backgroundColor: Colors.transparent,
      builder: (context) => Container(
        decoration: const BoxDecoration(
          color: Colors.white,
          borderRadius: BorderRadius.vertical(top: Radius.circular(20)),
        ),
        padding: const EdgeInsets.all(20),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            Container(
              width: 40,
              height: 4,
              decoration: BoxDecoration(
                color: Colors.grey.shade300,
                borderRadius: BorderRadius.circular(2),
              ),
            ),
            const SizedBox(height: 20),
            Text(
              'Ubah Foto Profil',
              style: GoogleFonts.poppins(
                fontSize: 18,
                fontWeight: FontWeight.w600,
              ),
            ),
            const SizedBox(height: 20),
            Row(
              children: [
                Expanded(
                  child: _buildImageSourceOption(
                    icon: Icons.camera_alt,
                    label: 'Kamera',
                    source: ImageSource.camera,
                  ),
                ),
                const SizedBox(width: 16),
                Expanded(
                  child: _buildImageSourceOption(
                    icon: Icons.photo_library,
                    label: 'Galeri',
                    source: ImageSource.gallery,
                  ),
                ),
              ],
            ),
            const SizedBox(height: 20),
          ],
        ),
      ),
    );
  }

  Widget _buildImageSourceOption({
    required IconData icon,
    required String label,
    required ImageSource source,
  }) {
    return InkWell(
      onTap: () {
        Navigator.pop(context);
        _changeProfilePicture(source);
      },
      borderRadius: BorderRadius.circular(12),
      child: Container(
        padding: const EdgeInsets.symmetric(vertical: 20),
        decoration: BoxDecoration(
          border: Border.all(color: Colors.grey.shade300),
          borderRadius: BorderRadius.circular(12),
        ),
        child: Column(
          children: [
            Icon(icon, size: 32, color: Colors.blue.shade600),
            const SizedBox(height: 8),
            Text(
              label,
              style: GoogleFonts.poppins(
                fontSize: 14,
                fontWeight: FontWeight.w500,
              ),
            ),
          ],
        ),
      ),
    );
  }

  /// Mengunggah gambar ke Cloudinary dan memperbarui URL-nya di Firestore.
  Future<void> _changeProfilePicture(ImageSource source) async {
    final pickedFile = await _picker.pickImage(source: source);
    if (pickedFile != null) {
      final imageFile = File(pickedFile.path);

      if (!mounted) return;
      setState(() {
        _isLoading = true;
      });

      final uploadResult = await CloudinaryService.uploadImageToCloudinary(
        imageFile,
      );

      if (!mounted) return;
      if (uploadResult != null && uploadResult['url'] != null) {
        final imageUrl = uploadResult['url'] as String;
        setState(() {
          _profilePictureUrl = imageUrl;
        });
        await _updateProfileInFirestore(profilePictureUrl: imageUrl);
        await _saveToPrefs(profilePictureUrl: imageUrl);
        if (!mounted) return;
        _showSnackBar('Foto profil berhasil diperbarui!');
      } else {
        _showSnackBar('Gagal mengunggah foto profil.', isError: true);
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
    String? profilePictureUrl,
    String? username,
    String? address,
    String? dateOfBirth,
  }) async {
    final user = FirebaseAuth.instance.currentUser;
    if (user != null && user.uid.isNotEmpty) {
      final DocumentReference<Map<String, dynamic>> docRef = FirebaseFirestore
          .instance
          .collection('employees')
          .doc(user.uid);

      final updates = <String, dynamic>{};
      if (name != null) updates['name'] = name;
      if (profilePictureUrl != null) updates['profilePictureUrl'] = profilePictureUrl;
      if (username != null) updates['username'] = username;
      if (address != null) updates['address'] = address;
      if (dateOfBirth != null) updates['dateOfBirth'] = dateOfBirth;

      if (!updates.containsKey('uid')) updates['uid'] = user.uid;

      try {
        await docRef.set(updates, SetOptions(merge: true));
      } catch (e) {
        debugPrint('Error updating profile in Firestore: $e');
        if (!mounted) return;
        _showSnackBar('Gagal menyimpan perubahan: ${e.toString()}', isError: true);
      }
    } else {
      debugPrint(
          'Tidak ada pengguna yang terautentikasi atau UID tidak ditemukan.');
      if (!mounted) return;
      _showSnackBar(
        'Gagal menyimpan. Pengguna tidak terautentikasi atau UID tidak valid.',
        isError: true,
      );
    }
  }

  /// Menyimpan perubahan profil
  void _saveProfile() async {
    if (_formKey.currentState!.validate()) {
      if (!mounted) return;
      setState(() {
        _isSaving = true;
      });

      await _updateProfileInFirestore(
        name: _nameController.text,
        username: _usernameController.text,
        address: _addressController.text,
        dateOfBirth: _selectedDateOfBirth?.toIso8601String(),
        profilePictureUrl: _profilePictureUrl,
      );

      await _saveToPrefs(
        name: _nameController.text,
        username: _usernameController.text,
        address: _addressController.text,
        dateOfBirth: _selectedDateOfBirth?.toIso8601String(),
        profilePictureUrl: _profilePictureUrl,
      );

      if (!mounted) return;
      setState(() {
        _isSaving = false;
      });
      _showSnackBar('Profil berhasil diperbarui!');
      
      // Delay untuk menampilkan snackbar sebelum kembali
      await Future.delayed(const Duration(milliseconds: 500));
      if (mounted) Navigator.of(context).pop();
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
            colorScheme: ColorScheme.light(
              primary: Colors.blue.shade600,
              onPrimary: Colors.white,
              onSurface: Colors.black87,
            ),
            textButtonTheme: TextButtonThemeData(
              style: TextButton.styleFrom(
                foregroundColor: Colors.blue.shade600,
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
      return 'U';
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
      backgroundColor: Colors.grey.shade50,
      appBar: AppBar(
        title: Text(
          'Edit Profil',
          style: GoogleFonts.poppins(
            fontSize: 20,
            fontWeight: FontWeight.w600,
            color: Colors.white,
          ),
        ),
        backgroundColor: Colors.blue.shade600,
        elevation: 0,
        centerTitle: true,
        iconTheme: const IconThemeData(color: Colors.white),
        actions: [
          if (!_isSaving)
            IconButton(
              icon: const Icon(Icons.check_rounded),
              onPressed: _saveProfile,
              tooltip: 'Simpan Perubahan',
            ),
          if (_isSaving)
            const Padding(
              padding: EdgeInsets.all(16.0),
              child: SizedBox(
                width: 20,
                height: 20,
                child: CircularProgressIndicator(
                  strokeWidth: 2,
                  color: Colors.white,
                ),
              ),
            ),
        ],
      ),
      body: _isLoading
          ? const Center(
              child: CircularProgressIndicator(),
            )
          : FadeTransition(
              opacity: _fadeAnimation,
              child: SingleChildScrollView(
                physics: const BouncingScrollPhysics(),
                child: Column(
                  children: [
                    // Header with Profile Picture
                    Container(
                      width: double.infinity,
                      decoration: BoxDecoration(
                        gradient: LinearGradient(
                          begin: Alignment.topCenter,
                          end: Alignment.bottomCenter,
                          colors: [
                            Colors.blue.shade600,
                            Colors.blue.shade400,
                          ],
                        ),
                      ),
                      child: Column(
                        children: [
                          const SizedBox(height: 20),
                          Stack(
                            children: [
                              Hero(
                                tag: 'profile_picture',
                                child: Container(
                                  decoration: BoxDecoration(
                                    shape: BoxShape.circle,
                                    border: Border.all(
                                      color: Colors.white,
                                      width: 4,
                                    ),
                                    boxShadow: [
                                      BoxShadow(
                                        color: Colors.black.withOpacity(0.2),
                                        blurRadius: 20,
                                        offset: const Offset(0, 10),
                                      ),
                                    ],
                                  ),
                                  child: CircleAvatar(
                                    radius: 60,
                                    backgroundColor: Colors.grey.shade200,
                                    backgroundImage: _profilePictureUrl.isNotEmpty
                                        ? NetworkImage(_profilePictureUrl)
                                        : null,
                                    child: _profilePictureUrl.isEmpty
                                        ? Text(
                                            _getInitials(_nameController.text),
                                            style: GoogleFonts.poppins(
                                              fontSize: 48,
                                              fontWeight: FontWeight.bold,
                                              color: Colors.grey.shade600,
                                            ),
                                          )
                                        : null,
                                  ),
                                ),
                              ),
                              Positioned(
                                bottom: 5,
                                right: 5,
                                child: InkWell(
                                  onTap: _showImageSourceDialog,
                                  borderRadius: BorderRadius.circular(25),
                                  child: Container(
                                    padding: const EdgeInsets.all(8),
                                    decoration: BoxDecoration(
                                      color: Colors.blue.shade600,
                                      shape: BoxShape.circle,
                                      border: Border.all(
                                        color: Colors.white,
                                        width: 3,
                                      ),
                                      boxShadow: [
                                        BoxShadow(
                                          color: Colors.black.withOpacity(0.2),
                                          blurRadius: 10,
                                          offset: const Offset(0, 5),
                                        ),
                                      ],
                                    ),
                                    child: const Icon(
                                      Icons.camera_alt_rounded,
                                      color: Colors.white,
                                      size: 20,
                                    ),
                                  ),
                                ),
                              ),
                            ],
                          ),
                          const SizedBox(height: 20),
                          Text(
                            _nameController.text.isNotEmpty
                                ? _nameController.text
                                : 'Nama Pengguna',
                            style: GoogleFonts.poppins(
                              fontSize: 24,
                              fontWeight: FontWeight.w600,
                              color: Colors.white,
                            ),
                          ),
                          const SizedBox(height: 5),
                          Text(
                            _positionController.text.isNotEmpty
                                ? _positionController.text
                                : 'Posisi',
                            style: GoogleFonts.poppins(
                              fontSize: 16,
                              color: Colors.white.withOpacity(0.9),
                            ),
                          ),
                          const SizedBox(height: 30),
                        ],
                      ),
                    ),
                    
                    // Form Content
                    Padding(
                      padding: const EdgeInsets.all(20.0),
                      child: Form(
                        key: _formKey,
                        child: Column(
                          children: [
                            // Active Duration Card (if available)
                            if (_createdAt != null) ...[
                              _buildInfoCard(
                                title: 'Lama Aktif',
                                value: _calculateActiveDuration(),
                                icon: Icons.access_time_rounded,
                                color: Colors.green,
                              ),
                              const SizedBox(height: 20),
                            ],

                            // Personal Information Section
                            _buildSectionHeader('Informasi Personal'),
                            const SizedBox(height: 16),
                            
                            _buildModernTextField(
                              controller: _nameController,
                              label: 'Nama Lengkap',
                              icon: Icons.person_rounded,
                              validator: (value) {
                                if (value == null || value.isEmpty) {
                                  return 'Nama tidak boleh kosong';
                                }
                                return null;
                              },
                            ),
                            const SizedBox(height: 20),
                            
                            _buildModernTextField(
                              controller: _usernameController,
                              label: 'Username',
                              icon: Icons.alternate_email_rounded,
                              validator: (value) {
                                if (value == null || value.isEmpty) {
                                  return 'Username tidak boleh kosong';
                                }
                                return null;
                              },
                            ),
                            const SizedBox(height: 20),
                            
                            _buildModernTextField(
                              controller: _dateOfBirthController,
                              label: 'Tanggal Lahir',
                              icon: Icons.calendar_today_rounded,
                              enabled: true,
                              readOnly: true,
                              onTap: () => _selectDateOfBirth(context),
                              validator: (value) {
                                if (value == null || value.isEmpty) {
                                  return 'Tanggal lahir tidak boleh kosong';
                                }
                                return null;
                              },
                            ),
                            const SizedBox(height: 20),
                            
                            _buildModernTextField(
                              controller: _addressController,
                              label: 'Alamat',
                              icon: Icons.location_on_rounded,
                              maxLines: 2,
                              validator: (value) {
                                if (value == null || value.isEmpty) {
                                  return 'Alamat tidak boleh kosong';
                                }
                                return null;
                              },
                            ),
                            
                            const SizedBox(height: 30),
                            
                            // Work Information Section
                            _buildSectionHeader('Informasi Pekerjaan'),
                            const SizedBox(height: 16),
                            
                            _buildModernTextField(
                              controller: _emailController,
                              label: 'Alamat Email',
                              icon: Icons.email_rounded,
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
                            
                            _buildModernTextField(
                              controller: _positionController,
                              label: 'Posisi',
                              icon: Icons.work_rounded,
                              enabled: false,
                              validator: (value) {
                                if (value == null || value.isEmpty) {
                                  return 'Posisi tidak boleh kosong';
                                }
                                return null;
                              },
                            ),
                            const SizedBox(height: 20),
                            
                            _buildModernTextField(
                              controller: _statusController,
                              label: 'Status',
                              icon: Icons.check_circle_outline_rounded,
                              enabled: false,
                              validator: (value) {
                                if (value == null || value.isEmpty) {
                                  return 'Status tidak boleh kosong';
                                }
                                return null;
                              },
                            ),
                            
                            const SizedBox(height: 40),
                            
                            // Save Button
                            SizedBox(
                              width: double.infinity,
                              child: ElevatedButton(
                                onPressed: _isSaving ? null : _saveProfile,
                                style: ElevatedButton.styleFrom(
                                  backgroundColor: Colors.blue.shade600,
                                  foregroundColor: Colors.white,
                                  padding: const EdgeInsets.symmetric(vertical: 16),
                                  shape: RoundedRectangleBorder(
                                    borderRadius: BorderRadius.circular(12),
                                  ),
                                  elevation: 3,
                                ),
                                child: _isSaving
                                    ? Row(
                                        mainAxisAlignment: MainAxisAlignment.center,
                                        children: [
                                          const SizedBox(
                                            width: 20,
                                            height: 20,
                                            child: CircularProgressIndicator(
                                              strokeWidth: 2,
                                              color: Colors.white,
                                            ),
                                          ),
                                          const SizedBox(width: 12),
                                          Text(
                                            'Menyimpan...',
                                            style: GoogleFonts.poppins(
                                              fontSize: 16,
                                              fontWeight: FontWeight.w600,
                                            ),
                                          ),
                                        ],
                                      )
                                    : Text(
                                        'Simpan Perubahan',
                                        style: GoogleFonts.poppins(
                                          fontSize: 16,
                                          fontWeight: FontWeight.w600,
                                        ),
                                      ),
                              ),
                            ),
                            
                            const SizedBox(height: 30),
                          ],
                        ),
                      ),
                    ),
                  ],
                ),
              ),
            ),
    );
  }

  Widget _buildSectionHeader(String title) {
    return Align(
      alignment: Alignment.centerLeft,
      child: Text(
        title,
        style: GoogleFonts.poppins(
          fontSize: 18,
          fontWeight: FontWeight.w600,
          color: Colors.grey.shade800,
        ),
      ),
    );
  }

  Widget _buildInfoCard({
    required String title,
    required String value,
    required IconData icon,
    required Color color,
  }) {
    return Container(
      width: double.infinity,
      padding: const EdgeInsets.all(20),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(16),
        border: Border.all(color: color.withOpacity(0.2)),
        boxShadow: [
          BoxShadow(
            color: Colors.black.withOpacity(0.05),
            blurRadius: 10,
            offset: const Offset(0, 5),
          ),
        ],
      ),
      child: Row(
        children: [
          Container(
            padding: const EdgeInsets.all(12),
            decoration: BoxDecoration(
              color: color.withOpacity(0.1),
              borderRadius: BorderRadius.circular(12),
            ),
            child: Icon(
              icon,
              color: color,
              size: 24,
            ),
          ),
          const SizedBox(width: 16),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  title,
                  style: GoogleFonts.poppins(
                    fontSize: 14,
                    color: Colors.grey.shade600,
                    fontWeight: FontWeight.w500,
                  ),
                ),
                const SizedBox(height: 4),
                Text(
                  value,
                  style: GoogleFonts.poppins(
                    fontSize: 16,
                    fontWeight: FontWeight.w600,
                    color: Colors.black87,
                  ),
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildModernTextField({
    required TextEditingController controller,
    required String label,
    required IconData icon,
    required String? Function(String?) validator,
    bool enabled = true,
    bool readOnly = false,
    int maxLines = 1,
    VoidCallback? onTap,
  }) {
    return Container(
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(16),
        boxShadow: [
          BoxShadow(
            color: Colors.black.withOpacity(0.05),
            blurRadius: 10,
            offset: const Offset(0, 5),
          ),
        ],
      ),
      child: TextFormField(
        controller: controller,
        enabled: enabled,
        readOnly: readOnly,
        maxLines: maxLines,
        onTap: onTap,
        style: GoogleFonts.poppins(
          fontSize: 16,
          fontWeight: FontWeight.w500,
          color: enabled ? Colors.black87 : Colors.grey.shade600,
        ),
        decoration: InputDecoration(
          labelText: label,
          labelStyle: GoogleFonts.poppins(
            color: Colors.grey.shade600,
            fontSize: 14,
            fontWeight: FontWeight.w500,
          ),
          prefixIcon: Container(
            margin: const EdgeInsets.only(right: 12),
            child: Icon(
              icon,
              color: enabled ? Colors.blue.shade600 : Colors.grey.shade500,
              size: 22,
            ),
          ),
          prefixIconConstraints: const BoxConstraints(
            minWidth: 50,
            minHeight: 50,
          ),
          border: OutlineInputBorder(
            borderRadius: BorderRadius.circular(16),
            borderSide: BorderSide.none,
          ),
          enabledBorder: OutlineInputBorder(
            borderRadius: BorderRadius.circular(16),
            borderSide: BorderSide(
              color: Colors.grey.shade200,
              width: 1,
            ),
          ),
          focusedBorder: OutlineInputBorder(
            borderRadius: BorderRadius.circular(16),
            borderSide: BorderSide(
              color: Colors.blue.shade600,
              width: 2,
            ),
          ),
          errorBorder: OutlineInputBorder(
            borderRadius: BorderRadius.circular(16),
            borderSide: BorderSide(
              color: Colors.red.shade400,
              width: 1,
            ),
          ),
          focusedErrorBorder: OutlineInputBorder(
            borderRadius: BorderRadius.circular(16),
            borderSide: BorderSide(
              color: Colors.red.shade400,
              width: 2,
            ),
          ),
          filled: true,
          fillColor: enabled ? Colors.white : Colors.grey.shade50,
          contentPadding: EdgeInsets.symmetric(
            horizontal: 20,
            vertical: maxLines > 1 ? 20 : 16,
          ),
          errorStyle: GoogleFonts.poppins(
            fontSize: 12,
            color: Colors.red.shade600,
          ),
          suffixIcon: readOnly && onTap != null
              ? Icon(
                  Icons.keyboard_arrow_down_rounded,
                  color: Colors.grey.shade600,
                )
              : (!enabled
                  ? Icon(
                      Icons.lock_outline_rounded,
                      color: Colors.grey.shade400,
                      size: 20,
                    )
                  : null),
        ),
        validator: validator,
      ),
    );
  }
}