<?php

declare(strict_types=1);

namespace Bakery_Widgets\Widgets;

use Bakery_Widgets\Svg;
use Elementor\Controls_Manager;
use Elementor\Group_Control_Background;
use Elementor\Group_Control_Border;
use Elementor\Group_Control_Box_Shadow;
use Elementor\Group_Control_Typography;
use Elementor\Widget_Base;
use WC_Cart;
use WP_User;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * ویجت «نوار حساب کاربری» — سه پیل مستقل: سبد خرید (+ بج تعداد)، کاربر
 * (نام + موجودی)، خروج. هر سه از یک ورودی سایت با ورود اجباری استفاده
 * می‌کنند (بدون حالت مهمان)، پس همیشه کاربر واقعی لاگین فرض می‌شود.
 *
 * هر پیل با یک سوییچر مستقل قابل حذف است تا بشود «تک‌مورد» یا هر
 * ترکیبی نمایش داد؛ جایگاه نهایی‌شان هم با کنترل عددی «ترتیب نمایش»
 * (CSS order) مستقل تعیین می‌شود، دقیقاً مثل ویجت قیمت — نه با ترتیب
 * DOM. مقدار پیش‌فرض این سه عدد طوری است که خروجی با رفرنس فیگما یکی
 * شود: خروج ۱ (سمت راست/ابتدای RTL)، کاربر ۲، سبد ۳ (سمت چپ/انتها).
 *
 * موجودی هنوز به منبع واقعی وصل نیست — از فیلتر `bkw_account_balance`
 * می‌آید که با یک عدد فرضی (تنظیم‌پذیر از تب محتوا) پیش‌فرض می‌خورد؛
 * وقتی منبع واقعی مشخص شد، فقط باید به این فیلتر هوک زد، چیزی در این
 * ویجت عوض نمی‌شود. کلیک روی سبد هم عمداً بدون مقصد است (هنوز تصمیم
 * گرفته نشده چه اتفاقی بیفتد) — اگر «لینک سبد خرید» خالی بماند، پیل
 * به‌جای <a> یک <div> غیرقابل‌کلیک است.
 */
final class Account_Bar extends Widget_Base
{
    #[\Override]
    public function get_name(): string
    {
        return 'bakery-account-bar';
    }

    #[\Override]
    public function get_title(): string
    {
        return __('نوار حساب کاربری بیکری عظام', 'bakery-widgets');
    }

    #[\Override]
    public function get_icon(): string
    {
        return 'eicon-user-circle-o';
    }

    #[\Override]
    public function get_categories(): array
    {
        return ['bakery'];
    }

    #[\Override]
    public function get_keywords(): array
    {
        return ['حساب کاربری', 'سبد خرید', 'خروج', 'موجودی', 'account', 'cart', 'logout', 'profile', 'wallet', 'بیکری', 'عظام'];
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
        $this->register_cart_controls();
        $this->register_user_controls();
        $this->register_logout_controls();
        $this->register_layout_controls();

        // تب استایل
        $this->register_common_box_style_controls();
        $this->register_cart_icon_style_controls();
        $this->register_badge_style_controls();
        $this->register_logout_icon_style_controls();
        $this->register_name_style_controls();
        $this->register_separator_style_controls();
        $this->register_balance_style_controls();
    }

    /* =====================================================================
     * محتوا — سبد خرید
     * =================================================================== */

    private function register_cart_controls(): void
    {
        $this->start_controls_section('section_cart', [
            'label' => __('سبد خرید', 'bakery-widgets'),
            'tab' => Controls_Manager::TAB_CONTENT,
        ]);

        $this->add_control('show_cart', [
            'label' => __('نمایش این بخش', 'bakery-widgets'),
            'type' => Controls_Manager::SWITCHER,
            'default' => 'yes',
        ]);

        $this->add_control('cart_icon', [
            'label' => __('آیکون', 'bakery-widgets'),
            'type' => Controls_Manager::MEDIA,
            'media_types' => ['image', 'svg'],
            'default' => ['url' => BAKERY_WIDGETS_URL . 'assets/icons/cart.svg'],
            'description' => __('برای جایگزینی، تصویر یا SVG دلخواه آپلود کنید.', 'bakery-widgets'),
            'condition' => ['show_cart' => 'yes'],
        ]);

        $this->add_control('cart_url', [
            'label' => __('لینک', 'bakery-widgets'),
            'type' => Controls_Manager::URL,
            'description' => __('خالی بماند تا این پیل فعلاً قابل‌کلیک نباشد.', 'bakery-widgets'),
            'condition' => ['show_cart' => 'yes'],
        ]);

        $this->add_control('heading_cart_badge', [
            'label' => __('بج تعداد', 'bakery-widgets'),
            'type' => Controls_Manager::HEADING,
            'separator' => 'before',
            'condition' => ['show_cart' => 'yes'],
        ]);

        $this->add_control('show_cart_badge', [
            'label' => __('نمایش بج تعداد', 'bakery-widgets'),
            'type' => Controls_Manager::SWITCHER,
            'default' => 'yes',
            'condition' => ['show_cart' => 'yes'],
        ]);

        $this->add_control('cart_badge_show_zero', [
            'label' => __('نمایش حتی وقتی سبد خالی است', 'bakery-widgets'),
            'type' => Controls_Manager::SWITCHER,
            'default' => '',
            'condition' => ['show_cart' => 'yes', 'show_cart_badge' => 'yes'],
        ]);

        $this->end_controls_section();
    }

