import 'dart:io';
import 'package:flutter/material.dart';
import 'package:geolocator/geolocator.dart';
import 'package:image_picker/image_picker.dart';
import 'package:intl/intl.dart';
import '../services/api_service.dart';
import '../widgets/voice_input_field.dart';

class InputTemuanScreen extends StatefulWidget {
  const InputTemuanScreen({super.key});

  @override
  State<InputTemuanScreen> createState() => _InputTemuanScreenState();
}

class _InputTemuanScreenState extends State<InputTemuanScreen> {
  final _formKey = GlobalKey<FormState>();

  // Cascading Drops
  List<dynamic> _ulps = [];
  List<dynamic> _penyulangs = [];
  List<dynamic> _sections = [];

  int? _selectedUlpId;
  int? _selectedPenyulangId;
  int? _selectedSectionId;

  // Controllers
  final _nogaController = TextEditingController();
  final _konduktorController = TextEditingController();
  final _materialController = TextEditingController();
  final _detailController = TextEditingController();
  final _alamatController = TextEditingController();
  final _latitudeController = TextEditingController();
  final _longitudeController = TextEditingController();
  final _tanggalController = TextEditingController();

  // Strings
  String? _selectedJenisTemuan;
  String? _selectedPelaksana;
  String? _selectedPrioritas;
  String? _selectedPotensi;

  // Photos
  final List<File> _selectedPhotos = [];
  final ImagePicker _picker = ImagePicker();

  bool _isLoadingDropdowns = false;
  bool _isGettingLocation = false;
  bool _isSubmitting = false;

  @override
  void initState() {
    super.initState();
    _tanggalController.text = DateFormat('yyyy-MM-dd').format(DateTime.now());
    _loadInitialDropdowns();
  }

  void _loadInitialDropdowns() async {
    setState(() => _isLoadingDropdowns = true);
    final ulpData = await ApiService.getUlps();
    setState(() {
      _ulps = ulpData;
      _isLoadingDropdowns = false;
    });
  }

  void _onUlpChanged(int? val) async {
    setState(() {
      _selectedUlpId = val;
      _selectedPenyulangId = null;
      _selectedSectionId = null;
      _penyulangs = [];
      _sections = [];
    });
    if (val != null) {
      final penyulangData = await ApiService.getPenyulangs(val);
      setState(() => _penyulangs = penyulangData);
    }
  }

  void _onPenyulangChanged(int? val) async {
    setState(() {
      _selectedPenyulangId = val;
      _selectedSectionId = null;
      _sections = [];
    });
    if (val != null) {
      final sectionData = await ApiService.getSections(val);
      setState(() => _sections = sectionData);
    }
  }

