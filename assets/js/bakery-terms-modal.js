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
 *
 * در حالت دوم، تأیید دو اکشن پشت سر هم می‌زند:
 *
 *   ۱) bkw_login_terms — لحظهٔ پذیرش را روی خودِ کاربر ثبت می‌کند
 *      (تاریخ و ساعت، یک بار برای همیشه). بدون این، ورود در گام بعدی
 *      رد می‌شود؛ یعنی پذیرفتن قوانین یک واقعیتِ ثبت‌شده در دیتابیس
 *      است و نه فقط مخفی‌شدن یک مودال در مرورگر.
 *   ۲) bkw_login_complete — همان لحظه‌ای که نشست واقعی وردپرس ساخته
 *      می‌شود.
 *
 * چیزی که به هر دو فرستاده می‌شود «بلیت» است، نه شمارهٔ موبایل: کد
 * تأیید پیش از باز شدن این مودال سنجیده و مصرف شده و سرور به‌جایش یک
 * بلیت یک‌بارمصرف ده‌دقیقه‌ای داده است. assets/js/bakery-login.js آن را
 * روی data-bkw-login-ticket ریشهٔ ویجت گذاشته و همین‌جا خوانده می‌شود.
 * یعنی این مودال هیچ‌وقت خودش تصمیم نمی‌گیرد چه کسی وارد شود — فقط
 * بلیتی را خرج می‌کند که سرور قبلاً صادر کرده.
 *
 * localStorage دیگر تصمیم نمی‌گیرد این مودال باز شود یا نه؛ آن را
 * سرور در پاسخ bkw_login_verify می‌گوید. برای مصرف مستقل (بدون
 * data-redirect-url) که کاربرِ شناخته‌شده‌ای در کار نیست، همچنان
 * localStorage تنها حافظهٔ موجود است.
 */
(function () {
    'use strict';

    /**
     * window.bkwLogin روی اسکریپت bakery-login لوکالایز شده (رجوع کن به
     * Plugin::register_scripts())؛ چون این مودال فقط زمانی
     * data-redirect-url دارد که تعبیه‌شدهٔ همان ویجت Login باشد، آن
     * اسکریپت همیشه هم‌زمان در صفحه لود شده و window.bkwLogin موجود است.
     */
    function callLoginAjax(action, ticket, onDone) {
        if (!window.bkwLogin || !window.bkwLogin.ajaxUrl) {
            onDone(false);
            return;
        }

        var body = new URLSearchParams();
        body.set('action', action);
        body.set('nonce', window.bkwLogin.nonce);
        body.set('ticket', ticket);

        fetch(window.bkwLogin.ajaxUrl, {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: body.toString(),
        })
            .then(function (response) {
                return response.json();
            })
            .then(function (json) {
                onDone(Boolean(json && json.success));
            })
            .catch(function () {
                onDone(false);
            });
    }

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

            // فقط زمانی تنظیم می‌شود که این مودال در ویجت Login تعبیه شده
            // باشد (رجوع کن به Terms_Modal_Controls::render_terms_modal)؛
            // مصرف مستقل چنین ویژگی‌ای ندارد و به‌جایش فقط پرده را می‌بندد.
            var redirectUrl = overlay.getAttribute('data-redirect-url');
            if (redirectUrl) {
                var loginRoot = overlay.closest('.bkw-login');
                var ticket = loginRoot ? loginRoot.getAttribute('data-bkw-login-ticket') || '' : '';

                acceptBtn.disabled = true;

                // ثبت پذیرش، بعد ورود. ترتیبشان مهم است: اکشن دوم بدون
                // پذیرشِ ثبت‌شده رد می‌کند.
                callLoginAjax('bkw_login_terms', ticket, function (recorded) {
                    if (!recorded) {
                        failed();
                        return;
                    }

                    callLoginAjax('bkw_login_complete', ticket, function (ok) {
                        if (ok) {
                            window.location.href = redirectUrl;
                            return;
                        }

                        failed();
                    });
                });

                return;
            }

            // مصرف مستقل: کاربر شناخته‌شده‌ای در کار نیست که پذیرشش را
            // روی حسابش ثبت کنیم، پس localStorage تنها حافظهٔ موجود
            // است. در مسیر ورود بالا، این کار را سرور کرده و رسیدن به
            // اینجا اصلاً ممکن نیست.
            try {
                window.localStorage.setItem(storageKey, 'accepted');
            } catch (e) {
                // در حالت خصوصی/مرورگر محدودشده در دسترس نیست؛ فقط
                // حافظهٔ «قبلاً پذیرفته» کار نمی‌کند و کاربر دفعهٔ بعد
                // دوباره می‌بیند — مسدودکننده نیست.
            }

            overlay.style.display = 'none';
            document.documentElement.classList.remove('bkw-panel-open');
        });

        /**
         * یا بلیت منقضی شده (کاربر مودال را خیلی طولانی باز گذاشته) یا
         * حساب بین سنجش کد و همین لحظه پاک شده. مودال بسته می‌شود و
         * کاربر در همان صفحهٔ ورود با پیام خطا می‌ماند تا از ابتدا تلاش
         * کند — به‌جای ریدایرکتی که دوباره قفلش می‌کند.
         */
        function failed() {
            var loginRoot = overlay.closest('.bkw-login');

            acceptBtn.disabled = false;
            overlay.hidden = true;
            overlay.style.display = 'none';
            document.documentElement.classList.remove('bkw-panel-open');

            var fieldError = loginRoot ? loginRoot.querySelector('[data-bkw-login-error]') : null;
            if (fieldError) {
                fieldError.hidden = false;
            }
        }
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
