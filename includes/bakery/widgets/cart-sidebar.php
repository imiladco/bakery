<?php

declare(strict_types=1);

namespace Bakery_Widgets\Widgets;

use Bakery_Widgets\Cart_Fragments;
use Bakery_Widgets\Svg;
use Elementor\Controls_Manager;
use Elementor\Group_Control_Background;
use Elementor\Group_Control_Border;
use Elementor\Group_Control_Typography;
use Elementor\Plugin as ElementorPlugin;
use Elementor\Widget_Base;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * ویجت «سایدبار سبد خرید» — پردهٔ تیرهٔ بلورشده + پنل کشویی تمام‌ارتفاع
 * روی کل سایت، دقیقاً مطابق رفرنس فیگما.
 *
 * این ویجت خودش هیچ دکمه‌ای برای «باز کردن» ندارد؛ باز شدنش کاملاً جدا
 * و بدون کوپل‌شدن به ویجت Header مدیریت می‌شود: assets/js/bakery-cart-sidebar.js
 * روی کل صفحه منتظر کلیک روی `.bkw-account-bar__cart` (همان پیل سبدِ
 * Traits\Account_Actions_Controls، مشترک بین Header و Account_Bar) است؛
 * اگر این سایدبار در صفحه حضور داشته باشد، آن کلیک را می‌گیرد (بدون
 * ناوبری به cart_url) و پنل را باز می‌کند؛ وگرنه لینک عادی کار می‌کند.
 * یعنی هیچ‌کدام از آن دو ویجت از وجود این‌یکی خبر ندارند.
 *
 * محتوای زندهٔ سبد (فهرست ردیف‌ها + جمع کل) در Cart_Fragments تعریف
 * شده — همان کلاسی که به فیلتر `woocommerce_add_to_cart_fragments`
 * ووکامرس هم قلاب شده؛ یعنی بعد از هر افزودن/افزایش/کاهش (چه از همین
 * سایدبار، چه از ویجت مستقل افزودن به سبد در جای دیگر صفحه) این پنل هم
 * خودش را به‌روز می‌کند، بدون کد اضافه.
 *
 * اعتبار («باقی‌مانده اعتبار شما») هنوز به منبع واقعی وصل نیست — از
 * همان فیلتر `bkw_account_balance` می‌آید که ویجت Account_Bar/Header هم
 * استفاده می‌کند (رجوع کن به Traits\Account_Actions_Controls::resolve_balance)،
 * با همان مقدار فرضی پیش‌فرض ۱۰,۰۰۰,۰۰۰ — یک عدد فرضی، یک منبع.
 *
 * دکمهٔ «ثبت سفارش»: فعلاً فقط ظاهر (بدون submit واقعی)؛ ساختار و منطق
 * نهایی‌اش طبق گفتهٔ کارفرما بعداً اضافه می‌شود.
 */
final class Cart_Sidebar extends Widget_Base
{
    #[\Override]
    public function get_name(): string
    {
        return 'bakery-cart-sidebar';
    }

    #[\Override]
    public function get_title(): string
    {
        return __('سایدبار سبد خرید بیکری عظام', 'bakery-widgets');
    }

    #[\Override]
    public function get_icon(): string
    {
        return 'eicon-cart-medium';
    }

    #[\Override]
    public function get_categories(): array
    {
        return ['bakery'];
    }

    #[\Override]
    public function get_keywords(): array
    {
        return ['سبد خرید', 'سایدبار', 'cart', 'sidebar', 'drawer', 'woocommerce', 'بیکری', 'عظام'];
    }

    #[\Override]
    public function get_style_depends(): array
    {
        return ['bakery-widgets'];
    }

    #[\Override]
    public function get_script_depends(): array
    {
        return ['bakery-cart-sidebar'];
    }

    /* ---------------------------------------------------------------------
     * کنترل‌ها
     * ------------------------------------------------------------------- */

    #[\Override]
    protected function register_controls(): void
    {
        $this->register_content_controls();

        $this->register_overlay_style_controls();
        $this->register_panel_style_controls();
        $this->register_header_style_controls();
        $this->register_credit_style_controls();
        $this->register_items_style_controls();
        $this->register_footer_style_controls();
        $this->register_loading_style_controls();
    }

    /* =====================================================================
     * محتوا
     * =================================================================== */