    /* =====================================================================
     * محتوا — کاربر
     * =================================================================== */

    private function register_user_controls(): void
    {
        $this->start_controls_section('section_user', [
            'label' => __('کاربر', 'bakery-widgets'),
            'tab' => Controls_Manager::TAB_CONTENT,
        ]);

        $this->add_control('show_user', [
            'label' => __('نمایش این بخش', 'bakery-widgets'),
            'type' => Controls_Manager::SWITCHER,
            'default' => 'yes',
        ]);

        $this->add_control('guest_name_fallback', [
            'label' => __('نام جایگزین', 'bakery-widgets'),
            'type' => Controls_Manager::TEXT,
            'default' => __('کاربر مهمان', 'bakery-widgets'),
            'description' => __('وقتی نام و نام‌خانوادگی کاربر در حساب کاربری ثبت نشده باشد نمایش داده می‌شود.', 'bakery-widgets'),
            'condition' => ['show_user' => 'yes'],
        ]);

        $this->add_control('heading_balance', [
            'label' => __('موجودی', 'bakery-widgets'),
            'type' => Controls_Manager::HEADING,
            'separator' => 'before',
            'condition' => ['show_user' => 'yes'],
        ]);

        $this->add_control('show_balance', [
            'label' => __('نمایش موجودی', 'bakery-widgets'),
            'type' => Controls_Manager::SWITCHER,
            'default' => 'yes',
            'condition' => ['show_user' => 'yes'],
        ]);

        $this->add_control('separator_text', [
            'label' => __('جداکننده', 'bakery-widgets'),
            'type' => Controls_Manager::TEXT,
            'default' => '-',
            'condition' => ['show_user' => 'yes', 'show_balance' => 'yes'],
        ]);

        $this->add_control('balance_label', [
            'label' => __('برچسب', 'bakery-widgets'),
            'type' => Controls_Manager::TEXT,
            'default' => __('موجودی :', 'bakery-widgets'),
            'condition' => ['show_user' => 'yes', 'show_balance' => 'yes'],
        ]);

        $this->add_control('balance_currency', [
            'label' => __('واحد پول', 'bakery-widgets'),
            'type' => Controls_Manager::TEXT,
            'default' => __('تومان', 'bakery-widgets'),
            'condition' => ['show_user' => 'yes', 'show_balance' => 'yes'],
        ]);

        /*
         * موجودی هنوز به هیچ منبع واقعی وصل نیست — این عدد فقط ورودیِ
         * فالبک فیلتر `bkw_account_balance` است (رجوع کن به resolve_balance()).
         * وقتی منبع واقعی مشخص شود، همین‌جا می‌ماند و فقط دیگر استفاده
         * نمی‌شود، چون فیلتر مقدار واقعی را جایگزین می‌کند.
         */
        $this->add_control('balance_fallback', [
            'label' => __('عدد فرضی (تا وصل‌شدن منبع واقعی)', 'bakery-widgets'),
            'type' => Controls_Manager::NUMBER,
            'default' => 10000000,
            'min' => 0,
            'condition' => ['show_user' => 'yes', 'show_balance' => 'yes'],
        ]);

        $this->end_controls_section();
    }

    /* =====================================================================
     * محتوا — خروج
     * =================================================================== */

    private function register_logout_controls(): void
    {
        $this->start_controls_section('section_logout', [
            'label' => __('خروج', 'bakery-widgets'),
            'tab' => Controls_Manager::TAB_CONTENT,
        ]);

        $this->add_control('show_logout', [
            'label' => __('نمایش این بخش', 'bakery-widgets'),
            'type' => Controls_Manager::SWITCHER,
            'default' => 'yes',
        ]);

        $this->add_control('logout_icon', [
            'label' => __('آیکون', 'bakery-widgets'),
            'type' => Controls_Manager::MEDIA,
            'media_types' => ['image', 'svg'],
            'default' => ['url' => BAKERY_WIDGETS_URL . 'assets/icons/logout.svg'],
            'description' => __('برای جایگزینی، تصویر یا SVG دلخواه آپلود کنید.', 'bakery-widgets'),
            'condition' => ['show_logout' => 'yes'],
        ]);

        $this->add_control('logout_redirect_url', [
            'label' => __('مقصد بعد از خروج', 'bakery-widgets'),
            'type' => Controls_Manager::URL,
            'description' => __('خالی = صفحهٔ اصلی سایت.', 'bakery-widgets'),
            'condition' => ['show_logout' => 'yes'],
        ]);

        $this->end_controls_section();
    }

