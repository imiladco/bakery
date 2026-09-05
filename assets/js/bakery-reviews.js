/**
 * مودال «ثبت امتیاز و نظر» — سراسری صفحه، مثل bakery-toast.js خودش را
 * در body می‌سازد و منتظر مارکاپ از سمت PHP نمی‌ماند، چون به هیچ ویجتی
 * تعلق ندارد.
 *
 * هر بار که صفحه بار می‌شود (برای کاربر واردشده)، از سرور می‌پرسد
 * «بعدی چیست؟» (bkw_next_review_prompt — رجوع کن به
 * Bakery_Widgets\Product_Reviews::next_pending). اگر چیزی مانده،
 * مودال را نشان می‌دهد؛ بعد از هر ثبت موفق، سرور مستقیم آیتمِ بعدی را
 * هم در همان پاسخ برمی‌گرداند (یک رفت‌وبرگشتِ کمتر) — اگر بود، مودال
 * با محتوای تازه باز می‌ماند (شمارنده جلو می‌رود)، وگرنه بسته می‌شود.
 *
 * سخت‌گیری (bkwReviews.strict) فقط دکمهٔ × را حذف/اضافه می‌کند — منطق
 * باز/بسته شدن یکی است، فقط راهِ دومِ بستن (× یا کلیک پرده یا Escape)
 * وقتی سخت‌گیرانه است اصلاً وصل نمی‌شود.
 */
