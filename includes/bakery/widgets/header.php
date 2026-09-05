<?php

declare(strict_types=1);

namespace Bakery_Widgets\Widgets;

use Bakery_Widgets\Widgets\Traits\Account_Actions_Controls;
use Elementor\Controls_Manager;
use Elementor\Group_Control_Background;
use Elementor\Group_Control_Border;
use Elementor\Group_Control_Typography;
use Elementor\Repeater;
use Elementor\Widget_Base;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * ویجت «هدر سایت» — نوار برند + ناوبری + اکشن‌های حساب کاربری
 * (سبد/کاربر/خروج، از Traits\Account_Actions_Controls — همان تریتی که
 * ویجت مستقل Account_Bar هم استفاده می‌کند، رجوع کن به مستندات آن‌جا).
 *
 * سه سطح مارک‌آپ کاملاً متفاوت زیر یک ریشهٔ dir="rtl" رندر می‌شود، نه
 * یک مارک‌آپ که فقط با CSS اندازه عوض کند — چون رفرنس فیگما واقعاً سه
 * ترکیب متفاوت‌اند، نه یک ترکیب در سه اندازه:
 *   - دسکتاپ (≥1025px): برند+ناوبری یک ردیف، اکشن‌ها (سه پیل کامل)
 *     ردیف دیگر — با render_account_actions() مشترک، بدون تفاوت با
 *     ویجت Account_Bar.
 *   - موبایل جمع‌شده (≤1024px): ردیف اول = همبرگر + برند + فقط پیل سبد
 *     (کوچک‌شده)، ردیف دوم = فقط پیل کاربر در حالت «فشرده» (رجوع کن به
 *     Account_Actions_Controls::render_user_item فشرده) — خروج اصلاً
 *     در این حالت نیست، به پنل منتقل شده.
 *   - پنل کشویی موبایل: با JS (assets/js/bakery-header.js) باز/بسته
 *     می‌شود؛ آیتم‌های ناوبری همان REPEATER محتوا هستند (یک‌بار تعریف،
 *     دو رندر — دسکتاپ متنی، پنل با آیکون دایره‌ای)، دکمهٔ خروج مارک‌آپ
 *     مستقل خودش را دارد چون طراحی پیل قرمز حاشیه‌دار است، نه یکی از
 *     سه پیل استاندارد — اما مقصدش را از همان
 *     Account_Actions_Controls::resolve_logout_url() می‌گیرد.
 *
 * «فعال بودن» هر آیتم ناوبری همیشه از روی URL درخواست فعلی محاسبه
 * می‌شود (is_active_nav_item())، نه یک کلید دستی روی هر آیتم — یک
 * سوییچ دستی روی هر صفحه باید جدا تنظیم شود و همیشه یک‌جا از قلم
 * می‌افتد؛ تشخیص خودکار همان چیزی است که یک تم استاندارد وردپرس هم با
 * current-menu-item انجام می‌دهد.
 */
final class Header extends Widget_Base
{
    use Account_Actions_Controls;

    #[\Override]
    public function get_name(): string
    {
        return 'bakery-header';
    }

    #[\Override]
    public function get_title(): string
    {
        return __('هدر بیکری عظام', 'bakery-widgets');
    }

    #[\Override]
    public function get_icon(): string
    {
        return 'eicon-header';
    }

    #[\Override]
    public function get_categories(): array
    {
        return ['bakery'];
    }

    #[\Override]
    public function get_keywords(): array
    {
        return ['هدر', 'ناوبری', 'منو', 'برند', 'header', 'nav', 'menu', 'brand', 'بیکری', 'عظام'];
    }

    #[\Override]
    public function get_style_depends(): array
    {
        return ['bakery-widgets'];
    }

    #[\Override]
    public function get_script_depends(): array
    {
        return ['bakery-header'];
    }

    /* ---------------------------------------------------------------------
     * کنترل‌ها
     * ------------------------------------------------------------------- */

    #[\Override]
    protected function register_controls(): void
    {
        // تب محتوا
        $this->register_brand_controls();
        $this->register_nav_controls();
        $this->register_account_actions_content_controls();
        $this->register_panel_footer_controls();

        // تب استایل
        $this->register_bar_style_controls();
        $this->register_brand_style_controls();
        $this->register_nav_style_controls();
        $this->register_hamburger_style_controls();
        $this->register_panel_style_controls();
        $this->register_nav_item_style_controls();
        $this->register_logout_button_style_controls();
        $this->register_account_actions_style_controls();
    }

    /* =====================================================================
     * محتوا — برند
     * =================================================================== */

    private function register_brand_controls(): void
    {
        $this->start_controls_section('section_brand', [
            'label' => __('برند', 'bakery-widgets'),
            'tab' => Controls_Manager::TAB_CONTENT,
        ]);

        $this->add_control('brand_text', [
            'label' => __('نام برند', 'bakery-widgets'),
            'type' => Controls_Manager::TEXT,
            'default' => __('بیکری عظام', 'bakery-widgets'),
            'label_block' => true,
        ]);

        $this->add_control('brand_logo', [
            'label' => __('آیکون برند', 'bakery-widgets'),
            'type' => Controls_Manager::MEDIA,
            'media_types' => ['image', 'svg'],
            'default' => ['url' => BAKERY_WIDGETS_URL . 'assets/icons/logo-badge.svg'],
            'description' => __('آیکون و نام برند طبق درخواست همیشه به صفحهٔ اصلی سایت لینک می‌شوند؛ کنترل لینک جداگانه‌ای ندارد.', 'bakery-widgets'),
        ]);

        $this->end_controls_section();
    }

