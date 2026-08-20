<?php

declare(strict_types=1);

namespace WHW\Integration;

use Elementor\Controls_Manager;
use Elementor\Group_Control_Box_Shadow;
use Elementor\Group_Control_Typography;
use Elementor\Widget_Base;
use WHW\Domain\HolidayStatus;
use WHW\Domain\VisualState;
use WHW\Service\Clock;
use WHW\Service\WeekBuilder;
use WHW\Storage\Holidays;
use WHW\Storage\Override;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Renders the current physical week (Saturday .. Friday, Jalali calendar)
 * with per-day open/holiday status. No business logic lives here — it
 * only consumes the already-resolved Day list from Service\WeekBuilder
 * and maps it to markup. See assets/css/whw.css for the structural CSS
 * this markup depends on (state classes + CSS custom properties written
 * by the Style tab controls below).
 */
final class Widget extends Widget_Base
{
    private const array WEEKDAY_KEYS = ['sat', 'sun', 'mon', 'tue', 'wed', 'thu', 'fri'];

    private const array DEFAULT_DAY_LABELS = [
        'sat' => 'شنبه',
        'sun' => 'یکشنبه',
        'mon' => 'دوشنبه',
        'tue' => 'سه‌شنبه',
        'wed' => 'چهارشنبه',
        'thu' => 'پنجشنبه',
        'fri' => 'جمعه',
    ];

    #[\Override]
    public function get_name(): string
    {
        return 'whw-weekly-holidays';
    }

    #[\Override]
    public function get_title(): string
    {
        return __('تعطیلات هفته', 'weekly-holidays-widget');
    }

    #[\Override]
    public function get_icon(): string
    {
        return 'eicon-calendar';
    }

    #[\Override]
    public function get_categories(): array
    {
        return ['whw'];
    }

    #[\Override]
    public function get_keywords(): array
    {
        return ['تعطیل', 'تقویم', 'هفته', 'شمسی', 'جلالی', 'holiday', 'calendar', 'week', 'jalali'];
    }

    #[\Override]
    public function get_style_depends(): array
    {
        return ['whw-weekly-holidays'];
    }

    /**
     * Exempts this widget from Elementor's built-in element cache
     * (`_elementor_element_cache`, active by default — see
     * core/base/document.php in elementor/elementor) so a cached page
     * never serves yesterday's holiday status. See Architecture V4 §1.
     */
    #[\Override]
    protected function is_dynamic_content(): bool
    {
        return true;
    }

    #[\Override]
    protected function register_controls(): void
    {
        $this->register_day_labels_controls();
        $this->register_status_labels_controls();
        $this->register_abbreviation_controls();

        $this->register_layout_style_controls();
        $this->register_card_style_controls();
        $this->register_state_style_controls();
        $this->register_today_emphasis_style_controls();
        $this->register_typography_style_controls();
        $this->register_dot_style_controls();
    }

    /* =====================================================================
     * Content tab
     * =================================================================== */

    private function register_day_labels_controls(): void
    {
        $this->start_controls_section('section_day_labels', [
            'label' => __('نام روزهای هفته', 'weekly-holidays-widget'),
            'tab' => Controls_Manager::TAB_CONTENT,
        ]);

        foreach (self::DEFAULT_DAY_LABELS as $key => $default) {
            $this->add_control("day_label_{$key}", [
                'label' => $default,
                'type' => Controls_Manager::TEXT,
                'default' => $default,
                'label_block' => false,
            ]);
        }

        $this->end_controls_section();
    }

    private function register_status_labels_controls(): void
    {
        $this->start_controls_section('section_status_labels', [
            'label' => __('برچسب‌های وضعیت', 'weekly-holidays-widget'),
            'tab' => Controls_Manager::TAB_CONTENT,
        ]);

        $this->add_control('label_open', [
            'label' => __('برچسب روز عادی', 'weekly-holidays-widget'),
            'type' => Controls_Manager::TEXT,
            'default' => __('باز', 'weekly-holidays-widget'),
        ]);

        $this->add_control('label_holiday', [
            'label' => __('برچسب روز تعطیل', 'weekly-holidays-widget'),
            'type' => Controls_Manager::TEXT,
            'default' => __('تعطیل', 'weekly-holidays-widget'),
        ]);

        $this->end_controls_section();
    }

