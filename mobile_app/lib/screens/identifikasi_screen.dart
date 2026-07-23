import 'package:flutter/material.dart';
import '../services/api_service.dart';
import '../widgets/voice_input_field.dart';

class IdentifikasiScreen extends StatefulWidget {
  const IdentifikasiScreen({super.key});

  @override
  State<IdentifikasiScreen> createState() => _IdentifikasiScreenState();
}

class _IdentifikasiScreenState extends State<IdentifikasiScreen> {
  List<dynamic> _allTemuanList = [];
  List<dynamic> _filteredTemuanList = [];
  bool _isLoading = true;

  String? _selectedStatus;
  String? _selectedPrioritas;
  final TextEditingController _searchController = TextEditingController();

  @override
  void initState() {
    super.initState();
    _fetchFindings();
  }

  void _fetchFindings() async {
    setState(() => _isLoading = true);
    final list = await ApiService.getTemuan(
      status: _selectedStatus,
      prioritas: _selectedPrioritas,
    );
    setState(() {
      _allTemuanList = list;
      _applySearchFilter();
      _isLoading = false;
    });
  }

  void _applySearchFilter() {
    final query = _searchController.text.toLowerCase().trim();
    if (query.isEmpty) {
      _filteredTemuanList = List.from(_allTemuanList);
    } else {
      _filteredTemuanList = _allTemuanList.where((item) {
        final penyulang = (item['nama_penyulang'] ?? '').toString().toLowerCase();
        final section = (item['nama_section'] ?? '').toString().toLowerCase();
        final noga = (item['noga'] ?? '').toString().toLowerCase();
        final detail = (item['detail_temuan'] ?? '').toString().toLowerCase();
        final nomor = (item['nomor_temuan'] ?? '').toString().toLowerCase();

        return penyulang.contains(query) ||
            section.contains(query) ||
            noga.contains(query) ||
            detail.contains(query) ||
            nomor.contains(query);
      }).toList();
    }
  }

