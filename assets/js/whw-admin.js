/**
 * Admin calendar interactivity: click-to-toggle a monthly holiday, and the
 * Today Override buttons. Plain vanilla JS + fetch() against the REST
 * routes registered in Admin/Rest.php — no build step, no framework
 * (Architecture V3 §20/V4: no npm/webpack pipeline exists in this plugin).
 * The server (Storage\Holidays / Storage\Override) remains the source of
 * truth; every toggle here is confirmed by the REST response before the
 * UI commits to the new state.
 */
(function () {
    'use strict';

    if (typeof whwAdmin === 'undefined') {
        return;
    }

    function request(path, body) {
        return fetch(whwAdmin.restUrl + path, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-WP-Nonce': whwAdmin.nonce,
            },
            body: JSON.stringify(body),
        }).then(function (response) {
            if (!response.ok) {
                throw new Error('request_failed');
            }
            return response.json();
        });
    }

    function setBusy(el, busy) {
        el.disabled = busy;
        el.classList.toggle('is-busy', busy);
    }

    function initCalendarGrid() {
        var grid = document.querySelector('.whw-admin-calendar');
        if (!grid) {
            return;
        }

        var year = parseInt(grid.getAttribute('data-jalali-year'), 10);
        var month = parseInt(grid.getAttribute('data-jalali-month'), 10);

        grid.addEventListener('click', function (event) {
            var button = event.target.closest('.whw-admin-day:not(.whw-admin-day--blank)');
            if (!button || button.disabled) {
                return;
            }

            var day = parseInt(button.getAttribute('data-day'), 10);

            setBusy(button, true);

            request('/holidays/toggle', {
                jalali_year: year,
                jalali_month: month,
                day: day,
            })
                .then(function (result) {
                    button.classList.toggle('is-manual-holiday', !!result.holiday);
                    button.setAttribute('aria-pressed', result.holiday ? 'true' : 'false');
                })
                .catch(function () {
                    window.alert(whwAdmin.strings.error);
                })
                .finally(function () {
                    setBusy(button, false);
                });
        });
    }

    function initOverrideControls() {
        var container = document.querySelector('.whw-admin-override');
        if (!container) {
            return;
        }

        var status = container.querySelector('.whw-admin-status');
        var buttons = container.querySelectorAll('.whw-override-btn');

        container.addEventListener('click', function (event) {
            var button = event.target.closest('.whw-override-btn');
            if (!button || button.disabled) {
                return;
            }

            var state = button.getAttribute('data-state');

            buttons.forEach(function (btn) {
                setBusy(btn, true);
            });

            request('/override', { state: state })
                .then(function () {
                    buttons.forEach(function (btn) {
                        var isActive = btn === button;
                        btn.classList.toggle('is-active', isActive);
                        btn.setAttribute('aria-pressed', isActive ? 'true' : 'false');
                    });
                    if (status) {
                        status.textContent = '';
                    }
                })
                .catch(function () {
                    window.alert(whwAdmin.strings.error);
                })
                .finally(function () {
                    buttons.forEach(function (btn) {
                        setBusy(btn, false);
                    });
                });
        });
    }

    document.addEventListener('DOMContentLoaded', function () {
        initCalendarGrid();
        initOverrideControls();
    });
})();
