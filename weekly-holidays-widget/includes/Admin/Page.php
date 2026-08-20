<?php

declare(strict_types=1);

namespace WHW\Admin;

use WHW\Domain\JalaliDate;
use WHW\Domain\OverrideState;
use WHW\Domain\Week;
use WHW\Service\Clock;
use WHW\Storage\Holidays;
use WHW\Storage\Official;
use WHW\Storage\Override;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * The graphical Jalali monthly calendar (Architecture V3 §20/V4). Month
 * navigation is a plain server-rendered link (?whw_y=&whw_m=) — no JS
 * needed for that. Only the per-day toggle and the Today Override control
 * go through REST + a small vanilla JS layer (assets/js/whw-admin.js);
 * no build step, no React, per the architecture decision (no npm/webpack
 * pipeline exists in this plugin).
 */
final class Page
{
    private const string SLUG = 'whw-weekly-holidays';
    private const string CAPABILITY = 'manage_options';

    public function __construct(
        private readonly Holidays $holidays,
        private readonly Override $override,
        private readonly Official $official,
    ) {
    }

    public function register(): void
    {
        add_action('admin_menu', [$this, 'registerMenu']);
        add_action('admin_enqueue_scripts', [$this, 'enqueueAssets']);
    }

    public function registerMenu(): void
    {
        add_menu_page(
            __('تعطیلات هفته', 'weekly-holidays-widget'),
            __('تعطیلات هفته', 'weekly-holidays-widget'),
            self::CAPABILITY,
            self::SLUG,
            [$this, 'render'],
            'dashicons-calendar-alt',
        );
    }

    public function enqueueAssets(string $hook): void
    {
        if ('toplevel_page_' . self::SLUG !== $hook) {
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
            ],
        ]);
    }

    public function render(): void
    {
        if (!current_user_can(self::CAPABILITY)) {
            return;
        }

        $today = Clock::now();
        $todayJalali = JalaliDate::fromGregorian($today);

        $year = isset($_GET['whw_y']) ? absint($_GET['whw_y']) : $todayJalali->year;
        $month = isset($_GET['whw_m']) ? absint($_GET['whw_m']) : $todayJalali->month;

        if ($year < 1300 || $year > 1500) {
            $year = $todayJalali->year;
        }

        if ($month < 1 || $month > 12) {
            $month = $todayJalali->month;
        }

        $daysInMonth = (new JalaliDate($year, $month, 1))->daysInMonth();
        $manualHolidays = $this->holidays->forMonth($year, $month);
        $officialHolidays = $this->official->forMonth($year, $month);

        $overrideState = OverrideState::Unset;
        $stored = $this->override->get();

        if (null !== $stored['date'] && $stored['date']->format('Y-m-d') === $today->format('Y-m-d')) {
            $overrideState = $stored['state'];
        }

        [$prevYear, $prevMonth] = $month > 1 ? [$year, $month - 1] : [$year - 1, 12];
        [$nextYear, $nextMonth] = $month < 12 ? [$year, $month + 1] : [$year + 1, 1];

        echo '<div class="wrap whw-admin-wrap">';
        echo '<h1>' . esc_html__('تعطیلات هفته', 'weekly-holidays-widget') . '</h1>';

        $this->renderTodayOverride($overrideState);
        $this->renderCalendar($year, $month, $daysInMonth, $manualHolidays, $officialHolidays, $todayJalali, $prevYear, $prevMonth, $nextYear, $nextMonth);

        echo '</div>';
    }

    private function renderTodayOverride(OverrideState $current): void
    {
        $options = [
            OverrideState::Unset->value => __('طبق منطق پیش‌فرض', 'weekly-holidays-widget'),
            OverrideState::ForceHoliday->value => __('اجبار به تعطیل', 'weekly-holidays-widget'),
            OverrideState::ForceNormal->value => __('اجبار به عادی', 'weekly-holidays-widget'),
        ];

        echo '<div class="whw-admin-override" role="group" aria-label="' . esc_attr__('Override وضعیت امروز', 'weekly-holidays-widget') . '">';
        echo '<h2>' . esc_html__('وضعیت امروز', 'weekly-holidays-widget') . '</h2>';

        foreach ($options as $value => $label) {
            printf(
                '<button type="button" class="button whw-override-btn%1$s" data-state="%2$s" aria-pressed="%3$s">%4$s</button>',
                $current->value === $value ? ' button-primary is-active' : '',
                esc_attr($value),
                $current->value === $value ? 'true' : 'false',
                esc_html($label),
            );
        }

        echo '<span class="whw-admin-status" role="status" aria-live="polite"></span>';
        echo '</div>';
    }

    /**
     * @param array<int, true> $manualHolidays
     * @param array<int, true> $officialHolidays
     */
    private function renderCalendar(
        int $year,
        int $month,
        int $daysInMonth,
        array $manualHolidays,
        array $officialHolidays,
        JalaliDate $todayJalali,
        int $prevYear,
        int $prevMonth,
        int $nextYear,
        int $nextMonth,
    ): void {
        $prevUrl = add_query_arg(['whw_y' => $prevYear, 'whw_m' => $prevMonth]);
        $nextUrl = add_query_arg(['whw_y' => $nextYear, 'whw_m' => $nextMonth]);

        echo '<div class="whw-admin-calendar" data-jalali-year="' . esc_attr((string) $year) . '" data-jalali-month="' . esc_attr((string) $month) . '">';

        echo '<div class="whw-admin-calendar__nav">';
        printf('<a class="button" href="%s">&raquo; %s</a>', esc_url($prevUrl), esc_html__('ماه قبل', 'weekly-holidays-widget'));
        printf('<strong>%d / %02d</strong>', $year, $month);
        printf('<a class="button" href="%s">%s &laquo;</a>', esc_url($nextUrl), esc_html__('ماه بعد', 'weekly-holidays-widget'));
        echo '</div>';

        echo '<div class="whw-admin-calendar__grid">';

        for ($day = 1; $day <= $daysInMonth; $day++) {
            $jalali = new JalaliDate($year, $month, $day);
            $weekdayIndex = Week::weekdayIndex($jalali->toGregorian());

            $classes = ['whw-admin-day'];
            if (6 === $weekdayIndex) {
                $classes[] = 'is-friday';
            }
            if (isset($manualHolidays[$day])) {
                $classes[] = 'is-manual-holiday';
            }
            if (isset($officialHolidays[$day])) {
                $classes[] = 'is-official-holiday';
            }
            if ($jalali->equals($todayJalali)) {
                $classes[] = 'is-today';
            }

            printf(
                '<button type="button" class="%1$s" data-day="%2$d" aria-pressed="%3$s">%2$d</button>',
                esc_attr(implode(' ', $classes)),
                $day,
                isset($manualHolidays[$day]) ? 'true' : 'false',
            );
        }

        echo '</div>';

        echo '<div class="whw-admin-calendar__legend">';
        printf('<span class="whw-legend-item is-friday">%s</span>', esc_html__('جمعه', 'weekly-holidays-widget'));
        printf('<span class="whw-legend-item is-manual-holiday">%s</span>', esc_html__('تعطیل انتخابی', 'weekly-holidays-widget'));
        printf('<span class="whw-legend-item is-official-holiday">%s</span>', esc_html__('تعطیل رسمی (اطلاع‌رسانی)', 'weekly-holidays-widget'));
        printf('<span class="whw-legend-item is-today">%s</span>', esc_html__('امروز', 'weekly-holidays-widget'));
        echo '</div>';

        echo '</div>';
    }
}
