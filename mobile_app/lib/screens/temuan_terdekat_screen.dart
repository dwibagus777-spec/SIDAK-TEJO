import 'package:flutter/material.dart';
import 'package:flutter_map/flutter_map.dart';
import 'package:latlong2/latlong2.dart';
import 'package:geolocator/geolocator.dart';
import '../services/api_service.dart';

class TemuanTerdekatScreen extends StatefulWidget {
  const TemuanTerdekatScreen({super.key});

  @override
  State<TemuanTerdekatScreen> createState() => _TemuanTerdekatScreenState();
}

class _TemuanTerdekatScreenState extends State<TemuanTerdekatScreen> {
  final MapController _mapController = MapController();
  LatLng? _userLocation;
  List<dynamic> _nearbyFindings = [];
  bool _isLoading = true;
  final int _searchRadius = 500; // Default 500 meters

  // Selected Marker Info Card
  dynamic _selectedFinding;

  @override
  void initState() {
    super.initState();
    _getUserLocationAndQuery();
  }

  void _getUserLocationAndQuery() async {
    setState(() {
      _isLoading = true;
      _selectedFinding = null;
    });

    try {
      LocationPermission permission = await Geolocator.checkPermission();
      if (permission == LocationPermission.denied) {
        permission = await Geolocator.requestPermission();
      }

      Position position = await Geolocator.getCurrentPosition(
        desiredAccuracy: LocationAccuracy.high,
      );

      final userLatLng = LatLng(position.latitude, position.longitude);

      // Call API
      final findings = await ApiService.getTemuanTerdekat(
        position.latitude,
        position.longitude,
        _searchRadius,
      );

      setState(() {
        _userLocation = userLatLng;
        _nearbyFindings = findings;
        _isLoading = false;
      });

      // Move map view to user
      _mapController.move(userLatLng, 15.5);
    } catch (e) {
      setState(() => _isLoading = false);
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            content: Text('Gagal memuat peta lokasi: $e'),
            backgroundColor: Colors.redAccent,
            behavior: SnackBarBehavior.floating,
          ),
        );
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    // Generate list of markers
    List<Marker> markers = [];

    // Add user marker
    if (_userLocation != null) {
      markers.add(
        Marker(
          point: _userLocation!,
          width: 60,
          height: 60,
          child: Column(
            children: [
              Container(
                padding: const EdgeInsets.all(4),
                decoration: BoxDecoration(
                  color: const Color(0xFF0082C8).withOpacity(0.2),
                  shape: BoxShape.circle,
                ),
                child: Container(
                  width: 20,
                  height: 20,
                  decoration: const BoxDecoration(
                    color: Color(0xFF0082C8),
                    shape: BoxShape.circle,
                  ),
                ),
              ),
              Container(
                padding: const EdgeInsets.symmetric(horizontal: 6, vertical: 2),
                decoration: BoxDecoration(
                  color: Colors.black.withOpacity(0.7),
                  borderRadius: BorderRadius.circular(4),
                ),
                child: const Text('Lokasi Saya', style: TextStyle(color: Colors.white, fontSize: 9)),
              )
            ],
          ),
        ),
      );
    }

    // Add finding markers
    for (var item in _nearbyFindings) {
      final double? lat = double.tryParse(item['latitude'] ?? '');
      final double? lng = double.tryParse(item['longitude'] ?? '');

      if (lat != null && lng != null) {
        final String prioritas = item['prioritas'] ?? 'MEDIUM';
        Color pinColor = const Color(0xFFEA580C);
        if (prioritas == 'EMERGENCY') pinColor = const Color(0xFFDC2626);
        if (prioritas == 'MEDIUM') pinColor = const Color(0xFFD97706);

        markers.add(
          Marker(
            point: LatLng(lat, lng),
            width: 44,
            height: 44,
            child: GestureDetector(
              onTap: () {
                setState(() {
                  _selectedFinding = item;
                });
              },
              child: Container(
                decoration: BoxDecoration(
                  color: pinColor,
                  shape: BoxShape.circle,
                  border: Border.all(color: Colors.white, width: 2),
                  boxShadow: [
                    BoxShadow(color: Colors.black.withOpacity(0.3), blurRadius: 6, offset: const Offset(0, 2))
                  ],
                ),
                child: const Icon(Icons.flash_on_rounded, color: Colors.white, size: 22),
              ),
            ),
          ),
        );
      }
    }