    private function register_abbreviation_controls(): void
    {
        $this->start_controls_section('section_abbreviations', [
            'label' => __('حروف اختصاری (اختیاری)', 'weekly-holidays-widget'),
            'tab' => Controls_Manager::TAB_CONTENT,
        ]);

        $this->add_control('abbreviations_note', [
            'type' => Controls_Manager::RAW_HTML,
            'raw' => __('در حالت موبایل به‌جای نام کامل روز، این حرف نمایش داده می‌شود. خالی بگذارید تا حرف اول نام روز به‌صورت خودکار استفاده شود.', 'weekly-holidays-widget'),
            'content_classes' => 'elementor-descriptor',
        ]);

        foreach (self::DEFAULT_DAY_LABELS as $key => $default) {
            $this->add_control("day_abbr_{$key}", [
                'label' => $default,
                'type' => Controls_Manager::TEXT,
                'default' => '',
                'placeholder' => mb_substr($default, 0, 1),
            ]);
        }

        $this->end_controls_section();
    }

    /* =====================================================================
     * Style tab
     * =================================================================== */

    private function register_layout_style_controls(): void
    {
        $this->start_controls_section('section_layout_style', [
            'label' => __('چیدمان', 'weekly-holidays-widget'),
            'tab' => Controls_Manager::TAB_STYLE,
        ]);

        $this->add_responsive_control('overflow_mode', [
            'label' => __('رفتار سرریز', 'weekly-holidays-widget'),
            'type' => Controls_Manager::SELECT,
            'default' => 'nowrap',
            'tablet_default' => 'nowrap',
            'mobile_default' => 'scroll',
            'options' => [
                'nowrap' => __('بدون شکست (fit)', 'weekly-holidays-widget'),
                'scroll' => __('اسکرول افقی', 'weekly-holidays-widget'),
                'wrap' => __('شکستن به چند سطر', 'weekly-holidays-widget'),
            ],
            'selectors_dictionary' => [
                'nowrap' => 'flex-wrap: nowrap; overflow-x: visible;',
                'scroll' => 'flex-wrap: nowrap; overflow-x: auto;',
                'wrap' => 'flex-wrap: wrap; overflow-x: visible;',
            ],
            'selectors' => [
                '{{WRAPPER}} .whw-week' => '{{VALUE}}',
            ],
        ]);

        $this->add_responsive_control('items_gap', [
            'label' => __('فاصله بین کارت‌ها', 'weekly-holidays-widget'),
            'type' => Controls_Manager::SLIDER,
            'size_units' => ['px'],
            'range' => ['px' => ['min' => 0, 'max' => 40]],
            'default' => ['unit' => 'px', 'size' => 12],
            'tablet_default' => ['unit' => 'px', 'size' => 10],
            'mobile_default' => ['unit' => 'px', 'size' => 8],
            'selectors' => [
                '{{WRAPPER}} .whw-week' => 'gap: {{SIZE}}{{UNIT}};',
            ],
        ]);

        $this->add_responsive_control('justify_content', [
            'label' => __('تراز افقی', 'weekly-holidays-widget'),
            'type' => Controls_Manager::CHOOSE,
            'options' => [
                'flex-start' => ['title' => __('ابتدا', 'weekly-holidays-widget'), 'icon' => 'eicon-justify-start-h'],
                'center' => ['title' => __('وسط', 'weekly-holidays-widget'), 'icon' => 'eicon-justify-center-h'],
                'flex-end' => ['title' => __('انتها', 'weekly-holidays-widget'), 'icon' => 'eicon-justify-end-h'],
            ],
            'default' => 'flex-start',
            'selectors' => [
                '{{WRAPPER}} .whw-week' => 'justify-content: {{VALUE}};',
            ],
        ]);

        $this->end_controls_section();
    }

