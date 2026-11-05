import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import '../constants/app_colors.dart';

/// Reusable, accessible empty and error state component for Nerkhnegar.
/// Follows appllama-app-design-skill and MASTER.md anti-slop rules (Law 8).
class WsqEmptyState extends StatelessWidget {
  final IconData icon;
  final String title;
  final String message;
  final String? actionLabel;
  final IconData? actionIcon;
  final VoidCallback? onAction;
  final bool isError;

  const WsqEmptyState({
    super.key,
    required this.icon,
    required this.title,
    required this.message,
    this.actionLabel,
    this.actionIcon,
    this.onAction,
    this.isError = false,
  });

  @override
  Widget build(BuildContext context) {
    final isDark = Theme.of(context).brightness == Brightness.dark;
    final iconColor = isError
        ? AppColors.error
        : (isDark ? AppColors.primaryLight : AppColors.primary);

    return Center(
      child: Padding(
        padding: const EdgeInsets.all(28),
        child: Column(
          mainAxisAlignment: MainAxisAlignment.center,
          mainAxisSize: MainAxisSize.min,
          children: [
            Container(
              width: 64,
              height: 64,
              decoration: BoxDecoration(
                color: isError
                    ? (isDark ? AppColors.darkErrorBg : AppColors.errorBg)
                    : AppColors.recessedBg(context),
                shape: BoxShape.circle,
                border: Border.all(
                  color: isError
                      ? AppColors.error.withValues(alpha: 0.35)
                      : AppColors.border(context),
                ),
              ),
              child: Icon(
                icon,
                size: 32,
                color: iconColor,
              ),
            ),
            const SizedBox(height: 16),
            Text(
              title,
              textAlign: TextAlign.center,
              style: TextStyle(
                color: isError
                    ? (isDark ? const Color(0xFFFCA5A5) : AppColors.error)
                    : (Theme.of(context).textTheme.titleMedium?.color ??
                        AppColors.textPrimary),
                fontSize: 16,
                fontWeight: FontWeight.bold,
              ),
            ),
            const SizedBox(height: 8),
            Text(
              message,
              textAlign: TextAlign.center,
              style: TextStyle(
                color: isDark
                    ? AppColors.darkTextSecondary
                    : AppColors.textSecondary,
                fontSize: 13,
                height: 1.6,
              ),
            ),
            if (actionLabel != null && onAction != null) ...[
              const SizedBox(height: 20),
              OutlinedButton.icon(
                style: OutlinedButton.styleFrom(
                  minimumSize: const Size(180, 48), // 48dp Android Touch Target
                  foregroundColor: isError
                      ? AppColors.error
                      : (isDark ? AppColors.primaryLight : AppColors.primary),
                  side: BorderSide(
                    color: isError
                        ? AppColors.error.withValues(alpha: 0.5)
                        : (isDark ? AppColors.darkCardBorder : AppColors.cardBorder),
                  ),
                ),
                onPressed: () {
                  HapticFeedback.lightImpact();
                  onAction!();
                },
                icon: Icon(actionIcon ?? Icons.refresh, size: 18),
                label: Text(actionLabel!),
              ),
            ],
          ],
        ),
      ),
    );
  }
}
