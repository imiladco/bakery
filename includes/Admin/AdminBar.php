<?php

declare(strict_types=1);

namespace WHW\Admin;

use WHW\Domain\HolidayStatus;
use WHW\Domain\JalaliDate;
use WHW\Service\Clock;
use WHW\Service\TodayStatus;
use WHW\Storage\Holidays;
use WHW\Storage\Override;
use WP_Admin_Bar;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * The colored-bullet "وضعیت امروز" node in the WP admin toolbar, with a
 * hover submenu offering the same quick actions as the dashboard widget
 * and settings-page sidebar: force open, force closed, or jump to
 * settings. The bullet/label are rendered server-side (always correct on
 * load); the two "تغییر به" actions are wired up by
 * assets/js/whw-admin-bar.js against the existing `/override` REST route
 * — no new endpoint needed.
 */
final class AdminBar
{
    private const CAPABILITY = 'manage_options';

    public function __construct(
        private readonly Holidays $holidays,
        private readonly Override $override,
    ) {
    }

    public function register(): void
    {
        add_action('admin_bar_menu', [$this, 'addNode'], 100);
        add_action('admin_enqueue_scripts', [$this, 'enqueueAssets']);
        add_action('wp_enqueue_scripts', [$this, 'enqueueAssets']);
    }

    public function addNode(WP_Admin_Bar $adminBar): void
    {
        if (!current_user_can(self::CAPABILITY) || !is_admin_bar_showing()) {
            return;
        }

        $today = Clock::now();
        $status = (new TodayStatus($this->holidays, $this->override))->resolve($today);
        $jalali = JalaliDate::fromGregorian($today);
        $isHoliday = HolidayStatus::Holiday === $status;

        $dot = sprintf(
            '<span class="whw-toolbar-dot" style="display:inline-block;width:8px;height:8px;border-radius:50%%;margin-inline-end:6px;background:%s;"></span>',
            $isHoliday ? '#d63638' : '#00a32a',
        );

        $title = $dot . sprintf(
            '%s %s - %s',
            esc_html__('وضعیت امروز:', 'weekly-holidays-widget'),
            $isHoliday ? esc_html__('بسته', 'weekly-holidays-widget') : esc_html__('باز', 'weekly-holidays-widget'),
            esc_html(PersianCalendarFormat::dayMonthYear($jalali)),
        );

        $settingsUrl = admin_url('admin.php?page=' . Page::SLUG);

        $adminBar->add_node([
            'id' => 'whw-today-status',
            'title' => $title,
            'href' => $settingsUrl,
        ]);

        $adminBar->add_node([
            'id' => 'whw-today-status-open',
            'parent' => 'whw-today-status',
            'title' => esc_html__('تغییر به: باز', 'weekly-holidays-widget'),
            'href' => '#',
        ]);

        $adminBar->add_node([
            'id' => 'whw-today-status-closed',
            'parent' => 'whw-today-status',
            'title' => esc_html__('تغییر به: بسته', 'weekly-holidays-widget'),
            'href' => '#',
        ]);

        $adminBar->add_node([
            'id' => 'whw-today-status-settings',
            'parent' => 'whw-today-status',
            'title' => esc_html__('ورود به تنظیمات', 'weekly-holidays-widget'),
            'href' => $settingsUrl,
        ]);
    }

    public function enqueueAssets(): void
    {
        if (!current_user_can(self::CAPABILITY) || !is_admin_bar_showing()) {
            return;
        }

        wp_enqueue_script(
            'whw-admin-bar',
            WHW_PLUGIN_URL . 'assets/js/whw-admin-bar.js',
            [],
            WHW_PLUGIN_VERSION,
            true,
        );

        wp_localize_script('whw-admin-bar', 'whwAdminBar', [
            'restUrl' => esc_url_raw(rest_url('whw/v1')),
            'nonce' => wp_create_nonce('wp_rest'),
        ]);
    }
}