    /* =====================================================================
     * محتوا — چیدمان (فاصله بین پیل‌ها و ترتیب نمایش هر پیل)
     * =================================================================== */

    private function register_layout_controls(): void
    {
        $this->start_controls_section('section_layout', [
            'label' => __('چیدمان', 'bakery-widgets'),
            'tab' => Controls_Manager::TAB_CONTENT,
        ]);

        $this->add_responsive_control('bar_gap', [
            'label' => __('فاصله بین پیل‌ها', 'bakery-widgets'),
            'type' => Controls_Manager::SLIDER,
            'size_units' => ['px', 'em', 'rem'],
            'range' => ['px' => ['min' => 0, 'max' => 60]],
            'default' => ['size' => 12, 'unit' => 'px'],
            'selectors' => [
                '{{WRAPPER}} .bkw-account-bar' => 'gap: {{SIZE}}{{UNIT}};',
            ],
        ]);

        $this->add_responsive_control('bar_wrap', [
            'label' => __('شکستن به سطر بعد (wrap)', 'bakery-widgets'),
            'type' => Controls_Manager::CHOOSE,
            'options' => [
                'nowrap' => ['title' => __('در یک خط بماند', 'bakery-widgets'), 'icon' => 'eicon-flex eicon-nowrap'],
                'wrap' => ['title' => __('به چند خط تقسیم شود', 'bakery-widgets'), 'icon' => 'eicon-flex eicon-wrap'],
            ],
            'default' => 'nowrap',
            'selectors' => [
                '{{WRAPPER}} .bkw-account-bar' => 'flex-wrap: {{VALUE}};',
            ],
        ]);

        $this->add_control('heading_order', [
            'label' => __('ترتیب نمایش پیل‌ها', 'bakery-widgets'),
            'type' => Controls_Manager::HEADING,
            'separator' => 'before',
            'description' => __('عدد کوچک‌تر، ابتدای سطر (سمت راست) نمایش داده می‌شود.', 'bakery-widgets'),
        ]);

        $this->add_control('order_logout', [
            'label' => __('خروج', 'bakery-widgets'),
            'type' => Controls_Manager::NUMBER,
            'default' => 1,
            'condition' => ['show_logout' => 'yes'],
            'selectors' => [
                '{{WRAPPER}} .bkw-account-bar__logout' => 'order: {{VALUE}};',
            ],
        ]);

        $this->add_control('order_user', [
            'label' => __('کاربر', 'bakery-widgets'),
            'type' => Controls_Manager::NUMBER,
            'default' => 2,
            'condition' => ['show_user' => 'yes'],
            'selectors' => [
                '{{WRAPPER}} .bkw-account-bar__user' => 'order: {{VALUE}};',
            ],
        ]);

        $this->add_control('order_cart', [
            'label' => __('سبد خرید', 'bakery-widgets'),
            'type' => Controls_Manager::NUMBER,
            'default' => 3,
            'condition' => ['show_cart' => 'yes'],
            'selectors' => [
                '{{WRAPPER}} .bkw-account-bar__cart' => 'order: {{VALUE}};',
            ],
        ]);

        $this->end_controls_section();
    }

    /* =====================================================================
     * استایل — کادر مشترک هر پیل
     *
     * هر سه پیل در رفرنس فیگما دقیقاً یک ظاهر دارند (سفید، بردر یکسان،
     * رادیوس ۱۶، پدینگ ۱۶/۱۰)، پس یک سکشن مشترک روی کلاس __item هر سه
     * را با هم کنترل می‌کند — به‌جای سه سکشن مشابه و تکراری.
     * =================================================================== */

