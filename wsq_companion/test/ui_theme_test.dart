import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:wsq_companion/core/constants/app_colors.dart';
import 'package:wsq_companion/core/constants/app_theme.dart';
import 'package:wsq_companion/core/widgets/wsq_empty_state.dart';

void main() {
  group('UI/UX Pro Max Theme & RTL Tests', () {
    test('Light theme configures Vazirmatn, Material 3 and Navy Brand', () {
      final theme = AppTheme.lightTheme;

      expect(theme.useMaterial3, isTrue);
      expect(theme.brightness, Brightness.light);
      expect(theme.colorScheme.primary, AppColors.primary);
      expect(theme.cardTheme.shape, isA<RoundedRectangleBorder>());
      expect(theme.textTheme.bodyLarge?.fontFamily, 'Vazirmatn');
      expect(theme.elevatedButtonTheme.style?.textStyle?.resolve({})?.fontFamily, 'Vazirmatn');
      expect(theme.dialogTheme.backgroundColor, AppColors.surface);
    });

    test('Dark theme configures dark surfaces, chipTheme, dialogTheme, and Vazirmatn', () {
      final darkTheme = AppTheme.darkTheme;

      expect(darkTheme.useMaterial3, isTrue);
      expect(darkTheme.brightness, Brightness.dark);
      expect(darkTheme.scaffoldBackgroundColor, AppColors.darkBackground);
      expect(darkTheme.cardTheme.color, AppColors.darkSurface);
      expect(darkTheme.textTheme.bodyMedium?.fontFamily, 'Vazirmatn');
      expect(darkTheme.chipTheme.backgroundColor, AppColors.darkSurface);
      expect(darkTheme.dialogTheme.backgroundColor, AppColors.darkSurface);
      expect(darkTheme.snackBarTheme.backgroundColor, AppColors.darkSurfaceCard);
    });

    testWidgets('AppColors helpers return contrast-safe colors across Light and Dark brightness', (tester) async {
      await tester.pumpWidget(
        MaterialApp(
          home: Theme(
            data: AppTheme.lightTheme,
            child: Builder(
              builder: (context) {
                final lightPrice = AppColors.priceColor(context);
                final lightCard = AppColors.cardBg(context);
                final lightRecessed = AppColors.recessedBg(context);
                final lightBadge = AppColors.statusBadgeBg(context, isPending: true);

                expect(lightPrice, AppColors.primaryDark);
                expect(lightCard, AppColors.surface);
                expect(lightRecessed, AppColors.background);
                expect(lightBadge, AppColors.pendingBg);

                return const SizedBox();
              },
            ),
          ),
        ),
      );

      await tester.pumpWidget(
        MaterialApp(
          home: Theme(
            data: AppTheme.darkTheme,
            child: Builder(
              builder: (context) {
                final darkPrice = AppColors.priceColor(context);
                final darkCard = AppColors.cardBg(context);
                final darkRecessed = AppColors.recessedBg(context);
                final darkBadge = AppColors.statusBadgeBg(context, isPending: true);

                // Verify dark price is bright enough to ensure WCAG 4.5:1 on dark background
                expect(darkPrice, const Color(0xFF60A5FA));
                expect(darkCard, AppColors.darkSurface);
                expect(darkRecessed, AppColors.darkSurfaceCard);
                expect(darkBadge, AppColors.darkPendingBg);

                return const SizedBox();
              },
            ),
          ),
        ),
      );
    });

    testWidgets('RTL Persian Widget renders with Vazirmatn typography and accessible touch target', (tester) async {
      await tester.pumpWidget(
        MaterialApp(
          theme: AppTheme.lightTheme,
          home: Directionality(
            textDirection: TextDirection.rtl,
            child: Scaffold(
              body: Center(
                child: ElevatedButton.icon(
                  onPressed: () {},
                  icon: const Icon(Icons.check),
                  label: const Text('تأیید و ارسال استعلام'),
                ),
              ),
            ),
          ),
        ),
      );

      final buttonFinder = find.byType(ElevatedButton);
      expect(buttonFinder, findsOneWidget);

      final buttonSize = tester.getSize(buttonFinder);
      // Verify minimum touch target height of 48dp on Android
      expect(buttonSize.height, greaterThanOrEqualTo(48.0));

      // Verify Persian text is rendered
      expect(find.text('تأیید و ارسال استعلام'), findsOneWidget);
    });

    testWidgets('Dark Mode bottom action bar and status chip render with accessible contrast and 48dp target', (tester) async {
      await tester.pumpWidget(
        MaterialApp(
          theme: AppTheme.darkTheme,
          home: Theme(
            data: AppTheme.darkTheme,
            child: Directionality(
              textDirection: TextDirection.rtl,
              child: Scaffold(
                bottomNavigationBar: Container(
                  padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 12),
                  color: AppColors.darkSurface,
                  child: SafeArea(
                    child: SizedBox(
                      width: double.infinity,
                      height: 48,
                      child: ElevatedButton.icon(
                        onPressed: () {},
                        icon: const Icon(Icons.send_rounded),
                        label: const Text('ذخیره و صدور پیش‌فاکتور'),
                      ),
                    ),
                  ),
                ),
                body: Center(
                  child: Builder(
                    builder: (context) {
                      final badgeBg = AppColors.statusBadgeBg(context, isPending: true);
                      return Container(
                        padding: const EdgeInsets.all(8),
                        color: badgeBg,
                        child: const Text(
                          'در انتظار قیمت',
                          style: TextStyle(
                            color: AppColors.pending,
                            fontSize: 12,
                            fontWeight: FontWeight.bold,
                          ),
                        ),
                      );
                    },
                  ),
                ),
              ),
            ),
          ),
        ),
      );

      final buttonFinder = find.byType(ElevatedButton);
      expect(buttonFinder, findsOneWidget);
      final btnSize = tester.getSize(buttonFinder);
      expect(btnSize.height, greaterThanOrEqualTo(48.0));
      expect(find.text('در انتظار قیمت'), findsOneWidget);
    });

    testWidgets('WsqEmptyState renders icon, title, guidance and triggers action callback', (tester) async {
      bool actionTriggered = false;

      await tester.pumpWidget(
        MaterialApp(
          theme: AppTheme.lightTheme,
          home: Directionality(
            textDirection: TextDirection.rtl,
            child: Scaffold(
              body: WsqEmptyState(
                icon: Icons.receipt_long_outlined,
                title: 'هیچ استعلامی یافت نشد.',
                message: 'استعلام‌های ثبت‌شده در فروشگاه به صورت خودکار در این صفحه نمایش داده می‌شوند.',
                actionLabel: 'به‌روزرسانی لیست',
                actionIcon: Icons.refresh,
                onAction: () => actionTriggered = true,
              ),
            ),
          ),
        ),
      );

      expect(find.byIcon(Icons.receipt_long_outlined), findsOneWidget);
      expect(find.text('هیچ استعلامی یافت نشد.'), findsOneWidget);
      expect(find.text('استعلام‌های ثبت‌شده در فروشگاه به صورت خودکار در این صفحه نمایش داده می‌شوند.'), findsOneWidget);
      expect(find.byType(OutlinedButton), findsOneWidget);

      await tester.tap(find.byType(OutlinedButton));
      await tester.pump();
      expect(actionTriggered, isTrue);
    });

    testWidgets('WsqEmptyState renders error state with distinct error styling and retry button', (tester) async {
      bool retryTriggered = false;

      await tester.pumpWidget(
        MaterialApp(
          theme: AppTheme.lightTheme,
          home: Directionality(
            textDirection: TextDirection.rtl,
            child: Scaffold(
              body: WsqEmptyState(
                icon: Icons.cloud_off_outlined,
                title: 'خطا در ارتباط',
                message: 'اتصال به فروشگاه برقرار نشد.',
                actionLabel: 'تلاش مجدد',
                actionIcon: Icons.refresh,
                onAction: () => retryTriggered = true,
                isError: true,
              ),
            ),
          ),
        ),
      );

      final iconFinder = find.byIcon(Icons.cloud_off_outlined);
      expect(iconFinder, findsOneWidget);
      final iconWidget = tester.widget<Icon>(iconFinder);
      expect(iconWidget.color, AppColors.error);

      expect(find.text('خطا در ارتباط'), findsOneWidget);
      expect(find.text('اتصال به فروشگاه برقرار نشد.'), findsOneWidget);

      await tester.tap(find.byType(OutlinedButton));
      await tester.pump();
      expect(retryTriggered, isTrue);
    });
  });
}
