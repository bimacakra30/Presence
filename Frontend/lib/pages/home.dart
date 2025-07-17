import 'dart:io';
import 'package:cloud_firestore/cloud_firestore.dart';
import 'package:flutter/material.dart';
import 'package:firebase_auth/firebase_auth.dart';
import 'package:image_picker/image_picker.dart';
import 'package:intl/intl.dart';
import 'package:shared_preferences/shared_preferences.dart';
import '../components/utils/cloudinary_service.dart';
import 'login_page.dart';

class HomePage extends StatefulWidget {
  const HomePage({super.key});

  @override
  State<HomePage> createState() => _HomePageState();
}

class _HomePageState extends State<HomePage> {
  String username = "Pengguna";

  @override
  void initState() {
    super.initState();
    fetchUsername();
  }

  Future<void> fetchUsername() async {
    final prefs = await SharedPreferences.getInstance();
    final savedName = prefs.getString('name');
    if (savedName != null && savedName.isNotEmpty) {
      setState(() {
        username = savedName;
      });
    }
  }

  Future<void> _ambilFotoDanUpload() async {
    final picker = ImagePicker();
    final XFile? photo = await picker.pickImage(source: ImageSource.camera);

    if (photo != null) {
      final file = File(photo.path);

      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Mengupload foto ke Cloudinary...')),
      );

      final imageUrl = await CloudinaryService.uploadImageToCloudinary(file);

      if (imageUrl != null) {
        final user = FirebaseAuth.instance.currentUser;
        final now = DateTime.now();
        final todayStart = DateTime(now.year, now.month, now.day);

        final absensiRef = FirebaseFirestore.instance.collection('absensi');

        // Cari data absensi hari ini berdasarkan uid
        final existingQuery = await absensiRef
            .where('uid', isEqualTo: user?.uid)
            .where('tanggal', isEqualTo: todayStart.toIso8601String())
            .limit(1)
            .get();

        if (existingQuery.docs.isEmpty) {
          // Belum ada Clock In → Simpan Clock In
          await absensiRef.add({
            'uid': user?.uid,
            'nama': username,
            'tanggal': todayStart.toIso8601String(),
            'clockIn': now.toIso8601String(),
            'fotoClockIn': imageUrl,
          });

          ScaffoldMessenger.of(context).showSnackBar(
            const SnackBar(content: Text('Berhasil Clock In')),
          );
        } else {
          // Sudah Clock In → Periksa apakah sudah Clock Out
          final doc = existingQuery.docs.first;
          final data = doc.data();

          if (data['clockOut'] == null && now.hour >= 17) {
            // Simpan Clock Out
            await absensiRef.doc(doc.id).update({
              'clockOut': now.toIso8601String(),
              'fotoClockOut': imageUrl,
            });

            ScaffoldMessenger.of(context).showSnackBar(
              const SnackBar(content: Text('Berhasil Clock Out')),
            );
          } else if (now.hour < 17) {
            ScaffoldMessenger.of(context).showSnackBar(
              const SnackBar(content: Text('Clock Out hanya tersedia setelah jam 5 sore')),
            );
          } else {
            ScaffoldMessenger.of(context).showSnackBar(
              const SnackBar(content: Text('Anda sudah Clock Out hari ini')),
            );
          }
        }
      } else {
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(content: Text('Gagal upload foto')),
        );
      }
    } else {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Pengambilan foto dibatalkan')),
      );
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: PreferredSize(
        preferredSize: const Size.fromHeight(10),
        child: AppBar(
          backgroundColor: const Color.fromARGB(195, 0, 159, 227),
          elevation: 0,
        ),
      ),
      body: SafeArea(
        child: ListView(
          padding: EdgeInsets.only(
            bottom: MediaQuery.of(context).viewPadding.bottom + 16,
          ),
          children: [
            _buildHeader(),
            _buildInfoCard(),
            _buildPresensiButton(),
            const SizedBox(height: 20),
            _buildMenuUtamaTitle(),
            const SizedBox(height: 12),
            _buildMenuIcons(),
            const SizedBox(height: 32),
          ],
        ),
      ),
    );
  }

  Widget _buildHeader() {
    return Container(
      padding: const EdgeInsets.all(16),
      decoration: const BoxDecoration(
        gradient: LinearGradient(
          colors: [Color(0xFF00A0E3), Color(0xFFB2EBF2)],
          begin: Alignment.topCenter,
          end: Alignment.bottomCenter,
        ),
      ),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  "Hi, $username",
                  style: const TextStyle(
                    fontSize: 22,
                    fontWeight: FontWeight.bold,
                    color: Colors.black,
                  ),
                ),
                const SizedBox(height: 4),
                const Text(
                  "Shared success is based on presence",
                  style: TextStyle(fontSize: 14, color: Colors.black54),
                ),
              ],
            ),
          ),
          GestureDetector(
            onTap: () {
              showModalBottomSheet(
                context: context,
                shape: const RoundedRectangleBorder(
                  borderRadius: BorderRadius.vertical(top: Radius.circular(24)),
                ),
                builder: (context) => _ProfileModal(name: username),
              );
            },
            child: CircleAvatar(
              radius: 28,
              backgroundColor: Colors.white,
              child: Icon(
                Icons.person,
                size: 30,
                color: Colors.grey[800],
              ),
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildInfoCard() {
    return Container(
      margin: const EdgeInsets.symmetric(horizontal: 16, vertical: 16),
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(20),
        boxShadow: const [
          BoxShadow(
            color: Colors.black12,
            blurRadius: 8,
            offset: Offset(0, 4),
          ),
        ],
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    const Text("Tanggal", style: TextStyle(fontSize: 13)),
                    const SizedBox(height: 4),
                    Text(
                      DateFormat('EEEE, dd MMMM yyyy', 'id_ID').format(DateTime.now()),
                      style: const TextStyle(
                        fontWeight: FontWeight.bold,
                        fontSize: 15,
                      ),
                    ),
                    const SizedBox(height: 4),
                    const Text("Masuk : -", style: TextStyle(color: Colors.blue)),
                  ],
                ),
              ),
              const SizedBox(width: 12),
              Column(
                crossAxisAlignment: CrossAxisAlignment.end,
                children: const [
                  Text("Jadwal", style: TextStyle(fontSize: 13)),
                  SizedBox(height: 4),
                  Text(
                    "08.00 - 17.00 WIB",
                    style: TextStyle(fontWeight: FontWeight.bold, fontSize: 15),
                  ),
                  SizedBox(height: 4),
                  Text("Pulang : -", style: TextStyle(color: Colors.blue)),
                ],
              ),
            ],
          ),
          const Divider(height: 32),
          const Text("Rekab Presensi Bulan Ini", style: TextStyle(fontSize: 14, fontWeight: FontWeight.bold)),
          const SizedBox(height: 12),
          Row(
            mainAxisAlignment: MainAxisAlignment.spaceEvenly,
            children: const [
              _StatusInfo(label: "Hadir", count: "7 Hari", color: Colors.green),
              _StatusInfo(label: "Izin", count: "0 Hari", color: Colors.orange),
              _StatusInfo(label: "Tidak Hadir", count: "0 Hari", color: Colors.red),
            ],
          ),
        ],
      ),
    );
  }

  Widget _buildPresensiButton() {
    return Container(
      margin: const EdgeInsets.symmetric(horizontal: 24),
      width: double.infinity,
      decoration: BoxDecoration(
        gradient: const LinearGradient(
          colors: [Color(0xFF00BCD4), Color(0xFF00ACC1)],
        ),
        borderRadius: BorderRadius.circular(20),
      ),
      child: MaterialButton(
        padding: const EdgeInsets.symmetric(vertical: 16),
        onPressed: _ambilFotoDanUpload,
        child: const Column(
          children: [
            Icon(Icons.qr_code_scanner, color: Colors.white, size: 28),
            SizedBox(height: 4),
            Text(
              "Presensi Sekarang",
              style: TextStyle(color: Colors.white, fontWeight: FontWeight.bold, fontSize: 16),
            ),
            Text(
              "(Pastikan berada di Lingkungan kantor)",
              style: TextStyle(color: Colors.white70, fontSize: 12),
            ),
          ],
        ),
      ),
    );
  }

  Widget _buildMenuUtamaTitle() {
    return const Padding(
      padding: EdgeInsets.symmetric(horizontal: 24),
      child: Align(
        alignment: Alignment.centerLeft,
        child: Text("Menu Utama", style: TextStyle(fontSize: 16, fontWeight: FontWeight.bold)),
      ),
    );
  }

  Widget _buildMenuIcons() {
    return Padding(
      padding: const EdgeInsets.symmetric(horizontal: 24),
      child: Wrap(
        alignment: WrapAlignment.spaceBetween,
        runSpacing: 16,
        spacing: 6,
        children: const [
          _MenuIcon(icon: Icons.assignment_outlined, label: "Riwayat Presensi"),
          _MenuIcon(icon: Icons.location_on_outlined, label: "Lokasi"),
          _MenuIcon(icon: Icons.mail_outline, label: "Pengajuan Izin"),
          _MenuIcon(icon: Icons.event_note_outlined, label: "Aktivitas"),
          _MenuIcon(icon: Icons.attach_money_outlined, label: "Informasi Gaji"),
        ],
      ),
    );
  }
}