    private function register_common_box_style_controls(): void
    {
        $this->start_controls_section('section_style_box', [
            'label' => __('کادر پیل‌ها', 'bakery-widgets'),
            'tab' => Controls_Manager::TAB_STYLE,
        ]);

        $this->add_responsive_control('item_padding', [
            'label' => __('پدینگ', 'bakery-widgets'),
            'type' => Controls_Manager::DIMENSIONS,
            'size_units' => ['px', 'em', '%'],
            'default' => ['top' => '10', 'right' => '16', 'bottom' => '10', 'left' => '16', 'unit' => 'px', 'isLinked' => false],
            'selectors' => [
                '{{WRAPPER}} .bkw-account-bar__item' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
            ],
        ]);

        $this->add_responsive_control('item_gap', [
            'label' => __('فاصله داخلی محتوا', 'bakery-widgets'),
            'type' => Controls_Manager::SLIDER,
            'size_units' => ['px', 'em'],
            'range' => ['px' => ['min' => 0, 'max' => 40]],
            'default' => ['size' => 12, 'unit' => 'px'],
            'selectors' => [
                '{{WRAPPER}} .bkw-account-bar__item' => 'gap: {{SIZE}}{{UNIT}};',
            ],
        ]);

        $this->start_controls_tabs('box_style_tabs');

        $this->start_controls_tab('box_style_normal', ['label' => __('عادی', 'bakery-widgets')]);

        $this->add_group_control(Group_Control_Background::get_type(), [
            'name' => 'item_background',
            'label' => __('پس‌زمینه', 'bakery-widgets'),
            'types' => ['classic', 'gradient'],
            'selector' => '{{WRAPPER}} .bkw-account-bar__item',
            'fields_options' => ['background' => ['default' => 'classic'], 'color' => ['default' => '#ffffff']],
        ]);

        $this->add_group_control(Group_Control_Border::get_type(), [
            'name' => 'item_border',
            'label' => __('حاشیه', 'bakery-widgets'),
            'selector' => '{{WRAPPER}} .bkw-account-bar__item',
            'fields_options' => [
                'border' => ['default' => 'solid'],
                'width' => ['default' => ['top' => '1', 'right' => '1', 'bottom' => '1', 'left' => '1', 'unit' => 'px']],
                'color' => ['default' => '#eaded6'],
            ],
        ]);

        $this->add_responsive_control('item_radius', [
            'label' => __('رادیوس', 'bakery-widgets'),
            'type' => Controls_Manager::DIMENSIONS,
            'size_units' => ['px', '%'],
            'default' => ['top' => '16', 'right' => '16', 'bottom' => '16', 'left' => '16', 'unit' => 'px', 'isLinked' => true],
            'selectors' => [
                '{{WRAPPER}} .bkw-account-bar__item' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
            ],
        ]);

        $this->add_group_control(Group_Control_Box_Shadow::get_type(), [
            'name' => 'item_shadow',
            'label' => __('سایه', 'bakery-widgets'),
            'selector' => '{{WRAPPER}} .bkw-account-bar__item',
        ]);

        $this->end_controls_tab();

        $this->start_controls_tab('box_style_hover', ['label' => __('هاور', 'bakery-widgets')]);

        $this->add_group_control(Group_Control_Background::get_type(), [
            'name' => 'item_background_hover',
            'label' => __('پس‌زمینه', 'bakery-widgets'),
            'types' => ['classic', 'gradient'],
            'selector' => '{{WRAPPER}} .bkw-account-bar__item:hover',
        ]);

        $this->add_control('item_border_color_hover', [
            'label' => __('رنگ حاشیه', 'bakery-widgets'),
            'type' => Controls_Manager::COLOR,
            'selectors' => [
                '{{WRAPPER}} .bkw-account-bar__item:hover' => 'border-color: {{VALUE}};',
            ],
        ]);

        $this->add_group_control(Group_Control_Box_Shadow::get_type(), [
            'name' => 'item_shadow_hover',
            'label' => __('سایه', 'bakery-widgets'),
            'selector' => '{{WRAPPER}} .bkw-account-bar__item:hover',
        ]);

        $this->end_controls_tab();
        $this->end_controls_tabs();

        $this->add_control('item_transition', [
            'label' => __('مدت زمان انیمیشن (میلی‌ثانیه)', 'bakery-widgets'),
            'type' => Controls_Manager::SLIDER,
            'range' => ['px' => ['min' => 0, 'max' => 2000]],
            'default' => ['size' => 200],
            'separator' => 'before',
            'selectors' => [
                '{{WRAPPER}} .bkw-account-bar__item' => 'transition: all {{SIZE}}ms ease;',
            ],
        ]);

        $this->end_controls_section();
    }

    /* =====================================================================
     * استایل — آیکون سبد خرید
     * =================================================================== */

    private function register_cart_icon_style_controls(): void
    {
        $this->start_controls_section('section_style_cart_icon', [
            'label' => __('آیکون سبد خرید', 'bakery-widgets'),
            'tab' => Controls_Manager::TAB_STYLE,
            'condition' => ['show_cart' => 'yes'],
        ]);

        $this->register_icon_style_controls('cart', '.bkw-account-bar__cart');

        $this->end_controls_section();
    }

    /* =====================================================================
     * استایل — بج تعداد سبد
     * =================================================================== */

