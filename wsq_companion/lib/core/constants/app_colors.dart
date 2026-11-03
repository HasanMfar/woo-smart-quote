import 'package:flutter/material.dart';

/// Design System Color Palette for Nerkhnegar (wsq_companion)
/// Grounded in ui-ux-pro-max B2B Swiss/Minimalism Master Guidelines
class AppColors {
  // Brand colors (Navy Professional & Tech Blue)
  static const Color primary = Color(0xFF1E3A5F);      // Deep Professional Navy
  static const Color primaryLight = Color(0xFF2E5B8E); // Soft Slate Blue
  static const Color primaryDark = Color(0xFF12243C);  // Deep Midnight Navy
  static const Color secondary = Color(0xFF2563EB);    // Vibrant Action Blue
  static const Color secondaryDark = Color(0xFF1D4ED8);// Darker Blue
  static const Color accent = Color(0xFF059669);       // Paid / Trust Emerald

  // Status colors
  static const Color pending = Color(0xFFD97706);      // Amber 600
  static const Color pendingBg = Color(0xFFFEF3C7);    // Amber 100
  static const Color priced = Color(0xFF2563EB);       // Blue 600
  static const Color pricedBg = Color(0xFFDBEAFE);     // Blue 100
  static const Color completed = Color(0xFF059669);    // Emerald 600
  static const Color completedBg = Color(0xFFD1FAE5);  // Emerald 100
  static const Color error = Color(0xFFDC2626);        // Red 600
  static const Color errorBg = Color(0xFFFEE2E2);      // Red 100

  // Light Mode Neutral background & surface
  static const Color background = Color(0xFFF8FAFC);   // Slate 50
  static const Color surface = Color(0xFFFFFFFF);      // White
  static const Color cardBorder = Color(0xFFE2E8F0);   // Slate 200
  static const Color textPrimary = Color(0xFF0F172A);  // Slate 900
  static const Color textSecondary = Color(0xFF475569);// Slate 600
  static const Color textMuted = Color(0xFF94A3B8);    // Slate 400
  static const Color divider = Color(0xFFE2E8F0);      // Slate 200

  // Dark Mode Status & Neutrals
  static const Color darkBackground = Color(0xFF0F172A);// Slate 900
  static const Color darkSurface = Color(0xFF1E293B);   // Slate 800
  static const Color darkSurfaceCard = Color(0xFF243247);
  static const Color darkCardBorder = Color(0xFF334155);// Slate 700
  static const Color darkTextPrimary = Color(0xFFF8FAFC);
  static const Color darkTextSecondary = Color(0xFF94A3B8);
  static const Color darkTextMuted = Color(0xFF64748B);
  static const Color darkDivider = Color(0xFF334155);

  static const Color darkPendingBg = Color(0xFF3B2506);
  static const Color darkPricedBg = Color(0xFF172554);
  static const Color darkCompletedBg = Color(0xFF064E3B);
  static const Color darkErrorBg = Color(0xFF450A0A);

  /// Returns high-contrast readable price color for current theme brightness
  static Color priceColor(BuildContext context) {
    return Theme.of(context).brightness == Brightness.dark
        ? const Color(0xFF60A5FA)
        : primaryDark;
  }

  /// Returns card surface color for current theme
  static Color cardBg(BuildContext context) {
    return Theme.of(context).brightness == Brightness.dark
        ? darkSurface
        : surface;
  }

  /// Returns recessed container background (e.g., notes, inline tables)
  static Color recessedBg(BuildContext context) {
    return Theme.of(context).brightness == Brightness.dark
        ? darkSurfaceCard
        : background;
  }

  /// Returns card border color for current theme
  static Color border(BuildContext context) {
    return Theme.of(context).brightness == Brightness.dark
        ? darkCardBorder
        : cardBorder;
  }

  /// Returns status badge background for current theme
  static Color statusBadgeBg(BuildContext context, {required bool isPending}) {
    final isDark = Theme.of(context).brightness == Brightness.dark;
    if (isPending) {
      return isDark ? darkPendingBg : pendingBg;
    }
    return isDark ? darkPricedBg : pricedBg;
  }
}
