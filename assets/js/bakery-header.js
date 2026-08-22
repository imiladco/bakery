/**
 * Mobile off-canvas panel toggle for the bakery-header widget. Plain
 * vanilla JS, no dependencies — only loaded on pages that actually place
 * the widget (see Header::get_script_depends()). Supports more than one
 * header instance on a page, though in practice only one is expected.
 */
(function () {
    'use strict';

    function setupHeader(header) {
        var hamburger = header.querySelector('.bkw-header__hamburger');
        var panel = header.querySelector('[data-bkw-panel]');
        var overlay = header.querySelector('[data-bkw-panel-overlay]');
        var closeBtn = header.querySelector('[data-bkw-panel-close]');

        if (!hamburger || !panel || !overlay) {
            return;
        }

        function isOpen() {
            return panel.classList.contains('is-open');
        }

        function open() {
            panel.classList.add('is-open');
            overlay.classList.add('is-open');
            hamburger.setAttribute('aria-expanded', 'true');
            document.documentElement.classList.add('bkw-panel-open');
        }

        function close() {
            panel.classList.remove('is-open');
            overlay.classList.remove('is-open');
            hamburger.setAttribute('aria-expanded', 'false');
            document.documentElement.classList.remove('bkw-panel-open');
        }

        hamburger.addEventListener('click', function () {
            if (isOpen()) {
                close();
            } else {
                open();
            }
        });

        if (closeBtn) {
            closeBtn.addEventListener('click', close);
        }

        overlay.addEventListener('click', close);

        document.addEventListener('keydown', function (event) {
            if ('Escape' === event.key && isOpen()) {
                close();
            }
        });

        // اگر با تغییر عرض صفحه از حالت موبایل خارج شد، پنل باز نماند
        window.addEventListener('resize', function () {
            if (isOpen() && window.innerWidth > 1024) {
                close();
            }
        });
    }

    function init() {
        document.querySelectorAll('.bkw-header').forEach(setupHeader);
    }

    if ('loading' === document.readyState) {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
