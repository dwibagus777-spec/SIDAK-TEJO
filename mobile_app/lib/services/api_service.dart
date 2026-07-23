import 'dart:convert';
import 'dart:io';
import 'package:http/http.dart' as http;
import 'package:shared_preferences/shared_preferences.dart';
import '../config/app_config.dart';

class ApiService {
  // Helper: Get stored token / credentials if any
  static Future<Map<String, String>> _getHeaders() async {
    return {
      'Accept': 'application/json',
    };
  }

  // 1. Auth: Login
  static Future<Map<String, dynamic>> login(String username, String password) async {
    try {
      final response = await http.post(
        Uri.parse('${AppConfig.apiBaseUrl}/auth/login'),
        body: {
          'username': username,
          'password': password,
        },
      ).timeout(const Duration(seconds: AppConfig.apiTimeout));

      final data = json.decode(response.body);
      
      if (response.statusCode == 200) {
        // Save user session locally
        final prefs = await SharedPreferences.getInstance();
        await prefs.setInt('user_id', data['user']['id']);
        await prefs.setString('user_name', data['user']['nama']);
        await prefs.setString('user_role', data['user']['role']);
        if (data['user']['ulp_id'] != null) {
          await prefs.setInt('user_ulp_id', data['user']['ulp_id']);
        }
        return {'success': true, 'message': 'Login berhasil', 'user': data['user']};
      } else {
        return {'success': false, 'message': data['messages']?['error'] ?? 'Username atau password salah'};
      }
    } catch (e) {
      return {'success': false, 'message': 'Gagal terhubung ke server: $e'};
    }
  }

  // 2. Options: Get ULPs
  static Future<List<dynamic>> getUlps() async {
    try {
      final response = await http.get(
        Uri.parse('${AppConfig.apiBaseUrl}/options'),
        headers: await _getHeaders(),
      ).timeout(const Duration(seconds: AppConfig.apiTimeout));

      if (response.statusCode == 200) {
        final data = json.decode(response.body);
        return data['ulps'] ?? [];
      }
    } catch (e) {
      print('Error getUlps: $e');
    }
    return [];
  }

  // 3. Options: Get Penyulangs by ULP ID
  static Future<List<dynamic>> getPenyulangs(int ulpId) async {
    try {
      final response = await http.get(
        Uri.parse('${AppConfig.apiBaseUrl}/penyulangs/$ulpId'),
        headers: await _getHeaders(),
      ).timeout(const Duration(seconds: AppConfig.apiTimeout));

      if (response.statusCode == 200) {
        return json.decode(response.body);
      }
    } catch (e) {
      print('Error getPenyulangs: $e');
    }
    return [];
  }

  // 4. Options: Get Sections by Penyulang ID
  static Future<List<dynamic>> getSections(int penyulangId) async {
    try {
      final response = await http.get(
        Uri.parse('${AppConfig.apiBaseUrl}/sections/$penyulangId'),
        headers: await _getHeaders(),
      ).timeout(const Duration(seconds: AppConfig.apiTimeout));

      if (response.statusCode == 200) {
        return json.decode(response.body);
      }
    } catch (e) {
      print('Error getSections: $e');
    }
    return [];
  }

  // 5. Findings: Create Finding with Multi-Upload Photos
  static Future<Map<String, dynamic>> createTemuan(
    Map<String, String> fields,
    List<File> photos,
  ) async {
    try {
      final uri = Uri.parse('${AppConfig.apiBaseUrl}/temuan/create');
      final request = http.MultipartRequest('POST', uri);
      
      // Add text fields
      request.fields.addAll(fields);

      // Add headers
      request.headers.addAll(await _getHeaders());

      // Add photo files
      for (int i = 0; i < photos.length; i++) {
        final photo = photos[i];
        final stream = http.ByteStream(photo.openRead());
        final length = await photo.length();
        
        final multipartFile = http.MultipartFile(
          'foto[]', // CI4 expects multi-upload as array name
          stream,
          length,
          filename: photo.path.split('/').last,
        );
        request.files.add(multipartFile);
      }

      final streamedResponse = await request.send().timeout(const Duration(seconds: 30));
      final response = await http.Response.fromStream(streamedResponse);
      final data = json.decode(response.body);

      if (response.statusCode == 200 || response.statusCode == 201) {
        return {'success': true, 'message': 'Temuan berhasil dilaporkan!'};
      } else {
        return {'success': false, 'message': data['messages']?['error'] ?? 'Gagal menyimpan temuan.'};
      }
    } catch (e) {
      return {'success': false, 'message': 'Gagal mengirim data: $e'};
    }
  }

  // 6. Map: Get Nearby Findings
  static Future<List<dynamic>> getTemuanTerdekat(double lat, double lng, int radiusMeters) async {
    try {
      final response = await http.get(
        Uri.parse('${AppConfig.apiBaseUrl}/temuan/terdekat?latitude=$lat&longitude=$lng&radius=$radiusMeters'),
        headers: await _getHeaders(),
      ).timeout(const Duration(seconds: AppConfig.apiTimeout));

      if (response.statusCode == 200) {
        return json.decode(response.body);
      }
    } catch (e) {
      print('Error getTemuanTerdekat: $e');
    }
    return [];
  }

  // 7. Findings: Get All/Filtered Findings
  static Future<List<dynamic>> getTemuan({String? status, String? prioritas}) async {
    try {
      final prefs = await SharedPreferences.getInstance();
      final ulpId = prefs.getInt('user_ulp_id');
      
      String url = '${AppConfig.apiBaseUrl}/temuan?';
      if (ulpId != null) url += 'ulp_id=$ulpId&';
      if (status != null) url += 'status=$status&';
      if (prioritas != null) url += 'prioritas=$prioritas&';

      final response = await http.get(
        Uri.parse(url),
        headers: await _getHeaders(),
      ).timeout(const Duration(seconds: AppConfig.apiTimeout));

      if (response.statusCode == 200) {
        return json.decode(response.body);
      }
    } catch (e) {
      print('Error getTemuan: $e');
    }
    return [];
  }

  // 8. Auth: Change Password
  static Future<Map<String, dynamic>> changePassword(String currentPassword, String newPassword) async {
    try {
      final prefs = await SharedPreferences.getInstance();
      final userId = prefs.getInt('user_id');

      if (userId == null) {
        return {'success': false, 'message': 'Sesi pengguna tidak valid, silakan login ulang.'};
      }

      final response = await http.post(
        Uri.parse('${AppConfig.apiBaseUrl}/auth/change-password'),
        body: {
          'user_id': userId.toString(),
          'current_password': currentPassword,
          'new_password': newPassword,
        },
      ).timeout(const Duration(seconds: AppConfig.apiTimeout));

      final data = json.decode(response.body);

      if (response.statusCode == 200) {
        return {'success': true, 'message': 'Password berhasil diperbarui.'};
      } else {
        return {'success': false, 'message': data['messages']?['error'] ?? 'Gagal mengganti password.'};
      }
    } catch (e) {
      return {'success': false, 'message': 'Gagal terhubung ke server: $e'};
    }
  }
}
