import 'dart:io';
import 'package:cloud_firestore/cloud_firestore.dart';
import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:firebase_auth/firebase_auth.dart';
import 'package:google_fonts/google_fonts.dart';
import 'package:image_picker/image_picker.dart';
import 'package:intl/intl.dart';
import 'package:shared_preferences/shared_preferences.dart';
import '../utils/cloudinary_service.dart';
import '../components/home_widgets.dart';
import '../utils/presensi_utils.dart';
import '../utils/holidays.dart';
import '../utils/notification_utils.dart';
import '../components/maps_location_widget.dart';
import 'settings_page.dart';
import 'permit_page.dart';
import 'package:Presence/components/profile_completion_notification.dart';

class HomePage extends StatefulWidget {
  const HomePage({super.key});

  @override
  State<HomePage> createState() => _HomePageState();
}

class _HomePageState extends State<HomePage> {
  String username = "Pengguna";
  String _profilePictureUrl = "";
  DateTime? clockInTime;
  DateTime? clockOutTime;
  bool? lateStatus;
  Map<String, int> monthlySummary = {'hadir': 0, 'izin': 0, 'tidakHadir': 0};
  bool isLoadingSummary = false;
  final GlobalKey<MapLocationWidgetState> _mapKey = GlobalKey();
  bool _isProfileInComplete = false;

  @override
  void initState() {
    super.initState();
    _loadProfileData();
    fetchPresensiHariIni();
    fetchMonthlyAttendanceSummary();
    _fetchMonthlyPermitsSummary();
    _checkProfileCompletion();
  }

  Future<void> _loadProfileData() async {
    final prefs = await SharedPreferences.getInstance();
    if (mounted) {
      setState(() {
        username = prefs.getString('username') ?? 'Pengguna';
        _profilePictureUrl = prefs.getString('profilePictureUrl') ?? '';
      });
    }
    _checkProfileCompletion();
  }

  Future<void> _checkProfileCompletion() async {
    final prefs = await SharedPreferences.getInstance();
    final name = prefs.getString('name');
    final email = prefs.getString('email');
    final firestoreUsername = prefs.getString('username');
    final dateOfBirth = prefs.getString('dateOfBirth');

    if (name == null ||
        name.isEmpty ||
        email == null ||
        email.isEmpty ||
        firestoreUsername == null ||
        firestoreUsername.isEmpty ||
        dateOfBirth == null ||
        dateOfBirth.isEmpty) {
      if (!mounted) return;
      setState(() {
        _isProfileInComplete = true;
      });
    } else {
      if (!mounted) return;
      setState(() {
        _isProfileInComplete = false;
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
        monthlySummary['hadir'] = summary['hadir'] ?? 0;
        monthlySummary['tidakHadir'] = summary['tidakHadir'] ?? 0;
        monthlySummary['izin'] = summary['izin'] ?? 0;
      });
    } catch (e) {
      debugPrint('Error fetching monthly attendance: $e');
      if (mounted) {
        // Periksa mounted sebelum showSnackBar
        showCustomSnackBar(
          context,
          'Gagal memuat rekap presensi: $e',
          isError: true,
        );
      }
    } finally {
      if (mounted) {
        // Periksa mounted sebelum setState
        setState(() {
          isLoadingSummary = false;
        });
      }
    }
  }

