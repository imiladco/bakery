/**
 * ویجت سابقهٔ سفارش‌ها — فقط دکمهٔ «لغو سفارش».
 *
 * بقیهٔ ویجت کاملاً سمت سرور رندر می‌شود و صفحه‌بندی‌اش لینک واقعی است،
 * پس این فایل عمداً کوچک است: یک درخواست به اکشن bkw_cancel_order
 * (Bakery_Widgets\Order_Cancellation) و به‌روزرسانی همان کارت.
 *
 * مهلت لغو دوباره سمت سرور سنجیده می‌شود — پنهان‌بودن یا نبودن این دکمه
 * هیچ چیزی را تضمین نمی‌کند. اگر مهلت درست بین بارگذاری صفحه و کلیک
 * تمام شده باشد، همان پیام سرور همین‌جا نشان داده می‌شود.
 */
(function () {
    'use strict';

    function setup() {
        if (typeof bkwOrderHistory === 'undefined') {
            return;
        }

        document.addEventListener('click', function (event) {
            var button = event.target.closest('[data-bkw-order-cancel]');
            if (!button || button.disabled) {
                return;
            }

            var card = button.closest('[data-bkw-order-card]');
            if (!card) {
                return;
            }

            event.preventDefault();
            cancelOrder(button, card);
        });
    }

    function cancelOrder(button, card) {
        var label = button.textContent;
        var pending = button.getAttribute('data-pending-text');

        button.disabled = true;
        if (pending) {
            button.textContent = pending;
        }

        var body = new URLSearchParams({
            action: 'bkw_cancel_order',
            nonce: bkwOrderHistory.nonce,
            order_id: card.getAttribute('data-order-id') || '',
        });

        fetch(bkwOrderHistory.ajaxUrl, {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: body.toString(),
        })
            .then(function (response) {
                return response.json();
            })
            .then(function (response) {
                if (response && response.success) {
                    // وضعیت سفارش، اعتبار کاربر و حتی این‌که کارت هنوز
                    // دکمهٔ لغو داشته باشد یا نه، همه سمت سرور عوض شده‌اند؛
                    // بارگذاری دوباره صادق‌ترین راه نشان‌دادن آن است، نه
                    // بازسازی دستی همین یک کارت در مرورگر.
                    window.location.reload();
                    return;
                }

                showError(card, response && response.data && response.data.message);
                restore(button, label);
            })
            .catch(function () {
                showError(card, null);
                restore(button, label);
            });
    }

    function restore(button, label) {
        button.disabled = false;
        button.textContent = label;
    }

    function showError(card, message) {
        var note = card.querySelector('.bkw-order-history__cancel-note');
        if (note) {
            note.textContent = message || bkwOrderHistory.genericError;
        }
    }

    if ('loading' === document.readyState) {
        document.addEventListener('DOMContentLoaded', setup);
    } else {
        setup();
    }
})();