    private function register_content_controls(): void
    {
        $this->start_controls_section('section_content', [
            'label' => __('محتوا', 'bakery-widgets'),
            'tab' => Controls_Manager::TAB_CONTENT,
        ]);

        $this->add_control('title_text', [
            'label' => __('عنوان', 'bakery-widgets'),
            'type' => Controls_Manager::TEXT,
            'default' => __('سبد خرید', 'bakery-widgets'),
            'label_block' => true,
        ]);

        $this->add_control('close_icon', [
            'label' => __('آیکون بستن', 'bakery-widgets'),
            'type' => Controls_Manager::MEDIA,
            'media_types' => ['image', 'svg'],
            'default' => ['url' => BAKERY_WIDGETS_URL . 'assets/icons/x-circle.svg'],
        ]);

        $this->add_control('heading_credit', [
            'label' => __('اعتبار', 'bakery-widgets'),
            'type' => Controls_Manager::HEADING,
            'separator' => 'before',
        ]);

        $this->add_control('credit_label', [
            'label' => __('برچسب اعتبار', 'bakery-widgets'),
            'type' => Controls_Manager::TEXT,
            'default' => __('باقی‌مانده اعتبار شما', 'bakery-widgets'),
            'label_block' => true,
        ]);

        /*
         * اعتبار هنوز به هیچ منبع واقعی وصل نیست — این عدد فقط ورودیِ
         * فالبک فیلتر `bkw_account_balance` است، همان فیلتری که ویجت
         * Account_Bar/Header هم استفاده می‌کند (یک منبع فرضی مشترک،
         * نه یک عدد جدا برای هر ویجت). وقتی منبع واقعی وصل شود، همین‌جا
         * می‌ماند و فقط دیگر استفاده نمی‌شود.
         */
        $this->add_control('credit_fallback', [
            'label' => __('عدد فرضی (تا وصل‌شدن منبع واقعی)', 'bakery-widgets'),
            'type' => Controls_Manager::NUMBER,
            'default' => 10000000,
            'min' => 0,
        ]);

        $this->add_control('heading_footer', [
            'label' => __('پانوشت', 'bakery-widgets'),
            'type' => Controls_Manager::HEADING,
            'separator' => 'before',
        ]);

        $this->add_control('total_label', [
            'label' => __('برچسب جمع کل', 'bakery-widgets'),
            'type' => Controls_Manager::TEXT,
            'default' => __('جمع کل این سفارش', 'bakery-widgets'),
            'label_block' => true,
        ]);

        $this->add_control('checkout_text', [
            'label' => __('متن دکمهٔ ثبت سفارش', 'bakery-widgets'),
            'type' => Controls_Manager::TEXT,
            'default' => __('ثبت سفارش', 'bakery-widgets'),
            'label_block' => true,
            'description' => __('فعلاً فقط ظاهری است؛ ساختار و منطق نهایی این دکمه جداگانه اضافه می‌شود.', 'bakery-widgets'),
        ]);

        $this->end_controls_section();
    }

    /* =====================================================================
     * استایل — پرده
     * =================================================================== */

    private function register_overlay_style_controls(): void
    {
        $this->start_controls_section('section_style_overlay', [
            'label' => __('پرده', 'bakery-widgets'),
            'tab' => Controls_Manager::TAB_STYLE,
        ]);

        $this->add_control('overlay_color', [
            'label' => __('رنگ پرده', 'bakery-widgets'),
            'type' => Controls_Manager::COLOR,
            'default' => 'rgba(26, 19, 14, 0.8)',
            'selectors' => [
                '{{WRAPPER}} .bkw-cart-sidebar__overlay' => 'background-color: {{VALUE}};',
            ],
        ]);

        $this->add_responsive_control('overlay_blur', [
            'label' => __('میزان بلور', 'bakery-widgets'),
            'type' => Controls_Manager::SLIDER,
            'size_units' => ['px'],
            'range' => ['px' => ['min' => 0, 'max' => 30]],
            'default' => ['unit' => 'px', 'size' => 8],
            'selectors' => [
                '{{WRAPPER}} .bkw-cart-sidebar' => '--bkw-cart-sidebar-blur: {{SIZE}}{{UNIT}};',
            ],
        ]);

        $this->end_controls_section();
    }

    /* =====================================================================
     * استایل — پنل
     * =================================================================== */