    private function register_badge_style_controls(): void
    {
        $this->start_controls_section('section_style_badge', [
            'label' => __('بج تعداد', 'bakery-widgets'),
            'tab' => Controls_Manager::TAB_STYLE,
            'condition' => ['show_cart' => 'yes', 'show_cart_badge' => 'yes'],
        ]);

        $selector = '{{WRAPPER}} .bkw-account-bar__badge';

        $this->add_group_control(Group_Control_Typography::get_type(), [
            'name' => 'badge_typography',
            'selector' => $selector,
        ]);

        $this->add_control('badge_color', [
            'label' => __('رنگ متن', 'bakery-widgets'),
            'type' => Controls_Manager::COLOR,
            'default' => '#ffffff',
            'selectors' => [$selector => 'color: {{VALUE}};'],
        ]);

        $this->add_control('badge_bg_color', [
            'label' => __('رنگ پس‌زمینه', 'bakery-widgets'),
            'type' => Controls_Manager::COLOR,
            'default' => '#8c583a',
            'selectors' => [$selector => 'background-color: {{VALUE}};'],
        ]);

        $this->add_responsive_control('badge_padding', [
            'label' => __('پدینگ', 'bakery-widgets'),
            'type' => Controls_Manager::DIMENSIONS,
            'size_units' => ['px', 'em'],
            'default' => ['top' => '2', 'right' => '8', 'bottom' => '2', 'left' => '8', 'unit' => 'px', 'isLinked' => false],
            'selectors' => [$selector => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};'],
        ]);

        $this->add_responsive_control('badge_radius', [
            'label' => __('رادیوس', 'bakery-widgets'),
            'type' => Controls_Manager::DIMENSIONS,
            'size_units' => ['px', '%'],
            'default' => ['top' => '8', 'right' => '8', 'bottom' => '8', 'left' => '8', 'unit' => 'px', 'isLinked' => true],
            'selectors' => [$selector => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};'],
        ]);

        $this->add_responsive_control('badge_min_width', [
            'label' => __('حداقل عرض', 'bakery-widgets'),
            'type' => Controls_Manager::SLIDER,
            'size_units' => ['px'],
            'range' => ['px' => ['min' => 0, 'max' => 60]],
            'selectors' => [$selector => 'min-width: {{SIZE}}{{UNIT}}; box-sizing: border-box;'],
        ]);

        $this->end_controls_section();
    }

    /* =====================================================================
     * استایل — آیکون خروج
     * =================================================================== */

    private function register_logout_icon_style_controls(): void
    {
        $this->start_controls_section('section_style_logout_icon', [
            'label' => __('آیکون خروج', 'bakery-widgets'),
            'tab' => Controls_Manager::TAB_STYLE,
            'condition' => ['show_logout' => 'yes'],
        ]);

        $this->register_icon_style_controls('logout', '.bkw-account-bar__logout');

        $this->end_controls_section();
    }

