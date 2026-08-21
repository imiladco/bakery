/**
 * Wires the two "تغییر به" actions in the admin-bar "وضعیت امروز" submenu
 * (Admin/AdminBar.php) to the existing `/override` REST route — the same
 * one the settings page and dashboard widget already use. On success the
 * page reloads so every "امروز" surface (toolbar bullet, dashboard
 * widget, settings page) reflects the new state immediately.
 */
(function () {
    'use strict';

    if (typeof whwAdminBar === 'undefined') {
        return;
    }

    function setOverride(state) {
        return fetch(whwAdminBar.restUrl + '/override', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-WP-Nonce': whwAdminBar.nonce,
            },
            body: JSON.stringify({ state: state }),
        });
    }

    function bind(nodeId, state) {
        var link = document.querySelector('#wp-admin-bar-' + nodeId + ' > a');
        if (!link) {
            return;
        }

        link.addEventListener('click', function (event) {
            event.preventDefault();
            setOverride(state).then(function () {
                window.location.reload();
            });
        });
    }

    document.addEventListener('DOMContentLoaded', function () {
        bind('whw-today-status-open', 'force_normal');
        bind('whw-today-status-closed', 'force_holiday');
    });
})();
