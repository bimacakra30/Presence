import 'package:cloud_firestore/cloud_firestore.dart';
import 'package:firebase_auth/firebase_auth.dart';
import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:intl/intl.dart';

class PermitHistoryPage extends StatefulWidget {
  const PermitHistoryPage({super.key});

  @override
  State<PermitHistoryPage> createState() => _PermitHistoryPageState();
}

class _PermitHistoryPageState extends State<PermitHistoryPage>
    with TickerProviderStateMixin {
  late AnimationController _animationController;
  late Animation<double> _fadeAnimation;

  String _selectedFilter = 'all'; // PERBAIKAN: Inisialisasi dengan 'all' huruf kecil
  final List<String> _filterOptions = [
    'all',
    'pending',
    'approved',
    'rejected',
  ];

  @override
  void initState() {
    super.initState();
    _animationController = AnimationController(
      duration: const Duration(milliseconds: 800),
      vsync: this,
    );
    _fadeAnimation = Tween<double>(begin: 0.0, end: 1.0).animate(
      CurvedAnimation(parent: _animationController, curve: Curves.easeOut),
    );

    _animationController.forward();
  }

  @override
  void dispose() {
    _animationController.dispose();
    super.dispose();
  }

  Stream<QuerySnapshot> getPermitHistoryStream() {
    final user = FirebaseAuth.instance.currentUser;
    if (user == null) {
      debugPrint('User is null, returning empty stream for permit history.');
      return const Stream.empty();
    }

    Query collectionQuery = FirebaseFirestore.instance
        .collection('permits')
        .where('uid', isEqualTo: user.uid);

    // Filter berdasarkan status hanya jika _selectedFilter BUKAN 'all'
    // Perbandingan status akan dilakukan di UI (StreamBuilder) untuk fleksibilitas
    // (karena Firestore .where() itu case-sensitive dan kita punya opsi 'all')

    // Always order by submissionDate for consistency
    collectionQuery = collectionQuery.orderBy('submissionDate', descending: true);

    debugPrint('Fetching permit history for UID: ${user.uid} with filter: $_selectedFilter');
    return collectionQuery.snapshots();
  }

  Future<String> _fetchEmployeeName(String uid) async {
    if (uid.isEmpty) {
      debugPrint('UID is empty, cannot fetch employee name.');
      return 'Pengguna Tidak Ditemukan';
    }
    try {
      final doc = await FirebaseFirestore.instance.collection('employees').doc(uid).get();
      if (doc.exists && doc.data() != null) {
        return doc.data()!['name'] ?? 'Nama Tidak Ditemukan';
      }
    } catch (e) {
      debugPrint('Error fetching employee name for UID $uid: $e');
    }
    return 'Nama Tidak Ditemukan';
  }


  Map<String, dynamic> _getStatusInfo(String status) {
    debugPrint('Processing status: $status (Lowercase: ${status.toLowerCase()})');

    switch (status.toLowerCase()) {
      case 'approved':
        return {
          'color': Colors.green.shade500,
          'backgroundColor': Colors.green.shade50,
          'icon': Icons.check_circle_outline,
          'label': 'Disetujui',
        };
      case 'rejected':
        return {
          'color': Colors.red.shade500,
          'backgroundColor': Colors.red.shade50,
          'icon': Icons.cancel_outlined,
          'label': 'Ditolak',
        };
      case 'pending': // Explicitly handle 'pending' case
        return {
          'color': Colors.orange.shade500,
          'backgroundColor': Colors.orange.shade50,
          'icon': Icons.access_time,
          'label': 'Menunggu',
        };
      default: // Fallback for any other string
        debugPrint('Unknown status encountered: "$status". Defaulting to "Menunggu".');
        return {
          'color': Colors.orange.shade500, // Default to orange for unknown
          'backgroundColor': Colors.orange.shade50,
          'icon': Icons.access_time,
          'label': 'Menunggu', // Default to 'Menunggu' for safety
        };
    }
  }

  Map<String, dynamic> _getPermitTypeInfo(String permitType) {
    switch (permitType) {
      case 'Sick Leave':
        return {
          'icon': Icons.local_hospital,
          'color': Colors.red.shade400,
          'label': 'Sakit',
        };
      case 'Annual Leave':
        return {
          'icon': Icons.beach_access,
          'color': const Color(0xFF00BCD4),
          'label': 'Cuti Tahunan',
        };
      case 'Personal Leave':
        return {
          'icon': Icons.person,
          'color': Colors.orange.shade400,
          'label': 'Izin Pribadi',
        };
      default:
        return {
          'icon': Icons.assignment,
          'color': Colors.grey.shade400,
          'label': permitType,
        };
    }
  }

  int _calculateDuration(String startDate, String endDate) {
    try {
      if (startDate == 'N/A' || endDate == 'N/A') return 0;
      final start = DateTime.parse(startDate);
      final end = DateTime.parse(endDate);
      return end.difference(start).inDays + 1;
    } catch (e) {
      debugPrint('Error calculating duration: $e');
      return 0;
    }
  }

  Widget _buildFilterChips() {
    return Container(
      height: 50,
      margin: const EdgeInsets.symmetric(horizontal: 20, vertical: 10),
      child: ListView.builder(
        scrollDirection: Axis.horizontal,
        itemCount: _filterOptions.length,
        itemBuilder: (context, index) {
          final filter = _filterOptions[index];
          final isSelected = _selectedFilter == filter;

          return AnimatedContainer(
            duration: const Duration(milliseconds: 200),
            margin: const EdgeInsets.only(right: 10),
            child: FilterChip(
              label: Text(
                // PERBAIKAN: Pastikan label filter juga menggunakan case yang konsisten
                filter == 'all' ? 'Semua' : _getStatusInfo(filter)['label'],
                style: TextStyle(
                  color: isSelected ? Colors.white : Colors.grey.shade700,
                  fontWeight: isSelected ? FontWeight.w600 : FontWeight.w500,
                ),
              ),
              selected: isSelected,
              onSelected: (selected) {
                HapticFeedback.lightImpact();
                setState(() {
                  _selectedFilter = selected ? filter : 'all'; // PERBAIKAN: Gunakan 'all' huruf kecil
                });
              },
              backgroundColor: Colors.grey.shade100,
              selectedColor: const Color(0xFF00BCD4),
              checkmarkColor: Colors.white,
              shape: RoundedRectangleBorder(
                borderRadius: BorderRadius.circular(20),
              ),
              elevation: isSelected ? 2 : 0,
            ),
          );
        },
      ),
    );
  }

  Widget _buildEmptyState() {
    return FadeTransition(
      opacity: _fadeAnimation,
      child: Center(
        child: Column(
          mainAxisAlignment: MainAxisAlignment.center,
          children: [
            Container(
              width: 120,
              height: 120,
              decoration: BoxDecoration(
                color: Colors.grey.shade100,
                shape: BoxShape.circle,
              ),
              child: Icon(
                Icons.assignment_outlined,
                size: 60,
                color: Colors.grey.shade400,
              ),
            ),
            const SizedBox(height: 24),
            Text(
              _selectedFilter == 'all' // PERBAIKAN: Gunakan 'all' huruf kecil
                  ? 'Belum Ada Riwayat Izin'
                  : 'Tidak Ada Izin ${_getStatusInfo(_selectedFilter)['label']}',
              style: TextStyle(
                fontSize: 20,
                fontWeight: FontWeight.w600,
                color: Colors.grey.shade600,
              ),
            ),
            const SizedBox(height: 8),
            Text(
              _selectedFilter == 'all' // PERBAIKAN: Gunakan 'all' huruf kecil
                  ? 'Riwayat perizinan Anda akan muncul di sini'
                  : 'Coba ganti filter untuk melihat izin lainnya',
              style: TextStyle(fontSize: 16, color: Colors.grey.shade500),
              textAlign: TextAlign.center,
            ),
          ],
        ),
      ),
    );
  }

  Widget _buildErrorState(String error) {
    return Center(
      child: Column(
        mainAxisAlignment: MainAxisAlignment.center,
        children: [
          Container(
            width: 120,
            height: 120,
            decoration: BoxDecoration(
              color: Colors.red.shade50,
              shape: BoxShape.circle,
            ),
            child: Icon(
              Icons.error_outline,
              size: 60,
              color: Colors.red.shade400,
            ),
          ),
          const SizedBox(height: 24),
          Text(
            'Terjadi Kesalahan',
            style: TextStyle(
              fontSize: 20,
              fontWeight: FontWeight.w600,
              color: Colors.red.shade600,
            ),
          ),
          const SizedBox(height: 8),
          Padding(
            padding: const EdgeInsets.symmetric(horizontal: 40),
            child: Text(
              error,
              style: TextStyle(fontSize: 16, color: Colors.grey.shade600),
              textAlign: TextAlign.center,
            ),
          ),
          const SizedBox(height: 24),
          ElevatedButton.icon(
            onPressed: () {
              setState(() {}); // Trigger rebuild
            },
            icon: const Icon(Icons.refresh),
            label: const Text('Coba Lagi'),
            style: ElevatedButton.styleFrom(
              backgroundColor: const Color(0xFF00BCD4),
              foregroundColor: Colors.white,
              shape: RoundedRectangleBorder(
                borderRadius: BorderRadius.circular(12),
              ),
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildPermitCard(Map<String, dynamic> permit, int index) {
    final status = permit['status'] ?? 'Pending';
    final permitType = permit['permitType'] ?? 'Izin';
    final startDate = permit['startDate'] ?? 'N/A';
    final endDate = permit['endDate'] ?? 'N/A';
    final description = permit['description'] ?? '';
    final submissionDate = permit['submissionDate'] ?? '';
    final uid = permit['uid'] ?? '';

    debugPrint('Permit Card #$index - Raw Status from Firestore: "$status"'); // Debug print untuk raw status
    debugPrint('Permit Card #$index - Filter: "$_selectedFilter"'); // Debug print untuk filter yang aktif

    final statusInfo = _getStatusInfo(status);
    final typeInfo = _getPermitTypeInfo(permitType);
    final duration = _calculateDuration(startDate, endDate);

    String formattedStartDate = 'N/A';
    String formattedEndDate = 'N/A';
    String formattedSubmissionDate = '';

    try {
      if (startDate != 'N/A') {
        final date = DateTime.parse(startDate);
        formattedStartDate = DateFormat('dd MMM yyyy').format(date);
      }
      if (endDate != 'N/A') {
        final date = DateTime.parse(endDate);
        formattedEndDate = DateFormat('dd MMM yyyy').format(date);
      }
      if (submissionDate.isNotEmpty) {
        final date = DateTime.parse(submissionDate);
        formattedSubmissionDate = DateFormat('dd MMM yyyy, HH:mm').format(date);
      }
    } catch (e) {
      debugPrint('Failed to parse date in _buildPermitCard: $e');
    }

    return AnimatedBuilder(
      animation: _animationController,
      builder: (context, child) {
        final slideOffset =
            Tween<Offset>(
              begin: const Offset(0, 0.1),
              end: Offset.zero,
            ).animate(
              CurvedAnimation(
                parent: _animationController,
                curve: Interval(
                  (index * 0.1).clamp(0.0, 1.0),
                  ((index * 0.1) + 0.3).clamp(0.0, 1.0),
                  curve: Curves.easeOut,
                ),
              ),
            );

        return SlideTransition(
          position: slideOffset,
          child: FadeTransition(
            opacity: _fadeAnimation,
            child: Container(
              margin: const EdgeInsets.symmetric(horizontal: 20, vertical: 6),
              child: Material(
                color: Colors.white,
                borderRadius: BorderRadius.circular(16),
                elevation: 2,
                shadowColor: Colors.black.withOpacity(0.1),
                child: InkWell(
                  borderRadius: BorderRadius.circular(16),
                  onTap: () {
                    HapticFeedback.lightImpact();
                    _showPermitDetails(
                      permit,
                      statusInfo,
                      typeInfo,
                      formattedStartDate,
                      formattedEndDate,
                      formattedSubmissionDate,
                      duration,
                    );
                  },
                  child: Padding(
                    padding: const EdgeInsets.all(20),
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Row(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            Container(
                              padding: const EdgeInsets.all(10),
                              decoration: BoxDecoration(
                                color: typeInfo['color'].withOpacity(0.1),
                                borderRadius: BorderRadius.circular(10),
                              ),
                              child: Icon(
                                typeInfo['icon'],
                                color: typeInfo['color'],
                                size: 20,
                              ),
                            ),
                            const SizedBox(width: 12),
                            Expanded(
                              child: Column(
                                crossAxisAlignment: CrossAxisAlignment.start,
                                children: [
                                  FutureBuilder<String>(
                                    future: _fetchEmployeeName(uid),
                                    builder: (context, snapshot) {
                                      if (snapshot.connectionState == ConnectionState.waiting) {
                                        return const Text(
                                          'Memuat Nama...',
                                          style: TextStyle(
                                            fontSize: 16,
                                            fontWeight: FontWeight.w600,
                                            color: Colors.black54,
                                          ),
                                        );
                                      }
                                      if (snapshot.hasError) {
                                        debugPrint('Error fetching employee name in card: ${snapshot.error}');
                                        return const Text(
                                          'Nama tidak tersedia',
                                          style: TextStyle(
                                            fontSize: 16,
                                            fontWeight: FontWeight.w600,
                                            color: Colors.red,
                                          ),
                                        );
                                      }
                                      return Row(
                                        mainAxisAlignment:
                                            MainAxisAlignment.spaceBetween,
                                        children: [
                                          Expanded(
                                            child: Text(
                                              snapshot.data ?? 'Nama Tidak Ditemukan',
                                              style: const TextStyle(
                                                fontSize: 16,
                                                fontWeight: FontWeight.w600,
                                                color: Colors.black87,
                                              ),
                                              overflow: TextOverflow.ellipsis,
                                            ),
                                          ),
                                          const SizedBox(width: 8),
                                          Container(
                                            padding: const EdgeInsets.symmetric(
                                              horizontal: 12,
                                              vertical: 6,
                                            ),
                                            decoration: BoxDecoration(
                                              color: statusInfo['backgroundColor'],
                                              borderRadius: BorderRadius.circular(
                                                20,
                                              ),
                                              border: Border.all(
                                                color: statusInfo['color']
                                                    .withOpacity(0.3),
                                              ),
                                            ),
                                            child: Row(
                                              mainAxisSize: MainAxisSize.min,
                                              children: [
                                                Icon(
                                                  statusInfo['icon'],
                                                  color: statusInfo['color'],
                                                  size: 14,
                                                ),
                                                const SizedBox(width: 4),
                                                Text(
                                                  statusInfo['label'],
                                                  style: TextStyle(
                                                    color: statusInfo['color'],
                                                    fontSize: 12,
                                                    fontWeight: FontWeight.w600,
                                                  ),
                                                ),
                                              ],
                                            ),
                                          ),
                                        ],
                                      );
                                    },
                                  ),
                                  if (formattedSubmissionDate.isNotEmpty)
                                    Padding(
                                      padding: const EdgeInsets.only(top: 4.0),
                                      child: Text(
                                        'Diajukan $formattedSubmissionDate',
                                        style: TextStyle(
                                          fontSize: 12,
                                          color: Colors.grey.shade600,
                                        ),
                                      ),
                                    ),
                                ],
                              ),
                            ),
                          ],
                        ),
                        const SizedBox(height: 16),

                        Container(
                          padding: const EdgeInsets.all(12),
                          decoration: BoxDecoration(
                            color: Colors.grey.shade50,
                            borderRadius: BorderRadius.circular(10),
                          ),
                          child: Row(
                            children: [
                              Icon(
                                Icons.date_range,
                                color: Colors.grey.shade600,
                                size: 16,
                              ),
                              const SizedBox(width: 8),
                              Expanded(
                                child: Text(
                                  '$formattedStartDate - $formattedEndDate',
                                  style: TextStyle(
                                    fontSize: 14,
                                    color: Colors.grey.shade700,
                                    fontWeight: FontWeight.w500,
                                  ),
                                ),
                              ),
                              if (duration > 0) ...[
                                Container(
                                  padding: const EdgeInsets.symmetric(
                                    horizontal: 8,
                                    vertical: 4,
                                  ),
                                  decoration: BoxDecoration(
                                    color: Colors.blue.shade100,
                                    borderRadius: BorderRadius.circular(12),
                                  ),
                                  child: Text(
                                    '$duration hari',
                                    style: TextStyle(
                                      fontSize: 12,
                                      color: const Color(0xFF00BCD4),
                                      fontWeight: FontWeight.w600,
                                    ),
                                  ),
                                ),
                              ],
                            ],
                          ),
                        ),

                        if (description.isNotEmpty) ...[
                          const SizedBox(height: 12),
                          Text(
                            description,
                            style: TextStyle(
                              fontSize: 14,
                              color: Colors.grey.shade700,
                              height: 1.4,
                            ),
                            maxLines: 2,
                            overflow: TextOverflow.ellipsis,
                          ),
                        ],

                        const SizedBox(height: 12),
                        Row(
                          mainAxisAlignment: MainAxisAlignment.spaceBetween,
                          children: [
                            Text(
                              'Tap untuk detail lengkap',
                              style: TextStyle(
                                fontSize: 12,
                                color: Colors.grey.shade500,
                                fontStyle: FontStyle.italic,
                              ),
                            ),
                            Icon(
                              Icons.arrow_forward_ios,
                              size: 12,
                              color: Colors.grey.shade400,
                            ),
                          ],
                        ),
                      ],
                    ),
                  ),
                ),
              ),
            ),
          ),
        );
      },
    );
  }

  void _showImageDialog(BuildContext context, String imageUrl) {
    showDialog(
      context: context,
      builder: (BuildContext context) {
        return Dialog(
          shape: RoundedRectangleBorder(
            borderRadius: BorderRadius.circular(16),
          ),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              ClipRRect(
                borderRadius: const BorderRadius.vertical(
                  top: Radius.circular(16),
                ),
                child: Image.network(
                  imageUrl,
                  fit: BoxFit.cover,
                  width: double.infinity,
                  loadingBuilder: (context, child, loadingProgress) {
                    if (loadingProgress == null) return child;
                    return Container(
                      height: 250,
                      color: Colors.grey[200],
                      child: const Center(child: CircularProgressIndicator()),
                    );
                  },
                  errorBuilder: (context, error, stackTrace) {
                    return Container(
                      height: 250,
                      color: Colors.grey[200],
                      child: const Center(
                        child: Icon(Icons.error, color: Colors.red, size: 50),
                      ),
                    );
                  },
                ),
              ),
              Padding(
                padding: const EdgeInsets.all(16.0),
                child: ElevatedButton(
                  onPressed: () => Navigator.of(context).pop(),
                  style: ElevatedButton.styleFrom(
                    backgroundColor: const Color(0xFF00A0E3),
                    foregroundColor: Colors.white,
                    minimumSize: const Size(double.infinity, 50),
                  ),
                  child: const Text('Tutup'),
                ),
              ),
            ],
          ),
        );
      },
    );
  }

  void _showPermitDetails(
    Map<String, dynamic> permit,
    Map<String, dynamic> statusInfo,
    Map<String, dynamic> typeInfo,
    String formattedStartDate,
    String formattedEndDate,
    String formattedSubmissionDate,
    int duration,
  ) {
    showModalBottomSheet(
      context: context,
      isScrollControlled: true,
      backgroundColor: Colors.transparent,
      builder: (context) => Container(
        decoration: const BoxDecoration(
          color: Colors.white,
          borderRadius: BorderRadius.vertical(top: Radius.circular(20)),
        ),
        padding: const EdgeInsets.symmetric(vertical: 20),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            Container(
              width: 40,
              height: 4,
              decoration: BoxDecoration(
                color: Colors.grey.shade300,
                borderRadius: BorderRadius.circular(2),
              ),
            ),
            const SizedBox(height: 20),

            Padding(
              padding: const EdgeInsets.symmetric(horizontal: 20),
              child: Row(
                children: [
                  Container(
                    padding: const EdgeInsets.all(12),
                    decoration: BoxDecoration(
                      color: statusInfo['color'].withOpacity(0.1),
                      borderRadius: BorderRadius.circular(12),
                    ),
                    child: Icon(
                      typeInfo['icon'],
                      color: typeInfo['color'],
                      size: 24,
                    ),
                  ),
                  const SizedBox(width: 16),
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(
                          'Detail ${typeInfo['label']}',
                          style: const TextStyle(
                            fontSize: 20,
                            fontWeight: FontWeight.bold,
                          ),
                        ),
                        Container(
                          margin: const EdgeInsets.only(top: 4),
                          padding: const EdgeInsets.symmetric(
                            horizontal: 12,
                            vertical: 6,
                          ),
                          decoration: BoxDecoration(
                            color: statusInfo['backgroundColor'],
                            borderRadius: BorderRadius.circular(20),
                          ),
                          child: Row(
                            mainAxisSize: MainAxisSize.min,
                            children: [
                              Icon(
                                statusInfo['icon'],
                                color: statusInfo['color'],
                                size: 16,
                              ),
                              const SizedBox(width: 6),
                              Text(
                                statusInfo['label'],
                                style: TextStyle(
                                  color: statusInfo['color'],
                                  fontSize: 14,
                                  fontWeight: FontWeight.w600,
                                ),
                              ),
                            ],
                          ),
                        ),
                      ],
                    ),
                  ),
                ],
              ),
            ),

            const SizedBox(height: 24),

            Padding(
              padding: const EdgeInsets.symmetric(horizontal: 20),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  _buildDetailItem(
                    icon: Icons.date_range,
                    title: 'Periode Izin',
                    value: '$formattedStartDate - $formattedEndDate',
                    subtitle: duration > 0 ? '$duration hari' : null,
                  ),
                  if (formattedSubmissionDate.isNotEmpty)
                    _buildDetailItem(
                      icon: Icons.schedule,
                      title: 'Tanggal Pengajuan',
                      value: formattedSubmissionDate,
                    ),
                  if (permit['description']?.isNotEmpty == true)
                    _buildDetailItem(
                      icon: Icons.description,
                      title: 'Deskripsi',
                      value: permit['description'],
                      isMultiline: true,
                    ),
                  if (permit['proofImageUrl']?.isNotEmpty == true)
                    _buildDetailItem(
                      icon: Icons.image,
                      title: 'Bukti Pendukung',
                      value: 'Gambar tersedia',
                      trailing: TextButton(
                        onPressed: () {
                          _showImageDialog(context, permit['proofImageUrl']!);
                        },
                        child: const Text(
                          'Lihat',
                          style: TextStyle(color: Color(0xFF00BCD4)),
                        ),
                      ),
                    ),
                ],
              ),
            ),

            const SizedBox(height: 20),
          ],
        ),
      ),
    );
  }

  Widget _buildDetailItem({
    required IconData icon,
    required String title,
    required String value,
    String? subtitle,
    bool isMultiline = false,
    Widget? trailing,
  }) {
    return Padding(
      padding: const EdgeInsets.only(bottom: 16),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Container(
            padding: const EdgeInsets.all(8),
            decoration: BoxDecoration(
              color: Colors.grey.shade100,
              borderRadius: BorderRadius.circular(8),
            ),
            child: Icon(icon, size: 20, color: Colors.grey.shade600),
          ),
          const SizedBox(width: 16),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  title,
                  style: TextStyle(
                    fontSize: 12,
                    color: Colors.grey.shade600,
                    fontWeight: FontWeight.w500,
                  ),
                ),
                const SizedBox(height: 4),
                Text(
                  value,
                  style: const TextStyle(
                    fontSize: 16,
                    fontWeight: FontWeight.w600,
                    color: Colors.black87,
                  ),
                  maxLines: isMultiline ? null : 1,
                ),
                if (subtitle != null) ...[
                  const SizedBox(height: 2),
                  Text(
                    subtitle,
                    style: TextStyle(fontSize: 14, color: Colors.grey.shade600),
                  ),
                ],
              ],
            ),
          ),
          if (trailing != null) trailing,
        ],
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    final user = FirebaseAuth.instance.currentUser;

    if (user == null) {
      return Scaffold(
        backgroundColor: Colors.grey.shade50,
        body: _buildErrorState('Anda harus login untuk melihat riwayat izin.'),
      );
    }

    return Scaffold(
      backgroundColor: Colors.grey.shade50,
      appBar: AppBar(
        title: const Text('Riwayat Perizinan'),
        backgroundColor: const Color(0xFF00BCD4),
        foregroundColor: Colors.white,
        elevation: 0,
        systemOverlayStyle: SystemUiOverlayStyle.light,
      ),
      body: Column(
        children: [
          _buildFilterChips(),
          Expanded(
            child: StreamBuilder<QuerySnapshot>(
              stream: getPermitHistoryStream(),
              builder: (context, snapshot) {
                if (snapshot.connectionState == ConnectionState.waiting) {
                  return const Center(child: CircularProgressIndicator());
                }

                if (snapshot.hasError) {
                  debugPrint('StreamBuilder error: ${snapshot.error}');
                  return _buildErrorState('Gagal memuat riwayat izin: ${snapshot.error}');
                }

                final allPermits = snapshot.data!.docs;
                // PERBAIKAN: Konversi status dari Firestore ke huruf kecil untuk perbandingan
                final filteredPermits = allPermits.where((doc) {
                  final permit = doc.data() as Map<String, dynamic>;
                  final permitStatusLowerCase = (permit['status'] as String).toLowerCase();
                  return _selectedFilter == 'all' || permitStatusLowerCase == _selectedFilter;
                }).toList();

                if (filteredPermits.isEmpty) {
                  return _buildEmptyState();
                }

                return ListView.builder(
                  padding: const EdgeInsets.only(bottom: 20),
                  itemCount: filteredPermits.length,
                  itemBuilder: (context, index) {
                    final permit = filteredPermits[index].data() as Map<String, dynamic>;
                    return _buildPermitCard(permit, index);
                  },
                );
              },
            ),
          ),
        ],
      ),
    );
  }
}
