/**
 * سایدبار سبد خرید: باز/بسته‌شدن (دقیقاً همان الگوی پنل کشویی موبایل
 * bakery-header.js: پرده + Escape + کلیک بیرون)، تعامل AJAX تعداد هر
 * ردیف (همان دو اکشن admin-ajax ویجت افزودن به سبد:
 * bkw_add_to_cart/bkw_set_cart_qty، رجوع کن به includes/bakery/cart-ajax.php)،
 * و خودِ پرداخت.
 *
 * پرداخت همین‌جا تمام می‌شود: دکمهٔ «ثبت سفارش» یک لینک به صفحهٔ
 * تسویه‌حساب نیست، بلکه اکشن bkw_place_order را صدا می‌زند
 * (Bakery_Credit\Integration\DirectCheckout) که سفارش را می‌سازد، اعتبار
 * را اتمیک کسر می‌کند و سبد را خالی می‌کند. صفحهٔ چک‌اوت ووکامرس در این
 * فروشگاه چیزی برای پرسیدن ندارد — کاربر از قبل تعریف‌شده و لاگین است،
 * ارسال و مالیات وجود ندارد و تنها روش پرداخت هم اعتبار ماهانه است.
 *
 * باز شدن از هیچ ویجت دیگری خبر ندارد: فقط سراسر صفحه منتظر کلیک روی
 * `.bkw-account-bar__cart` (پیل مشترک هدر/نوار حساب کاربری) است. اگر
 * این سایدبار در صفحه نباشد، کلیک دست‌نخورده می‌ماند و لینک عادی (در
 * صورت وجود cart_url) طبق روال کار می‌کند.
 */