    /* =====================================================================
     * محتوا — آیتم‌های ناوبری (یک‌بار تعریف؛ هم در نوار دسکتاپ، هم در
     * پنل موبایل رندر می‌شود)
     * =================================================================== */

    private function register_nav_controls(): void
    {
        $this->start_controls_section('section_nav', [
            'label' => __('آیتم‌های ناوبری', 'bakery-widgets'),
            'tab' => Controls_Manager::TAB_CONTENT,
        ]);

        $repeater = new Repeater();

        $repeater->add_control('label', [
            'label' => __('برچسب', 'bakery-widgets'),
            'type' => Controls_Manager::TEXT,
            'default' => __('آیتم منو', 'bakery-widgets'),
            'label_block' => true,
        ]);

        $repeater->add_control('url', [
            'label' => __('لینک', 'bakery-widgets'),
            'type' => Controls_Manager::URL,
            'default' => ['url' => ''],
            'label_block' => true,
        ]);

        $repeater->add_control('icon', [
            'label' => __('آیکون (فقط پنل موبایل)', 'bakery-widgets'),
            'type' => Controls_Manager::MEDIA,
            'media_types' => ['image', 'svg'],
            'description' => __('در نوار دسکتاپ نمایش داده نمی‌شود؛ فقط داخل نشان دایره‌ایِ همین ردیف در پنل منوی موبایل استفاده می‌شود.', 'bakery-widgets'),
        ]);

        $this->add_control('nav_items', [
            'label' => __('آیتم‌ها', 'bakery-widgets'),
            'type' => Controls_Manager::REPEATER,
            'fields' => $repeater->get_controls(),
            'default' => [
                [
                    'label' => __('خانه', 'bakery-widgets'),
                    'url' => ['url' => home_url('/')],
                    'icon' => ['url' => BAKERY_WIDGETS_URL . 'assets/icons/home.svg'],
                ],
                [
                    'label' => __('سفارشات قبلی', 'bakery-widgets'),
                    'url' => ['url' => ''],
                    'icon' => ['url' => BAKERY_WIDGETS_URL . 'assets/icons/calendar.svg'],
                ],
            ],
            'title_field' => '{{{ label }}}',
        ]);

        $this->end_controls_section();
    }

    /* =====================================================================
     * محتوا — پانویس پنل موبایل
     * =================================================================== */

    private function register_panel_footer_controls(): void
    {
        $this->start_controls_section('section_panel_footer', [
            'label' => __('پانویس پنل موبایل', 'bakery-widgets'),
            'tab' => Controls_Manager::TAB_CONTENT,
        ]);

        $this->add_control('panel_footer_line1', [
            'label' => __('متن خط اول', 'bakery-widgets'),
            'type' => Controls_Manager::TEXT,
            'default' => __('بیکری عظام — نسخه ۱.۰.۰', 'bakery-widgets'),
            'label_block' => true,
        ]);

        $this->add_control('panel_footer_line2', [
            'label' => __('متن خط دوم', 'bakery-widgets'),
            'type' => Controls_Manager::TEXT,
            'default' => __('© ۱۴۰۵ تمامی حقوق محفوظ است', 'bakery-widgets'),
            'label_block' => true,
        ]);

        $this->end_controls_section();
    }

    /* =====================================================================
     * استایل — نوار هدر
     * =================================================================== */

    private function register_bar_style_controls(): void
    {
        $this->start_controls_section('section_style_bar', [
            'label' => __('نوار هدر', 'bakery-widgets'),
            'tab' => Controls_Manager::TAB_STYLE,
        ]);

        $this->add_control('bar_sticky', [
            'label' => __('چسبان به بالای صفحه', 'bakery-widgets'),
            'type' => Controls_Manager::SWITCHER,
            'default' => '',
            'selectors' => [
                '{{WRAPPER}} .bkw-header' => 'position: sticky; top: 0; z-index: 999;',
            ],
        ]);

        $this->add_control('bar_bg_color', [
            'label' => __('رنگ پس‌زمینه', 'bakery-widgets'),
            'type' => Controls_Manager::COLOR,
            'default' => '#faf6f0',
            'selectors' => [
                '{{WRAPPER}} .bkw-header' => 'background-color: {{VALUE}};',
            ],
        ]);

        $this->add_control('bar_border_color', [
            'label' => __('رنگ خط پایین', 'bakery-widgets'),
            'type' => Controls_Manager::COLOR,
            'default' => '#eaded6',
            'selectors' => [
                '{{WRAPPER}} .bkw-header' => 'border-bottom-color: {{VALUE}};',
            ],
        ]);

        $this->add_control('bar_border_width', [
            'label' => __('ضخامت خط پایین', 'bakery-widgets'),
            'type' => Controls_Manager::SLIDER,
            'size_units' => ['px'],
            'range' => ['px' => ['min' => 0, 'max' => 6]],
            'default' => ['unit' => 'px', 'size' => 1],
            'selectors' => [
                '{{WRAPPER}} .bkw-header' => 'border-bottom-style: solid; border-bottom-width: {{SIZE}}{{UNIT}};',
            ],
        ]);

        $this->add_responsive_control('bar_padding', [
            'label' => __('پدینگ', 'bakery-widgets'),
            'type' => Controls_Manager::DIMENSIONS,
            'size_units' => ['px', '%'],
            'default' => ['top' => '24', 'right' => '120', 'bottom' => '24', 'left' => '120', 'unit' => 'px', 'isLinked' => false],
            'mobile_default' => ['top' => '12', 'right' => '16', 'bottom' => '16', 'left' => '16', 'unit' => 'px', 'isLinked' => false],
            'selectors' => [
                '{{WRAPPER}} .bkw-header' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
            ],
        ]);

        $this->end_controls_section();
    }