    private function register_panel_style_controls(): void
    {
        $this->start_controls_section('section_style_panel', [
            'label' => __('پنل', 'bakery-widgets'),
            'tab' => Controls_Manager::TAB_STYLE,
        ]);

        $this->add_control('panel_side', [
            'label' => __('جهت باز شدن', 'bakery-widgets'),
            'type' => Controls_Manager::CHOOSE,
            'options' => [
                'right' => ['title' => __('از راست', 'bakery-widgets'), 'icon' => 'eicon-h-align-right'],
                'left' => ['title' => __('از چپ', 'bakery-widgets'), 'icon' => 'eicon-h-align-left'],
            ],
            'default' => 'left',
            'selectors_dictionary' => [
                'right' => 'right: 0; left: auto; --bkw-cart-sidebar-closed-x: 100%; border-left: var(--bkw-cart-sidebar-border-width, 1px) solid var(--bkw-cart-sidebar-border-color, #eaded6); border-right: none;',
                'left' => 'left: 0; right: auto; --bkw-cart-sidebar-closed-x: -100%; border-right: var(--bkw-cart-sidebar-border-width, 1px) solid var(--bkw-cart-sidebar-border-color, #eaded6); border-left: none;',
            ],
            'selectors' => [
                '{{WRAPPER}} .bkw-cart-sidebar__panel' => '{{VALUE}}',
            ],
        ]);

        $this->add_responsive_control('panel_width', [
            'label' => __('عرض', 'bakery-widgets'),
            'type' => Controls_Manager::SLIDER,
            'size_units' => ['px'],
            'range' => ['px' => ['min' => 280, 'max' => 600]],
            'default' => ['unit' => 'px', 'size' => 500],
            'selectors' => [
                // max-width عمداً 100vw است، نه یک عدد کوچک‌تر مثل 92vw: طبق
                // رفرنس فیگما موبایل، سایدبار روی صفحهٔ موبایل باید کاملاً
                // تمام‌عرض (لبه‌به‌لبه) باشد، نه یک نوار با فاصلهٔ کناری قابل‌مشاهده.
                '{{WRAPPER}} .bkw-cart-sidebar__panel' => 'width: {{SIZE}}{{UNIT}}; max-width: 100vw;',
            ],
        ]);

        $this->add_responsive_control('panel_padding', [
            'label' => __('پدینگ', 'bakery-widgets'),
            'type' => Controls_Manager::DIMENSIONS,
            'size_units' => ['px', 'em'],
            'default' => ['top' => '24', 'right' => '24', 'bottom' => '24', 'left' => '24', 'unit' => 'px', 'isLinked' => true],
            'selectors' => [
                '{{WRAPPER}} .bkw-cart-sidebar__panel' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
            ],
        ]);

        $this->add_control('panel_bg_color', [
            'label' => __('رنگ پس‌زمینه', 'bakery-widgets'),
            'type' => Controls_Manager::COLOR,
            'default' => '#efe6d5',
            'selectors' => [
                '{{WRAPPER}} .bkw-cart-sidebar__panel' => 'background-color: {{VALUE}};',
            ],
        ]);

        $this->add_control('panel_border_color', [
            'label' => __('رنگ حاشیه', 'bakery-widgets'),
            'type' => Controls_Manager::COLOR,
            'default' => '#eaded6',
            'selectors' => [
                '{{WRAPPER}} .bkw-cart-sidebar__panel' => '--bkw-cart-sidebar-border-color: {{VALUE}};',
            ],
        ]);

        $this->add_control('panel_gap', [
            'label' => __('فاصلهٔ محتوا تا پانوشت', 'bakery-widgets'),
            'type' => Controls_Manager::SLIDER,
            'size_units' => ['px'],
            'range' => ['px' => ['min' => 0, 'max' => 80]],
            'default' => ['unit' => 'px', 'size' => 32],
            'selectors' => [
                '{{WRAPPER}} .bkw-cart-sidebar__panel' => 'gap: {{SIZE}}{{UNIT}};',
            ],
        ]);

        $this->end_controls_section();
    }

    /* =====================================================================
     * استایل — سربرگ (عنوان + دکمهٔ بستن)
     * =================================================================== */