    /**
     * کنترل‌های مشترک استایل هر آیکون (سبد/خروج): اندازه، رنگ fill،
     * رنگ/ضخامت stroke، قرینهٔ افقی — عادی و هاور. هر دو آیکون فیگما
     * فقط stroke دارند (fill="none")، پس «رنگ آیکون» روی fill هم اثر
     * می‌گذارد تا اگر کاربر بعداً یک SVG توپُر جایگزین کرد، همان کنترل
     * برایش هم کار کند — دقیقاً مثل آیکون‌باکس.
     */
    private function register_icon_style_controls(string $prefix, string $item_class): void
    {
        $icon_selector = "{{WRAPPER}} {$item_class} .bkw-account-bar__icon";

        $this->add_responsive_control($prefix . '_icon_size', [
            'label' => __('اندازه', 'bakery-widgets'),
            'type' => Controls_Manager::SLIDER,
            'size_units' => ['px'],
            'range' => ['px' => ['min' => 8, 'max' => 60]],
            'default' => ['unit' => 'px', 'size' => 20],
            'selectors' => [
                "{$icon_selector} svg, {$icon_selector} img" => 'width: {{SIZE}}{{UNIT}}; height: {{SIZE}}{{UNIT}};',
            ],
        ]);

        $this->add_control($prefix . '_icon_flip', [
            'label' => __('قرینهٔ افقی', 'bakery-widgets'),
            'type' => Controls_Manager::SWITCHER,
            'default' => '',
            'selectors' => [
                $icon_selector => 'transform: scaleX(-1);',
            ],
        ]);

        $this->start_controls_tabs($prefix . '_icon_style_tabs');

        $this->start_controls_tab($prefix . '_icon_style_normal', ['label' => __('عادی', 'bakery-widgets')]);

        $this->add_control($prefix . '_icon_fill_color', [
            'label' => __('رنگ آیکون', 'bakery-widgets'),
            'type' => Controls_Manager::COLOR,
            'description' => __('بخش‌های توپُر (fill) فایل SVG را بازرنگ می‌کند.', 'bakery-widgets'),
            'selectors' => [
                "{$icon_selector} svg [fill]:not([fill=\"none\"]):not([fill=\"transparent\"])" => 'fill: {{VALUE}};',
                "{$icon_selector} svg :is(path,circle,rect,ellipse,polygon,polyline,line):not([fill]):not([stroke])" => 'fill: {{VALUE}};',
            ],
        ]);

        $this->add_control($prefix . '_icon_stroke_color', [
            'label' => __('رنگ خطوط آیکون', 'bakery-widgets'),
            'type' => Controls_Manager::COLOR,
            'default' => '#2a1e17',
            'description' => __('بخش‌های خطی (stroke) فایل SVG را بازرنگ می‌کند.', 'bakery-widgets'),
            'selectors' => [
                "{$icon_selector} svg [stroke]:not([stroke=\"none\"]):not([stroke=\"transparent\"])" => 'stroke: {{VALUE}};',
            ],
        ]);

        $this->add_control($prefix . '_icon_stroke_width', [
            'label' => __('ضخامت خط', 'bakery-widgets'),
            'type' => Controls_Manager::NUMBER,
            'min' => 0.5,
            'max' => 6,
            'step' => 0.5,
            'default' => 2,
            'selectors' => [
                "{$icon_selector} svg [stroke]:not([stroke=\"none\"])" => 'stroke-width: {{VALUE}};',
            ],
        ]);

        $this->end_controls_tab();

        $this->start_controls_tab($prefix . '_icon_style_hover', ['label' => __('هاور', 'bakery-widgets')]);

        $this->add_control($prefix . '_icon_fill_color_hover', [
            'label' => __('رنگ آیکون', 'bakery-widgets'),
            'type' => Controls_Manager::COLOR,
            'selectors' => [
                "{{WRAPPER}} {$item_class}:hover .bkw-account-bar__icon svg [fill]:not([fill=\"none\"]):not([fill=\"transparent\"])" => 'fill: {{VALUE}};',
                "{{WRAPPER}} {$item_class}:hover .bkw-account-bar__icon svg :is(path,circle,rect,ellipse,polygon,polyline,line):not([fill]):not([stroke])" => 'fill: {{VALUE}};',
            ],
        ]);

        $this->add_control($prefix . '_icon_stroke_color_hover', [
            'label' => __('رنگ خطوط آیکون', 'bakery-widgets'),
            'type' => Controls_Manager::COLOR,
            'selectors' => [
                "{{WRAPPER}} {$item_class}:hover .bkw-account-bar__icon svg [stroke]:not([stroke=\"none\"]):not([stroke=\"transparent\"])" => 'stroke: {{VALUE}};',
            ],
        ]);

        $this->end_controls_tab();
        $this->end_controls_tabs();
    }

    /* =====================================================================
     * استایل — نام کاربر
     * =================================================================== */

    private function register_name_style_controls(): void
    {
        $this->start_controls_section('section_style_name', [
            'label' => __('نام کاربر', 'bakery-widgets'),
            'tab' => Controls_Manager::TAB_STYLE,
            'condition' => ['show_user' => 'yes'],
        ]);

        $selector = '{{WRAPPER}} .bkw-account-bar__name';

        $this->add_group_control(Group_Control_Typography::get_type(), [
            'name' => 'name_typography',
            'selector' => $selector,
        ]);

        $this->add_control('name_color', [
            'label' => __('رنگ', 'bakery-widgets'),
            'type' => Controls_Manager::COLOR,
            'default' => '#2a1e17',
            'selectors' => [$selector => 'color: {{VALUE}};'],
        ]);

        $this->end_controls_section();
    }

    /* =====================================================================
     * استایل — جداکننده
     * =================================================================== */

    private function register_separator_style_controls(): void
    {
        $this->start_controls_section('section_style_separator', [
            'label' => __('جداکننده', 'bakery-widgets'),
            'tab' => Controls_Manager::TAB_STYLE,
            'condition' => ['show_user' => 'yes', 'show_balance' => 'yes'],
        ]);

        $selector = '{{WRAPPER}} .bkw-account-bar__separator';

        $this->add_group_control(Group_Control_Typography::get_type(), [
            'name' => 'separator_typography',
            'selector' => $selector,
        ]);

        $this->add_control('separator_color', [
            'label' => __('رنگ', 'bakery-widgets'),
            'type' => Controls_Manager::COLOR,
            'default' => '#2a1e17',
            'selectors' => [$selector => 'color: {{VALUE}};'],
        ]);

        $this->end_controls_section();
    }

    /* =====================================================================
     * استایل — موجودی (برچسب / عدد / واحد پول مستقل)
     * =================================================================== */

