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
 * فقط در همین حالت دوم، تأیید همچنین Bakery_Widgets\Mobile_Login را
 * صدا می‌زند (bkw_login_complete — همان لحظه‌ای که wp_set_auth_cookie
 * واقعی زده می‌شود؛ رجوع کن به یادداشت assets/js/bakery-login.js) و
 * سپس کوکی دسترسی سایت (Bakery_Widgets\Site_Gate::COOKIE_NAME) را ست
 * می‌کند — همان کوکی‌ای که دروازهٔ سمت PHP (includes/bakery/site-gate.php)
 * روی هر صفحهٔ دیگر سایت چک می‌کند.
 */
(function () {
    'use strict';

    var SITE_ACCESS_COOKIE = 'bkw_site_access';
    var SITE_ACCESS_MAX_AGE = 60 * 60 * 24 * 365; // یک سال

    function grantSiteAccess() {
        try {
            var secure = 'https:' === window.location.protocol ? '; Secure' : '';
            document.cookie = SITE_ACCESS_COOKIE + '=1; path=/; max-age=' + SITE_ACCESS_MAX_AGE + '; SameSite=Lax' + secure;
        } catch (e) {
            // اگر کوکی به هر دلیلی قابل نوشتن نباشد، ریدایرکت همچنان انجام
            // می‌شود؛ فقط دفعهٔ بعد دوباره به صفحهٔ ورود هدایت می‌شود —
            // مسدودکننده نیست.
        }
    }

    /**
     * window.bkwLogin روی اسکریپت bakery-login لوکالایز شده (رجوع کن به
     * Plugin::register_scripts())؛ چون این مودال فقط زمانی
     * data-redirect-url دارد که تعبیه‌شدهٔ همان ویجت Login باشد، آن
     * اسکریپت همیشه هم‌زمان در صفحه لود شده و window.bkwLogin موجود است.
     */
    function callLoginAjax(action, mobile, onDone) {
        if (!window.bkwLogin || !window.bkwLogin.ajaxUrl) {
            onDone(false);
            return;
        }

        var body = new URLSearchParams();
        body.set('action', action);
        body.set('nonce', window.bkwLogin.nonce);
        body.set('mobile', mobile);

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
                var loginRoot = overlay.closest('.bkw-login');
                var mobileInput = loginRoot ? loginRoot.querySelector('[data-bkw-login-field="mobile"]') : null;
                var mobile = mobileInput ? mobileInput.value.trim() : '';

                acceptBtn.disabled = true;

                callLoginAjax('bkw_login_complete', mobile, function (ok) {
                    if (ok) {
                        grantSiteAccess();
                        window.location.href = redirectUrl;
                        return;
                    }

                    // خیلی نادر: شماره بین شروع لاگین و همین لحظه از
                    // حساب کاربری پاک/عوض شده. مودال را می‌بندد و مسیر
                    // عادی «شماره ثبت نشده» را در همان صفحهٔ ورود دوباره
                    // نشان می‌دهد، به‌جای ریدایرکتی که دوباره قفلش می‌کند.
                    acceptBtn.disabled = false;
                    overlay.hidden = true;
                    overlay.style.display = 'none';
                    document.documentElement.classList.remove('bkw-panel-open');

                    var fieldError = loginRoot ? loginRoot.querySelector('[data-bkw-login-error]') : null;
                    if (fieldError) {
                        fieldError.hidden = false;
                    }
                });
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