(function () {
    'use strict';

    if (typeof bkwReviews === 'undefined') {
        return;
    }

    var STAR_ICON =
        '<svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg" aria-hidden="true" ' +
        'stroke-linejoin="round" stroke-linecap="round">' +
        '<path d="M12 3.5l2.6 5.27 5.82.85-4.21 4.1.99 5.79L12 16.9l-5.2 2.61.99-5.79-4.21-4.1 5.82-.85L12 3.5z"/>' +
        '</svg>';

    var modalEl = null;
    var starButtons = [];
    var selectedRating = 0;
    var current = null; // { order_id, product_id, ... } از پاسخ سرور

    function buildModal() {
        var overlay = document.createElement('div');
        overlay.className = 'bkw-review-modal';
        overlay.setAttribute('role', 'dialog');
        overlay.setAttribute('aria-modal', 'true');
        overlay.setAttribute('aria-label', bkwReviews.title);
        overlay.hidden = true;

        var closeBtn = bkwReviews.strict
            ? ''
            : '<button type="button" class="bkw-review-modal__close" data-bkw-review-close aria-label="بستن">' +
              '<svg viewBox="0 0 16 16" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">' +
              '<path d="M4 4l8 8M12 4l-8 8" stroke="#615249" stroke-width="2" stroke-linecap="round"/>' +
              '</svg></button>';

        var stars = '';
        for (var i = 1; i <= 5; i++) {
            stars +=
                '<button type="button" class="bkw-review-modal__star" data-star="' + i + '" ' +
                'aria-label="' + i + ' ستاره">' + STAR_ICON + '</button>';
        }

        overlay.innerHTML =
            '<div class="bkw-review-modal__card">' +
            '<div class="bkw-review-modal__header">' +
            closeBtn +
            '<div class="bkw-review-modal__title">' + escapeHtml(bkwReviews.title) + '</div>' +
            '</div>' +
            '<div class="bkw-review-modal__counter" data-bkw-review-counter hidden></div>' +
            '<div class="bkw-review-modal__product">' +
            '<div class="bkw-review-modal__product-meta">' +
            '<div class="bkw-review-modal__product-name" data-bkw-review-name></div>' +
            '<div class="bkw-review-modal__product-desc" data-bkw-review-desc></div>' +
            '</div>' +
            '</div>' +
            '<div class="bkw-review-modal__stars-section">' +
            '<div class="bkw-review-modal__stars-label">' + escapeHtml(bkwReviews.ratingLabel) + '</div>' +
            '<div class="bkw-review-modal__star-row" data-bkw-review-stars>' + stars + '</div>' +
            '<div class="bkw-review-modal__star-labels">' +
            '<span>' + escapeHtml(bkwReviews.bestLabel) + '</span>' +
            '<span>' + escapeHtml(bkwReviews.worstLabel) + '</span>' +
            '</div>' +
            '</div>' +
            '<div class="bkw-review-modal__comment-section">' +
            '<div class="bkw-review-modal__comment-label">' + escapeHtml(bkwReviews.commentLabel) + '</div>' +
            '<textarea class="bkw-review-modal__comment" data-bkw-review-comment rows="3"></textarea>' +
            '</div>' +
            '<div class="bkw-review-modal__actions">' +
            '<div class="bkw-review-modal__error" data-bkw-review-error hidden></div>' +
            '<button type="button" class="bkw-review-modal__submit" data-bkw-review-submit disabled>' +
            escapeHtml(bkwReviews.submitLabel) +
            '</button>' +
            '</div>' +
            '</div>';

        document.body.appendChild(overlay);

        starButtons = Array.prototype.slice.call(overlay.querySelectorAll('.bkw-review-modal__star'));

        starButtons.forEach(function (btn) {
            var value = parseInt(btn.getAttribute('data-star'), 10);

            btn.addEventListener('mouseenter', function () {
                paintStars(value);
            });

            btn.addEventListener('click', function () {
                selectedRating = value;
                paintStars(value);
                updateSubmitState();
            });
        });

        overlay.querySelector('[data-bkw-review-stars]').addEventListener('mouseleave', function () {
            paintStars(selectedRating);
        });

        var closeButton = overlay.querySelector('[data-bkw-review-close]');
        if (closeButton) {
            closeButton.addEventListener('click', close);
        }

        if (!bkwReviews.strict) {
            overlay.addEventListener('click', function (event) {
                if (event.target === overlay) {
                    close();
                }
            });

            document.addEventListener('keydown', function (event) {
                if ('Escape' === event.key && !overlay.hidden) {
                    close();
                }
            });
        }

        overlay.querySelector('[data-bkw-review-submit]').addEventListener('click', submit);

        modalEl = overlay;
        return overlay;
    }

    function escapeHtml(value) {
        var div = document.createElement('div');
        div.textContent = String(value == null ? '' : value);
        return div.innerHTML;
    }

    function paintStars(uptoValue) {
        starButtons.forEach(function (btn) {
            var value = parseInt(btn.getAttribute('data-star'), 10);
            btn.classList.toggle('is-filled', value <= uptoValue);
        });
    }

    function updateSubmitState() {
        var submitBtn = modalEl.querySelector('[data-bkw-review-submit]');
        submitBtn.disabled = selectedRating < 1;
    }

    function showError(message) {
        var errorEl = modalEl.querySelector('[data-bkw-review-error]');
        errorEl.textContent = message;
        errorEl.hidden = false;
    }

    function hideError() {
        modalEl.querySelector('[data-bkw-review-error]').hidden = true;
    }

    function populate(item) {
        current = item;
        selectedRating = 0;
        paintStars(0);
        updateSubmitState();
        hideError();

        modalEl.querySelector('[data-bkw-review-name]').textContent = item.product_name;
        modalEl.querySelector('[data-bkw-review-desc]').textContent = item.product_excerpt;
        modalEl.querySelector('[data-bkw-review-comment]').value = '';
        modalEl.querySelector('[data-bkw-review-comment]').placeholder =
            bkwReviews.commentPlaceholder.replace('%s', item.product_name);

        var counterEl = modalEl.querySelector('[data-bkw-review-counter]');
        if (item.total > 1) {
            counterEl.textContent = bkwReviews.counter
                .replace('%1$s', toPersianDigits(item.index))
                .replace('%2$s', toPersianDigits(item.total));
            counterEl.hidden = false;
        } else {
            counterEl.hidden = true;
        }
    }

    var PERSIAN_DIGITS = ['۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹'];

    function toPersianDigits(value) {
        return String(value).replace(/[0-9]/g, function (digit) {
            return PERSIAN_DIGITS[digit];
        });
    }

    function open(item) {
        var overlay = modalEl || buildModal();
        populate(item);

        overlay.hidden = false;
        requestAnimationFrame(function () {
            overlay.classList.add('is-visible');
        });
    }

    function close() {
        if (!modalEl) {
            return;
        }

        modalEl.classList.remove('is-visible');
        window.setTimeout(function () {
            modalEl.hidden = true;
        }, 240);
    }

    function fetchNext(onResult) {
        var body = new URLSearchParams({ action: 'bkw_next_review_prompt', nonce: bkwReviews.nonce });

        fetch(bkwReviews.ajaxUrl, {
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
                    onResult(response.data);
                }
            })
            .catch(function () {
                // شبکه/سرور خطا داد؛ مزاحم بازدید عادی نمی‌شویم، سکوت می‌کنیم.
            });
    }

    function submit() {
        if (selectedRating < 1 || !current) {
            showError(bkwReviews.ratingRequired);
            return;
        }

        hideError();

        var submitBtn = modalEl.querySelector('[data-bkw-review-submit]');
        submitBtn.disabled = true;

        var body = new URLSearchParams({
            action: 'bkw_submit_review',
            nonce: bkwReviews.nonce,
            order_id: String(current.order_id),
            product_id: String(current.product_id),
            rating: String(selectedRating),
            comment: modalEl.querySelector('[data-bkw-review-comment]').value,
        });

        fetch(bkwReviews.ajaxUrl, {
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
                    showError((response && response.data && response.data.message) || bkwReviews.genericError);
                    submitBtn.disabled = false;
                    return;
                }

                if (response.data.next) {
                    populate(response.data.next);
                } else {
                    close();
                }
            })
            .catch(function () {
                showError(bkwReviews.genericError);
                submitBtn.disabled = false;
            });
    }

    document.addEventListener('DOMContentLoaded', function () {
        fetchNext(function (data) {
            if (data && data.pending) {
                open(data);
            }
        });
    });
})();
