<?php

declare(strict_types=1);

namespace Bakery_Widgets\Widgets;

use Elementor\Controls_Manager;
use Elementor\Group_Control_Text_Shadow;
use Elementor\Group_Control_Typography;
use Elementor\Utils;
use Elementor\Widget_Base;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * ویجت «عنوان بخش» — پیش‌عنوان کوچک (مثلاً «پخت بیکری») + عنوان اصلی
 * درشت («فروش ویژه») + یک خط تزئینی اختیاری.
 *
 * تفاوت رفرنس دسکتاپ و موبایل فیگما فقط اندازه/چیدمان نیست: در موبایل
 * چیدمان از وسط‌چین به راست‌چین عوض می‌شود و خط تزئینی اضافه می‌شود.
 * هر دو با همان مکانیزم کنترل‌های ریسپانسیو موجود المنتور پوشش داده
 * می‌شوند (مقدار پیش‌فرض جدا برای هر بریک‌پوینت) — بدون نیاز به دو
 * مارک‌آپ یا دو ویجت جدا: خط همیشه در DOM هست، فقط عرضش پیش‌فرض در
 * دسکتاپ صفر (نامرئی) و در موبایل ۴۰px است.
 */
final class Section_Title extends Widget_Base
{
    #[\Override]
    public function get_name(): string
    {
        return 'bakery-section-title';
    }

    #[\Override]
    public function get_title(): string
    {
        return __('عنوان بخش بیکری عظام', 'bakery-widgets');
    }

    #[\Override]
    public function get_icon(): string
    {
        return 'eicon-t-letter';
    }

    #[\Override]
    public function get_categories(): array
    {
        return ['bakery'];
    }

    #[\Override]
    public function get_keywords(): array
    {
        return ['عنوان', 'تیتر', 'هدینگ', 'بخش', 'title', 'heading', 'section', 'headline', 'بیکری', 'عظام'];
    }

    #[\Override]
    public function get_style_depends(): array
    {
        return ['bakery-widgets'];
    }

    /* ---------------------------------------------------------------------
     * کنترل‌ها
     * ------------------------------------------------------------------- */

    #[\Override]
    protected function register_controls(): void
    {
        // تب محتوا
        $this->register_content_controls();
        $this->register_layout_controls();

        // تب استایل
        $this->register_eyebrow_style_controls();
        $this->register_title_style_controls();
        $this->register_line_style_controls();
    }

    /* =====================================================================
     * محتوا — پیش‌عنوان / عنوان / خط
     * =================================================================== */

    private function register_content_controls(): void
    {
        $this->start_controls_section('section_content', [
            'label' => __('محتوا', 'bakery-widgets'),
            'tab' => Controls_Manager::TAB_CONTENT,
        ]);

        $this->add_control('eyebrow_text', [
            'label' => __('پیش‌عنوان', 'bakery-widgets'),
            'type' => Controls_Manager::TEXT,
            'default' => __('پخت بیکری', 'bakery-widgets'),
            'dynamic' => ['active' => true],
            'label_block' => true,
        ]);

        $this->add_control('eyebrow_tag', [
            'label' => __('تگ پیش‌عنوان', 'bakery-widgets'),
            'type' => Controls_Manager::SELECT,
            'default' => 'span',
            'options' => ['span' => 'span', 'div' => 'div', 'p' => 'p'],
            'condition' => ['eyebrow_text!' => ''],
        ]);

        $this->add_control('heading_title', [
            'label' => __('عنوان', 'bakery-widgets'),
            'type' => Controls_Manager::HEADING,
            'separator' => 'before',
        ]);

        $this->add_control('title_text', [
            'label' => __('متن عنوان', 'bakery-widgets'),
            'type' => Controls_Manager::TEXT,
            'default' => __('فروش ویژه', 'bakery-widgets'),
            'dynamic' => ['active' => true],
            'label_block' => true,
        ]);

        $this->add_control('title_tag', [
            'label' => __('تگ عنوان', 'bakery-widgets'),
            'type' => Controls_Manager::SELECT,
            'default' => 'h2',
            'options' => [
                'h1' => 'H1', 'h2' => 'H2', 'h3' => 'H3',
                'h4' => 'H4', 'h5' => 'H5', 'h6' => 'H6',
                'div' => 'div', 'span' => 'span', 'p' => 'p',
            ],
            'condition' => ['title_text!' => ''],
        ]);

        $this->add_control('heading_line', [
            'label' => __('خط تزئینی', 'bakery-widgets'),
            'type' => Controls_Manager::HEADING,
            'separator' => 'before',
        ]);

        $this->add_control('show_line', [
            'label' => __('نمایش خط', 'bakery-widgets'),
            'type' => Controls_Manager::SWITCHER,
            'default' => 'yes',
            'description' => __('در رفرنس دسکتاپ فیگما این خط نیست و در موبایل هست — عرض پیش‌فرض خط از تب استایل همین تفاوت را با کنترل ریسپانسیو (۰ در دسکتاپ، ۴۰px در موبایل) پوشش می‌دهد؛ لازم نیست این سوییچ را خاموش کنید.', 'bakery-widgets'),
        ]);

        $this->end_controls_section();
    }

