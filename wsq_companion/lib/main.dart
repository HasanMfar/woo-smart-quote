import 'package:flutter/material.dart';
import 'package:flutter_localizations/flutter_localizations.dart';
import 'core/constants/app_strings.dart';
import 'core/constants/app_theme.dart';
import 'core/storage/local_storage.dart';
import 'features/auth/services/auth_service.dart';
import 'features/auth/screens/qr_scanner_screen.dart';
import 'features/dashboard/screens/dashboard_screen.dart';
import 'services/notification_service.dart';
import 'services/background_sync.dart';

void main() async {
  WidgetsFlutterBinding.ensureInitialized();

  // Initialize storage & background services
  await LocalStorage.init();
  await NotificationService.init();
  await BackgroundSync.init();

  final isAuth = await AuthService.isAuthenticated();

  runApp(WSQCompanionApp(isAuthenticated: isAuth));
}

class WSQCompanionApp extends StatelessWidget {
  final bool isAuthenticated;

  const WSQCompanionApp({super.key, required this.isAuthenticated});

  @override
  Widget build(BuildContext context) {
    return MaterialApp(
      title: AppStrings.appName,
      debugShowCheckedModeBanner: false,
      theme: AppTheme.lightTheme,
      localizationsDelegates: const [
        GlobalMaterialLocalizations.delegate,
        GlobalWidgetsLocalizations.delegate,
        GlobalCupertinoLocalizations.delegate,
      ],
      supportedLocales: const [
        Locale('fa', 'IR'),
      ],
      locale: const Locale('fa', 'IR'),
      home: isAuthenticated ? const DashboardScreen() : const QrScannerScreen(),
    );
  }
}