    private function register_balance_style_controls(): void
    {
        $this->start_controls_section('section_style_balance', [
            'label' => __('موجودی', 'bakery-widgets'),
            'tab' => Controls_Manager::TAB_STYLE,
            'condition' => ['show_user' => 'yes', 'show_balance' => 'yes'],
        ]);

        $this->add_responsive_control('balance_gap', [
            'label' => __('فاصله بین برچسب/عدد/واحد پول', 'bakery-widgets'),
            'type' => Controls_Manager::SLIDER,
            'size_units' => ['px'],
            'range' => ['px' => ['min' => 0, 'max' => 24]],
            'default' => ['size' => 4, 'unit' => 'px'],
            'selectors' => [
                '{{WRAPPER}} .bkw-account-bar__balance' => 'gap: {{SIZE}}{{UNIT}};',
            ],
        ]);

        $this->add_control('heading_balance_label', [
            'label' => __('برچسب', 'bakery-widgets'),
            'type' => Controls_Manager::HEADING,
            'separator' => 'before',
        ]);
        $this->register_balance_part_style_controls('balance_label', '{{WRAPPER}} .bkw-account-bar__balance-label', '#615249');

        $this->add_control('heading_balance_amount', [
            'label' => __('عدد', 'bakery-widgets'),
            'type' => Controls_Manager::HEADING,
            'separator' => 'before',
        ]);
        $this->register_balance_part_style_controls('balance_amount', '{{WRAPPER}} .bkw-account-bar__balance-amount', '#2a1e17');

        $this->add_control('heading_balance_currency', [
            'label' => __('واحد پول', 'bakery-widgets'),
            'type' => Controls_Manager::HEADING,
            'separator' => 'before',
        ]);
        $this->register_balance_part_style_controls('balance_currency', '{{WRAPPER}} .bkw-account-bar__balance-currency', '#615249');

        $this->end_controls_section();
    }

    private function register_balance_part_style_controls(string $prefix, string $selector, string $default_color): void
    {
        $this->add_group_control(Group_Control_Typography::get_type(), [
            'name' => $prefix . '_typography',
            'selector' => $selector,
        ]);

        $this->add_control($prefix . '_color', [
            'label' => __('رنگ', 'bakery-widgets'),
            'type' => Controls_Manager::COLOR,
            'default' => $default_color,
            'selectors' => [$selector => 'color: {{VALUE}};'],
        ]);
    }

    /* =====================================================================
     * منطق — کاربر، موجودی، سبد
     * =================================================================== */

    /** نام و نام‌خانوادگی کاربر لاگین‌کرده؛ خالی‌بودن هردو یعنی display_name، بعد فالبک تنظیمات */
    private function resolve_display_name(WP_User $user, string $fallback): string
    {
        if (0 === $user->ID) {
            return $fallback;
        }

        $full_name = trim(trim((string) $user->first_name) . ' ' . trim((string) $user->last_name));
        if ('' !== $full_name) {
            return $full_name;
        }

        $display_name = trim((string) $user->display_name);

        return '' !== $display_name ? $display_name : $fallback;
    }

    /**
     * موجودی هنوز به منبع واقعی وصل نیست؛ `$fallback` (از تب محتوا) از
     * این فیلتر عبور می‌کند تا وقتی منبع واقعی مشخص شد، بدون تغییر این
     * ویجت فقط با یک `add_filter()` جایگزین شود.
     */
    private function resolve_balance(float $fallback, int $user_id): float
    {
        return (float) apply_filters('bkw_account_balance', $fallback, $user_id);
    }

    private function cart_count(): int
    {
        if (!function_exists('WC')) {
            return 0;
        }

        $cart = WC()->cart;

        return $cart instanceof WC_Cart ? $cart->get_cart_contents_count() : 0;
    }

    private function to_persian_digits(string $value): string
    {
        return strtr($value, ['0' => '۰', '1' => '۱', '2' => '۲', '3' => '۳', '4' => '۴', '5' => '۵', '6' => '۶', '7' => '۷', '8' => '۸', '9' => '۹']);
    }

    /* ---------------------------------------------------------------------
     * رندر
     * ------------------------------------------------------------------- */

    #[\Override]
    protected function render(): void
    {
        $settings = $this->get_settings_for_display();

        $show_cart = 'yes' === $settings['show_cart'];
        $show_user = 'yes' === $settings['show_user'];
        $show_logout = 'yes' === $settings['show_logout'];

        if (!$show_cart && !$show_user && !$show_logout) {
            return;
        }

        echo '<div class="bkw-account-bar" dir="rtl">';

        if ($show_cart) {
            $this->render_cart_item($settings);
        }

        if ($show_user) {
            $this->render_user_item($settings);
        }

        if ($show_logout) {
            $this->render_logout_item($settings);
        }

        echo '</div>';
    }

