import 'package:flutter/material.dart';
import 'package:google_fonts/google_fonts.dart';
import 'package:shared_preferences/shared_preferences.dart';
import 'package:Presence/pages/edit_profile_page.dart';
import 'package:intl/intl.dart';

class ProfileDetailPage extends StatefulWidget {
  const ProfileDetailPage({super.key});

  @override
  State<ProfileDetailPage> createState() => _ProfileDetailPageState();
}

class _ProfileDetailPageState extends State<ProfileDetailPage>
    with SingleTickerProviderStateMixin {
  String _name = 'User';
  String _profilePictureUrl = '';
  String _email = '';
  String _firestoreUsername = '';
  String _position = '';
  String _status = '';
  String _provider = '';
  String _createdAt = '';
  String _address = '';
  String _dateOfBirth = '';

  bool _isLoading = true;
  late AnimationController _animationController;
  late Animation<double> _fadeAnimation;
  late Animation<Offset> _slideAnimation;

  @override
  void initState() {
    super.initState();
    _initializeAnimations();
    _loadProfileData();
  }

  void _initializeAnimations() {
    _animationController = AnimationController(
      duration: const Duration(milliseconds: 1000),
      vsync: this,
    );

    _fadeAnimation = Tween<double>(begin: 0.0, end: 1.0).animate(
      CurvedAnimation(
        parent: _animationController,
        curve: const Interval(0.0, 0.6, curve: Curves.easeOut),
      ),
    );

    _slideAnimation =
        Tween<Offset>(begin: const Offset(0, 0.3), end: Offset.zero).animate(
          CurvedAnimation(
            parent: _animationController,
            curve: const Interval(0.3, 1.0, curve: Curves.easeOutCubic),
          ),
        );
  }

  @override
  void dispose() {
    _animationController.dispose();
    super.dispose();
  }

  Future<void> _loadProfileData() async {
    setState(() => _isLoading = true);

    final prefs = await SharedPreferences.getInstance();
    if (!mounted) return;

    setState(() {
      _name = prefs.getString('name') ?? 'User';
      _profilePictureUrl = prefs.getString('profilePictureUrl') ?? '';
      _email = prefs.getString('email') ?? '';
      _firestoreUsername = prefs.getString('username') ?? '';
      _position = prefs.getString('position') ?? '';
      _status = prefs.getString('status') ?? '';
      _provider = prefs.getString('provider') ?? '';
      _address = prefs.getString('address') ?? '';
      _dateOfBirth = prefs.getString('dateOfBirth') ?? '';

      String? createdAtString = prefs.getString('createdAt');
      if (createdAtString != null && createdAtString.isNotEmpty) {
        try {
          DateTime createdAtDateTime = DateTime.parse(createdAtString);
          _createdAt = DateFormat(
            'dd MMMM yyyy',
            'id_ID',
          ).format(createdAtDateTime);
        } catch (e) {
          _createdAt = 'Tanggal tidak valid';
          debugPrint('Error parsing createdAt: $e');
        }
      } else {
        _createdAt = 'Tidak tersedia';
      }

      _isLoading = false;
    });

    _animationController.forward();
  }

  String _formatDateOfBirth(String dateString) {
    if (dateString.isEmpty) return 'Tidak tersedia';
    try {
      DateTime date = DateTime.parse(dateString);
      return DateFormat('dd MMMM yyyy', 'id_ID').format(date);
    } catch (e) {
      return dateString.isNotEmpty ? dateString : 'Tidak tersedia';
    }
  }

  String _getProviderDisplayName(String provider) {
    switch (provider.toLowerCase()) {
      case 'google.com':
      case 'google':
        return 'Google';
      case 'password':
        return 'Email & Password';
      case 'facebook.com':
      case 'facebook':
        return 'Facebook';
      case 'apple.com':
      case 'apple':
        return 'Apple';
      default:
        return provider.isNotEmpty ? provider : 'Tidak tersedia';
    }
  }

  String _getStatusColor(String status) {
    switch (status.toLowerCase()) {
      case 'active':
      case 'aktif':
        return 'success';
      case 'inactive':
      case 'tidak aktif':
        return 'warning';
      case 'suspended':
      case 'ditangguhkan':
        return 'error';
      default:
        return 'info';
    }
  }

  Color _getStatusBadgeColor(String colorType) {
    switch (colorType) {
      case 'success':
        return Colors.green;
      case 'warning':
        return Colors.orange;
      case 'error':
        return Colors.red;
      default:
        return Color(0xFF00BCD4);
    }
  }

  /// Helper untuk mendapatkan inisial dari nama
  String _getInitials(String name) {
    if (name.isEmpty) return 'U';
    final nameParts = name.trim().split(' ');
    if (nameParts.length > 1) {
      return (nameParts[0][0] + nameParts[1][0]).toUpperCase();
    }
    return nameParts[0][0].toUpperCase();
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: Colors.grey.shade50,
      body: _isLoading
          ? const Center(child: CircularProgressIndicator())
          : CustomScrollView(
              physics: const BouncingScrollPhysics(),
              slivers: [
                // Custom App Bar with Profile Header
                SliverAppBar(
                  expandedHeight: 280,
                  floating: false,
                  pinned: true,
                  elevation: 0,
                  backgroundColor: Color(0xFF00BCD4),
                  iconTheme: const IconThemeData(color: Colors.white),
                  flexibleSpace: FlexibleSpaceBar(
                    background: FadeTransition(
                      opacity: _fadeAnimation,
                      child: Container(
                        decoration: BoxDecoration(
                          gradient: LinearGradient(
                            begin: Alignment.topCenter,
                            end: Alignment.bottomCenter,
                            colors: [Color(0xFF00BCD4), Color(0xFF00ACC1)]
                          ),
                        ),
                        child: SafeArea(
                          child: Column(
                            mainAxisAlignment: MainAxisAlignment.end,
                            children: [
                              const SizedBox(height: 55),
                              // Profile Picture
                              Hero(
                                tag: 'profile_picture_detail',
                                child: Container(
                                  decoration: BoxDecoration(
                                    shape: BoxShape.circle,
                                    border: Border.all(
                                      color: Colors.white,
                                      width: 4,
                                    ),
                                    boxShadow: [
                                      BoxShadow(
                                        color: Colors.black.withOpacity(0.3),
                                        blurRadius: 20,
                                        offset: const Offset(0, 10),
                                      ),
                                    ],
                                  ),
                                  child: CircleAvatar(
                                    radius: 60,
                                    backgroundColor: Colors.grey.shade200,
                                    backgroundImage:
                                        _profilePictureUrl.isNotEmpty
                                        ? NetworkImage(_profilePictureUrl)
                                        : null,
                                    child: _profilePictureUrl.isEmpty
                                        ? Text(
                                            _getInitials(_firestoreUsername),
                                            style: GoogleFonts.poppins(
                                              fontSize: 36,
                                              fontWeight: FontWeight.bold,
                                              color: Colors.grey.shade600,
                                            ),
                                          )
                                        : null,
                                  ),
                                ),
                              ),
                              const SizedBox(height: 16),
                              // Username
                              Text(
                                _firestoreUsername,
                                style: GoogleFonts.poppins(
                                  fontSize: 24,
                                  fontWeight: FontWeight.bold,
                                  color: Colors.white,
                                ),
                                textAlign: TextAlign.center,
                              ),
                              const SizedBox(height: 4),
                              // Position
                              Text(
                                _position.isNotEmpty
                                    ? _position
                                    : 'Posisi tidak tersedia',
                                style: GoogleFonts.poppins(
                                  fontSize: 16,
                                  color: Colors.white.withOpacity(0.9),
                                ),
                                textAlign: TextAlign.center,
                              ),
                              const SizedBox(height: 20),
                            ],
                          ),
                        ),
                      ),
                    ),
                  ),
                  actions: [
                    IconButton(
                      icon: const Icon(Icons.edit_rounded),
                      onPressed: () {
                        Navigator.push(
                          context,
                          MaterialPageRoute(
                            builder: (context) => const EditProfilePage(),
                          ),
                        ).then((_) => _loadProfileData());
                      },
                      tooltip: 'Edit Profil',
                    ),
                  ],
                ),

                // Content
                SliverToBoxAdapter(
                  child: SlideTransition(
                    position: _slideAnimation,
                    child: FadeTransition(
                      opacity: _fadeAnimation,
                      child: Padding(
                        padding: const EdgeInsets.all(20),
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            // Quick Actions Card
                            _buildQuickActionsCard(),
                            const SizedBox(height: 24),

                            // Personal Information Section
                            _buildSectionTitle('Informasi Personal'),
                            const SizedBox(height: 12),
                            _buildInfoCard(
                              icon: Icons.alternate_email_rounded,
                              title: 'Name',
                              value: _name.isNotEmpty
                                  ? _name
                                  : 'Tidak tersedia',
                            ),
                            const SizedBox(height: 12),
                            _buildInfoCard(
                              icon: Icons.email_rounded,
                              title: 'Email',
                              value: _email.isNotEmpty
                                  ? _email
                                  : 'Tidak tersedia',
                              isClickable: _email.isNotEmpty,
                            ),
                            const SizedBox(height: 12),
                            _buildInfoCard(
                              icon: Icons.cake_rounded,
                              title: 'Tanggal Lahir',
                              value: _formatDateOfBirth(_dateOfBirth),
                            ),
                            const SizedBox(height: 12),
                            _buildInfoCard(
                              icon: Icons.location_on_rounded,
                              title: 'Alamat',
                              value: _address.isNotEmpty
                                  ? _address
                                  : 'Tidak tersedia',
                              maxLines: 2,
                            ),

                            const SizedBox(height: 24),

                            // Work Information Section
                            _buildSectionTitle('Informasi Pekerjaan'),
                            const SizedBox(height: 12),
                            _buildInfoCard(
                              icon: Icons.work_rounded,
                              title: 'Posisi',
                              value: _position.isNotEmpty
                                  ? _position
                                  : 'Tidak tersedia',
                            ),
                            const SizedBox(height: 12),
                            _buildStatusCard(),

                            const SizedBox(height: 24),

                            // Account Information Section
                            _buildSectionTitle('Informasi Akun'),
                            const SizedBox(height: 12),
                            _buildInfoCard(
                              icon: Icons.login_rounded,
                              title: 'Metode Login',
                              value: _getProviderDisplayName(_provider),
                            ),
                            const SizedBox(height: 12),
                            _buildInfoCard(
                              icon: Icons.calendar_today_rounded,
                              title: 'Bergabung Sejak',
                              value: _createdAt,
                            ),

                            const SizedBox(height: 40),
                          ],
                        ),
                      ),
                    ),
                  ),
                ),
              ],
            ),
    );
  }

  Widget _buildQuickActionsCard() {
    return Container(
      width: double.infinity,
      padding: const EdgeInsets.all(20),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(16),
        boxShadow: [
          BoxShadow(
            color: Colors.black.withOpacity(0.05),
            blurRadius: 10,
            offset: const Offset(0, 5),
          ),
        ],
      ),
      child: Column(
        children: [
          Text(
            'Tindakan Cepat',
            style: GoogleFonts.poppins(
              fontSize: 16,
              fontWeight: FontWeight.w600,
              color: Colors.grey.shade800,
            ),
          ),
          const SizedBox(height: 16),
          Row(
            children: [
              Expanded(
                child: _buildQuickAction(
                  icon: Icons.edit_rounded,
                  label: 'Edit Profil',
                  color: const Color(0xFF00BCD4),
                  onTap: () {
                    Navigator.push(
                      context,
                      MaterialPageRoute(
                        builder: (context) => const EditProfilePage(),
                      ),
                    ).then((_) => _loadProfileData());
                  },
                ),
              ),
            ],
          ),
        ],
      ),
    );
  }

  Widget _buildQuickAction({
    required IconData icon,
    required String label,
    required Color color,
    required VoidCallback onTap,
  }) {
    return InkWell(
      onTap: onTap,
      borderRadius: BorderRadius.circular(12),
      child: Container(
        padding: const EdgeInsets.symmetric(vertical: 16),
        decoration: BoxDecoration(
          color: color.withOpacity(0.1),
          borderRadius: BorderRadius.circular(12),
          border: Border.all(color: color.withOpacity(0.2), width: 1),
        ),
        child: Column(
          children: [
            Icon(icon, color: color, size: 28),
            const SizedBox(height: 8),
            Text(
              label,
              style: GoogleFonts.poppins(
                fontSize: 12,
                fontWeight: FontWeight.w600,
                color: color,
              ),
              textAlign: TextAlign.center,
            ),
          ],
        ),
      ),
    );
  }

  Widget _buildSectionTitle(String title) {
    return Text(
      title,
      style: GoogleFonts.poppins(
        fontSize: 18,
        fontWeight: FontWeight.w600,
        color: Colors.grey.shade800,
      ),
    );
  }

  Widget _buildInfoCard({
    required IconData icon,
    required String title,
    required String value,
    bool isClickable = false,
    int maxLines = 1,
  }) {
    return Container(
      width: double.infinity,
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(12),
        border: Border.all(color: Colors.grey.shade200, width: 1),
        boxShadow: [
          BoxShadow(
            color: Colors.black.withOpacity(0.03),
            blurRadius: 8,
            offset: const Offset(0, 2),
          ),
        ],
      ),
      child: Row(
        children: [
          Container(
            padding: const EdgeInsets.all(10),
            decoration: BoxDecoration(
              color: Colors.blue.shade50,
              borderRadius: BorderRadius.circular(10),
            ),
            child: Icon(icon, color: const Color(0xFF00BCD4), size: 20),
          ),
          const SizedBox(width: 16),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  title,
                  style: GoogleFonts.poppins(
                    fontSize: 13,
                    color: Colors.grey.shade600,
                    fontWeight: FontWeight.w500,
                  ),
                ),
                const SizedBox(height: 4),
                Text(
                  value,
                  style: GoogleFonts.poppins(
                    fontSize: 15,
                    fontWeight: FontWeight.w600,
                    color: Colors.black87,
                  ),
                  maxLines: maxLines,
                  overflow: TextOverflow.ellipsis,
                ),
              ],
            ),
          ),
          if (isClickable)
            Icon(Icons.launch_rounded, color: Colors.grey.shade400, size: 16),
        ],
      ),
    );
  }

  Widget _buildStatusCard() {
    final statusColor = _getStatusColor(_status);
    final badgeColor = _getStatusBadgeColor(statusColor);

    return Container(
      width: double.infinity,
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(12),
        border: Border.all(color: Colors.grey.shade200, width: 1),
        boxShadow: [
          BoxShadow(
            color: Colors.black.withOpacity(0.03),
            blurRadius: 8,
            offset: const Offset(0, 2),
          ),
        ],
      ),
      child: Row(
        children: [
          Container(
            padding: const EdgeInsets.all(10),
            decoration: BoxDecoration(
              color: badgeColor.withOpacity(0.1),
              borderRadius: BorderRadius.circular(10),
            ),
            child: Icon(
              Icons.check_circle_rounded,
              color: badgeColor,
              size: 20,
            ),
          ),
          const SizedBox(width: 16),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  'Status',
                  style: GoogleFonts.poppins(
                    fontSize: 13,
                    color: Colors.grey.shade600,
                    fontWeight: FontWeight.w500,
                  ),
                ),
                const SizedBox(height: 4),
                Row(
                  children: [
                    Container(
                      padding: const EdgeInsets.symmetric(
                        horizontal: 8,
                        vertical: 4,
                      ),
                      decoration: BoxDecoration(
                        color: badgeColor.withOpacity(0.1),
                        borderRadius: BorderRadius.circular(6),
                        border: Border.all(
                          color: badgeColor.withOpacity(0.3),
                          width: 1,
                        ),
                      ),
                      child: Text(
                        _status.isNotEmpty ? _status : 'Tidak tersedia',
                        style: GoogleFonts.poppins(
                          fontSize: 12,
                          fontWeight: FontWeight.w600,
                          color: badgeColor,
                        ),
                      ),
                    ),
                  ],
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }
}
