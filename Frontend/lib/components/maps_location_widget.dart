import 'dart:math';
import 'package:flutter/material.dart';
import 'package:flutter_map/flutter_map.dart';
import 'package:geolocator/geolocator.dart';
import 'package:latlong2/latlong.dart';
import 'package:fluttertoast/fluttertoast.dart';

class MapLocationWidget extends StatefulWidget {
  const MapLocationWidget({super.key});

  @override
  State<MapLocationWidget> createState() => MapLocationWidgetState();
}

class MapLocationWidgetState extends State<MapLocationWidget> {
  LatLng? _currentPosition;
  late Future<bool> _permissionFuture;

  final LatLng centerLocation = LatLng(-7.690909, 111.604621);
  final double allowedRadiusInMeters = 50;
  bool isWithinRadius = false;

  @override
  void initState() {
    super.initState();
    _permissionFuture = _handleLocationPermission();
  }

  Future<bool> _handleLocationPermission() async {
    bool serviceEnabled = await Geolocator.isLocationServiceEnabled();
    if (!serviceEnabled) {
      _showToast('Layanan lokasi tidak aktif.');
      return false;
    }

    LocationPermission permission = await Geolocator.checkPermission();
    if (permission == LocationPermission.denied) {
      permission = await Geolocator.requestPermission();
      if (permission == LocationPermission.denied) {
        _showToast('Izin lokasi ditolak');
        return false;
      }
    }

    if (permission == LocationPermission.deniedForever) {
      _showToast('Izin lokasi ditolak permanen. Aktifkan di pengaturan.');
      return false;
    }

    Position position = await Geolocator.getCurrentPosition(
      desiredAccuracy: LocationAccuracy.high,
    );
    LatLng currentLatLng = LatLng(position.latitude, position.longitude);

    double distance = calculateDistance(
      currentLatLng.latitude,
      currentLatLng.longitude,
      centerLocation.latitude,
      centerLocation.longitude,
    );

    if (distance > allowedRadiusInMeters) {
      _showToast('Kamu berada di luar area yang diizinkan (${distance.toStringAsFixed(1)} m)');
      setState(() {
        isWithinRadius = false;
        _currentPosition = currentLatLng;
      });
      return false;
    }

    setState(() {
      _currentPosition = currentLatLng;
      isWithinRadius = true;
    });

    return true;
  }

  void _showToast(String message) {
    Fluttertoast.showToast(
      msg: message,
      toastLength: Toast.LENGTH_LONG,
      gravity: ToastGravity.BOTTOM,
      backgroundColor: Colors.black87,
      textColor: Colors.white,
      fontSize: 16.0,
    );
  }

  Future<void> refreshLocation() async {
    setState(() {
      _permissionFuture = _handleLocationPermission();
    });
  }

  bool userIsWithinRadius() {
    return isWithinRadius;
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
          future: _permissionFuture,
          builder: (context, snapshot) {
            if (snapshot.connectionState == ConnectionState.waiting) {
              return const Center(child: CircularProgressIndicator());
            }

            if (snapshot.hasError || _currentPosition == null) {
              return const Center(child: Text('Tidak dapat mengakses lokasi'));
            }

            final isInsideRadius = calculateDistance(
              _currentPosition!.latitude,
              _currentPosition!.longitude,
              centerLocation.latitude,
              centerLocation.longitude,
            ) <= allowedRadiusInMeters;

            return Stack(
              children: [
                FlutterMap(
                  options: MapOptions(
                    initialCenter: _currentPosition!,
                    initialZoom: 15,
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
                          color: Colors.blue.withOpacity(0.2),
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
                            color: isInsideRadius ? Colors.green : Colors.red,
                          ),
                        ),
                      ],
                    ),
                  ],
                ),

                // Status informasi
                Positioned(
                  top: 16,
                  left: 16,
                  right: 16,
                  child: Container(
                    padding: const EdgeInsets.all(12),
                    decoration: BoxDecoration(
                      color: isInsideRadius ? Colors.green[600] : Colors.red[600],
                      borderRadius: BorderRadius.circular(12),
                      boxShadow: const [
                        BoxShadow(color: Colors.black26, blurRadius: 4, offset: Offset(0, 2)),
                      ],
                    ),
                    child: Text(
                      isInsideRadius
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

  double calculateDistance(double lat1, double lon1, double lat2, double lon2) {
    const earthRadius = 6371000; // in meters
    final dLat = _degToRad(lat2 - lat1);
    final dLon = _degToRad(lon2 - lon1);

    final a = sin(dLat / 2) * sin(dLat / 2) +
        cos(_degToRad(lat1)) * cos(_degToRad(lat2)) *
            sin(dLon / 2) * sin(dLon / 2);

    final c = 2 * atan2(sqrt(a), sqrt(1 - a));
    return earthRadius * c;
  }

  double _degToRad(double deg) {
    return deg * (pi / 180);
  }
}
