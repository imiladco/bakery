<?php

declare(strict_types=1);

namespace Bakery_Widgets\Widgets;

use Bakery_Widgets\Cart_Fragments;
use Bakery_Widgets\Order_Cancellation;
use Bakery_Widgets\Order_Statuses;
use Bakery_Widgets\Widgets\Traits\Confirm_Modal_Controls;
use Elementor\Controls_Manager;
use Elementor\Group_Control_Background;
use Elementor\Group_Control_Border;
use Elementor\Group_Control_Box_Shadow;
use Elementor\Group_Control_Typography;
use Elementor\Plugin as ElementorPlugin;
use Elementor\Widget_Base;
use WC_Order;
use WHW\Admin\PersianCalendarFormat;
use WHW\Domain\JalaliDate;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * ویجت «سابقهٔ سفارش‌ها» — فهرست سفارش‌های خودِ کاربرِ واردشده، با
 * صفحه‌بندی و امکان لغو در مهلت مجاز.
 *
 * بخش «مراحل سفارش» (استپر وسط کارت) طبق درخواست فعلاً خالی نگه داشته
 * شده: جایش رزرو است و ارتفاعش از تب استایل تنظیم می‌شود، ولی چیزی
 * داخلش رندر نمی‌شود. وقتی وضعیت‌های واقعی پخت/بسته‌بندی/تحویل تعریف
 * شدند، فقط همین یک حفره پر می‌شود و بقیهٔ کارت دست نمی‌خورد.
 *
 * صفحه‌بندی عمداً با لینک ساده و بارگذاری دوباره است، نه AJAX: برخلاف
 * تغییر تعداد در سبد (که باید بدون پرش انجام شود چون کاربر وسط کار
 * است)، این‌جا خواندن یک فهرست است — لینک واقعی یعنی دکمهٔ back مرورگر،
 * باز کردن در تب جدید و ایندکس‌شدن همه درست کار می‌کنند.
 *
 * لغو سفارش خودش هیچ منطقی این‌جا ندارد: هم «آیا هنوز می‌شود لغو کرد»
 * و هم خودِ اکشن در Bakery_Widgets\Order_Cancellation است تا دکمه و
 * سرور دقیقاً یک قاعده را بخوانند. برگشت اعتبار هم خودکار است — تغییر
 * وضعیت به «لغوشده» قلاب ووکامرس را می‌زند و ماژول اعتبار آن‌جا سطر
 * منفی را ثبت می‌کند.
 */
final class Order_History extends Widget_Base
{
    use Confirm_Modal_Controls;

    private const PAGE_QUERY_ARG = 'bkw_orders_page';

    #[\Override]
    public function get_name(): string
    {
        return 'bakery-order-history';
    }

    #[\Override]
    public function get_title(): string
    {
        return __('سابقهٔ سفارش‌ها بیکری عظام', 'bakery-widgets');
    }

    #[\Override]
    public function get_icon(): string
    {
        return 'eicon-post-list';
    }

    #[\Override]
    public function get_categories(): array
    {
        return ['bakery'];
    }

    #[\Override]
    public function get_keywords(): array
    {
        return ['سفارش', 'سابقه', 'تاریخچه', 'لغو', 'orders', 'history', 'cancel', 'بیکری', 'عظام'];
    }

    #[\Override]
    public function get_style_depends(): array
    {
        return ['bakery-widgets'];
    }

    #[\Override]
    public function get_script_depends(): array
    {
        return ['bakery-order-history'];
    }

    /* ---------------------------------------------------------------------
     * کنترل‌ها
     * ------------------------------------------------------------------- */

    #[\Override]
    protected function register_controls(): void
    {
        $this->register_list_controls();
        $this->register_labels_controls();
        $this->register_cancel_controls();
        $this->register_confirm_modal_controls([
            'section_label' => __('مودال تأیید لغو', 'bakery-widgets'),
            'notice' => __('لغو سفارش برگشت‌پذیر نیست و اعتبار همان لحظه به کاربر برمی‌گردد، پس پیش از آن یک تأیید نهایی گرفته می‌شود. دکمهٔ «لغو سفارش» روی کارت فقط همین مودال را باز می‌کند.', 'bakery-widgets'),
            'title' => __('لغو سفارش', 'bakery-widgets'),
            'text' => __('آیا از لغو این سفارش اطمینان دارید؟', 'bakery-widgets'),
            'accept_text' => __('بله، سفارش لغو شود', 'bakery-widgets'),
            'cancel_text' => __('انصراف', 'bakery-widgets'),
        ]);
        $this->register_pagination_controls();

        $this->register_card_style_controls();
        $this->register_meta_style_controls();
        $this->register_price_style_controls();
        $this->register_stepper_style_controls();
        $this->register_cancel_style_controls();
        // رنگ پیش‌فرض دکمهٔ تأیید همان رنگ دکمهٔ «لغو سفارش» روی کارت
        // است، تا مودال ادامهٔ همان کنش دیده شود نه یک عنصر جدا.
        $this->register_confirm_modal_style_controls([
            'section_label' => __('مودال تأیید لغو', 'bakery-widgets'),
            'accept_bg' => '#ba291e',
            'accept_color' => '#ffffff',
        ]);
        $this->register_pagination_style_controls();
    }

    private function register_list_controls(): void
    {
        $this->start_controls_section('section_list', [
            'label' => __('فهرست سفارش‌ها', 'bakery-widgets'),
            'tab' => Controls_Manager::TAB_CONTENT,
        ]);

        $this->add_control('per_page', [
            'label' => __('تعداد در هر صفحه', 'bakery-widgets'),
            'type' => Controls_Manager::NUMBER,
            'default' => 5,
            'min' => 1,
            'max' => 50,
        ]);

        $this->add_control('empty_text', [
            'label' => __('متن «سفارشی نیست»', 'bakery-widgets'),
            'type' => Controls_Manager::TEXTAREA,
            'default' => __('هنوز سفارشی ثبت نکرده‌اید.', 'bakery-widgets'),
            'rows' => 2,
        ]);

        $this->add_control('guest_text', [
            'label' => __('متن برای کاربر واردنشده', 'bakery-widgets'),
            'type' => Controls_Manager::TEXTAREA,
            'default' => __('برای دیدن سابقهٔ سفارش‌ها باید وارد حساب کاربری خود شوید.', 'bakery-widgets'),
            'rows' => 2,
        ]);

        $this->end_controls_section();
    }