    private function render_cart_item(array $settings): void
    {
        $url = !empty($settings['cart_url']['url']) ? $settings['cart_url'] : null;
        $tag = $url ? 'a' : 'div';

        $this->add_render_attribute('cart', 'class', ['bkw-account-bar__item', 'bkw-account-bar__cart']);
        if ($url) {
            $this->add_link_attributes('cart', $url);
        }

        $show_badge = 'yes' === ($settings['show_cart_badge'] ?? 'yes');
        $count = $this->cart_count();
        $render_badge = $show_badge && ($count > 0 || 'yes' === $settings['cart_badge_show_zero']);

        printf('<%1$s %2$s>', esc_html($tag), $this->get_render_attribute_string('cart')); // phpcs:ignore WordPress.Security.EscapeOutput -- render attributes are Elementor-escaped

        // ترتیب DOM عمداً برعکس فیگماست: زیر dir="rtl"، اولین فرزند سمت
        // راست می‌نشیند؛ چون در رفرنس آیکون سمت راست بج است، آیکون باید
        // اول بیاید (رجوع کن به یادداشت بالای کلاس).
        echo '<span class="bkw-account-bar__icon">';
        $this->render_bundled_icon($settings, 'cart_icon', 'assets/icons/cart.svg');
        echo '</span>';

        if ($render_badge) {
            printf('<span class="bkw-account-bar__badge">%s</span>', esc_html($this->to_persian_digits((string) $count)));
        }

        printf('</%s>', esc_html($tag));
    }

    private function render_user_item(array $settings): void
    {
        $user = wp_get_current_user();
        $fallback_name = (string) $settings['guest_name_fallback'];
        $name = $this->resolve_display_name($user, '' !== trim($fallback_name) ? $fallback_name : __('کاربر مهمان', 'bakery-widgets'));

        $show_balance = 'yes' === ($settings['show_balance'] ?? 'yes');

        echo '<div class="bkw-account-bar__item bkw-account-bar__user">';

        printf('<span class="bkw-account-bar__name">%s</span>', esc_html($name));

        if ($show_balance) {
            $balance = $this->resolve_balance((float) $settings['balance_fallback'], $user->ID);
            $amount = $this->to_persian_digits(number_format($balance, 0, '.', ','));

            printf('<span class="bkw-account-bar__separator">%s</span>', esc_html((string) $settings['separator_text']));

            echo '<span class="bkw-account-bar__balance">';
            printf('<span class="bkw-account-bar__balance-label">%s</span>', esc_html((string) $settings['balance_label']));
            printf('<span class="bkw-account-bar__balance-amount">%s</span>', esc_html($amount));
            printf('<span class="bkw-account-bar__balance-currency">%s</span>', esc_html((string) $settings['balance_currency']));
            echo '</span>';
        }

        echo '</div>';
    }

    private function render_logout_item(array $settings): void
    {
        $redirect = !empty($settings['logout_redirect_url']['url']) ? (string) $settings['logout_redirect_url']['url'] : home_url('/');
        $logout_url = wp_logout_url($redirect);

        printf(
            '<a class="bkw-account-bar__item bkw-account-bar__logout" href="%s">',
            esc_url($logout_url),
        );

        echo '<span class="bkw-account-bar__icon">';
        $this->render_bundled_icon($settings, 'logout_icon', 'assets/icons/logout.svg');
        echo '</span>';

        echo '</a>';
    }

    /**
     * آیکون همراه ویجت (سبد/خروج): تصویر انتخابی کاربر اگر SVG معتبر یا
     * تصویر معمولی باشد؛ در غیر این‌صورت (یعنی کنترل هنوز روی مقدار
     * پیش‌فرض خودش است) همان فایل SVG داخل افزونه مستقیم خوانده و
     * پاک‌سازی می‌شود — بدون درخواست شبکه به URL پیش‌فرض کنترل.
     */
    private function render_bundled_icon(array $settings, string $key, string $bundled_relative_path): void
    {
        $image = is_array($settings[$key] ?? null) ? $settings[$key] : [];
        $id = !empty($image['id']) ? (int) $image['id'] : 0;
        $url = (string) ($image['url'] ?? '');

        if ($id > 0) {
            $svg = Svg::from_attachment($id);
            if ('' !== $svg) {
                echo $svg; // phpcs:ignore WordPress.Security.EscapeOutput -- sanitized by Svg::sanitize()
                return;
            }

            if ('' !== $url) {
                printf('<img src="%s" alt="">', esc_url($url));
                return;
            }
        }

        $path = BAKERY_WIDGETS_PATH . $bundled_relative_path;
        $svg = is_readable($path) ? Svg::sanitize((string) file_get_contents($path)) : '';

        if ('' !== $svg) {
            echo $svg; // phpcs:ignore WordPress.Security.EscapeOutput -- sanitized by Svg::sanitize()
        }
    }
}
