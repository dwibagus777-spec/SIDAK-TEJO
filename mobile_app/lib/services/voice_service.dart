import 'package:flutter/material.dart';
import 'package:speech_to_text/speech_to_text.dart' as stt;
import 'package:permission_handler/permission_handler.dart';

class VoiceService {
  static final VoiceService _instance = VoiceService._internal();
  factory VoiceService() => _instance;
  VoiceService._internal();

  final stt::SpeechToText _speech = stt::SpeechToText();
  bool _isAvailable = false;
  bool _isListening = false;

  bool get isListening => _isListening;
  bool get isAvailable => _isAvailable;

  Future<bool> initSpeech() async {
    try {
      var status = await Permission.microphone.request();
      if (status.isGranted) {
        _isAvailable = await _speech.initialize(
          onError: (val) => debugPrint('Voice Error: $val'),
          onStatus: (val) {
            debugPrint('Voice Status: $val');
            if (val == 'done' || val == 'notListening') {
              _isListening = false;
            }
          },
        );
      } else {
        _isAvailable = false;
      }
    } catch (e) {
      debugPrint('Voice Init exception: $e');
      _isAvailable = false;
    }
    return _isAvailable;
  }

  Future<void> listen({
    required Function(String text) onResult,
    required Function(bool listening) onListeningStateChanged,
    String localeId = 'id_ID',
  }) async {
    if (!_isAvailable) {
      bool init = await initSpeech();
      if (!init) {
        onListeningStateChanged(false);
        return;
      }
    }

    if (_isListening) {
      await stopListening();
      onListeningStateChanged(false);
      return;
    }

    _isListening = true;
    onListeningStateChanged(true);

    try {
      await _speech.listen(
        localeId: localeId,
        onResult: (result) {
          onResult(result.recognizedWords);
          if (result.finalResult) {
            _isListening = false;
            onListeningStateChanged(false);
          }
        },
        cancelOnError: true,
        partialResults: true,
      );
    } catch (e) {
      debugPrint('Voice listen exception: $e');
      _isListening = false;
      onListeningStateChanged(false);
    }
  }

  Future<void> stopListening() async {
    if (_isListening) {
      try {
        await _speech.stop();
      } catch (e) {
        debugPrint('Voice stop exception: $e');
      }
      _isListening = false;
    }
  }

  // Parse voice commands for global navigation/actions
  static String? matchVoiceCommand(String rawText) {
    final text = rawText.toLowerCase().trim();
    if (text.contains('input') || text.contains('tambah temuan') || text.contains('lapor')) {
      return 'input_temuan';
    } else if (text.contains('terdekat') || text.contains('peta') || text.contains('sekitar')) {
      return 'temuan_terdekat';
    } else if (text.contains('identifikasi') || text.contains('gangguan') || text.contains('analisis')) {
      return 'identifikasi';
    } else if (text.contains('data temuan') || text.contains('daftar temuan')) {
      return 'data_temuan';
    } else if (text.contains('eviden') || text.contains('kubikel') || text.contains('trafo')) {
      return 'eviden';
    } else if (text.contains('keluar') || text.contains('logout')) {
      return 'logout';
    }
    return null;
  }
}