    private function register_labels_controls(): void
    {
        $this->start_controls_section('section_labels', [
            'label' => __('برچسب‌ها', 'bakery-widgets'),
            'tab' => Controls_Manager::TAB_CONTENT,
        ]);

        $this->add_control('order_prefix', [
            'label' => __('پیشوند شمارهٔ سفارش', 'bakery-widgets'),
            'type' => Controls_Manager::TEXT,
            'default' => __('سفارش', 'bakery-widgets'),
        ]);

        $this->add_control('items_label', [
            'label' => __('برچسب اقلام', 'bakery-widgets'),
            'type' => Controls_Manager::TEXT,
            'default' => __('اقلام سفارش', 'bakery-widgets'),
        ]);

        $this->add_control('total_label', [
            'label' => __('برچسب مبلغ', 'bakery-widgets'),
            'type' => Controls_Manager::TEXT,
            'default' => __('مبلغ کل', 'bakery-widgets'),
        ]);

        $this->add_control('currency_label', [
            'label' => __('واحد پول', 'bakery-widgets'),
            'type' => Controls_Manager::TEXT,
            'default' => __('تومان', 'bakery-widgets'),
        ]);

        $this->add_control('persian_digits', [
            'label' => __('ارقام فارسی', 'bakery-widgets'),
            'type' => Controls_Manager::SWITCHER,
            'default' => 'yes',
            'description' => __('شمارهٔ سفارش، تاریخ، مبلغ و تعداد اقلام با ارقام فارسی نوشته می‌شوند — مطابق رفرنس فیگما.', 'bakery-widgets'),
        ]);

        $this->end_controls_section();
    }

    private function register_cancel_controls(): void
    {
        $this->start_controls_section('section_cancel', [
            'label' => __('لغو سفارش', 'bakery-widgets'),
            'tab' => Controls_Manager::TAB_CONTENT,
        ]);

        $this->add_control('cancel_text', [
            'label' => __('متن دکمه', 'bakery-widgets'),
            'type' => Controls_Manager::TEXT,
            'default' => __('لغو سفارش', 'bakery-widgets'),
        ]);

        $this->add_control('cancel_note', [
            'label' => __('یادداشت زیر دکمه', 'bakery-widgets'),
            'type' => Controls_Manager::TEXTAREA,
            'default' => __('شما تا قبل ساعت ۱۰ صبح فرصت لغو سفارش دارید.', 'bakery-widgets'),
            'rows' => 2,
        ]);

        $this->add_control('cancel_cutoff_hour', [
            'label' => __('ساعت مبنای مهلت لغو', 'bakery-widgets'),
            'type' => Controls_Manager::NUMBER,
            'default' => 10,
            'min' => 0,
            'max' => 23,
            'description' => __('سفارشی که تا این ساعت ثبت شود در نوبت پخت همان روز است و تا همین ساعت قابل لغو می‌ماند؛ سفارش بعد از این ساعت به نوبت روز بعد می‌رود و مهلت لغوش همین ساعت در روز بعد است. اگر این عدد را عوض کردید، یادداشت بالا را هم دستی هماهنگ کنید.', 'bakery-widgets'),
        ]);

        $this->add_control('cancel_pending_text', [
            'label' => __('متن دکمه هنگام لغو', 'bakery-widgets'),
            'type' => Controls_Manager::TEXT,
            'default' => __('در حال لغو…', 'bakery-widgets'),
        ]);

        $this->end_controls_section();
    }

    private function register_pagination_controls(): void
    {
        $this->start_controls_section('section_pagination', [
            'label' => __('صفحه‌بندی', 'bakery-widgets'),
            'tab' => Controls_Manager::TAB_CONTENT,
        ]);

        $this->add_control('prev_text', [
            'label' => __('متن «قبلی»', 'bakery-widgets'),
            'type' => Controls_Manager::TEXT,
            'default' => __('قبلی', 'bakery-widgets'),
        ]);

        $this->add_control('next_text', [
            'label' => __('متن «بعدی»', 'bakery-widgets'),
            'type' => Controls_Manager::TEXT,
            'default' => __('بعدی', 'bakery-widgets'),
        ]);

        $this->end_controls_section();
    }

    /* =====================================================================
     * استایل — کارت
     * =================================================================== */

    private function register_card_style_controls(): void
    {
        $this->start_controls_section('section_style_card', [
            'label' => __('کارت سفارش', 'bakery-widgets'),
            'tab' => Controls_Manager::TAB_STYLE,
        ]);

        $this->add_responsive_control('list_gap', [
            'label' => __('فاصلهٔ بین کارت‌ها', 'bakery-widgets'),
            'type' => Controls_Manager::SLIDER,
            'size_units' => ['px'],
            'range' => ['px' => ['min' => 0, 'max' => 60]],
            'default' => ['unit' => 'px', 'size' => 20],
            'selectors' => ['{{WRAPPER}} .bkw-order-history' => 'gap: {{SIZE}}{{UNIT}};'],
        ]);

        $this->add_responsive_control('card_padding', [
            'label' => __('پدینگ', 'bakery-widgets'),
            'type' => Controls_Manager::DIMENSIONS,
            'size_units' => ['px'],
            'default' => ['top' => '24', 'right' => '24', 'bottom' => '24', 'left' => '24', 'unit' => 'px', 'isLinked' => true],
            'mobile_default' => ['top' => '16', 'right' => '16', 'bottom' => '16', 'left' => '16', 'unit' => 'px', 'isLinked' => true],
            'selectors' => ['{{WRAPPER}} .bkw-order-history__card' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};'],
        ]);

        $this->add_responsive_control('card_gap', [
            'label' => __('فاصلهٔ بخش‌های داخل کارت', 'bakery-widgets'),
            'type' => Controls_Manager::SLIDER,
            'size_units' => ['px'],
            'range' => ['px' => ['min' => 0, 'max' => 48]],
            'default' => ['unit' => 'px', 'size' => 20],
            'selectors' => ['{{WRAPPER}} .bkw-order-history__card' => 'gap: {{SIZE}}{{UNIT}};'],
        ]);

        $this->add_control('card_radius', [
            'label' => __('رادیوس', 'bakery-widgets'),
            'type' => Controls_Manager::SLIDER,
            'size_units' => ['px'],
            'range' => ['px' => ['min' => 0, 'max' => 60]],
            'default' => ['unit' => 'px', 'size' => 24],
            'selectors' => ['{{WRAPPER}} .bkw-order-history__card' => 'border-radius: {{SIZE}}{{UNIT}};'],
        ]);

        $this->add_group_control(Group_Control_Background::get_type(), [
            'name' => 'card_background',
            'types' => ['classic'],
            'selector' => '{{WRAPPER}} .bkw-order-history__card',
            'fields_options' => ['color' => ['default' => '#ffffff']],
        ]);

        $this->add_group_control(Group_Control_Border::get_type(), [
            'name' => 'card_border',
            'selector' => '{{WRAPPER}} .bkw-order-history__card',
            'fields_options' => [
                'border' => ['default' => 'solid'],
                'width' => ['default' => ['top' => '1', 'right' => '1', 'bottom' => '1', 'left' => '1', 'unit' => 'px']],
                'color' => ['default' => '#eaded6'],
            ],
        ]);

        $this->add_group_control(Group_Control_Box_Shadow::get_type(), [
            'name' => 'card_shadow',
            'selector' => '{{WRAPPER}} .bkw-order-history__card',
            'fields_options' => [
                'box_shadow_type' => ['default' => 'yes'],
                'box_shadow' => ['default' => [
                    'horizontal' => 0, 'vertical' => 4, 'blur' => 8, 'spread' => 0,
                    'color' => 'rgba(35, 25, 18, 0.03)',
                ]],
            ],
        ]);

        $this->add_control('divider_color', [
            'label' => __('رنگ خط جداکننده', 'bakery-widgets'),
            'type' => Controls_Manager::COLOR,
            'default' => '#eaded6',
            'selectors' => ['{{WRAPPER}} .bkw-order-history__divider' => 'background-color: {{VALUE}};'],
        ]);

        $this->end_controls_section();
    }

