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
 * The graphical Jalali monthly calendar (Architecture V3 §20/V4).
 *
 * Month navigation is AJAX (Admin\Rest::calendar(), fetched by
 * assets/js/whw-admin.js) with the plain `?whw_y=&whw_m=` links kept as a
 * no-JS fallback — same markup renders the initial page load and every
 * AJAX swap, via renderCalendarFragment()/renderOfficialHolidaysFragment()
 * being public so Admin\Rest can call them directly instead of
 * duplicating the HTML.
 */
final class Page
{
    public const SLUG = 'whw-weekly-holidays';
    private const CAPABILITY = 'manage_options';

    private const WEEKDAY_LABELS = ['شنبه', 'یکشنبه', 'دوشنبه', 'سه‌شنبه', 'چهارشنبه', 'پنجشنبه', 'جمعه'];

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
                'dayMarkedHoliday' => __('روز %d تعطیل علامت خورد', 'weekly-holidays-widget'),
                'dayMarkedNormal' => __('روز %d عادی شد', 'weekly-holidays-widget'),
            ],
        ]);
    }

    public function render(): void
    {
        if (!current_user_can(self::CAPABILITY)) {
            return;
        }

        [$year, $month] = $this->resolveRequestedMonth();

        echo '<div class="wrap whw-admin-wrap">';
        echo '<h1>' . esc_html__('تعطیلات هفته', 'weekly-holidays-widget') . '</h1>';
        echo '<p class="whw-admin-intro">' . esc_html__('تقویم زیر تعیین می‌کند ویجت «تعطیلات هفته» هر روز را چه وضعیتی نشان دهد. برای تعطیل‌کردن دستی یک روز، رویش کلیک کنید.', 'weekly-holidays-widget') . '</p>';

        echo '<div class="whw-admin-layout">';
        echo '<div class="whw-admin-card whw-admin-card--main">';
        echo $this->renderCalendarFragment($year, $month); // phpcs:ignore WordPress.Security.EscapeOutput -- pre-escaped by the fragment renderer
        echo '</div>';

        echo '<div class="whw-admin-sidebar">';
        $this->renderTodayOverride();
        echo $this->renderOfficialHolidaysFragment($year, $month); // phpcs:ignore WordPress.Security.EscapeOutput -- pre-escaped by the fragment renderer
        echo '</div>';
        echo '</div>';

        echo '</div>';
    }

    /** @return array{0: int, 1: int} [jalaliYear, jalaliMonth] */
    private function resolveRequestedMonth(): array
    {
        $todayJalali = JalaliDate::fromGregorian(Clock::now());

        $year = isset($_GET['whw_y']) ? absint($_GET['whw_y']) : $todayJalali->year;
        $month = isset($_GET['whw_m']) ? absint($_GET['whw_m']) : $todayJalali->month;

        if ($year < 1300 || $year > 1500) {
            $year = $todayJalali->year;
        }

        if ($month < 1 || $month > 12) {
            $month = $todayJalali->month;
        }

        return [$year, $month];
    }

    private function renderTodayOverride(): void
    {
        $today = Clock::now();
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

        echo '<div class="whw-admin-card whw-admin-card--side whw-admin-override" role="group" aria-label="' . esc_attr__('Override وضعیت امروز', 'weekly-holidays-widget') . '">';
        echo '<h2>' . esc_html__('وضعیت امروز', 'weekly-holidays-widget') . '</h2>';
        echo '<p class="description">' . esc_html__('این کلید فقط برای امروز اعتبار دارد و فردا خودش خنثی می‌شود — نیازی به خاموش کردن دستی نیست.', 'weekly-holidays-widget') . '</p>';

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
    }

    /**
     * Self-contained `.whw-admin-calendar` fragment — safe to echo on first
     * page load or return as a REST response body for AJAX month swaps
     * (Admin\Rest::calendar()). Every value is escaped internally.
     */
    public function renderCalendarFragment(int $year, int $month): string
    {
        $todayJalali = JalaliDate::fromGregorian(Clock::now());
        $daysInMonth = (new JalaliDate($year, $month, 1))->daysInMonth();
        $manualHolidays = $this->holidays->forMonth($year, $month);
        $officialHolidays = $this->official->forMonth($year, $month);

        [$prevYear, $prevMonth] = $month > 1 ? [$year, $month - 1] : [$year - 1, 12];
        [$nextYear, $nextMonth] = $month < 12 ? [$year, $month + 1] : [$year + 1, 1];
        $prevDaysInMonth = (new JalaliDate($prevYear, $prevMonth, 1))->daysInMonth();

        $prevUrl = add_query_arg(['whw_y' => $prevYear, 'whw_m' => $prevMonth]);
        $nextUrl = add_query_arg(['whw_y' => $nextYear, 'whw_m' => $nextMonth]);

        ob_start();

        printf(
            '<div class="whw-admin-calendar" data-jalali-year="%s" data-jalali-month="%s">',
            esc_attr((string) $year),
            esc_attr((string) $month),
        );

        echo '<div class="whw-admin-calendar__nav">';
        printf(
            '<a class="whw-nav-btn" href="%s" data-year="%d" data-month="%d"><span aria-hidden="true">&rsaquo;</span> %s</a>',
            esc_url($prevUrl),
            $prevYear,
            $prevMonth,
            esc_html__('ماه قبل', 'weekly-holidays-widget'),
        );
        printf(
            '<strong class="whw-admin-calendar__title">%s %s</strong>',
            esc_html(PersianCalendarFormat::monthName($month)),
            esc_html(PersianCalendarFormat::digits((string) $year)),
        );
        printf(
            '<a class="whw-nav-btn" href="%s" data-year="%d" data-month="%d">%s <span aria-hidden="true">&lsaquo;</span></a>',
            esc_url($nextUrl),
            $nextYear,
            $nextMonth,
            esc_html__('ماه بعد', 'weekly-holidays-widget'),
        );
        echo '</div>';

        echo '<div class="whw-admin-calendar__weekdays">';
        foreach (self::WEEKDAY_LABELS as $label) {
            printf('<span>%s</span>', esc_html($label));
        }
        echo '</div>';

        // روز اول ماه لزوماً شنبه نیست؛ همین‌قدر خانهٔ روز-ماه‌مجاور قبل از
        // روز ۱ اضافه می‌شود تا هر روز واقعاً زیر ستون هم‌نامش بنشیند و هر
        // سطر هم یک هفته‌ی کامل و بدون جای خالی دیده شود.
        $firstWeekdayIndex = Week::weekdayIndex((new JalaliDate($year, $month, 1))->toGregorian());
        $totalCells = $firstWeekdayIndex + $daysInMonth;
        $trailingBlanks = (7 - ($totalCells % 7)) % 7;

        echo '<div class="whw-admin-calendar__grid">';

        for ($i = $firstWeekdayIndex; $i > 0; $i--) {
            $overflowDay = $prevDaysInMonth - $i + 1;
            printf(
                '<span class="whw-admin-day whw-admin-day--overflow" aria-hidden="true">%s</span>',
                esc_html(PersianCalendarFormat::digits((string) $overflowDay)),
            );
        }

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
                '<button type="button" class="%1$s" data-day="%2$d" aria-pressed="%3$s">%4$s</button>',
                esc_attr(implode(' ', $classes)),
                $day,
                isset($manualHolidays[$day]) ? 'true' : 'false',
                esc_html(PersianCalendarFormat::digits((string) $day)),
            );
        }

        for ($i = 1; $i <= $trailingBlanks; $i++) {
            printf(
                '<span class="whw-admin-day whw-admin-day--overflow" aria-hidden="true">%s</span>',
                esc_html(PersianCalendarFormat::digits((string) $i)),
            );
        }

        echo '</div>';

        echo '<div class="whw-admin-calendar__legend">';
        printf('<span class="whw-legend-item is-friday">%s</span>', esc_html__('جمعه', 'weekly-holidays-widget'));
        printf('<span class="whw-legend-item is-manual-holiday">%s</span>', esc_html__('تعطیل انتخابی', 'weekly-holidays-widget'));
        printf('<span class="whw-legend-item is-official-holiday">%s</span>', esc_html__('تعطیل رسمی (اطلاع‌رسانی)', 'weekly-holidays-widget'));
        printf('<span class="whw-legend-item is-today">%s</span>', esc_html__('امروز', 'weekly-holidays-widget'));
        echo '</div>';

        echo '<span class="whw-calendar-status whw-visually-hidden" role="status" aria-live="polite"></span>';

        echo '</div>';

        return (string) ob_get_clean();
    }

    /**
     * Self-contained sidebar card listing the viewed month's official
     * (national) holidays by name — informational, matches the same
     * "AJAX-swappable fragment" pattern as renderCalendarFragment().
     */
    public function renderOfficialHolidaysFragment(int $year, int $month): string
    {
        $officialHolidays = $this->official->forMonth($year, $month);
        ksort($officialHolidays);

        ob_start();

        echo '<div class="whw-admin-card whw-admin-card--side whw-admin-official">';
        echo '<h2>' . esc_html__('تعطیلات رسمی این ماه', 'weekly-holidays-widget') . '</h2>';

        if ([] === $officialHolidays) {
            echo '<p class="description">' . esc_html__('برای این ماه تعطیل رسمی ثبت نشده است.', 'weekly-holidays-widget') . '</p>';
        } else {
            echo '<ul class="whw-official-list">';
            foreach ($officialHolidays as $day => $name) {
                printf(
                    '<li><span class="whw-official-list__day">%s %s</span><span class="whw-official-list__name">%s</span></li>',
                    esc_html(PersianCalendarFormat::digits((string) $day)),
                    esc_html(PersianCalendarFormat::monthName($month)),
                    esc_html($name),
                );
            }
            echo '</ul>';
        }

        echo '</div>';

        return (string) ob_get_clean();
    }
}
