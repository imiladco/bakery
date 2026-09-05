/**
 * توست سراسری صفحه — به هیچ ویجتی تعلق ندارد و به همین دلیل هم مثل
 * bakery-header.js خودش را در body می‌سازد، نه اینکه منتظر مارکاپ از
 * سمت PHP باشد: مصرف‌کننده‌هایش (bakery-add-to-cart.js و
 * bakery-cart-sidebar.js) فقط وقتی صدایش می‌زنند که پاسخ سرور بگوید
 * درخواست کاربر رد/بریده شده؛ اکثر بازدیدها اصلاً به آن نیاز پیدا
 * نمی‌کنند.
 *
 * دو نوع دارد، نه یکی — رنگ و آیکون هرکدام معنای متفاوتی حمل می‌کند:
 *   - error (قرمز): اعتبار ماهانه کافی نیست. محدودیتِ کاربر است.
 *   - warning (نارنجی): ظرفیتِ خودِ محصول برای امروز تمام شده.
 *     محدودیتِ محصول است، ربطی به اعتبار کاربر ندارد.
 * چرا یک عنصر مشترک به‌جای دو تا: هر دو مصرف‌کننده ممکن است پشتِ سرِ
 * هم پیام‌های متفاوت بفرستند و باید فقط یکی دیده شود، نه روی هم.
 *
 * window.bkwToast.show(title, text, variant) عمومی است؛
 * insufficientCredit()/soldOutToday() میان‌برهایی با متن‌های
 * لوکالایزشده از PHP‌اند (رجوع کن به
 * includes/bakery/plugin.php::register_scripts).
 */
(function () {
    'use strict';

    var ICONS = {
        error: '<svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">' +
            '<circle cx="12" cy="12" r="9" stroke="#BA291E" stroke-width="2"/>' +
            '<path d="M12 7.5V13" stroke="#BA291E" stroke-width="2" stroke-linecap="round"/>' +
            '<path d="M12 16.5V16.51" stroke="#BA291E" stroke-width="2" stroke-linecap="round"/>' +
            '</svg>',
        warning: '<svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">' +
            '<circle cx="12" cy="12" r="10" stroke="#F97316" stroke-width="2"/>' +
            '<path d="M12 7V13" stroke="#F97316" stroke-width="2" stroke-linecap="round"/>' +
            '<path d="M12 16.5V16.51" stroke="#F97316" stroke-width="2" stroke-linecap="round"/>' +
            '</svg>',
    };

    var toastEl = null;
    var hideTimer = null;

    /** چون فقط با اولین نمایش ساخته می‌شود، مکرراً صدا زدنش هزینه‌ای ندارد. */
    function ensureToast() {
        if (toastEl) {
            return toastEl;
        }

        toastEl = document.createElement('div');
        toastEl.className = 'bkw-toast';
        toastEl.setAttribute('role', 'alert');
        toastEl.setAttribute('aria-live', 'assertive');
        toastEl.innerHTML =
            '<div class="bkw-toast__icon"></div>' +
            '<div class="bkw-toast__text">' +
            '<div class="bkw-toast__title" data-bkw-toast-title></div>' +
            '<div class="bkw-toast__desc" data-bkw-toast-desc></div>' +
            '</div>';

        document.body.appendChild(toastEl);
        return toastEl;
    }

    /**
     * نمایش/تمدید — اگر توست از قبل باز است، فقط تایمر مخفی‌شدنش را از
     * نو می‌چیند (کلیک پشت‌سرهم روی «+» توست را هر بار جا‌به‌جا/چشمک‌زن
     * نمی‌کند، فقط باز نگهش می‌دارد).
     */
    function show(title, text, variant) {
        variant = ICONS[variant] ? variant : 'error';

        var el = ensureToast();
        el.className = 'bkw-toast bkw-toast--' + variant;
        el.querySelector('.bkw-toast__icon').innerHTML = ICONS[variant];
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
            strings.insufficientCreditText || 'موجودی کیف پول شما برای ثبت این سفارش کافی نمی‌باشد.',
            'error'
        );
    }

    function soldOutToday() {
        var strings = window.bkwToastStrings || {};
        show(
            strings.soldOutTodayTitle || 'این محصول برای امروز پر شده است',
            strings.soldOutTodayText || 'متأسفانه ظرفیت این محصول برای امروز تکمیل شده است.',
            'warning'
        );
    }

    /**
     * میان‌بر مشترکِ دو مصرف‌کننده: مقدار 'blocked_reason' که
     * Cart_Ajax در پاسخِ افزودن به سبد برمی‌گرداند را مستقیم می‌گیرد و
     * توستِ درست را نشان می‌دهد؛ مقدار خالی یعنی کاری لازم نیست.
     */
    function forReason(reason) {
        if ('stock' === reason) {
            soldOutToday();
        } else if ('credit' === reason) {
            insufficientCredit();
        }
    }

    window.bkwToast = {
        show: show,
        insufficientCredit: insufficientCredit,
        soldOutToday: soldOutToday,
        forReason: forReason,
    };
})();