    private function register_header_style_controls(): void
    {
        $this->start_controls_section('section_style_header', [
            'label' => __('سربرگ', 'bakery-widgets'),
            'tab' => Controls_Manager::TAB_STYLE,
        ]);

        $this->add_group_control(Group_Control_Typography::get_type(), [
            'name' => 'title_typography',
            'selector' => '{{WRAPPER}} .bkw-cart-sidebar__title',
            'fields_options' => [
                'font_weight' => ['default' => '900'],
                'font_size' => ['default' => ['unit' => 'px', 'size' => 22]],
            ],
        ]);

        $this->add_control('title_color', [
            'label' => __('رنگ عنوان', 'bakery-widgets'),
            'type' => Controls_Manager::COLOR,
            'default' => '#231912',
            'selectors' => ['{{WRAPPER}} .bkw-cart-sidebar__title' => 'color: {{VALUE}};'],
        ]);

        $this->add_control('heading_close_btn', [
            'label' => __('دکمهٔ بستن', 'bakery-widgets'),
            'type' => Controls_Manager::HEADING,
            'separator' => 'before',
        ]);

        $this->add_control('close_size', [
            'label' => __('اندازهٔ دکمه', 'bakery-widgets'),
            'type' => Controls_Manager::SLIDER,
            'size_units' => ['px'],
            'range' => ['px' => ['min' => 24, 'max' => 60]],
            'default' => ['unit' => 'px', 'size' => 40],
            'selectors' => [
                '{{WRAPPER}} .bkw-cart-sidebar__close' => 'width: {{SIZE}}{{UNIT}}; height: {{SIZE}}{{UNIT}};',
            ],
        ]);

        $this->add_control('close_radius', [
            'label' => __('رادیوس', 'bakery-widgets'),
            'type' => Controls_Manager::SLIDER,
            'size_units' => ['px', '%'],
            'range' => ['px' => ['min' => 0, 'max' => 50]],
            'default' => ['unit' => 'px', 'size' => 20],
            'selectors' => [
                '{{WRAPPER}} .bkw-cart-sidebar__close' => 'border-radius: {{SIZE}}{{UNIT}};',
            ],
        ]);

        $this->add_group_control(Group_Control_Background::get_type(), [
            'name' => 'close_background',
            'types' => ['classic'],
            'selector' => '{{WRAPPER}} .bkw-cart-sidebar__close',
            'fields_options' => ['color' => ['default' => '#faf6f0']],
        ]);

        $this->add_group_control(Group_Control_Border::get_type(), [
            'name' => 'close_border',
            'selector' => '{{WRAPPER}} .bkw-cart-sidebar__close',
            'fields_options' => [
                'border' => ['default' => 'solid'],
                'width' => ['default' => ['top' => '1', 'right' => '1', 'bottom' => '1', 'left' => '1', 'unit' => 'px']],
                'color' => ['default' => '#faf6f0'],
            ],
        ]);

        $this->add_control('close_icon_size', [
            'label' => __('اندازهٔ آیکون', 'bakery-widgets'),
            'type' => Controls_Manager::SLIDER,
            'size_units' => ['px'],
            'range' => ['px' => ['min' => 12, 'max' => 40]],
            'default' => ['unit' => 'px', 'size' => 40],
            'selectors' => [
                '{{WRAPPER}} .bkw-cart-sidebar__close svg' => 'width: {{SIZE}}{{UNIT}}; height: {{SIZE}}{{UNIT}};',
            ],
        ]);

        $this->add_control('close_icon_color', [
            'label' => __('رنگ آیکون', 'bakery-widgets'),
            'type' => Controls_Manager::COLOR,
            'default' => '#2a1e17',
            'selectors' => [
                '{{WRAPPER}} .bkw-cart-sidebar__close svg [stroke]:not([stroke="none"])' => 'stroke: {{VALUE}};',
            ],
        ]);

        $this->end_controls_section();
    }

    /* =====================================================================
     * استایل — اعتبار
     * =================================================================== */

    private function register_credit_style_controls(): void
    {
        $this->start_controls_section('section_style_credit', [
            'label' => __('اعتبار', 'bakery-widgets'),
            'tab' => Controls_Manager::TAB_STYLE,
        ]);

        $this->add_group_control(Group_Control_Typography::get_type(), [
            'name' => 'credit_value_typography',
            'label' => __('تایپوگرافی مقدار', 'bakery-widgets'),
            'selector' => '{{WRAPPER}} .bkw-cart-sidebar__credit-value',
            'fields_options' => [
                'font_weight' => ['default' => '800'],
                'font_size' => ['default' => ['unit' => 'px', 'size' => 20]],
            ],
        ]);

        $this->add_group_control(Group_Control_Typography::get_type(), [
            'name' => 'credit_label_typography',
            'label' => __('تایپوگرافی برچسب', 'bakery-widgets'),
            'selector' => '{{WRAPPER}} .bkw-cart-sidebar__credit-label',
            'fields_options' => [
                'font_weight' => ['default' => '600'],
                'font_size' => ['default' => ['unit' => 'px', 'size' => 16]],
            ],
        ]);

        $this->add_control('credit_color', [
            'label' => __('رنگ متن', 'bakery-widgets'),
            'type' => Controls_Manager::COLOR,
            'default' => '#7d7065',
            'selectors' => [
                '{{WRAPPER}} .bkw-cart-sidebar__credit' => 'color: {{VALUE}};',
            ],
        ]);

        $this->add_control('heading_divider', [
            'label' => __('خط جداکننده', 'bakery-widgets'),
            'type' => Controls_Manager::HEADING,
            'separator' => 'before',
        ]);

        $this->add_control('divider_color', [
            'label' => __('رنگ', 'bakery-widgets'),
            'type' => Controls_Manager::COLOR,
            'default' => 'rgba(195, 171, 155, 0.5)',
            'selectors' => [
                '{{WRAPPER}} .bkw-cart-sidebar__divider' => 'border-color: {{VALUE}};',
            ],
        ]);

        $this->end_controls_section();
    }

    /* =====================================================================
     * استایل — فهرست اقلام
     * =================================================================== */

