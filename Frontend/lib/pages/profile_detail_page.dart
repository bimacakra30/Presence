import 'package:flutter/material.dart';
import 'package:google_fonts/google_fonts.dart';
import 'package:shared_preferences/shared_preferences.dart';
import 'package:Presence/components/profile_avatar.dart';
import 'package:Presence/pages/edit_profile_page.dart';
import 'package:intl/intl.dart'; // Untuk format tanggal

class ProfileDetailPage extends StatefulWidget {
  const ProfileDetailPage({super.key});

  @override
  State<ProfileDetailPage> createState() => _ProfileDetailPageState();
}

class _ProfileDetailPageState extends State<ProfileDetailPage> {
  String _username = 'User';
  String _profilePictureUrl = '';
  String _email = '';
  String _firestoreUsername = ''; // Menambahkan field untuk username dari Firestore
  String _position = ''; // Menambahkan field untuk posisi
  String _status = '';   // Menambahkan field untuk status
  String _provider = ''; // Menambahkan field untuk provider login
  String _createdAt = ''; // Menambahkan field untuk tanggal dibuat

  @override
  void initState() {
    super.initState();
    _loadProfileData();
  }

  Future<void> _loadProfileData() async {
    final prefs = await SharedPreferences.getInstance();
    if (!mounted) return;

    setState(() {
      _username = prefs.getString('name') ?? 'User';
      _profilePictureUrl = prefs.getString('ProfilePictureUrl') ?? '';
      _email = prefs.getString('email') ?? '';
      _firestoreUsername = prefs.getString('username') ?? ''; // Ambil username
      _position = prefs.getString('position') ?? '';         // Ambil posisi
      _status = prefs.getString('status') ?? '';             // Ambil status
      _provider = prefs.getString('provider') ?? '';         // Ambil provider
      String? createdAtString = prefs.getString('createdAt');
      if (createdAtString != null && createdAtString.isNotEmpty) {
        try {
          DateTime createdAtDateTime = DateTime.parse(createdAtString);
          _createdAt = DateFormat('dd MMMM yyyy, HH:mm', 'id_ID').format(createdAtDateTime);
        } catch (e) {
          _createdAt = 'Tanggal tidak valid';
          debugPrint('Error parsing createdAt: $e');
        }
      } else {
        _createdAt = 'Tidak tersedia';
      }
    });
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: Text(
          'Detail Profil',
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
      ),
      body: SingleChildScrollView(
        child: Padding(
          padding: const EdgeInsets.all(20.0),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.center,
            children: [
              // Bagian Avatar
              ProfileAvatar(
                profilePictureUrl: _profilePictureUrl,
                name: _username,
                // Parameter 'radius' tidak ada di ProfileAvatar Anda, jadi dihapus
              ),
              const SizedBox(height: 20),
              // Bagian Nama
              Text(
                _username,
                style: GoogleFonts.poppins(
                  fontSize: 28,
                  fontWeight: FontWeight.bold,
                  color: Colors.black87,
                ),
              ),
              const SizedBox(height: 8),
              // Bagian Email
              Text(
                _email.isNotEmpty ? _email : 'Email tidak tersedia',
                style: GoogleFonts.poppins(
                  fontSize: 18,
                  color: Colors.grey.shade600,
                ),
              ),
              const SizedBox(height: 30),
              // Tombol untuk Edit Profil
              ElevatedButton.icon(
                onPressed: () {
                  Navigator.push(
                    context,
                    MaterialPageRoute(builder: (context) => const EditProfilePage()),
                  ).then((_) {
                    _loadProfileData(); // Muat ulang data setelah kembali dari edit
                  });
                },
                icon: const Icon(Icons.edit, color: Colors.white),
                label: const Text('Edit Profil', style: TextStyle(color: Colors.white)),
                style: ElevatedButton.styleFrom(
                  backgroundColor: const Color(0xFF00A0E3),
                  padding: const EdgeInsets.symmetric(horizontal: 30, vertical: 15),
                  shape: RoundedRectangleBorder(
                    borderRadius: BorderRadius.circular(12),
                  ),
                ),
              ),
              const SizedBox(height: 20),

              // Menampilkan Detail Informasi Tambahan
              _buildInfoTile(Icons.alternate_email, 'Username', _firestoreUsername.isNotEmpty ? _firestoreUsername : 'Tidak tersedia'),
              _buildInfoTile(Icons.work_outline, 'Posisi', _position.isNotEmpty ? _position : 'Tidak tersedia'),
              _buildInfoTile(Icons.check_circle_outline, 'Status', _status.isNotEmpty ? _status : 'Tidak tersedia'),
              _buildInfoTile(Icons.login, 'Metode Login', _provider.isNotEmpty ? _provider : 'Tidak tersedia'),
              // _buildInfoTile(Icons.fingerprint, 'UID', _uid.isNotEmpty ? _uid : 'Tidak tersedia'), // Baris ini dihapus
              _buildInfoTile(Icons.calendar_today_outlined, 'Bergabung Sejak', _createdAt),
            ],
          ),
        ),
      ),
    );
  }

  Widget _buildInfoTile(IconData icon, String title, String subtitle) {
    return Card(
      margin: const EdgeInsets.symmetric(vertical: 8.0, horizontal: 16.0),
      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
      elevation: 1,
      child: ListTile(
        leading: Icon(icon, color: Colors.blueGrey.shade600),
        title: Text(title, style: GoogleFonts.poppins(fontWeight: FontWeight.w500)),
        subtitle: Text(subtitle, style: GoogleFonts.poppins(color: Colors.grey.shade700)),
      ),
    );
  }
}