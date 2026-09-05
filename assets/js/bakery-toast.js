/**
 * توست سراسری صفحه — به هیچ ویجتی تعلق ندارد و به همین دلیل هم مثل
 * bakery-header.js خودش را در body می‌سازد، نه اینکه منتظر مارکاپ از
 * سمت PHP باشد: هر دو مصرف‌کننده‌اش (bakery-add-to-cart.js موقع افزودن
 * به سبد، bakery-cart-sidebar.js موقع ثبت سفارش) فقط وقتی صدایش می‌زنند
 * که پاسخ سرور «اعتبار کافی نیست» بگوید؛ اکثر بازدیدها اصلاً به آن
 * نیاز پیدا نمی‌کنند.
 *
 * چرا یک عنصر مشترک به‌جای یکی برای هر مصرف‌کننده: هر دو دقیقاً همین
 * پیام را نشان می‌دهند و اگر (مثلاً) کاربر همزمان از سایدبار سبد هم
 * تلاش کند هم دکمهٔ «+» ویجت را بزند، باید فقط یک توست دیده شود، نه دو
 * تا روی هم.
 *
 * window.bkwToast.show(title, text) عمومی و برای هر پیام دیگری هم قابل
 * استفاده است؛ window.bkwToast.insufficientCredit() فقط یک میان‌بر با
 * متن‌های لوکالایزشدهٔ همین پیام است (از PHP، رجوع کن به
 * includes/bakery/plugin.php::register_scripts).
 */
(function () {
    'use strict';

    var ICON = '<svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">' +
        '<circle cx="12" cy="12" r="9" stroke="#BA291E" stroke-width="2"/>' +
        '<path d="M12 7.5V13" stroke="#BA291E" stroke-width="2" stroke-linecap="round"/>' +
        '<path d="M12 16.5V16.51" stroke="#BA291E" stroke-width="2" stroke-linecap="round"/>' +
        '</svg>';

    var toastEl = null;
    var hideTimer = null;

    /** چون فقط با کلیک ساخته می‌شود، مکرراً صدا زدنش هزینه‌ای ندارد. */
    function ensureToast() {
        if (toastEl) {
            return toastEl;
        }

        toastEl = document.createElement('div');
        toastEl.className = 'bkw-toast';
        toastEl.setAttribute('role', 'alert');
        toastEl.setAttribute('aria-live', 'assertive');
        toastEl.innerHTML =
            '<div class="bkw-toast__text">' +
            '<div class="bkw-toast__title" data-bkw-toast-title></div>' +
            '<div class="bkw-toast__desc" data-bkw-toast-desc></div>' +
            '</div>' +
            '<div class="bkw-toast__icon">' + ICON + '</div>';

        document.body.appendChild(toastEl);
        return toastEl;
    }

    /**
     * نمایش/تمدید — اگر توست از قبل باز است، فقط تایمر مخفی‌شدنش را از
     * نو می‌چیند (کلیک پشت‌سرهم روی «+» توست را هر بار جا‌به‌جا/چشمک‌زن
     * نمی‌کند، فقط باز نگهش می‌دارد).
     */
    function show(title, text) {
        var el = ensureToast();
        el.querySelector('[data-bkw-toast-title]').textContent = title;
        el.querySelector('[data-bkw-toast-desc]').textContent = text;

        // یک فریم بعد از append/تغییر محتوا کلاس اضافه می‌شود تا ترنزیشن
        // CSS واقعاً اجرا شود (اگر همان لحظهٔ ساخت عنصر اضافه می‌شد،
        // مرورگر گذارِ حالت اولیه→is-visible را نمی‌دید).
        requestAnimationFrame(function () {
            el.classList.add('is-visible');
        });

        window.clearTimeout(hideTimer);
        hideTimer = window.setTimeout(function () {
            el.classList.remove('is-visible');
        }, 5000);
    }

    function insufficientCredit() {
        var strings = window.bkwToastStrings || {};
        show(
            strings.insufficientCreditTitle || 'موجودی کافی نیست',
            strings.insufficientCreditText || 'موجودی کیف پول شما برای ثبت این سفارش کافی نمی‌باشد.'
        );
    }

    window.bkwToast = { show: show, insufficientCredit: insufficientCredit };
})();
