/**
 * تعامل ویجت «افزودن به سبد»: افزودن اولیه، افزایش/کاهش تعداد — همه از
 * طریق AJAX ووکامرس (admin-ajax، اکشن‌های bkw_add_to_cart/bkw_set_cart_qty
 * در includes/bakery/cart-ajax.php)، بدون رفرش صفحه. مثل bakery-header.js
 * وابستگی خارجی ندارد (fetch بومی به‌جای jQuery).
 */
(function () {
    'use strict';

    if (typeof bkwAtc === 'undefined') {
        return;
    }

    /**
     * فرگمنت‌های ووکامرس (مثل شمارندهٔ مینی‌سبد) را در صفحه جایگزین می‌کند
     * و رویداد استاندارد wc_fragment_refresh را هم پرتاب می‌کند تا هر
     * اسکریپت دیگری (مثلاً wc-cart-fragments خودِ قالب) هم خودش را به‌روز کند.
     */
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
     * شمارندهٔ بج سبد در پیل هدر/نوار حساب کاربری (Traits\Account_Actions_Controls)
     * را همه‌جای صفحه به‌روز می‌کند — آن بج همیشه در DOM هست (حتی وقتی
     * صفر و باید مخفی بماند، فقط با display:none)، دقیقاً برای همین.
     */
    function updateCartBadge(count) {
        count = parseInt(count, 10) || 0;

        document.querySelectorAll('[data-bkw-cart-badge]').forEach(function (badge) {
            var showZero = '1' === badge.getAttribute('data-show-zero');
            badge.style.display = count > 0 || showZero ? '' : 'none';
            badge.textContent = toPersianDigits(count);
        });
    }

    function setLoading(root, isLoading) {
        root.classList.toggle('is-loading', isLoading);
    }

    function renderQty(root, qty, max) {
        qty = parseInt(qty, 10) || 0;
        max = parseInt(max, 10);
        if (isNaN(max)) {
            max = -1;
        }

        root.setAttribute('data-qty', String(qty));
        root.setAttribute('data-max', String(max));

        var count = root.querySelector('.bkw-atc__count');
        if (count) {
            count.textContent = String(qty);
        }

        var plusBtn = root.querySelector('.bkw-atc__step--plus');
        if (plusBtn) {
            plusBtn.disabled = -1 !== max && qty >= max;
        }

        root.setAttribute('data-state', qty > 0 ? 'qty' : 'add');
    }

    /**
     * یک اکشن AJAX را اجرا می‌کند؛ در تمام طول درخواست لایهٔ بلور روشن
     * است و با پاسخ (موفق یا نه) خاموش می‌شود.
     */
    function callAjax(root, action, payload, onSuccess) {
        setLoading(root, true);

        var body = new URLSearchParams(Object.assign({ action: action, nonce: bkwAtc.nonce }, payload));

        fetch(bkwAtc.ajaxUrl, {
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
                    onSuccess(response.data);
                    applyFragments(response.data);
                }
            })
            .catch(function () {
                // شبکه/سرور خطا داد؛ حالت قبلی دست‌نخورده می‌ماند، فقط بلور خاموش می‌شود.
            })
            .finally(function () {
                setLoading(root, false);
            });
    }

    document.addEventListener('click', function (event) {
        var addBtn = event.target.closest('.bkw-atc__btn--add');
        var plusBtn = event.target.closest('.bkw-atc__step--plus');
        var minusBtn = event.target.closest('.bkw-atc__step--minus');

        if (!addBtn && !plusBtn && !minusBtn) {
            return;
        }

        event.preventDefault();

        var button = addBtn || plusBtn || minusBtn;
        var root = button.closest('.bkw-atc');
        if (!root || root.classList.contains('is-loading') || button.disabled) {
            return;
        }

        var productId = root.getAttribute('data-product-id');

        if (minusBtn) {
            var nextQty = Math.max(0, (parseInt(root.getAttribute('data-qty'), 10) || 0) - 1);
            callAjax(root, 'bkw_set_cart_qty', { product_id: productId, quantity: nextQty }, function (payload) {
                renderQty(root, payload.qty, payload.max);
            });
            return;
        }

        callAjax(root, 'bkw_add_to_cart', { product_id: productId, quantity: 1 }, function (payload) {
            renderQty(root, payload.qty, payload.max);

            if (payload.blocked_reason && window.bkwToast) {
                window.bkwToast.forReason(payload.blocked_reason);
            }

            // یک CustomEvent بومی — نه jQuery.trigger — پس یک شنوندهٔ jQuery
            // (رایج در قالب‌های ووکامرس) خودِ رویداد را می‌گیرد، ولی
            // fragments/cart_hash را به‌عنوان آرگومان دومِ jQuery نمی‌بیند؛
            // آن‌ها را از event.detail بخواند. به‌روزرسانی واقعی DOM
            // (فرگمنت‌ها) در applyFragments از قبل انجام شده.
            document.body.dispatchEvent(new CustomEvent('added_to_cart', { bubbles: true, detail: payload }));
        });
    });
})();