    /* =====================================================================
     * استایل — برند
     * =================================================================== */

    private function register_brand_style_controls(): void
    {
        $this->start_controls_section('section_style_brand', [
            'label' => __('برند', 'bakery-widgets'),
            'tab' => Controls_Manager::TAB_STYLE,
        ]);

        $this->add_responsive_control('brand_gap', [
            'label' => __('فاصله آیکون تا نام', 'bakery-widgets'),
            'type' => Controls_Manager::SLIDER,
            'size_units' => ['px'],
            'range' => ['px' => ['min' => 0, 'max' => 30]],
            'default' => ['unit' => 'px', 'size' => 10],
            'selectors' => [
                '{{WRAPPER}} .bkw-header__brand' => 'gap: {{SIZE}}{{UNIT}};',
            ],
        ]);

        $this->add_responsive_control('brand_logo_size', [
            'label' => __('اندازهٔ آیکون', 'bakery-widgets'),
            'type' => Controls_Manager::SLIDER,
            'size_units' => ['px'],
            'range' => ['px' => ['min' => 16, 'max' => 80]],
            'default' => ['unit' => 'px', 'size' => 40],
            'mobile_default' => ['unit' => 'px', 'size' => 32],
            'selectors' => [
                '{{WRAPPER}} .bkw-header__brand-logo svg, {{WRAPPER}} .bkw-header__brand-logo img' => 'width: {{SIZE}}{{UNIT}}; height: {{SIZE}}{{UNIT}};',
            ],
        ]);

        $this->add_group_control(Group_Control_Typography::get_type(), [
            'name' => 'brand_typography',
            'selector' => '{{WRAPPER}} .bkw-header__brand-text',
        ]);

        $this->add_control('brand_color', [
            'label' => __('رنگ نام', 'bakery-widgets'),
            'type' => Controls_Manager::COLOR,
            'default' => '#2a1e17',
            'selectors' => [
                '{{WRAPPER}} .bkw-header__brand-text' => 'color: {{VALUE}};',
            ],
        ]);

        $this->end_controls_section();
    }

    /* =====================================================================
     * استایل — ناوبری دسکتاپ
     * =================================================================== */

    private function register_nav_style_controls(): void
    {
        $this->start_controls_section('section_style_nav', [
            'label' => __('ناوبری دسکتاپ', 'bakery-widgets'),
            'tab' => Controls_Manager::TAB_STYLE,
        ]);

        $this->add_responsive_control('nav_gap', [
            'label' => __('فاصله بین آیتم‌ها', 'bakery-widgets'),
            'type' => Controls_Manager::SLIDER,
            'size_units' => ['px'],
            'range' => ['px' => ['min' => 0, 'max' => 60]],
            'default' => ['unit' => 'px', 'size' => 32],
            'selectors' => [
                '{{WRAPPER}} .bkw-header__nav' => 'gap: {{SIZE}}{{UNIT}};',
            ],
        ]);

        $this->add_control('heading_nav_link', [
            'label' => __('آیتم عادی', 'bakery-widgets'),
            'type' => Controls_Manager::HEADING,
        ]);

        $this->add_group_control(Group_Control_Typography::get_type(), [
            'name' => 'nav_link_typography',
            'selector' => '{{WRAPPER}} .bkw-header__nav-link',
        ]);

        $this->add_control('nav_link_color', [
            'label' => __('رنگ', 'bakery-widgets'),
            'type' => Controls_Manager::COLOR,
            'default' => '#615249',
            'selectors' => [
                '{{WRAPPER}} .bkw-header__nav-link' => 'color: {{VALUE}};',
            ],
        ]);

        $this->add_control('nav_link_color_hover', [
            'label' => __('رنگ هاور', 'bakery-widgets'),
            'type' => Controls_Manager::COLOR,
            'default' => '#8c583a',
            'selectors' => [
                '{{WRAPPER}} .bkw-header__nav-link:hover' => 'color: {{VALUE}};',
            ],
        ]);

        $this->add_control('heading_nav_active', [
            'label' => __('آیتم فعال (صفحهٔ جاری)', 'bakery-widgets'),
            'type' => Controls_Manager::HEADING,
            'separator' => 'before',
        ]);

        $this->add_group_control(Group_Control_Typography::get_type(), [
            'name' => 'nav_active_typography',
            'selector' => '{{WRAPPER}} .bkw-header__nav-item--active',
        ]);

        $this->add_control('nav_active_color', [
            'label' => __('رنگ', 'bakery-widgets'),
            'type' => Controls_Manager::COLOR,
            'default' => '#8c583a',
            'selectors' => [
                '{{WRAPPER}} .bkw-header__nav-item--active' => 'color: {{VALUE}};',
            ],
        ]);

        $this->end_controls_section();
    }

