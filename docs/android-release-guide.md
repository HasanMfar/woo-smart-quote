# راهنمای دریافت فایل Release APK و راه‌اندازی GitHub Actions

برای بیلد و دریافت فایل **Release APK** اپلیکیشن همراه اندروید (`WooCommerce Smart Quote Companion`) بر روی مخزن خصوصی گیت‌هاب (Private Repository)، مراحل زیر به‌صورت صفر تا صد آماده شده است.

---

## ۱. اطلاعات کلید اختصاصی امضای نسخه نهایی (Production Keystore)

کلید امضای نسخه نهایی (RSA 4096، اعتبار ۱۰,۰۰۰ روز) در تاریخ ۲۰۲۶-۱۰-۲۸ چرخش (rotate) شد. فایل keystore و رمزهای آن خارج از مخزن نگهداری می‌شوند و هرگز نباید کامیت شوند.

سکرت‌های GitHub Actions از قبل روی مخزن تنظیم شده‌اند؛ در اینجا فقط نام سکرت‌ها مستند می‌شود:

| نام سکرت در گیت‌هاب (Secret Name) | مقدار |
|---|---|
| `KEYSTORE_BASE64` | محتوای Base64 فایل keystore (خارج از مخزن) |
| `KEYSTORE_PASSWORD` | فقط در GitHub Actions Secrets |
| `KEY_ALIAS` | `wsq-release-key` |
| `KEY_PASSWORD` | فقط در GitHub Actions Secrets |

> [!WARNING]
> فایل keystore، رمزها و خروجی Base64 آن را هرگز در مخزن قرار ندهید. برای ثبت یا به‌روزرسانی سکرت‌ها از `gh secret set` استفاده کنید.
> اگر سکرت‌ها تنظیم نشده باشند، پایپ‌لاین با Debug Key بیلد می‌کند.

---

## ۲. مراحل فعال‌سازی در گیت‌هاب (۳ مرحله ساده)

### مرحله ۱: ثبت Secrets در ریپازیتوری گیت‌هاب
1. در ریپازیتوری خصوصی خود در GitHub به مسیر **Settings** > **Secrets and variables** > **Actions** بروید.
2. روی دکمه **New repository secret** کلیک کنید و ۴ سکرت بالا را اضافه نمایید.

### مرحله ۲: پوش کردن پروژه به ریپازیتوری
ترمینال را در پوشه پروژه باز کنید و دستورات زیر را اجرا کنید:

```bash
git init
git add .
git commit -m "feat: complete woo smart quote plugin, rest api and android companion app"
git branch -M main
git remote add origin https://github.com/<YOUR_USERNAME>/<YOUR_PRIVATE_REPO>.git
git push -u origin main
```

*(یا اگر می‌خواهید فقط پوشه `wsq_companion` را به‌صورت ریپازیتوری جداگانه پوش کنید، وارد پوشه `wsq_companion` شده و همین دستورات را اجرا کنید؛ پایپ‌لاین در هر دو حالت به طور خودکار مسیر پروژه را تشخیص می‌دهد).*

---

## ۳. دانلود مستقیم فایل Release APK

به محض اجرای `git push`:
1. در صفحه گیت‌هاب به تب **Actions** بروید.
2. گردش‌کار **Build & Sign Android Companion App** به‌صورت خودکار شروع به کار می‌کند (زمان بیلد: حدود ۳ الی ۴ دقیقه).
3. پس از اتمام (تیک سبز):
   - روی عنوان اجرا کلیک کنید و به بخش پایین صفحه در قسمت **Artifacts** بروید.
   - فایل **`release-apks`** را دانلود کنید.

### محتویات آرتیفکت APK:
- **`app-release.apk`**: فایل نصبی یونیورسال (قابل نصب روی تمام مدل‌ها و برندهای گوشی اندروید).
- **`app-arm64-v8a-release.apk`**: فایل بهینه‌شده فوق‌سبک مخصوص گوشی‌های مدرن ۶۴ بیتی.
- **`app-armeabi-v7a-release.apk`**: فایل مخصوص گوشی‌های قدیمی‌تر ۳۲ بیتی.
- **`release-aab`**: بسته استاندارد Android App Bundle (جهت بارگذاری در مارکت‌های بازار، مایکت یا گوگل‌پلی).
