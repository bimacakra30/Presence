import 'package:cloud_firestore/cloud_firestore.dart';
import 'package:firebase_auth/firebase_auth.dart';
import 'package:flutter/material.dart';
import 'package:table_calendar/table_calendar.dart';
import 'package:intl/intl.dart';

class AttendanceHistoryPage extends StatefulWidget {
  const AttendanceHistoryPage({super.key});

  @override
  State<AttendanceHistoryPage> createState() => _AttendanceHistoryPageState();
}

class _AttendanceHistoryPageState extends State<AttendanceHistoryPage> {
  Map<DateTime, Map<String, dynamic>> attendanceMap = {};
  DateTime focusedDay = DateTime.now();
  DateTime? selectedDay;
  String? userId;

  @override
  void initState() {
    super.initState();
    loadUserAndFetchData();
  }

  Future<void> loadUserAndFetchData() async {
    final user = FirebaseAuth.instance.currentUser;

    if (user != null) {
      userId = user.uid;
      await fetchAttendanceData();
    } else {
      debugPrint('User not logged in');
    }
  }

  Future<void> fetchAttendanceData() async {
    if (userId == null) return;

    final snapshot = await FirebaseFirestore.instance
        .collection('presence')
        .where('uid', isEqualTo: userId)
        .get();

    Map<DateTime, Map<String, dynamic>> tempMap = {};

    for (var doc in snapshot.docs) {
      final data = doc.data();
      final dateString = (data['date'] as String?) ?? '';
      final parsedDate = DateTime.tryParse(dateString);

      if (parsedDate != null) {
        final dateOnly = DateTime(parsedDate.year, parsedDate.month, parsedDate.day);
        tempMap[dateOnly] = data;
      }
    }

    setState(() {
      attendanceMap = tempMap;
    });
  }

  Color getStatusColor(Map<String, dynamic>? data) {
    if (data == null) return const Color(0xFFF44336); // Merah: Tidak Hadir
    if (data['late'] == true) return const Color(0xFFFF9800); // Oranye: Terlambat
    if (data['clockOut'] != null) return const Color(0xFF4CAF50); // Hijau: Hadir
    return const Color(0xFF2196F3); // Biru: Sudah clock-in, belum clock-out
  }

