import 'package:cloud_firestore/cloud_firestore.dart';
import 'package:firebase_auth/firebase_auth.dart';
import 'package:shared_preferences/shared_preferences.dart';

Future<String?> getCurrentUid() async {
  final prefs = await SharedPreferences.getInstance();
  return FirebaseAuth.instance.currentUser?.uid ?? prefs.getString('uid');
}

// Future<Map<String, dynamic>?> fetchPresensiHariIni() async {
//   final uid = await getCurrentUid();
//   if (uid == null) return null;

//   final now = DateTime.now();
//   final todayStart = DateTime(now.year, now.month, now.day);

//   final query = await FirebaseFirestore.instance
//       .collection('presence')
//       .where('uid', isEqualTo: uid)
//       .where('date', isEqualTo: todayStart.toIso8601String())
//       .limit(1)
//       .get();

//   if (query.docs.isNotEmpty) {
//     return query.docs.first.data();
//   }
//   return null;
// }

Future<Map<String, dynamic>?> fetchPresensiHariIniUtil() async {
  final uid = await getCurrentUid();
  if (uid == null) return null;

  final now = DateTime.now();
  final todayStart = DateTime(now.year, now.month, now.day);

  final query = await FirebaseFirestore.instance
      .collection('presence')
      .where('uid', isEqualTo: uid)
      .where('date', isEqualTo: todayStart.toIso8601String())
      .limit(1)
      .get();

  if (query.docs.isNotEmpty) {
    return query.docs.first.data();
  }
  return null;
}

Future<Map<String, int>> fetchMonthlyAttendance() async {
  final uid = await getCurrentUid();
  if (uid == null) return {'hadir': 0, 'terlambat': 0, 'tidakHadir': 0};

  final now = DateTime.now();
  final firstDayOfMonth = DateTime(now.year, now.month, 1);
  final lastDayOfMonth = DateTime(now.year, now.month + 1, 0);

  final query = await FirebaseFirestore.instance
      .collection('presence')
      .where('uid', isEqualTo: uid)
      .where('date', isGreaterThanOrEqualTo: firstDayOfMonth.toIso8601String())
      .where('date', isLessThanOrEqualTo: lastDayOfMonth.toIso8601String())
      .get();

  int hadir = 0;
  int terlambat = 0;
  int tidakHadir = 0;

  Set<DateTime> attendanceDays = {};
  for (var doc in query.docs) {
    final data = doc.data();
    final dateString = data['date'] as String?;
    if (dateString != null) {
      final parsedDate = DateTime.parse(dateString);
      final dateOnly = DateTime(parsedDate.year, parsedDate.month, parsedDate.day);
      attendanceDays.add(dateOnly);

      if (data['clockIn'] != null && data['clockOut'] != null) {
        if (data['late'] == true) {
          terlambat++;
        } else {
          hadir++;
        }
      }
    }
  }

  for (int day = 1; day <= now.day; day++) {
    final currentDay = DateTime(now.year, now.month, day);
    if (!attendanceDays.contains(currentDay)) {
      tidakHadir++;
    }
  }

  return {
    'hadir': hadir,
    'terlambat': terlambat,
    'tidakHadir': tidakHadir,
  };
}