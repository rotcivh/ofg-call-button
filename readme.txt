=== OFG Call Button ===
Contributors: weblogbaz
Tags: call button, phone, mobile, click tracking, floating button
Requires at least: 6.0
Tested up to: 7.0
Requires PHP: 7.4
Stable tag: 1.1.9
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Adds a configurable floating call button with per-page phone overrides and optional local click statistics.

== Description ==

OFG Call Button adds a lightweight floating call button to WordPress sites. Site owners can configure phone numbers, colors, position, visibility by screen size, animation effects, drag behavior, and per-content overrides.

The plugin can also record local click statistics when the site owner enables tracking in the plugin settings. These reports help show total calls, calls by page, sources, referrers, landing pages, and recent click activity inside the WordPress dashboard.

Features:

* Floating click-to-call button for mobile, tablet, and desktop layouts.
* Primary and alternative phone numbers.
* Per-post, per-page, and WooCommerce product overrides.
* Option to hide the button on specific content.
* Custom colors, text, position, animation, and drag behavior.
* Optional local click statistics and reports.
* Persian date display for report filters when the site locale is Persian.

Development repository: https://github.com/rotcivh/ofg-call-button

= فارسی =

دکمه تماس افق یک افزونه ساده و کاربردی برای نمایش دکمه تماس شناور در سایت وردپرسی است.

با این افزونه می‌توانید شماره تماس، رنگ‌ها، جایگاه دکمه، متن دکمه، نمایش در اندازه‌های مختلف صفحه، افکت‌ها و امکان جابه‌جایی دکمه را تنظیم کنید. همچنین برای هر نوشته، برگه یا محصول ووکامرس می‌توانید شماره اختصاصی قرار دهید یا نمایش دکمه را غیرفعال کنید.

در صورت فعال‌سازی گزارش‌گیری محلی، افزونه آمار کلیک‌ها را داخل پایگاه داده همان سایت ذخیره می‌کند و در پیشخوان وردپرس نمایش می‌دهد.

== Installation ==

1. Upload the `ofg-call-button` folder to `/wp-content/plugins/`, or install the plugin through the WordPress plugins screen.
2. Activate the plugin through the `Plugins` screen in WordPress.
3. Open `OFG Call Button` in the WordPress admin menu.
4. Enter at least one phone number and save the settings.
5. Enable local click statistics only if you want to store call-click report data on your site.

== Frequently Asked Questions ==

= Can I hide the button on a single page? =

Yes. Open the page, post, or product editor and use the OFG Call Button meta box.

= Can I set a different number for one page? =

Yes. Enter a custom phone number in the same meta box.

= Does click tracking require Google Analytics or an external service? =

No. The optional click statistics feature stores data locally in WordPress and does not contact an external analytics service.

= What data is stored when local click statistics are enabled? =

The plugin stores click time, page ID/title/URL, landing URL, referrer URL, source/medium, phone number, user agent, and mobile/desktop flag.

= Can I disable click statistics? =

Yes. Local click statistics are disabled by default for new installations and can be enabled or disabled from the plugin settings page.

= آیا افزونه فارسی را پشتیبانی می‌کند؟ =

بله. افزونه فایل ترجمه فارسی دارد و در سایت‌های فارسی، فیلتر تاریخ گزارش‌ها را به صورت شمسی نمایش می‌دهد.

== Privacy ==

OFG Call Button does not send data to external services.

When local click statistics are enabled by the site owner, the plugin stores call-click report data in the local WordPress database. This may include user agent, page URL, landing URL, referrer URL, phone number, and click time. Site owners should mention this in their privacy policy if they enable click statistics.

When click statistics are disabled, the plugin only renders the call button and does not store click report events.

== Changelog ==

= 1.1.9 =
* Prepared plugin metadata, readme, text domain, and privacy notes for WordPress.org submission.
* Disabled local click statistics by default for new installations.
* Removed bundled font dependency to avoid unclear third-party asset licensing.

= 1.0.0 =
* Initial release.
