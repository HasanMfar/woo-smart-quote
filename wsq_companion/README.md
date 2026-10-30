# Nerkhnegar - Android Companion App

اپلیکیشن اختصاصی اندروید مدیریت استعلام قیمت و صدور پیش‌فاکتور برای فروشگاه‌های ووکامرس.

## نحوه بیلد Release APK با GitHub Actions

1. کلید امضای نسخه نهایی (keystore) خارج از مخزن و در مسیر امن محلی نگهداری می‌شود و نباید هرگز کامیت شود.

2. سکرت‌های مورد نیاز در GitHub Actions (از قبل تنظیم شده‌اند):
   - `KEYSTORE_BASE64`: محتوای Base64 فایل keystore (خارج از مخزن)
   - `KEYSTORE_PASSWORD`: رمز keystore (فقط در GitHub Actions Secrets)
   - `KEY_ALIAS`: `wsq-release-key`
   - `KEY_PASSWORD`: رمز کلید (فقط در GitHub Actions Secrets)

3. پس از `git push` به شاخه `main` یا فعال‌سازی دستی از تب Actions:
   - فایل‌های نصبی `app-release.apk` (یونیورسال و اسپلیت ۶۴ بیتی) و `app-release.aab` در بخش **Artifacts** قابل دانلود خواهند بود.