    private function register_card_style_controls(): void
    {
        $this->start_controls_section('section_card_style', [
            'label' => __('کارت روز', 'weekly-holidays-widget'),
            'tab' => Controls_Manager::TAB_STYLE,
        ]);

        $this->add_responsive_control('card_width', [
            'label' => __('عرض کارت', 'weekly-holidays-widget'),
            'type' => Controls_Manager::SLIDER,
            'size_units' => ['px'],
            'range' => ['px' => ['min' => 30, 'max' => 200]],
            'default' => ['unit' => 'px', 'size' => 100],
            'mobile_default' => ['unit' => 'px', 'size' => 44],
            'selectors' => [
                '{{WRAPPER}} .whw-day' => 'width: {{SIZE}}{{UNIT}};',
            ],
        ]);

        $this->add_responsive_control('card_padding', [
            'label' => __('فاصله داخلی', 'weekly-holidays-widget'),
            'type' => Controls_Manager::DIMENSIONS,
            'size_units' => ['px'],
            'default' => ['top' => 16, 'right' => 16, 'bottom' => 16, 'left' => 16, 'unit' => 'px', 'isLinked' => true],
            'mobile_default' => ['top' => 8, 'right' => 8, 'bottom' => 8, 'left' => 8, 'unit' => 'px', 'isLinked' => true],
            'selectors' => [
                '{{WRAPPER}} .whw-day' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
            ],
        ]);

        $this->add_responsive_control('card_gap', [
            'label' => __('فاصله داخلی محتوا', 'weekly-holidays-widget'),
            'type' => Controls_Manager::SLIDER,
            'size_units' => ['px'],
            'range' => ['px' => ['min' => 0, 'max' => 24]],
            'default' => ['unit' => 'px', 'size' => 10],
            'mobile_default' => ['unit' => 'px', 'size' => 4],
            'selectors' => [
                '{{WRAPPER}} .whw-day' => 'gap: {{SIZE}}{{UNIT}};',
            ],
        ]);

        $this->add_responsive_control('card_align_items', [
            'label' => __('تراز محتوای کارت', 'weekly-holidays-widget'),
            'type' => Controls_Manager::CHOOSE,
            'options' => [
                'flex-end' => ['title' => __('راست', 'weekly-holidays-widget'), 'icon' => 'eicon-align-end-h'],
                'center' => ['title' => __('وسط', 'weekly-holidays-widget'), 'icon' => 'eicon-align-center-h'],
                'flex-start' => ['title' => __('چپ', 'weekly-holidays-widget'), 'icon' => 'eicon-align-start-h'],
            ],
            'default' => 'flex-end',
            'mobile_default' => 'center',
            'selectors' => [
                '{{WRAPPER}} .whw-day' => 'align-items: {{VALUE}};',
            ],
        ]);

        $this->add_control('card_border_width', [
            'label' => __('ضخامت حاشیه', 'weekly-holidays-widget'),
            'type' => Controls_Manager::SLIDER,
            'size_units' => ['px'],
            'range' => ['px' => ['min' => 0, 'max' => 6]],
            'default' => ['unit' => 'px', 'size' => 1],
            'selectors' => [
                '{{WRAPPER}} .whw-day' => 'border-width: {{SIZE}}{{UNIT}};',
            ],
        ]);

        $this->add_responsive_control('card_radius', [
            'label' => __('گردی گوشه‌ها', 'weekly-holidays-widget'),
            'type' => Controls_Manager::SLIDER,
            'size_units' => ['px'],
            'range' => ['px' => ['min' => 0, 'max' => 40]],
            'default' => ['unit' => 'px', 'size' => 18],
            'mobile_default' => ['unit' => 'px', 'size' => 10],
            'selectors' => [
                '{{WRAPPER}} .whw-day' => 'border-radius: {{SIZE}}{{UNIT}};',
            ],
        ]);

        $this->end_controls_section();
    }

