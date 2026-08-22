/**
 * مودال اجباری قوانین و مقررات. عمداً هیچ راه بستنی جز چک‌کردن چک‌باکس
 * و کلیک روی دکمهٔ تأیید ندارد — نه دکمهٔ بستن، نه کلیک روی پرده، نه
 * کلید Escape. وضعیت «پذیرفته‌شده» در localStorage با کلید نسخه‌دار
 * (data-storage-key، از Traits\Terms_Modal_Controls::render_terms_modal())
 * ذخیره می‌شود. بررسی اولیهٔ همان localStorage با یک اسکریپت inline
 * داخل خودِ PHP انجام شده (برای جلوگیری از فلش)؛ این فایل فقط رفتار
 * تعاملی (فعال‌شدن دکمه، بستن نهایی، قفل اسکرول) را مدیریت می‌کند.
 *
 * دو زمینهٔ مصرف دارد (رجوع کن به مستندات Terms_Modal_Controls):
 * مستقل (بدون data-redirect-url) که فقط قفل صفحه را باز می‌کند، و
 * تعبیه‌شده در Login (با data-redirect-url) که با ویژگی HTML `hidden`
 * شروع می‌شود — assets/js/bakery-login.js آن را نمایان می‌کند — و بعد
 * از تأیید به همان آدرس ریدایرکت می‌کند.
 */
(function () {
    'use strict';

    function setupModal(overlay) {
        var isEditMode = '1' === overlay.getAttribute('data-edit-mode');
        var storageKey = overlay.getAttribute('data-storage-key');
        var checkbox = overlay.querySelector('[data-bkw-terms-checkbox]');
        var acceptBtn = overlay.querySelector('[data-bkw-terms-accept]');

        if (!checkbox || !acceptBtn) {
            return;
        }

        // در ادیتور المنتور، ادمین باید همیشه بتواند مودال را ببیند و
        // استایلش را بسازد — نه قفل صفحه، نه وابسته به تیک‌زدن.
        if (isEditMode) {
            acceptBtn.disabled = false;
            return;
        }

        // برای حالت مستقل (بدون hidden)، اگر پرده از قبل نمایان است، از
        // همین ابتدا اسکرول قفل می‌شود. حالت تعبیه‌شده در Login با ویژگی
        // hidden رندر می‌شود — یعنی این‌جا هنوز نمایان نیست و نباید قفل
        // شود؛ قفل آن وقتی اعمال می‌شود که bakery-login.js خودش با
        // برداشتن hidden، کلاس bkw-panel-open را اضافه می‌کند.
        if (!overlay.hasAttribute('hidden') && 'none' !== overlay.style.display) {
            document.documentElement.classList.add('bkw-panel-open');
        }

        checkbox.addEventListener('change', function () {
            acceptBtn.disabled = !checkbox.checked;
        });

        acceptBtn.addEventListener('click', function () {
            if (!checkbox.checked) {
                return;
            }

            try {
                window.localStorage.setItem(storageKey, 'accepted');
            } catch (e) {
                // localStorage ممکن است در حالت خصوصی/مرورگر محدودشده در
                // دسترس نباشد؛ در این صورت فقط حافظهٔ «قبلاً پذیرفته» کار
                // نمی‌کند و کاربر دفعهٔ بعد دوباره می‌بیند — مسدودکننده نیست.
            }

            // فقط زمانی تنظیم می‌شود که این مودال در ویجت Login تعبیه شده
            // باشد (رجوع کن به Terms_Modal_Controls::render_terms_modal)؛
            // مصرف مستقل چنین ویژگی‌ای ندارد و به‌جایش فقط پرده را می‌بندد.
            var redirectUrl = overlay.getAttribute('data-redirect-url');
            if (redirectUrl) {
                window.location.href = redirectUrl;
                return;
            }

            overlay.style.display = 'none';
            document.documentElement.classList.remove('bkw-panel-open');
        });
    }

    function init() {
        document.querySelectorAll('[data-bkw-terms]').forEach(setupModal);
    }

    if ('loading' === document.readyState) {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