    /* =====================================================================
     * استایل — دکمهٔ همبرگر (فقط موبایل)
     * =================================================================== */

    private function register_hamburger_style_controls(): void
    {
        $this->start_controls_section('section_style_hamburger', [
            'label' => __('دکمهٔ همبرگر (موبایل)', 'bakery-widgets'),
            'tab' => Controls_Manager::TAB_STYLE,
        ]);

        $this->register_icon_button_style_controls('hamburger', '.bkw-header__hamburger', 18, 12);

        $this->end_controls_section();
    }

    /* =====================================================================
     * استایل — پنل کشویی موبایل
     * =================================================================== */

    private function register_panel_style_controls(): void
    {
        $this->start_controls_section('section_style_panel', [
            'label' => __('پنل منوی موبایل', 'bakery-widgets'),
            'tab' => Controls_Manager::TAB_STYLE,
        ]);

        $this->add_control('panel_side', [
            'label' => __('جهت باز شدن', 'bakery-widgets'),
            'type' => Controls_Manager::CHOOSE,
            'options' => [
                'right' => ['title' => __('از راست', 'bakery-widgets'), 'icon' => 'eicon-h-align-right'],
                'left' => ['title' => __('از چپ', 'bakery-widgets'), 'icon' => 'eicon-h-align-left'],
            ],
            'default' => 'right',
            'selectors_dictionary' => [
                'right' => 'right: 0; left: auto; --bkw-panel-closed-x: 100%;',
                'left' => 'left: 0; right: auto; --bkw-panel-closed-x: -100%;',
            ],
            'selectors' => [
                '{{WRAPPER}} .bkw-header__panel' => '{{VALUE}}',
            ],
        ]);

        $this->add_control('panel_bg_color', [
            'label' => __('رنگ پس‌زمینه', 'bakery-widgets'),
            'type' => Controls_Manager::COLOR,
            'default' => '#faf6f0',
            'selectors' => [
                '{{WRAPPER}} .bkw-header__panel' => 'background-color: {{VALUE}};',
            ],
        ]);

        $this->add_control('panel_width', [
            'label' => __('عرض', 'bakery-widgets'),
            'type' => Controls_Manager::SLIDER,
            'size_units' => ['px'],
            'range' => ['px' => ['min' => 240, 'max' => 480]],
            'default' => ['unit' => 'px', 'size' => 320],
            'selectors' => [
                '{{WRAPPER}} .bkw-header__panel' => 'width: {{SIZE}}{{UNIT}}; max-width: 92vw;',
            ],
        ]);

        $this->add_control('panel_overlay_color', [
            'label' => __('رنگ پردهٔ پشت پنل', 'bakery-widgets'),
            'type' => Controls_Manager::COLOR,
            'default' => 'rgba(42, 30, 23, 0.45)',
            'selectors' => [
                '{{WRAPPER}} .bkw-header__panel-overlay' => 'background-color: {{VALUE}};',
            ],
        ]);

        $this->add_control('heading_panel_close', [
            'label' => __('دکمهٔ بستن', 'bakery-widgets'),
            'type' => Controls_Manager::HEADING,
            'separator' => 'before',
        ]);

        $this->register_icon_button_style_controls('panel_close', '.bkw-header__close', 16, 12);

        $this->add_control('heading_panel_user_card', [
            'label' => __('کارت کاربر', 'bakery-widgets'),
            'type' => Controls_Manager::HEADING,
            'separator' => 'before',
        ]);

        $this->add_group_control(Group_Control_Background::get_type(), [
            'name' => 'panel_user_card_background',
            'types' => ['classic'],
            'selector' => '{{WRAPPER}} .bkw-header__user-card',
            'fields_options' => ['color' => ['default' => '#ffffff']],
        ]);

        $this->add_group_control(Group_Control_Border::get_type(), [
            'name' => 'panel_user_card_border',
            'selector' => '{{WRAPPER}} .bkw-header__user-card',
            'fields_options' => [
                'border' => ['default' => 'solid'],
                'width' => ['default' => ['top' => '1', 'right' => '1', 'bottom' => '1', 'left' => '1', 'unit' => 'px']],
                'color' => ['default' => '#eaded6'],
            ],
        ]);

        $this->add_control('panel_user_card_radius', [
            'label' => __('رادیوس', 'bakery-widgets'),
            'type' => Controls_Manager::SLIDER,
            'size_units' => ['px'],
            'range' => ['px' => ['min' => 0, 'max' => 40]],
            'default' => ['unit' => 'px', 'size' => 16],
            'selectors' => [
                '{{WRAPPER}} .bkw-header__user-card' => 'border-radius: {{SIZE}}{{UNIT}};',
            ],
        ]);

        $this->add_group_control(Group_Control_Typography::get_type(), [
            'name' => 'panel_user_name_typography',
            'label' => __('تایپوگرافی نام', 'bakery-widgets'),
            'selector' => '{{WRAPPER}} .bkw-header__user-card-name',
            'fields_options' => [
                'font_size' => ['default' => ['unit' => 'px', 'size' => 14]],
                'font_weight' => ['default' => '800'],
            ],
        ]);

        $this->add_control('panel_user_name_color', [
            'label' => __('رنگ نام', 'bakery-widgets'),
            'type' => Controls_Manager::COLOR,
            'default' => '#2a1e17',
            'selectors' => [
                '{{WRAPPER}} .bkw-header__user-card-name' => 'color: {{VALUE}};',
            ],
        ]);

        $this->add_group_control(Group_Control_Typography::get_type(), [
            'name' => 'panel_user_balance_typography',
            'label' => __('تایپوگرافی موجودی', 'bakery-widgets'),
            'selector' => '{{WRAPPER}} .bkw-header__user-card-balance',
            'fields_options' => [
                'font_size' => ['default' => ['unit' => 'px', 'size' => 11]],
                'font_weight' => ['default' => '600'],
            ],
        ]);

        $this->add_control('panel_user_balance_color', [
            'label' => __('رنگ موجودی', 'bakery-widgets'),
            'type' => Controls_Manager::COLOR,
            'default' => '#8c583a',
            'selectors' => [
                '{{WRAPPER}} .bkw-header__user-card-balance' => 'color: {{VALUE}};',
            ],
        ]);

        $this->add_control('heading_panel_footer', [
            'label' => __('پانویس', 'bakery-widgets'),
            'type' => Controls_Manager::HEADING,
            'separator' => 'before',
        ]);

        $this->add_control('panel_footer_color', [
            'label' => __('رنگ متن', 'bakery-widgets'),
            'type' => Controls_Manager::COLOR,
            'default' => '#615249',
            'selectors' => [
                '{{WRAPPER}} .bkw-header__panel-footer' => 'color: {{VALUE}};',
            ],
        ]);

        $this->end_controls_section();
    }

