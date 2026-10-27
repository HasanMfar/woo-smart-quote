import 'dart:io';
import 'package:dio/dio.dart';
import 'package:flutter/material.dart';
import 'package:path_provider/path_provider.dart';
import 'package:share_plus/share_plus.dart';
import 'package:url_launcher/url_launcher.dart';
import '../../../core/constants/app_colors.dart';
import '../../../core/constants/app_strings.dart';
import '../../../core/utils/number_formatter.dart';

class PdfPreviewScreen extends StatefulWidget {
  final int quoteId;
  final String quoteNumber;
  final String pdfUrl;

  const PdfPreviewScreen({
    super.key,
    required this.quoteId,
    required this.quoteNumber,
    required this.pdfUrl,
  });

  @override
  State<PdfPreviewScreen> createState() => _PdfPreviewScreenState();
}

class _PdfPreviewScreenState extends State<PdfPreviewScreen> {
  String? _localFilePath;
  bool _isDownloading = true;
  String? _errorMessage;

  @override
  void initState() {
    super.initState();
    _downloadPdf();
  }

  Future<void> _downloadPdf() async {
    setState(() {
      _isDownloading = true;
      _errorMessage = null;
    });

    try {
      final dir = await getTemporaryDirectory();
      final savePath = '${dir.path}/quote_${widget.quoteNumber}.pdf';

      final dio = Dio();
      await dio.download(widget.pdfUrl, savePath);

      if (File(savePath).existsSync()) {
        setState(() {
          _localFilePath = savePath;
          _isDownloading = false;
        });
      } else {
        setState(() {
          _isDownloading = false;
          _errorMessage = 'فایل پیش‌فاکتور یافت نشد.';
        });
      }
    } catch (e) {
      setState(() {
        _isDownloading = false;
        _errorMessage = 'خطا در بارگیری فایل پیش‌فاکتور: $e';
      });
    }
  }

  void _sharePdf() async {
    if (_localFilePath != null && File(_localFilePath!).existsSync()) {
      await Share.shareXFiles(
        [XFile(_localFilePath!)],
        text: 'پیش‌فاکتور رسمی استعلام #${NumberFormatter.toPersianDigits(widget.quoteNumber)}',
      );
    } else {
      await Share.share(
        'پیش‌فاکتور رسمی استعلام #${NumberFormatter.toPersianDigits(widget.quoteNumber)}\n${widget.pdfUrl}',
      );
    }
  }

  void _openInBrowser() async {
    final uri = Uri.parse(widget.pdfUrl);
    if (await canLaunchUrl(uri)) {
      await launchUrl(uri, mode: LaunchMode.externalApplication);
    }
  }

  @override
  Widget build(BuildContext context) {
    return Directionality(
      textDirection: TextDirection.rtl,
      child: Scaffold(
        appBar: AppBar(
          title: Text('پیش‌فاکتور #${NumberFormatter.toPersianDigits(widget.quoteNumber)}'),
          actions: [
            IconButton(
              icon: const Icon(Icons.share_outlined),
              tooltip: AppStrings.sharePdf,
              onPressed: _sharePdf,
            ),
            IconButton(
              icon: const Icon(Icons.open_in_browser),
              tooltip: 'باز کردن در مرورگر',
              onPressed: _openInBrowser,
            ),
          ],
        ),
        body: Center(
          child: Padding(
            padding: const EdgeInsets.all(24),
            child: _isDownloading
                ? Column(
                    mainAxisAlignment: MainAxisAlignment.center,
                    children: [
                      const CircularProgressIndicator(),
                      const SizedBox(height: 16),
                      Text(
                        'در حال دریافت پیش‌فاکتور رسمی PDF…',
                        style: TextStyle(color: AppColors.textSecondary),
                      ),
                    ],
                  )
                : _errorMessage != null
                    ? Column(
                        mainAxisAlignment: MainAxisAlignment.center,
                        children: [
                          const Icon(Icons.error_outline, size: 48, color: AppColors.error),
                          const SizedBox(height: 12),
                          Text(_errorMessage!, textAlign: TextAlign.center),
                          const SizedBox(height: 16),
                          ElevatedButton.icon(
                            onPressed: _downloadPdf,
                            icon: const Icon(Icons.refresh),
                            label: const Text(AppStrings.retry),
                          ),
                          const SizedBox(height: 8),
                          TextButton(
                            onPressed: _openInBrowser,
                            child: const Text('باز کردن مستقیم لینک PDF'),
                          ),
                        ],
                      )
                    : Column(
                        mainAxisAlignment: MainAxisAlignment.center,
                        children: [
                          Container(
                            padding: const EdgeInsets.all(24),
                            decoration: BoxDecoration(
                              color: AppColors.secondary.withOpacity(0.12),
                              shape: BoxShape.circle,
                            ),
                            child: const Icon(Icons.picture_as_pdf, size: 72, color: AppColors.secondary),
                          ),
                          const SizedBox(height: 20),
                          Text(
                            'پیش‌فاکتور رسمی استعلام #${NumberFormatter.toPersianDigits(widget.quoteNumber)}',
                            style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 18),
                          ),
                          const SizedBox(height: 8),
                          const Text(
                            'سند PDF با فونت وزیرمتن و مطابق با استانداردهای مالیاتی صادر شده است.',
                            textAlign: TextAlign.center,
                            style: TextStyle(color: AppColors.textSecondary, fontSize: 13),
                          ),
                          const SizedBox(height: 28),
                          SizedBox(
                            width: 240,
                            height: 48,
                            child: ElevatedButton.icon(
                              style: ElevatedButton.styleFrom(backgroundColor: AppColors.secondary),
                              onPressed: _sharePdf,
                              icon: const Icon(Icons.share),
                              label: const Text('اشتراک‌گذاری و ارسال'),
                            ),
                          ),
                          const SizedBox(height: 12),
                          SizedBox(
                            width: 240,
                            height: 48,
                            child: OutlinedButton.icon(
                              onPressed: _openInBrowser,
                              icon: const Icon(Icons.open_in_new),
                              label: const Text('مشاهده در نمایشگر فایل'),
                            ),
                          ),
                        ],
                      ),
          ),
        ),
      ),
    );
  }
}