    private function register_items_style_controls(): void
    {
        $this->start_controls_section('section_style_items', [
            'label' => __('اقلام سبد', 'bakery-widgets'),
            'tab' => Controls_Manager::TAB_STYLE,
        ]);

        $this->add_responsive_control('items_gap', [
            'label' => __('فاصله بین ردیف‌ها', 'bakery-widgets'),
            'type' => Controls_Manager::SLIDER,
            'size_units' => ['px'],
            'range' => ['px' => ['min' => 0, 'max' => 40]],
            'default' => ['unit' => 'px', 'size' => 16],
            'selectors' => [
                '{{WRAPPER}} .bkw-cart-sidebar__items' => 'gap: {{SIZE}}{{UNIT}};',
            ],
        ]);

        $this->add_control('heading_item_name', [
            'label' => __('نام محصول', 'bakery-widgets'),
            'type' => Controls_Manager::HEADING,
        ]);

        $this->add_group_control(Group_Control_Typography::get_type(), [
            'name' => 'item_name_typography',
            'selector' => '{{WRAPPER}} .bkw-cart-sidebar__item-name',
            'fields_options' => [
                'font_weight' => ['default' => '700'],
                // رفرنس فیگما موبایل نام محصول را ۱۸px می‌خواهد (نه ۱۶px دسکتاپ) —
                // font_size داخل گروه تایپوگرافی خودش ریسپانسیو است، پس همین‌جا
                // مقدار پیش‌فرض موبایل جدا تنظیم می‌شود.
                'font_size' => [
                    'default' => ['unit' => 'px', 'size' => 16],
                    'mobile_default' => ['unit' => 'px', 'size' => 18],
                ],
            ],
        ]);

        $this->add_control('item_name_color', [
            'label' => __('رنگ', 'bakery-widgets'),
            'type' => Controls_Manager::COLOR,
            'default' => '#231912',
            'selectors' => ['{{WRAPPER}} .bkw-cart-sidebar__item-name' => 'color: {{VALUE}};'],
        ]);

        $this->add_control('heading_item_price', [
            'label' => __('قیمت واحد', 'bakery-widgets'),
            'type' => Controls_Manager::HEADING,
            'separator' => 'before',
        ]);

        $this->add_group_control(Group_Control_Typography::get_type(), [
            'name' => 'item_price_typography',
            'selector' => '{{WRAPPER}} .bkw-cart-sidebar__item-price',
            'fields_options' => [
                'font_weight' => ['default' => '500'],
                'font_size' => ['default' => ['unit' => 'px', 'size' => 14]],
            ],
        ]);

        $this->add_control('item_price_color', [
            'label' => __('رنگ', 'bakery-widgets'),
            'type' => Controls_Manager::COLOR,
            'default' => '#7d7065',
            'selectors' => ['{{WRAPPER}} .bkw-cart-sidebar__item-price' => 'color: {{VALUE}};'],
        ]);

        $this->add_control('heading_item_icon', [
            'label' => __('تصویر محصول', 'bakery-widgets'),
            'type' => Controls_Manager::HEADING,
            'separator' => 'before',
        ]);

        $this->add_control('item_icon_size', [
            'label' => __('اندازه', 'bakery-widgets'),
            'type' => Controls_Manager::SLIDER,
            'size_units' => ['px'],
            'range' => ['px' => ['min' => 32, 'max' => 96]],
            'default' => ['unit' => 'px', 'size' => 64],
            'selectors' => [
                '{{WRAPPER}} .bkw-cart-sidebar__item-icon' => 'width: {{SIZE}}{{UNIT}}; height: {{SIZE}}{{UNIT}};',
            ],
        ]);

        $this->add_control('item_icon_radius', [
            'label' => __('رادیوس', 'bakery-widgets'),
            'type' => Controls_Manager::SLIDER,
            'size_units' => ['px', '%'],
            'range' => ['px' => ['min' => 0, 'max' => 48]],
            'default' => ['unit' => 'px', 'size' => 18],
            'selectors' => [
                '{{WRAPPER}} .bkw-cart-sidebar__item-icon' => 'border-radius: {{SIZE}}{{UNIT}};',
            ],
        ]);

        $this->add_control('item_icon_bg', [
            'label' => __('رنگ پس‌زمینه', 'bakery-widgets'),
            'type' => Controls_Manager::COLOR,
            'default' => '#ffffff',
            'selectors' => [
                '{{WRAPPER}} .bkw-cart-sidebar__item-icon' => 'background-color: {{VALUE}};',
            ],
        ]);

        $this->add_control('heading_qty', [
            'label' => __('کنترل تعداد', 'bakery-widgets'),
            'type' => Controls_Manager::HEADING,
            'separator' => 'before',
        ]);

        $this->add_group_control(Group_Control_Background::get_type(), [
            'name' => 'qty_background',
            'types' => ['classic'],
            'selector' => '{{WRAPPER}} .bkw-cart-sidebar__qty',
            'fields_options' => ['color' => ['default' => '#ffffff']],
        ]);

        $this->add_responsive_control('qty_radius', [
            'label' => __('رادیوس محفظه', 'bakery-widgets'),
            'type' => Controls_Manager::SLIDER,
            'size_units' => ['px'],
            'range' => ['px' => ['min' => 0, 'max' => 100]],
            // رفرنس فیگما دسکتاپ یک باکس با گوشهٔ نسبتاً گرد است (۱۶px)؛
            // رفرنس موبایل همان کنترل را کاملاً بیضی/پیل می‌خواهد (۱۰۰px).
            'default' => ['unit' => 'px', 'size' => 16],
            'mobile_default' => ['unit' => 'px', 'size' => 100],
            'selectors' => [
                '{{WRAPPER}} .bkw-cart-sidebar__qty' => 'border-radius: {{SIZE}}{{UNIT}};',
            ],
        ]);

        $this->add_responsive_control('qty_gap', [
            'label' => __('فاصلهٔ داخلی', 'bakery-widgets'),
            'type' => Controls_Manager::SLIDER,
            'size_units' => ['px'],
            'range' => ['px' => ['min' => 0, 'max' => 30]],
            'default' => ['unit' => 'px', 'size' => 12],
            'mobile_default' => ['unit' => 'px', 'size' => 16],
            'selectors' => [
                '{{WRAPPER}} .bkw-cart-sidebar__qty' => 'gap: {{SIZE}}{{UNIT}};',
            ],
        ]);

        $this->add_responsive_control('qty_padding', [
            'label' => __('پدینگ', 'bakery-widgets'),
            'type' => Controls_Manager::DIMENSIONS,
            'size_units' => ['px'],
            'default' => ['top' => '8', 'right' => '12', 'bottom' => '8', 'left' => '12', 'unit' => 'px', 'isLinked' => false],
            'mobile_default' => ['top' => '8', 'right' => '14', 'bottom' => '8', 'left' => '14', 'unit' => 'px', 'isLinked' => false],
            'selectors' => [
                '{{WRAPPER}} .bkw-cart-sidebar__qty' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
            ],
        ]);

        $this->add_control('step_icon_size', [
            'label' => __('اندازهٔ آیکون +/-', 'bakery-widgets'),
            'type' => Controls_Manager::SLIDER,
            'size_units' => ['px'],
            'range' => ['px' => ['min' => 8, 'max' => 24]],
            'default' => ['unit' => 'px', 'size' => 12],
            'selectors' => [
                '{{WRAPPER}} .bkw-cart-sidebar__step svg' => 'width: {{SIZE}}{{UNIT}}; height: {{SIZE}}{{UNIT}};',
            ],
        ]);

        $this->add_control('step_icon_color', [
            'label' => __('رنگ آیکون +/-', 'bakery-widgets'),
            'type' => Controls_Manager::COLOR,
            'default' => '#231912',
            'selectors' => [
                '{{WRAPPER}} .bkw-cart-sidebar__step' => 'color: {{VALUE}};',
            ],
        ]);

        $this->add_control('heading_step_hover', [
            'label' => __('حالت هاور', 'bakery-widgets'),
            'type' => Controls_Manager::HEADING,
            'separator' => 'before',
        ]);

        $this->add_control('step_hover_background', [
            'label' => __('رنگ پس‌زمینه', 'bakery-widgets'),
            'type' => Controls_Manager::COLOR,
            'default' => 'rgba(35, 25, 18, 0.08)',
            'selectors' => [
                '{{WRAPPER}} .bkw-cart-sidebar__step:not(:disabled):hover' => 'background-color: {{VALUE}};',
            ],
        ]);

        $this->add_control('step_hover_color', [
            'label' => __('رنگ آیکون', 'bakery-widgets'),
            'type' => Controls_Manager::COLOR,
            'selectors' => [
                '{{WRAPPER}} .bkw-cart-sidebar__step:not(:disabled):hover' => 'color: {{VALUE}};',
            ],
        ]);

        $this->add_group_control(Group_Control_Typography::get_type(), [
            'name' => 'qty_value_typography',
            'selector' => '{{WRAPPER}} .bkw-cart-sidebar__qty-value',
            'fields_options' => [
                'font_weight' => ['default' => '600'],
                'font_size' => ['default' => ['unit' => 'px', 'size' => 15]],
            ],
        ]);

        $this->add_control('qty_value_color', [
            'label' => __('رنگ عدد', 'bakery-widgets'),
            'type' => Controls_Manager::COLOR,
            'default' => '#231912',
            'selectors' => [
                '{{WRAPPER}} .bkw-cart-sidebar__qty-value' => 'color: {{VALUE}};',
            ],
        ]);

        $this->add_control('step_disabled_opacity', [
            'label' => __('شفافیت دکمه در سقف موجودی', 'bakery-widgets'),
            'type' => Controls_Manager::SLIDER,
            'range' => ['px' => ['min' => 0, 'max' => 1, 'step' => 0.05]],
            'default' => ['size' => 0.4],
            'selectors' => [
                '{{WRAPPER}} .bkw-cart-sidebar__step:disabled' => 'opacity: {{SIZE}};',
            ],
        ]);

        $this->end_controls_section();
    }