    /**
     * کنترل‌های مشترک یک دکمهٔ آیکونی مربعی کوچک (همبرگر / بستن پنل):
     * پس‌زمینه، حاشیه، رادیوس، پدینگ، اندازه و رنگ آیکون.
     */
    private function register_icon_button_style_controls(string $prefix, string $selector_class, int $icon_size, int $radius): void
    {
        $selector = "{{WRAPPER}} {$selector_class}";

        $this->add_group_control(Group_Control_Background::get_type(), [
            'name' => $prefix . '_background',
            'types' => ['classic'],
            'selector' => $selector,
            'fields_options' => ['color' => ['default' => '#ffffff']],
        ]);

        $this->add_group_control(Group_Control_Border::get_type(), [
            'name' => $prefix . '_border',
            'selector' => $selector,
            'fields_options' => [
                'border' => ['default' => 'solid'],
                'width' => ['default' => ['top' => '1', 'right' => '1', 'bottom' => '1', 'left' => '1', 'unit' => 'px']],
                'color' => ['default' => '#eaded6'],
            ],
        ]);

        $this->add_control($prefix . '_radius', [
            'label' => __('رادیوس', 'bakery-widgets'),
            'type' => Controls_Manager::SLIDER,
            'size_units' => ['px'],
            'range' => ['px' => ['min' => 0, 'max' => 30]],
            'default' => ['unit' => 'px', 'size' => $radius],
            'selectors' => [$selector => 'border-radius: {{SIZE}}{{UNIT}};'],
        ]);

        $this->add_control($prefix . '_icon_size', [
            'label' => __('اندازهٔ آیکون', 'bakery-widgets'),
            'type' => Controls_Manager::SLIDER,
            'size_units' => ['px'],
            'range' => ['px' => ['min' => 10, 'max' => 40]],
            'default' => ['unit' => 'px', 'size' => $icon_size],
            'selectors' => [
                "{$selector} svg" => 'width: {{SIZE}}{{UNIT}}; height: {{SIZE}}{{UNIT}};',
            ],
        ]);

        $this->add_control($prefix . '_icon_color', [
            'label' => __('رنگ آیکون', 'bakery-widgets'),
            'type' => Controls_Manager::COLOR,
            'default' => '#2a1e17',
            'selectors' => [
                "{$selector} svg [stroke]:not([stroke=\"none\"])" => 'stroke: {{VALUE}};',
            ],
        ]);
    }

    /* =====================================================================
     * استایل — ردیف‌های ناوبری پنل موبایل
     * =================================================================== */

