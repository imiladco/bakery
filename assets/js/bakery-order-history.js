/**
 * ویجت سابقهٔ سفارش‌ها — فقط لغو سفارش.
 *
 * بقیهٔ ویجت کاملاً سمت سرور رندر می‌شود و صفحه‌بندی‌اش لینک واقعی است،
 * پس این فایل عمداً کوچک است: باز کردن مودال تأیید، یک درخواست به اکشن
 * bkw_cancel_order (Bakery_Widgets\Order_Cancellation) و بارگذاری دوباره.
 *
 * دکمهٔ «لغو سفارش» روی کارت خودش هیچ کاری نمی‌کند جز باز کردن مودال —
 * لغو برگشت‌پذیر نیست، پس مثل ثبت سفارش یک تأیید نهایی می‌گیرد
 * (Traits\Confirm_Modal_Controls، همان مودالی که سایدبار سبد هم دارد).
 * مودال یکی است برای کل ویجت و شناسهٔ سفارشِ در دست، موقع باز شدن روی
 * خودش نوشته می‌شود.
 *
 * مهلت لغو دوباره سمت سرور سنجیده می‌شود — پنهان‌بودن یا نبودن این دکمه
 * هیچ چیزی را تضمین نمی‌کند. اگر مهلت درست بین بارگذاری صفحه و کلیک
 * تمام شده باشد، همان پیام سرور داخل مودال نشان داده می‌شود.
 */
(function () {
    'use strict';

    function modalOf(root) {
        return root.querySelector('[data-bkw-confirm="order-cancel"]');
    }

    function setup() {
        if (typeof bkwOrderHistory === 'undefined') {
            return;
        }

        document.addEventListener('click', function (event) {
            var openBtn = event.target.closest('[data-bkw-order-cancel]');
            if (openBtn && !openBtn.disabled) {
                event.preventDefault();
                openConfirm(openBtn);
                return;
            }

            var cancelBtn = event.target.closest('[data-bkw-confirm-cancel]');
            if (cancelBtn) {
                var root = cancelBtn.closest('[data-bkw-order-history]');
                if (root) {
                    event.preventDefault();
                    closeConfirm(modalOf(root));
                }
                return;
            }

            var acceptBtn = event.target.closest('[data-bkw-confirm-accept]');
            if (acceptBtn && !acceptBtn.disabled) {
                var historyRoot = acceptBtn.closest('[data-bkw-order-history]');
                if (historyRoot) {
                    event.preventDefault();
                    cancelOrder(acceptBtn, modalOf(historyRoot));
                }
            }
        });

        // Escape فقط مودال را می‌بندد؛ هیچ چیز دیگری در این ویجت به آن
        // وابسته نیست.
        document.addEventListener('keydown', function (event) {
            if ('Escape' !== event.key) {
                return;
            }

            document.querySelectorAll('[data-bkw-confirm="order-cancel"]').forEach(function (modal) {
                if (!modal.hidden) {
                    closeConfirm(modal);
                }
            });
        });
    }

    function openConfirm(button) {
        var card = button.closest('[data-bkw-order-card]');
        var root = button.closest('[data-bkw-order-history]');
        var modal = root ? modalOf(root) : null;

        if (!card || !modal) {
            return;
        }

        // مودال مشترک است، پس هر بار باید بداند دربارهٔ کدام سفارش است.
        modal.setAttribute('data-order-id', card.getAttribute('data-order-id') || '');

        showError(modal, null, true);
        modal.hidden = false;
        document.documentElement.classList.add('bkw-panel-open');

        var accept = modal.querySelector('[data-bkw-confirm-accept]');
        if (accept) {
            accept.disabled = false;
            accept.classList.remove('is-pending');
            accept.focus();
        }
    }

    function closeConfirm(modal) {
        if (!modal) {
            return;
        }

        modal.hidden = true;
        document.documentElement.classList.remove('bkw-panel-open');
    }

    function cancelOrder(button, modal) {
        if (!modal) {
            return;
        }

        button.disabled = true;
        button.classList.add('is-pending');
        showError(modal, null, true);

        var body = new URLSearchParams({
            action: 'bkw_cancel_order',
            nonce: bkwOrderHistory.nonce,
            order_id: modal.getAttribute('data-order-id') || '',
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

                showError(modal, response && response.data && response.data.message);
                restore(button);
            })
            .catch(function () {
                showError(modal, null);
                restore(button);
            });
    }

    function restore(button) {
        button.disabled = false;
        button.classList.remove('is-pending');
    }

    /**
     * خطا داخل خودِ مودال نشان داده می‌شود، چون همان‌جاست که کاربر
     * منتظر نتیجه ایستاده. hide=true فقط پاکش می‌کند.
     */
    function showError(modal, message, hide) {
        var note = modal.querySelector('[data-bkw-order-confirm-error]');
        if (!note) {
            return;
        }

        if (hide) {
            note.hidden = true;
            note.textContent = '';
            return;
        }

        note.textContent = message || bkwOrderHistory.genericError;
        note.hidden = false;
    }

    if ('loading' === document.readyState) {
        document.addEventListener('DOMContentLoaded', setup);
    } else {
        setup();
    }
})();
