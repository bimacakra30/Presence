import 'package:Presence/pages/edit_profile_page.dart';
import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:firebase_auth/firebase_auth.dart';
import 'package:google_sign_in/google_sign_in.dart';
import 'package:shared_preferences/shared_preferences.dart';
import 'login_page.dart';
import 'history.dart';
import 'permit_history_page.dart';
import 'package:Presence/components/profile_avatar.dart';
import 'package:Presence/pages/profile_detail_page.dart';

class SettingsPage extends StatefulWidget {
  const SettingsPage({super.key});

  @override
  State<SettingsPage> createState() => _SettingsPageState();
}

class _SettingsPageState extends State<SettingsPage>
    with TickerProviderStateMixin {
  late AnimationController _animationController;
  late Animation<double> _fadeAnimation;
  late Animation<Offset> _slideAnimation;

  String _username = 'User';
  String _profilePictureUrl = '';
  String _email = '';
  bool _pushNotifications = true;
  bool _appNotifications = true;

  @override
  void initState() {
    super.initState();
    _initializeAnimations();
    _loadUserData();
  }

  void _initializeAnimations() {
    _animationController = AnimationController(
      duration: const Duration(milliseconds: 800),
      vsync: this,
    );
    _fadeAnimation = Tween<double>(begin: 0.0, end: 1.0).animate(
      CurvedAnimation(parent: _animationController, curve: Curves.easeOut),
    );
    _slideAnimation =
        Tween<Offset>(begin: const Offset(0, 0.2), end: Offset.zero).animate(
          CurvedAnimation(
            parent: _animationController,
            curve: Curves.easeOutCubic,
          ),
        );

    _animationController.forward();
  }

  Future<void> _loadUserData() async {
    final prefs = await SharedPreferences.getInstance();
    if (!mounted) return;

    setState(() {
      _profilePictureUrl = prefs.getString('profilePictureUrl') ?? '';
      _username = prefs.getString('name') ?? 'User';
      _email = prefs.getString('email') ?? '';
      _pushNotifications = prefs.getBool('push_notifications') ?? true;
      _appNotifications = prefs.getBool('app_notifications') ?? true;
    });
  }

  Future<void> _updateNotificationSetting(String key, bool value) async {
    final prefs = await SharedPreferences.getInstance();
    await prefs.setBool(key, value);
  }

  @override
  void dispose() {
    _animationController.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: Colors.grey.shade50,
      body: CustomScrollView(
        slivers: [
          _buildAppBar(),
          SliverToBoxAdapter(
            child: FadeTransition(
              opacity: _fadeAnimation,
              child: SlideTransition(
                position: _slideAnimation,
                child: _buildContent(),
              ),
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildAppBar() {
    return SliverAppBar(
      expandedHeight: 111,
      backgroundColor: const Color(0xFF00A0E3),
      elevation: 0,
      pinned: true,
      stretch: true,
      leading: IconButton(
        icon: const Icon(Icons.arrow_back_ios, color: Colors.white),
        onPressed: () => Navigator.of(context).pop(),
      ),
      flexibleSpace: FlexibleSpaceBar(
        stretchModes: const [StretchMode.zoomBackground],
        background: Container(
          decoration: const BoxDecoration(
            gradient: LinearGradient(
              colors: [Color(0xFF00BCD4), Color(0xFF00ACC1)],
              begin: Alignment.topCenter,
              end: Alignment.bottomCenter,
            ),
          ),
          child: const SafeArea(
            child: Padding(
              padding: EdgeInsets.only(bottom: 12.0, left: 45.0),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                mainAxisAlignment: MainAxisAlignment.end,
                children: [
                  Text(
                    'Pengaturan',
                    style: TextStyle(
                      fontSize: 28,
                      fontWeight: FontWeight.bold,
                      color: Colors.white,
                      letterSpacing: 0.5,
                    ),
                  ),
                  SizedBox(height: 8),
                  Text(
                    'Kelola akun dan preferensi Anda',
                    style: TextStyle(
                      fontSize: 16,
                      color: Colors.white70,
                      fontWeight: FontWeight.w400,
                    ),
                  ),
                  SizedBox(height: 20),
                ],
              ),
            ),
          ),
        ),
      ),
    );
  }

  Widget _buildContent() {
    return Padding(
      padding: const EdgeInsets.all(20),
      child: Column(
        children: [
          _buildProfileSection(),
          const SizedBox(height: 24),
          _buildAccountSection(),
          const SizedBox(height: 24),
          _buildNotificationSection(),
          const SizedBox(height: 24),
          _buildGeneralSection(),
          const SizedBox(height: 32),
          _buildLogoutButton(),
          const SizedBox(height: 20),
        ],
      ),
    );
  }

  Widget _buildProfileSection() {
    // Menghilangkan logika pemisahan nama menjadi dua baris karena tidak digunakan di sini
    // dan bisa menyebabkan kebingungan. Cukup tampilkan _username apa adanya.

    return InkWell(
      onTap: () {
        // Navigasi ke ProfileDetailPage, bukan EditProfilePage
        Navigator.push(
          context,
          MaterialPageRoute(builder: (context) => const ProfileDetailPage()),
        ).then((value) {
          // Memuat ulang data saat kembali dari ProfileDetailPage (jika ada perubahan dari EditProfilePage di dalamnya)
          _loadUserData();
        });
      },
      child: Container(
        decoration: BoxDecoration(
          color: Colors.white,
          borderRadius: BorderRadius.circular(16),
          boxShadow: [
            BoxShadow(
              color: Colors.black.withOpacity(0.05),
              blurRadius: 10,
              offset: const Offset(0, 2),
            ),
          ],
        ),
        padding: const EdgeInsets.all(20),
        child: Row(
          children: [
            ProfileAvatar(
              profilePictureUrl: _profilePictureUrl,
              name: _username,
            ),
            const SizedBox(width: 16),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    _username, // Tampilkan username lengkap
                    style: const TextStyle(
                      fontSize: 18,
                      fontWeight: FontWeight.w600,
                      color: Colors.black87,
                    ),
                  ),
                  const SizedBox(height: 4),
                  Text(
                    _email.isNotEmpty ? _email : 'Email tidak tersedia',
                    style: TextStyle(fontSize: 14, color: Colors.grey.shade600),
                    overflow: TextOverflow.ellipsis,
                  ),
                ],
              ),
            ),
            const Icon(
              Icons.chevron_right,
              color: Colors.grey,
            ),
          ],
        ),
      ),
    );
  }

  Widget _buildAccountSection() {
    return _buildSection(
      title: 'Akun',
      icon: Icons.person_outline,
      children: [
        _buildMenuItem(
          icon: Icons.history,
          title: 'Riwayat Presensi',
          subtitle: 'Lihat histori kehadiran Anda',
          onTap: () {
            HapticFeedback.lightImpact();
            Navigator.push(
              context,
              MaterialPageRoute(
                builder: (context) => const AttendanceHistoryPage(),
              ),
            );
          },
        ),
        _buildMenuItem(
          icon: Icons.assignment_outlined,
          title: 'Riwayat Perizinan',
          subtitle: 'Lihat histori pengajuan izin',
          onTap: () {
            HapticFeedback.lightImpact();
            Navigator.push(
              context,
              MaterialPageRoute(
                builder: (context) => const PermitHistoryPage(),
              ),
            );
          },
        ),
        _buildMenuItem(
          icon: Icons.edit_outlined,
          title: 'Edit Profil',
          subtitle: 'Ubah informasi pribadi',
          onTap: () {
            HapticFeedback.lightImpact();
            // Ini tetap akan menavigasi ke EditProfilePage
            Navigator.push(
              context,
              MaterialPageRoute(
                builder: (context) => const EditProfilePage(),
              ),
            ).then((value) {
              _loadUserData(); // Memanggil _loadUserData() setelah kembali dari EditProfilePage
            });
          },
        ),
      ],
    );
  }
  Widget _buildNotificationSection() {
    return _buildSection(
      title: 'Notifikasi',
      icon: Icons.notifications_outlined,
      children: [
        _buildSwitchMenuItem(
          icon: Icons.push_pin_outlined,
          title: 'Push Notifications',
          subtitle: 'Terima notifikasi push dari aplikasi',
          value: _pushNotifications,
          onChanged: (value) {
            setState(() {
              _pushNotifications = value;
            });
            _updateNotificationSetting('push_notifications', value);
            HapticFeedback.lightImpact();
          },
        ),
        _buildSwitchMenuItem(
          icon: Icons.notifications_active_outlined,
          title: 'App Notifications',
          subtitle: 'Notifikasi dalam aplikasi',
          value: _appNotifications,
          onChanged: (value) {
            setState(() {
              _appNotifications = value;
            });
            _updateNotificationSetting('app_notifications', value);
            HapticFeedback.lightImpact();
          },
        ),
      ],
    );
  }

  Widget _buildGeneralSection() {
    return _buildSection(
      title: 'Umum',
      icon: Icons.settings_outlined,
      children: [
        _buildMenuItem(
          icon: Icons.language_outlined,
          title: 'Bahasa',
          subtitle: 'Indonesia',
          onTap: () {
            HapticFeedback.lightImpact();
            _showLanguageDialog();
          },
          trailing: const Icon(
            Icons.arrow_forward_ios,
            size: 16,
            color: Colors.grey,
          ),
        ),
        _buildMenuItem(
          icon: Icons.help_outline,
          title: 'Bantuan & Dukungan',
          subtitle: 'FAQ dan kontak support',
          onTap: () {
            HapticFeedback.lightImpact();
            _showComingSoonDialog('Bantuan & Dukungan');
          },
        ),
        _buildMenuItem(
          icon: Icons.info_outline,
          title: 'Tentang Aplikasi',
          subtitle: 'Versi 1.0.0',
          onTap: () {
            HapticFeedback.lightImpact();
            _showAboutDialog();
          },
        ),
        _buildMenuItem(
          icon: Icons.privacy_tip_outlined,
          title: 'Kebijakan Privasi',
          subtitle: 'Pelajari cara kami melindungi data',
          onTap: () {
            HapticFeedback.lightImpact();
            _showComingSoonDialog('Kebijakan Privasi');
          },
        ),
      ],
    );
  }

  Widget _buildSection({
    required String title,
    required IconData icon,
    required List<Widget> children,
  }) {
    return Container(
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(16),
        boxShadow: [
          BoxShadow(
            color: Colors.black.withOpacity(0.05),
            blurRadius: 10,
            offset: const Offset(0, 2),
          ),
        ],
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Padding(
            padding: const EdgeInsets.all(20),
            child: Row(
              children: [
                Container(
                  padding: const EdgeInsets.all(8),
                  decoration: BoxDecoration(
                    color: const Color(0xFF00A0E3).withOpacity(0.1),
                    borderRadius: BorderRadius.circular(8),
                  ),
                  child: Icon(icon, color: const Color(0xFF00A0E3), size: 20),
                ),
                const SizedBox(width: 12),
                Text(
                  title,
                  style: const TextStyle(
                    fontSize: 18,
                    fontWeight: FontWeight.w600,
                    color: Colors.black87,
                  ),
                ),
              ],
            ),
          ),
          const Divider(height: 1, color: Colors.grey, thickness: 0.2),
          ...children,
        ],
      ),
    );
  }

  Widget _buildMenuItem({
    required IconData icon,
    required String title,
    required String subtitle,
    required VoidCallback onTap,
    Widget? trailing,
  }) {
    return Material(
      color: Colors.transparent,
      child: InkWell(
        onTap: onTap,
        borderRadius: const BorderRadius.vertical(bottom: Radius.circular(16)),
        child: Padding(
          padding: const EdgeInsets.symmetric(horizontal: 20, vertical: 16),
          child: Row(
            children: [
              Container(
                padding: const EdgeInsets.all(8),
                decoration: BoxDecoration(
                  color: Colors.grey.shade100,
                  borderRadius: BorderRadius.circular(8),
                ),
                child: Icon(icon, color: Colors.grey.shade600, size: 20),
              ),
              const SizedBox(width: 16),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      title,
                      style: const TextStyle(
                        fontSize: 16,
                        fontWeight: FontWeight.w500,
                        color: Colors.black87,
                      ),
                    ),
                    const SizedBox(height: 2),
                    Text(
                      subtitle,
                      style: TextStyle(
                        fontSize: 14,
                        color: Colors.grey.shade600,
                      ),
                    ),
                  ],
                ),
              ),
              trailing ??
                  const Icon(
                    Icons.arrow_forward_ios,
                    size: 16,
                    color: Colors.grey,
                  ),
            ],
          ),
        ),
      ),
    );
  }

  Widget _buildSwitchMenuItem({
    required IconData icon,
    required String title,
    required String subtitle,
    required bool value,
    required ValueChanged<bool> onChanged,
  }) {
    return Padding(
      padding: const EdgeInsets.symmetric(horizontal: 20, vertical: 12),
      child: Row(
        children: [
          Container(
            padding: const EdgeInsets.all(8),
            decoration: BoxDecoration(
              color: Colors.grey.shade100,
              borderRadius: BorderRadius.circular(8),
            ),
            child: Icon(icon, color: Colors.grey.shade600, size: 20),
          ),
          const SizedBox(width: 16),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  title,
                  style: const TextStyle(
                    fontSize: 16,
                    fontWeight: FontWeight.w500,
                    color: Colors.black87,
                  ),
                ),
                const SizedBox(height: 2),
                Text(
                  subtitle,
                  style: TextStyle(fontSize: 14, color: Colors.grey.shade600),
                ),
              ],
            ),
          ),
          Switch(
            value: value,
            onChanged: onChanged,
            activeColor: const Color(0xFF00A0E3),
            materialTapTargetSize: MaterialTapTargetSize.shrinkWrap,
          ),
        ],
      ),
    );
  }

  Widget _buildLogoutButton() {
    return SizedBox(
      width: double.infinity,
      height: 56,
      child: ElevatedButton.icon(
        onPressed: () => _showLogoutDialog(),
        style: ElevatedButton.styleFrom(
          backgroundColor: const Color.fromARGB(255, 94, 94, 94),
          foregroundColor: Colors.white,
          shape: RoundedRectangleBorder(
            borderRadius: BorderRadius.circular(16),
          ),
          elevation: 2,
          shadowColor: const Color.fromARGB(255, 110, 110, 110),
        ),
        icon: const Icon(Icons.logout, size: 20),
        label: const Text(
          'Keluar dari Akun',
          style: TextStyle(fontSize: 16, fontWeight: FontWeight.w600),
        ),
      ),
    );
  }

  void _showLogoutDialog() {
    showDialog(
      context: context,
      builder: (context) => AlertDialog(
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(16)),
        title: Row(
          children: [
            Icon(Icons.logout, color: Colors.red.shade500),
            const SizedBox(width: 8),
            const Text('Keluar dari Akun'),
          ],
        ),
        content: const Text(
          'Apakah Anda yakin ingin keluar dari akun? Anda perlu login kembali untuk mengakses aplikasi.',
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(context),
            child: const Text('Batal'),
          ),
          ElevatedButton(
            onPressed: () async {
              Navigator.pop(context);
              await _performLogout();
            },
            style: ElevatedButton.styleFrom(
              backgroundColor: Colors.red.shade500,
              foregroundColor: Colors.white,
              shape: RoundedRectangleBorder(
                borderRadius: BorderRadius.circular(8),
              ),
            ),
            child: const Text('Keluar'),
          ),
        ],
      ),
    );
  }

  Future<void> _performLogout() async {
  try {
    // Tampilkan dialog loading
    showDialog(
      context: context,
      barrierDismissible: false,
      builder: (context) => const Center(
        child: Card(
          child: Padding(
            padding: EdgeInsets.all(20),
            child: Column(
              mainAxisSize: MainAxisSize.min,
              children: [
                CircularProgressIndicator(),
                SizedBox(height: 16),
                Text('Keluar dari akun...'),
              ],
            ),
          ),
        ),
      ),
    );

    // Logout dari Google Sign-In
    final googleSignIn = GoogleSignIn();
    await googleSignIn.signOut();

    // Logout dari Firebase Authentication
    await FirebaseAuth.instance.signOut();

    // Hapus data lokal dari SharedPreferences
    final prefs = await SharedPreferences.getInstance();
    await prefs.clear();

    // Tutup dialog loading
    if (mounted) {
      Navigator.pop(context); // Tutup dialog sebelum navigasi
      Navigator.pushAndRemoveUntil(
        context,
        MaterialPageRoute(builder: (context) => const LoginPage()),
        (route) => false,
      );
    }
    debugPrint('Logout berhasil. Semua sesi dan data lokal dihapus.');
  } catch (e) {
    // Tutup dialog loading jika ada error
    if (mounted) {
      Navigator.pop(context);
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text('Gagal logout: ${e.toString()}'),
          backgroundColor: Colors.red,
          behavior: SnackBarBehavior.floating,
        ),
      );
    }
    debugPrint('Error saat logout: $e');
  }
}

  void _showLanguageDialog() {
    showDialog(
      context: context,
      builder: (context) => AlertDialog(
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(16)),
        title: const Text('Pilih Bahasa'),
        content: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            ListTile(
              leading: const Text('🇮🇩'),
              title: const Text('Bahasa Indonesia'),
              trailing: const Icon(Icons.check, color: Colors.green),
              onTap: () => Navigator.pop(context),
            ),
            ListTile(
              leading: const Text('🇺🇸'),
              title: const Text('English'),
              subtitle: const Text('Coming Soon'),
              enabled: false,
              onTap: () {},
            ),
          ],
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(context),
            child: const Text('Tutup'),
          ),
        ],
      ),
    );
  }

  void _showAboutDialog() {
    showAboutDialog(
      context: context,
      applicationName: 'Presence App',
      applicationVersion: '1.0.0',
      applicationIcon: Container(
        width: 60,
        height: 60,
        decoration: BoxDecoration(
          gradient: const LinearGradient(
            colors: [Color(0xFF00A0E3), Color(0xFF0288D1)],
          ),
          borderRadius: BorderRadius.circular(12),
        ),
        child: const Icon(Icons.business, color: Colors.white, size: 30),
      ),
      children: [
        const Text(
          'Aplikasi untuk kehadiran dan perizinan karyawan.',
        ),
        const SizedBox(height: 16),
        const Text('Dikembangkan Tim Magang Politeknik Negeri Madiun.'),
      ],
    );
  }

  void _showComingSoonDialog(String feature) {
    showDialog(
      context: context,
      builder: (context) => AlertDialog(
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(16)),
        title: Row(
          children: [
            Icon(Icons.construction, color: Colors.orange.shade600),
            const SizedBox(width: 8),
            const Text('Segera Hadir'),
          ],
        ),
        content: Text(
          'Fitur $feature sedang dalam tahap pengembangan dan akan tersedia dalam update mendatang.',
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(context),
            child: const Text('OK'),
          ),
        ],
      ),
    );
  }
}