    /* =====================================================================
     * استایل — پانوشت (جمع کل + دکمهٔ ثبت سفارش)
     * =================================================================== */

    private function register_footer_style_controls(): void
    {
        $this->start_controls_section('section_style_footer', [
            'label' => __('پانوشت', 'bakery-widgets'),
            'tab' => Controls_Manager::TAB_STYLE,
        ]);

        $this->add_group_control(Group_Control_Typography::get_type(), [
            'name' => 'total_value_typography',
            'label' => __('تایپوگرافی جمع کل', 'bakery-widgets'),
            'selector' => '{{WRAPPER}} .bkw-cart-sidebar__total-value',
            'fields_options' => [
                'font_weight' => ['default' => '800'],
                'font_size' => ['default' => ['unit' => 'px', 'size' => 20]],
            ],
        ]);

        $this->add_control('total_value_color', [
            'label' => __('رنگ جمع کل', 'bakery-widgets'),
            'type' => Controls_Manager::COLOR,
            'default' => '#231912',
            'selectors' => ['{{WRAPPER}} .bkw-cart-sidebar__total-value' => 'color: {{VALUE}};'],
        ]);

        $this->add_group_control(Group_Control_Typography::get_type(), [
            'name' => 'total_label_typography',
            'label' => __('تایپوگرافی برچسب', 'bakery-widgets'),
            'selector' => '{{WRAPPER}} .bkw-cart-sidebar__total-label',
            'fields_options' => [
                'font_weight' => ['default' => '600'],
                'font_size' => ['default' => ['unit' => 'px', 'size' => 16]],
            ],
        ]);

        $this->add_control('total_label_color', [
            'label' => __('رنگ برچسب', 'bakery-widgets'),
            'type' => Controls_Manager::COLOR,
            'default' => '#7d7065',
            'selectors' => ['{{WRAPPER}} .bkw-cart-sidebar__total-label' => 'color: {{VALUE}};'],
        ]);

        $this->add_control('heading_checkout', [
            'label' => __('دکمهٔ ثبت سفارش', 'bakery-widgets'),
            'type' => Controls_Manager::HEADING,
            'separator' => 'before',
        ]);

        $this->add_group_control(Group_Control_Typography::get_type(), [
            'name' => 'checkout_typography',
            'selector' => '{{WRAPPER}} .bkw-cart-sidebar__checkout',
            'fields_options' => [
                'font_weight' => ['default' => '700'],
                'font_size' => ['default' => ['unit' => 'px', 'size' => 18]],
            ],
        ]);

        $this->add_control('checkout_color', [
            'label' => __('رنگ متن', 'bakery-widgets'),
            'type' => Controls_Manager::COLOR,
            'default' => '#ffffff',
            'selectors' => ['{{WRAPPER}} .bkw-cart-sidebar__checkout' => 'color: {{VALUE}};'],
        ]);

        $this->add_control('checkout_bg', [
            'label' => __('رنگ پس‌زمینه', 'bakery-widgets'),
            'type' => Controls_Manager::COLOR,
            'default' => '#366874',
            'selectors' => ['{{WRAPPER}} .bkw-cart-sidebar__checkout' => 'background-color: {{VALUE}};'],
        ]);

        $this->add_control('checkout_height', [
            'label' => __('ارتفاع', 'bakery-widgets'),
            'type' => Controls_Manager::SLIDER,
            'size_units' => ['px'],
            'range' => ['px' => ['min' => 40, 'max' => 80]],
            'default' => ['unit' => 'px', 'size' => 56],
            'selectors' => [
                '{{WRAPPER}} .bkw-cart-sidebar__checkout' => 'height: {{SIZE}}{{UNIT}};',
            ],
        ]);

        $this->add_control('checkout_radius', [
            'label' => __('رادیوس', 'bakery-widgets'),
            'type' => Controls_Manager::SLIDER,
            'size_units' => ['px'],
            'range' => ['px' => ['min' => 0, 'max' => 40]],
            'default' => ['unit' => 'px', 'size' => 16],
            'selectors' => [
                '{{WRAPPER}} .bkw-cart-sidebar__checkout' => 'border-radius: {{SIZE}}{{UNIT}};',
            ],
        ]);

        $this->end_controls_section();
    }

