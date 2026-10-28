import 'package:flutter/material.dart';
import 'package:mobile_scanner/mobile_scanner.dart';
import '../../../core/constants/app_colors.dart';
import '../../../core/constants/app_strings.dart';
import '../services/auth_service.dart';
import '../../dashboard/screens/dashboard_screen.dart';

class QrScannerScreen extends StatefulWidget {
  /// True when the scanner is the app's root screen (no store paired yet).
  final bool isRoot;

  const QrScannerScreen({super.key, this.isRoot = false});

  @override
  State<QrScannerScreen> createState() => _QrScannerScreenState();
}

class _QrScannerScreenState extends State<QrScannerScreen> {
  final MobileScannerController _controller = MobileScannerController(
    detectionSpeed: DetectionSpeed.normal,
    facing: CameraFacing.back,
  );
  bool _isProcessing = false;
  final TextEditingController _manualUrlController = TextEditingController();
  final TextEditingController _manualCodeController = TextEditingController();

  @override
  void dispose() {
    _controller.dispose();
    _manualUrlController.dispose();
    _manualCodeController.dispose();
    super.dispose();
  }

  void _completePairing() {
    if (!mounted) return;
    if (widget.isRoot) {
      Navigator.of(context).pushReplacement(
        MaterialPageRoute(builder: (_) => const DashboardScreen()),
      );
    } else {
      Navigator.of(context).pop(true);
    }
  }

  void _onDetect(BarcodeCapture capture) async {
    if (_isProcessing) return;
    final barcodes = capture.barcodes;
    if (barcodes.isEmpty) return;

    final rawValue = barcodes.first.rawValue;
    if (rawValue == null || rawValue.isEmpty) return;

    setState(() => _isProcessing = true);

    final success = await AuthService.pairWithQrPayload(rawValue);

    if (!mounted) return;

    if (success) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(
          content: Text(AppStrings.pairingSuccess),
          backgroundColor: AppColors.secondary,
        ),
      );
      _completePairing();
    } else {
      setState(() => _isProcessing = false);
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(
          content: Text(AppStrings.pairingError),
          backgroundColor: AppColors.error,
        ),
      );
    }
  }

  void _showManualDialog() {
    showDialog(
      context: context,
      builder: (ctx) => AlertDialog(
        title: const Text('اتصال دستی با کد', textAlign: TextAlign.right),
        content: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            TextField(
              controller: _manualUrlController,
              decoration: const InputDecoration(
                labelText: 'آدرس وبسایت فروشگاه (URL)',
                hintText: 'https://example.com',
              ),
              textDirection: TextDirection.ltr,
            ),
            const SizedBox(height: 12),
            TextField(
              controller: _manualCodeController,
              decoration: const InputDecoration(
                labelText: 'کد جفت‌سازی ۸ رقمی',
                hintText: '1234-5678',
              ),
              textDirection: TextDirection.ltr,
            ),
          ],
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(ctx),
            child: const Text('انصراف'),
          ),
          ElevatedButton(
            onPressed: () async {
              Navigator.pop(ctx);
              setState(() => _isProcessing = true);
              final ok = await AuthService.pairWithManualEntry(
                siteUrl: _manualUrlController.text,
                pairingCode: _manualCodeController.text,
              );
              if (!mounted) return;
              if (ok) {
                _completePairing();
              } else {
                setState(() => _isProcessing = false);
                ScaffoldMessenger.of(context).showSnackBar(
                  const SnackBar(content: Text(AppStrings.pairingError), backgroundColor: AppColors.error),
                );
              }
            },
            child: const Text('اتصال'),
          ),
        ],
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: const Text(AppStrings.scanQrTitle),
        actions: [
          IconButton(
            icon: const Icon(Icons.flash_on),
            onPressed: () => _controller.toggleTorch(),
          ),
          IconButton(
            icon: const Icon(Icons.cameraswitch),
            onPressed: () => _controller.switchCamera(),
          ),
        ],
      ),
      body: Stack(
        children: [
          MobileScanner(
            controller: _controller,
            onDetect: _onDetect,
          ),
          // Viewfinder Overlay
          Center(
            child: Container(
              width: 250,
              height: 250,
              decoration: BoxDecoration(
                border: Border.all(color: AppColors.primaryLight, width: 3),
                borderRadius: BorderRadius.circular(20),
              ),
            ),
          ),
          // Instruction Card
          Positioned(
            bottom: 30,
            left: 20,
            right: 20,
            child: Card(
              color: Colors.white.withOpacity(0.95),
              child: Padding(
                padding: const EdgeInsets.all(16),
                child: Column(
                  mainAxisSize: MainAxisSize.min,
                  children: [
                    const Text(
                      AppStrings.scanQrSubtitle,
                      textAlign: TextAlign.center,
                      style: TextStyle(fontSize: 14, fontWeight: FontWeight.bold),
                    ),
                    const SizedBox(height: 12),
                    TextButton.icon(
                      onPressed: _showManualDialog,
                      icon: const Icon(Icons.keyboard),
                      label: const Text('وارد کردن دستی کد اتصال'),
                    ),
                  ],
                ),
              ),
            ),
          ),
          if (_isProcessing)
            Container(
              color: Colors.black54,
              child: const Center(
                child: CircularProgressIndicator(color: Colors.white),
              ),
            ),
        ],
      ),
    );
  }
}
