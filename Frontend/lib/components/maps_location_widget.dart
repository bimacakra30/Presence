import 'dart:math';
import 'package:flutter/material.dart';
import 'package:flutter_map/flutter_map.dart';
import 'package:geolocator/geolocator.dart';
import 'package:latlong2/latlong.dart';
import '../utils/notification_utils.dart'; // Impor file notification_utils yang baru

class MapLocationWidget extends StatefulWidget {
  // Key ditambahkan agar Home Page bisa memanggil refreshLocation()
  const MapLocationWidget({super.key});

  @override
  State<MapLocationWidget> createState() => MapLocationWidgetState();
}

class MapLocationWidgetState extends State<MapLocationWidget> {
  LatLng? _currentPosition; // Posisi pengguna saat ini
  late Future<bool> _permissionFuture; // Future untuk melacak status izin lokasi

  // Lokasi pusat kantor dan radius yang diizinkan
  final LatLng centerLocation = LatLng(-7.626858, 111.537677);
  final double allowedRadiusInMeters = 50;
  
  // Status apakah pengguna berada di dalam radius
  bool isWithinRadius = false;

  @override
  void initState() {
    super.initState();
    // Inisialisasi future untuk mendapatkan lokasi saat widget pertama kali dibuat
    _permissionFuture = _fetchLocationAndCheckRadius();
  }

  // Fungsi utama untuk menangani izin lokasi, mendapatkan posisi, dan memeriksa radius
  Future<bool> _fetchLocationAndCheckRadius() async {
    // Reset status sebelum mencoba mendapatkan lokasi baru
    setState(() {
      _currentPosition = null;
      isWithinRadius = false;
    });

    // 1. Cek apakah layanan lokasi aktif
    bool serviceEnabled = await Geolocator.isLocationServiceEnabled();
    if (!serviceEnabled) {
      // Menggunakan showCustomSnackBar
      showCustomSnackBar(context, 'Layanan lokasi tidak aktif. Harap aktifkan GPS Anda.', isError: true);
      return false; // Gagal karena layanan tidak aktif
    }

    // 2. Cek dan minta izin lokasi
    LocationPermission permission = await Geolocator.checkPermission();
    if (permission == LocationPermission.denied) {
      permission = await Geolocator.requestPermission();
      if (permission == LocationPermission.denied) {
        // Menggunakan showCustomSnackBar
        showCustomSnackBar(context, 'Izin lokasi ditolak. Aplikasi memerlukan izin lokasi.', isError: true);
        return false; // Gagal karena izin ditolak
      }
    }

    if (permission == LocationPermission.deniedForever) {
      // Menggunakan showCustomSnackBar
      showCustomSnackBar(context, 'Izin lokasi ditolak permanen. Aktifkan di pengaturan perangkat Anda.', isError: true);
      return false; // Gagal karena izin ditolak permanen
    }

    try {
      Position position = await Geolocator.getCurrentPosition(
        desiredAccuracy: LocationAccuracy.high, // Akurasi tinggi untuk presensi
      );

      LatLng currentLatLng = LatLng(position.latitude, position.longitude);

      double distance = _calculateDistance(
        currentLatLng.latitude,
        currentLatLng.longitude,
        centerLocation.latitude,
        centerLocation.longitude,
      );

      setState(() {
        _currentPosition = currentLatLng; // Simpan posisi saat ini
        isWithinRadius = distance <= allowedRadiusInMeters; // Tentukan status radius
      });

      if (!isWithinRadius) {
        showCustomSnackBar(context, 'Kamu berada di luar area yang diizinkan (${distance.toStringAsFixed(1)} m).', isError: true);
      } else {
        showCustomSnackBar(context, 'Kamu berada di area presensi. (${distance.toStringAsFixed(1)} m).');
      }
      
      return isWithinRadius;
    } catch (e) {
      debugPrint('Error mendapatkan lokasi: $e');
      showCustomSnackBar(context, 'Gagal mendapatkan lokasi: ${e.toString()}', isError: true);
      setState(() {
        _currentPosition = null; // Pastikan posisi diset null jika ada error
        isWithinRadius = false;
      });
      return false; // Gagal karena error saat mendapatkan posisi
    }
  }
  
  Future<void> refreshLocation() async {
    setState(() {
      _permissionFuture = _fetchLocationAndCheckRadius(); // Memulai ulang proses fetch lokasi
    });
  }

  // Fungsi untuk mengembalikan status apakah pengguna berada di dalam radius
  bool userIsWithinRadius() {
    return isWithinRadius; // Mengembalikan nilai state isWithinRadius
  }