    /* =====================================================================
     * استایل — شمارهٔ سفارش، تاریخ، اقلام
     * =================================================================== */

    private function register_meta_style_controls(): void
    {
        $this->start_controls_section('section_style_meta', [
            'label' => __('شمارهٔ سفارش و اقلام', 'bakery-widgets'),
            'tab' => Controls_Manager::TAB_STYLE,
        ]);

        $this->add_group_control(Group_Control_Typography::get_type(), [
            'name' => 'caption_typography',
            'label' => __('تایپوگرافی برچسب‌های کوچک', 'bakery-widgets'),
            'selector' => '{{WRAPPER}} .bkw-order-history__caption',
            'fields_options' => [
                'font_size' => ['default' => ['unit' => 'px', 'size' => 13]],
                'font_weight' => ['default' => '500'],
            ],
        ]);

        $this->add_control('caption_color', [
            'label' => __('رنگ برچسب‌های کوچک', 'bakery-widgets'),
            'type' => Controls_Manager::COLOR,
            'default' => '#a8968d',
            'selectors' => ['{{WRAPPER}} .bkw-order-history__caption' => 'color: {{VALUE}};'],
        ]);

        $this->add_control('heading_number', [
            'label' => __('شمارهٔ سفارش', 'bakery-widgets'),
            'type' => Controls_Manager::HEADING,
            'separator' => 'before',
        ]);

        $this->add_group_control(Group_Control_Typography::get_type(), [
            'name' => 'number_typography',
            'selector' => '{{WRAPPER}} .bkw-order-history__number',
            'fields_options' => [
                'font_size' => ['default' => ['unit' => 'px', 'size' => 18], 'mobile_default' => ['unit' => 'px', 'size' => 16]],
                'font_weight' => ['default' => '900'],
            ],
        ]);

        $this->add_control('number_color', [
            'label' => __('رنگ کلمهٔ «سفارش»', 'bakery-widgets'),
            'type' => Controls_Manager::COLOR,
            'default' => '#231912',
            'selectors' => ['{{WRAPPER}} .bkw-order-history__number' => 'color: {{VALUE}};'],
        ]);

        $this->add_control('number_value_color', [
            'label' => __('رنگ خودِ شماره', 'bakery-widgets'),
            'type' => Controls_Manager::COLOR,
            'default' => '#8c583a',
            'selectors' => ['{{WRAPPER}} .bkw-order-history__number-value' => 'color: {{VALUE}};'],
        ]);

        $this->add_control('heading_date', [
            'label' => __('تاریخ', 'bakery-widgets'),
            'type' => Controls_Manager::HEADING,
            'separator' => 'before',
        ]);

        $this->add_group_control(Group_Control_Typography::get_type(), [
            'name' => 'date_typography',
            'selector' => '{{WRAPPER}} .bkw-order-history__date',
            'fields_options' => [
                'font_size' => ['default' => ['unit' => 'px', 'size' => 13]],
                'font_weight' => ['default' => '500'],
            ],
        ]);

        $this->add_control('date_color', [
            'label' => __('رنگ', 'bakery-widgets'),
            'type' => Controls_Manager::COLOR,
            'default' => '#a8968d',
            'selectors' => ['{{WRAPPER}} .bkw-order-history__date' => 'color: {{VALUE}};'],
        ]);

        $this->add_control('heading_items', [
            'label' => __('اقلام سفارش', 'bakery-widgets'),
            'type' => Controls_Manager::HEADING,
            'separator' => 'before',
        ]);

        $this->add_group_control(Group_Control_Typography::get_type(), [
            'name' => 'items_typography',
            'selector' => '{{WRAPPER}} .bkw-order-history__items',
            'fields_options' => [
                'font_size' => ['default' => ['unit' => 'px', 'size' => 15], 'mobile_default' => ['unit' => 'px', 'size' => 13]],
                'font_weight' => ['default' => '700'],
            ],
        ]);

        $this->add_control('items_color', [
            'label' => __('رنگ', 'bakery-widgets'),
            'type' => Controls_Manager::COLOR,
            'default' => '#615249',
            'selectors' => ['{{WRAPPER}} .bkw-order-history__items' => 'color: {{VALUE}};'],
        ]);

        $this->add_responsive_control('items_max_width', [
            'label' => __('حداکثر عرض متن اقلام', 'bakery-widgets'),
            'type' => Controls_Manager::SLIDER,
            'size_units' => ['px', '%'],
            'range' => ['px' => ['min' => 120, 'max' => 700]],
            'default' => ['unit' => 'px', 'size' => 400],
            'mobile_default' => ['unit' => '%', 'size' => 100],
            'description' => __('متن بلندتر از این با «…» کوتاه می‌شود — مطابق رفرنس فیگما.', 'bakery-widgets'),
            'selectors' => ['{{WRAPPER}} .bkw-order-history__items' => 'max-width: {{SIZE}}{{UNIT}};'],
        ]);

        $this->add_responsive_control('meta_gap', [
            'label' => __('فاصلهٔ اقلام تا شمارهٔ سفارش', 'bakery-widgets'),
            'type' => Controls_Manager::SLIDER,
            'size_units' => ['px'],
            'range' => ['px' => ['min' => 0, 'max' => 80]],
            'default' => ['unit' => 'px', 'size' => 32],
            'mobile_default' => ['unit' => 'px', 'size' => 12],
            'selectors' => ['{{WRAPPER}} .bkw-order-history__meta' => 'gap: {{SIZE}}{{UNIT}};'],
        ]);

        $this->end_controls_section();
    }

    /* =====================================================================
     * استایل — مبلغ
     * =================================================================== */

    private function register_price_style_controls(): void
    {
        $this->start_controls_section('section_style_price', [
            'label' => __('مبلغ کل', 'bakery-widgets'),
            'tab' => Controls_Manager::TAB_STYLE,
        ]);

        $this->add_group_control(Group_Control_Typography::get_type(), [
            'name' => 'amount_typography',
            'selector' => '{{WRAPPER}} .bkw-order-history__amount',
            'fields_options' => [
                'font_size' => ['default' => ['unit' => 'px', 'size' => 18], 'mobile_default' => ['unit' => 'px', 'size' => 16]],
                'font_weight' => ['default' => '900'],
            ],
        ]);

        $this->add_control('amount_color', [
            'label' => __('رنگ عدد', 'bakery-widgets'),
            'type' => Controls_Manager::COLOR,
            'default' => '#231912',
            'selectors' => ['{{WRAPPER}} .bkw-order-history__amount' => 'color: {{VALUE}};'],
        ]);

        $this->add_group_control(Group_Control_Typography::get_type(), [
            'name' => 'currency_typography',
            'label' => __('تایپوگرافی واحد پول', 'bakery-widgets'),
            'selector' => '{{WRAPPER}} .bkw-order-history__currency',
            'fields_options' => [
                'font_size' => ['default' => ['unit' => 'px', 'size' => 13]],
                'font_weight' => ['default' => '500'],
            ],
        ]);

        $this->add_control('currency_color', [
            'label' => __('رنگ واحد پول', 'bakery-widgets'),
            'type' => Controls_Manager::COLOR,
            'default' => '#c59b62',
            'selectors' => ['{{WRAPPER}} .bkw-order-history__currency' => 'color: {{VALUE}};'],
        ]);

        $this->add_responsive_control('price_width', [
            'label' => __('عرض ستون مبلغ', 'bakery-widgets'),
            'type' => Controls_Manager::SLIDER,
            'size_units' => ['px'],
            'range' => ['px' => ['min' => 80, 'max' => 260]],
            'default' => ['unit' => 'px', 'size' => 120],
            'selectors' => ['{{WRAPPER}} .bkw-order-history__price' => 'width: {{SIZE}}{{UNIT}};'],
        ]);

        $this->end_controls_section();
    }