    private function register_nav_item_style_controls(): void
    {
        $this->start_controls_section('section_style_nav_item', [
            'label' => __('ردیف‌های منوی موبایل', 'bakery-widgets'),
            'tab' => Controls_Manager::TAB_STYLE,
        ]);

        $this->add_control('nav_item_gap', [
            'label' => __('فاصله بین ردیف‌ها', 'bakery-widgets'),
            'type' => Controls_Manager::SLIDER,
            'size_units' => ['px'],
            'range' => ['px' => ['min' => 0, 'max' => 24]],
            'default' => ['unit' => 'px', 'size' => 8],
            'selectors' => [
                '{{WRAPPER}} .bkw-header__nav-stack' => 'gap: {{SIZE}}{{UNIT}};',
            ],
        ]);

        /*
         * ضخامت قلم عمداً از این گروه بیرون است.
         *
         * ردیف فعال و غیرفعال دو ضخامت متفاوت دارند (۸۰۰ و ۶۰۰) و آن
         * را کنترل‌های هر حالت می‌نویسند. اگر این‌جا هم ضخامت داشت،
         * چون کنترل‌های حالت بعد از این ثبت می‌شوند و هم‌وزن‌اند،
         * مقداری که ادمین این‌جا می‌گذاشت بی‌اثر می‌ماند — کنترلی که
         * کار نمی‌کند از نبودنش بدتر است.
         */
        $this->add_group_control(Group_Control_Typography::get_type(), [
            'name' => 'nav_item_typography',
            'label' => __('تایپوگرافی برچسب', 'bakery-widgets'),
            'selector' => '{{WRAPPER}} .bkw-header__nav-label',
            'exclude' => ['font_weight'],
            'fields_options' => [
                'font_size' => ['default' => ['unit' => 'px', 'size' => 15]],
            ],
        ]);

        $this->add_control('nav_item_chevron_color', [
            'label' => __('رنگ فلش', 'bakery-widgets'),
            'type' => Controls_Manager::COLOR,
            'default' => '#615249',
            'selectors' => [
                '{{WRAPPER}} .bkw-header__nav-chevron svg [stroke]' => 'stroke: {{VALUE}};',
            ],
        ]);

        $this->start_controls_tabs('nav_item_style_tabs');

        $this->start_controls_tab('nav_item_style_inactive', ['label' => __('غیرفعال', 'bakery-widgets')]);
        $this->register_nav_item_state_style_controls('', '#2a1e17', '600', '#ffffff');
        $this->end_controls_tab();

        $this->start_controls_tab('nav_item_style_active', ['label' => __('فعال', 'bakery-widgets')]);
        $this->register_nav_item_state_style_controls('--active', '#8c583a', '800', '#8c583a');
        $this->end_controls_tab();

        $this->end_controls_tabs();

        $this->end_controls_section();
    }

    /**
     * @param string $suffix '' برای غیرفعال، '--active' برای فعال — با
     * .bkw-header__nav-row ترکیب می‌شود.
     */
    private function register_nav_item_state_style_controls(string $suffix, string $label_color, string $label_weight, string $badge_bg): void
    {
        $row_selector = "{{WRAPPER}} .bkw-header__nav-row{$suffix}";
        $prefix = '' === $suffix ? 'nav_row' : 'nav_row_active';

        $this->add_group_control(Group_Control_Background::get_type(), [
            'name' => $prefix . '_background',
            'types' => ['classic'],
            'selector' => $row_selector,
            'fields_options' => ['color' => ['default' => '' === $suffix ? 'transparent' : '#f3ece3']],
        ]);

        $this->add_group_control(Group_Control_Border::get_type(), [
            'name' => $prefix . '_border',
            'selector' => $row_selector,
            'fields_options' => [
                'border' => ['default' => '' === $suffix ? 'none' : 'solid'],
                'width' => ['default' => ['top' => '1', 'right' => '1', 'bottom' => '1', 'left' => '1', 'unit' => 'px']],
                'color' => ['default' => '#eaded6'],
            ],
        ]);

        $this->add_control($prefix . '_radius', [
            'label' => __('رادیوس', 'bakery-widgets'),
            'type' => Controls_Manager::SLIDER,
            'size_units' => ['px'],
            'range' => ['px' => ['min' => 0, 'max' => 30]],
            'default' => ['unit' => 'px', 'size' => 14],
            'selectors' => [$row_selector => 'border-radius: {{SIZE}}{{UNIT}};'],
        ]);

        $this->add_control($prefix . '_label_color', [
            'label' => __('رنگ و ضخامت برچسب', 'bakery-widgets'),
            'type' => Controls_Manager::COLOR,
            'default' => $label_color,
            'selectors' => ["{$row_selector} .bkw-header__nav-label" => 'color: {{VALUE}}; font-weight: ' . $label_weight . ';'],
        ]);

        $this->add_control($prefix . '_badge_bg', [
            'label' => __('رنگ پس‌زمینهٔ نشان', 'bakery-widgets'),
            'type' => Controls_Manager::COLOR,
            'default' => $badge_bg,
            'selectors' => ["{$row_selector} .bkw-header__nav-badge" => 'background-color: {{VALUE}};'],
        ]);

        $this->add_control($prefix . '_badge_icon_color', [
            'label' => __('رنگ آیکون نشان', 'bakery-widgets'),
            'type' => Controls_Manager::COLOR,
            'default' => '' === $suffix ? '#8c583a' : '#ffffff',
            'selectors' => ["{$row_selector} .bkw-header__nav-badge svg [stroke]" => 'stroke: {{VALUE}};'],
        ]);
    }

    /* =====================================================================
     * استایل — دکمهٔ خروج پنل موبایل
     * =================================================================== */

