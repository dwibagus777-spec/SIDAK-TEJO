import 'package:flutter/material.dart';
import '../services/voice_service.dart';

class VoiceInputField extends StatefulWidget {
  final TextEditingController controller;
  final String labelText;
  final String? hintText;
  final IconData? prefixIcon;
  final int maxLines;
  final TextInputType keyboardType;
  final String? Function(String?)? validator;
  final Function(String)? onChanged;

  const VoiceInputField({
    super.key,
    required this.controller,
    required this.labelText,
    this.hintText,
    this.prefixIcon,
    this.maxLines = 1,
    this.keyboardType = TextInputType.text,
    this.validator,
    this.onChanged,
  });

  @override
  State<VoiceInputField> createState() => _VoiceInputFieldState();
}

class _VoiceInputFieldState extends State<VoiceInputField> {
  final VoiceService _voiceService = VoiceService();
  bool _isListening = false;

  void _toggleListening() async {
    await _voiceService.listen(
      onResult: (text) {
        setState(() {
          widget.controller.text = text;
          widget.controller.selection = TextSelection.fromPosition(
            TextPosition(offset: widget.controller.text.length),
          );
        });
        if (widget.onChanged != null) {
          widget.onChanged!(text);
        }
      },
      onListeningStateChanged: (listening) {
        if (mounted) {
          setState(() {
            _isListening = listening;
          });
        }
      },
    );
  }

  @override
  Widget build(BuildContext context) {
    return TextFormField(
      controller: widget.controller,
      maxLines: widget.maxLines,
      keyboardType: widget.keyboardType,
      validator: widget.validator,
      onChanged: widget.onChanged,
      style: const TextStyle(color: Color(0xFF1E293B), fontSize: 14),
      decoration: InputDecoration(
        labelText: widget.labelText,
        hintText: widget.hintText ?? 'Bisa diketik atau gunakan tombol mikrofon...',
        hintStyle: TextStyle(color: Colors.grey.shade400, fontSize: 13),
        labelStyle: TextStyle(color: Colors.grey.shade700, fontWeight: FontWeight.w500),
        prefixIcon: widget.prefixIcon != null ? Icon(widget.prefixIcon, color: const Color(0xFF0284C7)) : null,
        suffixIcon: Padding(
          padding: const EdgeInsets.only(right: 4.0),
          child: Tooltip(
            message: _isListening ? 'Mendengarkan...' : 'Input Suara (Voice)',
            child: IconButton(
              icon: Icon(
                _isListening ? Icons.mic : Icons.mic_none_rounded,
                color: _isListening ? Colors.redAccent : const Color(0xFF0284C7),
                size: 22,
              ),
              onPressed: _toggleListening,
            ),
          ),
        ),
        filled: true,
        fillColor: Colors.white,
        contentPadding: const EdgeInsets.symmetric(horizontal: 16, vertical: 12),
        border: OutlineInputBorder(
          borderRadius: BorderRadius.circular(10),
          borderSide: BorderSide(color: Colors.grey.shade300),
        ),
        enabledBorder: OutlineInputBorder(
          borderRadius: BorderRadius.circular(10),
          borderSide: BorderSide(color: Colors.grey.shade300),
        ),
        focusedBorder: OutlineInputBorder(
          borderRadius: BorderRadius.circular(10),
          borderSide: const BorderSide(color: Color(0xFF0284C7), width: 1.8),
        ),
      ),
    );
  }
}