    /**
     * Three independent state sections (Normal / Today / Holiday) — the
     * mandatory per-brief requirement. Colors are set as CSS custom
     * properties (`--whw-*`) on the state class rather than directly on
     * child elements, so descendants (name/status/dot) can read them via
     * `var()` without a separate control per descendant per state. Uses
     * add_responsive_control for every color — mechanically supported by
     * Controls_Stack regardless of control type (verified against
     * elementor/elementor source; the editor's device switcher is driven
     * purely by the `responsive` model attribute, not control type).
     */
    private function register_state_style_controls(): void
    {
        $this->start_controls_section('section_state_style', [
            'label' => __('حالت‌های روز', 'weekly-holidays-widget'),
            'tab' => Controls_Manager::TAB_STYLE,
        ]);

        $this->start_controls_tabs('state_style_tabs');

        $this->register_state_style_tab(
            tab_id: 'state_tab_normal',
            tab_label: __('عادی', 'weekly-holidays-widget'),
            selector: '{{WRAPPER}} .whw-day--normal',
            defaults: [
                'bg' => '#ffffff',
                'border' => '#e6d8c9',
                'name' => '#2a1e17',
                'status' => '#16a34a',
                'dot' => '#22c55e',
            ],
        );

        $this->register_state_style_tab(
            tab_id: 'state_tab_today',
            tab_label: __('امروز', 'weekly-holidays-widget'),
            selector: '{{WRAPPER}} .whw-day--today',
            defaults: [
                'bg' => '#ffffff',
                'border' => '#e6d8c9',
                'name' => '#2a1e17',
                'status' => '#2a1e17',
                'dot' => '#22c55e',
            ],
            mobile_defaults: [
                'bg' => '#8c583a',
                'border' => '#8c583a',
                'name' => '#ffffff',
                'status' => '#ffffff',
                'dot' => '#22c55e',
            ],
        );

        $this->register_state_style_tab(
            tab_id: 'state_tab_holiday',
            tab_label: __('تعطیل', 'weekly-holidays-widget'),
            selector: '{{WRAPPER}} .whw-day--holiday',
            defaults: [
                'bg' => '#a8261a',
                'border' => '#a8261a',
                'name' => '#ffffff',
                'status' => '#ffffff',
                'dot' => '#ffffff',
            ],
            mobile_defaults: [
                'bg' => '#f3e7e2',
                'border' => '#e6c7c1',
                'name' => '#ba291e',
                'status' => '#ba291e',
                'dot' => '#ba291e',
            ],
        );

        $this->end_controls_tabs();

        $this->end_controls_section();
    }

    /**
     * @param array{bg: string, border: string, name: string, status: string, dot: string} $defaults
     * @param array{bg: string, border: string, name: string, status: string, dot: string}|null $mobile_defaults
     */
    private function register_state_style_tab(
        string $tab_id,
        string $tab_label,
        string $selector,
        array $defaults,
        ?array $mobile_defaults = null,
    ): void {
        $this->start_controls_tab($tab_id, ['label' => $tab_label]);

        $fields = [
            'bg' => ['label' => __('پس‌زمینه', 'weekly-holidays-widget'), 'prop' => '--whw-bg'],
            'border' => ['label' => __('رنگ حاشیه', 'weekly-holidays-widget'), 'prop' => '--whw-border'],
            'name' => ['label' => __('رنگ نام روز', 'weekly-holidays-widget'), 'prop' => '--whw-name-color'],
            'status' => ['label' => __('رنگ برچسب وضعیت', 'weekly-holidays-widget'), 'prop' => '--whw-status-color'],
            'dot' => ['label' => __('رنگ نقطه', 'weekly-holidays-widget'), 'prop' => '--whw-dot-color'],
        ];

        foreach ($fields as $key => $field) {
            $this->add_responsive_control("{$tab_id}_{$key}", [
                'label' => $field['label'],
                'type' => Controls_Manager::COLOR,
                'default' => $defaults[$key],
                'mobile_default' => $mobile_defaults[$key] ?? $defaults[$key],
                'selectors' => [
                    $selector => "{$field['prop']}: {{VALUE}};",
                ],
            ]);
        }

        $this->add_group_control(Group_Control_Box_Shadow::get_type(), [
            'name' => "{$tab_id}_shadow",
            'selector' => $selector,
        ]);

        $this->end_controls_tab();
    }