  void _takePhoto(ImageSource source) async {
    if (_selectedPhotos.length >= 10) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Maksimal foto yang diunggah adalah 10 foto.')),
      );
      return;
    }
    try {
      final XFile? pickedFile = await _picker.pickImage(
        source: source,
        imageQuality: 70,
        maxWidth: 1280,
        maxHeight: 1280,
      );

      if (pickedFile != null) {
        setState(() {
          _selectedPhotos.add(File(pickedFile.path));
        });
      }
    } catch (e) {
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text('Gagal mengambil gambar: $e')),
      );
    }
  }

  void _getCurrentLocation() async {
    setState(() => _isGettingLocation = true);
    try {
      bool serviceEnabled = await Geolocator.isLocationServiceEnabled();
      if (!serviceEnabled) {
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(content: Text('GPS / Layanan Lokasi belum diaktifkan di HP Anda.')),
        );
        setState(() => _isGettingLocation = false);
        return;
      }

      LocationPermission permission = await Geolocator.checkPermission();
      if (permission == LocationPermission.denied) {
        permission = await Geolocator.requestPermission();
        if (permission == LocationPermission.denied) {
          ScaffoldMessenger.of(context).showSnackBar(
            const SnackBar(content: Text('Izin akses lokasi ditolak oleh pengguna.')),
          );
          setState(() => _isGettingLocation = false);
          return;
        }
      }

      Position position = await Geolocator.getCurrentPosition(
        desiredAccuracy: LocationAccuracy.high,
      );

      setState(() {
        _latitudeController.text = position.latitude.toString();
        _longitudeController.text = position.longitude.toString();
        _isGettingLocation = false;
      });
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Berhasil mengambil koordinat lokasi GPS Anda.')),
      );
    } catch (e) {
      setState(() => _isGettingLocation = false);
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text('Gagal mengambil lokasi: $e')),
      );
    }
  }

  void _handleSubmit() async {
    if (!_formKey.currentState!.validate()) return;
    if (_selectedUlpId == null || _selectedPenyulangId == null || _selectedSectionId == null) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Harap pilih ULP, Penyulang, dan Section secara lengkap.')),
      );
      return;
    }
    if (_selectedPhotos.isEmpty) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Minimal unggah 1 foto eviden di lokasi.')),
      );
      return;
    }

    setState(() => _isSubmitting = true);

    Map<String, String> fields = {
      'ulp_id': _selectedUlpId.toString(),
      'penyulang_id': _selectedPenyulangId.toString(),
      'section_id': _selectedSectionId.toString(),
      'noga': _nogaController.text.trim(),
      'jenis_temuan': _selectedJenisTemuan ?? '',
      'pelaksana': _selectedPelaksana ?? '',
      'prioritas': _selectedPrioritas ?? '',
      'potensi_gangguan': _selectedPotensi ?? '',
      'nama_konduktor': _konduktorController.text.trim(),
      'material_dibutuhkan': _materialController.text.trim(),
      'detail_temuan': _detailController.text.trim(),
      'alamat_lokasi': _alamatController.text.trim(),
      'latitude': _latitudeController.text.trim(),
      'longitude': _longitudeController.text.trim(),
      'tanggal_temuan': _tanggalController.text.trim(),
    };

    final result = await ApiService.createTemuan(fields, _selectedPhotos);
    setState(() => _isSubmitting = false);

    if (result['success']) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Temuan baru berhasil dikirim dan tersimpan!')),
      );
      Navigator.pop(context);
    } else {
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text('Gagal menyimpan: ${result['message']}')),
      );
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: const Color(0xFFF4F6F9),
      appBar: AppBar(
        backgroundColor: const Color(0xFF004D4F),
        title: const Text('Input Temuan Lokasi', style: TextStyle(color: Colors.white, fontWeight: FontWeight.bold)),
        iconTheme: const IconThemeData(color: Colors.white),
      ),
      body: SafeArea(
        child: _isLoadingDropdowns
            ? const Center(child: CircularProgressIndicator())
            : SingleChildScrollView(
                padding: const EdgeInsets.all(16.0),
                child: Form(
                  key: _formKey,
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.stretch,
                    children: [
                      // Header Card Info
                      Card(
                        elevation: 0,
                        color: Colors.white,
                        shape: RoundedRectangleBorder(
                          borderRadius: BorderRadius.circular(12),
                          side: BorderSide(color: Colors.grey.shade200),
                        ),
                        child: Padding(
                          padding: const EdgeInsets.all(16.0),
                          child: Column(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: [
                              // Dropdown: ULP
                              _buildLabel('Unit Layanan Pelanggan (ULP) *'),
                              _buildDropdown<int>(
                                value: _selectedUlpId,
                                items: _ulps.map((e) => DropdownMenuItem<int>(
                                  value: e['id'],
                                  child: Text(e['nama_ulp']),
                                )).toList(),
                                onChanged: _onUlpChanged,
                                hint: 'Pilih ULP',
                              ),
                              const SizedBox(height: 14),

                              // Dropdown: Penyulang
                              _buildLabel('Penyulang *'),
                              _buildDropdown<int>(
                                value: _selectedPenyulangId,
                                items: _penyulangs.map((e) => DropdownMenuItem<int>(
                                  value: e['id'],
                                  child: Text(e['nama_penyulang']),
                                )).toList(),
                                onChanged: _onPenyulangChanged,
                                hint: _selectedUlpId == null ? 'Pilih ULP Terlebih Dahulu' : 'Pilih Penyulang',
                              ),
                              const SizedBox(height: 14),

                              // Dropdown: Section
                              _buildLabel('Section *'),
                              _buildDropdown<int>(
                                value: _selectedSectionId,
                                items: _sections.map((e) => DropdownMenuItem<int>(
                                  value: e['id'],
                                  child: Text(e['nama_section']),
                                )).toList(),
                                onChanged: (v) => setState(() => _selectedSectionId = v),
                                hint: _selectedPenyulangId == null ? 'Pilih Penyulang Terlebih Dahulu' : 'Pilih Section',
                              ),
                              const SizedBox(height: 14),

                              // Voice Field: NOGA
                              _buildLabel('Nomor Gardu (NOGA)'),
                              VoiceInputField(
                                controller: _nogaController,
                                labelText: 'Nomor Gardu (NOGA)',
                                hintText: 'Contoh: G.123 (bisa diketik / sebutkan)',
                                prefixIcon: Icons.confirmation_number_outlined,
                              ),
                              const SizedBox(height: 14),

                              // Dropdown: Jenis Temuan
                              _buildLabel('Jenis Temuan *'),
                              _buildDropdown<String>(
                                value: _selectedJenisTemuan,
                                items: const [
                                  DropdownMenuItem(value: 'KONSTRUKSI', child: Text('KONSTRUKSI')),
                                  DropdownMenuItem(value: 'HOTSPOT', child: Text('HOTSPOT')),
                                  DropdownMenuItem(value: 'ROW', child: Text('ROW')),
                                ],
                                onChanged: (v) => setState(() => _selectedJenisTemuan = v),
                                hint: 'Pilih Jenis Temuan',
                              ),
                              const SizedBox(height: 14),

                              // Dropdown: Pelaksana
                              _buildLabel('Pelaksana Pekerjaan *'),
                              _buildDropdown<String>(
                                value: _selectedPelaksana,
                                items: const [
                                  DropdownMenuItem(value: 'PDKB', child: Text('PDKB')),
                                  DropdownMenuItem(value: 'HAR GARDU', child: Text('HAR GARDU')),
                                  DropdownMenuItem(value: 'HAR GTT', child: Text('HAR GTT')),
                                  DropdownMenuItem(value: 'HAR KONSTRUKSI', child: Text('HAR KONSTRUKSI')),
                                  DropdownMenuItem(value: 'HAR ROW', child: Text('HAR ROW')),
                                  DropdownMenuItem(value: 'HAR CRANE', child: Text('HAR CRANE')),
                                ],
                                onChanged: (v) => setState(() => _selectedPelaksana = v),
                                hint: 'Pilih Pelaksana',
                              ),
                              const SizedBox(height: 14),

                              // Dropdown: Prioritas
                              _buildLabel('Prioritas SLA *'),
                              _buildDropdown<String>(
                                value: _selectedPrioritas,
                                items: const [
                                  DropdownMenuItem(value: 'EMERGENCY', child: Text('EMERGENCY (1 Hari)')),
                                  DropdownMenuItem(value: 'HIGH', child: Text('HIGH (3 Hari)')),
                                  DropdownMenuItem(value: 'MEDIUM', child: Text('MEDIUM (7 Hari)')),
                                ],
                                onChanged: (v) => setState(() => _selectedPrioritas = v),
                                hint: 'Pilih Prioritas',
                              ),
                              const SizedBox(height: 14),

                              // Dropdown: Potensi Gangguan
                              _buildLabel('Potensi Gangguan *'),
                              _buildDropdown<String>(
                                value: _selectedPotensi,
                                items: const [
                                  DropdownMenuItem(value: 'DGR', child: Text('DGR')),
                                  DropdownMenuItem(value: 'OCR', child: Text('OCR')),
                                  DropdownMenuItem(value: 'OCRDGR', child: Text('OCR + DGR')),
                                ],
                                onChanged: (v) => setState(() => _selectedPotensi = v),
                                hint: 'Pilih Potensi Gangguan',
                              ),
                              const SizedBox(height: 14),

                              // Voice Field: Nama Konduktor
                              _buildLabel('Nama Konduktor *'),
                              VoiceInputField(
                                controller: _konduktorController,
                                labelText: 'Nama Konduktor',
                                hintText: 'Contoh: A3CS 150mm',
                                prefixIcon: Icons.electrical_services_rounded,
                                validator: (v) => v!.trim().isEmpty ? 'Nama konduktor wajib diisi' : null,
                              ),
                              const SizedBox(height: 14),

                              // Voice Field: Material
                              _buildLabel('Material yang Dibutuhkan *'),
                              VoiceInputField(
                                controller: _materialController,
                                labelText: 'Material Dibutuhkan',
                                hintText: 'Sebutkan material (misal: Isolator 2 buah)...',
                                maxLines: 2,
                                prefixIcon: Icons.build_circle_outlined,
                                validator: (v) => v!.trim().isEmpty ? 'Material wajib diisi' : null,
                              ),
                              const SizedBox(height: 14),

                              // Voice Field: Detail Temuan
                              _buildLabel('Detail Temuan Jaringan *'),
                              VoiceInputField(
                                controller: _detailController,
                                labelText: 'Detail Temuan',
                                hintText: 'Jelaskan kondisi temuan di lapangan...',
                                maxLines: 3,
                                prefixIcon: Icons.report_problem_outlined,
                                validator: (v) => v!.trim().isEmpty ? 'Detail temuan wajib diisi' : null,
                              ),
                              const SizedBox(height: 14),

                              // Voice Field: Alamat
                              _buildLabel('Alamat Lokasi Temuan *'),
                              VoiceInputField(
                                controller: _alamatController,
                                labelText: 'Alamat Lokasi',
                                hintText: 'Sebutkan jalan, desa, kecamatan...',
                                maxLines: 2,
                                prefixIcon: Icons.location_on_outlined,
                                validator: (v) => v!.trim().isEmpty ? 'Alamat lokasi wajib diisi' : null,
                              ),
                            ],
                          ),
                        ),
                      ),
                      const SizedBox(height: 16),

                      // Location GPS Section Card
                      Card(
                        elevation: 0,
                        color: Colors.white,
                        shape: RoundedRectangleBorder(
                          borderRadius: BorderRadius.circular(12),
                          side: BorderSide(color: Colors.grey.shade200),
                        ),
                        child: Padding(
                          padding: const EdgeInsets.all(16.0),
                          child: Column(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: [
                              const Text(
                                'Pencatatan Geolocation GPS',
                                style: TextStyle(color: Color(0xFF1E293B), fontSize: 15, fontWeight: FontWeight.bold),
                              ),
                              const SizedBox(height: 12),
                              Row(
                                children: [
                                  Expanded(
                                    child: TextFormField(
                                      controller: _latitudeController,
                                      readOnly: true,
                                      style: const TextStyle(color: Color(0xFF1E293B), fontSize: 13),
                                      decoration: InputDecoration(
                                        labelText: 'Latitude',
                                        filled: true,
                                        fillColor: Colors.grey.shade100,
                                        border: OutlineInputBorder(borderRadius: BorderRadius.circular(8)),
                                      ),
                                    ),
                                  ),
                                  const SizedBox(width: 12),
                                  Expanded(
                                    child: TextFormField(
                                      controller: _longitudeController,
                                      readOnly: true,
                                      style: const TextStyle(color: Color(0xFF1E293B), fontSize: 13),
                                      decoration: InputDecoration(
                                        labelText: 'Longitude',
                                        filled: true,
                                        fillColor: Colors.grey.shade100,
                                        border: OutlineInputBorder(borderRadius: BorderRadius.circular(8)),
                                      ),
                                    ),
                                  ),
                                ],
                              ),
                              const SizedBox(height: 12),
                              SizedBox(
                                width: double.infinity,
                                child: ElevatedButton.icon(
                                  onPressed: _isGettingLocation ? null : _getCurrentLocation,
                                  icon: _isGettingLocation
                                      ? const SizedBox(
                                          height: 16,
                                          width: 16,
                                          child: CircularProgressIndicator(color: Colors.white, strokeWidth: 2),
                                        )
                                      : const Icon(Icons.my_location_rounded),
                                  label: Text(_isGettingLocation ? 'Mendapatkan GPS...' : 'Ambil Geolocation GPS Saya'),
                                  style: ElevatedButton.styleFrom(
                                    backgroundColor: const Color(0xFF0082C8),
                                    foregroundColor: Colors.white,
                                    padding: const EdgeInsets.symmetric(vertical: 12),
                                    shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(8)),
                                  ),
                                ),
                              ),
                            ],
                          ),
                        ),
                      ),
                      const SizedBox(height: 16),

                      // Photos Eviden Section Card
                      Card(
                        elevation: 0,
                        color: Colors.white,
                        shape: RoundedRectangleBorder(
                          borderRadius: BorderRadius.circular(12),
                          side: BorderSide(color: Colors.grey.shade200),
                        ),
                        child: Padding(
                          padding: const EdgeInsets.all(16.0),
                          child: Column(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: [
                              const Text(
                                'Unggah Foto Eviden (Min. 1, Max. 10)',
                                style: TextStyle(color: Color(0xFF1E293B), fontSize: 15, fontWeight: FontWeight.bold),
                              ),
                              const SizedBox(height: 12),
                              Row(
                                children: [
                                  Expanded(
                                    child: OutlinedButton.icon(
                                      onPressed: () => _takePhoto(ImageSource.camera),
                                      icon: const Icon(Icons.camera_alt_outlined),
                                      label: const Text('Kamera (Foto)'),
                                      style: OutlinedButton.styleFrom(
                                        foregroundColor: const Color(0xFF0082C8),
                                        side: const BorderSide(color: Color(0xFF0082C8)),
                                        padding: const EdgeInsets.symmetric(vertical: 12),
                                      ),
                                    ),
                                  ),
                                  const SizedBox(width: 12),
                                  Expanded(
                                    child: OutlinedButton.icon(
                                      onPressed: () => _takePhoto(ImageSource.gallery),
                                      icon: const Icon(Icons.photo_library_outlined),
                                      label: const Text('Galeri HP'),
                                      style: OutlinedButton.styleFrom(
                                        foregroundColor: const Color(0xFF0082C8),
                                        side: const BorderSide(color: Color(0xFF0082C8)),
                                        padding: const EdgeInsets.symmetric(vertical: 12),
                                      ),
                                    ),
                                  ),
                                ],
                              ),
                              const SizedBox(height: 12),

                              if (_selectedPhotos.isNotEmpty)
                                SizedBox(
                                  height: 90,
                                  child: ListView.builder(
                                    scrollDirection: Axis.horizontal,
                                    itemCount: _selectedPhotos.length,
                                    itemBuilder: (context, i) {
                                      return Stack(
                                        children: [
                                          Container(
                                            margin: const EdgeInsets.only(right: 12),
                                            width: 90,
                                            height: 90,
                                            decoration: BoxDecoration(
                                              borderRadius: BorderRadius.circular(8),
                                              image: DecorationImage(
                                                image: FileImage(_selectedPhotos[i]),
                                                fit: BoxFit.cover,
                                              ),
                                            ),
                                          ),
                                          Positioned(
                                            top: 4,
                                            right: 16,
                                            child: GestureDetector(
                                              onTap: () {
                                                setState(() {
                                                  _selectedPhotos.removeAt(i);
                                                });
                                              },
                                              child: Container(
                                                padding: const EdgeInsets.all(4),
                                                decoration: const BoxDecoration(
                                                  color: Colors.redAccent,
                                                  shape: BoxShape.circle,
                                                ),
                                                child: const Icon(Icons.close, color: Colors.white, size: 14),
                                              ),
                                            ),
                                          )
                                        ],
                                      );
                                    },
                                  ),
                                ),
                            ],
                          ),
                        ),
                      ),
                      const SizedBox(height: 24),

                      // Submit Button
                      ElevatedButton(
                        onPressed: _isSubmitting ? null : _handleSubmit,
                        style: ElevatedButton.styleFrom(
                          backgroundColor: const Color(0xFF059669),
                          foregroundColor: Colors.white,
                          padding: const EdgeInsets.symmetric(vertical: 16),
                          shape: RoundedRectangleBorder(
                            borderRadius: BorderRadius.circular(12),
                          ),
                        ),
                        child: _isSubmitting
                            ? const SizedBox(
                                height: 24,
                                width: 24,
                                child: CircularProgressIndicator(color: Colors.white, strokeWidth: 2),
                              )
                            : const Text(
                                'LAPORKAN TEMUAN',
                                style: TextStyle(fontSize: 16, fontWeight: FontWeight.bold),
                              ),
                      ),
                      const SizedBox(height: 24),
                    ],
                  ),
                ),
              ),
      ),
    );
  }

  Widget _buildLabel(String text) {
    return Padding(
      padding: const EdgeInsets.only(bottom: 6.0),
      child: Text(text, style: const TextStyle(color: Color(0xFF475569), fontSize: 13, fontWeight: FontWeight.w600)),
    );
  }

  Widget _buildDropdown<T>({
    required T? value,
    required List<DropdownMenuItem<T>> items,
    required ValueChanged<T?> onChanged,
    required String hint,
  }) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 12),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(10),
        border: Border.all(color: Colors.grey.shade300),
      ),
      child: DropdownButtonHideUnderline(
        child: DropdownButton<T>(
          dropdownColor: Colors.white,
          value: value,
          items: items,
          onChanged: onChanged,
          isExpanded: true,
          hint: Text(hint, style: TextStyle(color: Colors.grey.shade400, fontSize: 14)),
          style: const TextStyle(color: Color(0xFF1E293B), fontSize: 14),
          icon: const Icon(Icons.arrow_drop_down, color: Color(0xFF0082C8)),
        ),
      ),
    );
  }
}
