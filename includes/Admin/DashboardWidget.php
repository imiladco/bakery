<?php

declare(strict_types=1);

namespace WHW\Admin;

use WHW\Domain\HolidayStatus;
use WHW\Domain\JalaliDate;
use WHW\Domain\OverrideState;
use WHW\Service\Clock;
use WHW\Service\TodayStatus;
use WHW\Storage\Holidays;
use WHW\Storage\Override;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * "وضعیت امروز" as a WordPress Dashboard (wp-admin/index.php) widget —
 * the same quick-override control as the settings page's sidebar card,
 * surfaced where an admin already looks every day. Reuses the exact same
 * markup/classes (.whw-admin-override, .whw-override-btn) so the shared
 * assets/js/whw-admin.js::initOverrideControls() drives it with no
 * dashboard-specific JS.
 */
final class DashboardWidget
{
    private const CAPABILITY = 'manage_options';

    public function __construct(
        private readonly Holidays $holidays,
        private readonly Override $override,
    ) {
    }

    public function register(): void
    {
        add_action('wp_dashboard_setup', [$this, 'addWidget']);
        add_action('admin_enqueue_scripts', [$this, 'enqueueAssets']);
    }

    public function addWidget(): void
    {
        if (!current_user_can(self::CAPABILITY)) {
            return;
        }

        wp_add_dashboard_widget(
            'whw_today_status',
            __('وضعیت امروز — تعطیلات هفته', 'weekly-holidays-widget'),
            [$this, 'render'],
        );
    }

    public function enqueueAssets(string $hook): void
    {
        if ('index.php' !== $hook) {
            return;
        }

        wp_enqueue_style('whw-admin', WHW_PLUGIN_URL . 'assets/css/whw-admin.css', [], WHW_PLUGIN_VERSION);

        wp_enqueue_script(
            'whw-admin',
            WHW_PLUGIN_URL . 'assets/js/whw-admin.js',
            [],
            WHW_PLUGIN_VERSION,
            true,
        );

        wp_localize_script('whw-admin', 'whwAdmin', [
            'restUrl' => esc_url_raw(rest_url('whw/v1')),
            'nonce' => wp_create_nonce('wp_rest'),
            'strings' => [
                'error' => __('خطا در ارتباط با سرور. دوباره تلاش کنید.', 'weekly-holidays-widget'),
                'dayMarkedHoliday' => __('روز %d تعطیل علامت خورد', 'weekly-holidays-widget'),
                'dayMarkedNormal' => __('روز %d عادی شد', 'weekly-holidays-widget'),
            ],
        ]);
    }

    public function render(): void
    {
        $today = Clock::now();
        $status = (new TodayStatus($this->holidays, $this->override))->resolve($today);
        $jalali = JalaliDate::fromGregorian($today);

        $overrideState = OverrideState::Unset;
        $stored = $this->override->get();

        if (null !== $stored['date'] && $stored['date']->format('Y-m-d') === $today->format('Y-m-d')) {
            $overrideState = $stored['state'];
        }

        $options = [
            OverrideState::Unset->value => __('طبق منطق پیش‌فرض', 'weekly-holidays-widget'),
            OverrideState::ForceHoliday->value => __('اجبار به تعطیل', 'weekly-holidays-widget'),
            OverrideState::ForceNormal->value => __('اجبار به عادی', 'weekly-holidays-widget'),
        ];

        echo '<div class="whw-admin-wrap">';

        printf(
            '<p class="whw-dashboard-status"><span class="whw-status-dot%1$s" aria-hidden="true"></span> %2$s <strong>%3$s</strong> — %4$s</p>',
            HolidayStatus::Holiday === $status ? ' is-closed' : ' is-open',
            esc_html__('وضعیت امروز:', 'weekly-holidays-widget'),
            HolidayStatus::Holiday === $status
                ? esc_html__('بسته', 'weekly-holidays-widget')
                : esc_html__('باز', 'weekly-holidays-widget'),
            esc_html(PersianCalendarFormat::dayMonthYear($jalali)),
        );

        echo '<div class="whw-admin-card whw-admin-card--side whw-admin-override" role="group" aria-label="' . esc_attr__('Override وضعیت امروز', 'weekly-holidays-widget') . '">';
        echo '<div class="whw-override-group">';
        foreach ($options as $value => $label) {
            printf(
                '<button type="button" class="whw-override-btn%1$s" data-state="%2$s" aria-pressed="%3$s">%4$s</button>',
                $overrideState->value === $value ? ' is-active' : '',
                esc_attr($value),
                $overrideState->value === $value ? 'true' : 'false',
                esc_html($label),
            );
        }
        echo '</div>';
        echo '<span class="whw-admin-status" role="status" aria-live="polite"></span>';
        echo '</div>';

        printf(
            '<p class="whw-dashboard-link"><a href="%s">%s</a></p>',
            esc_url(admin_url('admin.php?page=' . Page::SLUG)),
            esc_html__('ورود به تنظیمات تعطیلات هفته', 'weekly-holidays-widget'),
        );

        echo '</div>';
    }
}