    /**
     * A layer independent of the color-state tabs above: always applied to
     * whichever card is physically "today", regardless of whether that day
     * also resolved to Holiday. Needed because the two axes are genuinely
     * independent (a Friday can be today) — see Architecture review of the
     * Figma reference, mobile variant.
     */
    private function register_today_emphasis_style_controls(): void
    {
        $this->start_controls_section('section_today_emphasis', [
            'label' => __('تاکید بصری امروز', 'weekly-holidays-widget'),
            'tab' => Controls_Manager::TAB_STYLE,
        ]);

        $this->add_control('today_emphasis_note', [
            'type' => Controls_Manager::RAW_HTML,
            'raw' => __('این تنظیمات همیشه روی کارت «امروز» اعمال می‌شوند، حتی اگر امروز هم‌زمان تعطیل باشد (مثلاً جمعه).', 'weekly-holidays-widget'),
            'content_classes' => 'elementor-descriptor',
        ]);

        $this->add_responsive_control('today_border_color', [
            'label' => __('رنگ حاشیه', 'weekly-holidays-widget'),
            'type' => Controls_Manager::COLOR,
            'default' => '',
            'selectors' => [
                '{{WRAPPER}} .whw-day.is-today' => 'border-color: {{VALUE}};',
            ],
        ]);

        $this->add_group_control(Group_Control_Box_Shadow::get_type(), [
            'name' => 'today_emphasis_shadow',
            'selector' => '{{WRAPPER}} .whw-day.is-today',
        ]);

        $this->end_controls_section();
    }

    private function register_typography_style_controls(): void
    {
        $this->start_controls_section('section_typography_style', [
            'label' => __('تایپوگرافی', 'weekly-holidays-widget'),
            'tab' => Controls_Manager::TAB_STYLE,
        ]);

        $this->add_group_control(Group_Control_Typography::get_type(), [
            'name' => 'day_name_typography',
            'label' => __('نام روز', 'weekly-holidays-widget'),
            'selector' => '{{WRAPPER}} .whw-day__name',
        ]);

        $this->add_group_control(Group_Control_Typography::get_type(), [
            'name' => 'status_label_typography',
            'label' => __('برچسب وضعیت', 'weekly-holidays-widget'),
            'selector' => '{{WRAPPER}} .whw-day__status',
        ]);

        $this->add_group_control(Group_Control_Typography::get_type(), [
            'name' => 'abbreviation_typography',
            'label' => __('حرف اختصاری (موبایل)', 'weekly-holidays-widget'),
            'selector' => '{{WRAPPER}} .whw-day__abbr',
        ]);

        $this->end_controls_section();
    }

    private function register_dot_style_controls(): void
    {
        $this->start_controls_section('section_dot_style', [
            'label' => __('نقطه وضعیت', 'weekly-holidays-widget'),
            'tab' => Controls_Manager::TAB_STYLE,
        ]);

        $this->add_responsive_control('dot_size', [
            'label' => __('اندازه', 'weekly-holidays-widget'),
            'type' => Controls_Manager::SLIDER,
            'size_units' => ['px'],
            'range' => ['px' => ['min' => 2, 'max' => 20]],
            'default' => ['unit' => 'px', 'size' => 8],
            'mobile_default' => ['unit' => 'px', 'size' => 6],
            'selectors' => [
                '{{WRAPPER}} .whw-day__dot' => 'width: {{SIZE}}{{UNIT}}; height: {{SIZE}}{{UNIT}};',
            ],
        ]);

        $this->end_controls_section();
    }

    /* =====================================================================
     * Render
     * =================================================================== */

    #[\Override]
    protected function render(): void
    {
        $settings = $this->get_settings_for_display();

        $builder = new WeekBuilder(new Holidays(), new Override());
        $days = $builder->build(Clock::now());

        echo '<div class="whw-week" dir="rtl">';

        foreach ($days as $day) {
            $key = self::WEEKDAY_KEYS[$day->weekdayIndex];

            $label = (string) ($settings["day_label_{$key}"] ?? self::DEFAULT_DAY_LABELS[$key]);
            $abbrOverride = (string) ($settings["day_abbr_{$key}"] ?? '');
            $abbr = '' !== $abbrOverride ? $abbrOverride : mb_substr($label, 0, 1);

            $statusLabel = HolidayStatus::Holiday === $day->status
                ? (string) $settings['label_holiday']
                : (string) $settings['label_open'];

            $classes = ['whw-day', 'whw-day--' . strtolower($day->visualState()->name)];

            if ($day->isToday) {
                $classes[] = 'is-today';
            }

            printf(
                '<div class="%1$s"><div class="whw-day__row"><span class="whw-day__dot" aria-hidden="true"></span><span class="whw-day__status">%2$s</span></div><span class="whw-day__name">%3$s</span><span class="whw-day__abbr">%4$s</span></div>',
                esc_attr(implode(' ', $classes)),
                esc_html($statusLabel),
                esc_html($label),
                esc_html($abbr),
            );
        }

        echo '</div>';
    }
}
