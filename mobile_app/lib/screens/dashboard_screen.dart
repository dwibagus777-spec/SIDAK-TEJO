import 'package:flutter/material.dart';
import 'package:shared_preferences/shared_preferences.dart';
import 'package:google_fonts/google_fonts.dart';
import 'login_screen.dart';
import 'input_temuan_screen.dart';
import 'temuan_terdekat_screen.dart';
import 'identifikasi_screen.dart';
import '../services/voice_service.dart';
import '../services/api_service.dart';

class DashboardScreen extends StatefulWidget {
  const DashboardScreen({super.key});

  @override
  State<DashboardScreen> createState() => _DashboardScreenState();
}

class _DashboardScreenState extends State<DashboardScreen> {
  String _userName = 'Administrator';
  String _userRole = 'administrator';
  final VoiceService _voiceService = VoiceService();
  bool _isVoiceListening = false;

  @override
  void initState() {
    super.initState();
    _loadUserInfo();
  }

  void _loadUserInfo() async {
    final prefs = await SharedPreferences.getInstance();
    setState(() {
      _userName = prefs.getString('user_name') ?? 'Administrator';
      _userRole = prefs.getString('user_role') ?? 'administrator';
    });
  }

  void _handleLogout() async {
    final prefs = await SharedPreferences.getInstance();
    await prefs.clear();
    if (mounted) {
      Navigator.pushReplacement(
        context,
        MaterialPageRoute(builder: (context) => const LoginScreen()),
      );
    }
  }

