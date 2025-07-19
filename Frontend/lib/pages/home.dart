import 'dart:io';
import 'package:cloud_firestore/cloud_firestore.dart';
import 'package:flutter/material.dart';
import 'package:firebase_auth/firebase_auth.dart';
import 'package:image_picker/image_picker.dart';
import 'package:intl/intl.dart';
import 'package:shared_preferences/shared_preferences.dart';
import '../utils/cloudinary_service.dart';
import '../components/home_widgets.dart';
import '../utils/presensi_utils.dart';
import 'settings_page.dart';

class HomePage extends StatefulWidget {
  const HomePage({super.key});

  @override
  State<HomePage> createState() => _HomePageState();
}

class _HomePageState extends State<HomePage> {
  String username = "Pengguna";
  DateTime? clockInTime;
  DateTime? clockOutTime;

  @override
  void initState() {
    super.initState();
    fetchUsername();
    fetchPresensiHariIni();
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

  Future<void> fetchPresensiHariIni() async {
    final data = await fetchPresensiHariIniUtil();
    if (data != null) {
      setState(() {
        if (data['clockIn'] != null) {
          clockInTime = DateTime.parse(data['clockIn']);
        }
        if (data['clockOut'] != null) {
          clockOutTime = DateTime.parse(data['clockOut']);
        }
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

      final uploadResult = await CloudinaryService.uploadImageToCloudinary(
        file,
      );

      if (uploadResult != null) {
        final imageUrl = uploadResult['url'];
        final publicId = uploadResult['public_id'];

        final prefs = await SharedPreferences.getInstance();
        final uid =
            FirebaseAuth.instance.currentUser?.uid ?? prefs.getString('uid');
        final now = DateTime.now();
        final todayStart = DateTime(now.year, now.month, now.day);

        final absensiRef = FirebaseFirestore.instance.collection('absensi');

        final existingQuery = await absensiRef
            .where('uid', isEqualTo: uid)
            .where('tanggal', isEqualTo: todayStart.toIso8601String())
            .limit(1)
            .get();

        if (existingQuery.docs.isEmpty) {
          // Clock In
          await absensiRef.add({
            'uid': uid,
            'nama': username,
            'tanggal': todayStart.toIso8601String(),
            'clockIn': now.toIso8601String(),
            'fotoClockIn': imageUrl,
            'fotoClockInPublicId': publicId,
          });

          ScaffoldMessenger.of(
            context,
          ).showSnackBar(const SnackBar(content: Text('Berhasil Clock In')));
        } else {
          // Clock Out
          final doc = existingQuery.docs.first;
          final data = doc.data();

          if (data['clockOut'] == null && now.hour >= 17) {
            await absensiRef.doc(doc.id).update({
              'clockOut': now.toIso8601String(),
              'fotoClockOut': imageUrl,
              'fotoClockOutPublicId': publicId,
            });

            ScaffoldMessenger.of(
              context,
            ).showSnackBar(const SnackBar(content: Text('Berhasil Clock Out')));
          } else if (now.hour < 17) {
            ScaffoldMessenger.of(context).showSnackBar(
              const SnackBar(
                content: Text('Clock Out hanya tersedia setelah jam 5 sore'),
              ),
            );
          } else {
            ScaffoldMessenger.of(context).showSnackBar(
              const SnackBar(content: Text('Anda sudah Clock Out hari ini')),
            );
          }
        }
      } else {
        ScaffoldMessenger.of(
          context,
        ).showSnackBar(const SnackBar(content: Text('Gagal upload foto')));
      }
    } else {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Pengambilan foto dibatalkan')),
      );
    }

    await fetchPresensiHariIni();
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
              Navigator.push(
                context,
                MaterialPageRoute(builder: (_) => const SettingsPage()),
              );
            },
            child: CircleAvatar(
              radius: 28,
              backgroundColor: Colors.white,
              child: Icon(Icons.person, size: 30, color: Colors.grey[800]),
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
          BoxShadow(color: Colors.black12, blurRadius: 8, offset: Offset(0, 4)),
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
                      DateFormat(
                        'EEEE, dd MMMM yyyy',
                        'id_ID',
                      ).format(DateTime.now()),
                      style: const TextStyle(
                        fontWeight: FontWeight.bold,
                        fontSize: 15,
                      ),
                    ),
                    const SizedBox(height: 4),
                    Text(
                      "Masuk : ${clockInTime != null ? DateFormat.Hm().format(clockInTime!) : "-"}",
                      style: const TextStyle(color: Colors.blue),
                    ),
                  ],
                ),
              ),
              const SizedBox(width: 12),
              Column(
                crossAxisAlignment: CrossAxisAlignment.end,
                children: [
                  const Text("Jadwal", style: TextStyle(fontSize: 13)),
                  const SizedBox(height: 4),
                  const Text(
                    "08.00 - 17.00 WIB",
                    style: TextStyle(fontWeight: FontWeight.bold, fontSize: 15),
                  ),
                  const SizedBox(height: 4),
                  Text(
                    "Pulang : ${clockOutTime != null ? DateFormat.Hm().format(clockOutTime!) : "-"}",
                    style: const TextStyle(color: Colors.blue),
                  ),
                ],
              ),
            ],
          ),
          const Divider(height: 32),
          const Text(
            "Rekab Presensi Bulan Ini",
            style: TextStyle(fontSize: 14, fontWeight: FontWeight.bold),
          ),
          const SizedBox(height: 12),
          Row(
            mainAxisAlignment: MainAxisAlignment.spaceEvenly,
            children: const [
              StatusInfo(label: "Hadir", count: "7 Hari", color: Colors.green),
              StatusInfo(label: "Izin", count: "0 Hari", color: Colors.orange),
              StatusInfo(
                label: "Tidak Hadir",
                count: "0 Hari",
                color: Colors.red,
              ),
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
              style: TextStyle(
                color: Colors.white,
                fontWeight: FontWeight.bold,
                fontSize: 16,
              ),
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
        child: Text(
          "Menu Utama",
          style: TextStyle(fontSize: 16, fontWeight: FontWeight.bold),
        ),
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
          MenuIcon(icon: Icons.assignment_outlined, label: "Riwayat Presensi"),
          MenuIcon(icon: Icons.location_on_outlined, label: "Lokasi"),
          MenuIcon(icon: Icons.mail_outline, label: "Pengajuan Izin"),
          MenuIcon(icon: Icons.event_note_outlined, label: "Aktivitas"),
          MenuIcon(icon: Icons.attach_money_outlined, label: "Informasi Gaji"),
        ],
      ),
    );
  }
}