    /* =====================================================================
     * استایل — لایهٔ «در حال انجام» کنترل تعداد
     * =================================================================== */

    private function register_loading_style_controls(): void
    {
        $this->start_controls_section('section_style_loading', [
            'label' => __('لایهٔ «در حال انجام»', 'bakery-widgets'),
            'tab' => Controls_Manager::TAB_STYLE,
            'description' => __('حین ارسال درخواست افزایش/کاهش تعداد هر ردیف، این لایه روی کنترل تعداد همان ردیف نمایان می‌شود — دقیقاً همان مفهوم ویجت افزودن به سبد.', 'bakery-widgets'),
        ]);

        $this->add_control('loading_background', [
            'label' => __('رنگ لایه', 'bakery-widgets'),
            'type' => Controls_Manager::COLOR,
            'default' => 'rgba(255, 255, 255, 0.45)',
            'selectors' => ['{{WRAPPER}} .bkw-cart-sidebar' => '--bkw-cart-sidebar-loading-bg: {{VALUE}};'],
        ]);

        $this->add_responsive_control('loading_blur', [
            'label' => __('میزان بلور', 'bakery-widgets'),
            'type' => Controls_Manager::SLIDER,
            'size_units' => ['px'],
            'range' => ['px' => ['min' => 0, 'max' => 20]],
            'default' => ['unit' => 'px', 'size' => 4],
            'selectors' => ['{{WRAPPER}} .bkw-cart-sidebar' => '--bkw-cart-sidebar-loading-blur: {{SIZE}}{{UNIT}};'],
        ]);

        $this->add_control('loading_duration', [
            'label' => __('مدت زمان محو شدن (میلی‌ثانیه)', 'bakery-widgets'),
            'type' => Controls_Manager::SLIDER,
            'range' => ['px' => ['min' => 0, 'max' => 1000]],
            'default' => ['size' => 180],
            'selectors' => ['{{WRAPPER}} .bkw-cart-sidebar' => '--bkw-cart-sidebar-loading-duration: {{SIZE}}ms;'],
        ]);

        $this->end_controls_section();
    }

