import 'dart:math';
import 'package:flutter/material.dart';
import 'package:flutter_map/flutter_map.dart';
import 'package:geolocator/geolocator.dart';
import 'package:latlong2/latlong.dart';
import '../utils/notification_utils.dart';

class MapLocationWidget extends StatefulWidget {
  const MapLocationWidget({super.key});

  @override
  State<MapLocationWidget> createState() => MapLocationWidgetState();
}

class MapLocationWidgetState extends State<MapLocationWidget> {
  LatLng? _currentPosition;
  late Future<bool> _permissionFuture;

  final LatLng centerLocation = const LatLng(-7.690620, 111.604692);
  final double allowedRadiusInMeters = 50;

  bool isWithinRadius = false;

  @override
  void initState() {
    super.initState();
    _permissionFuture = _fetchLocationAndCheckRadius();
  }

  Future<bool> _fetchLocationAndCheckRadius() async {
    if (mounted) {
      setState(() {
        _currentPosition = null;
        isWithinRadius = false;
      });
    }

    final serviceEnabled = await Geolocator.isLocationServiceEnabled();
    if (!mounted) return false;
    if (!serviceEnabled) {
      showCustomSnackBar(
        context,
        'Layanan lokasi tidak aktif. Harap aktifkan GPS Anda.',
        isError: true,
      );
      return false;
    }

    LocationPermission permission = await Geolocator.checkPermission();
    if (!mounted) return false;

    if (permission == LocationPermission.denied) {
      permission = await Geolocator.requestPermission();
      if (!mounted) return false;
      if (permission == LocationPermission.denied) {
        showCustomSnackBar(
          context,
          'Izin lokasi ditolak. Aplikasi memerlukan izin lokasi.',
          isError: true,
        );
        return false;
      }
    }

    if (permission == LocationPermission.deniedForever) {
      if (!mounted) return false;
      showCustomSnackBar(
        context,
        'Izin lokasi ditolak permanen. Aktifkan di pengaturan perangkat Anda.',
        isError: true,
      );
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

      final currentLatLng = LatLng(position.latitude, position.longitude);
      final distance = _calculateDistance(
        currentLatLng.latitude,
        currentLatLng.longitude,
        centerLocation.latitude,
        centerLocation.longitude,
      );

      if (mounted) {
        setState(() {
          _currentPosition = currentLatLng;
          isWithinRadius = distance <= allowedRadiusInMeters;
        });
      }

      if (!mounted) return isWithinRadius;
      if (!isWithinRadius) {
        showCustomSnackBar(
          context,
          'Kamu berada di luar area yang diizinkan (${distance.toStringAsFixed(1)} m).',
          isError: true,
        );
      } else {
        showCustomSnackBar(
          context,
          'Kamu berada di area presensi. (${distance.toStringAsFixed(1)} m).',
        );
      }
      return isWithinRadius;
    } catch (e) {
      if (!mounted) return false;
      debugPrint('Error mendapatkan lokasi: $e');
      showCustomSnackBar(
        context,
        'Gagal mendapatkan lokasi: $e',
        isError: true,
      );
      if (mounted) {
        setState(() {
          _currentPosition = null;
          isWithinRadius = false;
        });
      }
      return false;
    }
  }

  Future<void> refreshLocation() async {
    if (mounted) {
      setState(() {
        _permissionFuture = _fetchLocationAndCheckRadius();
      });
    }
  }

  bool userIsWithinRadius() => isWithinRadius;

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
          future: _permissionFuture,
          builder: (context, snapshot) {
            if (snapshot.connectionState == ConnectionState.waiting) {
              return const Center(child: CircularProgressIndicator());
            }

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

            return Stack(
              children: [
                FlutterMap(
                  options: MapOptions(
                    initialCenter: _currentPosition!,
                    initialZoom: 15,
                    interactionOptions: const InteractionOptions(
                      flags: InteractiveFlag.all & ~InteractiveFlag.rotate,
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
                          point: centerLocation,
                          radius: allowedRadiusInMeters,
                          color: Colors.blue.withValues(alpha: 0.2),
                          borderStrokeWidth: 2,
                          borderColor: Colors.blueAccent,
                        ),
                      ],
                    ),
                    MarkerLayer(
                      markers: [
                        Marker(
                          point: _currentPosition!,
                          width: 40,
                          height: 40,
                          child: Icon(
                            Icons.location_pin,
                            size: 40,
                            color: isWithinRadius ? Colors.green : Colors.red,
                          ),
                        ),
                      ],
                    ),
                  ],
                ),

                Positioned(
                  top: 16,
                  left: 16,
                  right: 16,
                  child: Container(
                    padding: const EdgeInsets.all(12),
                    decoration: BoxDecoration(
                      color: (isWithinRadius ? Colors.green : Colors.red).withValues(alpha: 0.9),
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

  double _calculateDistance(double lat1, double lon1, double lat2, double lon2) {
    const earthRadius = 6371000; // meter
    final dLat = _degToRad(lat2 - lat1);
    final dLon = _degToRad(lon2 - lon1);
    final a = sin(dLat / 2) * sin(dLat / 2) +
        cos(_degToRad(lat1)) * cos(_degToRad(lat2)) *
            sin(dLon / 2) * sin(dLon / 2);
    final c = 2 * atan2(sqrt(a), sqrt(1 - a));
    return earthRadius * c;
  }

  double _degToRad(double deg) => deg * (pi / 180);
}