import 'package:flutter/material.dart';
import 'package:firebase_auth/firebase_auth.dart';
import 'package:image_picker/image_picker.dart';
import 'package:intl/intl.dart';
import 'package:shared_preferences/shared_preferences.dart';

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

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: PreferredSize(
        preferredSize: Size.fromHeight(10),
        child: AppBar(
          backgroundColor: Color.fromARGB(195, 0, 159, 227),
          elevation: 0,
        ),
      ),
      body: SafeArea(
        child: ListView(
          padding: EdgeInsets.only(
            bottom: MediaQuery.of(context).viewPadding.bottom + 16,
          ),
          children: [
            Container(
              padding: const EdgeInsets.only(
                top: 16,
                left: 24,
                right: 24,
                bottom: 16,
              ),
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
                          borderRadius: BorderRadius.vertical(
                            top: Radius.circular(24),
                          ),
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
            ),

            Container(
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
                            const Text(
                              "Tanggal",
                              style: TextStyle(fontSize: 13),
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
                            const SizedBox(height: 4),
                            const Text(
                              "Masuk : -",
                              style: TextStyle(color: Colors.blue),
                            ),
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
                            style: TextStyle(
                              fontWeight: FontWeight.bold,
                              fontSize: 15,
                            ),
                          ),
                          SizedBox(height: 4),
                          Text(
                            "Pulang : -",
                            style: TextStyle(color: Colors.blue),
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
                      _StatusInfo(
                        label: "Hadir",
                        count: "7 Hari",
                        color: Colors.green,
                      ),
                      _StatusInfo(
                        label: "Izin",
                        count: "0 Hari",
                        color: Colors.orange,
                      ),
                      _StatusInfo(
                        label: "Tidak Hadir",
                        count: "0 Hari",
                        color: Colors.red,
                      ),
                    ],
                  ),
                ],
              ),
            ),

            Container(
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
                onPressed: () async {
                  final picker = ImagePicker();
                  final XFile? photo = await picker.pickImage(
                    source: ImageSource.camera,
                  );

                  if (photo != null) {
                    ScaffoldMessenger.of(context).showSnackBar(
                      SnackBar(
                        content: Text('Foto berhasil diambil: ${photo.name}'),
                      ),
                    );
                  } else {
                    ScaffoldMessenger.of(context).showSnackBar(
                      const SnackBar(
                        content: Text('Pengambilan foto dibatalkan'),
                      ),
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
            ),

            const SizedBox(height: 20),
            const Padding(
              padding: EdgeInsets.symmetric(horizontal: 24),
              child: Align(
                alignment: Alignment.centerLeft,
                child: Text(
                  "Menu Utama",
                  style: TextStyle(fontSize: 16, fontWeight: FontWeight.bold),
                ),
              ),
            ),

            const SizedBox(height: 12),
            Padding(
              padding: const EdgeInsets.symmetric(horizontal: 24),
              child: Wrap(
                alignment: WrapAlignment.spaceBetween,
                runSpacing: 16,
                spacing: 6,
                children: const [
                  _MenuIcon(
                    icon: Icons.assignment_outlined,
                    label: "Riwayat Presensi",
                  ),
                  _MenuIcon(icon: Icons.location_on_outlined, label: "Lokasi"),
                  _MenuIcon(icon: Icons.mail_outline, label: "Pengajuan Izin"),
                  _MenuIcon(
                    icon: Icons.event_note_outlined,
                    label: "Aktivitas",
                  ),
                  _MenuIcon(
                    icon: Icons.attach_money_outlined,
                    label: "Informasi Gaji",
                  ),
                ],
              ),
            ),

            const SizedBox(height: 32),
          ],
        ),
      ),
    );
  }
}

class _StatusInfo extends StatelessWidget {
  final String label;
  final String count;
  final Color color;

  const _StatusInfo({
    required this.label,
    required this.count,
    required this.color,
  });

  @override
  Widget build(BuildContext context) {
    return Column(
      children: [
        Text(
          count,
          style: TextStyle(
            color: color,
            fontWeight: FontWeight.bold,
            fontSize: 16,
          ),
        ),
        const SizedBox(height: 4),
        Text(label, style: const TextStyle(fontSize: 13)),
        const SizedBox(height: 4),
        Container(
          width: 40,
          height: 4,
          decoration: BoxDecoration(
            color: color,
            borderRadius: BorderRadius.circular(2),
          ),
        ),
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
    return SizedBox(
      width: 100,
      child: Column(
        children: [
          Container(
            padding: const EdgeInsets.all(14),
            decoration: BoxDecoration(
              color: Colors.white,
              borderRadius: BorderRadius.circular(16),
              boxShadow: const [
                BoxShadow(
                  color: Colors.black12,
                  blurRadius: 4,
                  offset: Offset(0, 2),
                ),
              ],
            ),
            child: Icon(icon, color: Colors.cyan[700], size: 28),
          ),
          const SizedBox(height: 8),
          Text(
            label,
            textAlign: TextAlign.center,
            style: const TextStyle(fontSize: 12),
          ),
        ],
      ),
    );
  }
}

class _ProfileModal extends StatelessWidget {
  final String name;

  const _ProfileModal({required this.name});

  @override
  Widget build(BuildContext context) {
    final user = FirebaseAuth.instance.currentUser;

    return FutureBuilder<String?>(
      future: _getEmail(user),
      builder: (context, snapshot) {
        final email = snapshot.data ?? "-";
        return Padding(
          padding: const EdgeInsets.all(24),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              Container(
                width: 40,
                height: 4,
                margin: const EdgeInsets.only(bottom: 16),
                decoration: BoxDecoration(
                  color: Colors.grey[300],
                  borderRadius: BorderRadius.circular(2),
                ),
              ),
              CircleAvatar(
                radius: 40,
                backgroundColor: Colors.blue[100],
                child: Icon(Icons.person, size: 40, color: Colors.blue),
              ),
              const SizedBox(height: 12),
              Text(
                name,
                style: const TextStyle(
                  fontSize: 18,
                  fontWeight: FontWeight.bold,
                ),
              ),
              const SizedBox(height: 4),
              Text(
                email,
                style: const TextStyle(fontSize: 14, color: Colors.grey),
              ),
              const SizedBox(height: 24),
              ListTile(
                leading: const Icon(Icons.settings),
                title: const Text("Pengaturan Akun"),
                onTap: () {
                  Navigator.pop(context);
                },
              ),
              ListTile(
                leading: const Icon(Icons.logout),
                title: const Text("Keluar"),
                onTap: () async {
                  await FirebaseAuth.instance.signOut();
                  if (context.mounted) {
                    Navigator.pushAndRemoveUntil(
                      context,
                      MaterialPageRoute(
                        builder: (context) => const LoginPage(),
                      ),
                      (route) => false,
                    );
                  }
                },
              ),
            ],
          ),
        );
      },
    );
  }

  Future<String?> _getEmail(User? user) async {
    if (user != null && user.email != null) return user.email;
    final prefs = await SharedPreferences.getInstance();
    return prefs.getString('email');
  }
}
