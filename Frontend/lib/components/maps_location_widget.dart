import 'dart:math';
import 'package:flutter/material.dart';
import 'package:flutter_map/flutter_map.dart';
import 'package:geolocator/geolocator.dart';
import 'package:latlong2/latlong.dart';
import 'package:cloud_firestore/cloud_firestore.dart';
import '../utils/customSnackBar_utils.dart'; // Pastikan path ini benar

// Data model for Office Location
class OfficeLocationConfig {
  final String id;
  final String name;
  final String description;
  final LatLng center;
  final double radius;

  OfficeLocationConfig({
    required this.id,
    required this.name,
    required this.description,
    required this.center,
    required this.radius,
  });

  factory OfficeLocationConfig.fromDocument(DocumentSnapshot doc) {
    final data = doc.data() as Map<String, dynamic>;

    // Perbaikan: Ambil nilai langsung sebagai double, bukan mencoba parsing dari String
    // Jika di Firestore tersimpan sebagai int atau double, ini akan langsung berhasil.
    // Jika masih string, double.tryParse akan menanganinya.
    final latitude = (data['latitude'] is num)
        ? (data['latitude'] as num).toDouble()
        : double.tryParse(data['latitude']?.toString() ?? '0.0') ?? 0.0;
    final longitude = (data['longitude'] is num)
        ? (data['longitude'] as num).toDouble()
        : double.tryParse(data['longitude']?.toString() ?? '0.0') ?? 0.0;
    final radius = (data['radius'] is num)
        ? (data['radius'] as num).toDouble()
        : double.tryParse(data['radius']?.toString() ?? '0.0') ?? 0.0;

    return OfficeLocationConfig(
      id: doc.id,
      name: data['nama_lokasi'] as String? ?? 'Nama Tidak Diketahui',
      description: data['deskripsi'] as String? ?? 'Deskripsi Tidak Tersedia',
      center: LatLng(latitude, longitude),
      radius: radius,
    );
  }
}

class MapLocationWidget extends StatefulWidget {
  const MapLocationWidget({super.key});

  @override
  State<MapLocationWidget> createState() => MapLocationWidgetState();
}

class MapLocationWidgetState extends State<MapLocationWidget> {
  LatLng? _currentPosition;
  List<OfficeLocationConfig> _availableOffices = []; // List of all available offices
  String? _activeOfficeName; // Stores the name of the office the user is currently in (if any)
  bool _isMockLocationDetected = false; // <--- BARU: Status deteksi Fake GPS
  double? _locationAccuracy; // <--- BARU: Akurasi lokasi

  // NEW: Public getter for _activeOfficeName
  String? get activeOfficeName => _activeOfficeName;

  late Future<bool> _permissionAndLocationFuture;

  @override
  void initState() {
    super.initState();
    _permissionAndLocationFuture = _loadGeolocatorConfigAndFetchLocation();
  }

  // Modified: Function to load all geolocator configurations from Firestore
  Future<void> _loadGeolocatorConfig() async {
    try {
      final QuerySnapshot querySnapshot = await FirebaseFirestore.instance
          .collection('geo_locator')
          .get();

      if (querySnapshot.docs.isNotEmpty) {
        final List<OfficeLocationConfig> offices = querySnapshot.docs
            .map((doc) => OfficeLocationConfig.fromDocument(doc))
            .toList();

        if (mounted) {
          setState(() {
            _availableOffices = offices;
          });
        }
        debugPrint(
          'Geo-locator configs loaded: ${_availableOffices.length} offices.',
        );
        return;
      } else {
        debugPrint('No geo_locator documents found in Firestore.');
      }
    } catch (e) {
      debugPrint('Error loading geo_locator configs from Firestore: $e');
      if (mounted) {
        showCustomSnackBar(
          context,
          'Gagal memuat konfigurasi lokasi kantor: $e',
          isError: true,
        );
      }
    }
    // Fallback if Firestore fails or is empty: ensure available offices list is empty
    if (mounted) {
      setState(() {
        _availableOffices = [];
      });
    }
    debugPrint(
      'No geo-locator config available. Map may not display correctly.',
    );
  }

