/**
 * ویجت ورود — رفتار سمت کاربر (جابه‌جایی مرحله، پیش‌رفتن خودکار
 * خانه‌های کد تأیید، شمارش معکوس، ارسال مجدد). کد تأیید طبق درخواست
 * صریح کارفرما همچنان کاملاً شبیه‌سازی‌شده است (هر کدی قبول می‌شود، بدون
 * پیامک واقعی) — اما «این شماره متعلق به کیست» دیگر ظاهری نیست: دو
 * درخواست AJAX به Bakery_Widgets\Mobile_Login می‌رود:
 *
 *   - مرحلهٔ ۱ (bkw_login_check): فقط بررسی می‌کند شماره برای کاربری
 *     ثبت شده یا نه؛ اگر نه، همان‌جا خطا نشان داده می‌شود و کاربر وقتش
 *     با تئاتر کد تأیید تلف نمی‌شود (ثبت‌نام خودکار وجود ندارد — فقط
 *     شماره‌هایی که مدیر از قبل تعریف کرده).
 *   - لحظهٔ گرفتن دسترسی (bkw_login_complete): همان‌جا wp_set_auth_cookie
 *     واقعی هم زده می‌شود — عمداً نه زودتر از مرحلهٔ ۱، چون
 *     Site_Gate::maybe_redirect() هر کاربر واقعاً لاگین‌شده را بی‌قیدوشرط
 *     رد می‌کند و لاگین واقعی زودتر یعنی رد شدن از کنار مودال قوانین.
 *
 * کلیک روی دکمهٔ مرحلهٔ ۲ مستقیم ریدایرکت نمی‌کند: اول مودال قوانینِ
 * تعبیه‌شده در همین صفحه (data-bkw-terms، رجوع کن به
 * Traits\Terms_Modal_Controls::render_terms_modal) را پیدا می‌کند. اگر
 * قبلاً در همین مرورگر پذیرفته شده (localStorage)، همین‌جا bkw_login_complete
 * را صدا می‌زند و بعد به data-redirect-url می‌رود؛ وگرنه مودال را
 * نمایان می‌کند و منتظر می‌ماند — خودِ assets/js/bakery-terms-modal.js
 * پس از چک‌باکس+تأیید هم bkw_login_complete و هم ریدایرکت را انجام
 * می‌دهد.
 *
 * هر جا کاربر واقعاً از این ویجت رد می‌شود (لاگین + تأیید قوانین)، کوکی
 * دسترسی سایت (Bakery_Widgets\Site_Gate::COOKIE_NAME) هم ست می‌شود —
 * همان کوکی‌ای که دروازهٔ سمت PHP (includes/bakery/site-gate.php) روی هر
 * صفحهٔ دیگر سایت چک می‌کند تا دیگر کاربر را دوباره به ورود نفرستد.
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
     * به Mobile_Login::ajax_check/ajax_complete وصل می‌شود. bkwLogin از
     * Plugin::register_scripts() (wp_localize_script) می‌آید. نبودش
     * (مثلاً کش قدیمی اسکریپت) یعنی نمی‌توانیم شماره را تأیید کنیم —
     * safe fail یعنی رد کردن، نه رد شدن بدون بررسی.
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

        if (!step1 || !step2) {
            return;
        }

        function hideFieldError() {
            if (fieldError) {
                fieldError.hidden = true;
            }
        }

        function showFieldError() {
            if (fieldError) {
                fieldError.hidden = false;
            }
        }

        function currentMobile() {
            return mobileInput ? mobileInput.value.trim() : '';
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

        function startCountdown() {
            stopCountdown();
            remainingSeconds = countdownSeconds;
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

        function goToStep2() {
            if (numberDisplay) {
                var mobileValue = mobileInput ? mobileInput.value.trim() : '';
                numberDisplay.textContent = '' !== mobileValue ? toPersianDigits(mobileValue) : '';
            }

            step1.hidden = true;
            step2.hidden = false;
            startCountdown();

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

        if (step1SubmitBtn) {
            step1SubmitBtn.addEventListener('click', function () {
                hideFieldError();
                step1SubmitBtn.disabled = true;

                callLoginAjax('bkw_login_check', currentMobile(), function (ok) {
                    step1SubmitBtn.disabled = false;

                    if (ok) {
                        goToStep2();
                    } else {
                        showFieldError();
                    }
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

                startCountdown();
                if (otpInputs.length > 0) {
                    otpInputs[0].focus();
                }
            });
        }

        function completeLoginAndRedirect() {
            step2SubmitBtn.disabled = true;

            callLoginAjax('bkw_login_complete', currentMobile(), function (ok) {
                step2SubmitBtn.disabled = false;

                if (ok) {
                    grantSiteAccess();
                    window.location.href = redirectUrl;
                } else {
                    // خیلی نادر: شماره بین مرحلهٔ ۱ و همین لحظه از حساب
                    // کاربری پاک/عوض شده. برمی‌گردد مرحلهٔ ۱ تا دوباره
                    // بررسی شود، به‌جای ریدایرکت به صفحه‌ای که دوباره
                    // قفلش می‌کند.
                    goToStep1();
                    showFieldError();
                }
            });
        }

        if (step2SubmitBtn) {
            step2SubmitBtn.addEventListener('click', function () {
                var termsOverlay = root.querySelector('[data-bkw-terms]');

                if (!termsOverlay) {
                    completeLoginAndRedirect();
                    return;
                }

                var storageKey = termsOverlay.getAttribute('data-storage-key');
                var alreadyAccepted = false;

                try {
                    alreadyAccepted = 'accepted' === window.localStorage.getItem(storageKey);
                } catch (e) {
                    // localStorage در دسترس نیست؛ فرض می‌شود هنوز پذیرفته
                    // نشده — مودال دوباره نشان داده می‌شود، مسدودکننده نیست.
                }

                if (alreadyAccepted) {
                    completeLoginAndRedirect();
                    return;
                }

                termsOverlay.hidden = false;
                document.documentElement.classList.add('bkw-panel-open');
            });
        }

        otpInputs.forEach(function (input, index) {
            input.addEventListener('input', function () {
                var digits = input.value.replace(/[^0-9۰-۹]/g, '');
                input.value = digits.slice(-1);

                if ('' !== input.value && index < otpInputs.length - 1) {
                    otpInputs[index + 1].focus();
                }
            });

            input.addEventListener('keydown', function (event) {
                if ('Backspace' === event.key && '' === input.value && index > 0) {
                    otpInputs[index - 1].focus();
                    otpInputs[index - 1].value = '';
                }
            });

            input.addEventListener('paste', function (event) {
                var pasted = (event.clipboardData || window.clipboardData).getData('text');
                var digits = pasted.replace(/[^0-9۰-۹]/g, '').split('');

                if (0 === digits.length) {
                    return;
                }

                event.preventDefault();

                for (var i = 0; i < digits.length && index + i < otpInputs.length; i++) {
                    otpInputs[index + i].value = digits[i];
                }

                var nextIndex = Math.min(index + digits.length, otpInputs.length - 1);
                otpInputs[nextIndex].focus();
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
