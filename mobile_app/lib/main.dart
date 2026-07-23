import 'package:flutter/material.dart';
import 'package:shared_preferences/shared_preferences.dart';
import 'screens/login_screen.dart';
import 'screens/dashboard_screen.dart';

import 'package:google_fonts/google_fonts.dart';

void main() async {
  WidgetsFlutterBinding.ensureInitialized();
  
  // Check if session token exists
  final prefs = await SharedPreferences.getInstance();
  final isLoggedIn = prefs.containsKey('user_id');

  runApp(SidakTejoApp(isLoggedIn: isLoggedIn));
}

class SidakTejoApp extends StatelessWidget {
  final bool isLoggedIn;
  
  const SidakTejoApp({super.key, required this.isLoggedIn});

  @override
  Widget build(BuildContext context) {
    return MaterialApp(
      title: 'SIDAK TEJO Mobile',
      debugShowCheckedModeBanner: false,
      theme: ThemeData(
        brightness: Brightness.light,
        primaryColor: const Color(0xFF004D4F),
        scaffoldBackgroundColor: const Color(0xFFF4F6F9),
        colorScheme: const ColorScheme.light(
          primary: Color(0xFF004D4F),
          secondary: Color(0xFF00B5B8),
          surface: Colors.white,
          background: Color(0xFFF4F6F9),
        ),
        textTheme: GoogleFonts.interTextTheme(ThemeData.light().textTheme).copyWith(
          titleLarge: GoogleFonts.outfit(fontWeight: FontWeight.bold, color: const Color(0xFF1E293B)),
          titleMedium: GoogleFonts.outfit(fontWeight: FontWeight.w600, color: const Color(0xFF1E293B)),
        ),
        appBarTheme: const AppBarTheme(
          backgroundColor: Color(0xFF004D4F),
          foregroundColor: Colors.white,
          elevation: 0,
        ),
        useMaterial3: true,
      ),
      home: isLoggedIn ? const DashboardScreen() : const LoginScreen(),
    );
  }
}