(function () {
    'use strict';

    function applyFragments(data) {
        if (data && data.fragments) {
            Object.keys(data.fragments).forEach(function (selector) {
                document.querySelectorAll(selector).forEach(function (el) {
                    var template = document.createElement('template');
                    template.innerHTML = data.fragments[selector].trim();
                    var replacement = template.content.firstElementChild;
                    if (replacement) {
                        el.replaceWith(replacement);
                    }
                });
            });
        }

        if (data && undefined !== data.cart_count) {
            updateCartBadge(data.cart_count);
        }

        document.body.dispatchEvent(new CustomEvent('wc_fragment_refresh', { bubbles: true }));
    }

    var PERSIAN_DIGITS = ['۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹'];

    function toPersianDigits(value) {
        return String(value).replace(/[0-9]/g, function (digit) {
            return PERSIAN_DIGITS[digit];
        });
    }

    /**
     * شمارندهٔ بج سبد در پیل هدر/نوار حساب کاربری را همه‌جای صفحه
     * به‌روز می‌کند — رجوع کن به یادداشت مشابه در bakery-add-to-cart.js.
     */
    function updateCartBadge(count) {
        count = parseInt(count, 10) || 0;

        document.querySelectorAll('[data-bkw-cart-badge]').forEach(function (badge) {
            var showZero = '1' === badge.getAttribute('data-show-zero');
            badge.style.display = count > 0 || showZero ? '' : 'none';
            badge.textContent = toPersianDigits(count);
        });
    }

    function setupSidebar(sidebar) {
        var overlay = sidebar.querySelector('[data-bkw-cart-overlay]');
        var panel = sidebar.querySelector('[data-bkw-cart-panel]');
        // بیش از یکی است: دکمهٔ بستنِ سربرگ، و دکمهٔ بستنِ پیام «سفارش ثبت شد».
        var closeButtons = sidebar.querySelectorAll('[data-bkw-cart-close]');

        if (!overlay || !panel) {
            return;
        }

        function isOpen() {
            return panel.classList.contains('is-open');
        }

        function open() {
            panel.classList.add('is-open');
            overlay.classList.add('is-open');
            document.documentElement.classList.add('bkw-panel-open');
        }

        function close() {
            panel.classList.remove('is-open');
            overlay.classList.remove('is-open');
            document.documentElement.classList.remove('bkw-panel-open');

            // حالت «سفارش ثبت شد» فقط تا بسته‌شدن پنل زندگی می‌کند؛ دفعهٔ
            // بعد کاربر باید سبد (خالی یا پرشدهٔ دوباره) را ببیند، نه
            // تأیید سفارش قبلی.
            var success = sidebar.querySelector('[data-bkw-cart-success]');
            var error = sidebar.querySelector('[data-bkw-cart-error]');

            sidebar.classList.remove('is-placed');
            if (success) {
                success.hidden = true;
            }
            if (error) {
                error.hidden = true;
            }
        }

        sidebar.bkwCartSidebarOpen = open;

        closeButtons.forEach(function (closeBtn) {
            closeBtn.addEventListener('click', close);
        });

        overlay.addEventListener('click', close);

        document.addEventListener('keydown', function (event) {
            if ('Escape' === event.key && isOpen()) {
                close();
            }
        });

        // اگر هنگام باز بودن سایدبار، یک کلیک روی افزودن به سبد در جای
        // دیگر صفحه سبد را خالی و دوباره پر کرد و به‌طور کامل جایگزین
        // شد، فرگمنت‌های تازه از قبل توسط applyFragments اعمال شده‌اند؛
        // این‌جا کاری لازم نیست.
    }

    function setupQuantitySteps() {
        if (typeof bkwCartSidebar === 'undefined') {
            return;
        }

        document.addEventListener('click', function (event) {
            var button = event.target.closest('[data-bkw-cart-step]');
            if (!button || button.disabled) {
                return;
            }

            var itemRow = button.closest('[data-bkw-cart-item]');
            if (!itemRow || itemRow.classList.contains('is-loading')) {
                return;
            }

            event.preventDefault();

            var productId = itemRow.getAttribute('data-product-id');
            var isPlus = 'plus' === button.getAttribute('data-bkw-cart-step');

            var action = isPlus ? 'bkw_add_to_cart' : 'bkw_set_cart_qty';
            var payload = { product_id: productId, quantity: 1 };

            if (!isPlus) {
                var currentQty = parseInt(itemRow.getAttribute('data-qty'), 10) || 0;
                payload.quantity = Math.max(0, currentQty - 1);
            }

            // لایهٔ بلور فقط روی همین ردیف (دقیقاً مفهوم ویجت افزودن به
            // سبد)؛ اگر پاسخ موفق باشد، کل [data-bkw-cart-items] با
            // فرگمنت تازه جایگزین می‌شود و این ردیف قدیمی (با کلاسش)
            // اصلاً از DOM حذف می‌شود — پاک‌کردن کلاس در finally فقط
            // برای حالت خطا (که ردیف دست‌نخورده می‌ماند) واقعاً اثر دارد.
            itemRow.classList.add('is-loading');

            var body = new URLSearchParams(Object.assign({ action: action, nonce: bkwCartSidebar.nonce }, payload));

            fetch(bkwCartSidebar.ajaxUrl, {
                method: 'POST',
                credentials: 'same-origin',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: body.toString(),
            })
                .then(function (response) {
                    return response.json();
                })
                .then(function (response) {
                    if (response && response.success && response.data) {
                        applyFragments(response.data);
                    }
                })
                .catch(function () {
                    // شبکه/سرور خطا داد؛ چیزی تغییر نکرده، فقط بلور خاموش می‌شود.
                })
                .finally(function () {
                    itemRow.classList.remove('is-loading');
                });
        });
    }

    /**
     * دکمهٔ «ثبت سفارش» = خودِ پرداخت. یک درخواست به
     * Bakery_Credit\Integration\DirectCheckout می‌رود که سفارش را می‌سازد،
     * اعتبار را اتمیک کسر می‌کند و سبد را خالی می‌کند — بدون صفحهٔ
     * تسویه‌حساب. تا وقتی پاسخ نیامده دکمه قفل است تا دو کلیک پشت‌هم دو
     * سفارش نسازد (قید یکتاییِ دفتر هم لایهٔ دوم همین است).
     */
    function setupPlaceOrder() {
        if (typeof bkwCartSidebar === 'undefined') {
            return;
        }

        document.addEventListener('click', function (event) {
            var button = event.target.closest('[data-bkw-cart-checkout]');
            if (!button || button.disabled) {
                return;
            }

            event.preventDefault();

            var sidebar = button.closest('[data-bkw-cart-sidebar]');

            // در ادیتور المنتور، ادمین دارد دکمه را استایل می‌دهد؛ کلیکش
            // نباید سفارش واقعی ثبت کند و اعتبار خرج کند.
            if (sidebar && '1' === sidebar.getAttribute('data-edit-mode')) {
                return;
            }

            var errorBox = sidebar ? sidebar.querySelector('[data-bkw-cart-error]') : null;
            var successBox = sidebar ? sidebar.querySelector('[data-bkw-cart-success]') : null;

            if (errorBox) {
                errorBox.hidden = true;
            }

            button.disabled = true;
            button.classList.add('is-pending');

            var body = new URLSearchParams({
                action: 'bkw_place_order',
                nonce: bkwCartSidebar.placeOrderNonce,
            });

            fetch(bkwCartSidebar.ajaxUrl, {
                method: 'POST',
                credentials: 'same-origin',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: body.toString(),
            })
                .then(function (response) {
                    return response.json();
                })
                .then(function (response) {
                    if (!response || !response.success) {
                        showError(errorBox, response && response.data && response.data.message);
                        return;
                    }

                    applyFragments(response.data);
                    showSuccess(sidebar, successBox, response.data);
                })
                .catch(function () {
                    showError(errorBox, null);
                })
                .finally(function () {
                    button.disabled = false;
                    button.classList.remove('is-pending');
                });
        });
    }

    function showError(errorBox, message) {
        if (!errorBox) {
            return;
        }

        errorBox.textContent = message || bkwCartSidebar.genericError;
        errorBox.hidden = false;
    }

    function showSuccess(sidebar, successBox, data) {
        if (!sidebar || !successBox) {
            return;
        }

        var orderLine = successBox.querySelector('[data-bkw-cart-success-order]');
        if (orderLine && data && data.order_number) {
            var prefix = successBox.getAttribute('data-order-prefix') || '';
            orderLine.textContent = (prefix + ' ' + toPersianDigits(data.order_number)).trim();
        }

        // پنل به حالت «ثبت شد» می‌رود: فهرست و جمع کل و دکمه جای خود را
        // به همین پیام می‌دهند تا کاربر سبدِ خالی‌شده را به‌جای تأیید
        // نبیند. بستن سایدبار خودش این حالت را پس می‌گیرد.
        sidebar.classList.add('is-placed');
        successBox.hidden = false;
    }

    function setupOpenTrigger() {
        document.addEventListener('click', function (event) {
            var cartPill = event.target.closest('.bkw-account-bar__cart');
            if (!cartPill) {
                return;
            }

            var sidebar = document.querySelector('[data-bkw-cart-sidebar]');
            if (!sidebar || 'function' !== typeof sidebar.bkwCartSidebarOpen) {
                return; // این سایدبار در صفحه نیست؛ رفتار عادی (لینک) دست‌نخورده می‌ماند
            }

            event.preventDefault();
            sidebar.bkwCartSidebarOpen();
        });
    }

    function init() {
        document.querySelectorAll('[data-bkw-cart-sidebar]').forEach(setupSidebar);
        setupQuantitySteps();
        setupPlaceOrder();
        setupOpenTrigger();
    }

    if ('loading' === document.readyState) {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