  Future<void> _fetchMonthlyPermitsSummary() async {
    final user = FirebaseAuth.instance.currentUser;
    if (user == null) {
      debugPrint('User not logged in, cannot fetch permit summary.');
      return;
    }

    try {
      final now = DateTime.now();
      final startOfMonth = DateTime(now.year, now.month, 1);
      final endOfMonth = DateTime(now.year, now.month + 1, 0, 23, 59, 59);

      debugPrint(
        'Fetching permits for month: ${DateFormat('yyyy-MM').format(now)}',
      );
      debugPrint('Start date query: ${startOfMonth.toIso8601String()}');
      debugPrint('End date query: ${endOfMonth.toIso8601String()}');

      final querySnapshot = await FirebaseFirestore.instance
          .collection('permits')
          .where('uid', isEqualTo: user.uid)
          .where(
            'submissionDate',
            isGreaterThanOrEqualTo: startOfMonth.toIso8601String(),
          )
          .where(
            'submissionDate',
            isLessThanOrEqualTo: endOfMonth.toIso8601String(),
          )
          .get();

      int approvedPermitsWorkDaysCount =
          0; // Menggunakan nama variabel yang lebih jelas

      debugPrint(
        'Found ${querySnapshot.docs.length} permit documents for current user and month.',
      );

      for (var doc in querySnapshot.docs) {
        final data = doc.data();
        final status = (data['status'] as String?)?.toLowerCase();

        debugPrint('Processing permit ID: ${doc.id}, Status: "$status"');

        if (status == 'approved') {
          final startDateString = data['startDate'] as String?;
          final endDateString = data['endDate'] as String?;

          debugPrint(
            'Approved permit: startDate: "$startDateString", endDate: "$endDateString"',
          );

          if (startDateString != null && endDateString != null) {
            try {
              final startDate = DateTime.parse(startDateString);
              final endDate = DateTime.parse(endDateString);

              int currentPermitWorkDays = 0;
              DateTime currentDate = startDate;

              // Iterate through each day in the permit range
              while (currentDate.isBefore(
                endDate.add(const Duration(days: 1)),
              )) {
                // Check if it's not a Sunday and not a national holiday
                if (currentDate.weekday != DateTime.sunday &&
                    !parsedHolidays.any(
                      (holiday) => isSameDay(holiday, currentDate),
                    )) {
                  currentPermitWorkDays++;
                } else {
                  debugPrint(
                    'Excluded date (Sunday or Holiday): ${DateFormat('yyyy-MM-dd').format(currentDate)}',
                  );
                }
                currentDate = currentDate.add(const Duration(days: 1));
              }
              approvedPermitsWorkDaysCount += currentPermitWorkDays;
              debugPrint(
                'Calculated work days for this permit: $currentPermitWorkDays days. Total approved work days: $approvedPermitsWorkDaysCount',
              );
            } catch (e) {
              debugPrint(
                'Error parsing date for permit duration in _fetchMonthlyPermitsSummary: $e',
              );
            }
          }
        }
      }

      if (mounted) {
        setState(() {
          monthlySummary['izin'] = approvedPermitsWorkDaysCount;
          debugPrint(
            'Final monthlySummary[\'izin\'] set to: ${monthlySummary['izin']}',
          );
        });
      }
    } catch (e) {
      debugPrint('Error fetching monthly permits summary: $e');
      if (mounted) {
        showCustomSnackBar(
          context,
          'Gagal memuat rekap izin: $e',
          isError: true,
        );
      }
    }
  }

