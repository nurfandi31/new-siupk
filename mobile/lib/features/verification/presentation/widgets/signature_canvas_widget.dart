import 'dart:convert';
import 'dart:typed_data';
import 'package:flutter/material.dart';
import 'package:signature/signature.dart';
import '../../../../core/theme/app_colors.dart';

class SignatureCanvasWidget extends StatefulWidget {
  final String title;
  final Function(String base64Png)? onSignatureSaved;

  const SignatureCanvasWidget({
    super.key,
    required this.title,
    this.onSignatureSaved,
  });

  @override
  State<SignatureCanvasWidget> createState() => _SignatureCanvasWidgetState();
}

class _SignatureCanvasWidgetState extends State<SignatureCanvasWidget> {
  late final SignatureController _controller;
  bool _hasSignature = false;

  @override
  void initState() {
    super.initState();
    _controller = SignatureController(
      penStrokeWidth: 3,
      penColor: Colors.black,
      exportBackgroundColor: Colors.transparent,
    );
    _controller.addListener(() {
      if (_controller.isNotEmpty != _hasSignature) {
        setState(() => _hasSignature = _controller.isNotEmpty);
      }
    });
  }

  @override
  void dispose() {
    _controller.dispose();
    super.dispose();
  }

  Future<void> _saveSignature() async {
    if (_controller.isEmpty) return;
    final Uint8List? data = await _controller.toPngBytes();
    if (data != null) {
      final base64String = base64Encode(data);
      widget.onSignatureSaved?.call(base64String);
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(content: Text('Tanda tangan digital berhasil disimpan'), duration: Duration(seconds: 1)),
        );
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(14),
      decoration: BoxDecoration(
        color: AppColors.surfaceContainerLowest,
        borderRadius: BorderRadius.circular(12),
        border: Border.all(color: AppColors.outlineVariant),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Row(
            mainAxisAlignment: MainAxisAlignment.spaceBetween,
            children: [
              Text(
                widget.title,
                style: const TextStyle(fontSize: 13, fontWeight: FontWeight.w700, color: AppColors.onSurface),
              ),
              if (_hasSignature)
                IconButton(
                  icon: const Icon(Icons.clear, size: 18, color: AppColors.error),
                  tooltip: 'Hapus Tanda Tangan',
                  onPressed: () {
                    _controller.clear();
                    setState(() => _hasSignature = false);
                  },
                ),
            ],
          ),
          const SizedBox(height: 8),
          Container(
            height: 140,
            decoration: BoxDecoration(
              color: Colors.white,
              borderRadius: BorderRadius.circular(8),
              border: Border.all(color: AppColors.outlineVariant),
            ),
            child: ClipRRect(
              borderRadius: BorderRadius.circular(8),
              child: Signature(
                controller: _controller,
                backgroundColor: Colors.white,
              ),
            ),
          ),
          const SizedBox(height: 10),
          Row(
            mainAxisAlignment: MainAxisAlignment.spaceBetween,
            children: [
              const Text(
                'Tanda tangan langsung pada layar',
                style: TextStyle(fontSize: 11, color: AppColors.onSurfaceVariant),
              ),
              TextButton.icon(
                style: TextButton.styleFrom(
                  padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 6),
                ),
                onPressed: _hasSignature ? _saveSignature : null,
                icon: const Icon(Icons.check, size: 16),
                label: const Text('Kunci TTD', style: TextStyle(fontSize: 12)),
              ),
            ],
          ),
        ],
      ),
    );
  }
}