    private function register_logout_button_style_controls(): void
    {
        $this->start_controls_section('section_style_panel_logout', [
            'label' => __('دکمهٔ خروج (پنل موبایل)', 'bakery-widgets'),
            'tab' => Controls_Manager::TAB_STYLE,
            'condition' => ['show_logout' => 'yes'],
        ]);

        $selector = '{{WRAPPER}} .bkw-header__logout-button';

        $this->add_group_control(Group_Control_Typography::get_type(), [
            'name' => 'panel_logout_typography',
            'selector' => $selector,
            'fields_options' => [
                'font_size' => ['default' => ['unit' => 'px', 'size' => 14]],
                'font_weight' => ['default' => '800'],
            ],
        ]);

        $this->add_control('panel_logout_color', [
            'label' => __('رنگ متن و حاشیه', 'bakery-widgets'),
            'type' => Controls_Manager::COLOR,
            'default' => '#a8261a',
            'selectors' => [
                $selector => 'color: {{VALUE}}; border-color: {{VALUE}};',
            ],
        ]);

        $this->add_control('panel_logout_border_width', [
            'label' => __('ضخامت حاشیه', 'bakery-widgets'),
            'type' => Controls_Manager::SLIDER,
            'size_units' => ['px'],
            'range' => ['px' => ['min' => 0, 'max' => 6, 'step' => 0.5]],
            'default' => ['unit' => 'px', 'size' => 1.5],
            'selectors' => [
                $selector => 'border-style: solid; border-width: {{SIZE}}{{UNIT}};',
            ],
        ]);

        $this->add_control('panel_logout_radius', [
            'label' => __('رادیوس', 'bakery-widgets'),
            'type' => Controls_Manager::SLIDER,
            'size_units' => ['px'],
            'range' => ['px' => ['min' => 0, 'max' => 100]],
            'default' => ['unit' => 'px', 'size' => 100],
            'selectors' => [
                $selector => 'border-radius: {{SIZE}}{{UNIT}};',
            ],
        ]);

        $this->add_control('panel_logout_icon_size', [
            'label' => __('اندازهٔ آیکون', 'bakery-widgets'),
            'type' => Controls_Manager::SLIDER,
            'size_units' => ['px'],
            'range' => ['px' => ['min' => 10, 'max' => 30]],
            'default' => ['unit' => 'px', 'size' => 16],
            'selectors' => [
                "{$selector} svg" => 'width: {{SIZE}}{{UNIT}}; height: {{SIZE}}{{UNIT}};',
            ],
        ]);

        $this->end_controls_section();
    }

    /* =====================================================================
     * منطق — تشخیص خودکار آیتم فعال
     * =================================================================== */

    private function normalize_path(string $path): string
    {
        $path = '/' . ltrim($path, '/');
        $trimmed = untrailingslashit($path);

        return '' !== $trimmed ? $trimmed : '/';
    }

    private function current_request_path(): string
    {
        $uri = isset($_SERVER['REQUEST_URI']) ? wp_unslash((string) $_SERVER['REQUEST_URI']) : '/';
        $path = (string) (wp_parse_url($uri, PHP_URL_PATH) ?: '/');

        return $this->normalize_path($path);
    }

    /** فقط بر اساس مسیر URL درخواست فعلی، بدون توجه به query string */
    private function is_active_nav_url(string $url): bool
    {
        $url = trim($url);
        if ('' === $url) {
            return false;
        }

        $target_path = (string) (wp_parse_url($url, PHP_URL_PATH) ?: '/');

        return $this->normalize_path($target_path) === $this->current_request_path();
    }

    /* ---------------------------------------------------------------------
     * رندر
     * ------------------------------------------------------------------- */

    #[\Override]
    protected function render(): void
    {
        $settings = $this->get_settings_for_display();
        $nav_items = is_array($settings['nav_items'] ?? null) ? $settings['nav_items'] : [];

        echo '<header class="bkw-header" dir="rtl">';

        echo '<div class="bkw-header__desktop-row">';
        echo '<div class="bkw-header__brand-nav">';
        $this->render_brand();
        $this->render_desktop_nav($nav_items);
        echo '</div>';
        echo '<div class="bkw-header__actions">';
        $this->render_account_actions($settings);
        echo '</div>';
        echo '</div>';

        echo '<div class="bkw-header__mobile-row">';
        echo '<button type="button" class="bkw-header__hamburger" aria-label="' . esc_attr__('باز کردن منو', 'bakery-widgets') . '" aria-expanded="false" aria-controls="' . esc_attr($this->get_id()) . '-panel">';
        $this->render_icon_field(['url' => BAKERY_WIDGETS_URL . 'assets/icons/menu.svg']);
        echo '</button>';
        $this->render_brand();
        if ('yes' === $settings['show_cart']) {
            $this->render_cart_item($settings);
        }
        echo '</div>';

        if ('yes' === $settings['show_user'] && 'yes' === ($settings['show_balance'] ?? 'yes')) {
            echo '<div class="bkw-header__mobile-wallet">';
            $this->render_user_item(['user_layout' => 'compact'] + $settings);
            echo '</div>';
        }

        $this->render_mobile_panel($settings, $nav_items);

        echo '</header>';
    }

    /**
     * برند (آیکون + نام)، همیشه لینک به صفحهٔ اصلی؛ در نوار دسکتاپ و
     * ردیف موبایل هر دو همین یک متد را صدا می‌زند تا مارک‌آپ برند یک‌جا
     * تعریف شده باشد.
     */
    private function render_brand(): void
    {
        $settings = $this->get_settings_for_display();
        $text = trim((string) $settings['brand_text']);

        printf('<a class="bkw-header__brand" href="%s">', esc_url(home_url('/')));

        echo '<span class="bkw-header__brand-logo">';
        $this->render_icon_field($settings['brand_logo'] ?? []);
        echo '</span>';

        if ('' !== $text) {
            printf('<span class="bkw-header__brand-text">%s</span>', esc_html($text));
        }

        echo '</a>';
    }

