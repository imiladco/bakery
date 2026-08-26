/**
 * سایدبار سبد خرید: باز/بسته‌شدن (دقیقاً همان الگوی پنل کشویی موبایل
 * bakery-header.js: پرده + Escape + کلیک بیرون) و تعامل AJAX تعداد هر
 * ردیف (همان دو اکشن admin-ajax ویجت افزودن به سبد:
 * bkw_add_to_cart/bkw_set_cart_qty، رجوع کن به includes/bakery/cart-ajax.php).
 *
 * باز شدن از هیچ ویجت دیگری خبر ندارد: فقط سراسر صفحه منتظر کلیک روی
 * `.bkw-account-bar__cart` (پیل مشترک هدر/نوار حساب کاربری) است. اگر
 * این سایدبار در صفحه نباشد، کلیک دست‌نخورده می‌ماند و لینک عادی (در
 * صورت وجود cart_url) طبق روال کار می‌کند.
 */
(function () {
    'use strict';

    function applyFragments(fragments) {
        if (!fragments) {
            return;
        }

        Object.keys(fragments).forEach(function (selector) {
            document.querySelectorAll(selector).forEach(function (el) {
                var template = document.createElement('template');
                template.innerHTML = fragments[selector].trim();
                var replacement = template.content.firstElementChild;
                if (replacement) {
                    el.replaceWith(replacement);
                }
            });
        });

        document.body.dispatchEvent(new CustomEvent('wc_fragment_refresh', { bubbles: true }));
    }

    function setupSidebar(sidebar) {
        var overlay = sidebar.querySelector('[data-bkw-cart-overlay]');
        var panel = sidebar.querySelector('[data-bkw-cart-panel]');
        var closeBtn = sidebar.querySelector('[data-bkw-cart-close]');

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
        }

        sidebar.bkwCartSidebarOpen = open;

        if (closeBtn) {
            closeBtn.addEventListener('click', close);
        }

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
                        applyFragments(response.data.fragments);
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
        setupOpenTrigger();
    }

    if ('loading' === document.readyState) {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
