import 'package:cloud_firestore/cloud_firestore.dart';
import 'package:firebase_auth/firebase_auth.dart';
import 'package:shared_preferences/shared_preferences.dart';

Future<String?> getCurrentUid() async {
  final prefs = await SharedPreferences.getInstance();
  return FirebaseAuth.instance.currentUser?.uid ?? prefs.getString('uid');
}

Future<Map<String, dynamic>?> fetchPresensiHariIni() async {
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