  @override
  Widget build(BuildContext context) {
    return Container(
      height: 300,
      margin: const EdgeInsets.symmetric(horizontal: 16, vertical: 16),
      decoration: BoxDecoration(
        borderRadius: BorderRadius.circular(20),
        boxShadow: const [
          BoxShadow(color: Colors.black12, blurRadius: 8, offset: Offset(0, 4)),
        ],
      ),
      child: ClipRRect(
        borderRadius: BorderRadius.circular(20),
        child: FutureBuilder<bool>(
          future: _permissionFuture, // Pantau future untuk status izin dan lokasi
          builder: (context, snapshot) {
            // Tampilan saat loading
            if (snapshot.connectionState == ConnectionState.waiting) {
              return const Center(child: CircularProgressIndicator());
            }

            // Tampilan jika ada error atau posisi belum didapat
            if (snapshot.hasError || _currentPosition == null) {
              return Center(
                child: Padding(
                  padding: const EdgeInsets.all(16.0),
                  child: Column(
                    mainAxisAlignment: MainAxisAlignment.center,
                    children: [
                      Icon(Icons.location_off, size: 50, color: Colors.grey[600]),
                      const SizedBox(height: 10),
                      Text(
                        'Tidak dapat mengakses lokasi. ${snapshot.error ?? ''}',
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

            // Tampilan map jika lokasi berhasil didapat
            return Stack(
              children: [
                FlutterMap(
                  options: MapOptions(
                    initialCenter: _currentPosition!, // Pusat map ke posisi pengguna
                    initialZoom: 15,
                    interactionOptions: const InteractionOptions(
                      flags: InteractiveFlag.all & ~InteractiveFlag.rotate, // Nonaktifkan rotasi
                    ),
                  ),
                  children: [
                    TileLayer(
                      urlTemplate: 'https://tile.openstreetmap.org/{z}/{x}/{y}.png',
                      userAgentPackageName: 'com.presence.app',
                    ),
                    CircleLayer(
                      circles: [
                        CircleMarker(
                          point: centerLocation, // Titik pusat kantor
                          radius: allowedRadiusInMeters, // Radius yang diizinkan
                          color: Colors.blue.withOpacity(0.2), // Warna area
                          borderStrokeWidth: 2,
                          borderColor: Colors.blueAccent,
                        ),
                      ],
                    ),
                    MarkerLayer(
                      markers: [
                        Marker(
                          point: _currentPosition!, // Marker posisi pengguna
                          width: 40,
                          height: 40,
                          child: Icon(
                            Icons.location_pin,
                            size: 40,
                            // Warna marker sesuai status di dalam/luar radius
                            color: isWithinRadius ? Colors.green : Colors.red,
                          ),
                        ),
                      ],
                    ),
                  ],
                ),

                // Status informasi di atas map
                Positioned(
                  top: 16,
                  left: 16,
                  right: 16,
                  child: Container(
                    padding: const EdgeInsets.all(12),
                    decoration: BoxDecoration(
                      color: isWithinRadius ? Colors.green[600] : Colors.red[600],
                      borderRadius: BorderRadius.circular(12),
                      boxShadow: const [
                        BoxShadow(color: Colors.black26, blurRadius: 4, offset: Offset(0, 2)),
                      ],
                    ),
                    child: Text(
                      isWithinRadius
                          ? "🟢 Anda berada di area presensi."
                          : "🔴 Anda berada di luar area presensi.",
                      style: const TextStyle(
                        color: Colors.white,
                        fontWeight: FontWeight.bold,
                      ),
                      textAlign: TextAlign.center,
                    ),
                  ),
                ),
              ],
            );
          },
        ),
      ),
    );
  }

  // Fungsi untuk menghitung jarak antara dua koordinat (Haversine formula)
  double _calculateDistance(double lat1, double lon1, double lat2, double lon2) {
    const earthRadius = 6371000; // Radius bumi dalam meter
    final dLat = _degToRad(lat2 - lat1);
    final dLon = _degToRad(lon2 - lon1);

    final a = sin(dLat / 2) * sin(dLat / 2) +
        cos(_degToRad(lat1)) * cos(_degToRad(lat2)) *
            sin(dLon / 2) * sin(dLon / 2);

    final c = 2 * atan2(sqrt(a), sqrt(1 - a));
    return earthRadius * c;
  }

  // Fungsi bantu untuk mengubah derajat ke radian
  double _degToRad(double deg) {
    return deg * (pi / 180);
  }
}