    /* ---------------------------------------------------------------------
     * رندر
     * ------------------------------------------------------------------- */

    #[\Override]
    protected function render(): void
    {
        if (!function_exists('wc_get_product')) {
            if (ElementorPlugin::$instance->editor->is_edit_mode()) {
                echo '<div class="bkw-notice">' . esc_html__('این ویجت به ووکامرس فعال نیاز دارد.', 'bakery-widgets') . '</div>';
            }
            return;
        }

        $settings = $this->get_settings_for_display();
        $panel_id = $this->get_id() . '-cart-sidebar';

        $credit = (float) apply_filters('bkw_account_balance', (float) $settings['credit_fallback'], get_current_user_id());

        ?>
        <div class="bkw-cart-sidebar" data-bkw-cart-sidebar dir="rtl">
            <div class="bkw-cart-sidebar__overlay" data-bkw-cart-overlay></div>

            <div id="<?php echo esc_attr($panel_id); ?>" class="bkw-cart-sidebar__panel" role="dialog" aria-modal="true" aria-label="<?php echo esc_attr($settings['title_text']); ?>" data-bkw-cart-panel>
                <div class="bkw-cart-sidebar__top">
                    <div class="bkw-cart-sidebar__header">
                        <p class="bkw-cart-sidebar__title"><?php echo esc_html($settings['title_text']); ?></p>
                        <button type="button" class="bkw-cart-sidebar__close" aria-label="<?php esc_attr_e('بستن سبد خرید', 'bakery-widgets'); ?>" data-bkw-cart-close>
                            <?php $this->render_icon_field($settings['close_icon'] ?? []); ?>
                        </button>
                    </div>

                    <div class="bkw-cart-sidebar__credit">
                        <span class="bkw-cart-sidebar__credit-label"><?php echo esc_html($settings['credit_label']); ?></span>
                        <span class="bkw-cart-sidebar__credit-value"><?php echo esc_html(Cart_Fragments::format_price($credit)); ?></span>
                    </div>

                    <div class="bkw-cart-sidebar__divider"></div>

                    <?php echo Cart_Fragments::items_html(); // phpcs:ignore WordPress.Security.EscapeOutput -- خودش هر مقدار را escape می‌کند ?>
                </div>

                <div class="bkw-cart-sidebar__footer">
                    <div class="bkw-cart-sidebar__divider"></div>

                    <div class="bkw-cart-sidebar__total">
                        <span class="bkw-cart-sidebar__total-label"><?php echo esc_html($settings['total_label']); ?></span>
                        <?php echo Cart_Fragments::total_html(); // phpcs:ignore WordPress.Security.EscapeOutput -- خودش escape می‌کند ?>
                    </div>

                    <button type="button" class="bkw-cart-sidebar__checkout" data-bkw-cart-checkout>
                        <?php echo esc_html($settings['checkout_text']); ?>
                    </button>
                </div>
            </div>
        </div>
        <?php
    }

    /** رندر یک فیلد MEDIA؛ کپی هم‌سو با Traits\Account_Actions_Controls::render_icon_field() */
    private function render_icon_field(array $image_field): void
    {
        $id = !empty($image_field['id']) ? (int) $image_field['id'] : 0;
        $url = (string) ($image_field['url'] ?? '');

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

        if ('' !== $url && str_starts_with($url, BAKERY_WIDGETS_URL)) {
            $path = BAKERY_WIDGETS_PATH . substr($url, strlen(BAKERY_WIDGETS_URL));
            $svg = is_readable($path) ? Svg::sanitize((string) file_get_contents($path)) : '';

            if ('' !== $svg) {
                echo $svg; // phpcs:ignore WordPress.Security.EscapeOutput -- sanitized by Svg::sanitize()
                return;
            }
        }

        if ('' !== $url) {
            printf('<img src="%s" alt="">', esc_url($url));
        }
    }
}