    /* =====================================================================
     * استایل — جای خالی مراحل سفارش
     * =================================================================== */

    private function register_stepper_style_controls(): void
    {
        $this->start_controls_section('section_style_stepper', [
            'label' => __('مراحل سفارش', 'bakery-widgets'),
            'tab' => Controls_Manager::TAB_STYLE,
        ]);

        $this->add_control('stepper_notice', [
            'type' => Controls_Manager::RAW_HTML,
            'raw' => __('مرحله از وضعیت خودِ سفارش در ووکامرس خوانده می‌شود. «در حال انجام» یعنی سفارش ثبت شد، «تکمیل‌شده» یعنی تحویل داده شد، و دو وضعیت «درحال آماده سازی» و «آماده تحویل» را همین افزونه به ووکامرس اضافه کرده. سفارش لغوشده به‌جای زنجیره، دو دایرهٔ ثبت‌شده ← لغو شد را نشان می‌دهد.', 'bakery-widgets'),
            'content_classes' => 'elementor-descriptor',
        ]);

        $this->add_responsive_control('step_width', [
            'label' => __('عرض هر مرحله', 'bakery-widgets'),
            'type' => Controls_Manager::SLIDER,
            'size_units' => ['px'],
            'range' => ['px' => ['min' => 40, 'max' => 140]],
            'default' => ['unit' => 'px', 'size' => 72],
            'selectors' => ['{{WRAPPER}} .bkw-order-history__step' => 'width: {{SIZE}}{{UNIT}};'],
        ]);

        $this->add_responsive_control('step_gap', [
            'label' => __('فاصلهٔ دایره تا برچسب', 'bakery-widgets'),
            'type' => Controls_Manager::SLIDER,
            'size_units' => ['px'],
            'range' => ['px' => ['min' => 0, 'max' => 24]],
            'default' => ['unit' => 'px', 'size' => 6],
            'selectors' => ['{{WRAPPER}} .bkw-order-history__step' => 'gap: {{SIZE}}{{UNIT}};'],
        ]);

        $this->add_control('heading_step_circle', [
            'label' => __('دایره', 'bakery-widgets'),
            'type' => Controls_Manager::HEADING,
            'separator' => 'before',
        ]);

        /*
         * اندازهٔ دایره هم‌زمان تراز عمودی رابط را هم عوض می‌کند: رابط با
         * margin-top به مرکز دایره می‌چسبد و آن مرکز نصف همین اندازه
         * است. اگر جدا تنظیم می‌شدند، هر بار که ادمین دایره را بزرگ
         * می‌کرد خط از وسط دایره‌ها می‌افتاد.
         */
        $this->add_responsive_control('step_circle_size', [
            'label' => __('اندازهٔ دایره', 'bakery-widgets'),
            'type' => Controls_Manager::SLIDER,
            'size_units' => ['px'],
            'range' => ['px' => ['min' => 16, 'max' => 56]],
            'default' => ['unit' => 'px', 'size' => 24],
            'selectors' => [
                '{{WRAPPER}} .bkw-order-history__step-circle' => 'width: {{SIZE}}{{UNIT}}; height: {{SIZE}}{{UNIT}}; border-radius: calc({{SIZE}}{{UNIT}} / 2);',
                '{{WRAPPER}} .bkw-order-history__step-connector' => 'margin-top: calc(({{SIZE}}{{UNIT}} - 2px) / 2);',
            ],
        ]);

        $this->add_responsive_control('step_icon_size', [
            'label' => __('اندازهٔ آیکون', 'bakery-widgets'),
            'type' => Controls_Manager::SLIDER,
            'size_units' => ['px'],
            'range' => ['px' => ['min' => 8, 'max' => 32]],
            'default' => ['unit' => 'px', 'size' => 14],
            'selectors' => ['{{WRAPPER}} .bkw-order-history__step-circle svg' => 'width: {{SIZE}}{{UNIT}}; height: {{SIZE}}{{UNIT}};'],
        ]);

        $this->add_control('step_icon_stroke', [
            'label' => __('ضخامت خط آیکون', 'bakery-widgets'),
            'type' => Controls_Manager::SLIDER,
            'size_units' => [''],
            'range' => ['' => ['min' => 0.5, 'max' => 3, 'step' => 0.1]],
            'default' => ['size' => 1],
            'selectors' => ['{{WRAPPER}} .bkw-order-history__step-circle svg [stroke]' => 'stroke-width: {{SIZE}};'],
        ]);

        $this->add_control('heading_step_done', [
            'label' => __('مرحلهٔ طی‌شده', 'bakery-widgets'),
            'type' => Controls_Manager::HEADING,
            'separator' => 'before',
        ]);

        $this->add_control('step_done_bg', [
            'label' => __('رنگ دایره', 'bakery-widgets'),
            'type' => Controls_Manager::COLOR,
            'default' => '#8c583a',
            'selectors' => [
                '{{WRAPPER}} .bkw-order-history__step.is-reached .bkw-order-history__step-circle' => 'background-color: {{VALUE}};',
                '{{WRAPPER}} .bkw-order-history__step-connector.is-reached' => 'background-color: {{VALUE}};',
            ],
        ]);

        $this->add_control('step_done_icon', [
            'label' => __('رنگ آیکون', 'bakery-widgets'),
            'type' => Controls_Manager::COLOR,
            'default' => '#ffffff',
            'selectors' => ['{{WRAPPER}} .bkw-order-history__step.is-reached .bkw-order-history__step-circle' => 'color: {{VALUE}};'],
        ]);

        $this->add_control('heading_step_pending', [
            'label' => __('مرحلهٔ طی‌نشده', 'bakery-widgets'),
            'type' => Controls_Manager::HEADING,
            'separator' => 'before',
        ]);

        $this->add_control('step_pending_bg', [
            'label' => __('رنگ دایره', 'bakery-widgets'),
            'type' => Controls_Manager::COLOR,
            'default' => '#f0e1c5',
            'selectors' => [
                '{{WRAPPER}} .bkw-order-history__step-circle' => 'background-color: {{VALUE}};',
                '{{WRAPPER}} .bkw-order-history__step-connector' => 'background-color: {{VALUE}};',
            ],
        ]);

        $this->add_control('step_pending_icon', [
            'label' => __('رنگ آیکون', 'bakery-widgets'),
            'type' => Controls_Manager::COLOR,
            'default' => '#8c583a',
            'selectors' => ['{{WRAPPER}} .bkw-order-history__step-circle' => 'color: {{VALUE}};'],
        ]);

        $this->add_control('heading_step_cancelled', [
            'label' => __('سفارش لغوشده', 'bakery-widgets'),
            'type' => Controls_Manager::HEADING,
            'separator' => 'before',
        ]);

        $this->add_control('step_cancelled_bg', [
            'label' => __('رنگ دایره و خط', 'bakery-widgets'),
            'type' => Controls_Manager::COLOR,
            'default' => '#c30f0f',
            'selectors' => [
                '{{WRAPPER}} .bkw-order-history__step.is-cancelled .bkw-order-history__step-circle' => 'background-color: {{VALUE}};',
                '{{WRAPPER}} .bkw-order-history__step-connector.is-cancelled' => 'background-color: {{VALUE}};',
            ],
        ]);

        $this->add_control('step_cancelled_icon', [
            'label' => __('رنگ آیکون', 'bakery-widgets'),
            'type' => Controls_Manager::COLOR,
            'default' => '#ffffff',
            'selectors' => ['{{WRAPPER}} .bkw-order-history__step.is-cancelled .bkw-order-history__step-circle' => 'color: {{VALUE}};'],
        ]);

        $this->add_control('heading_step_connector', [
            'label' => __('خط رابط', 'bakery-widgets'),
            'type' => Controls_Manager::HEADING,
            'separator' => 'before',
        ]);

        $this->add_responsive_control('step_connector_width', [
            'label' => __('طول', 'bakery-widgets'),
            'type' => Controls_Manager::SLIDER,
            'size_units' => ['px'],
            'range' => ['px' => ['min' => 8, 'max' => 80]],
            'default' => ['unit' => 'px', 'size' => 32],
            'selectors' => ['{{WRAPPER}} .bkw-order-history__step-connector' => 'width: {{SIZE}}{{UNIT}};'],
        ]);

        $this->add_control('step_connector_thickness', [
            'label' => __('ضخامت', 'bakery-widgets'),
            'type' => Controls_Manager::SLIDER,
            'size_units' => ['px'],
            'range' => ['px' => ['min' => 1, 'max' => 8]],
            'default' => ['unit' => 'px', 'size' => 2],
            'selectors' => ['{{WRAPPER}} .bkw-order-history__step-connector' => 'height: {{SIZE}}{{UNIT}}; border-radius: calc({{SIZE}}{{UNIT}} / 2);'],
        ]);

        $this->add_control('heading_step_label', [
            'label' => __('برچسب', 'bakery-widgets'),
            'type' => Controls_Manager::HEADING,
            'separator' => 'before',
        ]);

        $this->add_group_control(Group_Control_Typography::get_type(), [
            'name' => 'step_label_typography',
            'selector' => '{{WRAPPER}} .bkw-order-history__step-label',
            'fields_options' => [
                'font_size' => ['default' => ['unit' => 'px', 'size' => 11]],
                'font_weight' => ['default' => '700'],
                'line_height' => ['default' => ['unit' => 'px', 'size' => 18]],
            ],
        ]);

        $this->add_control('step_label_color', [
            'label' => __('رنگ برچسب', 'bakery-widgets'),
            'type' => Controls_Manager::COLOR,
            'default' => '#615249',
            'selectors' => ['{{WRAPPER}} .bkw-order-history__step-label' => 'color: {{VALUE}};'],
        ]);

        $this->end_controls_section();
    }

