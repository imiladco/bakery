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

    function get(path, query) {
        var url = whwAdmin.restUrl + path + '?' + new URLSearchParams(query).toString();

        return fetch(url, {
            method: 'GET',
            headers: {
                'X-WP-Nonce': whwAdmin.nonce,
            },
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

    /**
     * Delegated on document (not the grid node itself) because AJAX month
     * navigation (initMonthNav) replaces the whole .whw-admin-calendar
     * fragment on every swap — a listener bound to the old node would be
     * discarded along with it.
     */
    function initCalendarGrid() {
        document.addEventListener('click', function (event) {
            var button = event.target.closest('.whw-admin-day:not(.whw-admin-day--overflow)');
            if (!button || button.disabled) {
                return;
            }

            var grid = button.closest('.whw-admin-calendar');
            if (!grid) {
                return;
            }

            var year = parseInt(grid.getAttribute('data-jalali-year'), 10);
            var month = parseInt(grid.getAttribute('data-jalali-month'), 10);
            var status = grid.querySelector('.whw-calendar-status');
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
                    if (status) {
                        status.textContent = result.holiday
                            ? whwAdmin.strings.dayMarkedHoliday.replace('%d', day)
                            : whwAdmin.strings.dayMarkedNormal.replace('%d', day);
                    }
                })
                .catch(function () {
                    window.alert(whwAdmin.strings.error);
                })
                .finally(function () {
                    setBusy(button, false);
                });
        });
    }

    /**
     * Intercepts clicks on the calendar's prev/next-month links and swaps
     * the calendar + official-holidays fragments in place via the REST
     * `/calendar` route, instead of a full page reload. The `<a href>`
     * stays intact as a no-JS fallback (progressive enhancement), and the
     * URL is kept in sync with history.pushState() so back/forward and
     * page reloads still land on the right month.
     */
    function initMonthNav() {
        document.addEventListener('click', function (event) {
            var link = event.target.closest('.whw-nav-btn');
            if (!link || link.classList.contains('is-busy')) {
                return;
            }

            var main = document.querySelector('.whw-admin-card--main');
            var official = document.querySelector('.whw-admin-official');
            if (!main) {
                return;
            }

            event.preventDefault();

            var year = link.getAttribute('data-year');
            var month = link.getAttribute('data-month');

            setBusy(link, true);

            get('/calendar', { jalali_year: year, jalali_month: month })
                .then(function (result) {
                    main.innerHTML = result.calendar_html;
                    if (official && result.official_html) {
                        official.outerHTML = result.official_html;
                    }
                    if (result.url) {
                        window.history.pushState({}, '', result.url);
                    }
                })
                .catch(function () {
                    window.alert(whwAdmin.strings.error);
                    window.location.href = link.href;
                })
                .finally(function () {
                    setBusy(link, false);
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
        initMonthNav();
        initOverrideControls();
    });
})();