    /* =====================================================================
     * محتوا — چیدمان
     * =================================================================== */

    private function register_layout_controls(): void
    {
        $this->start_controls_section('section_layout', [
            'label' => __('چیدمان', 'bakery-widgets'),
            'tab' => Controls_Manager::TAB_CONTENT,
        ]);

        /*
         * .bkw-section-title یک فلکس ستونی زیر dir="rtl" است؛ در فلکس
         * ستونی محور عرضی (align-items) هم از جهت متن تبعیت می‌کند، پس
         * زیر rtl مقدار flex-start فیزیکاً راست است و flex-end چپ —
         * برعکس چیزی که در نگاه اول به نظر می‌رسد (همان نکته‌ای که در
         * ویجت تعطیلات هفته هم اصلاح شد). این‌جا مستقیم مقدار درست را
         * به برچسب درست وصل کرده‌ایم، نه این‌که به‌اشتباه برعکس شود.
         */
        $this->add_responsive_control('align_items', [
            'label' => __('چیدمان', 'bakery-widgets'),
            'type' => Controls_Manager::CHOOSE,
            'options' => [
                'flex-start' => ['title' => __('راست', 'bakery-widgets'), 'icon' => 'eicon-text-align-right'],
                'center' => ['title' => __('وسط', 'bakery-widgets'), 'icon' => 'eicon-text-align-center'],
                'flex-end' => ['title' => __('چپ', 'bakery-widgets'), 'icon' => 'eicon-text-align-left'],
            ],
            'default' => 'center',
            'mobile_default' => 'flex-start',
            'selectors' => [
                '{{WRAPPER}} .bkw-section-title' => 'align-items: {{VALUE}};',
            ],
        ]);

        $this->add_responsive_control('items_gap', [
            'label' => __('فاصله بین اجزا', 'bakery-widgets'),
            'type' => Controls_Manager::SLIDER,
            'size_units' => ['px', 'em'],
            'range' => ['px' => ['min' => 0, 'max' => 40]],
            'default' => ['size' => 8, 'unit' => 'px'],
            'mobile_default' => ['size' => 4, 'unit' => 'px'],
            'selectors' => [
                '{{WRAPPER}} .bkw-section-title' => 'gap: {{SIZE}}{{UNIT}};',
            ],
        ]);

        $this->end_controls_section();
    }

    /* =====================================================================
     * استایل — پیش‌عنوان
     * =================================================================== */

    private function register_eyebrow_style_controls(): void
    {
        $this->start_controls_section('section_style_eyebrow', [
            'label' => __('پیش‌عنوان', 'bakery-widgets'),
            'tab' => Controls_Manager::TAB_STYLE,
            'condition' => ['eyebrow_text!' => ''],
        ]);

        $selector = '{{WRAPPER}} .bkw-section-title__eyebrow';

        $this->add_group_control(Group_Control_Typography::get_type(), [
            'name' => 'eyebrow_typography',
            'selector' => $selector,
            'fields_options' => [
                'font_size' => [
                    'default' => ['unit' => 'px', 'size' => 16],
                    'mobile_default' => ['unit' => 'px', 'size' => 12],
                ],
                'font_weight' => ['default' => '700'],
            ],
        ]);

        $this->add_control('eyebrow_color', [
            'label' => __('رنگ', 'bakery-widgets'),
            'type' => Controls_Manager::COLOR,
            'default' => '#c59b62',
            'selectors' => [$selector => 'color: {{VALUE}};'],
        ]);

        $this->end_controls_section();
    }

    /* =====================================================================
     * استایل — عنوان
     * =================================================================== */

