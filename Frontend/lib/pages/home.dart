import 'dart:io';
import 'package:cloud_firestore/cloud_firestore.dart';
import 'package:flutter/material.dart';
import 'package:firebase_auth/firebase_auth.dart';
import 'package:image_picker/image_picker.dart';
import 'package:fluttertoast/fluttertoast.dart';
import 'package:intl/intl.dart';
import '../components/profile_avatar.dart';
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
  bool? lateStatus;

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
    try {
      final data = await fetchPresensiHariIniUtil();

      if (data != null) {
        setState(() {
          if (data['clockIn'] != null) {
            clockInTime = DateTime.parse(data['clockIn']);
          }

          if (data['clockOut'] != null) {
            clockOutTime = DateTime.parse(data['clockOut']);
          }

          if (data['late'] != null) {
            lateStatus = data['late'];
          }
        });
      }
    } catch (e) {
      debugPrint('Error fetching presensi: $e');

      ScaffoldMessenger.of(
        context,
      ).showSnackBar(SnackBar(content: Text('Gagal memuat data presensi: $e')));
    }
  }

  Future<void> _ambilFotoDanUpload() async {
    final picker = ImagePicker();
    try {
      // Ambil foto dari kamera
      final XFile? photo = await picker.pickImage(source: ImageSource.camera);
      if (photo == null) {
        _showMessage('Pengambilan foto dibatalkan');
        return;
      }

      // Upload foto ke Cloudinary
      _showMessage('Mengupload foto ke Cloudinary...');
      final file = File(photo.path);
      final uploadResult = await CloudinaryService.uploadImageToCloudinary(
        file,
      );
      if (uploadResult == null || uploadResult['url'] == null) {
        _showMessage('Gagal upload foto ke Cloudinary');
        return;
      }

      // Ambil data pengguna
      final prefs = await SharedPreferences.getInstance();
      final uid =
          FirebaseAuth.instance.currentUser?.uid ?? prefs.getString('uid');
      if (uid == null) {
        _showMessage('User tidak ditemukan, silakan login ulang');
        return;
      }

      // Proses presence
      final now = DateTime.now();
      final existingQuery = await FirebaseFirestore.instance
          .collection('presence')
          .where('uid', isEqualTo: uid)
          .where(
            'date',
            isEqualTo: DateTime(now.year, now.month, now.day).toIso8601String(),
          )
          .limit(1)
          .get();

      await _handleAttendance(
        uid: uid,
        username: username,
        imageUrl: uploadResult['url'],
        publicId: uploadResult['public_id'],
        now: now,
        existingQuery: existingQuery,
      );

      await fetchPresensiHariIni();
    } catch (e) {
      debugPrint('Error saat proses foto dan upload: $e');
      _showMessage('Terjadi kesalahan saat presensi: $e');
    }
  }

  Future<void> _handleAttendance({
    required String uid,
    required String username,
    required String imageUrl,
    required String publicId,
    required DateTime now,
    required QuerySnapshot existingQuery,
  }) async {
    try {
      final todayStart = DateTime(now.year, now.month, now.day);
      const workStartHour = 8;
      const workEndHour = 17;
      final presenceRef = FirebaseFirestore.instance.collection('presence');

      if (existingQuery.docs.isEmpty) {
        final workStartTime = DateTime(
          now.year,
          now.month,
          now.day,
          workStartHour,
        );
        final isLate = now.isAfter(workStartTime);

        String? lateDuration;

        if (isLate) {
          final duration = now.difference(workStartTime);
          final hours = duration.inHours;
          final minutes = duration.inMinutes % 60;
          lateDuration = '${hours > 0 ? '$hours jam ' : ''}$minutes menit';
        }

        await presenceRef.add({
          'uid': uid,
          'name': username,
          'date': todayStart.toIso8601String(),
          'clockIn': now.toIso8601String(),
          'fotoClockIn': imageUrl,
          'fotoClockInPublicId': publicId,
          'late': isLate,
          if (lateDuration != null) 'lateDuration': lateDuration,
        });

        setState(() {
          clockInTime = now;
          lateStatus = isLate;
        });

        _showMessage('Berhasil Clock In');
      } else {
        // Handle Clock Out
        final doc = existingQuery.docs.first;
        final data = doc.data() as Map<String, dynamic>;
        final hasClockedOut = data['clockOut'] != null;
        final canClockOut = now.hour >= workEndHour;

        if (hasClockedOut) {
          _showMessage('Anda sudah Clock Out hari ini');
          return;
        }

        if (!canClockOut) {
          _showMessage('Clock Out hanya tersedia setelah jam 17:00');
          return;
        }

        await presenceRef.doc(doc.id).update({
          'clockOut': now.toIso8601String(),
          'fotoClockOut': imageUrl,
          'fotoClockOutPublicId': publicId,
        });

        _showMessage('Berhasil Clock Out');
      }
    } catch (e) {
      _showMessage('Terjadi kesalahan saat presensi: $e');
      rethrow; // Rethrow untuk debugging jika diperlukan
    }
  }

  void _showMessage(String message) {
    Fluttertoast.showToast(
      msg: message,
      toastLength: Toast.LENGTH_LONG,
      gravity: ToastGravity.BOTTOM,
      timeInSecForIosWeb: 3,
      backgroundColor: Colors.black87,
      textColor: Colors.white,
      fontSize: 16.0,
    );
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
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
            child: const ProfileAvatar(),
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
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              // Kolom Kiri - Informasi Presensi
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    const Text(
                      "Tanggal",
                      style: TextStyle(fontSize: 13, color: Colors.black54),
                    ),
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
                    const SizedBox(height: 8),
                    Text(
                      "Masuk : ${clockInTime != null ? DateFormat.Hm().format(clockInTime!) : "-"}",
                      style: const TextStyle(fontSize: 14, color: Colors.blue),
                    ),
                    const SizedBox(height: 4),
                    if (clockInTime != null && lateStatus != null)
                      Text(
                        'Status: ${lateStatus! ? 'Terlambat' : 'Tepat Waktu'}',
                        style: TextStyle(
                          fontSize: 14,
                          fontWeight: FontWeight.bold,
                          color: lateStatus! ? Colors.red : Colors.green,
                        ),
                      ),
                  ],
                ),
              ),

              // Spacer antara dua kolom
              const SizedBox(width: 16),

              // Kolom Kanan - Jadwal dan Pulang
              Column(
                crossAxisAlignment: CrossAxisAlignment.end,
                children: [
                  const Text(
                    "Jadwal",
                    style: TextStyle(fontSize: 13, color: Colors.black54),
                  ),
                  const SizedBox(height: 4),
                  const Text(
                    "08.00 - 17.00 WIB",
                    style: TextStyle(fontWeight: FontWeight.bold, fontSize: 15),
                  ),
                  const SizedBox(height: 8),
                  Text(
                    "Pulang : ${clockOutTime != null ? DateFormat.Hm().format(clockOutTime!) : "-"}",
                    style: const TextStyle(fontSize: 14, color: Colors.blue),
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
    return Padding(
      padding: const EdgeInsets.symmetric(horizontal: 24, vertical: 16),
      child: Text(
        "Menu Utama",
        style: const TextStyle(
          fontSize: 20, // Ukuran font lebih besar
          fontWeight: FontWeight.bold,
          color: Colors.black, // Warna teks
        ),
      ),
    );
  }

  Widget _buildMenuIcons() {
    return Padding(
      padding: const EdgeInsets.symmetric(horizontal: 24),
      child: GridView.count(
        crossAxisCount: 2, // Mengatur jumlah kolom
        shrinkWrap: true, // Menghindari overflow
        physics: const NeverScrollableScrollPhysics(), // Menonaktifkan scroll
        childAspectRatio: 1.2, // Rasio aspek untuk ikon
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
