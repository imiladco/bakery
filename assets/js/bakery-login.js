/**
 * ویجت ورود — رفتار سمت کاربر و سه مرحلهٔ سرور.
 *
 * کد تأیید دیگر شبیه‌سازی‌شده نیست؛ وقتی ورود پیامکی در تنظیمات فعال
 * باشد (Bakery_Widgets\Otp_Settings) کد واقعاً با کاوه‌نگار فرستاده و
 * سمت سرور سنجیده می‌شود. سه اکشن admin-ajax به
 * Bakery_Widgets\Mobile_Login می‌رود:
 *
 *   - bkw_login_check   مرحلهٔ ۱ و دکمهٔ «ارسال مجدد»: شماره را می‌شناسد
 *                       و کد می‌فرستد. resendIn برمی‌گرداند — همان عددی
 *                       که شمارش معکوس با آن شروع می‌شود، پس تایمرِ روی
 *                       صفحه همان تایمری است که سرور اجرا می‌کند و این
 *                       دو هرگز از هم جدا نمی‌افتند.
 *   - bkw_login_verify  کد را می‌سنجد و یک «بلیت» یک‌بارمصرف می‌دهد. هنوز
 *                       لاگین نکرده است.
 *   - bkw_login_complete بلیت را خرج می‌کند و نشست واقعی وردپرس می‌سازد.
 *
 * چرا سنجش کد و لاگین از هم جدا شده‌اند: مودال قوانین باید بین‌شان
 * بنشیند. اگر همان لحظهٔ درست‌بودن کد لاگین می‌کردیم، کاربر می‌توانست
 * مودال را دور بزند (Site_Gate هر کاربر لاگین‌شده را رد می‌کند)؛ اگر
 * سنجش را تا بعد از مودال عقب می‌انداختیم، کاربر تازه بعد از پذیرفتن
 * قوانین می‌فهمید کدش غلط بوده. توضیح کاملش در داک‌بلاک Mobile_Login.
 *
 * بلیت در همین صفحه (متغیر loginTicket) نگه داشته می‌شود و
 * assets/js/bakery-terms-modal.js از طریق data-bkw-login-ticket روی
 * ریشهٔ ویجت به آن می‌رسد.
 *
 * هر جا کاربر واقعاً از این ویجت رد می‌شود، کوکی دسترسی سایت
 * (Bakery_Widgets\Site_Gate::COOKIE_NAME) هم ست می‌شود — همان کوکی‌ای که
 * دروازهٔ سمت PHP روی هر صفحهٔ دیگر سایت چک می‌کند.
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
     * به اکشن‌های Mobile_Login وصل می‌شود. bkwLogin از
     * Plugin::register_scripts() (wp_localize_script) می‌آید. نبودش
     * (مثلاً کش قدیمی اسکریپت) یعنی نمی‌توانیم چیزی را تأیید کنیم —
     * safe fail یعنی رد کردن، نه رد شدن بدون بررسی.
     */
    function callLoginAjax(action, params, onDone) {
        if (!window.bkwLogin || !window.bkwLogin.ajaxUrl) {
            onDone({ success: false });
            return;
        }

        var body = new URLSearchParams();
        body.set('action', action);
        body.set('nonce', window.bkwLogin.nonce);

        Object.keys(params).forEach(function (key) {
            body.set(key, params[key]);
        });

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
                onDone(json && 'object' === typeof json ? json : { success: false });
            })
            .catch(function () {
                onDone({ success: false });
            });
    }

    function payload(json) {
        return (json && json.data) || {};
    }

    var PERSIAN_DIGITS = ['۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹'];

    function toPersianDigits(value) {
        return String(value).replace(/[0-9]/g, function (digit) {
            return PERSIAN_DIGITS[Number(digit)];
        });
    }

    function formatCountdown(totalSeconds) {
        var minutes = Math.floor(totalSeconds / 60);
        var seconds = totalSeconds % 60;
        var text = String(minutes).padStart(2, '0') + ':' + String(seconds).padStart(2, '0');

        return toPersianDigits(text);
    }

    function setupLogin(root) {
        var otpLength = parseInt(root.getAttribute('data-otp-length'), 10) || 4;
        var countdownSeconds = parseInt(root.getAttribute('data-countdown-seconds'), 10) || 105;
        var redirectUrl = root.getAttribute('data-redirect-url') || '/';

        var step1 = root.querySelector('.bkw-login__step[data-step="1"]');
        var step2 = root.querySelector('.bkw-login__step[data-step="2"]');
        var mobileInput = root.querySelector('[data-bkw-login-field="mobile"]');
        var numberDisplay = root.querySelector('[data-bkw-login-number-display]');
        var otpInputs = Array.prototype.slice.call(root.querySelectorAll('[data-bkw-otp-digit]'));
        var countdownValue = root.querySelector('[data-bkw-countdown-value]');
        var resendBtn = root.querySelector('[data-bkw-login-resend]');
        var step1SubmitBtn = root.querySelector('[data-bkw-login-step1-submit]');
        var step2SubmitBtn = root.querySelector('[data-bkw-login-step2-submit]');
        var editNumberBtn = root.querySelector('[data-bkw-login-edit-number]');
        var fieldError = root.querySelector('[data-bkw-login-error]');
        var codeError = root.querySelector('[data-bkw-login-code-error]');

        if (!step1 || !step2) {
            return;
        }

        // متن پیش‌فرض «این شماره ثبت نشده» از تنظیمات ویجت می‌آید؛ وقتی
        // سرور پیام دقیق‌تری دارد (سقف ارسال، خطای پیامک) جایش می‌نشیند
        // و بعد دوباره به همین برمی‌گردد.
        var defaultFieldError = fieldError ? fieldError.textContent : '';

        function showError(element, message, fallback) {
            if (!element) {
                return;
            }

            element.textContent = message || fallback || '';
            element.hidden = false;
        }

        function hideError(element) {
            if (element) {
                element.hidden = true;
            }
        }

        function currentMobile() {
            return mobileInput ? mobileInput.value.trim() : '';
        }

        function currentCode() {
            return otpInputs
                .map(function (input) {
                    return input.value;
                })
                .join('');
        }

        function clearCode() {
            otpInputs.forEach(function (input) {
                input.value = '';
            });
        }

        var countdownTimer = null;
        var remainingSeconds = countdownSeconds;

        function stopCountdown() {
            if (null !== countdownTimer) {
                window.clearInterval(countdownTimer);
                countdownTimer = null;
            }
        }

        function renderCountdown() {
            if (countdownValue) {
                countdownValue.textContent = formatCountdown(remainingSeconds);
            }
        }

        /**
         * seconds از سرور می‌آید (resendIn). اگر نیامده باشد — پاسخ ناقص،
         * سرور قدیمی — به مقدار تنظیم‌شدهٔ ویجت برمی‌گردد.
         */
        function startCountdown(seconds) {
            stopCountdown();
            remainingSeconds = 'number' === typeof seconds && seconds > 0 ? seconds : countdownSeconds;
            renderCountdown();

            if (resendBtn) {
                resendBtn.disabled = true;
            }

            countdownTimer = window.setInterval(function () {
                remainingSeconds -= 1;

                if (remainingSeconds <= 0) {
                    remainingSeconds = 0;
                    renderCountdown();
                    stopCountdown();
                    if (resendBtn) {
                        resendBtn.disabled = false;
                    }
                    return;
                }

                renderCountdown();
            }, 1000);
        }

        function goToStep2(seconds) {
            if (numberDisplay) {
                var mobileValue = currentMobile();
                numberDisplay.textContent = '' !== mobileValue ? toPersianDigits(mobileValue) : '';
            }

            step1.hidden = true;
            step2.hidden = false;
            startCountdown(seconds);

            if (otpInputs.length > 0) {
                otpInputs[0].focus();
            }
        }

        function goToStep1() {
            stopCountdown();
            step2.hidden = true;
            step1.hidden = false;

            if (mobileInput) {
                mobileInput.focus();
            }
        }

        /** مرحلهٔ ۱ و دکمهٔ ارسال مجدد هر دو همین را صدا می‌زنند. */
        function requestCode(button, onSent) {
            button.disabled = true;

            callLoginAjax('bkw_login_check', { mobile: currentMobile() }, function (json) {
                button.disabled = false;

                if (json.success) {
                    onSent(payload(json).resendIn);
                    return;
                }

                showError(fieldError, payload(json).message, defaultFieldError);
            });
        }

        if (step1SubmitBtn) {
            step1SubmitBtn.addEventListener('click', function () {
                hideError(fieldError);
                hideError(codeError);

                requestCode(step1SubmitBtn, function (resendIn) {
                    clearCode();
                    goToStep2(resendIn);
                });
            });
        }

        if (editNumberBtn) {
            editNumberBtn.addEventListener('click', goToStep1);
        }

        if (resendBtn) {
            resendBtn.addEventListener('click', function () {
                if (resendBtn.disabled) {
                    return;
                }

                hideError(codeError);

                requestCode(resendBtn, function (resendIn) {
                    clearCode();
                    startCountdown(resendIn);

                    if (otpInputs.length > 0) {
                        otpInputs[0].focus();
                    }
                });
            });
        }

        /**
         * بلیتِ «کد درست بود». روی ریشهٔ ویجت هم نوشته می‌شود تا
         * assets/js/bakery-terms-modal.js — که کدِ جداگانه‌ای دارد —
         * بتواند بعد از پذیرفتن قوانین همان را خرج کند.
         */
        function storeTicket(ticket) {
            root.setAttribute('data-bkw-login-ticket', ticket || '');
        }

        function completeAndRedirect() {
            callLoginAjax('bkw_login_complete', { ticket: root.getAttribute('data-bkw-login-ticket') || '' }, function (json) {
                if (json.success) {
                    grantSiteAccess();
                    window.location.href = redirectUrl;
                    return;
                }

                step2SubmitBtn.disabled = false;
                showError(codeError, payload(json).message, '');
            });
        }

        if (step2SubmitBtn) {
            step2SubmitBtn.addEventListener('click', function () {
                hideError(codeError);
                step2SubmitBtn.disabled = true;

                callLoginAjax('bkw_login_verify', { mobile: currentMobile(), code: currentCode() }, function (json) {
                    if (!json.success) {
                        step2SubmitBtn.disabled = false;
                        showError(codeError, payload(json).message, '');
                        clearCode();
                        focusFirstEmpty();

                        // سرور کد را کشته (منقضی یا سقف تلاش) — تایمر
                        // ارسال مجدد باید همین حالا آزاد شود، وگرنه کاربر
                        // پشت شمارشی می‌ماند که دیگر معنایی ندارد.
                        if (payload(json).expired) {
                            stopCountdown();
                            remainingSeconds = 0;
                            renderCountdown();
                            if (resendBtn) {
                                resendBtn.disabled = false;
                            }
                        }

                        return;
                    }

                    storeTicket(payload(json).ticket);

                    var termsOverlay = root.querySelector('[data-bkw-terms]');
                    if (!termsOverlay) {
                        completeAndRedirect();
                        return;
                    }

                    var storageKey = termsOverlay.getAttribute('data-storage-key');
                    var alreadyAccepted = false;

                    try {
                        alreadyAccepted = 'accepted' === window.localStorage.getItem(storageKey);
                    } catch (e) {
                        // localStorage در دسترس نیست؛ فرض می‌شود هنوز
                        // پذیرفته نشده — مودال دوباره نشان داده می‌شود،
                        // مسدودکننده نیست.
                    }

                    if (alreadyAccepted) {
                        completeAndRedirect();
                        return;
                    }

                    // بلیت ده دقیقه اعتبار دارد، پس باز ماندن مودال
                    // مشکلی نمی‌سازد؛ دکمه هم قفل می‌ماند تا کسی دوباره
                    // «تأیید» نزند و بلیت دوم بگیرد.
                    termsOverlay.hidden = false;
                    document.documentElement.classList.add('bkw-panel-open');
                });
            });
        }

        /** اولین خانهٔ خالی، یا -۱ اگر همه پر باشند. */
        function firstEmptyIndex() {
            for (var i = 0; i < otpInputs.length; i++) {
                if ('' === otpInputs[i].value) {
                    return i;
                }
            }

            return -1;
        }

        function focusFirstEmpty() {
            var index = firstEmptyIndex();
            var target = otpInputs[-1 === index ? otpInputs.length - 1 : index];

            if (target) {
                target.focus();
                target.select();
            }
        }

        otpInputs.forEach(function (input, index) {
            /*
             * قاعده: کسی نباید در خانه‌ای بنویسد که جلوترش خانهٔ خالی
             * مانده. گذاشتن رقم در خانهٔ چهارم وقتی سومی خالی است، کدی
             * می‌سازد که هرگز درست نخواهد بود.
             *
             * پس فقط وقتی فوکوس جابه‌جا می‌شود که واقعاً خانهٔ خالیِ
             * عقب‌تری وجود داشته باشد. اگر همهٔ خانه‌ها پر باشند
             * (firstEmptyIndex برابر -۱) دست نمی‌زنیم — وگرنه کاربر
             * نمی‌توانست روی یک رقمِ غلط کلیک کند و اصلاحش کند.
             *
             * روی focus و نه click، تا رسیدن با Tab یا ضربهٔ لمسی هم
             * همین رفتار را داشته باشد. setTimeout لازم است چون
             * جابه‌جا کردن فوکوس وسط خودِ رویداد focus را بعضی
             * مرورگرها نادیده می‌گیرند.
             */
            input.addEventListener('focus', function () {
                var target = firstEmptyIndex();

                if (-1 === target || target >= index) {
                    return;
                }

                window.setTimeout(focusFirstEmpty, 0);
            });

            input.addEventListener('input', function () {
                var digits = input.value.replace(/[^0-9۰-۹]/g, '');
                input.value = digits.slice(-1);

                if ('' !== input.value && index < otpInputs.length - 1) {
                    otpInputs[index + 1].focus();
                }
            });

            input.addEventListener('keydown', function (event) {
                if ('Backspace' !== event.key) {
                    return;
                }

                // خانهٔ پرشده اول خودش خالی می‌شود؛ فقط وقتی از قبل
                // خالی بوده به عقب می‌رویم. بدون این، یک Backspace دو
                // رقم را می‌برد.
                if ('' !== input.value) {
                    return;
                }

                if (index > 0) {
                    event.preventDefault();
                    otpInputs[index - 1].value = '';
                    otpInputs[index - 1].focus();
                }
            });

            input.addEventListener('paste', function (event) {
                var pasted = (event.clipboardData || window.clipboardData).getData('text');
                var digits = pasted.replace(/[^0-9۰-۹]/g, '').split('');

                if (0 === digits.length) {
                    return;
                }

                event.preventDefault();

                // چسباندن همیشه از خانهٔ اول شروع می‌شود، نه از خانه‌ای
                // که کلیک شده: چیزی که کاربر می‌چسباند کلِ کد است.
                clearCode();

                for (var i = 0; i < digits.length && i < otpInputs.length; i++) {
                    otpInputs[i].value = digits[i];
                }

                focusFirstEmpty();
            });
        });
    }

    function init() {
        document.querySelectorAll('.bkw-login').forEach(setupLogin);
    }

    if ('loading' === document.readyState) {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
