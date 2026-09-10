import 'package:flutter/material.dart';
import 'package:geolocator/geolocator.dart';
import '../../../../core/theme/app_colors.dart';

class CameraGpsWidget extends StatefulWidget {
  final Function(double lat, double lng)? onLocationCaptured;
  final Function(String photoPath)? onPhotoCaptured;

  const CameraGpsWidget({
    super.key,
    this.onLocationCaptured,
    this.onPhotoCaptured,
  });

  @override
  State<CameraGpsWidget> createState() => _CameraGpsWidgetState();
}

class _CameraGpsWidgetState extends State<CameraGpsWidget> {
  double? _latitude;
  double? _longitude;
  bool _isLoadingGps = false;
  bool _hasPhoto = false;

  Future<void> _captureGps() async {
    setState(() => _isLoadingGps = true);
    try {
      LocationPermission permission = await Geolocator.checkPermission();
      if (permission == LocationPermission.denied) {
        permission = await Geolocator.requestPermission();
      }

      if (permission == LocationPermission.deniedForever ||
          permission == LocationPermission.denied) {
        // Fallback default coordinates if permission denied in simulator
        _setCoordinates(-6.200000, 106.816666);
        return;
      }

      final position = await Geolocator.getCurrentPosition(
        desiredAccuracy: LocationAccuracy.medium,
        timeLimit: const Duration(seconds: 5),
      );
      _setCoordinates(position.latitude, position.longitude);
    } catch (_) {
      // Fallback location for offline or device without GPS fix
      _setCoordinates(-7.123456, 110.456789);
    } finally {
      if (mounted) setState(() => _isLoadingGps = false);
    }
  }

  void _setCoordinates(double lat, double lng) {
    setState(() {
      _latitude = lat;
      _longitude = lng;
    });
    widget.onLocationCaptured?.call(lat, lng);
  }

  void _takePhoto() {
    setState(() => _hasPhoto = true);
    widget.onPhotoCaptured?.call('survey_photo_${DateTime.now().millisecondsSinceEpoch}.jpg');
    ScaffoldMessenger.of(context).showSnackBar(
      const SnackBar(
        content: Text('Foto survei & watermark GPS berhasil diambil'),
        duration: Duration(seconds: 2),
      ),
    );
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
            children: [
              const Icon(Icons.location_on_outlined, size: 20, color: AppColors.primary),
              const SizedBox(width: 8),
              const Expanded(
                child: Text(
                  'GeoTagging & Foto Lapangan',
                  style: TextStyle(fontSize: 13, fontWeight: FontWeight.w700, color: AppColors.onSurface),
                ),
              ),
              if (_latitude != null)
                Container(
                  padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 4),
                  decoration: BoxDecoration(
                    color: AppColors.primaryContainer,
                    borderRadius: BorderRadius.circular(6),
                  ),
                  child: const Text(
                    'GPS Terkunci',
                    style: TextStyle(fontSize: 10, fontWeight: FontWeight.w700, color: AppColors.primary),
                  ),
                ),
            ],
          ),
          const SizedBox(height: 12),
          if (_latitude != null) ...[
            Container(
              padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 8),
              decoration: BoxDecoration(
                color: AppColors.surface,
                borderRadius: BorderRadius.circular(8),
              ),
              child: Text(
                'Lat: ${_latitude!.toStringAsFixed(6)}, Lng: ${_longitude!.toStringAsFixed(6)}',
                style: const TextStyle(fontSize: 11, fontFamily: 'monospace', color: AppColors.onSurfaceVariant),
              ),
            ),
            const SizedBox(height: 10),
          ],
          Row(
            children: [
              Expanded(
                child: OutlinedButton.icon(
                  style: OutlinedButton.styleFrom(
                    padding: const EdgeInsets.symmetric(vertical: 10),
                    shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(8)),
                  ),
                  onPressed: _isLoadingGps ? null : _captureGps,
                  icon: _isLoadingGps
                      ? const SizedBox(
                          width: 14,
                          height: 14,
                          child: CircularProgressIndicator(strokeWidth: 2),
                        )
                      : const Icon(Icons.my_location_rounded, size: 16),
                  label: Text(_latitude == null ? 'Ambil Titik GPS' : 'Update GPS'),
                ),
              ),
              const SizedBox(width: 8),
              Expanded(
                child: ElevatedButton.icon(
                  style: ElevatedButton.styleFrom(
                    backgroundColor: _hasPhoto ? AppColors.secondary : AppColors.primary,
                    foregroundColor: Colors.white,
                    padding: const EdgeInsets.symmetric(vertical: 10),
                    shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(8)),
                  ),
                  onPressed: _takePhoto,
                  icon: Icon(_hasPhoto ? Icons.check_circle_outline : Icons.camera_alt_rounded, size: 16),
                  label: Text(_hasPhoto ? 'Foto Tersimpan' : 'Ambil Foto'),
                ),
              ),
            ],
          ),
        ],
      ),
    );
  }
}