    private function register_title_style_controls(): void
    {
        $this->start_controls_section('section_style_title', [
            'label' => __('عنوان', 'bakery-widgets'),
            'tab' => Controls_Manager::TAB_STYLE,
            'condition' => ['title_text!' => ''],
        ]);

        $selector = '{{WRAPPER}} .bkw-section-title__title';

        $this->add_group_control(Group_Control_Typography::get_type(), [
            'name' => 'title_typography',
            'selector' => $selector,
            'fields_options' => [
                'font_size' => [
                    'default' => ['unit' => 'px', 'size' => 36],
                    'mobile_default' => ['unit' => 'px', 'size' => 22],
                ],
                'font_weight' => ['default' => '900'],
            ],
        ]);

        $this->add_control('title_color', [
            'label' => __('رنگ', 'bakery-widgets'),
            'type' => Controls_Manager::COLOR,
            'default' => '#2a1e17',
            'selectors' => [$selector => 'color: {{VALUE}};'],
        ]);

        $this->add_group_control(Group_Control_Text_Shadow::get_type(), [
            'name' => 'title_shadow',
            'selector' => $selector,
        ]);

        $this->end_controls_section();
    }

    /* =====================================================================
     * استایل — خط تزئینی
     * =================================================================== */

    private function register_line_style_controls(): void
    {
        $this->start_controls_section('section_style_line', [
            'label' => __('خط تزئینی', 'bakery-widgets'),
            'tab' => Controls_Manager::TAB_STYLE,
            'condition' => ['show_line' => 'yes'],
        ]);

        $selector = '{{WRAPPER}} .bkw-section-title__line';

        // پیش‌فرض دسکتاپ صفر (نامرئی، مطابق رفرنس دسکتاپ فیگما که خط ندارد)
        // و پیش‌فرض موبایل ۴۰px (مطابق رفرنس موبایل) — رجوع کن به یادداشت show_line.
        $this->add_responsive_control('line_width', [
            'label' => __('عرض', 'bakery-widgets'),
            'type' => Controls_Manager::SLIDER,
            'size_units' => ['px', '%'],
            'range' => ['px' => ['min' => 0, 'max' => 200]],
            'default' => ['unit' => 'px', 'size' => 0],
            'mobile_default' => ['unit' => 'px', 'size' => 40],
            'selectors' => [
                $selector => 'width: {{SIZE}}{{UNIT}};',
            ],
        ]);

        $this->add_control('line_height', [
            'label' => __('ضخامت', 'bakery-widgets'),
            'type' => Controls_Manager::SLIDER,
            'size_units' => ['px'],
            'range' => ['px' => ['min' => 1, 'max' => 20]],
            'default' => ['unit' => 'px', 'size' => 3],
            'selectors' => [
                $selector => 'height: {{SIZE}}{{UNIT}};',
            ],
        ]);

        $this->add_control('line_color', [
            'label' => __('رنگ', 'bakery-widgets'),
            'type' => Controls_Manager::COLOR,
            'default' => '#8c583a',
            'selectors' => [
                $selector => 'background-color: {{VALUE}};',
            ],
        ]);

        $this->add_responsive_control('line_radius', [
            'label' => __('رادیوس', 'bakery-widgets'),
            'type' => Controls_Manager::SLIDER,
            'size_units' => ['px'],
            'range' => ['px' => ['min' => 0, 'max' => 20]],
            'default' => ['unit' => 'px', 'size' => 1.5],
            'selectors' => [
                $selector => 'border-radius: {{SIZE}}{{UNIT}};',
            ],
        ]);

        $this->end_controls_section();
    }

    /* ---------------------------------------------------------------------
     * رندر
     * ------------------------------------------------------------------- */

    #[\Override]
    protected function render(): void
    {
        $settings = $this->get_settings_for_display();

        $eyebrow = trim((string) $settings['eyebrow_text']);
        $title = trim((string) $settings['title_text']);
        $show_line = 'yes' === $settings['show_line'];

        if ('' === $eyebrow && '' === $title && !$show_line) {
            return;
        }

        $eyebrow_tag = Utils::validate_html_tag($settings['eyebrow_tag']);
        $title_tag = Utils::validate_html_tag($settings['title_tag']);

        echo '<div class="bkw-section-title" dir="rtl">';

        if ('' !== $eyebrow) {
            printf(
                '<%1$s class="bkw-section-title__eyebrow">%2$s</%1$s>',
                esc_html($eyebrow_tag),
                esc_html($eyebrow),
            );
        }

        if ('' !== $title) {
            printf(
                '<%1$s class="bkw-section-title__title">%2$s</%1$s>',
                esc_html($title_tag),
                esc_html($title),
            );
        }

        if ($show_line) {
            echo '<div class="bkw-section-title__line" aria-hidden="true"></div>';
        }

        echo '</div>';
    }
}