    return Scaffold(
      backgroundColor: const Color(0xFFF4F6F9),
      appBar: AppBar(
        backgroundColor: const Color(0xFF004D4F),
        title: const Text('Temuan Terdekat (500m)', style: TextStyle(color: Colors.white, fontWeight: FontWeight.bold)),
        iconTheme: const IconThemeData(color: Colors.white),
        actions: [
          IconButton(
            icon: const Icon(Icons.refresh_rounded),
            onPressed: _getUserLocationAndQuery,
            tooltip: 'Refresh GPS & Data',
          )
        ],
      ),
      body: SafeArea(
        child: Stack(
          children: [
            // OpenStreetMap View
            FlutterMap(
              mapController: _mapController,
              options: MapOptions(
                initialCenter: _userLocation ?? const LatLng(-7.4478, 112.7183), // Default Sidoarjo
                initialZoom: 15.0,
              ),
              children: [
                TileLayer(
                  urlTemplate: 'https://tile.openstreetmap.org/{z}/{x}/{y}.png',
                  userAgentPackageName: 'com.sidaktejo.mobile',
                ),
                MarkerLayer(markers: markers),
              ],
            ),

            // Loading indicator overlay
            if (_isLoading)
              Container(
                color: Colors.black25,
                child: const Center(
                  child: Card(
                    child: Padding(
                      padding: EdgeInsets.symmetric(horizontal: 20, vertical: 16),
                      child: Row(
                        mainAxisSize: MainAxisSize.min,
                        children: [
                          CircularProgressIndicator(color: Color(0xFF0082C8)),
                          SizedBox(width: 16),
                          Text('Mencari temuan terdekat...', style: TextStyle(fontWeight: FontWeight.w600)),
                        ],
                      ),
                    ),
                  ),
                ),
              ),

            // Top Status Bar Radar Info
            Positioned(
              top: 16,
              left: 16,
              child: Container(
                padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 8),
                decoration: BoxDecoration(
                  color: Colors.white,
                  borderRadius: BorderRadius.circular(20),
                  boxShadow: [
                    BoxShadow(color: Colors.black.withOpacity(0.1), blurRadius: 8, offset: const Offset(0, 2))
                  ],
                ),
                child: Row(
                  children: [
                    const Icon(Icons.radar_rounded, color: Color(0xFFD97706), size: 18),
                    const SizedBox(width: 8),
                    Text(
                      'Radius: $_searchRadius Meter',
                      style: const TextStyle(color: Color(0xFF1E293B), fontSize: 13, fontWeight: FontWeight.bold),
                    ),
                  ],
                ),
              ),
            ),

            // Bottom Selected Marker Card Popup
            if (_selectedFinding != null)
              Positioned(
                bottom: 20,
                left: 16,
                right: 16,
                child: Card(
                  elevation: 6,
                  color: Colors.white,
                  shape: RoundedRectangleBorder(
                    borderRadius: BorderRadius.circular(16),
                    side: const BorderSide(color: Color(0xFF0082C8), width: 1.5),
                  ),
                  child: Padding(
                    padding: const EdgeInsets.all(16),
                    child: Column(
                      mainAxisSize: MainAxisSize.min,
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Row(
                          mainAxisAlignment: MainAxisAlignment.spaceBetween,
                          children: [
                            Container(
                              padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 4),
                              decoration: BoxDecoration(
                                color: const Color(0xFFFEE2E2),
                                borderRadius: BorderRadius.circular(6),
                              ),
                              child: Text(
                                _selectedFinding['nomor_temuan'] ?? 'N/A',
                                style: const TextStyle(color: Color(0xFFDC2626), fontSize: 11, fontWeight: FontWeight.bold),
                              ),
                            ),
                            Text(
                              _selectedFinding['distance_text'] ?? '',
                              style: const TextStyle(color: Color(0xFFD97706), fontSize: 13, fontWeight: FontWeight.bold),
                            ),
                          ],
                        ),
                        const SizedBox(height: 8),
                        Text(
                          _selectedFinding['nama_penyulang'] ?? 'Penyulang',
                          style: const TextStyle(color: Color(0xFF1E293B), fontSize: 16, fontWeight: FontWeight.bold),
                        ),
                        const SizedBox(height: 4),
                        Text(
                          'Section: ${_selectedFinding['nama_section'] ?? '-'}',
                          style: const TextStyle(color: Color(0xFF64748B), fontSize: 13),
                        ),
                        const SizedBox(height: 4),
                        Text(
                          'NOGA: ${_selectedFinding['noga'] ?? 'Tidak ada'}',
                          style: const TextStyle(color: Color(0xFF64748B), fontSize: 13),
                        ),
                        const SizedBox(height: 12),
                        Row(
                          mainAxisAlignment: MainAxisAlignment.spaceBetween,
                          children: [
                            Row(
                              children: [
                                const Icon(Icons.engineering, color: Color(0xFF0082C8), size: 16),
                                const SizedBox(width: 6),
                                Text(
                                  _selectedFinding['pelaksana'] ?? 'PDKB',
                                  style: const TextStyle(color: Color(0xFF0082C8), fontSize: 13, fontWeight: FontWeight.bold),
                                ),
                              ],
                            ),
                            Row(
                              children: [
                                const Icon(Icons.warning_amber, color: Color(0xFFEA580C), size: 16),
                                const SizedBox(width: 6),
                                Text(
                                  _selectedFinding['prioritas'] ?? 'MEDIUM',
                                  style: const TextStyle(color: Color(0xFFEA580C), fontSize: 13, fontWeight: FontWeight.bold),
                                ),
                              ],
                            ),
                          ],
                        )
                      ],
                    ),
                  ),
                ),
              ),
          ],
        ),
      ),
    );
  }
}