    /* =====================================================================
     * استایل — لغو سفارش
     * =================================================================== */

    private function register_cancel_style_controls(): void
    {
        $this->start_controls_section('section_style_cancel', [
            'label' => __('لغو سفارش', 'bakery-widgets'),
            'tab' => Controls_Manager::TAB_STYLE,
        ]);

        $this->add_group_control(Group_Control_Typography::get_type(), [
            'name' => 'cancel_typography',
            'label' => __('تایپوگرافی دکمه', 'bakery-widgets'),
            'selector' => '{{WRAPPER}} .bkw-order-history__cancel',
            'fields_options' => [
                'font_size' => ['default' => ['unit' => 'px', 'size' => 13]],
                'font_weight' => ['default' => '700'],
            ],
        ]);

        $this->add_control('cancel_color', [
            'label' => __('رنگ متن دکمه', 'bakery-widgets'),
            'type' => Controls_Manager::COLOR,
            'default' => '#ffffff',
            'selectors' => ['{{WRAPPER}} .bkw-order-history__cancel' => 'color: {{VALUE}};'],
        ]);

        $this->add_control('cancel_bg', [
            'label' => __('رنگ پس‌زمینهٔ دکمه', 'bakery-widgets'),
            'type' => Controls_Manager::COLOR,
            'default' => '#ba291e',
            'selectors' => ['{{WRAPPER}} .bkw-order-history__cancel' => 'background-color: {{VALUE}};'],
        ]);

        $this->add_responsive_control('cancel_padding', [
            'label' => __('پدینگ دکمه', 'bakery-widgets'),
            'type' => Controls_Manager::DIMENSIONS,
            'size_units' => ['px'],
            'default' => ['top' => '10', 'right' => '16', 'bottom' => '10', 'left' => '16', 'unit' => 'px', 'isLinked' => false],
            'selectors' => ['{{WRAPPER}} .bkw-order-history__cancel' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};'],
        ]);

        $this->add_control('cancel_radius', [
            'label' => __('رادیوس دکمه', 'bakery-widgets'),
            'type' => Controls_Manager::SLIDER,
            'size_units' => ['px'],
            'range' => ['px' => ['min' => 0, 'max' => 100]],
            'default' => ['unit' => 'px', 'size' => 100],
            'selectors' => ['{{WRAPPER}} .bkw-order-history__cancel' => 'border-radius: {{SIZE}}{{UNIT}};'],
        ]);

        $this->add_control('heading_cancel_note', [
            'label' => __('یادداشت زیر دکمه', 'bakery-widgets'),
            'type' => Controls_Manager::HEADING,
            'separator' => 'before',
        ]);

        $this->add_group_control(Group_Control_Typography::get_type(), [
            'name' => 'cancel_note_typography',
            'selector' => '{{WRAPPER}} .bkw-order-history__cancel-note',
            'fields_options' => [
                'font_size' => ['default' => ['unit' => 'px', 'size' => 10]],
                'font_weight' => ['default' => '500'],
            ],
        ]);

        $this->add_control('cancel_note_color', [
            'label' => __('رنگ', 'bakery-widgets'),
            'type' => Controls_Manager::COLOR,
            'default' => '#ba291e',
            'selectors' => ['{{WRAPPER}} .bkw-order-history__cancel-note' => 'color: {{VALUE}};'],
        ]);

        $this->add_responsive_control('cancel_gap', [
            'label' => __('فاصلهٔ دکمه تا یادداشت', 'bakery-widgets'),
            'type' => Controls_Manager::SLIDER,
            'size_units' => ['px'],
            'range' => ['px' => ['min' => 0, 'max' => 30]],
            'default' => ['unit' => 'px', 'size' => 6],
            'selectors' => ['{{WRAPPER}} .bkw-order-history__cancel-section' => 'gap: {{SIZE}}{{UNIT}};'],
        ]);

        $this->end_controls_section();
    }