  // Modified: Combine config loading and location fetching
  Future<bool> _loadGeolocatorConfigAndFetchLocation() async {
    if (mounted) {
      setState(() {
        _currentPosition = null;
        _activeOfficeName = null; // Reset active office name
        _isMockLocationDetected = false; // <--- BARU: Reset status Fake GPS
        _locationAccuracy = null; // <--- BARU: Reset akurasi
      });
    }

    // Always ensure configs are loaded before attempting to fetch location
    await _loadGeolocatorConfig();

    if (_availableOffices.isEmpty) {
      if (mounted) {
        showCustomSnackBar(
          context,
          'Tidak ada data lokasi kantor yang tersedia di Firestore.',
          isError: true,
        );
      }
      return false;
    }

    final serviceEnabled = await Geolocator.isLocationServiceEnabled();
    if (!mounted) return false;
    if (!serviceEnabled) {
      if (mounted) {
        showCustomSnackBar(
          context,
          'Layanan lokasi tidak aktif. Harap aktifkan GPS Anda.',
          isError: true,
        );
      }
      return false;
    }

    LocationPermission permission = await Geolocator.checkPermission();
    if (!mounted) return false;

    if (permission == LocationPermission.denied) {
      permission = await Geolocator.requestPermission();
      if (!mounted) return false;
      if (permission == LocationPermission.denied) {
        if (mounted) {
          showCustomSnackBar(
            context,
            'Izin lokasi ditolak. Aplikasi memerlukan izin lokasi.',
            isError: true,
          );
        }
        return false;
      }
    }

    if (permission == LocationPermission.deniedForever) {
      if (mounted) {
        showCustomSnackBar(
          context,
          'Izin lokasi ditolak permanen. Aktifkan di pengaturan perangkat Anda.',
          isError: true,
        );
      }
      return false;
    }

    try {
      final position = await Geolocator.getCurrentPosition(
        locationSettings: const LocationSettings(
          accuracy: LocationAccuracy.high,
          distanceFilter: 0,
        ),
      );
      if (!mounted) return false;

      // <--- BARU: Deteksi Fake GPS
      final isMock = position.isMocked;
      final accuracy = position.accuracy;

      if (mounted) {
        setState(() {
          _isMockLocationDetected = isMock;
          _locationAccuracy = accuracy;
        });
      }

      if (isMock) {
        if (mounted) {
          showCustomSnackBar(
            context,
            '⛔ Terdeteksi Lokasi Palsu (Mock Location)! Presensi diblokir.',
            isError: true,
          );
        }
        return false; // Blokir presensi jika lokasi palsu terdeteksi
      }
      // Akhir deteksi Fake GPS --->

      final currentLatLng = LatLng(position.latitude, position.longitude);
      String? foundOfficeName; // To store the name of the office if found within radius

      // NEW LOGIC: Check distance against ALL available offices
      for (var office in _availableOffices) {
        final distance = _calculateDistance(
          currentLatLng.latitude,
          currentLatLng.longitude,
          office.center.latitude,
          office.center.longitude,
        );
        debugPrint(
          'Distance to ${office.name}: ${distance.toStringAsFixed(1)} m (Radius: ${office.radius} m)',
        );

        if (distance <= office.radius) {
          foundOfficeName = office.name;
          break; // Found an office, no need to check others
        }
      }

      if (mounted) {
        setState(() {
          _currentPosition = currentLatLng;
          _activeOfficeName = foundOfficeName; // Set the name of the active office
        });
      }

      if (!mounted) return userIsWithinRadius(); // Check the getter
      if (!userIsWithinRadius()) {
        // User is not in any office
        showCustomSnackBar(
          context,
          '🔴 Kamu berada di luar area presensi manapun.',
          isError: true,
        );
      } else {
        // User is in an office
        showCustomSnackBar(
          context,
          '🟢 Kamu berada di area presensi $_activeOfficeName.',
        );
      }
      return userIsWithinRadius(); // Return the getter
    } catch (e) {
      if (!mounted) return false;
      debugPrint('Error mendapatkan lokasi: $e');
      if (mounted) {
        showCustomSnackBar(
          context,
          'Gagal mendapatkan lokasi: $e',
          isError: true,
        );
      }
      if (mounted) {
        setState(() {
          _currentPosition = null;
          _activeOfficeName = null; // Reset
          _isMockLocationDetected = false; // Reset
          _locationAccuracy = null; // Reset
        });
      }
      return false;
    }
  }

