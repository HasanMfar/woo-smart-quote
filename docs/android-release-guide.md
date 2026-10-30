# راهنمای دریافت فایل Release APK و راه‌اندازی GitHub Actions

برای بیلد و دریافت فایل **Release APK** اپلیکیشن همراه اندروید (`Nerkhnegar Companion`) بر روی مخزن خصوصی گیت‌هاب (Private Repository)، مراحل زیر به‌صورت صفر تا صد آماده شده است.

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

---

## ۴. انتشار عمومی فایل‌های APK بدون فاش شدن سورس‌کد مخزن (Public Releases Repo Architecture)

### صورت مسئله:
- مخزن سورس پروژه شما (`woo-smart-quote`) حاوی کدهای اختصاصی PHP، سورس فلاتر و کدهای بیزینس است و **باید ۱۰۰٪ خصوصی (Private)** باقی بماند.
- در گیت‌هاب، فایل‌های پیوست شده به Release در یک مخزن Private فقط توسط کاربرانی قابل دانلود است که در آن مخزن عضویت یا دسترسی دست‌کم Read داشته باشند؛ در غیر این صورت لینک مستقیم با خطای 404 مواجه می‌شود.

### راه‌حل برگزیده (استاندارد صنعت نرم‌افزار):
ایجاد یک **مخزن جداگانه و عمومی صرفاً جهت انتشار (Releases-Only Public Repository)**:
1. در گیت‌هاب یک ریپازیتوری عمومی جدید با نام مثلاً `nerkhnegar-companion-releases` ایجاد کنید (با تیک Add a README file). در این مخزن هیچ سورس کدی قرار نمی‌گیرد.
2. مخزن اصلی سورس شما همچنان کاملاً Private و محرمانه باقی می‌ماند.
3. فایل‌های نهایی `.apk` بیلد شده، در بخش Releases مخزن عمومی منتشر می‌شوند.
4. گیت‌هاب یک لینک دانلود مستقیم همیشگی و دائمی با ریدایرکت خودکار به آخرین نسخه برای فایل یونیورسال ارائه می‌دهد:
   ```
   https://github.com/<YOUR_USERNAME>/nerkhnegar-companion-releases/releases/latest/download/app-release.apk
   ```
   و برای نسخه ۶۴ بیتی:
   ```
   https://github.com/HasanMfar/nerkhnegar-companion-releases/releases/latest/download/app-arm64-v8a-release.apk
   ```

### روش‌های آپلود فایل APK به مخزن عمومی:

#### روش اول: خودکار با GitHub Actions (پیشنهادی)
1. در گیت‌هاب به مسیر **Settings** > **Developer Settings** > **Personal Access Tokens** > **Fine-grained tokens** بروید.
2. یک توکن جدید بسازید و دسترسی آن را فقط به مخزن `nerkhnegar-companion-releases` و با مجوز **Contents: Read and write** محدود نمایید.
3. توکن ساخته شده را در مخزن سورس خصوصی خود، در مسیر **Settings** > **Secrets and variables** > **Actions** با نام `PUBLIC_RELEASE_PAT` ذخیره کنید.
4. پایپ‌لاین `.github/workflows/build-android.yml` به‌صورت خودکار پس از هر بار پوش به شاخه `main`، فایل‌های APK را بیلد کرده و مستقیماً روی مخزن عمومی ریلیز می‌نماید؛ بدون اینکه حتی یک خط از سورس کد مخزن اصلی به مخزن عمومی منتقل شود.

#### روش دوم: آپلود دستی (ساده و سریع)
1. از تب **Actions** در مخزن خصوصی، فایل `release-apks` را دانلود و Extract کنید.
2. وارد مخزن عمومی `nerkhnegar-companion-releases` شوید.
3. در ستون سمت راست روی **Releases** > **Draft a new release** کلیک کنید.
4. تگ `v1.0.0` را انتخاب کرده و فایل‌های `app-release.apk` و `app-arm64-v8a-release.apk` را بکشید و رها کنید (Drag & Drop) و روی **Publish release** کلیک نمایید.

---

## ۵. تنظیم و سفارشی‌سازی لینک دانلود در افزونه نرخنگار

در تب **اپلیکیشن همراه** پیشخوان وردپرس، لینک دانلود به طور پیش‌فرض بر روی لینک عمومی انتشارهای رسمی گیت‌هاب تنظیم شده است. همچنین به سه روش زیر می‌توانید لینک دلخواه خود را جایگزین کنید:

1. **از طریق پیشخوان وردپرس:**
   در تب اپلیکیشن همراه، روی دکمه «آدرس اختصاصی…» در مرحله ۱ کلیک کرده و آدرس مستقیم فایل APK آپلود شده روی هاست یا رسانه وردپرس خود را ذخیره کنید.
2. **از طریق فایل wp-config.php:**
   ```php
   define( 'WSQ_COMPANION_APK_URL', 'https://your-site.com/downloads/nerkhnegar-companion.apk' );
   ```
3. **از طریق هوک فیلتر در functions.php پوسته:**
   ```php
   add_filter( 'wsq_companion_apk_download_url', function( $url, $variant ) {
       if ( 'arm64' === $variant ) {
           return 'https://cdn.yourdomain.com/app-arm64.apk';
       }
       return 'https://cdn.yourdomain.com/app-universal.apk';
   }, 10, 2 );
   ```