  Future<void> _handleRefresh() async {
    showCustomSnackBar(context, 'Memperbarui data...');
    await Future.wait([
      _loadProfileData(),
      fetchPresensiHariIni(),
      fetchMonthlyAttendanceSummary(),
      _fetchMonthlyPermitsSummary(),
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
      if (isSunday) reasons.add('Hari Minggu');
      if (isNationalHoliday) {
        final holidayDescription = getHolidayDescription(now);
        if (holidayDescription != null)
          reasons.add(holidayDescription);
        else
          reasons.add('hari libur');
      }
      message += reasons.join(' dan ');
      showCustomSnackBar(context, message, isError: true);
      return;
    }

    final activeOfficeName = _mapKey.currentState?.activeOfficeName;
    if (activeOfficeName == null) {
      if (mounted) {
        showCustomSnackBar(
          context,
          'Tidak dapat menentukan lokasi presensi saat ini. Pastikan GPS aktif dan berada di area kantor.',
          isError: true,
        );
      }
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
        locationName: activeOfficeName,
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
    required String locationName,
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
          'locationName': locationName,
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
          'locationName': locationName,
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

  void ShowCustomSnackBar(
    BuildContext context,
    String massage, {
    bool isError = false,
  }) {
    ScaffoldMessenger.of(context).showSnackBar(
      SnackBar(
        content: Text(massage),
        backgroundColor: isError
            ? const Color.fromRGBO(68, 88, 99, 1)
            : Colors.green,
        behavior: SnackBarBehavior.floating,
      ),
    );
  }

  String _getInitials(String name) {
    if (name.isEmpty) return '';
    List<String> parts = name.trim().split(' ');
    if (parts.length >= 2) {
      return '${parts[0][0]}${parts[1][0]}'.toUpperCase();
    } else {
      return name[0].toUpperCase();
    }
  }

  @override
  Widget build(BuildContext context) {
    // Mengatur warna ikon status bar agar terlihat jelas
    SystemChrome.setSystemUIOverlayStyle(
      const SystemUiOverlayStyle(statusBarIconBrightness: Brightness.light),
    );

    return Scaffold(
      body: RefreshIndicator(
        onRefresh: _handleRefresh,
        child: CustomScrollView(
          slivers: [
            SliverAppBar(
              expandedHeight: 150.0,
              flexibleSpace: FlexibleSpaceBar(
                background: Container(
                  decoration: const BoxDecoration(
                    gradient: LinearGradient(
                      colors: [Color(0xFF00BCD4), Color(0xFF00ACC1)],
                      begin: Alignment.topCenter,
                      end: Alignment.bottomCenter,
                    ),
                  ),
                  child: Padding(
                    padding: const EdgeInsets.symmetric(
                      horizontal: 20,
                      vertical: 30,
                    ),
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      mainAxisAlignment: MainAxisAlignment.end,
                      children: [
                        Row(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            Expanded(
                              child: Column(
                                crossAxisAlignment: CrossAxisAlignment.start,
                                children: [
                                  Text(
                                    "Hi, $username",
                                    style: const TextStyle(
                                      fontSize: 28,
                                      fontWeight: FontWeight.bold,
                                      color: Colors.white,
                                    ),
                                  ),
                                  const SizedBox(height: 4),
                                  const Text(
                                    "Shared success is based on presence",
                                    style: TextStyle(
                                      fontSize: 16,
                                      color: Colors.white70,
                                    ),
                                  ),
                                ],
                              ),
                            ),
                            const SizedBox(width: 16),
                            GestureDetector(
                              onTap: () {
                                Navigator.push(
                                  context,
                                  MaterialPageRoute(
                                    builder: (_) => const SettingsPage(),
                                  ),
                                ).then((_) {
                                  _loadProfileData();
                                });
                              },
                              child: CircleAvatar(
                                radius: 42,
                                backgroundColor: Colors.grey.shade200,
                                backgroundImage: _profilePictureUrl.isNotEmpty
                                    ? NetworkImage(_profilePictureUrl)
                                    : null,
                                child: _profilePictureUrl.isEmpty
                                    ? Text(
                                        _getInitials(username),
                                        style: GoogleFonts.poppins(
                                          fontSize: 36,
                                          fontWeight: FontWeight.bold,
                                          color: Colors.grey.shade600,
                                        ),
                                      )
                                    : null,
                              ),
                            ),
                          ],
                        ),
                      ],
                    ),
                  ),
                ),
              ),
              backgroundColor: Colors.white70,
              iconTheme: const IconThemeData(color: Colors.white),
              floating: true,
            ),
            if (_isProfileInComplete)
              SliverToBoxAdapter(child: ProfileCompletionNotification()),
            SliverList(
              delegate: SliverChildListDelegate([
                _buildInfoCard(),
                _buildPresensiButton(),
                const SizedBox(height: 20),
                MapLocationWidget(key: _mapKey),
                const SizedBox(height: 20),
              ]),
            ),
          ],
        ),
      ),
      floatingActionButton: FloatingActionButton.extended(
        onPressed: () {
          Navigator.push(
            context,
            MaterialPageRoute(builder: (context) => const PermitPage()),
          );
        },
        icon: const Icon(Icons.assignment),
        label: const Text('Ajukan Izin'),
      ),
      backgroundColor: Colors.white70,
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
                      color: const Color(0xFF4CAF50),
                    ),
                    StatusInfo(
                      label: "Izin", // PERBAIKAN: Mengubah label
                      count:
                          "${monthlySummary['izin']} Hari", // PERBAIKAN: Mengambil dari key 'izin'
                      color: const Color(0xFFFF9800),
                    ),
                    StatusInfo(
                      label: "Tidak Hadir",
                      count: "${monthlySummary['tidakHadir']} Hari",
                      color: const Color(0xFFF44336),
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
          final isActiveOfficeAvailable =
              _mapKey.currentState?.activeOfficeName != null;
          if (isActiveOfficeAvailable) {
            _ambilFotoDanUpload();
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