    /** @param array<int, array<string, mixed>> $nav_items */
    private function render_desktop_nav(array $nav_items): void
    {
        if ([] === $nav_items) {
            return;
        }

        echo '<nav class="bkw-header__nav" aria-label="' . esc_attr__('ناوبری اصلی', 'bakery-widgets') . '">';

        foreach ($nav_items as $item) {
            $label = trim((string) ($item['label'] ?? ''));
            if ('' === $label) {
                continue;
            }

            $url = (string) ($item['url']['url'] ?? '');
            $is_active = $this->is_active_nav_url($url);

            if ('' === $url || $is_active) {
                printf(
                    '<span class="bkw-header__nav-link bkw-header__nav-item--active">%s</span>',
                    esc_html($label),
                );
                continue;
            }

            printf(
                '<a class="bkw-header__nav-link" href="%s">%s</a>',
                esc_url($url),
                esc_html($label),
            );
        }

        echo '</nav>';
    }

    /** @param array<int, array<string, mixed>> $nav_items */
    private function render_mobile_panel(array $settings, array $nav_items): void
    {
        $panel_id = $this->get_id() . '-panel';

        printf('<div class="bkw-header__panel-overlay" data-bkw-panel-overlay></div>');
        printf('<div id="%s" class="bkw-header__panel" role="dialog" aria-modal="true" aria-label="%s" data-bkw-panel>', esc_attr($panel_id), esc_attr__('منوی سایت', 'bakery-widgets'));

        echo '<div class="bkw-header__panel-content">';

        echo '<div class="bkw-header__panel-header">';
        $this->render_brand();
        echo '<button type="button" class="bkw-header__close" aria-label="' . esc_attr__('بستن منو', 'bakery-widgets') . '" data-bkw-panel-close>';
        $this->render_icon_field(['url' => BAKERY_WIDGETS_URL . 'assets/icons/x-circle.svg']);
        echo '</button>';
        echo '</div>';

        if ('yes' === $settings['show_user']) {
            $this->render_panel_user_card($settings);
        }

        $this->render_panel_nav($nav_items);

        echo '<div class="bkw-header__panel-footer-zone">';

        if ('yes' === $settings['show_logout']) {
            printf(
                '<a class="bkw-header__logout-button" href="%s">',
                esc_url($this->resolve_logout_url($settings)),
            );
            echo '<span>' . esc_html__('خروج از حساب', 'bakery-widgets') . '</span>';
            $this->render_icon_field(['url' => BAKERY_WIDGETS_URL . 'assets/icons/logout.svg']);
            echo '</a>';
        }

        $line1 = trim((string) $settings['panel_footer_line1']);
        $line2 = trim((string) $settings['panel_footer_line2']);

        if ('' !== $line1 || '' !== $line2) {
            echo '<div class="bkw-header__panel-footer">';
            if ('' !== $line1) {
                printf('<p class="bkw-header__panel-footer-line1">%s</p>', esc_html($line1));
            }
            if ('' !== $line2) {
                printf('<p class="bkw-header__panel-footer-line2">%s</p>', esc_html($line2));
            }
            echo '</div>';
        }

        echo '</div>';

        echo '</div>';
        echo '</div>';
    }

    private function render_panel_user_card(array $settings): void
    {
        $user = wp_get_current_user();
        $fallback_name = (string) $settings['guest_name_fallback'];
        $name = $this->resolve_display_name($user, '' !== trim($fallback_name) ? $fallback_name : __('کاربر مهمان', 'bakery-widgets'));

        echo '<div class="bkw-header__user-card">';
        echo '<div class="bkw-header__user-card-stack">';
        printf('<p class="bkw-header__user-card-name">%s</p>', esc_html($name));

        if ('yes' === ($settings['show_balance'] ?? 'yes')) {
            $amount = $this->format_balance($settings, $user);
            $balance_text = trim(sprintf(
                '%s %s %s',
                (string) $settings['balance_label'],
                $amount,
                (string) $settings['balance_currency'],
            ));
            printf('<p class="bkw-header__user-card-balance">%s</p>', esc_html($balance_text));
        }

        echo '</div>';
        echo '</div>';
    }

    /** @param array<int, array<string, mixed>> $nav_items */
    private function render_panel_nav(array $nav_items): void
    {
        if ([] === $nav_items) {
            return;
        }

        echo '<div class="bkw-header__nav-stack">';

        foreach ($nav_items as $item) {
            $label = trim((string) ($item['label'] ?? ''));
            if ('' === $label) {
                continue;
            }

            $url = (string) ($item['url']['url'] ?? '');
            $is_active = $this->is_active_nav_url($url);
            $tag = '' !== $url ? 'a' : 'span';
            $row_class = 'bkw-header__nav-row' . ($is_active ? ' bkw-header__nav-row--active' : '');

            printf('<%1$s class="%2$s"', esc_html($tag), esc_attr($row_class));
            if ('' !== $url) {
                printf(' href="%s"', esc_url($url));
            }
            echo '>';

            echo '<span class="bkw-header__nav-badge">';
            $this->render_icon_field($item['icon'] ?? []);
            echo '</span>';

            printf('<span class="bkw-header__nav-label">%s</span>', esc_html($label));

            echo '<span class="bkw-header__nav-chevron">';
            $this->render_icon_field(['url' => BAKERY_WIDGETS_URL . 'assets/icons/chevron-left.svg']);
            echo '</span>';

            printf('</%s>', esc_html($tag));
        }

        echo '</div>';
    }
}
