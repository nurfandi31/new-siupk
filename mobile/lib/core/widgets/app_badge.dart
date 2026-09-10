import 'package:flutter/material.dart';
import '../theme/app_colors.dart';

enum AppBadgeTone { success, warning, error, primary, neutral, info }
typedef BadgeType = AppBadgeTone;

class AppBadge extends StatelessWidget {
  final String label;
  final AppBadgeTone tone;
  final IconData? icon;

  const AppBadge({
    super.key,
    required this.label,
    this.tone = AppBadgeTone.neutral,
    this.icon,
  });

  @override
  Widget build(BuildContext context) {
    Color bgColor;
    Color fgColor;

    switch (tone) {
      case AppBadgeTone.success:
        bgColor = AppColors.successContainer;
        fgColor = AppColors.onSuccessContainer;
        break;
      case AppBadgeTone.warning:
        bgColor = AppColors.warningContainer;
        fgColor = AppColors.onWarningContainer;
        break;
      case AppBadgeTone.error:
        bgColor = AppColors.errorContainer;
        fgColor = AppColors.onErrorContainer;
        break;
      case AppBadgeTone.primary:
        bgColor = AppColors.primaryContainer;
        fgColor = AppColors.onPrimaryContainer;
        break;
      case AppBadgeTone.info:
      case AppBadgeTone.neutral:
        bgColor = AppColors.surfaceContainerHigh;
        fgColor = AppColors.onSurfaceVariant;
        break;
    }

    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 4),
      decoration: BoxDecoration(
        color: bgColor,
        borderRadius: BorderRadius.circular(6),
      ),
      child: Row(
        mainAxisSize: MainAxisSize.min,
        children: [
          if (icon != null) ...[
            Icon(icon, size: 12, color: fgColor),
            const SizedBox(width: 4),
          ],
          Text(
            label,
            style: TextStyle(
              fontSize: 11,
              fontWeight: FontWeight.w600,
              color: fgColor,
            ),
          ),
        ],
      ),
    );
  }
}
