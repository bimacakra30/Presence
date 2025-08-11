import 'dart:io';
import 'package:cloud_firestore/cloud_firestore.dart';
import 'package:flutter/material.dart';
import 'package:firebase_auth/firebase_auth.dart';
import 'package:image_picker/image_picker.dart';
import 'package:intl/intl.dart';
import '../components/profile_avatar.dart';
import 'package:shared_preferences/shared_preferences.dart';
import '../utils/cloudinary_service.dart';
import '../components/home_widgets.dart';
import '../utils/presensi_utils.dart';
import '../utils/holidays.dart';
import '../utils/notification_utils.dart'; // Impor file baru
import '../components/maps_location_widget.dart';
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
  Map<String, int> monthlySummary = {
    'hadir': 0,
    'terlambat': 0,
    'tidakHadir': 0,
  };
  bool isLoadingSummary = false;
  final GlobalKey<MapLocationWidgetState> _mapKey = GlobalKey();

  @override
  void initState() {
    super.initState();
    fetchUsername();
    fetchPresensiHariIni();
    fetchMonthlyAttendanceSummary();
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
      showCustomSnackBar(
        context,
        'Gagal memuat data presensi: $e',
        isError: true,
      );
    }
  }

  Future<void> fetchMonthlyAttendanceSummary() async {
    setState(() {
      isLoadingSummary = true;
    });
    try {
      final summary = await fetchMonthlyAttendance();
      setState(() {
        monthlySummary = summary;
      });
    } catch (e) {
      debugPrint('Error fetching monthly attendance: $e');
      showCustomSnackBar(
        context,
        'Gagal memuat rekap presensi: $e',
        isError: true,
      );
    } finally {
      setState(() {
        isLoadingSummary = false;
      });
    }
  }

  Future<void> _handleRefresh() async {
    showCustomSnackBar(context, 'Memperbarui data...');
    await Future.wait([
      fetchUsername(),
      fetchPresensiHariIni(),
      fetchMonthlyAttendanceSummary(),
      _mapKey.currentState?.refreshLocation() ?? Future.value(),
    ]);
    showCustomSnackBar(context, 'Data berhasil diperbarui');
  }

  Future<void> _ambilFotoDanUpload() async {
    final now = DateTime.now();

    final isSunday = now.weekday == DateTime.sunday;
    final isNationalHoliday = parsedHolidays.any(
      (holiday) => isSameDay(holiday, now),
    );

    if (isSunday || isNationalHoliday) {
      String message = 'Presensi tidak tersedia hari ini karena ';
      List<String> reasons = [];

      if (isSunday) {
        reasons.add('Hari Minggu');
      }

      if (isNationalHoliday) {
        final holidayDescription = getHolidayDescription(now);
        if (holidayDescription != null) {
          reasons.add(holidayDescription);
        } else {
          reasons.add('hari libur');
        }
      }

      // Gabungkan alasan menjadi satu pesan
      message += reasons.join(' dan ');

      showCustomSnackBar(context, message, isError: true);
      return;
    }

    final picker = ImagePicker();
    try {
      final XFile? photo = await picker.pickImage(source: ImageSource.camera);
      if (photo == null) {
        showCustomSnackBar(context, 'Pengambilan foto dibatalkan');
        return;
      }

      showCustomSnackBar(context, 'Mengupload foto.....');
      final file = File(photo.path);
      final uploadResult = await CloudinaryService.uploadImageToCloudinary(
        file,
      );

      if (uploadResult == null || uploadResult['url'] == null) {
        showCustomSnackBar(context, 'Gagal upload Foto', isError: true);
        return;
      }

      final prefs = await SharedPreferences.getInstance();
      final uid =
          FirebaseAuth.instance.currentUser?.uid ?? prefs.getString('uid');

      if (uid == null) {
        showCustomSnackBar(
          context,
          'User tidak ditemukan, silakan login ulang',
          isError: true,
        );
        return;
      }

      final todayStart = DateTime(now.year, now.month, now.day);
      final existingQuery = await FirebaseFirestore.instance
          .collection('presence')
          .where('uid', isEqualTo: uid)
          .where('date', isEqualTo: todayStart.toIso8601String())
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
      await fetchMonthlyAttendanceSummary();
    } catch (e) {
      debugPrint('Error saat proses foto dan upload: $e');
      showCustomSnackBar(
        context,
        'Terjadi kesalahan saat presensi: $e',
        isError: true,
      );
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
        showCustomSnackBar(context, 'Berhasil Clock In');
      } else {
        final doc = existingQuery.docs.first;
        final data = doc.data() as Map<String, dynamic>;
        final hasClockedOut = data['clockOut'] != null;
        final canClockOut = now.hour >= workEndHour;

        if (hasClockedOut) {
          showCustomSnackBar(
            context,
            'Anda sudah Clock Out hari ini',
            isError: true,
          );
          return;
        }

        if (!canClockOut) {
          showCustomSnackBar(
            context,
            'Clock Out hanya tersedia setelah jam 17:00',
            isError: true,
          );
          return;
        }

        await presenceRef.doc(doc.id).update({
          'clockOut': now.toIso8601String(),
          'fotoClockOut': imageUrl,
          'fotoClockOutPublicId': publicId,
        });

        showCustomSnackBar(context, 'Berhasil Clock Out');
      }
    } catch (e) {
      showCustomSnackBar(
        context,
        'Terjadi kesalahan saat presensi: $e',
        isError: true,
      );
      rethrow;
    }
  }

  // Fungsi _showMessage yang lama sudah dihapus

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      body: SafeArea(
        child: RefreshIndicator(
          onRefresh: _handleRefresh,
          child: ListView(
            padding: EdgeInsets.only(
              bottom: MediaQuery.of(context).viewPadding.bottom + 16,
            ),
            children: [
              _buildHeader(),
              _buildInfoCard(),
              _buildPresensiButton(),
              const SizedBox(height: 20),
              MapLocationWidget(key: _mapKey),
              const SizedBox(height: 20),
            ],
          ),
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
              const SizedBox(width: 16),
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
          isLoadingSummary
              ? const Center(child: CircularProgressIndicator())
              : Row(
                  mainAxisAlignment: MainAxisAlignment.spaceEvenly,
                  children: [
                    StatusInfo(
                      label: "Hadir",
                      count: "${monthlySummary['hadir']} Hari",
                      color: const Color(
                        0xFF4CAF50,
                      ), // Green from AttendanceHistoryPage
                    ),
                    StatusInfo(
                      label: "Terlambat",
                      count: "${monthlySummary['terlambat']} Hari",
                      color: const Color(
                        0xFFFF9800,
                      ), // Orange from AttendanceHistoryPage
                    ),
                    StatusInfo(
                      label: "Tidak Hadir",
                      count: "${monthlySummary['tidakHadir']} Hari",
                      color: const Color(
                        0xFFF44336,
                      ), // Red from AttendanceHistoryPage
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
        onPressed: () {
          final isAllowed = _mapKey.currentState?.userIsWithinRadius() ?? false;

          if (isAllowed) {
            _ambilFotoDanUpload(); // Aksi presensi
          } else {
            showCustomSnackBar(
              context,
              'Kamu berada di luar area kantor!',
              isError: true,
            );
          }
        },
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
}
