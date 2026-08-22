/**
 * مودال اجباری قوانین و مقررات. عمداً هیچ راه بستنی جز چک‌کردن چک‌باکس
 * و کلیک روی دکمهٔ تأیید ندارد — نه دکمهٔ بستن، نه کلیک روی پرده، نه
 * کلید Escape. وضعیت «پذیرفته‌شده» در localStorage با کلید نسخه‌دار
 * (data-storage-key، از Widgets\Terms_Modal::render()) ذخیره می‌شود.
 * بررسی اولیهٔ همان localStorage با یک اسکریپت inline داخل خودِ
 * PHP انجام شده (برای جلوگیری از فلش)؛ این فایل فقط رفتار تعاملی
 * (فعال‌شدن دکمه، بستن نهایی، قفل اسکرول) را مدیریت می‌کند.
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

        if ('none' !== overlay.style.display) {
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