    /* =====================================================================
     * استایل — صفحه‌بندی
     * =================================================================== */

    private function register_pagination_style_controls(): void
    {
        $this->start_controls_section('section_style_pagination', [
            'label' => __('صفحه‌بندی', 'bakery-widgets'),
            'tab' => Controls_Manager::TAB_STYLE,
        ]);

        $this->add_group_control(Group_Control_Typography::get_type(), [
            'name' => 'pagination_typography',
            'selector' => '{{WRAPPER}} .bkw-order-history__page',
            'fields_options' => [
                'font_size' => ['default' => ['unit' => 'px', 'size' => 14]],
                'font_weight' => ['default' => '700'],
            ],
        ]);

        $this->add_responsive_control('pagination_gap', [
            'label' => __('فاصلهٔ دکمه‌ها', 'bakery-widgets'),
            'type' => Controls_Manager::SLIDER,
            'size_units' => ['px'],
            'range' => ['px' => ['min' => 0, 'max' => 30]],
            'default' => ['unit' => 'px', 'size' => 8],
            'selectors' => ['{{WRAPPER}} .bkw-order-history__pagination' => 'gap: {{SIZE}}{{UNIT}};'],
        ]);

        $this->add_responsive_control('pagination_size', [
            'label' => __('اندازهٔ دکمه‌ها', 'bakery-widgets'),
            'type' => Controls_Manager::SLIDER,
            'size_units' => ['px'],
            'range' => ['px' => ['min' => 28, 'max' => 72]],
            'default' => ['unit' => 'px', 'size' => 44],
            'selectors' => ['{{WRAPPER}} .bkw-order-history__page' => 'min-width: {{SIZE}}{{UNIT}}; height: {{SIZE}}{{UNIT}};'],
        ]);

        $this->add_control('pagination_radius', [
            'label' => __('رادیوس', 'bakery-widgets'),
            'type' => Controls_Manager::SLIDER,
            'size_units' => ['px'],
            'range' => ['px' => ['min' => 0, 'max' => 40]],
            'default' => ['unit' => 'px', 'size' => 100],
            'selectors' => ['{{WRAPPER}} .bkw-order-history__page' => 'border-radius: {{SIZE}}{{UNIT}};'],
        ]);

        $this->add_control('pagination_color', [
            'label' => __('رنگ متن', 'bakery-widgets'),
            'type' => Controls_Manager::COLOR,
            'default' => '#615249',
            'selectors' => ['{{WRAPPER}} .bkw-order-history__page' => 'color: {{VALUE}};'],
        ]);

        $this->add_control('pagination_bg', [
            'label' => __('رنگ پس‌زمینه', 'bakery-widgets'),
            'type' => Controls_Manager::COLOR,
            'default' => '#ffffff',
            'selectors' => ['{{WRAPPER}} .bkw-order-history__page' => 'background-color: {{VALUE}};'],
        ]);

        $this->add_control('pagination_active_color', [
            'label' => __('رنگ متن صفحهٔ جاری', 'bakery-widgets'),
            'type' => Controls_Manager::COLOR,
            'default' => '#ffffff',
            'selectors' => ['{{WRAPPER}} .bkw-order-history__page.is-current' => 'color: {{VALUE}};'],
        ]);

        $this->add_control('pagination_active_bg', [
            'label' => __('رنگ پس‌زمینهٔ صفحهٔ جاری', 'bakery-widgets'),
            'type' => Controls_Manager::COLOR,
            'default' => '#ba291e',
            'selectors' => ['{{WRAPPER}} .bkw-order-history__page.is-current' => 'background-color: {{VALUE}};'],
        ]);

        $this->add_group_control(Group_Control_Border::get_type(), [
            'name' => 'pagination_border',
            'selector' => '{{WRAPPER}} .bkw-order-history__page',
            'fields_options' => [
                'border' => ['default' => 'solid'],
                'width' => ['default' => ['top' => '1', 'right' => '1', 'bottom' => '1', 'left' => '1', 'unit' => 'px']],
                'color' => ['default' => '#eaded6'],
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
        if (!function_exists('wc_get_orders')) {
            if (ElementorPlugin::$instance->editor->is_edit_mode()) {
                echo '<div class="bkw-notice">' . esc_html__('این ویجت به ووکامرس فعال نیاز دارد.', 'bakery-widgets') . '</div>';
            }
            return;
        }

        $settings = $this->get_settings_for_display();

        // اکشن AJAX لغو باید همان ساعت مبنایی را ببیند که ادمین این‌جا
        // گذاشته — رجوع کن به Order_Cancellation::remember_cutoff_hour().
        Order_Cancellation::remember_cutoff_hour((int) $settings['cancel_cutoff_hour']);

        $userId = get_current_user_id();

        if ($userId <= 0) {
            printf(
                '<div class="bkw-order-history" dir="rtl"><p class="bkw-order-history__empty">%s</p></div>',
                esc_html((string) $settings['guest_text'])
            );
            return;
        }

        $per_page = max(1, (int) $settings['per_page']);
        $page = $this->current_page();

        $query = wc_get_orders([
            'customer_id' => $userId,
            'limit' => $per_page,
            'paged' => $page,
            'orderby' => 'date',
            'order' => 'DESC',
            'paginate' => true,
        ]);

        $orders = is_object($query) && isset($query->orders) ? $query->orders : [];
        $max_pages = is_object($query) && isset($query->max_num_pages) ? (int) $query->max_num_pages : 1;

        echo '<div class="bkw-order-history" dir="rtl" data-bkw-order-history>';

        if (empty($orders)) {
            printf('<p class="bkw-order-history__empty">%s</p>', esc_html((string) $settings['empty_text']));
        } else {
            foreach ($orders as $order) {
                if ($order instanceof WC_Order) {
                    $this->render_card($order, $settings, $userId);
                }
            }

            $this->render_pagination($page, $max_pages, $settings);

            /*
             * یک مودال برای کل ویجت و نه یکی به‌ازای هر کارت: مارکاپش
             * ثابت است و فقط شناسهٔ سفارش عوض می‌شود، که
             * assets/js/bakery-order-history.js موقع باز کردن رویش
             * می‌نویسد. تکرارش به‌ازای هر سفارش یعنی ده پرده و ده کارت
             * پنهان در DOM بدون هیچ سودی.
             */
            $this->render_confirm_modal($settings, [
                'hook' => 'order-cancel',
                'pending_text' => (string) $settings['cancel_pending_text'],
                'extra_html' => '<p class="bkw-order-history__confirm-error" data-bkw-order-confirm-error role="alert" hidden></p>',
            ]);
        }

        echo '</div>';
    }

    private function render_card(WC_Order $order, array $settings, int $userId): void
    {
        $cancellable = Order_Cancellation::is_cancellable($order, $userId);

        printf(
            '<div class="bkw-order-history__card" data-bkw-order-card data-order-id="%d">',
            (int) $order->get_id()
        );

        /*
         * ترتیب DOM زیر dir="rtl" برعکس ترتیب خروجی JSX فیگماست: آن فریم
         * چیدمان LTR دارد، پس ترتیبش «چپ به راست دیده‌شده» است. این‌جا
         * فرزندِ اولِ یک ردیف فلکس سمت راست می‌نشیند، پس بلوک شمارهٔ
         * سفارش باید اول بیاید تا مثل رفرنس سمت راست بنشیند و مبلغ آخر
         * بیاید تا سمت چپ بیفتد.
         */
        echo '<div class="bkw-order-history__row">';

        echo '<div class="bkw-order-history__meta">';
        $this->render_order_meta($order, $settings);
        $this->render_items_summary($order, $settings);
        echo '</div>';

        $this->render_stepper($order);

        $this->render_price($order, $settings);

        echo '</div>';

        if ($cancellable) {
            echo '<div class="bkw-order-history__divider"></div>';
            $this->render_cancel_section($settings);
        }

        echo '</div>';
    }

    /**
     * مراحل سفارش (فیگما، نودهای 289:7463 و 289:7495).
     *
     * دو چیدمان دارد و نه یکی: زنجیرهٔ خطی چهار مرحله‌ای، و حالت لغو که
     * فقط دو دایره است (ثبت شد ← لغو شد). لغو مرحله‌ای بعد از تحویل
     * نیست، یک انشعاب است — نمایشش داخل همان زنجیره یعنی جا زدنش
     * به‌عنوان «پیشرفت».
     *
     * ترتیب DOM برعکس خروجی JSX فیگماست. آن فریم چیدمان LTR دارد پس
     * ترتیبش «چپ به راست دیده‌شده» است؛ این‌جا زیر dir="rtl" فرزندِ اولِ
     * ردیف فلکس سمت راست می‌نشیند، و مرحلهٔ اول باید سمت راست باشد.
     */
    private function render_stepper(WC_Order $order): void
    {
        echo '<div class="bkw-order-history__stepper" data-bkw-order-stepper>';

        if (Order_Statuses::is_cancelled($order)) {
            $this->render_step('processing', __('سفارش ثبت شد', 'bakery-widgets'), true, false);
            $this->render_connector(false, true);
            $this->render_step('cancelled', __('لغو شد', 'bakery-widgets'), true, true);
        } else {
            $current = Order_Statuses::current_index($order);
            $index = 0;

            foreach (Order_Statuses::chain() as $status => $label) {
                // رابط پیش از هر مرحله (نه پیش از اولی) و «رسیده» بودنش
                // تابع خودِ همان مرحله است: خطی که به یک مرحلهٔ طی‌شده
                // می‌رسد پررنگ است.
                if ($index > 0) {
                    $this->render_connector($index <= $current, false);
                }

                $this->render_step($status, $label, $index <= $current, false);
                $index++;
            }
        }

        echo '</div>';
    }

    private function render_step(string $status, string $label, bool $reached, bool $cancelled): void
    {
        printf(
            '<div class="bkw-order-history__step%s%s">',
            $reached ? ' is-reached' : '',
            $cancelled ? ' is-cancelled' : ''
        );

        echo '<span class="bkw-order-history__step-circle">';
        $this->render_step_icon(Order_Statuses::icon($status));
        echo '</span>';

        printf('<span class="bkw-order-history__step-label">%s</span>', esc_html($label));

        echo '</div>';
    }

    private function render_connector(bool $reached, bool $cancelled): void
    {
        printf(
            '<span class="bkw-order-history__step-connector%s%s"></span>',
            $reached ? ' is-reached' : '',
            $cancelled ? ' is-cancelled' : ''
        );
    }

    /**
     * آیکون‌ها عیناً همان خروجی فیگما هستند؛ تنها تغییر، stroke ثابت به
     * currentColor است. بدون آن، هر آیکون به یک حالت قفل می‌شد و برای
     * دو حالتِ «طی‌شده» (سفید روی قهوه‌ای) و «طی‌نشده» (قهوه‌ای روی
     * کرم) باید دو نسخه از هر کدام نگه می‌داشتیم.
     */
    private function render_step_icon(string $name): void
    {
        $icons = [
            'clipboard' => '<path d="M9.33304 2.33302H10.4996C10.809 2.33302 11.1057 2.45595 11.3245 2.67476C11.5432 2.89356 11.6661 3.19033 11.6661 3.49978V11.6671C11.6661 11.9765 11.5432 12.2733 11.3245 12.4921C11.1057 12.7109 10.809 12.8339 10.4996 12.8339H3.50029C3.1909 12.8339 2.89419 12.7109 2.67541 12.4921C2.45664 12.2733 2.33374 11.9765 2.33374 11.6671V3.49978C2.33374 3.19033 2.45664 2.89356 2.67541 2.67476C2.89419 2.45595 3.1909 2.33302 3.50029 2.33302H4.66684M5.25011 1.16626H8.74976C9.0719 1.16626 9.33304 1.42745 9.33304 1.74964V2.9164C9.33304 3.23859 9.0719 3.49978 8.74976 3.49978H5.25011C4.92798 3.49978 4.66684 3.23859 4.66684 2.9164V1.74964C4.66684 1.42745 4.92798 1.16626 5.25011 1.16626Z" stroke="currentColor" stroke-linecap="round"/>',
            'flame' => '<path d="M8.78572 4.76316C7.64286 3.79825 6.88095 2.54386 6.5 1C5.54762 1.77193 5.07143 2.54386 5.07143 3.31579C5.07143 4.47368 5.92857 5.05263 5.92857 6.21053C5.92857 6.59439 5.77806 6.96254 5.51015 7.23397C5.24224 7.50541 4.87888 7.6579 4.5 7.6579C4.12112 7.6579 3.75776 7.50541 3.48985 7.23397C3.22194 6.96254 3.07143 6.59439 3.07143 6.21053C2.70051 6.71159 2.5 7.32103 2.5 7.94737C2.5 9.02219 2.92143 10.053 3.67157 10.813C4.42172 11.573 5.43913 12 6.5 12C7.56087 12 8.57828 11.573 9.32843 10.813C10.0786 10.053 10.5 9.02219 10.5 7.94737C10.5 6.78947 9.92857 5.72807 8.78572 4.76316Z" stroke="currentColor" stroke-linecap="round"/>',
            'truck' => '<path d="M8.16682 10.4996V3.50029C8.16682 3.1909 8.04389 2.89419 7.82508 2.67541C7.60627 2.45664 7.3095 2.33374 7.00006 2.33374H2.33302C2.02358 2.33374 1.72681 2.45664 1.508 2.67541C1.28919 2.89419 1.16626 3.1909 1.16626 3.50029V9.91632C1.16626 10.071 1.22772 10.2194 1.33713 10.3288C1.44653 10.4381 1.59492 10.4996 1.74964 10.4996H2.9164M2.9164 10.4996C2.9164 11.1439 3.43878 11.6661 4.08316 11.6661C4.72754 11.6661 5.24992 11.1439 5.24992 10.4996M2.9164 10.4996C2.9164 9.85532 3.43878 9.33304 4.08316 9.33304C4.72754 9.33304 5.24992 9.85532 5.24992 10.4996M5.24992 10.4996H8.7502M8.7502 10.4996C8.7502 11.1439 9.27258 11.6661 9.91696 11.6661C10.5613 11.6661 11.0837 11.1439 11.0837 10.4996M8.7502 10.4996C8.7502 9.85532 9.27258 9.33304 9.91696 9.33304C10.5613 9.33304 11.0837 9.85532 11.0837 10.4996M11.0837 10.4996H12.2505C12.4052 10.4996 12.5536 10.4381 12.663 10.3288C12.7724 10.2194 12.8339 10.071 12.8339 9.91632V7.78736C12.8336 7.655 12.7884 7.52664 12.7055 7.4234L10.6754 4.88615C10.6208 4.81784 10.5516 4.76266 10.4728 4.7247C10.394 4.68674 10.3078 4.66696 10.2203 4.66684H8.16682" stroke="currentColor" stroke-linecap="round"/>',
            'check' => '<path d="M11.6661 3.5L5.25011 9.9162L2.33374 6.99974" stroke="currentColor" stroke-linecap="round"/>',
            // ✕ در فیگما یک نویسهٔ متنی است، نه SVG. این‌جا کشیده شده تا
            // به وجودِ آن نویسه در فونت سایت وابسته نباشد؛ نبودنش یعنی
            // مربع خالی به‌جای علامت لغو.
            'cross' => '<path d="M4 4L10 10M10 4L4 10" stroke="currentColor" stroke-linecap="round"/>',
        ];

        printf(
            '<svg width="14" height="14" viewBox="0 0 14 14" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true" focusable="false">%s</svg>',
            $icons[$name] ?? $icons['clipboard'] // phpcs:ignore WordPress.Security.EscapeOutput -- مسیرهای ثابت داخلی، بدون ورودی کاربر
        );
    }

    private function render_order_meta(WC_Order $order, array $settings): void
    {
        $created = $order->get_date_created();

        echo '<div class="bkw-order-history__order">';

        printf(
            '<p class="bkw-order-history__number">%1$s <span class="bkw-order-history__number-value">#%2$s</span></p>',
            esc_html((string) $settings['order_prefix']),
            esc_html($this->digits((string) $order->get_order_number(), $settings))
        );

        if ($created) {
            printf(
                '<p class="bkw-order-history__date">%s</p>',
                esc_html($this->jalali_date($created->getTimestamp(), $settings))
            );
        }

        echo '</div>';
    }

    private function render_items_summary(WC_Order $order, array $settings): void
    {
        echo '<div class="bkw-order-history__items-block">';
        printf('<p class="bkw-order-history__caption">%s</p>', esc_html((string) $settings['items_label']));
        printf('<p class="bkw-order-history__items">%s</p>', esc_html($this->items_summary($order, $settings)));
        echo '</div>';
    }

    private function render_price(WC_Order $order, array $settings): void
    {
        echo '<div class="bkw-order-history__price">';
        printf('<p class="bkw-order-history__caption">%s</p>', esc_html((string) $settings['total_label']));
        echo '<p class="bkw-order-history__price-row">';
        printf(
            '<span class="bkw-order-history__amount">%s</span>',
            esc_html($this->digits(Cart_Fragments::format_amount((float) $order->get_total()), $settings))
        );
        printf('<span class="bkw-order-history__currency">%s</span>', esc_html((string) $settings['currency_label']));
        echo '</p>';
        echo '</div>';
    }

    private function render_cancel_section(array $settings): void
    {
        echo '<div class="bkw-order-history__cancel-section">';

        printf(
            '<button type="button" class="bkw-order-history__cancel" data-bkw-order-cancel data-pending-text="%1$s">%2$s</button>',
            esc_attr((string) $settings['cancel_pending_text']),
            esc_html((string) $settings['cancel_text'])
        );

        $note = trim((string) $settings['cancel_note']);
        if ('' !== $note) {
            printf('<p class="bkw-order-history__cancel-note">%s</p>', esc_html($note));
        }

        echo '</div>';
    }

    /**
     * صفحه‌بندی با لینک واقعی (نه AJAX) — رجوع کن به یادداشت بالای کلاس.
     * فقط وقتی بیش از یک صفحه هست رندر می‌شود.
     */
    private function render_pagination(int $page, int $max_pages, array $settings): void
    {
        if ($max_pages < 2) {
            return;
        }

        echo '<nav class="bkw-order-history__pagination" aria-label="' . esc_attr__('صفحه‌بندی سفارش‌ها', 'bakery-widgets') . '">';

        // «بعدی» اول می‌آید تا زیر dir="rtl" سمت راست بنشیند — همان
        // ترتیبی که در رفرنس دیده می‌شود.
        $this->render_page_link($page + 1, (string) $settings['next_text'], $page < $max_pages);

        for ($i = 1; $i <= $max_pages; $i++) {
            $label = $this->digits((string) $i, $settings);

            if ($i === $page) {
                printf('<span class="bkw-order-history__page is-current" aria-current="page">%s</span>', esc_html($label));
                continue;
            }

            $this->render_page_link($i, $label, true);
        }

        $this->render_page_link($page - 1, (string) $settings['prev_text'], $page > 1);

        echo '</nav>';
    }

    private function render_page_link(int $target, string $label, bool $enabled): void
    {
        if (!$enabled) {
            printf('<span class="bkw-order-history__page is-disabled" aria-disabled="true">%s</span>', esc_html($label));
            return;
        }

        printf(
            '<a class="bkw-order-history__page" href="%s">%s</a>',
            esc_url(add_query_arg(self::PAGE_QUERY_ARG, $target)),
            esc_html($label)
        );
    }

    /* ---------------------------------------------------------------------
     * کمکی‌ها
     * ------------------------------------------------------------------- */

    private function current_page(): int
    {
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- فقط شمارهٔ صفحه، بدون هیچ اثر جانبی
        $raw = isset($_GET[self::PAGE_QUERY_ARG]) ? absint($_GET[self::PAGE_QUERY_ARG]) : 1;

        return max(1, $raw);
    }

    /** «۴× نان بربری، ۱× دسر تیرامیسو» — دقیقاً قالب رفرنس فیگما */
    private function items_summary(WC_Order $order, array $settings): string
    {
        $parts = [];

        foreach ($order->get_items() as $item) {
            $name = trim((string) $item->get_name());

            if ('' === $name) {
                continue;
            }

            $parts[] = sprintf(
                '%s× %s',
                $this->digits((string) (int) $item->get_quantity(), $settings),
                $name
            );
        }

        return implode('، ', $parts);
    }

    private function jalali_date(int $timestamp, array $settings): string
    {
        /*
         * setTimezone و نه پارامتر سازنده: با فرمت '@timestamp' آرگومان
         * منطقهٔ زمانی بی‌صدا نادیده گرفته می‌شود و تاریخ نزدیک نیمه‌شب
         * یک روز عقب‌تر نشان داده می‌شد.
         */
        $local = (new \DateTimeImmutable('@' . $timestamp))->setTimezone(wp_timezone());
        $jalali = JalaliDate::fromGregorian($local);

        return $this->digits(
            sprintf('%04d/%02d/%02d', $jalali->year, $jalali->month, $jalali->day),
            $settings
        );
    }

    private function digits(string $value, array $settings): string
    {
        return 'yes' === ($settings['persian_digits'] ?? 'yes')
            ? PersianCalendarFormat::digits($value)
            : $value;
    }
}