  Future<void> refreshLocation() async {
    if (mounted) {
      setState(() {
        _permissionAndLocationFuture = _loadGeolocatorConfigAndFetchLocation();
      });
    }
  }

  // This getter now correctly reflects if _activeOfficeName is not null AND no mock location
  bool userIsWithinRadius() => _activeOfficeName != null && !_isMockLocationDetected;

  @override
  Widget build(BuildContext context) {
    return Column(
      children: [
        // Removed DropdownButtonFormField
        Container(
          height: 300,
          margin: const EdgeInsets.symmetric(horizontal: 16, vertical: 16),
          decoration: BoxDecoration(
            borderRadius: BorderRadius.circular(20),
            boxShadow: const [
              BoxShadow(
                color: Colors.black12,
                blurRadius: 8,
                offset: Offset(0, 4),
              ),
            ],
          ),
          child: ClipRRect(
            borderRadius: BorderRadius.circular(20),
            child: FutureBuilder<bool>(
              future: _permissionAndLocationFuture,
              builder: (context, snapshot) {
                if (snapshot.connectionState == ConnectionState.waiting) {
                  return const Center(child: CircularProgressIndicator());
                }

                // Show error if no office configs are loaded at all
                if (_availableOffices.isEmpty) {
                  return Center(
                    child: Padding(
                      padding: const EdgeInsets.all(16.0),
                      child: Column(
                        mainAxisAlignment: MainAxisAlignment.center,
                        children: [
                          Icon(
                            Icons.business_center,
                            size: 50,
                            color: Colors.orange.shade600,
                          ),
                          const SizedBox(height: 10),
                          const Text(
                            'Tidak ada data lokasi kantor yang tersedia di Firestore.',
                            textAlign: TextAlign.center,
                            style: TextStyle(
                              color: Color.fromRGBO(97, 97, 97, 1),
                            ),
                          ),
                          const SizedBox(height: 10),
                          ElevatedButton(
                            onPressed: refreshLocation,
                            child: const Text('Coba Lagi'),
                          ),
                        ],
                      ),
                    ),
                  );
                }

                // Show general location error if current position is null or snapshot has error
                if (snapshot.hasError || _currentPosition == null) {
                  return Center(
                    child: Padding(
                      padding: const EdgeInsets.all(16.0),
                      child: Column(
                        mainAxisAlignment: MainAxisAlignment.center,
                        children: [
                          Icon(
                            Icons.location_off,
                            size: 50,
                            color: Colors.grey.shade600,
                          ),
                          const SizedBox(height: 10),
                          Text(
                            'Tidak dapat mengakses lokasi Anda. ${snapshot.error ?? ''}',
                            textAlign: TextAlign.center,
                            style: TextStyle(color: Colors.grey[700]),
                          ),
                          const SizedBox(height: 10),
                          ElevatedButton(
                            onPressed: refreshLocation,
                            child: const Text('Coba Lagi'),
                          ),
                        ],
                      ),
                    ),
                  );
                }

                // If all good, display the map
                return Stack(
                  children: [
                    FlutterMap(
                      options: MapOptions(
                        initialCenter:
                            _currentPosition!, // Center map on user's current location
                        initialZoom:
                            15, // Zoom level adjusted for better view of multiple circles if needed
                        interactionOptions: const InteractionOptions(
                          flags: InteractiveFlag.all & ~InteractiveFlag.rotate,
                        ),
                      ),
                      children: [
                        TileLayer(
                          urlTemplate:
                              'https://tile.openstreetmap.org/{z}/{x}/{y}.png',
                          userAgentPackageName: 'com.presence.app',
                        ),
                        // NEW: Draw circles for ALL available offices
                        CircleLayer(
                          circles: _availableOffices.map((office) {
                            return CircleMarker(
                              point: office.center,
                              radius: office.radius,
                              color: Colors.blue.withAlpha(
                                (255 * 0.2).round(),
                              ),
                              borderStrokeWidth: 2,
                              borderColor: Colors.blueAccent,
                            );
                          }).toList(),
                        ),
                        // NEW: Draw markers for ALL available offices and current user
                        MarkerLayer(
                          markers: [
                            // User's current location marker
                            Marker(
                              point: _currentPosition!,
                              width: 40,
                              height: 40,
                              child: Icon(
                                Icons
                                    .person_pin_circle, // More specific icon for user
                                size: 40,
                                color: _isMockLocationDetected
                                    ? Colors.orange // Marker orange jika fake GPS
                                    : (userIsWithinRadius()
                                        ? Colors.green
                                        : Colors.red),
                              ),
                            ),
                            // Markers for each office
                            ..._availableOffices.map((office) {
                              return Marker(
                                point: office.center,
                                width: 40,
                                height: 40,
                                child: Icon(
                                  Icons.business, // Icon for office
                                  size: 40,
                                  color: Colors.blue.shade800,
                                ),
                              );
                            }),
                          ],
                        ),
                      ],
                    ),

                    // Status Text Overlay
                    Positioned(
                      top: 16,
                      left: 16,
                      right: 16,
                      child: Container(
                        padding: const EdgeInsets.all(12),
                        decoration: BoxDecoration(
                          color: _isMockLocationDetected
                              ? Colors.orange.withAlpha((255 * 0.9).round())
                              : (userIsWithinRadius() ? Colors.green : Colors.red)
                                  .withAlpha((255 * 0.9).round()),
                          borderRadius: BorderRadius.circular(12),
                          boxShadow: const [
                            BoxShadow(
                              color: Colors.black26,
                              blurRadius: 4,
                              offset: Offset(0, 2),
                            ),
                          ],
                        ),
                        child: Text(
                          _isMockLocationDetected
                              ? "⛔ Lokasi Palsu (Mock Location) terdeteksi!"
                              : (_activeOfficeName != null
                                  ? "🟢 Anda berada di area presensi $_activeOfficeName."
                                  : "🔴 Anda berada di luar area presensi manapun."),
                          style: const TextStyle(
                            color: Colors.white,
                            fontWeight: FontWeight.bold,
                          ),
                          textAlign: TextAlign.center,
                        ),
                      ),
                    ),
                    // <--- BARU: Tampilkan akurasi lokasi
                    if (_locationAccuracy != null)
                      Positioned(
                        bottom: 16,
                        left: 16,
                        right: 16,
                        child: Container(
                          padding: const EdgeInsets.all(8),
                          decoration: BoxDecoration(
                            color: Colors.black.withOpacity(0.7),
                            borderRadius: BorderRadius.circular(8),
                          ),
                          child: Text(
                            'Akurasi GPS: ${_locationAccuracy!.toStringAsFixed(1)} meter',
                            style: const TextStyle(
                              color: Colors.white,
                              fontSize: 12,
                            ),
                            textAlign: TextAlign.center,
                          ),
                        ),
                      ),
                    // Akhir akurasi lokasi --->
                  ],
                );
              },
            ),
          ),
        ),
      ],
    );
  }

  double _calculateDistance(
    double lat1,
    double lon1,
    double lat2,
    double lon2,
  ) {
    const earthRadius = 6371000; // meter
    final dLat = _degToRad(lat2 - lat1);
    final dLon = _degToRad(lon2 - lon1);
    final a =
        sin(dLat / 2) * sin(dLat / 2) +
        cos(_degToRad(lat1)) *
            cos(_degToRad(lat2)) *
            sin(dLon / 2) *
            sin(dLon / 2);
    final c = 2 * atan2(sqrt(a), sqrt(1 - a));
    return earthRadius * c;
  }

  double _degToRad(double deg) => deg * (pi / 180);
}