class _StatusInfo extends StatelessWidget {
  final String label;
  final String count;
  final Color color;

  const _StatusInfo({required this.label, required this.count, required this.color});

  @override
  Widget build(BuildContext context) {
    return Column(
      children: [
        Text(label, style: TextStyle(color: color, fontWeight: FontWeight.bold)),
        const SizedBox(height: 4),
        Text(count),
      ],
    );
  }
}

class _MenuIcon extends StatelessWidget {
  final IconData icon;
  final String label;

  const _MenuIcon({required this.icon, required this.label});

  @override
  Widget build(BuildContext context) {
    return Column(
      children: [
        CircleAvatar(
          backgroundColor: Colors.teal.shade100,
          child: Icon(icon, color: Colors.teal.shade800),
        ),
        const SizedBox(height: 4),
        Text(label, textAlign: TextAlign.center),
      ],
    );
  }
}

class _ProfileModal extends StatelessWidget {
  final String name;

  const _ProfileModal({required this.name});

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.all(24),
      child: Column(
        mainAxisSize: MainAxisSize.min,
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text("Profil Pengguna", style: Theme.of(context).textTheme.titleLarge),
          const SizedBox(height: 12),
          Text("Nama: $name"),
          const SizedBox(height: 20),
          ElevatedButton(
            onPressed: () async {
              await FirebaseAuth.instance.signOut();
              Navigator.pushReplacement(
                context,
                MaterialPageRoute(builder: (_) => const LoginPage()),
              );
            },
            child: const Text("Logout"),
          ),
        ],
      ),
    );
  }
}