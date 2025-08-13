import 'package:cloud_firestore/cloud_firestore.dart';
import 'package:firebase_auth/firebase_auth.dart';
import 'package:flutter/material.dart';

/// ======== MODEL ========
class AttendanceData {
  final String date;       // yyyy-MM-ddTHH:mm:ss.SSS (string kunci)
  final String clockIn;
  final String clockOut;
  final bool isLate;
  final String lateDuration;
  final String name;

  const AttendanceData({
    required this.date,
    required this.clockIn,
    required this.clockOut,
    required this.isLate,
    required this.lateDuration,
    required this.name,
  });

  factory AttendanceData.fromMap(Map<String, dynamic> m) => AttendanceData(
    date: (m['date'] ?? '') as String,
    clockIn: (m['clockIn'] ?? '') as String,
    clockOut: (m['clockOut'] ?? '') as String,
    isLate: (m['late'] ?? false) as bool,
    lateDuration: (m['lateDuration'] ?? '') as String,
    name: (m['name'] ?? '') as String,
  );
}

class AttendanceSummary {
  final int totalDays;
  final int presentDays;
  final int lateDays;
  final int absentDays;
  final List<AttendanceData> attendances;

  const AttendanceSummary({
    required this.totalDays,
    required this.presentDays,
    required this.lateDays,
    required this.absentDays,
    required this.attendances,
  });
}

/// ======== SERVICE ========
class AttendanceService {
  AttendanceService._();
  static final instance = AttendanceService._();
  final _fs = FirebaseFirestore.instance;
  final _auth = FirebaseAuth.instance;

  Future<AttendanceSummary> monthlyRecap({int? month, int? year}) async {
    final user = _auth.currentUser;
    if (user == null) throw Exception('Silakan login.');

    final now = DateTime.now();
    final y = year ?? now.year;
    final m = month ?? now.month;

    final start = DateTime(y, m, 1);
    final end = DateTime(y, m + 1, 0);

    final qs = await _fs
        .collection('presence')
        .where('uid', isEqualTo: user.uid)
        .where('date', isGreaterThanOrEqualTo: _key(start))
        .where('date', isLessThanOrEqualTo: _key(end))
        .orderBy('date') // penting untuk index (uid==, date range + orderBy date)
        .get();

    final items = qs.docs
        .map((d) => AttendanceData.fromMap(d.data()))
        .toList();

    final lateDays = items.where((e) => e.isLate).length;
    final totalWorking = _workingDays(start, end);
    final present = items.length;
    final absent = (totalWorking - present).clamp(0, totalWorking);

    return AttendanceSummary(
      totalDays: totalWorking,
      presentDays: present,
      lateDays: lateDays,
      absentDays: absent,
      attendances: items,
    );
  }

  // YYYY-MM-DDT00:00:00.000 – disesuaikan dengan format di koleksi kamu
  String _key(DateTime d) =>
      '${d.year}-${d.month.toString().padLeft(2, '0')}-${d.day.toString().padLeft(2, '0')}T00:00:00.000';

  int _workingDays(DateTime start, DateTime end) {
    var d = start;
    var c = 0;
    while (!d.isAfter(end)) {
      if (d.weekday >= DateTime.monday && d.weekday <= DateTime.friday) c++;
      d = d.add(const Duration(days: 1));
    }
    return c;
  }
}

/// ======== UI ========
class AttendanceRecapPage extends StatefulWidget {
  const AttendanceRecapPage({super.key});
  @override
  State<AttendanceRecapPage> createState() => _AttendanceRecapPageState();
}

class _AttendanceRecapPageState extends State<AttendanceRecapPage> {
  late Future<AttendanceSummary> _future;

  @override
  void initState() {
    super.initState();
    _future = AttendanceService.instance.monthlyRecap();
  }

  void _reload() {
    setState(() => _future = AttendanceService.instance.monthlyRecap());
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text('Rekap Absensi')),
      body: FutureBuilder<AttendanceSummary>(
        future: _future,
        builder: (context, snap) {
          if (snap.connectionState != ConnectionState.done) {
            return const Center(
              child: Column(
                mainAxisSize: MainAxisSize.min,
                children: [CircularProgressIndicator(), SizedBox(height: 12), Text('Memuat...')],
              ),
            );
          }
          if (snap.hasError) {
            return _ErrorView(message: '${snap.error}', onRetry: _reload);
          }
          final s = snap.data!;
          return Padding(
            padding: const EdgeInsets.all(16),
            child: Column(
              children: [
                Card(
                  child: Padding(
                    padding: const EdgeInsets.all(16),
                    child: Column(
                      children: [
                        const Text('Ringkasan Bulanan',
                            style: TextStyle(fontSize: 18, fontWeight: FontWeight.bold)),
                        const SizedBox(height: 12),
                        Row(
                          mainAxisAlignment: MainAxisAlignment.spaceAround,
                          children: [
                            _Stat('Total Hari', '${s.totalDays}'),
                            _Stat('Hadir', '${s.presentDays}'),
                            _Stat('Terlambat', '${s.lateDays}'),
                            _Stat('Tidak Hadir', '${s.absentDays}'),
                          ],
                        ),
                      ],
                    ),
                  ),
                ),
                const SizedBox(height: 12),
                ElevatedButton(onPressed: _reload, child: const Text('Muat Ulang')),
                const SizedBox(height: 8),
                Expanded(
                  child: s.attendances.isEmpty
                      ? const Center(child: Text('Tidak ada data.'))
                      : ListView.separated(
                    itemCount: s.attendances.length,
                    separatorBuilder: (_, _) => const Divider(height: 1),
                    itemBuilder: (_, i) {
                      final a = s.attendances[i];
                      final status = a.isLate ? 'Terlambat ${a.lateDuration}' : 'Tepat waktu';
                      return ListTile(
                        title: Text(_dispDate(a.date)),
                        subtitle: Text('Masuk: ${a.clockIn} • Keluar: ${a.clockOut}'),
                        trailing: Text(status),
                      );
                    },
                  ),
                ),
              ],
            ),
          );
        },
      ),
    );
  }

  String _dispDate(String v) {
    try {
      final d = v.contains('T') ? DateTime.parse(v) : DateTime.now();
      return '${d.day.toString().padLeft(2, '0')}-${d.month.toString().padLeft(2, '0')}-${d.year}';
    } catch (_) {
      return v;
    }
  }
}

class _Stat extends StatelessWidget {
  final String label, value;
  const _Stat(this.label, this.value);
  @override
  Widget build(BuildContext context) => Column(
    children: [
      Text(value, style: const TextStyle(fontSize: 22, fontWeight: FontWeight.bold, color: Colors.blue)),
      Text(label, style: const TextStyle(fontSize: 12)),
    ],
  );
}

class _ErrorView extends StatelessWidget {
  final String message;
  final VoidCallback onRetry;
  const _ErrorView({required this.message, required this.onRetry});
  @override
  Widget build(BuildContext context) => Center(
    child: Padding(
      padding: const EdgeInsets.all(16),
      child: Column(mainAxisSize: MainAxisSize.min, children: [
        Text(message, textAlign: TextAlign.center),
        const SizedBox(height: 12),
        ElevatedButton(onPressed: onRetry, child: const Text('Coba Lagi')),
      ]),
    ),
  );
}