  void _showDetailModal(dynamic item) {
    showModalBottomSheet(
      context: context,
      backgroundColor: Colors.white,
      shape: const RoundedRectangleBorder(
        borderRadius: BorderRadius.vertical(top: Radius.circular(20)),
      ),
      builder: (context) {
        final status = item['status'] ?? 'BELUM';
        final isSelesai = status == 'SELESAI';
        return SingleChildScrollView(
          padding: const EdgeInsets.all(20.0),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              // Header title
              Row(
                mainAxisAlignment: MainAxisAlignment.spaceBetween,
                children: [
                  Text(
                    item['nomor_temuan'] ?? 'Detail Temuan',
                    style: const TextStyle(color: Color(0xFF0082C8), fontSize: 18, fontWeight: FontWeight.bold),
                  ),
                  Container(
                    padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
                    decoration: BoxDecoration(
                      color: isSelesai ? const Color(0xFFD1FAE5) : const Color(0xFFFEF3C7),
                      borderRadius: BorderRadius.circular(6),
                    ),
                    child: Text(
                      status,
                      style: TextStyle(
                        color: isSelesai ? const Color(0xFF059669) : const Color(0xFFD97706),
                        fontSize: 11,
                        fontWeight: FontWeight.bold,
                      ),
                    ),
                  )
                ],
              ),
              const Divider(color: Color(0xFFE2E8F0), height: 24),

              _buildDetailRow('ULP', item['nama_ulp'] ?? '-'),
              _buildDetailRow('Penyulang', item['nama_penyulang'] ?? '-'),
              _buildDetailRow('Section', item['nama_section'] ?? '-'),
              _buildDetailRow('NOGA (Nomor Gardu)', item['noga'] ?? 'Tidak ada'),
              _buildDetailRow('Jenis Temuan', item['jenis_temuan'] ?? '-'),
              _buildDetailRow('Pelaksana Pekerjaan', item['pelaksana'] ?? '-'),
              _buildDetailRow('Prioritas SLA', item['prioritas'] ?? '-'),
              _buildDetailRow('Potensi Gangguan', item['potensi_gangguan'] ?? '-'),
              _buildDetailRow('Tanggal Temuan', item['tanggal_temuan'] ?? '-'),

              const SizedBox(height: 12),
              const Text('Keterangan Detail:', style: TextStyle(color: Color(0xFF475569), fontSize: 13, fontWeight: FontWeight.bold)),
              const SizedBox(height: 4),
              Text(
                item['detail_temuan'] ?? '-',
                style: const TextStyle(color: Color(0xFF1E293B), fontSize: 14),
              ),
              const SizedBox(height: 24),
            ],
          ),
        );
      },
    );
  }

  Widget _buildDetailRow(String label, String val) {
    return Padding(
      padding: const EdgeInsets.only(bottom: 10.0),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          SizedBox(
            width: 130,
            child: Text(
              label,
              style: const TextStyle(color: Color(0xFF64748B), fontSize: 13),
            ),
          ),
          Expanded(
            child: Text(
              ': $val',
              style: const TextStyle(color: Color(0xFF1E293B), fontSize: 13, fontWeight: FontWeight.w600),
            ),
          ),
        ],
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: const Color(0xFFF4F6F9),
      appBar: AppBar(
        backgroundColor: const Color(0xFF004D4F),
        title: const Text('Identifikasi Temuan', style: TextStyle(color: Colors.white, fontWeight: FontWeight.bold)),
        iconTheme: const IconThemeData(color: Colors.white),
      ),
      body: SafeArea(
        child: Column(
          children: [
            // Voice Search & Filter Section Bar
            Container(
              padding: const EdgeInsets.all(12),
              color: Colors.white,
              child: Column(
                children: [
                  // Voice Search Input Field
                  VoiceInputField(
                    controller: _searchController,
                    labelText: 'Cari Temuan (Penyulang / Gardu / Detail)',
                    hintText: 'Cari atau gunakan suara...',
                    prefixIcon: Icons.search_rounded,
                    onChanged: (_) {
                      setState(() {
                        _applySearchFilter();
                      });
                    },
                  ),
                  const SizedBox(height: 10),
                  Row(
                    children: [
                      // Filter Status Dropdown
                      Expanded(
                        child: Container(
                          padding: const EdgeInsets.symmetric(horizontal: 10),
                          decoration: BoxDecoration(
                            color: const Color(0xFFF8FAFC),
                            borderRadius: BorderRadius.circular(8),
                            border: Border.all(color: Colors.grey.shade300),
                          ),
                          child: DropdownButtonHideUnderline(
                            child: DropdownButton<String>(
                              value: _selectedStatus,
                              hint: const Text('Status', style: TextStyle(color: Colors.grey, fontSize: 12)),
                              style: const TextStyle(color: Color(0xFF1E293B), fontSize: 13),
                              items: const [
                                DropdownMenuItem(value: null, child: Text('Semua Status')),
                                DropdownMenuItem(value: 'BELUM', child: Text('BELUM')),
                                DropdownMenuItem(value: 'PROSES', child: Text('PROSES')),
                                DropdownMenuItem(value: 'SELESAI', child: Text('SELESAI')),
                              ],
                              onChanged: (v) {
                                setState(() => _selectedStatus = v);
                                _fetchFindings();
                              },
                            ),
                          ),
                        ),
                      ),
                      const SizedBox(width: 10),

                      // Filter Prioritas Dropdown
                      Expanded(
                        child: Container(
                          padding: const EdgeInsets.symmetric(horizontal: 10),
                          decoration: BoxDecoration(
                            color: const Color(0xFFF8FAFC),
                            borderRadius: BorderRadius.circular(8),
                            border: Border.all(color: Colors.grey.shade300),
                          ),
                          child: DropdownButtonHideUnderline(
                            child: DropdownButton<String>(
                              value: _selectedPrioritas,
                              hint: const Text('Prioritas', style: TextStyle(color: Colors.grey, fontSize: 12)),
                              style: const TextStyle(color: Color(0xFF1E293B), fontSize: 13),
                              items: const [
                                DropdownMenuItem(value: null, child: Text('Semua SLA')),
                                DropdownMenuItem(value: 'EMERGENCY', child: Text('EMERGENCY')),
                                DropdownMenuItem(value: 'HIGH', child: Text('HIGH')),
                                DropdownMenuItem(value: 'MEDIUM', child: Text('MEDIUM')),
                              ],
                              onChanged: (v) {
                                setState(() => _selectedPrioritas = v);
                                _fetchFindings();
                              },
                            ),
                          ),
                        ),
                      ),
                    ],
                  ),
                ],
              ),
            ),

            // Findings List View
            Expanded(
              child: _isLoading
                  ? const Center(child: CircularProgressIndicator(color: Color(0xFF0082C8)))
                  : _filteredTemuanList.isEmpty
                      ? const Center(
                          child: Text('Tidak ada data temuan yang ditemukan.', style: TextStyle(color: Color(0xFF64748B))),
                        )
                      : ListView.builder(
                          padding: const EdgeInsets.all(12),
                          itemCount: _filteredTemuanList.length,
                          itemBuilder: (context, index) {
                            final item = _filteredTemuanList[index];
                            final status = item['status'] ?? 'BELUM';
                            final prioritas = item['prioritas'] ?? 'MEDIUM';
                            final isSelesai = status == 'SELESAI';

                            Color prioritasColor = const Color(0xFFEA580C);
                            if (prioritas == 'EMERGENCY') prioritasColor = const Color(0xFFDC2626);
                            if (prioritas == 'MEDIUM') prioritasColor = const Color(0xFFD97706);

                            return Card(
                              elevation: 0,
                              margin: const EdgeInsets.only(bottom: 10),
                              color: Colors.white,
                              shape: RoundedRectangleBorder(
                                borderRadius: BorderRadius.circular(12),
                                side: BorderSide(color: Colors.grey.shade200),
                              ),
                              child: ListTile(
                                contentPadding: const EdgeInsets.symmetric(horizontal: 16, vertical: 8),
                                title: Row(
                                  mainAxisAlignment: MainAxisAlignment.spaceBetween,
                                  children: [
                                    Text(
                                      item['nomor_temuan'] ?? 'N/A',
                                      style: const TextStyle(color: Color(0xFF0082C8), fontWeight: FontWeight.bold, fontSize: 14),
                                    ),
                                    Container(
                                      padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 2),
                                      decoration: BoxDecoration(
                                        color: isSelesai ? const Color(0xFFD1FAE5) : const Color(0xFFFEF3C7),
                                        borderRadius: BorderRadius.circular(4),
                                      ),
                                      child: Text(
                                        status,
                                        style: TextStyle(
                                          color: isSelesai ? const Color(0xFF059669) : const Color(0xFFD97706),
                                          fontSize: 10,
                                          fontWeight: FontWeight.bold,
                                        ),
                                      ),
                                    ),
                                  ],
                                ),
                                subtitle: Column(
                                  crossAxisAlignment: CrossAxisAlignment.start,
                                  children: [
                                    const SizedBox(height: 6),
                                    Text(
                                      item['nama_penyulang'] ?? 'Penyulang',
                                      style: const TextStyle(color: Color(0xFF1E293B), fontWeight: FontWeight.w600, fontSize: 14),
                                    ),
                                    const SizedBox(height: 4),
                                    Text(
                                      'Section: ${item['nama_section'] ?? '-'}',
                                      style: const TextStyle(color: Color(0xFF64748B), fontSize: 12),
                                    ),
                                    const SizedBox(height: 4),
                                    Row(
                                      children: [
                                        Icon(Icons.warning_amber_rounded, size: 14, color: prioritasColor),
                                        const SizedBox(width: 4),
                                        Text(
                                          'SLA: $prioritas',
                                          style: TextStyle(color: prioritasColor, fontSize: 12, fontWeight: FontWeight.bold),
                                        ),
                                      ],
                                    )
                                  ],
                                ),
                                trailing: const Icon(Icons.chevron_right_rounded, size: 20, color: Color(0xFF0082C8)),
                                onTap: () => _showDetailModal(item),
                              ),
                            );
                          },
                        ),
            ),
          ],
        ),
      ),
    );
  }
}