  void _showChangePasswordDialog() {
    final currentPasswordController = TextEditingController();
    final newPasswordController = TextEditingController();
    final formKey = GlobalKey<FormState>();
    bool isSubmitting = false;

    showDialog(
      context: context,
      builder: (context) {
        return StatefulBuilder(
          builder: (context, setModalState) {
            return AlertDialog(
              shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(16)),
              title: Row(
                children: [
                  const Icon(Icons.key_rounded, color: Color(0xFF004D4F)),
                  const SizedBox(width: 8),
                  Text('Ganti Password', style: GoogleFonts.outfit(fontWeight: FontWeight.bold, fontSize: 18)),
                ],
              ),
              content: Form(
                key: formKey,
                child: Column(
                  mainAxisSize: MainAxisSize.min,
                  children: [
                    TextFormField(
                      controller: currentPasswordController,
                      obscureText: true,
                      decoration: const InputDecoration(
                        labelText: 'Password Saat Ini',
                        border: OutlineInputBorder(),
                      ),
                      validator: (v) => v!.isEmpty ? 'Wajib diisi' : null,
                    ),
                    const SizedBox(height: 12),
                    TextFormField(
                      controller: newPasswordController,
                      obscureText: true,
                      decoration: const InputDecoration(
                        labelText: 'Password Baru',
                        border: OutlineInputBorder(),
                      ),
                      validator: (v) => (v == null || v.length < 6) ? 'Minimal 6 karakter' : null,
                    ),
                  ],
                ),
              ),
              actions: [
                TextButton(
                  onPressed: () => Navigator.pop(context),
                  child: const Text('Batal'),
                ),
                ElevatedButton(
                  style: ElevatedButton.styleFrom(backgroundColor: const Color(0xFF004D4F), foregroundColor: Colors.white),
                  onPressed: isSubmitting
                      ? null
                      : () async {
                          if (!formKey.currentState!.validate()) return;
                          setModalState(() => isSubmitting = true);
                          final res = await ApiService.changePassword(
                            currentPasswordController.text,
                            newPasswordController.text,
                          );
                          setModalState(() => isSubmitting = false);
                          if (context.mounted) {
                            Navigator.pop(context);
                            ScaffoldMessenger.of(context).showSnackBar(
                              SnackBar(
                                content: Text(res['message']),
                                backgroundColor: res['success'] ? Colors.green : Colors.redAccent,
                              ),
                            );
                          }
                        },
                  child: isSubmitting
                      ? const SizedBox(width: 16, height: 16, child: CircularProgressIndicator(color: Colors.white, strokeWidth: 2))
                      : const Text('Simpan'),
                ),
              ],
            );
          },
        );
      },
    );
  }

  void _toggleVoiceAssistant() async {
    await _voiceService.listen(
      onResult: (spokenText) {
        final command = VoiceService.matchVoiceCommand(spokenText);
        if (command != null) {
          _voiceService.stopListening();
          _executeVoiceCommand(command);
        } else {
          ScaffoldMessenger.of(context).showSnackBar(
            SnackBar(
              content: Text('Perintah tidak dikenali: "$spokenText". Coba sebutkan "Input Temuan" atau "Temuan Terdekat"'),
              duration: const Duration(seconds: 2),
            ),
          );
        }
      },
      onListeningStateChanged: (listening) {
        if (mounted) {
          setState(() {
            _isVoiceListening = listening;
          });
        }
      },
    );
  }

  void _executeVoiceCommand(String command) {
    switch (command) {
      case 'input_temuan':
        Navigator.push(context, MaterialPageRoute(builder: (context) => const InputTemuanScreen()));
        break;
      case 'temuan_terdekat':
        Navigator.push(context, MaterialPageRoute(builder: (context) => const TemuanTerdekatScreen()));
        break;
      case 'identifikasi':
      case 'data_temuan':
        Navigator.push(context, MaterialPageRoute(builder: (context) => const IdentifikasiScreen()));
        break;
      case 'logout':
        _handleLogout();
        break;
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: const Color(0xFFF4F6F9),
      // Clean Appbar (Matching Web Header Dark Tosca Theme #004D4F)
      appBar: AppBar(
        backgroundColor: const Color(0xFF004D4F),
        elevation: 2,
        toolbarHeight: 64,
        automaticallyImplyLeading: false,
        title: Row(
          children: [
            Container(
              padding: const EdgeInsets.all(6),
              decoration: BoxDecoration(
                color: Colors.white.withOpacity(0.15),
                shape: BoxShape.circle,
              ),
              child: const Icon(Icons.flash_on_rounded, color: Colors.yellowAccent, size: 20),
            ),
            const SizedBox(width: 10),
            Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  'SIDAK TEJO',
                  style: GoogleFonts.outfit(
                    color: Colors.white,
                    fontSize: 16,
                    fontWeight: FontWeight.bold,
                    letterSpacing: 0.5,
                  ),
                ),
                Text(
                  'PLN UP3 SIDOARJO',
                  style: GoogleFonts.inter(
                    color: Colors.white70,
                    fontSize: 10,
                    fontWeight: FontWeight.w600,
                    letterSpacing: 0.5,
                  ),
                ),
              ],
            ),
          ],
        ),
        actions: [
          Padding(
            padding: const EdgeInsets.only(right: 6.0),
            child: InkWell(
              onTap: _showChangePasswordDialog,
              borderRadius: BorderRadius.circular(20),
              child: Container(
                padding: const EdgeInsets.all(8),
                decoration: BoxDecoration(
                  color: Colors.white.withOpacity(0.18),
                  shape: BoxShape.circle,
                ),
                child: const Icon(Icons.key_rounded, color: Colors.white, size: 18),
              ),
            ),
          ),
          Padding(
            padding: const EdgeInsets.only(right: 12.0),
            child: InkWell(
              onTap: _handleLogout,
              borderRadius: BorderRadius.circular(20),
              child: Container(
                padding: const EdgeInsets.all(8),
                decoration: BoxDecoration(
                  color: Colors.white.withOpacity(0.18),
                  shape: BoxShape.circle,
                ),
                child: const Icon(Icons.exit_to_app_rounded, color: Colors.white, size: 18),
              ),
            ),
          ),
        ],
      ),
      floatingActionButton: FloatingActionButton.extended(
        onPressed: _toggleVoiceAssistant,
        backgroundColor: _isVoiceListening ? Colors.redAccent : const Color(0xFFFF6B35),
        icon: Icon(
          _isVoiceListening ? Icons.mic : Icons.mic_none_rounded,
          color: Colors.white,
        ),
        label: Text(
          _isVoiceListening ? 'Mendengarkan...' : 'Perintah Suara',
          style: const TextStyle(color: Colors.white, fontWeight: FontWeight.bold),
        ),
      ),
      body: SafeArea(
        child: SingleChildScrollView(
          padding: const EdgeInsets.all(16.0),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              // Welcome Banner Card (Clean Non-overlapping Container)
              Container(
                width: double.infinity,
                padding: const EdgeInsets.symmetric(horizontal: 20, vertical: 16),
                decoration: BoxDecoration(
                  gradient: const LinearGradient(
                    colors: [Color(0xFF004D4F), Color(0xFF007275)],
                    begin: Alignment.topLeft,
                    end: Alignment.bottomRight,
                  ),
                  borderRadius: BorderRadius.circular(16),
                  boxShadow: [
                    BoxShadow(
                      color: const Color(0xFF004D4F).withOpacity(0.25),
                      blurRadius: 10,
                      offset: const Offset(0, 4),
                    )
                  ],
                ),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    const Text(
                      'Selamat Datang,',
                      style: TextStyle(color: Colors.white70, fontSize: 13, fontWeight: FontWeight.normal),
                    ),
                    const SizedBox(height: 2),
                    Text(
                      _userName,
                      style: GoogleFonts.outfit(
                        color: Colors.white,
                        fontSize: 24,
                        fontWeight: FontWeight.extrabold,
                      ),
                    ),
                  ],
                ),
              ),

              const SizedBox(height: 20),

              // 2-Column Grid Layout for Cards (Role Adaptive)
              GridView.count(
                crossAxisCount: 2,
                crossAxisSpacing: 12,
                mainAxisSpacing: 12,
                childAspectRatio: 1.55,
                shrinkWrap: true,
                physics: const NeverScrollableScrollPhysics(),
                children: [
                  // 1. Data Temuan
                  _buildGridCard(
                    title: _userRole == 'har_row' ? 'Data Temuan (ROW)' : 'Data Temuan',
                    icon: Icons.format_list_bulleted_rounded,
                    badgeBgColor: const Color(0xFFDBEAFE),
                    iconColor: const Color(0xFF2563EB),
                    onTap: () {
                      Navigator.push(context, MaterialPageRoute(builder: (context) => const IdentifikasiScreen()));
                    },
                  ),

                  // 2. Update Pekerjaan
                  _buildGridCard(
                    title: 'Update Pekerjaan',
                    icon: Icons.edit_note_rounded,
                    badgeBgColor: const Color(0xFFFFEDD5),
                    iconColor: const Color(0xFFEA580C),
                    onTap: () {
                      Navigator.push(context, MaterialPageRoute(builder: (context) => const InputTemuanScreen()));
                    },
                  ),

                  // 3. Temuan Terdekat
                  _buildGridCard(
                    title: 'Temuan Terdekat',
                    icon: Icons.person_pin_circle_outlined,
                    badgeBgColor: const Color(0xFFE0F2FE),
                    iconColor: const Color(0xFF0284C7),
                    onTap: () {
                      Navigator.push(context, MaterialPageRoute(builder: (context) => const TemuanTerdekatScreen()));
                    },
                  ),

                  // 4. Identifikasi Gangguan
                  _buildGridCard(
                    title: 'Identifikasi Gangguan',
                    icon: Icons.bolt_rounded,
                    onTap: () {
                      Navigator.push(context, MaterialPageRoute(builder: (context) => const InputTemuanScreen()));
                    },
                  ),

                  // 10. Lap. Management
                  _buildGridCard(
                    title: 'Lap. Management',
                    icon: Icons.description_outlined,
                    badgeBgColor: const Color(0xFFCFFAFE),
                    iconColor: const Color(0xFF0891B2),
                    onTap: () {
                      Navigator.push(context, MaterialPageRoute(builder: (context) => const IdentifikasiScreen()));
                    },
                  ),
                ],
              ),
              const SizedBox(height: 80), // Bottom spacing for FAB
            ],
          ),
        ),
      ),
    );
  }

  Widget _buildGridCard({
    required String title,
    required IconData icon,
    required Color badgeBgColor,
    required Color iconColor,
    required VoidCallback onTap,
  }) {
    return Card(
      elevation: 1,
      color: Colors.white,
      shadowColor: Colors.black.withOpacity(0.06),
      shape: RoundedRectangleBorder(
        borderRadius: BorderRadius.circular(16),
        side: BorderSide(color: Colors.grey.shade200),
      ),
      child: InkWell(
        onTap: onTap,
        borderRadius: BorderRadius.circular(16),
        child: Padding(
          padding: const EdgeInsets.symmetric(vertical: 12, horizontal: 8),
          child: Column(
            mainAxisAlignment: MainAxisAlignment.center,
            children: [
              Container(
                padding: const EdgeInsets.all(10),
                decoration: BoxDecoration(
                  color: badgeBgColor,
                  shape: BoxShape.circle,
                ),
                child: Icon(icon, color: iconColor, size: 22),
              ),
              const SizedBox(height: 8),
              Text(
                title,
                textAlign: TextAlign.center,
                style: GoogleFonts.outfit(
                  color: const Color(0xFF1E293B),
                  fontSize: 13,
                  fontWeight: FontWeight.w600,
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}