  String getStatusLabel(Map<String, dynamic>? data) {
    if (data == null) return 'Tidak Hadir';
    if (data['late'] == true) return 'Terlambat';
    if (data['clockOut'] != null) return 'Hadir';
    return 'Belum Clock Out';
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: const Color(0xFFF5F5F5),
      appBar: AppBar(
        title: const Text(
          'Riwayat Presensi Bulanan',
          style: TextStyle(fontWeight: FontWeight.w600, fontSize: 20),
        ),
        backgroundColor: const Color(0xFF00BCD4),
        foregroundColor: Colors.white,
        elevation: 0,
        flexibleSpace: Container(
          decoration: const BoxDecoration(
            gradient: LinearGradient(
              begin: Alignment.topLeft,
              end: Alignment.bottomRight,
              colors: [Color(0xFF00BCD4), Color(0xFF26C6DA)],
            ),
          ),
        ),
      ),
      body: SingleChildScrollView(
        child: Column(
          children: [
            Container(
              margin: const EdgeInsets.all(16),
              padding: const EdgeInsets.all(16),
              decoration: BoxDecoration(
                color: Colors.white,
                borderRadius: BorderRadius.circular(20),
                boxShadow: [
                  BoxShadow(
                    color: const Color(0xFF00BCD4).withAlpha((255 * 0.2).round()),
                    spreadRadius: 2,
                    blurRadius: 10,
                    offset: const Offset(0, 4),
                  ),
                ],
              ),
              child: TableCalendar(
                firstDay: DateTime(DateTime.now().year, DateTime.now().month - 1, 1),
                lastDay: DateTime(DateTime.now().year, DateTime.now().month + 1, 31),
                focusedDay: focusedDay,
                selectedDayPredicate: (day) => isSameDay(selectedDay, day),
                onDaySelected: (day, focus) {
                  setState(() {
                    selectedDay = day;
                    focusedDay = focus;
                  });
                },
                headerStyle: HeaderStyle(
                  formatButtonVisible: false,
                  titleCentered: true,
                  titleTextStyle: const TextStyle(
                    fontSize: 18,
                    fontWeight: FontWeight.bold,
                    color: Color(0xFF00BCD4),
                  ),
                  leftChevronIcon: const Icon(Icons.chevron_left, color: Color(0xFF00BCD4)),
                  rightChevronIcon: const Icon(Icons.chevron_right, color: Color(0xFF00BCD4)),
                ),
                daysOfWeekStyle: const DaysOfWeekStyle(
                  weekdayStyle: TextStyle(color: Color(0xFF00BCD4), fontWeight: FontWeight.w600),
                  weekendStyle: TextStyle(color: Color(0xFF26C6DA), fontWeight: FontWeight.w600),
                ),
                calendarStyle: CalendarStyle(
                  outsideDaysVisible: false,
                  weekendTextStyle: const TextStyle(color: Color(0xFF00BCD4)),
                  todayDecoration: const BoxDecoration(
                    color: Color(0xFF26C6DA),
                    shape: BoxShape.circle,
                  ),
                  selectedDecoration: const BoxDecoration(
                    color: Color(0xFF00BCD4),
                    shape: BoxShape.circle,
                  ),
                  defaultTextStyle: const TextStyle(color: Color(0xFF2F4F4F)),
                ),
                calendarBuilders: CalendarBuilders(
                  defaultBuilder: (context, day, _) {
                    final dateKey = DateTime(day.year, day.month, day.day);
                    final data = attendanceMap[dateKey];
                    return Container(
                      margin: const EdgeInsets.all(4),
                      decoration: BoxDecoration(
                        gradient: LinearGradient(
                          begin: Alignment.topLeft,
                          end: Alignment.bottomRight,
                          colors: [
                            getStatusColor(data),
                            getStatusColor(data).withAlpha((255 * 0.8).round()),
                          ],
                        ),
                        borderRadius: BorderRadius.circular(12),
                        boxShadow: [
                          BoxShadow(
                            color: getStatusColor(data).withAlpha((255 * 0.3).round()),
                            spreadRadius: 1,
                            blurRadius: 3,
                            offset: const Offset(0, 2),
                          ),
                        ],
                      ),
                      alignment: Alignment.center,
                      child: Text(
                        '${day.day}',
                        style: const TextStyle(
                          color: Colors.white,
                          fontWeight: FontWeight.w600,
                          fontSize: 14,
                        ),
                      ),
                    );
                  },
                ),
              ),
            ),

            // Legend
            buildLegendSection(),

            const SizedBox(height: 20),
            if (selectedDay != null) buildDetailInfo(selectedDay!),
            const SizedBox(height: 20),
          ],
        ),
      ),
    );
  }

  Widget buildLegendSection() {
    return Container(
      margin: const EdgeInsets.symmetric(horizontal: 16),
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(15),
        boxShadow: [
          BoxShadow(
            color: const Color(0xFF00BCD4).withAlpha(26),
            spreadRadius: 1,
            blurRadius: 5,
            offset: const Offset(0, 2),
          ),
        ],
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          const Text(
            'Keterangan:',
            style: TextStyle(
              fontSize: 16,
              fontWeight: FontWeight.bold,
              color: Color(0xFF00BCD4),
            ),
          ),
          const SizedBox(height: 12),
          Row(
            children: [
              Expanded(child: buildLegendItem(color: const Color(0xFF4CAF50), label: 'Hadir')),
              Expanded(child: buildLegendItem(color: const Color(0xFFFF9800), label: 'Terlambat')),
            ],
          ),
          const SizedBox(height: 8),
          Row(
            children: [
              Expanded(child: buildLegendItem(color: const Color(0xFFF44336), label: 'Tidak Hadir')),
              Expanded(child: buildLegendItem(color: const Color(0xFF2196F3), label: 'Belum Melakukan Presensi Pulang')),
            ],
          ),
        ],
      ),
    );
  }

  Widget buildLegendItem({required Color color, required String label}) {
    return Row(
      children: [
        Container(
          width: 16,
          height: 16,
          decoration: BoxDecoration(color: color, borderRadius: BorderRadius.circular(8)),
        ),
        const SizedBox(width: 8),
        Flexible(
          child: Text(
            label,
            style: const TextStyle(fontSize: 12, color: Color(0xFF2F4F4F)),
          ),
        ),
      ],
    );
  }

  Widget buildDetailInfo(DateTime date) {
    final dateKey = DateTime(date.year, date.month, date.day);
    final data = attendanceMap[dateKey];

    return Container(
      margin: const EdgeInsets.symmetric(horizontal: 16),
      padding: const EdgeInsets.all(20),
      decoration: BoxDecoration(
        gradient: const LinearGradient(
          begin: Alignment.topLeft,
          end: Alignment.bottomRight,
          colors: [Color(0xFFE0F7FA), Color(0xFFB2EBF2)],
        ),
        borderRadius: BorderRadius.circular(20),
        border: Border.all(color: Color(0x4D00BCD4), width: 1),
        boxShadow: [
          BoxShadow(
            color: const Color(0xFF00BCD4).withAlpha(51),
            spreadRadius: 2,
            blurRadius: 8,
            offset: const Offset(0, 4),
          ),
        ],
      ),
      child: Column(
        children: [
          Container(
            padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 8),
            decoration: BoxDecoration(
              color: const Color(0xFF00BCD4),
              borderRadius: BorderRadius.circular(12),
            ),
            child: Text(
              DateFormat('EEEE, dd MMMM yyyy', 'id_ID').format(date),
              style: const TextStyle(fontSize: 16, fontWeight: FontWeight.w600, color: Colors.white),
            ),
          ),
          const SizedBox(height: 16),
          Container(
            padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 12),
            decoration: BoxDecoration(
              color: getStatusColor(data).withAlpha(26),
              borderRadius: BorderRadius.circular(12),
              border: Border.all(color: getStatusColor(data).withAlpha(77), width: 1),
            ),
            child: Row(
              mainAxisAlignment: MainAxisAlignment.center,
              children: [
                Container(
                  width: 12,
                  height: 12,
                  decoration: BoxDecoration(color: getStatusColor(data), shape: BoxShape.circle),
                ),
                const SizedBox(width: 8),
                Text(
                  getStatusLabel(data),
                  style: TextStyle(fontSize: 18, color: getStatusColor(data), fontWeight: FontWeight.bold),
                ),
              ],
            ),
          ),
          if (data != null && (data['clockIn'] != null || data['clockOut'] != null)) ...[
            const SizedBox(height: 16),
            Container(
              padding: const EdgeInsets.all(16),
              decoration: BoxDecoration(
                color: Colors.white,
                borderRadius: BorderRadius.circular(12),
                border: Border.all(color: const Color(0xFF87CEEB).withAlpha(51), width: 1),
              ),
              child: Column(
                children: [
                  if (data['clockIn'] != null)
                    buildTimeInfo(
                      icon: Icons.login,
                      label: 'Waktu Masuk',
                      time: DateFormat.Hm().format(DateTime.parse(data['clockIn'])),
                      color: const Color(0xFF00BCD4),
                    ),
                  if (data['clockIn'] != null && data['clockOut'] != null) const SizedBox(height: 12),
                  if (data['clockOut'] != null)
                    buildTimeInfo(
                      icon: Icons.logout,
                      label: 'Waktu Pulang',
                      time: DateFormat.Hm().format(DateTime.parse(data['clockOut'])),
                      color: const Color(0xFF26C6DA),
                    ),
                ],
              ),
            ),
          ],
        ],
      ),
    );
  }

  Widget buildTimeInfo({
    required IconData icon,
    required String label,
    required String time,
    required Color color,
  }) {
    return Row(
      children: [
        Container(
          padding: const EdgeInsets.all(8),
          decoration: BoxDecoration(color: color.withAlpha(26), borderRadius: BorderRadius.circular(8)),
          child: Icon(icon, color: color, size: 20),
        ),
        const SizedBox(width: 12),
        Expanded(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(
                label,
                style: const TextStyle(fontSize: 12, color: Color(0xFF666666), fontWeight: FontWeight.w500),
              ),
              Text(
                time,
                style: TextStyle(fontSize: 16, color: color, fontWeight: FontWeight.bold),
              ),
            ],
          ),
        ),
      ],
    );
  }
}